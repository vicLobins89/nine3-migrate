<?php
/**
 * Class for rendering the hubspot options and runnning the export
 *
 * @package nine3migrate
 */

namespace nine3migrate;

use HubSpot\Factory;
use HubSpot\Client\Cms\Blogs\Posts\ApiException;

/**
 * Class definition
 */
class Nine3_Hubspot {
	/**
	 * HubSpot API Key.
	 *
	 * @var object
	 */
	private $api_key;

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
	 * Array of default HubSpot post fields to fetch.
	 *
	 * @var array
	 */
	private $fields = [
		'blog_post_author',
		'featured_image',
		'id',
		'language',
		'meta_description',
		'post_body',
		'post_summary',
		'publish_date',
		'slug',
		'title',
		'topics',
		'widgets',
	];

	/**
	 * Let's build something
	 */
	public function __construct() {
		// Load in helpers.
		$this->helpers = new Nine3_Helpers();

		// Render settings.
		$this->helpers->render_settings( 'nine3_hubspot_group', 'admin.php?page=nine3-migrate-page&tab=hubspot' );

		// Setup hubspot export page.
		$this->setup_hubspot_export();
	}

	/**
	 * Setup import page
	 */
	private function setup_hubspot_export() {
		$options       = get_option( 'nine3_hubspot' );
		$api_key       = isset( $options['hubspot_api_key'] ) ? $options['hubspot_api_key'] : false;
		$blog_id       = isset( $options['hubspot_blog_id'] ) ? $options['hubspot_blog_id'] : false;
		$limit         = isset( $options['hubspot_limit'] ) ? $options['hubspot_limit'] : 20;
		$offset        = isset( $options['hubspot_offset'] ) ? $options['hubspot_offset'] : 0;
		$fields        = isset( $options['hubspot_fields'] ) ? $options['hubspot_fields'] : '';
		$widget_labels = isset( $options['hubspot_widgets'] ) ? $options['hubspot_widgets'] : '';

		if ( ! $api_key ) {
			$this->errors[] = esc_html__( 'Please add an API Key.', 'nine3migrate' );
		} else {
			$this->api_key = $api_key;
		}

		if ( ! $blog_id ) {
			$this->errors[] = esc_html__( 'Please add a HubSpot Blog ID.', 'nine3migrate' );
		}

		if ( $limit > 300 ) {
			$this->errors[] = esc_html__( 'Max limit is 300.', 'nine3migrate' );
		}

		if ( ! $api_key || ! $blog_id || $limit > 300 ) {
			$this->helpers->show_errors( $this->errors );
			return;
		}

		// Merge fields into object property if set.
		if ( $fields ) {
			$this->fields = array_merge( $this->fields, explode( ',', $fields ) );
		}

		// If no errors, render export button.
		$export_url = admin_url( 'admin.php?page=nine3-migrate-page&tab=hubspot' );
		?>
		<a href="<?php echo esc_url( $export_url . '&hubspot-export=run' ); ?>" class="button button-primary"><?php esc_html_e( 'Run Export!', 'nine3migrate' ); ?></a>
		<?php

		// Run hubspot export.
		if ( isset( $_GET['hubspot-export'] ) && $_GET['hubspot-export'] === 'run' ) {
			?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Abort Export!', 'nine3migrate' ); ?></a>
			<?php

			$url  = 'https://api.hubapi.com/content/api/v2/blog-posts';
			$opts = [
				'hapikey'          => $this->api_key,
				'limit'            => $limit,
				'content_group_id' => $blog_id,
				'offset'           => $offset,
				'order_by'         => '-publish_date',
			];
			$data = $this->get_api_data( $url, $opts );

			if ( $data ) {
				$data = $this->process_post_data( $data, $widget_labels );

				// Create temp file.
				$file_options = [
					'filename'  => 'export_' . $blog_id . '_' . gmdate( 'Ymd' ),
				];
				$filename     = $this->helpers->write_temp_php_file( $data, $file_options );
			}
		}

		// If there are temp php files in the export folder, render download forms for them.
		if ( is_dir( NINE3_EXPORT_PATH ) ) {
			$files = list_files( NINE3_EXPORT_PATH );
			foreach ( $files as $file ) {
				if ( file_exists( $file ) ) {
					$this->helpers->render_download_form( $file, 'json' );
				}
			}
		}
	}

	/**
	 * Try to get data from the HubSpot API endpoint
	 *
	 * @param string $endpoint the API endpoint URL.
	 * @param array  $options array of options to send with the request.
	 */
	private function get_api_data( $endpoint, $options = [] ) {
		$endpoint = add_query_arg( $options, $endpoint );
		$response = wp_remote_get( $endpoint );

		// Error checks.
		$error = false;

		// Early check for initial response.
		if ( ! $response || ! isset( $response['response'] ) ) {
			$this->errors[] = esc_html__( 'No response, please contact site administrator.', 'nine3migrate' );
			$error          = true;
		}

		// Check response code/message.
		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code !== 200 ) {
			$response_message = wp_remote_retrieve_response_message( $response );
			$error_message    = __( 'Error', 'nine3migrate' ) . ': ' . $response_code . ' ' . $response_message;
			$this->errors[]   = $error_message;
			$error            = true;
		}

		// Display errors.
		if ( $error ) {
			$this->helpers->show_errors( $this->errors );
			return;
		}

		// We can now retrieve the response body.
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Processes HubSpot blog post data and returns only what we need
	 *
	 * @param array  $data the retrieved body of API response.
	 * @param string $widget_labels comma separated list of widget names to fetch for each post.
	 */
	private function process_post_data( $data, $widget_labels ) {
		$decoded       = json_decode( $data, true );
		$hbspt_posts   = $decoded['objects'];
		$posts_array   = [];
		$widget_labels = explode( ',', $widget_labels );

		foreach ( $hbspt_posts as $hbspt_post ) {
			$post_data = [];

			// Get the data for each of our defined fields as save to new array.
			foreach ( $this->fields as $field ) {
				if ( isset( $hbspt_post[ $field ] ) ) {
					$post_data[ $field ] = $hbspt_post[ $field ];
				}
			}

			// Fetch topics - the requires another call to the API.
			if ( isset( $post_data['topics'] ) ) {
				$topics = [];
				foreach ( $post_data['topics'] as $topic_id ) {
					$url   = "https://api.hubapi.com/blogs/v3/topics/$topic_id?hapikey=$this->api_key";
					$topic = $this->get_api_data( $url );

					if ( $topic ) {
						$decoded  = json_decode( $topic, true );
						$topics[] = $decoded['name'];
					}
				}

				$post_data['topics'] = implode( ',', $topics );
			}

			// Fix slug.
			if ( isset( $post_data['slug'] ) && strpos( $data, '/' ) !== false ) {
				$slug              = explode( '/', $post_data['slug'] );
				$post_data['slug'] = end( $slug );
			}

			// Fetch only the widgets we want.
			if ( isset( $post_data['widgets'] ) && ! empty( $widget_labels ) ) {
				$widgets = [];
				foreach ( $post_data['widgets'] as $id => $widget ) {
					if ( isset( $widget['label'] ) && in_array( $widget['label'], $widget_labels, true ) ) {
						$widgets[ $widget['label'] . ' ' . $id ] = $widget;
					}
				}

				$post_data['widgets'] = $widgets;
			}

			// Serialize any post data arrays.
			foreach ( $post_data as &$value ) {
				if ( is_array( $value ) ) {
					$value = serialize( $value );
				}
			}

			$posts_array[] = $post_data;
		}

		return $posts_array;
	}
}
