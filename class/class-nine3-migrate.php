<?php
/**
 * The base class for setting up the migration plugin
 *
 * @package nine3migrate
 */

namespace nine3migrate;

/**
 * Let's get this party started
 */
class Nine3_Migrate {
	/**
	 * Let's set up some hooks and whatnot.
	 */
	public function __construct() {
		// Enqueue JS.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_styles' ] );

		// Menu hook.
		add_action( 'admin_menu', [ $this, 'register_page_menu' ] );

		// Settings sections/fields.
		add_action( 'admin_init', [ $this, 'register_migrate_settings' ] );

		// Allow some extra mime types.
		add_filter( 'upload_mimes', [ $this, 'add_mime_types' ] );

		// Ajax actions.
		add_action( 'wp_ajax_nine3-run-import', [ $this, 'run_import' ] );
		add_action( 'wp_ajax_nine3-run-export', [ $this, 'run_export' ] );

		// Reset settings.
		add_action( 'admin_init', [ $this, 'settings_form_submit' ] );
	}

	/**
	 * Enqueue plugin styles and scripts.
	 */
	public function enqueue_scripts_styles() {
		wp_enqueue_script( 'nine3-migrate-import-scripts', NINE3_MIGRATE_URI . '/js/migrate.js', [], time() );
		wp_enqueue_script( 'nine3-migrate-field-map', NINE3_MIGRATE_URI . '/js/field-map.js', [], time() );
		wp_enqueue_style( 'nine3-migrate-styles', NINE3_MIGRATE_URI . '/css/style.css', [], time() );
	}

	/**
	 * Register settings page
	 *
	 * @hook admin_menu
	 */
	public function register_page_menu() {
		add_menu_page(
			__( '93digital Migrate Tool', 'nine3migrate' ),
			__( '93digital Migrate', 'nine3migrate' ),
			'manage_options',
			'nine3-migrate-page',
			[ $this, 'render_page_options' ],
			'dashicons-feedback',
			150
		);

		add_submenu_page(
			'nine3-migrate-page',
			__( '93digital Export Tool', 'nine3migrate' ),
			__( 'Export Posts', 'nine3migrate' ),
			'manage_options',
			'admin.php?page=nine3-migrate-page&tab=export'
		);

		add_submenu_page(
			'nine3-migrate-page',
			__( '93digital Export Tool', 'nine3migrate' ),
			__( 'Export Terms', 'nine3migrate' ),
			'manage_options',
			'admin.php?page=nine3-migrate-page&tab=export-terms'
		);

		add_submenu_page(
			'nine3-migrate-page',
			__( '93digital Import Tool', 'nine3migrate' ),
			__( 'Import Posts', 'nine3migrate' ),
			'manage_options',
			'admin.php?page=nine3-migrate-page&tab=import'
		);

		add_submenu_page(
			'nine3-migrate-page',
			__( '93digital Import Tool', 'nine3migrate' ),
			__( 'Import Terms', 'nine3migrate' ),
			'manage_options',
			'admin.php?page=nine3-migrate-page&tab=import-terms'
		);

		add_submenu_page(
			'nine3-migrate-page',
			__( '93digital Import Tool', 'nine3migrate' ),
			__( 'HubSpot', 'nine3migrate' ),
			'manage_options',
			'admin.php?page=nine3-migrate-page&tab=hubspot'
		);
	}

	/**
	 * Nine3 Export Settings Fields
	 *
	 * @hook admin_init
	 */
	public function register_migrate_settings() {
		// Export settings fields array.
		$all_cpts      = array_merge(
			[ 'any' => 'any' ],
			get_post_types( [ 'public' => true ] ),
			get_post_types( [ '_builtin' => false ] )
		);
		$all_tax       = get_taxonomies();
		$export_fields = [
			[
				'name'  => 'export_cpt',
				'title' => __( 'Post Type', 'nine3migrate' ),
				'type'  => 'select',
				'value' => $all_cpts,
			],
			[
				'name'  => 'export_status',
				'title' => __( 'Post Status', 'nine3migrate' ),
				'type'  => 'select',
				'value' => [ 'Any', 'Publish', 'Draft', 'Private' ],
			],
			[
				'name'  => 'export_format',
				'title' => __( 'Format', 'nine3migrate' ),
				'type'  => 'select',
				'value' => [ 'JSON', 'CSV' ],
			],
			[
				'name'  => 'export_direction',
				'title' => __( 'Direction', 'nine3migrate' ),
				'type'  => 'select',
				'label' => __( 'Newest or oldest posts first? ASC = oldest, DESC = newest', 'nine3migrate' ),
				'value' => [ 'ASC', 'DESC' ],
			],
			[
				'name'  => 'export_limit',
				'title' => __( 'Limit', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'export_offset',
				'title' => __( 'Offset', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'export_private_meta',
				'title' => __( 'Private Meta', 'nine3migrate' ),
				'label' => __( 'If checked, private meta will also be added.', 'nine3migrate' ),
				'type'  => 'true_false',
			],
			[
				'name'  => 'export_post_ids',
				'title' => __( 'Post IDs', 'nine3migrate' ),
				'label' => __( 'If set, only these IDs will be exported (comma separeted list).', 'nine3migrate' ),
				'type'  => 'textarea',
			],
			[
				'name'  => 'export_taxonomy',
				'title' => __( 'Taxonomy', 'nine3migrate' ),
				'label' => __( 'Use this with the below textarea to select posts from a specific taxonomy containing term names.' ),
				'type'  => 'select',
				'value' => $all_tax,
			],
			[
				'name'  => 'export_term_names',
				'title' => __( 'Term Names', 'nine3migrate' ),
				'label' => __( 'If set, only posts containing these term names will be exported (comma separeted list).', 'nine3migrate' ),
				'type'  => 'textarea',
			],
			[
				'name'  => 'export_meta_query',
				'title' => __( 'Meta Query', 'nine3migrate' ),
				'label' => __( 'Add custom meta query, e.g.: key:value,key:value', 'nine3migrate' ),
				'type'  => 'textarea',
			],
			[
				'name'  => 'export_parent',
				'title' => __( 'Post Parent', 'nine3migrate' ),
				'label' => __( 'ID of post parent', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'export_convert_keys',
				'title' => __( 'Fetch URL for Keys', 'nine3migrate' ),
				'label' => __( 'If any of these meta keys are found they will be converted from ID to attachment/post URL (comma separeted list).', 'nine3migrate' ),
				'type'  => 'textarea',
			],
			[
				'name'  => 'export_exclude_meta',
				'title' => __( 'Exclude Meta Keys', 'nine3migrate' ),
				'label' => __( 'If meta keys contain any of the terms, they will be skipped (comma separeted list).', 'nine3migrate' ),
				'type'  => 'textarea',
			],
		];

		// Export terms settings fields array.
		$export_terms_fields = [
			[
				'name'  => 'export_tax',
				'title' => __( 'Taxonomy', 'nine3migrate' ),
				'type'  => 'select',
				'value' => get_taxonomies(),
			],
		];

		// Import settings fields array.
		$import_fields = [
			[
				'name'  => 'import_id',
				'title' => __( 'ID', 'nine3migrate' ),
				'label' => __( 'Set an ID for the import to target the \'nine3_migrate_before_insert_data\' filter.', 'nine3migrate' ),
				'type'  => 'text',
			],
			[
				'name'  => 'import_offset',
				'title' => __( 'Offset', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'import_cpt',
				'title' => __( 'Post Type', 'nine3migrate' ),
				'type'  => 'select',
				'value' => array_merge( get_post_types( [ 'public' => true ] ), get_post_types( [ '_builtin' => false ] ) ),
			],
			[
				'name'  => 'import_file',
				'title' => __( 'Import file', 'nine3migrate' ),
				'type'  => 'file',
			],
			[
				'name'  => 'import_fieldmap',
				'title' => __( 'Map Fields', 'nine3migrate' ),
				'label' => __( 'Select "Automatic" to keep all field keys as they are (WordPress only). Or select "Manual" to manually map them using a drag and drop interface.', 'nine3migrate' ),
				'type'  => 'select',
				'value' => [ 'Automatic', 'Manual' ],
			],
			[
				'name'  => 'import_update',
				'title' => __( 'Update', 'nine3migrate' ),
				'label' => __( 'Choose what to do if WordPress encounters a post with the same \'post_name\'. Default is to add new post.', 'nine3migrate' ),
				'type'  => 'select',
				'value' => [ 'Add new post', 'Update', 'Skip' ],
			],
			[
				'name'  => 'import_domain',
				'title' => __( 'File Domain', 'nine3migrate' ),
				'label' => __( 'If a domain is specified only files hosted there will be downloaded and replaced during content clean.', 'nine3migrate' ),
				'type'  => 'text',
			],
			[
				'name'  => 'import_parent',
				'title' => __( 'Post Parent', 'nine3migrate' ),
				'label' => __( 'ID of post parent to attach all posts to.', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'basic_auth_user',
				'title' => __( 'Basic Auth User', 'nine3migrate' ),
				'label' => __( 'If basic auth is set up, enter the credentials here.', 'nine3migrate' ),
				'type'  => 'text',
			],
			[
				'name'  => 'basic_auth_pass',
				'title' => __( 'Basic Auth Password', 'nine3migrate' ),
				'type'  => 'text',
			],
		];

		// Import terms settings fields array.
		$import_terms_fields = [
			[
				'name'  => 'import_tax',
				'title' => __( 'Taxonomy', 'nine3migrate' ),
				'type'  => 'select',
				'value' => get_taxonomies(),
			],
			[
				'name'  => 'import_tax_file',
				'title' => __( 'Import file', 'nine3migrate' ),
				'type'  => 'file',
			],
		];

		// Hubspot settings fields array.
		$hubspot_fields = [
			[
				'name'  => 'hubspot_api_key',
				'title' => __( 'API Key', 'nine3migrate' ),
				'type'  => 'text',
			],
			[
				'name'  => 'hubspot_blog_id',
				'title' => __( 'Blog ID', 'nine3migrate' ),
				'type'  => 'text',
			],
			[
				'name'  => 'hubspot_limit',
				'title' => __( 'Limit', 'nine3migrate' ),
				'label' => __( 'Number of items to return. Default: 20 | Max: 300', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'hubspot_offset',
				'title' => __( 'Offset', 'nine3migrate' ),
				'label' => __( 'The offset to start returning rows from.', 'nine3migrate' ),
				'type'  => 'number',
			],
			[
				'name'  => 'hubspot_fields',
				'title' => __( 'Fields', 'nine3migrate' ),
				'label' => __( 'Comma separated list of HubSpot post fields you\'d like to include in the export..', 'nine3migrate' ),
				'type'  => 'textarea',
			],
			[
				'name'  => 'hubspot_widgets',
				'title' => __( 'Widgets', 'nine3migrate' ),
				'label' => __( 'Comma separated list of widgets you\'d like to include in the export.', 'nine3migrate' ),
				'type'  => 'textarea',
			],
		];

		// Set up array for registering settings.
		$settings_array = [
			[
				'option_group' => 'nine3_export_group',
				'option_name'  => 'nine3_export',
				'option_title' => __( 'Export Settings', 'nine3migrate' ),
				'page_slug'    => 'admin.php?page=nine3-migrate-page&tab=export',
				'fields'       => $export_fields,
			],
			[
				'option_group' => 'nine3_export_group',
				'option_name'  => 'nine3_export_terms',
				'option_title' => __( 'Export Settings', 'nine3migrate' ),
				'page_slug'    => 'admin.php?page=nine3-migrate-page&tab=export-terms',
				'fields'       => $export_terms_fields,
			],
			[
				'option_group' => 'nine3_import_group',
				'option_name'  => 'nine3_import',
				'option_title' => __( 'Import Settings', 'nine3migrate' ),
				'page_slug'    => 'admin.php?page=nine3-migrate-page&tab=import',
				'fields'       => $import_fields,
				'args'         => [ 'sanitize_callback' => [ $this, 'handle_file_upload' ] ],
			],
			[
				'option_group' => 'nine3_import_group',
				'option_name'  => 'nine3_import_terms',
				'option_title' => __( 'Import Settings', 'nine3migrate' ),
				'page_slug'    => 'admin.php?page=nine3-migrate-page&tab=import-terms',
				'fields'       => $import_terms_fields,
				'args'         => [ 'sanitize_callback' => [ $this, 'handle_file_upload' ] ],
			],
			[
				'option_group' => 'nine3_hubspot_group',
				'option_name'  => 'nine3_hubspot',
				'option_title' => __( 'HubSpot Settings', 'nine3migrate' ),
				'page_slug'    => 'admin.php?page=nine3-migrate-page&tab=hubspot',
				'fields'       => $hubspot_fields,
			],
		];

		// Loop through settings and register them.
		foreach ( $settings_array as $value ) {
			$args = isset( $value['args'] ) ? $value['args'] : [];

			register_setting(
				$value['option_group'],
				$value['option_name'],
				$args
			);

			add_settings_section(
				$value['option_group'] . '_section',
				$value['option_title'],
				null,
				$value['page_slug']
			);

			$this->add_settings(
				$value['option_name'],
				$value['page_slug'],
				$value['option_group'] . '_section',
				$value['fields']
			);
		}
	}

	/**
	 * Render plugin tabs / pages
	 */
	public function render_page_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html_e( 'You do not have sufficient permissions to access this page.', 'nine3migrate' ) );
		}

		// Get the active tab from the $_GET param.
		$default_tab = null;
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $default_tab; // phpcs:ignore

		?>
		<div class="wrap" id="nine3-migrate">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="?page=nine3-migrate-page" class="nav-tab <?php echo $tab === null ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Reset', 'nine3migrate' ); ?></a>
				<a href="?page=nine3-migrate-page&tab=export" class="nav-tab <?php echo $tab === 'export' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Export Posts', 'nine3migrate' ); ?></a>
				<a href="?page=nine3-migrate-page&tab=export-terms" class="nav-tab <?php echo $tab === 'export-terms' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Export Terms', 'nine3migrate' ); ?></a>
				<a href="?page=nine3-migrate-page&tab=import" class="nav-tab <?php echo $tab === 'import' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import Posts', 'nine3migrate' ); ?></a>
				<a href="?page=nine3-migrate-page&tab=import-terms" class="nav-tab <?php echo $tab === 'import-terms' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import Terms', 'nine3migrate' ); ?></a>
				<a href="?page=nine3-migrate-page&tab=hubspot" class="nav-tab <?php echo $tab === 'hubspot' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'HubSpot', 'nine3migrate' ); ?></a>
			</nav>

			<div class="tab-content">
			<?php
			switch ( $tab ) :
				case 'export':
					$nine3_export = new Nine3_Export();
					break;
				case 'export-terms':
					$nine3_export_terms = new Nine3_Export_Terms();
					break;
				case 'import':
					$nine3_import = new Nine3_Import();
					break;
				case 'import-terms':
					$nine3_import_terms = new Nine3_Import_Terms();
					break;
				case 'hubspot':
					$nine3_hubspot = new Nine3_Hubspot();
					break;
				default:
					ob_start();
					?>
					<div class="wrap">
						<p><?php esc_html_e( 'Reset all settings and delete any generated plugin files.', 'nine3migrate' ); ?></p>
						<form method="get" action="<?php echo esc_url( admin_url( '/admin.php?page=nine3-migrate-page' ) ); ?>">
							<input type="hidden" name="page" value="nine3-migrate-page">
							<input type="hidden" name="reset" value="1">
							<button type="submit" class="button"><?php esc_html_e( 'Reset Settings', 'nine3migrate' ); ?></button>
						</form>
						<br>

						<p><?php esc_html_e( 'Delete ALL previously migrated posts.', 'nine3migrate' ); ?></p>
						<form method="get" action="<?php echo esc_url( admin_url( '/admin.php?page=nine3-migrate-page' ) ); ?>">
							<input type="hidden" name="page" value="nine3-migrate-page">
							<input type="hidden" name="delete" value="1">
							<button type="submit" class="button"><?php esc_html_e( 'Delete Migrated Posts', 'nine3migrate' ); ?></button>
						</form>
					</div>
					<?php
					echo ob_get_clean(); // phpcs:ignore
					break;
			endswitch;
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Function to add settings fields into a settings group
	 *
	 * @param string $option the option name.
	 * @param string $page the string for the settings page.
	 * @param string $section specific section of settings group.
	 * @param array  $fields array of fields to loop through and render.
	 */
	public function add_settings( $option, $page, $section, array $fields ) {
		// Loop and add setting.
		foreach ( $fields as $field ) {
			add_settings_field(
				$field['name'],
				$field['title'],
				[ $this, 'render_field' ],
				$page,
				$section,
				[
					'option' => $option,
					'name'   => $field['name'],
					'label'  => isset( $field['label'] ) ? $field['label'] : false,
					'title'  => $field['title'],
					'type'   => $field['type'],
					'value'  => isset( $field['value'] ) ? $field['value'] : false,
				]
			);
		}
	}

	/**
	 * Render the HTML for settings
	 * Callback for: add_settings_field
	 *
	 * @param array $args the value passed from callback.
	 */
	public function render_field( array $args ) {
		$options     = get_option( $args['option'] );
		$option_name = esc_html( $args['option'] ) . '[' . esc_html( $args['name'] ) . ']';

		if ( ! empty( $args['label'] ) ) :
			?>
			<label for="<?php echo $option_name; // phpcs:ignore ?>"><?php echo $args['label'] ;?></label>
			<br>
			<?php
		endif;

		switch ( $args['type'] ) :
			case 'select':
				?>
				<select name="<?php echo $option_name; // phpcs:ignore ?>">
					<option value=""><?php echo esc_html__( 'Select', 'nine3migrate' ) . ' ' . esc_html( $args['title'] ); ?></option>
					<?php foreach ( $args['value'] as $val ) : ?>
						<option value="<?php echo $val; ?>" <?php echo ( isset( $options[ $args['name'] ] ) && $options[ $args['name'] ] === $val ) ? 'selected' : ''; ?>><?php echo esc_html( $val ); // phpcs:ignore ?></option>
					<?php endforeach; ?>
				</select>
				<?php
				break;
			case 'number':
				?>
				<input type='number' min="0" name='<?php echo $option_name; // phpcs:ignore ?>' value='<?php echo isset( $options[ $args['name'] ] ) ? esc_html( $options[ $args['name'] ] ) : ''; ?>'>
				<?php
				break;
			case 'text':
				?>
				<input type='text' name='<?php echo $option_name; // phpcs:ignore ?>' value='<?php echo isset( $options[ $args['name'] ] ) ? esc_html( $options[ $args['name'] ] ) : ''; ?>'>
				<?php
				break;
			case 'textarea':
				?>
				<textarea
					name="<?php echo $option_name; // phpcs:ignore ?>"
					cols="50"
					rows="6"><?php echo isset( $options[ $args['name'] ] ) ? esc_html( $options[ $args['name'] ] ) : ''; ?></textarea>
				<?php
				break;
			case 'true_false':
				?>
				<input type="checkbox" name="<?php echo $option_name; // phpcs:ignore ?>" value="1" <?php echo isset( $options[ $args['name'] ] ) ? 'checked' : ''; ?>>
				<?php
				break;
			case 'file':
				$files = list_files( NINE3_IMPORT_PATH );
				?>
				<label for="<?php echo $option_name; // phpcs:ignore ?>"><?php esc_html_e( 'Select existing file', 'nine3migrate' ); ?></label><br>
				<select name="<?php echo $option_name; // phpcs:ignore ?>">
					<option value=""><?php esc_html_e( 'Select file', 'nine3migrate' ); ?></option>
					<?php foreach ( $files as $file ) : ?>
						<option value="<?php echo $file; ?>" <?php echo ( isset( $options[ $args['name'] ] ) && $options[ $args['name'] ] === $file ) ? 'selected' : ''; ?>><?php echo str_replace( NINE3_IMPORT_PATH, '', $file ); // phpcs:ignore ?></option>
					<?php endforeach; ?>
				</select>
				<br><br>
				<label for="<?php echo $args['name'];  // phpcs:ignore ?>"><?php esc_html_e( 'Or upload a new one', 'nine3migrate' ); ?></label><br>
				<input type="file" name="<?php echo $args['name'];  // phpcs:ignore ?>" accept="application/json,text/csv"><br>
				<br>
				<?php
				break;
		endswitch;

		?>
		<br>
		<?php
	}

	/**
	 * Handle the file upload
	 *
	 * @param array $options the array of page options saved.
	 */
	public function handle_file_upload( $options ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		if ( ! empty( $_FILES ) ) {
			foreach ( $_FILES as $name => $file ) {
				if ( ( strpos( $name, 'import_file' ) !== false || strpos( $name, 'import_tax_file' ) !== false ) && $file['tmp_name'] ) {
					$upload_args = [
						'test_form' => false,
						'mimes'     => [
							'json' => 'application/json',
							'csv'  => 'text/csv',
						],
					];

					// Upload file and change DIR.
					add_filter( 'upload_dir', [ $this, 'upload_dir' ] );
					$urls = wp_handle_upload( $file, $upload_args );
					remove_filter( 'upload_dir', [ $this, 'upload_dir' ] );

					if ( isset( $urls['file'] ) ) {
						$temp = $urls['file'];
						$options[ $name ] = $temp;
					}
				}
			}
		}

		return $options;
	}

	/**
	 * Filter function to change the upload folder for JSON uploads.
	 *
	 * @param array $upload the array of upload paths and urls.
	 */
	public function upload_dir( $upload ) {
		// Create DIR if not exists.
		if ( ! is_dir( NINE3_IMPORT_PATH ) ) {
			wp_mkdir_p( NINE3_IMPORT_PATH );
		}

		$upload['path']   = NINE3_IMPORT_PATH;
		$upload['url']    = NINE3_IMPORT_URI;
		$upload['subdir'] = '/nine3-import';

		return $upload;
	}

	/**
	 * Add some mime types.
	 *
	 * @param array $mimes mime types keyed by file extension.
	 */
	public function add_mime_types( $mimes ) {
		$mimes['json'] = 'application/json';
		$mimes['csv']  = 'text/csv';
		$mimes['rtf']  = 'application/rtf';
		return $mimes;
	}

	/**
	 * Gets post type and runs the import for each cpt.
	 */
	public function run_import() {
		// Get/set data array.
		if ( ! empty( $_REQUEST['data'] ) ) {
			$data = json_decode( wp_unslash( $_REQUEST['data'] ), true ); // phpcs:ignore
			if ( isset( $data['taxonomy'] ) ) {
				$run_import = new Nine3_Import_Terms_Process( array_filter( $data ) );
			} else {
				$run_import = new Nine3_Import_Process( array_filter( $data ) );
			}
		}
	}

	/**
	 * Gets post type and runs the export for each cpt.
	 */
	public function run_export() {
		// Get/set data array.
		if ( ! empty( $_REQUEST['data'] ) ) {
			$data = json_decode( wp_unslash( $_REQUEST['data'] ), true ); // phpcs:ignore
			$run_export = new Nine3_Export_Process( array_filter( $data ) );
		}
	}

	/**
	 * Resets all plugin settings.
	 */
	public function settings_form_submit() {
		if ( isset( $_GET['reset'] ) && $_GET['reset'] ) {
			update_option( 'nine3_import', '' );
			update_option( 'nine3_export', '' );
			update_option( 'nine3_import_terms', '' );
			update_option( 'nine3_export_terms', '' );
			update_option( 'nine3_hubspot', '' );
			$this->delete_directory( NINE3_EXPORT_PATH );
			$this->delete_directory( NINE3_IMPORT_PATH );
		}

		if ( isset( $_GET['delete'] ) && $_GET['delete'] ) {
			$post_args = [
				'posts_per_page' => -1,
				'meta_key'       => 'nine3_migrate',
			];
			$query = new \WP_Query( $post_args );
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) :
					$query->the_post();
					$the_id = get_the_ID();
					wp_delete_post( $the_id, true );
				endwhile;
			}
			wp_reset_postdata();
		}
	}

	/**
	 * Recursively deletes directory and files
	 *
	 * @param string $dir the path to delete.
	 */
	private function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
				return true;
		}

		if ( ! is_dir( $dir ) ) {
			return unlink( $dir );
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( $item == '.' || $item == '..' ) {
					continue;
			}

			if ( ! $this->delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
				return false;
			}
		}

		return rmdir( $dir );
	}
}
