<?php
/**
 * These are helper functions and utils for the migrate class
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Class definition
 */
class Nine3_Helpers {
	/*********************************************
	 * Renderers
	 *********************************************/

	/**
	 * Render out section settings form.
	 *
	 * @param string $group the options group name.
	 * @param string $page the slug of the options page.
	 */
	public function render_settings( $group, $page ) {
		ob_start();
		?>
		<form method="post" action="options.php" enctype="multipart/form-data">
			<?php
			settings_fields( $group );
			do_settings_sections( $page );
			submit_button();
			?>
		</form>
		<?php
		ob_flush();
	}

	/**
	 * Renders the HTML for the form/button to download an export file
	 *
	 * @param string $filepath the location of the file.
	 * @param string $format file type to output.
	 * @param bool   $return whether to return or echo.
	 */
	public function render_download_form( $filepath, $format, $return = false ) {
		$download_path = NINE3_MIGRATE_URI . '/helpers/download.php';
		ob_start();
		?>
		<form method="post" action="<?php echo esc_html( $download_path ); ?>">
			<input type="hidden" name="export_filepath" value="<?php echo esc_html( $filepath ); ?>">
			<input type="hidden" name="export_format" value="<?php echo esc_html( $format ); ?>">
			<input class="button" type="submit" value="<?php echo esc_html__( 'Download', 'nine3migrate' ) . ' ' . esc_html( strtoupper( $format ) ); ?>">
		</form>
		<?php
		if ( $return ) {
			return ob_get_clean();
		} else {
			ob_flush();
		}
	}

	/**
	 * Render the export form
	 *
	 * @param array $options import options array.
	 */
	public function render_export_form( $options ) {
		$post_count     = 0;
		$form_name      = $options['post_type'];
		$post_count_obj = $form_name === 'any' ? wp_count_posts() : wp_count_posts( $form_name );

		if ( ! empty( $options['limit'] ) ) {
			$post_count = $options['limit'];
		} elseif ( isset( $options['ids'] ) ) {
			$post_count = count( $options['ids'] );
		} elseif ( ( isset( $options['term_names'] ) && isset( $options['taxonomy'] ) ) || isset( $options['meta_query'] ) ) {
			$post_args = [
				'post_type'      => $form_name,
				'posts_per_page' => -1,
				'post_status'    => $options['post_status'],
				'fields'         => 'ids',
			];

			if ( isset( $options['term_names'] ) && isset( $options['taxonomy'] ) ) {
				$post_args['tax_query'] = [
					[
						'taxonomy' => $options['taxonomy'],
						'field'    => 'name',
						'terms'    => $options['term_names'],
					],
				];
			}

			if ( isset( $options['meta_query'] ) ) {
				$post_args['meta_query'] = $options['meta_query'];
			}

			$this->debug( $post_args, 'log' );

			$post_ids   = get_posts( $post_args );
			$post_count = count( $post_ids );
		} elseif ( $options['post_status'] === 'any' ) {
			$post_count    += $post_count_obj->publish += $post_count_obj->future += $post_count_obj->draft += $post_count_obj->pending += $post_count_obj->private;
		} else {
			$post_count = $post_count_obj->{$options['post_status']};
		}

		ob_start();
		?>
		<div class="field-map__wrapper">
			<h2><?php esc_html_e( 'Run export', 'nine3migrate' ); ?></h2>
			<p><?php echo esc_html__( 'Post count: ' ) . esc_html( $post_count ); ?></p>
			<form method="get" class="nine3-migrate" name="<?php echo esc_html( $form_name ); ?>-form">
				<input type="hidden" name="offset" value="<?php echo esc_html( $options['offset'] ?? 0 ); ?>">
				<input type="hidden" name="action" value="nine3-run-export">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Run export!', 'nine3migrate' ); ?></button>
				<button type="button" class="stop-import button button-secondary"><?php esc_html_e( 'Abort export!', 'nine3migrate' ); ?></button>
			</form>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html; // phpcs:ignore
	}

	/**
	 * Render the import form
	 *
	 * @param array $options import options array.
	 * @param bool  $automatic whether the import is automatic or manual.
	 */
	public function render_import_form( $options, $automatic = false ) {
		$is_tax    = isset( $options['taxonomy'] );
		$form_name = $is_tax ? $options['taxonomy'] : $options['post_type'];
		ob_start();
		?>
		<div class="field-map__wrapper">
			<h2><?php esc_html_e( 'Run import', 'nine3migrate' ); ?></h2>
			<?php if ( $automatic ) : ?>
				<p><?php esc_html_e( 'All fields will be automatically mapped.', 'nine3migrate' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Drag and drop fields into appropriate input boxes.', 'nine3migrate' ); ?></p>
			<?php endif; ?>

			<form method="get" class="nine3-migrate" name="<?php echo esc_html( $form_name ); ?>-form">
				<?php if ( $is_tax ) : ?>
					<input type="hidden" name="taxonomy" value="<?php echo esc_html( $options['taxonomy'] ); ?>">
				<?php else : ?>
					<input type="hidden" name="post_type" value="<?php echo esc_html( $options['post_type'] ); ?>">
					<input type="hidden" name="update" value="<?php echo esc_html( $options['update'] ); ?>">
				<?php endif; ?>
				<input type="hidden" name="offset" value="<?php echo esc_html( $options['offset'] ); ?>">
				<input type="hidden" name="import_file" value="<?php echo esc_url( $options['import_file'] ); ?>">
				<input type="hidden" name="action" value="nine3-run-import">

				<?php
				if ( ! $automatic ) {
					$this->render_field_mapper( $options['post_type'], $options['import_file'] );
				} else {
					?>
					<input type="hidden" name="automatic" value="<?php echo esc_html( $automatic ); ?>">
					<?php
				}
				?>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Run import!', 'nine3migrate' ); ?></button>
				<button type="button" class="stop-import button button-secondary"><?php esc_html_e( 'Abort import!', 'nine3migrate' ); ?></button>
			</form>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html; // phpcs:ignore
	}

	/**
	 * Renders markup for manual field mapper (drag and drop)
	 *
	 * @param string $post_type needed to get meta and tax keys.
	 * @param string $file path of the data file.
	 */
	public function render_field_mapper( $post_type, $file ) {
		// First array from file (key + value).
		$data = $this->return_file_data( $file, true );

		// WP default fields.
		$default_fields = [
			'post_date'      => [
				'label' => __( 'Post Date', 'nine3migrate' ),
			],
			'post_content'   => [
				'label'    => __( 'Post Content', 'nine3migrate' ),
				'multiple' => true,
				'options'  => [
					'replace_images' => __( 'Replace Images', 'nine3migrate' ),
					'remove_divi'    => __( 'Remove Divi', 'nine3migrate' ),
					'remove_styles'  => __( 'Remove Styles', 'nine3migrate' ),
					'remove_classes' => __( 'Remove Classes', 'nine3migrate' ),
				],
			],
			'post_title'     => [
				'label' => __( 'Post Title', 'nine3migrate' ),
			],
			'post_name'      => [
				'label' => __( 'Post Name (slug)', 'nine3migrate' ),
			],
			'post_excerpt'   => [
				'label' => __( 'Post Excerpt', 'nine3migrate' ),
			],
			'post_status'    => [
				'label' => __( 'Post Status', 'nine3migrate' ),
			],
			'featured_image' => [
				'label' => __( 'Featured Image', 'nine3migrate' ),
			],
			'post_author' => [
				'label' => __( 'Post Author', 'nine3migrate' ),
			],
		];

		// Get taxonomies of CPT.
		$taxonomies = get_object_taxonomies( [ 'post_type' => $post_type ] );

		// Meta keys from current cpt.
		$meta_keys = $this->generate_cpt_meta_keys( $post_type );

		ob_start();
		?>
		<div class="field-map">
			<div class="field-map__uploaded">
				<h3><?php esc_html_e( 'Fields uploaded from JSON file', 'nine3migrate' ); ?></h3>
				<?php
				foreach ( $data as $key => $value ) :
					if ( is_array( $value ) ) {
						$value = '(JSON Array)';
					} else {
						$value = wp_trim_words( $value, 10, '...' );
					}
					?>
					<div class="field-map__field" data-key="{<?php echo esc_html( $key ); ?>}" draggable="true">
						<p class="label"><?php echo esc_html( $key ); ?></p>
						<p class="value"><?php echo wp_kses_post( $value ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="field-map__current">
				<h3><?php esc_html_e( 'Fields existing in the current install', 'nine3migrate' ); ?></h3>
				<div class="field-map__list">
					<div class="field-map__defaults">
						<h4><?php esc_html_e( 'WordPress Defaults', 'nine3migrate' ); ?></h4>
						<?php foreach ( $default_fields as $key => $value ) : ?>
							<div class="field-map__field">
								<label for="nine3_data[<?php echo esc_html( $key ); ?>][key]"><?php echo esc_html( $value['label'] ); ?></label><br>
								<?php if ( isset( $value['multiple'] ) && $value['multiple'] ) : ?>
									<textarea name="nine3_data[<?php echo esc_html( $key ); ?>][key]" cols="30" rows="5"></textarea>
								<?php else : ?>
									<input type="text" name="nine3_data[<?php echo esc_html( $key ); ?>][key]">
								<?php endif; ?>

								<?php
								if ( isset( $value['options'] ) ) {
									foreach ( $value['options'] as $opt => $label ) :
										?>
										<label for="nine3_data[<?php echo esc_html( $key ); ?>][<?php echo esc_html( $opt ); ?>]"><?php echo esc_html( $label ); ?></label>
										<input type="checkbox" name="nine3_data[<?php echo esc_html( $key ); ?>][<?php echo esc_html( $opt ); ?>]" value="true">
										<?php
									endforeach;
								}
								?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="field-map__taxonomies">
						<h4><?php esc_html_e( 'Taxonomies', 'nine3migrate' ); ?></h4>
						<?php foreach ( $taxonomies as $value ) : ?>
							<div class="field-map__field">
								<label for="nine3_data[nine3_tax_<?php echo esc_html( $value ); ?>][key]"><?php echo esc_html( $value ); ?> (taxonomy)</label><br>
								<input type="text" name="nine3_data[nine3_tax_<?php echo esc_html( $value ); ?>][key]">
							</div>
						<?php endforeach; ?>
					</div>

					<div class="field-map__meta">
						<h4><?php esc_html_e( 'Custom Meta', 'nine3migrate' ); ?></h4>
						<?php foreach ( $meta_keys as $value ) : ?>
							<div class="field-map__field">
								<label for="nine3_data[<?php echo esc_html( $value ); ?>][key]"><?php echo esc_html( $value ); ?></label><br>
								<input type="text" name="nine3_data[<?php echo esc_html( $value ); ?>][key]">
								<select name="nine3_data[<?php echo esc_html( $value ); ?>][type]">
									<option value="plain"><?php esc_html_e( 'Plain Text/HTML', 'nine3migrate' ); ?></option>
									<option value="array"><?php esc_html_e( 'Array', 'nine3migrate' ); ?></option>
									<option value="date"><?php esc_html_e( 'Date', 'nine3migrate' ); ?></option>
									<option value="slug"><?php esc_html_e( 'Slug', 'nine3migrate' ); ?></option>
									<option value="image_id"><?php esc_html_e( 'Image ID', 'nine3migrate' ); ?></option>
									<option value="image_url"><?php esc_html_e( 'Image URL', 'nine3migrate' ); ?></option>
									<option value="file"><?php esc_html_e( 'File', 'nine3migrate' ); ?></option>
								</select>
							</div>
						<?php endforeach; ?>

						<div class="field-map__field">
							<h4><?php esc_html_e( 'Add custom meta fields', 'nine3migrate' ); ?></h4>
							<label for="new_meta_field"><?php esc_html_e( 'Enter meta key and press Add Field', 'nine3migrate' ); ?></label>
							<input type="text" name="new_meta_field" id="new_meta_field">
							<a class="add_field button"><?php esc_html_e( 'Add Field', 'nine3migrate' ); ?></a>
						</div>

						<div class="field-map__field">
							<label for="nine3_data[str_replace][from]"><?php esc_html_e( 'String Replace', 'nine3migrate' ); ?></label><br>
							<input type="text" name="nine3_data[str_replace][from]">
							<input type="text" name="nine3_data[str_replace][to]">
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		echo $html; // phpcs:ignore
	}

	/**
	 * Output HTML for the progress bar
	 */
	public function render_progress_bar() {
		ob_start();
		?>
		<div class="progress-bar">
			<p><span class="progress-bar__step">0</span> of <span class="progress-bar__total">0</span></p>
			<div class="progress-bar__inner">
				<span class="progress-bar__loader"></span>
			</div>
		</div>
		<?php
		ob_get_flush();
	}

	/*********************************************
	 * Data cleanup / handling
	 *********************************************/

	/**
	 * Cleanup the content for the export
	 *
	 * @param string $content the string of post_content usually.
	 */
	public function export_content_cleanup( $content ) {
		$new_content = $content;

		// If the post contains Gutenberg/ACF blocks we need to pick them out and make sure the IDs are converted to URLs.
		$regex = '/<!-- wp:acf.*{((.|\n)*?)} \/-->/';
		preg_match_all( $regex, $content, $matches );

		// $matches[1] is the 2nd grouping in our regex. ie the json array of ACF data.
		if ( empty( $matches[1] ) ) {
			return $new_content;
		}

		foreach ( $matches[1] as $match ) {
			// Parse each matched json string.
			$json_string = '{' . $match . '}';
			$parsed_data = json_decode( $json_string, true );

			if ( isset( $parsed_data['data'] ) ) {
				foreach ( $parsed_data['data'] as $key => $value ) {
					if ( substr( $key, 0, 1 ) === '_' ) {
						// Get the type of ACF field.
						$acf_field = get_field_object( $value );
						$old_value = $parsed_data['data'][ ltrim( $key, '_' ) ];
						$new_value = $old_value;

						switch ( $acf_field['type'] ) {
							case 'image':
							case 'file':
								if ( is_numeric( $old_value ) ) {
									// Get URL of post attachment.
									$new_value = wp_get_attachment_url( $old_value );
								}
								break;
							case 'taxonomy':
								$taxonomy = $acf_field['taxonomy'];
								if ( is_array( $old_value ) ) {
									$new_value = [];
									foreach ( $old_value as $term_id ) {
										$new_value[] = get_term( $term_id, $taxonomy )->name;
									}
								} elseif ( is_numeric( $old_value ) ) {
									$new_value = get_term( $old_value, $taxonomy )->name;
								}
								break;
							case 'relationship':
							case 'post_object':
								if ( is_array( $old_value ) ) {
									$new_value = [];
									foreach ( $old_value as $post_id ) {
										$new_value[] = get_the_permalink( $post_id );
									}
								} elseif ( is_numeric( $old_value ) ) {
									$new_value = get_the_permalink( $old_value );
								}
								break;
						}

						$parsed_data['data'][ ltrim( $key, '_' ) ] = $new_value;
					}
				}
			}

			// Recode data to JSON.
			$encoded_string = wp_json_encode( $parsed_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
			$encoded_string = ltrim( $encoded_string, '{' );
			$encoded_string = rtrim( $encoded_string, '}' );

			// Replace every match with new string.
			$new_content = str_replace( $match, $encoded_string, $new_content );
		}

		return $new_content;
	}

	/**
	 * We need to check if the field is an ACF object and if the field is a post/term object.
	 * Then we can retrieve the post URL if it is.
	 *
	 * @param string $key meta key to check against.
	 * @param mixed  $value the value to check.
	 * @param int    $post_id the post ID for field.
	 */
	public function check_acf_post_field( $key, $value, $post_id ) {
		if ( is_numeric( $value ) && function_exists( 'get_field_object' ) ) {
			$acf_object = get_field_object( $key, $post_id );
			if ( $acf_object ) {
				if ( in_array( $acf_object['type'], [ 'post_object', 'page_link', 'relationship', 'file', 'image' ], true ) ) {
					$value = $this->get_post_url( $value );
				} elseif ( in_array( $acf_object['type'], [ 'taxonomy' ], true ) ) {
					$value = $this->get_term_name( $value );
				}
			}
		}

		return $value;
	}

	/**
	 * Returns post/attachment URL for given ID
	 *
	 * @param int $id the post ID.
	 */
	public function get_post_url( $id ) {
		$meta_post = get_post( $id );
		if ( ! empty( $meta_post ) ) {
			$meta_val = get_permalink( $meta_post );
			if ( $meta_post->post_type === 'attachment' ) {
				$meta_val = wp_get_attachment_url( $meta_post->ID );
			}
		}

		return $meta_val ?? $id;
	}

	/**
	 * Returns taxonomy Term name for given ID
	 *
	 * @param int $id the term ID.
	 */
	public function get_term_name( $id ) {
		$term = get_term( $id );
		if ( ! empty( $term ) ) {
			$term_name = $term->name;
		}

		return $term_name ?? $id;
	}

	/**
	 * Function to fetch all meta keys from the DB for each CPT
	 *
	 * @param string $post_type the cpt name.
	 * @param bool   $private_meta whether to fetch the private meta keys.
	 */
	public function generate_cpt_meta_keys( $post_type, $private_meta = false ) {
		global $wpdb;

		$exclude_private = $private_meta ? '' : "AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)'";

		$query = "SELECT DISTINCT($wpdb->postmeta.meta_key) 
			FROM $wpdb->posts 
			LEFT JOIN $wpdb->postmeta 
			ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
			WHERE $wpdb->posts.post_type = '%s' 
			AND $wpdb->postmeta.meta_key != '' 
			" . $exclude_private . "
			AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'";

		$meta_keys = $wpdb->get_col( $wpdb->prepare( $query, $post_type ) ); // phpcs:ignore
		natcasesort( $meta_keys );

		return $meta_keys;
	}

	/**
	 * Return data array from JSON file
	 *
	 * @param string $file path to the JSON file.
	 * @param bool   $first whether to return the first array val or not.
	 */
	public function return_file_data( $file, $first = false ) {
		$ext = wp_check_filetype( $file )['ext'];

		if ( $ext === 'json' ) {
			$json_file = file_get_contents( $file );
			$data      = json_decode( $json_file, true );
		} elseif ( $ext === 'csv' ) {
			// First get the CSV and set up array.
			$csv_data = [];
			$csv_file = fopen( $file, 'r' );
			while ( ( $line = fgetcsv( $csv_file ) ) !== false ) {
				$csv_data[] = $line;
			}
			fclose( $csv_file );

			// Reconfigure array to use first row as keys.
			$data = [];
			$keys = $csv_data[0];
			unset( $csv_data[0] );

			foreach ( $csv_data as $row ) {
				$data[] = array_combine( $keys, $row );
			}
		}

		if ( $first ) {
			if ( $ext === 'json' ) {
				$data = $this->merge_and_return_first( $data );
				return $data;
			} else {
				return $data[0];
			}
		} else {
			return $data;
		}
	}

	/**
	 * Helper to return all keys (and values) for nested arrays
	 *
	 * @param array $data whole data array for import.
	 */
	public function merge_and_return_first( $data ) {
		$return = [];

		// Merge nested arrays into 1 $return array.
		foreach ( $data as $post ) {
			foreach ( $post as $key => $value ) {
				if ( ! isset( $return[ $key ] ) ) {
					$return[ $key ] = $value;
				} else {
					if ( is_array( $value ) ) {
						$return[ $key ] = array_merge( $return[ $key ], $value );
					}
				}
			}
		}

		// Sort the nested arrays.
		foreach ( $return as $key => $value ) {
			if ( is_array( $value ) ) {
				array_multisort( array_keys( $return[ $key ] ), SORT_NATURAL, $return[ $key ] );
			}
		}

		// Expand and unset tax and meta keys.
		foreach ( $return as $key => $value ) {
			if ( $key === 'nine3_tax' || $key === 'nine3_meta' ) {
				foreach ( $value as $n_key => $n_value ) {
					$return[ $n_key ] = $n_value;
				}
				unset( $return[ $key ] );
			}
		}

		return $return;
	}

	/**
	 * Wrapper function which cleans up post content according to given options
	 * eg:
	 * $args = [
	 *   'replace_images' => true,
	 *   'remove_divi'    => true,
	 *   'remove_styles'  => true,
	 *   'remove_classes' => false,
	 * ];
	 *
	 * @param string $content the post content as a string.
	 * @param array  $args the cleanup options.
	 */
	public function cleanup_post_content( $content, $args = [] ) {
		// Run divi removal :D.
		if ( isset( $args['remove_divi'] ) ) {
			$content = $this->remove_divi_shortcodes( $content );
		}

		// Run image upload/replacement function.
		if ( isset( $args['replace_images'] ) ) {
			$content = $this->upload_and_replace_images( $content );
		}

		// Remove styles.
		if ( isset( $args['remove_styles'] ) ) {
			$content = $this->remove_inline_styles( $content );
		}

		// Remove classes.
		if ( isset( $args['remove_classes'] ) ) {
			$content = $this->remove_inline_classes( $content );
		}

		return $content;
	}

	/**
	 * Remove Divi shortcodes
	 *
	 * @param string $content the post content as a string.
	 */
	public function remove_divi_shortcodes( $content ) {
		// Find all divi buttons and replace with new A tag markup.
		preg_match_all( '/\[et_pb_button.*?\]/', $content, $buttons, PREG_PATTERN_ORDER );
		if ( $buttons ) {
			foreach ( $buttons[0] as $divi_btn ) {
				// Button URL.
				preg_match( '/button_url="(.*?)\"/', $divi_btn, $url_matches );
				$button_url = isset( $url_matches[1] ) ? $url_matches[1] : '';

				if ( strpos( $button_url, '.pdf' ) === true ) {
					$button_url = $this->upload_image( $button_url, 0, false, true ); //phpcs:ignore
				}

				// Button text.
				preg_match( '/button_text="(.*?)\"/', $divi_btn, $text_matches );
				$button_text = isset( $text_matches[1] ) ? $text_matches[1] : '';

				// Is new window?
				preg_match( '/url_new_window="(.*?)\"/', $divi_btn, $window_matches );
				$is_new_window = isset( $window_matches[1] ) ? $window_matches[1] : '';
				$target = '';
				if ( $is_new_window && $is_new_window === 'on' ) {
					$target = ' target="_blank"';
				}

				// New A tag element.
				$new_link = '<a class="button" href="' . $button_url . '"' . $target . '>' . $button_text . '</a>';

				// Replace the string in content.
				$content = str_replace( $divi_btn, $new_link, $content );
			}
		}

		// Find all images and replace with img tags.
		preg_match_all( '/\[et_pb_image.*?\]/', $content, $images, PREG_PATTERN_ORDER );
		if ( $images ) {
			foreach ( $images[0] as $divi_img ) {
				// Image URL.
				preg_match( '/url="(.*?)\"/', $divi_img, $url_matches );
				$image_url = isset( $url_matches[1] ) ? $url_matches[1] : '';

				if ( strpos( $image_url, '.pdf' ) === true ) {
					$image_url = $this->upload_image( $image_url, 0, false, true ); //phpcs:ignore
				}

				// Image src.
				preg_match( '/src="(.*?)\"/', $divi_img, $src_matches );
				$image_src = isset( $src_matches[1] ) ? $src_matches[1] : '';
				$image_src = $this->upload_image( $image_src, 0, false, true ); //phpcs:ignore

				// New IMG tag element.
				$new_image = '<img src="' . $image_src . '" alt="' . __( 'Main content image', 'nine3import' ) . '" />';
				if ( $image_url ) {
					$target = '';
					// Is new window?
					preg_match( '/url_new_window="(.*?)\"/', $divi_img, $window_matches );
					$is_new_window = isset( $window_matches[1] ) ? $window_matches[1] : '';
					if ( $is_new_window && $is_new_window === 'on' ) {
						$target = ' target="_blank"';
					}

					$new_image = '<a href="' . $image_url . '"' . $target . '>' . $new_image . '</a>';
				}

				// Replace the string in content.
				$content = str_replace( $divi_img, $new_image, $content );
			}
		}

		// Remove Social Media Links.
		$social_regex = '/(\[et_pb_social_media_follow_network.*?\])(.*)/';
		$content      = preg_replace( $social_regex, '', $content );

		// Remove the rest of the shortcodes.
		$divi_regex = '/\[\/?et_pb.*?\]/';
		$content    = preg_replace( $divi_regex, "\r\n", $content );

		return $content;
	}

	/**
	 * Remove inline styles
	 *
	 * @param string $content the post content as a string.
	 */
	public function remove_inline_styles( $content ) {
		$content = preg_replace( '/style=".*?"/', '', $content );
		return $content;
	}

	/**
	 * Remove inline classes
	 *
	 * @param string $content the post content as a string.
	 */
	public function remove_inline_classes( $content ) {
		$content = preg_replace( '/class=".*?"/', '', $content );
		return $content;
	}

	/**
	 * Upload and replace content image src
	 *
	 * @param string $content the post content as a string.
	 */
	public function upload_and_replace_images( $content ) {
		// If content is empty return.
		if ( empty( $content ) ) {
			return;
		}

		// Check if file domain is set.
		$options = get_option( 'nine3_import' );
		$domain  = isset( $options['import_domain'] ) ? $options['import_domain'] : false;

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $content );
		libxml_clear_errors();
		$dom->preserveWhiteSpace = false; //phpcs:ignore

		$images = $dom->getElementsByTagName( 'img' );
		if ( $images ) {
			foreach ( $images as $image ) {
				$old_src = $image->getAttribute( 'src' );

				// If domain is not in the src, continue.
				if ( $domain && strpos( $old_src, $domain ) === false ) {
					continue;
				}

				$old_src      = str_replace( '&', '&amp;', $old_src );
				$old_src      = preg_replace( '/-\d+[Xx]\d+\./', '.', $old_src ); // Removes image size.
				$new_image_id = (string) $this->upload_image( $old_src );
				if ( is_numeric( $new_image_id ) ) {
					$new_src = wp_get_attachment_url( $new_image_id );
					$content = str_replace( $old_src, $new_src, $content );
				}
			}
		}

		$anchors = $dom->getElementsByTagName( 'a' );
		if ( $anchors ) {
			foreach ( $anchors as $anchor ) {
				$old_href = $anchor->getAttribute( 'href' );

				// If domain is not in the href, continue.
				if ( $domain && strpos( $old_href, $domain ) === false ) {
					continue;
				}

				if ( $old_href ) {
					$doctypes = [ '.pdf', '.doc', '.docx', '.xlsx', '.csv', '.xls', '.ppt', '.pptx', '.txt' ];
					foreach ( $doctypes as $ext ) {
						if ( stripos( $old_href, $ext ) !== false ) {
							$upload_href = $old_href;

							// If the href is relative, add domain.
							if ( substr( $old_href, 0, 11 ) === '/wp-content' && $domain ) {
								$upload_href = esc_url( rtrim( $domain, '/' ) . $old_href );
							}

							$new_href = (string) $this->upload_image( $upload_href, 0, false, true );
							$content  = str_replace( $old_href, $new_href, $content );
						}
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Function to set taxonomy terms
	 *
	 * @param array  $terms_array the array of term names.
	 * @param string $taxonomy the taxonomy to insert into.
	 * @param array  $post_id the post ID.
	 * @param int    $parent the id of parent term.
	 */
	public function set_taxonomy_terms( $terms_array, $taxonomy, $post_id, $parent = false ) {
		if ( empty( $terms_array ) || ! is_array( $terms_array ) ) {
			$this->debug( 'No terms given or not an array.', 'log' );
			return;
		}

		$new_terms = wp_set_object_terms( $post_id, $terms_array, $taxonomy );

		// Add parents terms.
		if ( $new_terms && $parent ) {
			foreach ( $new_terms as $new_term ) {
				wp_update_term( $new_term, $taxonomy, [ 'parent' => $parent ] );
			}
		}

		return $new_terms;
	}

	/*********************************************
	 * File handling
	 *********************************************/

	/**
	 * Function to upload image using a filepath
	 *
	 * @param string $image the full url of new image to upload.
	 * @param int    $parent_id the post id to attach the image to.
	 * @param bool   $is_featured do we want to make the image a featured image of the post.
	 * @param bool   $return_url return the URL of the image not the ID.
	 */
	public function upload_image( $image, $parent_id = 0, $is_featured = false, $return_url = false ) {
		// Remove any parameters from URL.
		$image = strtok( $image, '?' );

		// Firstly check if there is already an attachment for the image.
		$attachments = get_posts(
			[
				'post_type'   => 'attachment',
				'numberposts' => 1,
				'meta_key'    => '_nine3_original_file_src',
				'meta_value'  => $image,
			]
		);

		if ( ! empty( $attachments ) ) {
			// Attachment for the image already exists, so set the attachment ID.
			$attach_id = $attachments[0]->ID;
		} else {
			// Check if the img url is the same as the site domain and see if it already has an attachment.
			$site_url = str_replace( [ 'http://', 'https://', 'www.' ], '', home_url() );
			if ( strpos( $image, $site_url ) !== false ) {
				$attach_id = attachment_url_to_postid( $image );
			}
		}

		// If no attach ID found, download the file and set meta data etc.
		if ( ! isset( $attach_id ) || ! $attach_id || $attach_id === 0 ) {
			// Check if basic auth is set.
			$options = get_option( 'nine3_import' );
			$ba_user = isset( $options['basic_auth_user'] ) ? $options['basic_auth_user'] : false;
			$ba_pass = isset( $options['basic_auth_pass'] ) ? $options['basic_auth_pass'] : false;
			if ( $ba_user && $ba_pass ) {
				$get_args = [
					'headers' => [
						'Authorization' => 'Basic ' . base64_encode( "$ba_user:$ba_pass" ),
					],
				];

				$get = wp_remote_request( $image, $get_args );
			} else {
				$get = wp_remote_request( $image );
			}

			$type     = wp_remote_retrieve_header( $get, 'content-type' );
			$modified = wp_remote_retrieve_header( $get, 'last-modified' );
			$status   = wp_remote_retrieve_response_code( $get );

			// Return false is there's no type, if the type is .exe or if the file is 404.
			if ( ! $type || strpos( $type, 'exe' ) === true || $status == '404' ) {
				return false;
			}

			// Try to get filename from headers.
			$file_string = wp_remote_retrieve_header( $get, 'content-disposition' );
			$regex       = '/(?<=filename=").*(?=")/';
			preg_match( $regex, $file_string, $matches );

			if ( ! empty( $matches ) ) {
				$basename = $matches[0];
			} else {
				$basename = basename( $image );
			}

			$mirror = wp_upload_bits(
				$basename,
				null,
				wp_remote_retrieve_body( $get ),
				$modified ? gmdate( 'Y/m', strtotime( $modified ) ) : null
			);

			// Debug if error and return original image.
			if ( ! empty( $mirror['error'] ) ) {
				$this->debug( 'Upload error:', 'log' );
				$this->debug( $mirror['error'], 'log' );
				$this->debug( $image, 'log' );
				return $image;
			}

			$attachment = [
				'post_title'     => $basename,
				'post_mime_type' => $type,
				'post_status'    => 'inherit',
			];

			$attach_id = wp_insert_attachment( $attachment, $mirror['file'], $parent_id );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = wp_generate_attachment_metadata( $attach_id, $mirror['file'] );

			if ( ! isset( $attach_data ) ) {
				$attach_data = wp_get_attachment_metadata( $attach_id );
				wp_update_attachment_metadata( $attach_id, $attach_data );
			}

			update_post_meta( $attach_id, '_nine3_original_file_src', $image );
		}

		if ( $is_featured ) {
			set_post_thumbnail( $parent_id, $attach_id );
		}

		return $return_url ? wp_get_attachment_url( $attach_id ) : $attach_id;
	}

	/**
	 * Write temp php file to store data array.
	 *
	 * @param array $data the php array to save.
	 * @param array $options array of options for file save.
	 */
	public function write_temp_php_file( array $data = [], $options = [] ) {
		// Create DIR if not exists.
		if ( ! is_dir( NINE3_EXPORT_PATH ) ) {
			wp_mkdir_p( NINE3_EXPORT_PATH );
		}

		$filename = 'export_' . uniqid();
		if ( isset( $options['filename'] ) ) {
			$filename = $options['filename'];
		}
		$filepath = NINE3_EXPORT_PATH . '/' . $filename . '.php';

		if ( isset( $options['mode'] ) ) {
			$fh = fopen( $filepath, $options['mode'] );
		} else {
			$fh = fopen( $filepath, 'w' );
		}

		if ( ! is_resource( $fh ) ) {
				return false;
		}

		// Create string to save to the temp file.
		if ( isset( $options['operation'] ) ) {
			$temp_data = $this->try_get_file_contents( $filepath );
			switch ( $options['operation'] ) {
				case 'start':
					$temp_data .= '<?php $export_data = array (' . "\r\n";
					break;
				case 'add':
					$temp_data .= var_export( $data, true ) . ',';
					break;
				case 'close':
					$temp_data = rtrim( $temp_data, ',' );
					$temp_data .= ');';
					break;
			}
		} else {
			$temp_data = '<?php $export_data = ' . var_export( $data, true ) . ';';
		}

		// Write contents.
		$saved = file_put_contents( $filepath, $temp_data, LOCK_EX );

		fclose( $fh );

		if ( $saved ) {
			$this->cleanup( NINE3_EXPORT_PATH, $filepath );
			return $filepath;
		} else {
			esc_html_e( 'Unable to create temp file, export will not work!', 'nine3migrate' );
		}
	}

	/**
	 * Recursive function to try and get file contents 3 times
	 *
	 * @param string $filepath the absolute file path.
	 * @param int    $iterator count amount of recursions.
	 */
	private function try_get_file_contents( $filepath, $iterator = 0 ) {
		if ( $iterator < 3 ) {
			if ( ( $temp_data = file_get_contents( $filepath ) ) !== false ) {
				return $temp_data;
			} else {
				sleep( 2 );
				$iterator++;
				$this->try_get_file_contents( $filepath, $iterator );
			}
		} else {
			return '';
		}
	}

	/**
	 * Delete files or folders.
	 *
	 * @param string $location file to delete or folder to clean.
	 * @param string $exclude if folder location add any files you want to keep.
	 */
	public function cleanup( $location, $exclude = false ) {
		if ( is_dir( $location ) ) {
			$files = glob( $location . '*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) && $file !== $exclude ) {
					unlink( $file );
				}
			}
		} elseif ( is_file( $location ) ) {
			unlink( $location );
		}
	}

	/*********************************************
	 * Errors and debugging
	 *********************************************/

	/**
	 * Print error messages
	 *
	 * @param array $errors the array of messages to loop and print.
	 */
	public function show_errors( array $errors ) {
		foreach ( $errors as $error ) :
			ob_start();
			?>
			<p><?php echo esc_html( $error ); ?></p>
			<?php
			ob_flush();
		endforeach;
	}

	/**
	 * Debugger
	 *
	 * @param mixed  $data any data we want to debug.
	 * @param string $type 'display' or 'log' the results.
	 * @param bool   $exit do we want to exit the function.
	 */
	public function debug( $data, $type = 'display', $exit = false ) {
		if ( empty( $data ) ) {
			return;
		}

		if ( $type === 'display' ) {
			echo '<pre>';
			var_dump( $data );
			echo '</pre>';
		} else {
			error_log( 'NINE3_MIGRATE: ' . print_r( $data, true ) );
		}

		if ( $exit ) {
			exit();
		}
	}

	/**
	 * This function iterates the offset or returns a 'completed' response to json
	 *
	 * @param array    $response the returned response.
	 * @param int/bool $offset how many posts to offset by in new redirect (if true, return complete).
	 */
	public function handle_import_response( $response, $offset ) {
		if ( is_numeric( $offset ) ) {
			$response['offset'] = intval( $offset ) + 1;
		} elseif ( $offset === true ) {
			$response['complete'] = 1;
		}

		echo json_encode( $response );
		wp_die();
	}
}