<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Settings {
	public static function init() {
		add_action( 'admin_post_lk_save_settings', array( __CLASS__, 'handle_save' ) );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'lk_manage_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_save_settings' );

		$bands = isset( $_POST['bands'] ) ? (array) wp_unslash( $_POST['bands'] ) : array();
		$sanitized_bands = array();
		foreach ( $bands as $band ) {
			$sanitized_bands[] = array(
				'min'    => isset( $band['min'] ) ? (int) $band['min'] : 0,
				'max'    => isset( $band['max'] ) ? (int) $band['max'] : 0,
				'label'  => isset( $band['label'] ) ? sanitize_text_field( $band['label'] ) : '',
				'remark' => isset( $band['remark'] ) ? sanitize_text_field( $band['remark'] ) : '',
				'color'  => isset( $band['color'] ) ? sanitize_hex_color( $band['color'] ) : '#3b82f6',
			);
		}
		update_option( 'lovekin_remark_bands', $sanitized_bands );

		$allowed_raw = isset( $_POST['allowed_types'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_types'] ) ) : '';
		$allowed_types = array_filter( array_map( 'trim', explode( ',', $allowed_raw ) ) );
		if ( empty( $allowed_types ) ) {
			$allowed_types = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		}
		$settings = array(
			'allowed_types'     => $allowed_types,
			'max_file_size_mb'  => isset( $_POST['max_file_size_mb'] ) ? (int) $_POST['max_file_size_mb'] : 10,
			'archive_quota_mb'  => isset( $_POST['archive_quota_mb'] ) ? (int) $_POST['archive_quota_mb'] : 100,
			'document_quota_mb' => isset( $_POST['document_quota_mb'] ) ? (int) $_POST['document_quota_mb'] : 250,
		);
		update_option( 'lovekin_upload_settings', $settings );

		$auth_settings = array(
			'login_page_id'    => self::sanitize_page_id( $_POST['login_page_id'] ?? 0 ),
			'register_page_id' => self::sanitize_page_id( $_POST['register_page_id'] ?? 0 ),
			'dashboard_page_id'=> self::sanitize_page_id( $_POST['dashboard_page_id'] ?? 0 ),
		);
		update_option( 'lovekin_auth_settings', $auth_settings );

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-settings&lk_saved=1' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_manage_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$bands    = get_option( 'lovekin_remark_bands', array() );
		$settings = get_option( 'lovekin_upload_settings', array() );
		$auth_settings = get_option( 'lovekin_auth_settings', array() );
		$auth_settings = is_array( $auth_settings ) ? $auth_settings : array();
		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'post_status' => 'publish',
			)
		);
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'LoveKin Settings', 'lovekin' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lk_save_settings" />
				<?php wp_nonce_field( 'lk_save_settings' ); ?>

				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'Remark Bands', 'lovekin' ); ?></h2>
					<?php foreach ( $bands as $index => $band ) : ?>
						<div class="lk-band-row">
							<input type="number" name="bands[<?php echo esc_attr( $index ); ?>][min]" value="<?php echo esc_attr( $band['min'] ); ?>" />
							<input type="number" name="bands[<?php echo esc_attr( $index ); ?>][max]" value="<?php echo esc_attr( $band['max'] ); ?>" />
							<input type="text" name="bands[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $band['label'] ); ?>" />
							<input type="text" name="bands[<?php echo esc_attr( $index ); ?>][remark]" value="<?php echo esc_attr( $band['remark'] ); ?>" />
							<input type="color" name="bands[<?php echo esc_attr( $index ); ?>][color]" value="<?php echo esc_attr( $band['color'] ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>

					<div class="lk-admin-card">
						<h2><?php esc_html_e( 'Upload Settings', 'lovekin' ); ?></h2>
						<label><?php esc_html_e( 'Allowed File Types (comma separated)', 'lovekin' ); ?></label>
					<input type="text" name="allowed_types" class="widefat" value="<?php echo esc_attr( implode( ',', $settings['allowed_types'] ?? array() ) ); ?>" />
					<label><?php esc_html_e( 'Max File Size (MB)', 'lovekin' ); ?></label>
					<input type="number" name="max_file_size_mb" value="<?php echo esc_attr( $settings['max_file_size_mb'] ?? 10 ); ?>" />
					<label><?php esc_html_e( 'Archive Quota (MB)', 'lovekin' ); ?></label>
					<input type="number" name="archive_quota_mb" value="<?php echo esc_attr( $settings['archive_quota_mb'] ?? 100 ); ?>" />
						<label><?php esc_html_e( 'Documents Quota (MB)', 'lovekin' ); ?></label>
						<input type="number" name="document_quota_mb" value="<?php echo esc_attr( $settings['document_quota_mb'] ?? 250 ); ?>" />
					</div>

					<div class="lk-admin-card">
						<h2><?php esc_html_e( 'Authentication Pages', 'lovekin' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Select your Login, Register, and Dashboard pages. If left blank, LoveKin will auto-detect pages by shortcode.', 'lovekin' ); ?></p>

						<label for="lk-login-page-id"><?php esc_html_e( 'Login Page', 'lovekin' ); ?></label>
						<select id="lk-login-page-id" name="login_page_id" class="widefat">
							<option value="0"><?php esc_html_e( 'Auto-detect from [lovekin_login]', 'lovekin' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( absint( $auth_settings['login_page_id'] ?? 0 ), $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<label for="lk-register-page-id"><?php esc_html_e( 'Register Page', 'lovekin' ); ?></label>
						<select id="lk-register-page-id" name="register_page_id" class="widefat">
							<option value="0"><?php esc_html_e( 'Auto-detect from [lovekin_register]', 'lovekin' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( absint( $auth_settings['register_page_id'] ?? 0 ), $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<label for="lk-dashboard-page-id"><?php esc_html_e( 'Dashboard Page', 'lovekin' ); ?></label>
						<select id="lk-dashboard-page-id" name="dashboard_page_id" class="widefat">
							<option value="0"><?php esc_html_e( 'Auto-detect from [lovekin_dashboard]', 'lovekin' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( absint( $auth_settings['dashboard_page_id'] ?? 0 ), $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'lovekin' ); ?></button>
				</form>
			</div>
			<?php
	}

	private static function sanitize_page_id( $raw_page_id ) {
		$page_id = absint( $raw_page_id );
		if ( ! $page_id ) {
			return 0;
		}

		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return 0;
		}

		return $page_id;
	}
}
