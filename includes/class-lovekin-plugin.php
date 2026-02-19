<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Plugin {
	/**
	 * @var LoveKin_Plugin
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return LoveKin_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	private function init() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-roles.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-cpt-course.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-cpt-assessment.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-admin-menus.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-relationships.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-assessments.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-reports.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-funding.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-documents.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-archive.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-settings.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-tools.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-shortcodes.php';

		add_action( 'init', array( 'LoveKin_Activator', 'maybe_upgrade' ), 20 );

		LoveKin_Roles::init();
		LoveKin_CPT_Course::init();
		LoveKin_CPT_Assessment::init();
		LoveKin_Admin_Menus::init();
		LoveKin_Relationships::init();
		LoveKin_Assessments::init();
		LoveKin_Reports::init();
		LoveKin_Funding::init();
		LoveKin_Documents::init();
		LoveKin_Archive::init();
		LoveKin_Settings::init();
		LoveKin_Tools::init();
		LoveKin_Shortcodes::init();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'lovekin', false, dirname( plugin_basename( LOVEKIN_PLUGIN_FILE ) ) . '/languages' );
	}
}
