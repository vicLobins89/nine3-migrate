<?php
/**
 * Class for rendering the export options and runnning the export (for terms)
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Class definition
 */
class Nine3_Export_Terms {
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
		$this->helpers->render_settings( 'nine3_export_group', 'admin.php?page=nine3-migrate-page&tab=export-terms' );

		// Run the export.
		$this->run_export();
	}

	/**
	 * Let's do some exporting
	 */
	private function run_export() {
		$options  = get_option( 'nine3_export_terms' );
		$taxonomy = ! empty( $options['export_tax'] ) ? $options['export_tax'] : false;

		if ( ! $taxonomy ) {
			$this->errors[] = esc_html__( 'Please select a Taxonomy.', 'nine3migrate' );
			$this->helpers->show_errors( $this->errors );
			return;
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		];

		$terms = get_terms( $args );

		// Set up export data array for final export.
		$export_data = [];

		// Loop through each post, get all data and add to $export_data.
		foreach ( $terms as $term ) {
			$term_array = [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'taxonomy'    => $term->taxonomy,
			];

			// Term parent.
			if ( $term->parent > 0 ) {
				$term_array['parent'] = get_term( $term->parent, $taxonomy )->name;
			}

			// Term data.
			$term_meta = get_term_meta( $term->term_id );
			foreach ( $term_meta as $key => $value ) {
				if ( is_array( $value ) && count( $value ) === 1 ) {
					$value = implode( '', $value );
				}
				$value = $this->helpers->check_acf_post_field( $key, $value, $term->term_id );

				$term_array['nine3_meta'][ $key ] = $value;
			}

			$export_data[] = $term_array;
		}

		?>
		<hr>
		<p><?php echo esc_html__( 'Term count: ', 'nine3migrate' ) . esc_html( count( $export_data ) ); ?></p>
		<?php

		// Create temp php file with our data array.
		$filepath = $this->helpers->write_temp_php_file( $export_data );

		// Render download button with php array filepath as param.
		if ( file_exists( $filepath ) ) {
			$this->helpers->render_download_form( $filepath, 'json' );
		}
	}
}
