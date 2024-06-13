<?php
/**
 * Export class for migrate posts
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Start the export
 */
class Nine3_Export_Process {
	/**
	 * Helpers object.
	 *
	 * @var object
	 */
	public $helpers;

	/**
	 * Contruct - on init run export.
	 *
	 * @param array $data array of post data from form.
	 */
	public function __construct( $data ) {
		global $wpdb;

		// Load in helpers.
		$this->helpers = new Nine3_Helpers();

		// Get/set offset (this needs to be requested as it increments each time).
		$offset = ! empty( $_REQUEST['offset'] ) ? intval( sanitize_title( wp_unslash( $_REQUEST['offset'] ) ) ) : 0;

		// Get export options.
		$options       = get_option( 'nine3_export' );
		$post_type     = ! empty( $options['export_cpt'] ) ? sanitize_title( wp_unslash( $options['export_cpt'] ) ) : 'post';
		$post_status   = ! empty( $options['export_status'] ) ? sanitize_title( wp_unslash( $options['export_status'] ) ) : 'publish';
		$format        = ! empty( $options['export_format'] ) ? sanitize_title( wp_unslash( $options['export_format'] ) ) : 'json';
		$limit         = ! empty( $options['export_limit'] ) ? intval( sanitize_title( wp_unslash( $options['export_limit'] ) ) ) : false;
		$custom_offset = ! empty( $options['export_offset'] ) ? intval( sanitize_title( wp_unslash( $options['export_offset'] ) ) ) : false;
		$direction     = ! empty( $options['export_direction'] ) ? strtoupper( sanitize_title( wp_unslash( $options['export_direction'] ) ) ) : 'ASC';
		$post_ids      = ! empty( $options['export_post_ids'] ) ? $options['export_post_ids'] : false;
		$taxonomy      = ! empty( $options['export_taxonomy'] ) ? $options['export_taxonomy'] : false;
		$term_names    = ! empty( $options['export_term_names'] ) ? $options['export_term_names'] : false;
		$meta_string   = ! empty( $options['export_meta_query'] ) ? $options['export_meta_query'] : false;
		$parent        = ! empty( $options['export_parent'] ) ? $options['export_parent'] : false;

		// CPT query.
		$cpt_query = "post_type = '{$post_type}'";
		if ( $post_type === 'any' ) {
			$cpt_query = "post_type != 'any'";
		}

		// Post status query.
		$post_status_query = $post_status === 'any' ? "AND post_status != 'auto-draft' AND post_status != 'trash'" : "AND post_status = '{$post_status}'";

		// Post ID query.
		$post_id_query = '';
		if ( $post_ids ) {
			$post_id_query = "AND ID IN ($post_ids)";
		}

		// Post parent query.
		$post_parent_query = '';
		if ( $parent ) {
			$post_parent_query = "AND post_parent IN ($parent)";
		}

		// Tax & meta query.
		if ( ( $taxonomy && $term_names ) || $meta_string ) {
			$post_args = [
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'post_status'    => $post_status,
				'fields'         => 'ids',
			];

			if ( $taxonomy && $term_names ) {
				$term_names = explode( ',', $term_names );
				$post_args['tax_query'] = [
					'relation' => 'AND',
					[
						'taxonomy' => $taxonomy,
						'field'    => 'name',
						'terms'    => $term_names,
					],
				];
			}

			if ( $meta_string ) {
				$meta_array = explode( ',', $meta_string );
				$meta_query = [];

				foreach ( $meta_array as $meta_pair ) {
					$pair  = explode( ':', $meta_pair );
					$query = [
						'key'   => $pair[0],
						'value' => $pair[1],
					];
					$meta_query[] = $query;
				}
				$post_args['meta_query'] = $meta_query;
			}

			$post_ids      = get_posts( $post_args );
			$post_ids      = implode( ',', $post_ids );
			$post_id_query = "AND ID IN ($post_ids)";
		}

		// Count totals.
		$count_query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$cpt_query} {$post_status_query} {$post_id_query} {$post_parent_query}";
		$count_total = intval( $wpdb->get_var( $count_query ) );
		$total       = $limit ? $limit : $count_total; // phpcs:ignore
		if ( $custom_offset && $limit ) {
			$total = $custom_offset + $limit;
			$limit = false;
		}

		// Main query.
		$query = "SELECT DISTINCT ID
		FROM {$wpdb->posts}
		WHERE {$cpt_query} {$post_status_query} {$post_id_query} {$post_parent_query}
		ORDER BY ID {$direction}
		LIMIT {$offset}, 1";

		$result = $wpdb->get_var( $query ); // phpcs:ignore

		// Setup options array for file creation.
		$file_options = [
			'filename'  => 'export_' . $post_type . '_' . gmdate( 'Ymd' ),
			'operation' => 'start',
			'mode'      => 'w',
		];

		// Create temp PHP file.
		if (
			( $result && $custom_offset === $offset ) ||
			( $result && $offset === 0 )
		) {
			$filepath = $this->helpers->write_temp_php_file( [], $file_options );
		}

		// Close temp PHP file.
		if (
			( $limit && $offset === $limit ) ||
			( $offset === $total ) ||
			( $offset >= $count_total )
		) {
			$file_options['operation'] = 'close';
			$file_options['mode'] = 'a';
			$filepath = $this->helpers->write_temp_php_file( [], $file_options );

			// If file exists, return html for the JS to render.
			if ( file_exists( $filepath ) ) {
				$response['html'] = $this->helpers->render_download_form( $filepath, $format, true );
			}

			if ( $custom_offset ) {
				$total = $count_total;
			}
			$response['total']    = $total;
			$response['complete'] = 1;
			$this->helpers->handle_import_response( $response, true );
			$this->helpers->debug( $response, 'log' );
		}

		// Loop and add data to file array.
		while ( $result ) {
			$response = $this->process_data( $result );
			if ( isset( $response['data'] ) ) {
				// Add data to temp php array.
				$file_options['operation'] = 'add';
				$file_options['mode'] = 'a';
				$filepath = $this->helpers->write_temp_php_file( $response['data'], $file_options );
				unset( $response['data'] );
			}

			if ( $custom_offset ) {
				$total = $count_total;
			}
			$response['total'] = $total;
			$this->helpers->handle_import_response( $response, $offset );
		}
	}

	/**
	 * Process post data
	 *
	 * @param object $post WP post object to export.
	 */
	private function process_data( $post ) {
		$start_time = microtime( true );

		// Get export options.
		$options      = get_option( 'nine3_export' );
		$format       = ! empty( $options['export_format'] ) ? sanitize_title( wp_unslash( $options['export_format'] ) ) : 'json';
		$private_meta = ! empty( $options['export_private_meta'] ) ? sanitize_title( wp_unslash( $options['export_private_meta'] ) ) : 0;

		// Setup convert keys array.
		if ( ! empty( $options['export_convert_keys'] ) ) {
			$convert_keys = explode( ',', $options['export_convert_keys'] );
		}

		// Setup exclude keys array.
		if ( ! empty( $options['export_exclude_meta'] ) ) {
			$exclude_keys = explode( ',', $options['export_exclude_meta'] );
		}

		// If post is ID, get post.
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		$post_array = [
			'ID'           => $post->ID,
			'post_date'    => $post->post_date,
			'post_title'   => $post->post_title,
			'post_name'    => $post->post_name,
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'guid'         => $post->guid,
			'post_content' => '',
			'post_excerpt' => '',
			'post_parent'  => 0,
			'post_author'  => 0,
		];

		// Cleanup post content.
		if ( ! empty( $post->post_content ) ) {
			$post_content = $this->helpers->export_content_cleanup( $post->post_content );
			$post_array['post_content'] = $post_content;
		}

		// Excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$post_array['post_excerpt'] = $post->post_excerpt;
		}

		// Post parent.
		if ( $post->post_parent > 0 ) {
			$post_array['post_parent'] = get_the_permalink( $post->post_parent );
		}

		// Post author.
		$author = get_userdata( $post->post_author );
		if ( $author ) {
			$post_array['post_author'] = $author->user_login . ',' . $author->user_email;
		}

		// Featured image.
		$featured_image = get_the_post_thumbnail_url( $post->ID );
		if ( $featured_image ) {
			$post_array['featured_image'] = $featured_image;
		}

		// Taxonomies.
		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( $taxonomies ) {
			foreach ( $taxonomies as $tax ) {
				// If not json create blank value with key for headers.
				if ( $format !== 'json' ) {
					$post_array[ $tax ] = '';
				}
				$terms_object = get_the_terms( $post->ID, $tax );
				$terms_array  = [];

				if ( ! $terms_object ) {
					continue;
				}

				// Remove commas from term names and add to array.
				foreach ( $terms_object as $term ) {
					$terms_array[] = str_replace( ',', ' ', $term->name );
				}

				if ( $format === 'json' ) {
					$post_array['nine3_tax'][ $tax ] = implode( ',', $terms_array );
				} else {
					$post_array[ $tax ] = implode( ',', $terms_array );
				}
			}
		}

		// Meta data.
		$meta_keys = $this->helpers->generate_cpt_meta_keys( $post->post_type, $private_meta );
		foreach ( $meta_keys as $key ) {
			// Remove excluded meta.
			if ( isset( $exclude_keys ) ) {
				foreach ( $exclude_keys as $exclude_key ) {
					if ( strpos( $key, $exclude_key ) !== false ) {
						continue 2;
					}
				}
			}

			$value = get_post_meta( $post->ID, $key, true );

			// Retrieves URLs if ACF.
			$value = $this->helpers->check_acf_post_field( $key, $value, $post->ID );

			// Remove private ACF fields as these are useless.
			if ( is_string( $value ) && substr( $value, 0, 6 ) === 'field_' ) {
				continue;
			}

			// Retrieves URLs if keys are set.
			if ( isset( $convert_keys ) ) {
				foreach ( $convert_keys as $set_key ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $sub_key => $sub_val ) {
							if ( is_array( $sub_val ) ) {
								foreach ( $sub_val as $third_key => $third_val ) {
									if ( $third_key === $set_key ) {
										$value[ $sub_key ][ $third_key ] = $this->helpers->get_post_url( $third_val );
									}
								}
							} elseif ( $sub_key === $set_key ) {
								$value[ $sub_key ] = $this->helpers->get_post_url( $sub_val );
							}
						}
					} elseif ( $key === $set_key ) {
						$decoded = json_decode( $value );
						if ( json_last_error() === JSON_ERROR_NONE ) {
							if ( is_array( $decoded ) || is_object( $decoded ) ) {
								foreach ( $decoded as $key => &$decoded_val ) {
									$decoded_val = is_numeric( $decoded_val ) ? $this->helpers->get_post_url( $decoded_val ) : $decoded_val;
								}
								$value = wp_json_encode( $decoded );
							} else {
								$value = is_numeric( $decoded ) ? $this->helpers->get_post_url( $decoded ) : $decoded;
							}
						} else {
							$value = $this->helpers->get_post_url( $value );
						}
					}
				}
			}

			if ( $format === 'json' ) {
				$post_array['nine3_meta'][ $key ] = $value;
			} else {
				$post_array[ $key ] = $value;
			}
		}

		$return = [
			'id'      => $post->ID,
			'error'   => false,
			'message' => __( 'Export successfull: ' ),
			'data'    => $post_array,
		];

		$this->helpers->debug( $return['message'] . $return['id'], 'log' );
		$this->helpers->debug( 'Time taken: ' . ( microtime( true ) - $start_time ) . ' seconds', 'log' );

		return $return;
	}
}
