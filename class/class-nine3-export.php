<?php
/**
 * Class for rendering the export options and runnning the export
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Class definition
 */
class Nine3_Export {
	/**
	 * Container for the helpers object.
	 *
	 * @var object
	 */
	public $helpers;

	/**
	 * Array of error messages.
	 *
	 * @var array
	 */
	public $errors;

	/**
	 * Let's build something
	 */
	public function __construct() {
		// Load in helpers.
		$this->helpers = new Nine3_Helpers();

		// Render settings.
		$this->helpers->render_settings( 'nine3_export_group', 'admin.php?page=nine3-migrate-page&tab=export' );

		// Run the export.
		$this->run_export();
	}

	/**
	 * Let's do some exporting
	 */
	private function run_export() {
		$options      = get_option( 'nine3_export' );
		$post_type    = ! empty( $options['export_cpt'] ) ? $options['export_cpt'] : false;
		$post_status  = ! empty( $options['export_status'] ) ? strtolower( $options['export_status'] ) : false;
		$format       = ! empty( $options['export_format'] ) ? strtolower( $options['export_format'] ) : false;
		$private_meta = isset( $options['export_private_meta'] ) ? $options['export_private_meta'] : false;
		$limit        = isset( $options['export_limit'] ) ? intval( $options['export_limit'] ) : false;
		$offset       = isset( $options['export_offset'] ) ? intval( $options['export_offset'] ) : false;
		$post_ids     = isset( $options['export_post_ids'] ) ? $options['export_post_ids'] : false;
		$taxonomy     = isset( $options['export_taxonomy'] ) ? $options['export_taxonomy'] : false;
		$term_names   = isset( $options['export_term_names'] ) ? $options['export_term_names'] : false;
		$meta_string  = isset( $options['export_meta_query'] ) ? $options['export_meta_query'] : false;

		if ( ! $post_type ) {
			$this->errors[] = esc_html__( 'Please select a Post Type.', 'nine3migrate' );
		}

		if ( ! $post_status ) {
			$this->errors[] = esc_html__( 'Please select a Post Status.', 'nine3migrate' );
		}

		if ( ! $format ) {
			$this->errors[] = esc_html__( 'Please select a Format.', 'nine3migrate' );
		}

		if ( ! $post_type || ! $post_status || ! $format ) {
			$this->helpers->show_errors( $this->errors );
			return;
		}

		// Render export form.
		$export_args = [
			'post_type'   => $post_type,
			'post_status' => $post_status,
			'format'      => $format,
			'private'     => $private_meta,
			'limit'       => $limit,
			'offset'      => $offset,
		];

		if ( $post_ids ) {
			$post_ids = explode( ',', $post_ids );
			$export_args['ids'] = $post_ids;
		}

		if ( $term_names && $taxonomy ) {
			$term_names = explode( ',', $term_names );
			$export_args['term_names'] = $term_names;
			$export_args['taxonomy']   = $taxonomy;
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
			$export_args['meta_query'] = $meta_query;
		}

		$this->helpers->render_export_form( $export_args );
		$this->helpers->render_progress_bar();

		// If there are temp php files in the export folder, render download forms for them.
		if ( is_dir( NINE3_EXPORT_PATH ) ) {
			$files = list_files( NINE3_EXPORT_PATH );
			foreach ( $files as $file ) {
				if ( file_exists( $file ) ) {
					$this->helpers->render_download_form( $file, $format );
				}
			}
		}
	}
}
