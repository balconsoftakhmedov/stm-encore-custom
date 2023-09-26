<?php
/*
 * Plugin Name:       STM Encore Custom
 * Description:       STM Encore Custom
 * Version:           1.0.0
 * Requires at least: 6.2.2
 * Requires PHP:      8.0
 * Author:            Stylemix
 * Author URI:        https://stylmix.net
 * Text Domain:       stm-encore-custom
 * Domain Path:       /languages
 */

namespace StmEncoreCustom;

use StmEncoreCustom\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function () {
		$class   = 'notice notice-error';
		$message = '<b>STM Encore Custom plugin</b> is not activated, please run <b>composer install</b>.';
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	} );

	return;
}

const STM_ENCORE_CUSTOM_FILE   = __FILE__;
const STM_ENCORE_CUSTOM_ACTION = 'stm_encore_custom_action';
const STM_ENCORE_CUSTOM_RUNNER = 'stm_encore_custom_runner';

require_once 'inc/helpers.php';
require_once 'vendor/autoload.php';

add_action( 'plugins_loaded', function () {
	if ( ! defined( 'STM_LISTINGS' ) ) {
		add_action( 'admin_notices', function () {
			$class   = 'notice notice-error';
			$message = '<b>Motors plugin</b> is not activated or installed! Encore plugin only compatible with <b>Motors – Car Dealer, Classifieds & Listing</b>.';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		} );

		return;
	}

	$settings    = Settings::instance();
	$bladerunner = new BladeRunner();
}, 11 );