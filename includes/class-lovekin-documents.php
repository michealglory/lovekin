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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_upload_document' );
		$redirect_url = admin_url( 'admin.php?page=lovekin-documents' );

		if ( empty( $_FILES['document_file']['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'missing', $redirect_url ) );
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
		$quota_mb      = isset( $settings['document_quota_mb'] ) ? max( 1, (int) $settings['document_quota_mb'] ) : 250;

		$file = $_FILES['document_file'];
		$file_name = sanitize_file_name( $file['name'] );
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		$check     = wp_check_filetype_and_ext( $file['tmp_name'], $file_name, $allowed_mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			$check = wp_check_filetype( $file_name, $allowed_mimes );
		}
		if ( empty( $check['ext'] ) ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'type', $redirect_url ) );
			exit;
		}

		if ( ! in_array( $file_ext, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'type', $redirect_url ) );
			exit;
		}

		if ( $file['size'] > ( $max_mb * 1024 * 1024 ) ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'size', $redirect_url ) );
			exit;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$total_bytes = (int) $wpdb->get_var( "SELECT COALESCE(SUM(file_size), 0) FROM {$table}" );
		$quota_bytes = $quota_mb * 1024 * 1024;
		if ( $quota_bytes > 0 && ( $total_bytes + (int) $file['size'] ) > $quota_bytes ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'quota', $redirect_url ) );
			exit;
		}

		self::ensure_documents_upload_structure();
		add_filter( 'upload_dir', array( __CLASS__, 'filter_documents_upload_dir' ) );
		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $allowed_mimes,
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_documents_upload_dir' ) );

		if ( isset( $upload['error'] ) || empty( $upload['file'] ) ) {
			wp_safe_redirect( add_query_arg( 'lk_error', 'upload', $redirect_url ) );
			exit;
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $file_name;
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'other';
		if ( '' === $title ) {
			$title = $file_name;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'title'       => $title,
				'description' => $description,
				'file_path'   => $upload['file'],
				'file_type'   => $file_ext,
				'file_size'   => (int) $file['size'],
				'category'    => $category,
				'visibility'  => 'all',
				'uploaded_by' => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
				'version'     => '1',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			if ( ! empty( $upload['file'] ) && file_exists( $upload['file'] ) ) {
				@unlink( $upload['file'] );
			}
			wp_safe_redirect( add_query_arg( 'lk_error', 'db', $redirect_url ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'lk_success', 'uploaded', $redirect_url ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_delete_document' );

		$doc_id = isset( $_GET['doc_id'] ) ? absint( $_GET['doc_id'] ) : 0;
		if ( $doc_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_documents';
			$doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );
			if ( $doc ) {
				$file_path = self::resolve_file_path( $doc->file_path );
				if ( $file_path && file_exists( $file_path ) ) {
					@unlink( $file_path );
				}
				$wpdb->delete( $table, array( 'id' => $doc_id ), array( '%d' ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-documents' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$docs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		$success = isset( $_GET['lk_success'] ) ? sanitize_key( wp_unslash( $_GET['lk_success'] ) ) : '';
		$error   = isset( $_GET['lk_error'] ) ? sanitize_key( wp_unslash( $_GET['lk_error'] ) ) : '';
		$total_size = 0;
		foreach ( $docs as $doc ) {
			$total_size += (int) $doc->file_size;
		}
		$total_mb = $total_size / ( 1024 * 1024 );

		$notice_map = array(
			'missing' => __( 'Please select a file before uploading.', 'lovekin' ),
			'type'    => __( 'Unsupported file type. Please upload an allowed file extension.', 'lovekin' ),
			'size'    => __( 'File exceeds the configured upload size limit.', 'lovekin' ),
			'quota'   => __( 'Document library quota exceeded. Increase quota or remove files.', 'lovekin' ),
			'upload'  => __( 'Upload failed. Please check server upload permissions and try again.', 'lovekin' ),
			'db'      => __( 'Document metadata could not be saved. Please try again.', 'lovekin' ),
		);
		?>
		<div class="wrap lk-admin-page lk-doc-admin">
			<h1><?php esc_html_e( 'Documents', 'lovekin' ); ?></h1>

			<?php if ( 'uploaded' === $success ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Document uploaded successfully.', 'lovekin' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error && isset( $notice_map[ $error ] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $notice_map[ $error ] ); ?></p></div>
			<?php endif; ?>

			<div class="lk-admin-card lk-doc-admin-card">
				<div class="lk-doc-admin-head">
					<h2><?php esc_html_e( 'Upload Document', 'lovekin' ); ?></h2>
					<span class="lk-doc-admin-meta">
						<?php
						printf(
							/* translators: 1: number of documents, 2: total MB */
							esc_html__( '%1$d files · %2$s MB total', 'lovekin' ),
							(int) count( $docs ),
							esc_html( number_format_i18n( $total_mb, 1 ) )
						);
						?>
					</span>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-doc-admin-form">
					<input type="hidden" name="action" value="lk_upload_document" />
					<?php wp_nonce_field( 'lk_upload_document' ); ?>
					<div class="lk-doc-admin-grid">
						<div class="lk-doc-admin-field">
							<label for="lk-doc-title"><?php esc_html_e( 'Title', 'lovekin' ); ?></label>
							<input id="lk-doc-title" type="text" name="title" class="widefat" placeholder="<?php esc_attr_e( 'Document title', 'lovekin' ); ?>" />
						</div>
						<div class="lk-doc-admin-field">
							<label for="lk-doc-category"><?php esc_html_e( 'Category', 'lovekin' ); ?></label>
							<select id="lk-doc-category" name="category" class="widefat">
								<option value="bylaws"><?php esc_html_e( 'Bylaws', 'lovekin' ); ?></option>
								<option value="policies"><?php esc_html_e( 'Policies', 'lovekin' ); ?></option>
								<option value="procedures"><?php esc_html_e( 'Procedures', 'lovekin' ); ?></option>
								<option value="forms"><?php esc_html_e( 'Forms', 'lovekin' ); ?></option>
								<option value="other"><?php esc_html_e( 'Other', 'lovekin' ); ?></option>
							</select>
						</div>
					</div>
					<div class="lk-doc-admin-field">
						<label for="lk-doc-description"><?php esc_html_e( 'Description', 'lovekin' ); ?></label>
						<textarea id="lk-doc-description" name="description" class="widefat" rows="2" placeholder="<?php esc_attr_e( 'Description', 'lovekin' ); ?>"></textarea>
					</div>
					<div class="lk-doc-admin-file">
						<label for="lk-doc-file"><?php esc_html_e( 'File', 'lovekin' ); ?></label>
						<input id="lk-doc-file" type="file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
						<p class="description"><?php esc_html_e( 'Shared documents are available to all logged-in members.', 'lovekin' ); ?></p>
					</div>
					<div class="lk-doc-admin-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Upload Document', 'lovekin' ); ?></button>
					</div>
				</form>
			</div>

			<div class="lk-admin-card lk-doc-admin-card">
				<div class="lk-doc-admin-head">
					<h2><?php esc_html_e( 'Library', 'lovekin' ); ?></h2>
				</div>
				<table class="widefat striped lk-doc-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Category', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'File Type', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Size', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Uploaded', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'lovekin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $docs ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No documents uploaded yet.', 'lovekin' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $docs as $doc ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $doc->title ); ?></strong>
										<?php if ( ! empty( $doc->description ) ) : ?>
											<p class="description"><?php echo esc_html( wp_trim_words( $doc->description, 18 ) ); ?></p>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( ucfirst( $doc->category ) ); ?></td>
									<td><?php echo esc_html( strtoupper( (string) $doc->file_type ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( ( (int) $doc->file_size ) / 1024, 1 ) ); ?> KB</td>
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
		</div>
		<?php
	}

	public static function get_documents_for_user( $user_id ) {
		if ( ! $user_id || ! user_can( $user_id, 'read' ) ) {
			return array();
		}
		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	public static function get_download_url( $doc_id ) {
		return add_query_arg( array( 'lk_document_download' => $doc_id ), home_url( '/' ) );
	}

	public static function handle_download() {
		$doc_id = isset( $_GET['lk_document_download'] ) ? absint( $_GET['lk_document_download'] ) : 0;
		if ( ! $doc_id || ! is_user_logged_in() ) {
			return;
		}
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_documents';
		$doc   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $doc_id ) );
		if ( ! $doc ) {
			return;
		}

		$file_path = self::resolve_file_path( $doc->file_path );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		nocache_headers();
		$download_name = sanitize_file_name( (string) $doc->title );
		$fallback_name = basename( $file_path );
		if ( '' === $download_name ) {
			$download_name = $fallback_name;
		}
		if ( '' === pathinfo( $download_name, PATHINFO_EXTENSION ) ) {
			$doc_ext = sanitize_key( (string) $doc->file_type );
			if ( '' !== $doc_ext ) {
				$download_name .= '.' . $doc_ext;
			}
		}
		if ( '' === pathinfo( $download_name, PATHINFO_EXTENSION ) ) {
			$download_name = $fallback_name;
		}

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $download_name ) );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}

	public static function filter_documents_upload_dir( $dirs ) {
		$subdir        = '/lovekin/documents';
		$dirs['subdir'] = $subdir;
		$dirs['path']   = $dirs['basedir'] . $subdir;
		$dirs['url']    = $dirs['baseurl'] . $subdir;
		return $dirs;
	}

	private static function ensure_documents_upload_structure() {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		$base_dir = trailingslashit( $uploads['basedir'] ) . 'lovekin';
		$docs_dir = $base_dir . '/documents';
		self::ensure_directory( $base_dir );
		self::ensure_directory( $docs_dir );
	}

	private static function ensure_directory( $dir ) {
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
