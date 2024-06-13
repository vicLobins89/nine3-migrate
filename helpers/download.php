<?php
/**
 * This allows us to get the posted file path and format and convert
 * the saved PHP array into JSON/CSV and force a download
 *
 * @package nine3migrate
 */

// Load WP.
$load_path = preg_replace( '/wp-content.*$/', '', __DIR__ );
require_once( $load_path . 'wp-load.php' );

if ( ! isset( $_POST['export_filepath'] ) || ! isset( $_POST['export_format'] ) ) { // phpcs:ignore
	wp_die( esc_html__( 'No data file given, something went wrong.', 'nine3migrate' ) );
}

$filepath = $_POST['export_filepath']; // phpcs:ignore
$format   = $_POST['export_format']; // phpcs:ignore

// Load temp php array.
include $filepath;

// Set up filename.
$filename = sanitize_title( wp_unslash( get_bloginfo( 'name' ) ) ) . '_';
if ( isset( $export_data[0]['post_type'] ) ) {
	$filename .= $export_data[0]['post_type'] . '_';
} elseif ( isset( $export_data[0]['taxonomy'] ) ) {
	$filename .= $export_data[0]['taxonomy'] . '_';
}
$filename .= current_time( 'dmY-His' ) . '.' . $format;

// Set up downloadble file.
if ( $format === 'csv' ) {
	$csv = str_replace( '.php', '.csv', $filepath );
	$fp  = fopen( $csv, 'w' );
	fputcsv( $fp, array_keys( $export_data[0] ) );
	foreach ( $export_data as $fields ) {
		fputcsv( $fp, $fields );
	}
	fclose( $fp );

	$data         = file_get_contents( $csv );
	$content_type = 'text/csv';
} else {
	$data         = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
	$content_type = 'application/json';
}

// Force download.
header( "Content-disposition: attachment; filename=$filename" );
header( "Content-type: $content_type" );
echo $data; // phpcs:ignore

// Delete temp files.
if ( isset( $filepath ) ) {
	unlink( $filepath );
}
if ( isset( $csv ) ) {
	unlink( $csv );
}
update_option( 'nine3_export', '' );
update_option( 'nine3_export_terms', '' );
