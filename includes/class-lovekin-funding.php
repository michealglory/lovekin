<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Funding {
	public static function init() {
		add_action( 'admin_post_lk_submit_funding', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_lk_submit_funding', array( __CLASS__, 'block_guest_submission' ) );
		add_action( 'admin_post_lk_update_funding_status', array( __CLASS__, 'handle_status_update' ) );
	}

	public static function block_guest_submission() {
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	public static function handle_submit() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! current_user_can( 'lk_request_funding' ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		check_admin_referer( 'lk_submit_funding' );

		$user_id = get_current_user_id();
		$format  = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : '';
		$purpose = isset( $_POST['purpose'] ) ? wp_kses_post( wp_unslash( $_POST['purpose'] ) ) : '';
		$amount  = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
		$account_details = isset( $_POST['account_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['account_details'] ) ) : '';
		$membership_code = get_user_meta( $user_id, 'lk_membership_code', true );

		global $wpdb;
		$table = $wpdb->prefix . 'lk_funding_requests';
		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'format'         => $format,
				'purpose'        => $purpose,
				'amount'         => $amount,
				'account_details'=> $account_details,
				'membership_code'=> $membership_code,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}
		wp_safe_redirect( add_query_arg( array( 'lk_funding' => 'submitted' ), $redirect_url ) );
		exit;
	}

	public static function handle_status_update() {
		if ( ! current_user_can( 'lk_review_funding' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_update_funding_status' );

		$request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'pending';
		$notes      = isset( $_POST['admin_notes'] ) ? wp_kses_post( wp_unslash( $_POST['admin_notes'] ) ) : '';

		if ( $request_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_funding_requests';
			$wpdb->update(
				$table,
				array(
					'status'      => $status,
					'admin_notes' => $notes,
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $request_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-funding' ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_review_funding' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_funding_requests';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'Funding Requests', 'lovekin' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Member', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Format', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No funding requests yet.', 'lovekin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$user = get_user_by( 'id', $row->user_id );
							?>
							<tr>
								<td><?php echo esc_html( $user ? $user->display_name : '-' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row->amount, 2 ) ); ?></td>
								<td><?php echo esc_html( $row->format ); ?></td>
								<td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lk-admin-inline">
										<input type="hidden" name="action" value="lk_update_funding_status" />
										<input type="hidden" name="request_id" value="<?php echo esc_attr( $row->id ); ?>" />
										<?php wp_nonce_field( 'lk_update_funding_status' ); ?>
										<select name="status">
											<option value="pending" <?php selected( $row->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'lovekin' ); ?></option>
											<option value="approved" <?php selected( $row->status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'lovekin' ); ?></option>
											<option value="rejected" <?php selected( $row->status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'lovekin' ); ?></option>
										</select>
										<textarea name="admin_notes" rows="2" class="small-text" placeholder="<?php esc_attr_e( 'Notes', 'lovekin' ); ?>"><?php echo esc_textarea( $row->admin_notes ); ?></textarea>
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Update', 'lovekin' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function get_user_requests( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_funding_requests';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);
	}
}
