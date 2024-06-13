<?php
/**
 * Plugin Name: 93digital Migration Tool
 * Plugin URI: https://93digital.co.uk/
 * Description: Imports content from a JSON/CSV File. And exports WordPress content to JSON/CSV File. May require some custom development on a site by site basis.. but hopefully not much.
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: 93digital
 * Author URI: https://93digital.co.uk/
 * License: GPLv2 or later
 * Text Domain: nine3migrate
 *
 * @package nine3migrate
 */

namespace nine3migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Base filepath and URL constants, without a trailing slash.
define( 'NINE3_MIGRATE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'NINE3_MIGRATE_URI', plugins_url( plugin_basename( __DIR__ ) ) );
define( 'NINE3_EXPORT_PATH', wp_upload_dir()['basedir'] . '/nine3-export' );
define( 'NINE3_IMPORT_PATH', wp_upload_dir()['basedir'] . '/nine3-import' );
define( 'NINE3_IMPORT_URI', wp_upload_dir()['baseurl'] . '/nine3-import' );

// Include the Composer autoloader.
@include_once __DIR__ . '/vendor/autoload.php'; // phpcs:ignore

/**
 * 'spl_autoload_register' callback function.
 * Autoloads all the required plugin classes, found in the /classes directory (relative to the plugin's root).
 *
 * @param string $class The name of the class being instantiated inculding its namespaces.
 */
function autoloader( $class ) {
	// $class returns the classname including any namespaces - this removes the namespace so we can locate the class's file.
	$raw_class = explode( '\\', $class );
	$filename  = str_replace( '_', '-', strtolower( end( $raw_class ) ) );

	$filepath = __DIR__ . '/class/class-' . $filename . '.php';

	if ( file_exists( $filepath ) ) {
		include_once $filepath;
	}
}
spl_autoload_register( __NAMESPACE__ . '\autoloader' );

/**
 * Init class.
 */
$nine3_migrate = new Nine3_Migrate();
