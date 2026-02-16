<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Activator {
	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-cpt-course.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-cpt-assessment.php';
		require_once LOVEKIN_PLUGIN_DIR . 'includes/class-lovekin-roles.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$tables = array();

		$tables[] = "CREATE TABLE {$prefix}lk_relationships (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			primary_user_id bigint(20) unsigned NOT NULL,
			secondary_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY primary_secondary (primary_user_id, secondary_user_id),
			KEY primary_user_id (primary_user_id),
			KEY secondary_user_id (secondary_user_id)
		) {$charset_collate};";

		$tables[] = "CREATE TABLE {$prefix}lk_attempts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			assessment_id bigint(20) unsigned NOT NULL,
			score decimal(5,2) NOT NULL DEFAULT 0.00,
			answers_json longtext NOT NULL,
			remark text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY assessment_id (assessment_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$tables[] = "CREATE TABLE {$prefix}lk_funding_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			format varchar(10) NOT NULL,
			purpose text NOT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			account_details text NOT NULL,
			membership_code varchar(100) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			admin_notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$tables[] = "CREATE TABLE {$prefix}lk_documents (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text NULL,
			file_path text NOT NULL,
			file_type varchar(50) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			category varchar(50) NOT NULL DEFAULT 'other',
			visibility varchar(20) NOT NULL DEFAULT 'all',
			uploaded_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			version varchar(50) NOT NULL DEFAULT '1',
			PRIMARY KEY  (id),
			KEY category (category),
			KEY visibility (visibility),
			KEY uploaded_by (uploaded_by),
			KEY created_at (created_at)
		) {$charset_collate};";

		$tables[] = "CREATE TABLE {$prefix}lk_archive_files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			file_name varchar(255) NOT NULL,
			file_path text NOT NULL,
			file_type varchar(50) NOT NULL,
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			folder text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		if ( false === get_option( 'lovekin_remark_bands' ) ) {
			$default_bands = array(
				array(
					'min'    => 0,
					'max'    => 49,
					'label'  => 'Needs Improvement',
					'remark' => 'Needs Improvement',
					'color'  => '#ef4444',
				),
				array(
					'min'    => 50,
					'max'    => 74,
					'label'  => 'Progressing',
					'remark' => 'Progressing',
					'color'  => '#f59e0b',
				),
				array(
					'min'    => 75,
					'max'    => 100,
					'label'  => 'Excellent',
					'remark' => 'Excellent',
					'color'  => '#10b981',
				),
			);
			add_option( 'lovekin_remark_bands', $default_bands );
		}

		if ( false === get_option( 'lovekin_upload_settings' ) ) {
			$upload_settings = array(
				'allowed_types'     => array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' ),
				'max_file_size_mb'  => 10,
				'archive_quota_mb'  => 100,
				'document_quota_mb' => 250,
			);
			add_option( 'lovekin_upload_settings', $upload_settings );
		}

		self::ensure_secure_uploads();
		LoveKin_Roles::register_roles();
		LoveKin_CPT_Course::register();
		LoveKin_CPT_Assessment::register();
		flush_rewrite_rules();
		update_option( 'lovekin_db_version', LOVEKIN_DB_VERSION );
	}

	/**
	 * Ensure secure uploads directory exists.
	 */
	private static function ensure_secure_uploads() {
		$uploads_dir = LOVEKIN_PLUGIN_DIR . 'uploads/';
		if ( ! file_exists( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}

		$htaccess_path = $uploads_dir . '.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			$rules = "Options -Indexes\n<FilesMatch \"\\.(php|php\\d|phtml)$\">\nDeny from all\n</FilesMatch>\n";
			file_put_contents( $htaccess_path, $rules );
		}
	}
}
