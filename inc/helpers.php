<?php

namespace StmEncoreCustom\Inc;

use Exception;
use StmEncoreCustom\Admin\Settings;

function dd( ...$vars ): void {
	if ( count( $vars ) === 1 ) {
		$vars = $vars[0];
	}

	echo '<pre>';
	var_dump( $vars );
	echo '</pre>';
	die();
}

function d( ...$vars ): void {
	if ( count( $vars ) === 1 ) {
		$vars = $vars[0];
	}

	echo '<pre>';
	var_dump( $vars );
	echo '</pre>';
}

function get_settings(): array {
	return get_option( Settings::OPTION_NAME, [] );
}

function get_available_attributes(): array {
	$available_attributes = [
		'price'               => esc_html__( 'Price', 'stm-encore-custom' ),
		'sale_price'          => esc_html__( 'Sale Price', 'stm-encore-custom' ),
		'stock_number'        => esc_html__( 'Stock Number', 'stm-encore-custom' ),
		'additional_features' => esc_html__( 'Features', 'stm-encore-custom' ),
		'vin_number'          => esc_html__( 'VIN', 'stm-encore-custom' ),
		'gallery'             => esc_html__( 'Gallery', 'stm-encore-custom' ),
		'city_mpg'            => esc_html__( 'City MPG', 'stm-encore-custom' ),
		'highway_mpg'         => esc_html__( 'Highway MPG', 'stm-encore-custom' ),
		'registration_date'   => esc_html__( 'Registration Date', 'stm-encore-custom' ),
	];

	return apply_filters( 'stm_encore_custom_available_attributes', $available_attributes );
}

function get_listings_taxonomies( $origin = null ): array {
	$attributes = stm_listings_attributes();

	if ( empty( $attributes ) ) {
		return [];
	}

	if ( 'origin' === $origin ) {
		return $attributes;
	}

	//array column where key is slug and single_name is value
	return array_column( $attributes, 'single_name', 'slug' );
}

function get_atts_and_taxes(): array {
	$atts = get_listings_attributes();
	$taxs = get_listings_taxonomies();

	return array_merge( $atts, $taxs );
}

function get_listings_attributes( $placeholder = false ): array {
	$collected_attributes = [];
	$available_attributes = get_available_attributes();
	$collected_attributes = array_merge( $collected_attributes, $available_attributes );
	$attributes           = get_listings_taxonomies();
	$collected_attributes = array_merge( $collected_attributes, $attributes );

	if ( $placeholder ) {
		$collected_attributes = array_merge( [ '' => $placeholder ], $collected_attributes );
	}

	return apply_filters( 'stm_encore_custom_listings_attributes', $collected_attributes );
}

/**
 * @throws Exception
 */
function parse_scv( $file, $separator = ',' ): array {
	$csv     = str_getcsv( $file, "\n" );
	$headers = array_map( 'trim', str_getcsv( $csv[0] ) );

	$file = array_slice( $csv, 1 );

	$data = array();
	foreach ( $file as $key => $row ) {
		$row     = str_getcsv( $row, $separator );
		$rowData = array_combine( $headers, array_map( 'trim', $row ) );
		$data[]  = $rowData;
	}

	return $data;
}

/**
 * @throws Exception
 */
function get_fields_map(): array {
	$settings = get_settings();

	$fields = $settings['fields'] ?: [];

	if ( empty( $fields ) ) {
		throw new Exception( 'No fields found' );
	}

	$fields = array_map( function ( $field ) {
		return array_map( 'trim', $field );
	}, $fields );

	return array_column( $fields, null, 'key' );
}

function get_map_setting( $option ) {
	$settings = get_settings();

	if ( ! isset( $settings[ $option ] ) ) {
		return null;
	}

	return $settings[ $option ];
}

function get_post_id_by_meta( $key, $value ): ?int {
	global $wpdb;

	$sql = $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
		$key,
		$value
	);

	$post_id = $wpdb->get_var( $sql );

	if ( $post_id ) {
		return (int) $post_id;
	}

	return null;
}

function stm_mime_content_type( $filename ): string {
	$mime_types = array(
		'txt'  => 'text/plain',
		'htm'  => 'text/html',
		'html' => 'text/html',
		'php'  => 'text/html',
		'css'  => 'text/css',
		'js'   => 'application/javascript',
		'json' => 'application/json',
		'xml'  => 'application/xml',
		'swf'  => 'application/x-shockwave-flash',
		'flv'  => 'video/x-flv',

		// images
		'png'  => 'image/png',
		'jpe'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg'  => 'image/jpeg',
		'gif'  => 'image/gif',
		'bmp'  => 'image/bmp',
		'ico'  => 'image/vnd.microsoft.icon',
		'tiff' => 'image/tiff',
		'tif'  => 'image/tiff',
		'svg'  => 'image/svg+xml',
		'svgz' => 'image/svg+xml',

		// archives
		'zip'  => 'application/zip',
		'rar'  => 'application/x-rar-compressed',
		'exe'  => 'application/x-msdownload',
		'msi'  => 'application/x-msdownload',
		'cab'  => 'application/vnd.ms-cab-compressed',

		// audio/video
		'mp3'  => 'audio/mpeg',
		'qt'   => 'video/quicktime',
		'mov'  => 'video/quicktime',

		// adobe
		'pdf'  => 'application/pdf',
		'psd'  => 'image/vnd.adobe.photoshop',
		'ai'   => 'application/postscript',
		'eps'  => 'application/postscript',
		'ps'   => 'application/postscript',

		// MS Office
		'doc'  => 'application/msword',
		'rtf'  => 'application/rtf',
		'xls'  => 'application/vnd.ms-excel',
		'ppt'  => 'application/vnd.ms-powerpoint',

		// open office
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
	);

	$_filename = explode( '.', $filename );
	$ext       = strtolower( array_pop( $_filename ) );
	if ( array_key_exists( $ext, $mime_types ) ) {
		return $mime_types[ $ext ];
	}

	return 'application/octet-stream';
}

/**
 * @throws Exception
 */
function stm_store_file( $file_url, $post_parent ): ?int {
	if ( empty( $file_url ) ) {
		throw new Exception( 'File url is empty' );
	}

	$file_name     = basename( $file_url ); // Get the filename with extension
	$file_contents = file_get_contents( $file_url );

	$mime = stm_mime_content_type( $file_name );
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );

	if ( ! $file_contents ) {
		throw new Exception( 'File contents is empty' );
	}

	$upload = wp_upload_bits( $file_name, null, $file_contents );

	if ( $upload['error'] ) {
		throw new Exception( $upload['error'] );
	}

	$attachment = array(
		'guid'           => $upload['url'],
		'post_mime_type' => $mime,
		'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
		'post_content'   => $file_url,
		'post_status'    => 'inherit',
		'post_parent'    => $post_parent,
	);

	$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

	if ( is_wp_error( $attach_id ) ) {
		throw new Exception( $attach_id->get_error_message() );
	}

	require_once( ABSPATH . 'wp-admin/includes/image.php' );

	wp_generate_attachment_metadata( $attach_id, $upload['file'] );

	return $attach_id;
}