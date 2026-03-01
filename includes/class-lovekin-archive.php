<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Archive {
	public static function init() {
		add_action( 'admin_post_lk_upload_archive', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_lk_delete_archive', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_nopriv_lk_upload_archive', array( __CLASS__, 'block_guest_submission' ) );
		add_action( 'init', array( __CLASS__, 'handle_download' ) );
	}

	public static function block_guest_submission() {
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	public static function handle_upload() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! current_user_can( 'lk_manage_archive' ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		check_admin_referer( 'lk_upload_archive' );
		$redirect_base = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( empty( $_FILES['archive_file']['name'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'missing' ), $redirect_base ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$settings = get_option( 'lovekin_upload_settings', array() );
		$allowed  = isset( $settings['allowed_types'] ) && is_array( $settings['allowed_types'] ) ? array_filter( array_map( 'strtolower', $settings['allowed_types'] ) ) : array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		if ( empty( $allowed ) ) {
			$allowed = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		}
		$allowed_mimes = self::get_allowed_mimes( $allowed );
		$max_mb        = isset( $settings['max_file_size_mb'] ) ? max( 1, (int) $settings['max_file_size_mb'] ) : 10;
		$quota_mb      = isset( $settings['archive_quota_mb'] ) ? max( 1, (int) $settings['archive_quota_mb'] ) : 100;

		$file = $_FILES['archive_file'];
		$file_name = sanitize_file_name( $file['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$check     = wp_check_filetype_and_ext( $file['tmp_name'], $file_name, $allowed_mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			$check = wp_check_filetype( $file_name, $allowed_mimes );
		}
		if ( empty( $check['ext'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'type' ), $redirect_base ) );
			exit;
		}

		if ( ! in_array( $file_ext, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'type' ), $redirect_base ) );
			exit;
		}

		if ( $file['size'] > ( $max_mb * 1024 * 1024 ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'size' ), $redirect_base ) );
			exit;
		}

		$user_id = get_current_user_id();
		global $wpdb;
		$table       = $wpdb->prefix . 'lk_archive_files';
		$total_bytes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(file_size), 0) FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);
		$quota_bytes = $quota_mb * 1024 * 1024;
		if ( $quota_bytes > 0 && ( $total_bytes + (int) $file['size'] ) > $quota_bytes ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'quota' ), $redirect_base ) );
			exit;
		}

		$folder = isset( $_POST['folder'] ) ? sanitize_text_field( wp_unslash( $_POST['folder'] ) ) : '';
		self::ensure_private_upload_structure( $user_id );

		add_filter( 'upload_dir', array( __CLASS__, 'filter_archive_upload_dir' ) );
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'mimes'                    => $allowed_mimes,
				'unique_filename_callback' => array( __CLASS__, 'generate_private_filename' ),
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_archive_upload_dir' ) );

		if ( isset( $upload['error'] ) || empty( $upload['file'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'upload' ), $redirect_base ) );
			exit;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'   => $user_id,
				'file_name' => $file_name,
				'file_path' => $upload['file'],
				'file_type' => $file_ext,
				'file_size' => (int) $file['size'],
				'folder'    => $folder,
				'created_at'=> current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $inserted ) {
			if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
				@unlink( $upload['file'] );
			}
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'db' ), $redirect_base ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'uploaded' ), $redirect_base ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		check_admin_referer( 'lk_delete_archive' );
		$file_id = isset( $_GET['file_id'] ) ? absint( $_GET['file_id'] ) : 0;

		if ( $file_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_archive_files';
			$file  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $file_id ) );
			if ( $file && (int) $file->user_id === get_current_user_id() ) {
				$file_path = self::resolve_file_path( $file->file_path );
				if ( $file_path && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				$wpdb->delete( $table, array( 'id' => $file_id ), array( '%d' ) );
			}
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	public static function get_user_files( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_archive_files';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);
	}

	public static function handle_download() {
		$file_id = isset( $_GET['lk_archive_download'] ) ? absint( $_GET['lk_archive_download'] ) : 0;
		if ( ! $file_id || ! is_user_logged_in() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_archive_files';
		$file  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $file_id ) );
		if ( ! $file || (int) $file->user_id !== get_current_user_id() ) {
			return;
		}

		$file_path = self::resolve_file_path( $file->file_path );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file->file_name ) );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}

	public static function filter_archive_upload_dir( $dirs ) {
		$user_id       = max( 1, absint( get_current_user_id() ) );
		$private_subdir = '/lovekin/private/archive/' . $user_id;
		$dirs['subdir'] = $private_subdir;
		$dirs['path']   = $dirs['basedir'] . $private_subdir;
		$dirs['url']    = $dirs['baseurl'] . $private_subdir;
		return $dirs;
	}

	public static function generate_private_filename( $dir, $name, $ext ) {
		$ext = is_string( $ext ) ? $ext : '';
		return wp_generate_uuid4() . '-' . wp_rand( 1000, 9999 ) . $ext;
	}

	private static function get_allowed_mimes( $allowed_exts ) {
		$mime_map = array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'txt'  => 'text/plain',
			'csv'  => 'text/csv',
			'zip'  => 'application/zip',
			'xls'  => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'  => 'application/vnd.ms-powerpoint',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		);
		$allowed_mimes = array();
		foreach ( (array) $allowed_exts as $ext ) {
			$ext = strtolower( sanitize_key( $ext ) );
			if ( isset( $mime_map[ $ext ] ) ) {
				$allowed_mimes[ $ext ] = $mime_map[ $ext ];
			}
		}
		if ( empty( $allowed_mimes ) ) {
			$allowed_mimes = array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
			);
		}
		return $allowed_mimes;
	}

	private static function ensure_private_upload_structure( $user_id ) {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		$base_dir    = trailingslashit( $uploads['basedir'] ) . 'lovekin';
		$private_dir = $base_dir . '/private';
		$archive_dir = $private_dir . '/archive';
		$user_dir    = $archive_dir . '/' . absint( $user_id );

		self::ensure_directory( $base_dir, false );
		self::ensure_directory( $private_dir, true );
		self::ensure_directory( $archive_dir, true );
		self::ensure_directory( $user_dir, true );
	}

	private static function ensure_directory( $dir, $private = false ) {
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		if ( ! $private ) {
			return;
		}

		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$rules = "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
			@file_put_contents( $htaccess_file, $rules );
		}
	}

	private static function resolve_file_path( $stored_path ) {
		$stored_path = is_string( $stored_path ) ? trim( $stored_path ) : '';
		if ( '' === $stored_path ) {
			return '';
		}

		if ( file_exists( $stored_path ) ) {
			return $stored_path;
		}

		$candidates = array();
		$uploads    = wp_upload_dir();
		$plugin_upload_base = trailingslashit( LOVEKIN_PLUGIN_DIR ) . 'uploads/';

		if ( filter_var( $stored_path, FILTER_VALIDATE_URL ) ) {
			$path = (string) wp_parse_url( $stored_path, PHP_URL_PATH );
			if ( $path ) {
				$wp_content_pos = strpos( $path, '/wp-content/' );
				if ( false !== $wp_content_pos ) {
					$relative = ltrim( substr( $path, $wp_content_pos + 1 ), '/' );
					$candidates[] = trailingslashit( ABSPATH ) . $relative;
				}
			}
		}

		if ( 0 !== strpos( $stored_path, '/' ) ) {
			$candidates[] = trailingslashit( $uploads['basedir'] ) . ltrim( $stored_path, '/' );
			$candidates[] = trailingslashit( $plugin_upload_base ) . ltrim( $stored_path, '/' );
		}

		$candidates[] = trailingslashit( $uploads['basedir'] ) . ltrim( str_replace( $uploads['baseurl'], '', $stored_path ), '/' );
		$candidates[] = trailingslashit( $plugin_upload_base ) . ltrim( str_replace( trailingslashit( LOVEKIN_PLUGIN_URL ) . 'uploads/', '', $stored_path ), '/' );

		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( $candidate && file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}
}
