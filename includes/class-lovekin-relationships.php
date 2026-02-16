<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Relationships {
	public static function init() {
		add_action( 'admin_post_lk_assign_relationship', array( __CLASS__, 'handle_assign' ) );
		add_action( 'admin_post_lk_remove_relationship', array( __CLASS__, 'handle_remove' ) );
		add_action( 'admin_post_lk_import_relationships', array( __CLASS__, 'handle_import' ) );
	}

	private static function insert_relationship( $primary_id, $secondary_id ) {
		if ( ! $primary_id || ! $secondary_id || $primary_id === $secondary_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_relationships';
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE primary_user_id = %d AND secondary_user_id = %d",
				$primary_id,
				$secondary_id
			)
		);

		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'primary_user_id'   => $primary_id,
					'secondary_user_id' => $secondary_id,
					'created_at'        => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s' )
			);
		}
	}

	public static function handle_assign() {
		if ( ! current_user_can( 'lk_assign_relationships' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_assign_relationship' );

		$primary_id   = isset( $_POST['primary_user_id'] ) ? absint( $_POST['primary_user_id'] ) : 0;
		$secondary_id = isset( $_POST['secondary_user_id'] ) ? absint( $_POST['secondary_user_id'] ) : 0;

		self::insert_relationship( $primary_id, $secondary_id );

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-relationships' ) );
		exit;
	}

	public static function handle_remove() {
		if ( ! current_user_can( 'lk_assign_relationships' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_remove_relationship' );

		$relationship_id = isset( $_GET['relationship_id'] ) ? absint( $_GET['relationship_id'] ) : 0;
		if ( $relationship_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_relationships';
			$wpdb->delete( $table, array( 'id' => $relationship_id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-relationships' ) );
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'lk_assign_relationships' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_import_relationships' );

		if ( empty( $_FILES['relationships_csv']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-relationships' ) );
			exit;
		}

		$handle = fopen( $_FILES['relationships_csv']['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_safe_redirect( admin_url( 'admin.php?page=lovekin-relationships' ) );
			exit;
		}

		$rows = 0;
		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( 0 === $rows && isset( $data[0] ) && 'primary_email' === strtolower( $data[0] ) ) {
				$rows++;
				continue;
			}
			$primary_email   = isset( $data[0] ) ? sanitize_email( $data[0] ) : '';
			$secondary_email = isset( $data[1] ) ? sanitize_email( $data[1] ) : '';
			$primary_user    = $primary_email ? get_user_by( 'email', $primary_email ) : false;
			$secondary_user  = $secondary_email ? get_user_by( 'email', $secondary_email ) : false;
			if ( $primary_user && $secondary_user ) {
				self::insert_relationship( $primary_user->ID, $secondary_user->ID );
			}
			$rows++;
		}
		fclose( $handle );

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-relationships' ) );
		exit;
	}

	public static function get_primary_for_secondary( $secondary_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_relationships';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE secondary_user_id = %d LIMIT 1",
				$secondary_id
			)
		);
	}

	public static function get_secondaries_for_primary( $primary_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_relationships';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE primary_user_id = %d ORDER BY created_at DESC",
				$primary_id
			)
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_assign_relationships' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$primary_users   = get_users( array( 'role__in' => array( 'lk_primary', 'administrator' ) ) );
		$secondary_users = get_users( array( 'role__in' => array( 'lk_secondary', 'lk_primary' ) ) );

		global $wpdb;
		$table = $wpdb->prefix . 'lk_relationships';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'Relationships', 'lovekin' ); ?></h1>
			<div class="lk-admin-grid">
				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'Assign Primary to Secondary', 'lovekin' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="lk_assign_relationship" />
						<?php wp_nonce_field( 'lk_assign_relationship' ); ?>
						<label class="lk-admin-label" for="primary_user_id"><?php esc_html_e( 'Primary Member', 'lovekin' ); ?></label>
						<select name="primary_user_id" id="primary_user_id" class="widefat">
							<option value="0"><?php esc_html_e( 'Select primary member', 'lovekin' ); ?></option>
							<?php foreach ( $primary_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
							<?php endforeach; ?>
						</select>

						<label class="lk-admin-label" for="secondary_user_id"><?php esc_html_e( 'Secondary Member', 'lovekin' ); ?></label>
						<select name="secondary_user_id" id="secondary_user_id" class="widefat">
							<option value="0"><?php esc_html_e( 'Select secondary member', 'lovekin' ); ?></option>
							<?php foreach ( $secondary_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
							<?php endforeach; ?>
						</select>

						<button type="submit" class="button button-primary"><?php esc_html_e( 'Assign', 'lovekin' ); ?></button>
					</form>
				</div>

				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'Bulk Import (CSV)', 'lovekin' ); ?></h2>
					<p class="description"><?php esc_html_e( 'CSV format: primary_email,secondary_email', 'lovekin' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="lk_import_relationships" />
						<?php wp_nonce_field( 'lk_import_relationships' ); ?>
						<input type="file" name="relationships_csv" accept=".csv" />
						<button type="submit" class="button button-secondary"><?php esc_html_e( 'Import', 'lovekin' ); ?></button>
					</form>
				</div>
			</div>

			<h2><?php esc_html_e( 'Existing Relationships', 'lovekin' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Primary', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Secondary', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Assigned', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="4"><?php esc_html_e( 'No relationships found yet.', 'lovekin' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$primary   = get_user_by( 'id', $row->primary_user_id );
							$secondary = get_user_by( 'id', $row->secondary_user_id );
							?>
							<tr>
								<td><?php echo esc_html( $primary ? $primary->display_name : '-' ); ?></td>
								<td><?php echo esc_html( $secondary ? $secondary->display_name : '-' ); ?></td>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td>
									<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lk_remove_relationship&relationship_id=' . $row->id ), 'lk_remove_relationship' ) ); ?>">
										<?php esc_html_e( 'Remove', 'lovekin' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
