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
class Nine3_Import_Terms {
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
		$this->helpers->render_settings( 'nine3_import_group', 'admin.php?page=nine3-migrate-page&tab=import-terms' );

		// Setup import page.
		$this->setup_import();
	}

	/**
	 * Setup import page
	 */
	private function setup_import() {
		$options  = get_option( 'nine3_import_terms' );
		$taxonomy = isset( $options['import_tax'] ) ? $options['import_tax'] : false;
		$file     = isset( $options['import_tax_file'] ) ? $options['import_tax_file'] : false;

		if ( ! $taxonomy ) {
			$this->errors[] = esc_html__( 'Please select a Taxonomy.', 'nine3migrate' );
		}

		if ( ! $file ) {
			$this->errors[] = esc_html__( 'Please select or upload an import file.', 'nine3migrate' );
		}

		if ( ! $taxonomy || ! $file ) {
			$this->helpers->show_errors( $this->errors );
			return;
		}

		// Check settings and render markup.
		$import_args = [
			'taxonomy'    => $taxonomy,
			'import_file' => $file,
		];
		$this->helpers->render_import_form( $import_args, true );
		$this->helpers->render_progress_bar();
	}
}
