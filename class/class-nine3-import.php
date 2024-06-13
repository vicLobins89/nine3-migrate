<?php
/**
 * Class for rendering the import options and runnning the import
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Class definition
 */
class Nine3_Import {
	/**
	 * Container for the settings object.
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
		$this->helpers->render_settings( 'nine3_import_group', 'admin.php?page=nine3-migrate-page&tab=import' );

		// Setup import page.
		$this->setup_import();
	}

	/**
	 * Setup import page
	 */
	private function setup_import() {
		$options   = get_option( 'nine3_import' );
		$post_type = isset( $options['import_cpt'] ) ? $options['import_cpt'] : false;
		$file      = isset( $options['import_file'] ) ? $options['import_file'] : false;
		$format    = $file ? wp_check_filetype( $file )['ext'] : false;
		$update    = isset( $options['import_update'] ) ? $options['import_update'] : false;
		$offset    = ! empty( $options['import_offset'] ) ? $options['import_offset'] : 0;
		$automatic = ( isset( $options['import_fieldmap'] ) && $options['import_fieldmap'] === 'Automatic' ) ? true : false;
		$automatic = $format === 'csv' ? false : $automatic;

		if ( ! $post_type ) {
			$this->errors[] = esc_html__( 'Please select a Post Type.', 'nine3migrate' );
		}

		if ( ! $file ) {
			$this->errors[] = esc_html__( 'Please select or upload an import file.', 'nine3migrate' );
		}

		if ( ! $post_type || ! $file ) {
			$this->helpers->show_errors( $this->errors );
			return;
		}

		/**
		 * Check settings and render markup.
		 * "Automatic" will only work with JSON.
		 * Also if this is a CSV, fields must be mapped manually.
		 */
		$import_args = [
			'post_type'   => $post_type,
			'import_file' => $file,
			'offset'      => $offset,
			'update'      => $update,
		];
		$this->helpers->render_import_form( $import_args, $automatic );
		$this->helpers->render_progress_bar();
	}
}
