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

		if ( empty( $_FILES['archive_file']['name'] ) ) {
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
			exit;
		}

		$settings = get_option( 'lovekin_upload_settings', array() );
		$allowed  = $settings['allowed_types'] ?? array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		$max_mb   = isset( $settings['max_file_size_mb'] ) ? (int) $settings['max_file_size_mb'] : 10;

		$file = $_FILES['archive_file'];
		$file_name = sanitize_file_name( $file['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$check     = wp_check_filetype( $file_name );
		if ( empty( $check['ext'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'type' ), wp_get_referer() ) );
			exit;
		}

		if ( ! in_array( $file_ext, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'type' ), wp_get_referer() ) );
			exit;
		}

		if ( $file['size'] > ( $max_mb * 1024 * 1024 ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'size' ), wp_get_referer() ) );
			exit;
		}

		$user_id   = get_current_user_id();
		$folder    = isset( $_POST['folder'] ) ? sanitize_text_field( wp_unslash( $_POST['folder'] ) ) : '';
		$upload_dir = LOVEKIN_PLUGIN_DIR . 'uploads/archive/' . $user_id . '/';
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$target = $upload_dir . time() . '-' . $file_name;
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'upload' ), wp_get_referer() ) );
			exit;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_archive_files';
		$wpdb->insert(
			$table,
			array(
				'user_id'   => $user_id,
				'file_name' => $file_name,
				'file_path' => $target,
				'file_type' => $file_ext,
				'file_size' => (int) $file['size'],
				'folder'    => $folder,
				'created_at'=> current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'lk_archive' => 'uploaded' ), wp_get_referer() ) );
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
				if ( file_exists( $file->file_path ) ) {
					unlink( $file->file_path );
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

		if ( ! file_exists( $file->file_path ) ) {
			return;
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $file->file_name ) );
		header( 'Content-Length: ' . filesize( $file->file_path ) );
		readfile( $file->file_path );
		exit;
	}
}
