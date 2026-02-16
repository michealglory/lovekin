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

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-settings&lk_saved=1' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_manage_settings' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$bands    = get_option( 'lovekin_remark_bands', array() );
		$settings = get_option( 'lovekin_upload_settings', array() );
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

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'lovekin' ); ?></button>
			</form>
		</div>
		<?php
	}
}
