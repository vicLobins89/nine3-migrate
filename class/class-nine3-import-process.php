<?php
/**
 * Import class for migrate posts
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Start the import
 */
class Nine3_Import_Process {
	/**
	 * Helpers object.
	 *
	 * @var object
	 */
	public $helpers;

	/**
	 * Contruct - on init run import.
	 *
	 * @param array $data array of post data from form.
	 */
	public function __construct( $data ) {
		// Load in helpers.
		$this->helpers = new Nine3_Helpers();

		// Get and set json file.
		if ( ! empty( $data['import_file'] ) ) {
			$import_data = $this->helpers->return_file_data( $data['import_file'] );
		}

		// Get/set offset.
		$offset = ! empty( $_REQUEST['offset'] ) ? sanitize_title( wp_unslash( $_REQUEST['offset'] ) ) : 0;

		// Get/set post type.
		$post_type = ! empty( $data['post_type'] ) ? sanitize_title( wp_unslash( $data['post_type'] ) ) : 'post';

		// Get/set update.
		$update = ! empty( $data['update'] ) ? sanitize_title( wp_unslash( $data['update'] ) ) : 'add-new-post';

		// Is automatic.
		$automatic = ! empty( $data['automatic'] ) ? sanitize_title( wp_unslash( $data['automatic'] ) ) : false;

		// Count totals.
		$total = count( $import_data );

		// Make up the data array of keys and values we need to map later.
		$keys_array = [];
		$data_array = [];

		if ( ! $automatic ) {
			foreach ( $data as $key => $value ) {
				if ( strpos( $key, 'nine3_data' ) !== false ) {
					$value = ltrim( $value, '{' );
					$value = rtrim( $value, '}' );
					$value = str_replace( '}{', ',', $value );
					$str   = $key . '=' . $value;
					parse_str( $str, $output );
					$keys_array[] = $output['nine3_data'];
				}
			}
			foreach ( $keys_array as $keys ) {
				foreach ( $keys as $key => $value ) {
					foreach ( $value as $k => $v ) {
						$data_array[ $key ][ $k ] = $v;
					}
				}
			}
		} else {
			$data_first = $this->helpers->return_file_data( $data['import_file'], true );
			foreach ( $data_first as $key => $value ) {
				$data_array[ $key ]['key'] = $key;

				// Add 'type' key for meta.
				$default_wp_keys = [
					'ID',
					'post_date',
					'post_title',
					'post_name',
					'post_type',
					'guid',
					'post_content',
					'post_excerpt',
					'post_parent',
					'post_author',
					'featured_image',
				];
				if ( ! in_array( $key, $default_wp_keys, true ) ) {
					$data_array[ $key ]['type'] = 'plain';
				}
			}
		}

		$this->helpers->debug( 'Item ' . $offset . ' of ' . $total, 'log' );

		if ( ! empty( $import_data ) ) {
			$response = [];

			while ( $offset < $total ) {
				$response = $this->process_data( $import_data[ $offset ], $post_type, $data_array, $update );
				$response['total'] = $total;
				$this->helpers->handle_import_response( $response, $offset );
			}

			$response['total']    = $total;
			$response['complete'] = 1;
			$this->helpers->handle_import_response( $response, true );
			$this->helpers->debug( $response, 'log' );
		}
	}

	/**
	 * Process post data
	 *
	 * @param object $post_data all fetched data from original DB.
	 * @param string $post_type string for CPT.
	 * @param array  $data_array array of data keys and types for mapping.
	 * @param bool   $update whether we shoould update or insert posts.
	 */
	private function process_data( $post_data, $post_type, $data_array, $update ) {
		if ( ! is_array( $post_data ) ) {
			return;
		}

		// $this->helpers->debug( $post_data, 'log' );
		// $this->helpers->debug( $data_array, 'log' );

		// Do not want to trigger any custom save post actions when inserting new posts.
		remove_all_actions( 'save_post' );
		$all_cpts = get_post_types(
			[
				'public'   => true,
				'_builtin' => false,
			]
		);
		foreach ( $all_cpts as $cpt ) {
			remove_all_actions( 'save_post_' . $cpt );
		}

		// If update = skip, return.
		if ( $update === 'skip' ) {
			$post_name = sanitize_title( wp_unslash( $post_data[ $data_array['post_name']['key'] ] ) );
			$return    = [
				'error'   => false,
				'message' => __( 'Post skipped: ' ) . $post_name,
				'id'      => '',
			];
			$this->helpers->debug( $return['message'], 'log' );
			return $return;
		}

		// Expand out the meta keys into the post_data array.
		if ( isset( $post_data['nine3_meta'] ) ) {
			foreach ( $post_data['nine3_meta'] as $key => $value ) {
				$post_data[ $key ] = $value;
			}
			unset( $post_data['nine3_meta'] );
		}

		$start_time = microtime( true );

		// Prepare same arrays for later.
		$taxonomies = [];
		$meta_array = [
			'nine3_migrate' => gmdate( 'Ymd' ),
		];

		// Check if we need to do str_replace().
		$str_replace = isset( $data_array['str_replace'] ) ? $data_array['str_replace'] : false;

		// Loop through and prepare the post data array for insert into post.
		foreach ( $data_array as $meta_key => $value_arr ) {
			$value_key  = ! empty( $value_arr['key'] ) ? $value_arr['key'] : false;
			$value_type = ! empty( $value_arr['type'] ) ? $value_arr['type'] : false;

			// Do str replace if set.
			if ( $str_replace && $value_key ) {
				$post_data[ $value_key ] = str_replace( $str_replace['from'], $str_replace['to'], $post_data[ $value_key ] );
			}

			// Pluck taxonomies into another array.
			if ( strpos( $meta_key, 'nine3_tax_' ) !== false && $value_key ) {
				$tax_key = str_replace( 'nine3_tax_', '', $meta_key );
				$taxonomies[ $tax_key ] = $value_arr;
				unset( $data_array[ $meta_key ] );
			}

			if ( $value_key && $value_type && isset( $post_data[ $value_key ] ) ) {
				switch ( $value_type ) {
					case 'array':
						$meta_value = explode( ',', $post_data[ $value_key ] );
						break;
					case 'slug':
						$meta_value = sanitize_title( wp_unslash( $post_data[ $value_key ] ) );
						break;
					case 'date':
						$time_string = strtotime( $post_data[ $value_key ] );
						$meta_value  = gmdate( 'Ymd', intval( $time_string ) );
						break;
					case 'image_id':
					case 'file':
						$meta_value = $this->helpers->upload_image( $post_data[ $value_key ] );
						break;
					case 'image_url':
						$meta_value = $this->helpers->upload_image( $post_data[ $value_key ], 0, false, true );
						break;
					default:
						$meta_value = $post_data[ $value_key ];
				}

				$meta_array[ $meta_key ] = $meta_value;
			}
		}

		/** Get/set up WP defaults. */
		// Post Date.
		$post_date = gmdate( 'Y-m-d H:i:s' );
		if ( isset( $data_array['post_date']['key'] ) ) {
			$time_string = $post_data[ $data_array['post_date']['key'] ];
			if ( is_numeric( $time_string ) && strlen( $time_string ) >= 10 ) {
				$post_date_str = substr( $time_string, 0, 10 );
			} else {
				$post_date_str = strtotime( $time_string );
				if ( ! $post_date_str || $post_date_str < 0 ) {
					$post_date_str = substr( $post_data[ $data_array['post_date']['key'] ], 0, 10 );
				}
			}

			$post_date = date( 'Y-m-d H:i:s', intval( $post_date_str ) );
			unset( $data_array['post_date'] );
		}

		// Post Content.
		$post_content = '';
		if ( isset( $data_array['post_content']['key'] ) ) {
			// If has multiple keys.
			if ( strpos( $data_array['post_content']['key'], ',' ) ) {
				$content_keys = explode( ',', $data_array['post_content']['key'] );
				foreach ( $content_keys as $key ) {
					if ( isset( $post_data[ $key ] ) ) {
						$content = $post_data[ $key ];

						if ( ! is_array( $content ) ) {
							$img_exts = [ 'jpg', 'jpeg', 'png' ];
							$url_ext  = pathinfo( $content, PATHINFO_EXTENSION );
							if ( in_array( $url_ext, $img_exts ) ) {
								$content = '<img src="' . $content . '" alt="Content image" />';
							}
						}

						// Allow third party to filter content.
						$content = apply_filters( 'nine3_migrate_post_content', $content, $key, $post_type, $post_data );

						// If content is array, turn to string to avoid errors.
						if ( is_array( $content ) ) {
							$content = implode( "\r\n", $content );
						}

						$post_content .= $content;
					}
				}
			} else {
				// Allow third party to filter content.
				$content      = $post_data[ $data_array['post_content']['key'] ];
				$content      = apply_filters( 'nine3_migrate_post_content', $content, $data_array['post_content']['key'], $post_type, $post_data );
				$post_content = $content;
			}

			unset( $data_array['post_content']['key'] );
			$post_content = $this->helpers->cleanup_post_content( $post_content, $data_array['post_content'] );
		}

		// Post Title.
		$post_title = $post_data[ $data_array['post_title']['key'] ];

		// Post name.
		$post_name = sanitize_title( wp_unslash( $post_data[ $data_array['post_name']['key'] ] ) );

		// Post excerpt.
		$post_excerpt = isset( $data_array['post_excerpt']['key'] ) ? wp_kses_post( $post_data[ $data_array['post_excerpt']['key'] ] ) : '';

		// Post status.
		$post_status = isset( $data_array['post_status']['key'] ) ? $post_data[ $data_array['post_status']['key'] ] : 'publish';

		// Post author.
		$author_id = 1;
		if ( isset( $data_array['post_author']['key'] ) ) {
			$author    = $post_data[ $data_array['post_author']['key'] ];
			$author    = explode( ',', $author );
			$username  = isset( $author[0] ) ? $author[0] : $author;
			$email     = isset( $author[1] ) ? $author[1] : $author;
			$author_id = isset( $username ) ? username_exists( $username ) : false;
			if ( ! $author_id ) {
				$author_id = isset( $email ) ? username_exists( $email ) : false;
			}

			if ( ! $author_id ) {
				$userdata = [
					'user_login' => $username,
					'user_pass'  => wp_generate_password(),
				];

				if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
					$userdata['user_email'] = $email;
				}

				$author_id = wp_insert_user( $userdata );
			}
		}

		// Prepare data array.
		$insert_data = [
			'ID'           => 0,
			'post_author'  => $author_id,
			'post_date'    => $post_date,
			'post_content' => $post_content,
			'post_title'   => $post_title,
			'post_name'    => ! empty( $post_name ) ? $post_name : $post_title,
			'post_excerpt' => $post_excerpt,
			'post_status'  => $post_status,
			'post_type'    => $post_type,
			'meta_input'   => $meta_array,
		];

		// Check if post exists and update.
		$updated = false;
		if ( $update === 'update' ) {
			if ( ! empty( $post_name ) ) {
				$wp_post_page = get_page_by_path( $post_name, 'OBJECT', $post_type );
				if ( $wp_post_page ) {
					$insert_data['ID'] = $wp_post_page->ID;
					$updated           = true;
				}
			} else {
				$post_exists = post_exists( $post_title, '', '', $post_type );
				if ( $post_exists ) {
					$insert_data['ID'] = $post_exists;
					$updated           = true;
				}
			}
		}

		// Allow third party to alter the data before insert.
		$insert_data = apply_filters( 'nine3_migrate_before_insert_data', $insert_data );

		// If import ID is set create another filter.
		$options   = get_option( 'nine3_import' );
		$import_id = isset( $options['import_id'] ) ? $options['import_id'] : false;
		if ( $import_id ) {
			$insert_data = apply_filters( 'nine3_migrate_before_insert_data__' . $import_id, $insert_data );
		}

		// Check if post parent is set.
		$post_parent = isset( $options['import_parent'] ) ? $options['import_parent'] : false;
		if ( $post_parent ) {
			$insert_data['post_parent'] = $post_parent;
		}

		$wp_post_id = wp_insert_post( $insert_data, true );

		// Set featured image.
		if ( isset( $data_array['featured_image']['key'] ) ) {
			if ( strpos( $data_array['featured_image']['key'], ',' ) !== false ) {
				$feaured_keys = explode( ',', $data_array['featured_image']['key'] );
				foreach ( $feaured_keys as $key ) {
					if ( isset( $post_data[ $key ] ) ) {
						$this->helpers->upload_image( $post_data[ $key ], $wp_post_id, true );
					}
				}
			} elseif ( isset( $post_data[ $data_array['featured_image']['key'] ] ) ) {
				$this->helpers->upload_image( $post_data[ $data_array['featured_image']['key'] ], $wp_post_id, true );
			}
		}

		// Set taxonomy terms.
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $tax => $value ) {
				if ( isset( $post_data['nine3_tax'][ $value['key'] ] ) ) {
					$terms = preg_split( '/(,)/', $post_data['nine3_tax'][ $value['key'] ] );
					$this->helpers->set_taxonomy_terms( $terms, $tax, $wp_post_id );
				} elseif ( isset( $post_data[ $value['key'] ] ) ) {
					$terms = preg_split( '/(,)/', $post_data[ $value['key'] ] );
					$this->helpers->set_taxonomy_terms( $terms, $tax, $wp_post_id );
				}
			}
		}

		// Handle return.
		$return['id'] = $wp_post_id;

		if ( is_int( $wp_post_id ) ) {
			$return['message'] = $updated ? __( 'Updated: ', 'nine3migrate' ) : __( 'Success: ', 'nine3migrate' );
			$return['error']   = false;
		} else {
			$return['message'] = __( 'Import failed: ', 'nine3migrate' );
			$return['error']   = true;
		}

		$this->helpers->debug( $return['message'] . $return['id'], 'log' );
		$this->helpers->debug( 'Time taken: ' . ( microtime( true ) - $start_time ) . ' seconds', 'log' );

		return $return;
	}
}
