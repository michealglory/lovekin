<?php
/**
 * Plugin Name: LoveKin
 * Description: Member profiles, courses, assessments, reports, and funding requests for LoveKin.
 * Version: 1.0.0
 * Author: Michael Medahunsi
 * Text Domain: lovekin
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LOVEKIN_VERSION' ) ) {
	define( 'LOVEKIN_VERSION', '1.0.0' );
}

if ( ! defined( 'LOVEKIN_DB_VERSION' ) ) {
	define( 'LOVEKIN_DB_VERSION', '1.0.0' );
}

if ( ! defined( 'LOVEKIN_PLUGIN_FILE' ) ) {
	define( 'LOVEKIN_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'LOVEKIN_PLUGIN_DIR' ) ) {
	define( 'LOVEKIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'LOVEKIN_PLUGIN_URL' ) ) {
	define( 'LOVEKIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-plugin.php';
require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-activator.php';

register_activation_hook( __FILE__, array( 'LoveKin_Activator', 'activate' ) );

function lovekin() {
	return LoveKin_Plugin::instance();
}

lovekin();
