<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Documents {
	public static function init() {
		add_action( 'admin_post_lk_upload_document', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_lk_delete_document', array( __CLASS__, 'handle_delete' ) );
		add_action( 'init', array( __CLASS__, 'handle_download' ) );
	}

	public static function handle_upload() {
		if ( ! current_user_can( 'lk_upload_documents' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_upload_document' );

		if ( empty( $_FILES['document_file']['name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents' ) );
			exit;
		}

		$settings = get_option( 'lovekin_upload_settings', array() );
		$allowed  = $settings['allowed_types'] ?? array( 'pdf', 'doc', 'docx' );
		$max_mb   = isset( $settings['max_file_size_mb'] ) ? (int) $settings['max_file_size_mb'] : 10;

		$file = $_FILES['document_file'];
		$file_name = sanitize_file_name( $file['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$check     = wp_check_filetype( $file_name );
		if ( empty( $check['ext'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents&lk_error=type' ) );
			exit;
		}

		if ( ! in_array( $file_ext, $allowed, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents&lk_error=type' ) );
			exit;
		}

		if ( $file['size'] > ( $max_mb * 1024 * 1024 ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents&lk_error=size' ) );
			exit;
		}

		$upload_dir = LOVEKIN_PLUGIN_DIR . 'uploads/documents/';
		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$target = $upload_dir . time() . '-' . $file_name;
		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents&lk_error=upload' ) );
			exit;
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $file_name;
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'other';
		$visibility  = isset( $_POST['visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['visibility'] ) ) : 'all';

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$wpdb->insert(
			$table,
			array(
				'title'       => $title,
				'description' => $description,
				'file_path'   => $target,
				'file_type'   => $file_ext,
				'file_size'   => (int) $file['size'],
				'category'    => $category,
				'visibility'  => $visibility,
				'uploaded_by' => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
				'version'     => '1',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents&lk_success=1' ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( 'lk_upload_documents' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_delete_document' );

		$doc_id = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;
		if ( $doc_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_documents';
			$doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );
			if ( $doc ) {
				if ( file_exists( $doc->file_path ) ) {
					unlink( $doc->file_path );
				}
				$wpdb->delete( $table, array( 'id' => $doc_id ), array( '%d' ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_upload_documents' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$docs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'Documents', 'lovekin' ); ?></h1>
			<div class="lk-admin-card">
				<h2><?php esc_html_e( 'Upload Document', 'lovekin' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="lk_upload_document" />
					<?php wp_nonce_field( 'lk_upload_document' ); ?>
					<input type="text" name="title" class="widefat" placeholder="<?php esc_attr_e( 'Document title', 'lovekin' ); ?>" />
					<textarea name="description" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Description', 'lovekin' ); ?>"></textarea>
					<select name="category" class="widefat">
						<option value="bylaws"><?php esc_html_e( 'Bylaws', 'lovekin' ); ?></option>
						<option value="policies"><?php esc_html_e( 'Policies', 'lovekin' ); ?></option>
						<option value="procedures"><?php esc_html_e( 'Procedures', 'lovekin' ); ?></option>
						<option value="forms"><?php esc_html_e( 'Forms', 'lovekin' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'lovekin' ); ?></option>
					</select>
					<select name="visibility" class="widefat">
						<option value="all"><?php esc_html_e( 'All Members', 'lovekin' ); ?></option>
						<option value="primary"><?php esc_html_e( 'Primary Only', 'lovekin' ); ?></option>
						<option value="secondary"><?php esc_html_e( 'Secondary Only', 'lovekin' ); ?></option>
					</select>
					<input type="file" name="document_file" accept=".pdf,.doc,.docx" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Upload', 'lovekin' ); ?></button>
				</form>
			</div>

			<h2><?php esc_html_e( 'Library', 'lovekin' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Category', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Visibility', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Uploaded', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $docs ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No documents uploaded yet.', 'lovekin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $docs as $doc ) : ?>
							<tr>
								<td><?php echo esc_html( $doc->title ); ?></td>
								<td><?php echo esc_html( ucfirst( $doc->category ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $doc->visibility ) ); ?></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y', $doc->created_at ) ); ?></td>
								<td>
									<a class="button button-secondary" href="<?php echo esc_url( self::get_download_url( $doc->id ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
									<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lk_delete_document&doc_id=' . $doc->id ), 'lk_delete_document' ) ); ?>"><?php esc_html_e( 'Delete', 'lovekin' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function get_documents_for_user( $user_id ) {
		if ( user_can( $user_id, 'lk_upload_documents' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_documents';
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		}
		$role = self::get_user_role_group( $user_id );
		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';

		if ( 'primary' === $role ) {
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE visibility IN ('all','primary') ORDER BY created_at DESC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} WHERE visibility IN ('all','secondary') ORDER BY created_at DESC" );
	}

	public static function get_download_url( $doc_id ) {
		return add_query_arg( array( 'lk_document_download' => $doc_id ), home_url( '/' ) );
	}

	public static function handle_download() {
		$doc_id = isset( $_GET['lk_document_download'] ) ? absint( $_GET['lk_document_download'] ) : 0;
		if ( ! $doc_id || ! is_user_logged_in() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );
		if ( ! $doc || ! file_exists( $doc->file_path ) ) {
			return;
		}

		$role = self::get_user_role_group( get_current_user_id() );
		if ( 'primary' === $role && ! in_array( $doc->visibility, array( 'all', 'primary' ), true ) ) {
			return;
		}
		if ( 'secondary' === $role && ! in_array( $doc->visibility, array( 'all', 'secondary' ), true ) ) {
			return;
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $doc->file_path ) );
		header( 'Content-Length: ' . filesize( $doc->file_path ) );
		readfile( $doc->file_path );
		exit;
	}

	private static function get_user_role_group( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return 'secondary';
		}
		if ( in_array( 'lk_primary', (array) $user->roles, true ) ) {
			return 'primary';
		}
		return 'secondary';
	}
}
