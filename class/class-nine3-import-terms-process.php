<?php
/**
 * Import class for migrate terms
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Start the import
 */
class Nine3_Import_Terms_Process {
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
		$taxonomy = ! empty( $data['taxonomy'] ) ? sanitize_title( wp_unslash( $data['taxonomy'] ) ) : 'category';

		// Count totals.
		$total = count( $import_data );

		$this->helpers->debug( 'Item ' . $offset . ' of ' . $total, 'log' );

		if ( ! empty( $import_data ) ) {
			$response = [];

			while ( $offset < $total ) {
				$response = $this->process_term_data( $import_data[ $offset ], $taxonomy );
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
	 * Process term data
	 *
	 * @param object $post_data all fetched data from original DB.
	 * @param string $taxonomy string for taxonomy.
	 */
	private function process_term_data( $post_data, $taxonomy ) {
		if ( ! is_array( $post_data ) ) {
			return;
		}

		// Term args.
		$term_slug = isset( $post_data['slug'] ) ? $post_data['slug'] : sanitize_title( wp_unslash( $post_data['name'] ) );
		$term_args = [
			'description' => isset( $post_data['description'] ) ? $post_data['description'] : '',
			'slug'        => $term_slug,
			'parent'      => isset( $post_data['parent'] ) ? $post_data['parent'] : 0,
		];

		$exists = term_exists( $post_data['name'], $taxonomy );
		if ( $exists ) {
			$term_id = $exists;
			if ( is_array( $exists ) ) {
				$term_id = $exists['term_id'];
			}
			wp_update_term( $term_id, $taxonomy, $term_args );
		} else {
			$term    = wp_insert_term( $post_data['name'], $taxonomy, $term_args );
			$term_id = $term['term_id'];
		}

		// Update term meta.
		if ( isset( $post_data['nine3_meta'] ) ) {
			foreach ( $post_data['nine3_meta'] as $key => $value ) {
				update_term_meta( $term_id, $key, $value );
			}
		}

		$start_time = microtime( true );

		// Handle return.
		$return['id'] = $term_id;

		if ( $term_id ) {
			$return['message'] = __( 'Success: ', 'nine3migrate' );
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
