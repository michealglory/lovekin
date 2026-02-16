<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Admin_Menus {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menus() {
		add_menu_page(
			__( 'LoveKin', 'lovekin' ),
			__( 'LoveKin', 'lovekin' ),
			'manage_options',
			'lovekin',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-groups',
			30
		);

		add_submenu_page( 'lovekin', __( 'Dashboard', 'lovekin' ), __( 'Dashboard', 'lovekin' ), 'manage_options', 'lovekin', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'lovekin', __( 'Courses', 'lovekin' ), __( 'Courses', 'lovekin' ), 'lk_manage_courses', 'edit.php?post_type=lk_course' );
		add_submenu_page( 'lovekin', __( 'Assessments', 'lovekin' ), __( 'Assessments', 'lovekin' ), 'lk_manage_assessments', 'edit.php?post_type=lk_assessment' );
		add_submenu_page( 'lovekin', __( 'Relationships', 'lovekin' ), __( 'Relationships', 'lovekin' ), 'lk_assign_relationships', 'lovekin-relationships', array( 'LoveKin_Relationships', 'render_admin_page' ) );
		add_submenu_page( 'lovekin', __( 'Reports', 'lovekin' ), __( 'Reports', 'lovekin' ), 'lk_view_all_reports', 'lovekin-reports', array( 'LoveKin_Reports', 'render_admin_page' ) );
		add_submenu_page( 'lovekin', __( 'Funding Requests', 'lovekin' ), __( 'Funding Requests', 'lovekin' ), 'lk_review_funding', 'lovekin-funding', array( 'LoveKin_Funding', 'render_admin_page' ) );
		add_submenu_page( 'lovekin', __( 'Documents', 'lovekin' ), __( 'Documents', 'lovekin' ), 'lk_upload_documents', 'lovekin-documents', array( 'LoveKin_Documents', 'render_admin_page' ) );
		add_submenu_page( 'lovekin', __( 'Settings', 'lovekin' ), __( 'Settings', 'lovekin' ), 'lk_manage_settings', 'lovekin-settings', array( 'LoveKin_Settings', 'render_admin_page' ) );
	}

	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		global $wpdb;
		$attempts_table = $wpdb->prefix . 'lk_attempts';
		$funding_table  = $wpdb->prefix . 'lk_funding_requests';

		$primary_count   = count( get_users( array( 'role' => 'lk_primary', 'fields' => 'ids' ) ) );
		$secondary_count = count( get_users( array( 'role' => 'lk_secondary', 'fields' => 'ids' ) ) );

		$attempts_30 = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$attempts_table} WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		$avg_30 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(score) FROM {$attempts_table} WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);
		$avg_30 = $avg_30 ? (float) $avg_30 : 0;

		$pending_funding = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$funding_table} WHERE status = 'pending'"
		);

		$recent_attempts = $wpdb->get_results(
			"SELECT score, created_at FROM {$attempts_table} ORDER BY created_at DESC LIMIT 10"
		);
		$line_labels = array();
		$line_scores = array();
		foreach ( array_reverse( $recent_attempts ) as $attempt ) {
			$line_labels[] = mysql2date( 'M j', $attempt->created_at );
			$line_scores[] = (float) $attempt->score;
		}

		$distribution = array( 'low' => 0, 'mid' => 0, 'high' => 0 );
		$distribution_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT score FROM {$attempts_table} WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);
		foreach ( $distribution_rows as $row ) {
			if ( $row->score < 50 ) {
				$distribution['low']++;
			} elseif ( $row->score < 75 ) {
				$distribution['mid']++;
			} else {
				$distribution['high']++;
			}
		}

		$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$attempts_table}" );
		$at_risk  = array();
		foreach ( $user_ids as $user_id ) {
			$last_three = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT score FROM {$attempts_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 3",
					$user_id
				)
			);
			if ( count( $last_three ) < 3 ) {
				continue;
			}
			$avg = array_sum( $last_three ) / 3;
			if ( $avg < 50 ) {
				$at_risk[] = array(
					'user' => get_user_by( 'id', $user_id ),
					'avg'  => $avg,
				);
			}
		}

		$chart_data = wp_json_encode(
			array(
				'labels'       => $line_labels,
				'scores'       => $line_scores,
				'distribution' => $distribution,
			)
		);
		?>
		<div class="wrap lk-admin-page lk-admin-dashboard" data-lk-admin-chart='<?php echo esc_attr( $chart_data ); ?>'>
			<h1><?php esc_html_e( 'LoveKin Dashboard', 'lovekin' ); ?></h1>

			<div class="lk-admin-stats">
				<div class="lk-admin-stat">
					<span><?php esc_html_e( 'Primary Members', 'lovekin' ); ?></span>
					<strong><?php echo esc_html( $primary_count ); ?></strong>
				</div>
				<div class="lk-admin-stat">
					<span><?php esc_html_e( 'Secondary Members', 'lovekin' ); ?></span>
					<strong><?php echo esc_html( $secondary_count ); ?></strong>
				</div>
				<div class="lk-admin-stat">
					<span><?php esc_html_e( 'Assessments (30d)', 'lovekin' ); ?></span>
					<strong><?php echo esc_html( $attempts_30 ); ?></strong>
				</div>
				<div class="lk-admin-stat">
					<span><?php esc_html_e( 'Avg Score (30d)', 'lovekin' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $avg_30, 1 ) ); ?>%</strong>
				</div>
				<div class="lk-admin-stat">
					<span><?php esc_html_e( 'Funding Pending', 'lovekin' ); ?></span>
					<strong><?php echo esc_html( $pending_funding ); ?></strong>
				</div>
			</div>

			<div class="lk-admin-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lk_course' ) ); ?>"><?php esc_html_e( 'Add Course', 'lovekin' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lk_assessment' ) ); ?>"><?php esc_html_e( 'Add Assessment', 'lovekin' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=lovekin-relationships' ) ); ?>"><?php esc_html_e( 'Assign Relationships', 'lovekin' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=lovekin-documents' ) ); ?>"><?php esc_html_e( 'Upload Document', 'lovekin' ); ?></a>
			</div>

			<div class="lk-admin-grid">
				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'Performance Snapshot', 'lovekin' ); ?></h2>
					<canvas class="lk-admin-chart" data-lk="admin-line"></canvas>
					<canvas class="lk-admin-chart" data-lk="admin-bar"></canvas>
				</div>

				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'At-Risk Members', 'lovekin' ); ?></h2>
					<?php if ( empty( $at_risk ) ) : ?>
						<p><?php esc_html_e( 'No members are currently flagged as at-risk.', 'lovekin' ); ?></p>
					<?php else : ?>
						<ul class="lk-admin-list">
							<?php foreach ( $at_risk as $entry ) : ?>
								<li>
									<strong><?php echo esc_html( $entry['user']->display_name ); ?></strong>
									<span><?php echo esc_html( number_format_i18n( $entry['avg'], 1 ) ); ?>%</span>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lovekin-reports&lk_user=' . $entry['user']->ID ) ); ?>"><?php esc_html_e( 'View Report', 'lovekin' ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'lovekin' ) === false ) {
			return;
		}
		wp_enqueue_style( 'lovekin-admin', LOVEKIN_PLUGIN_URL . 'assets/css/lovekin-admin.css', array(), LOVEKIN_VERSION );
		wp_enqueue_script( 'lovekin-admin', LOVEKIN_PLUGIN_URL . 'assets/js/lovekin-admin.js', array( 'jquery' ), LOVEKIN_VERSION, true );
		wp_enqueue_script( 'lovekin-chart', 'https://cdn.jsdelivr.net/npm/chart.js', array(), LOVEKIN_VERSION, true );
	}
}
