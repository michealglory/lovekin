<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Reports {
	public static function init() {
		add_action( 'admin_post_lk_update_remark', array( __CLASS__, 'handle_update_remark' ) );
	}

	public static function get_remark_for_score( $score ) {
		$bands = get_option( 'lovekin_remark_bands', array() );
		foreach ( $bands as $band ) {
			$min = isset( $band['min'] ) ? (int) $band['min'] : 0;
			$max = isset( $band['max'] ) ? (int) $band['max'] : 0;
			if ( $score >= $min && $score <= $max ) {
				return $band;
			}
		}
		return array( 'label' => __( 'Progress', 'lovekin' ), 'remark' => __( 'Keep going!', 'lovekin' ), 'color' => '#3b82f6' );
	}

	public static function handle_update_remark() {
		if ( ! current_user_can( 'lk_edit_remarks' ) && ! current_user_can( 'lk_view_all_reports' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}
		check_admin_referer( 'lk_update_remark' );

		$attempt_id  = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;
		$remark_text = isset( $_POST['remark'] ) ? wp_kses_post( wp_unslash( $_POST['remark'] ) ) : '';

		if ( $attempt_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_attempts';
			$wpdb->update(
				$table,
				array( 'remark' => $remark_text ),
				array( 'id' => $attempt_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=lovekin-reports' ) );
		exit;
	}

	public static function get_user_attempts( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at ASC",
				$user_id
			)
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'lk_view_all_reports' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$users = get_users();
		$selected_user = isset( $_GET['lk_user'] ) ? absint( $_GET['lk_user'] ) : 0;
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'Member Reports', 'lovekin' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="lovekin-reports" />
				<select name="lk_user" class="widefat" style="max-width: 320px;">
					<option value="0"><?php esc_html_e( 'Select a member', 'lovekin' ); ?></option>
					<?php foreach ( $users as $user ) : ?>
						<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $selected_user, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View Report', 'lovekin' ); ?></button>
			</form>

			<?php if ( $selected_user ) : ?>
				<?php echo self::render_report_view( $selected_user, true ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_report_view( $user_id, $is_admin = false ) {
		$attempts = self::get_user_attempts( $user_id );
		$averages = self::get_average_scores( $attempts );
		$chart_data = wp_json_encode( self::get_chart_data( $attempts ) );
		$attempts_payload = array();
		$course_options = array();
		foreach ( $attempts as $attempt ) {
			$course = get_post( $attempt->course_id );
			$course_title = $course ? $course->post_title : __( 'Course', 'lovekin' );
			$course_options[ $attempt->course_id ] = $course_title;
			$attempts_payload[] = array(
				'id'           => (int) $attempt->id,
				'score'        => (float) $attempt->score,
				'timestamp'    => strtotime( $attempt->created_at ),
				'date'         => mysql2date( 'M j, Y', $attempt->created_at ),
				'course_id'    => (int) $attempt->course_id,
				'course_title' => $course_title,
			);
		}
		$attempts_json = wp_json_encode( $attempts_payload );
		$trend = self::get_trend_delta( $attempts );
		$trend_class = $trend >= 0 ? 'lk-trend--up' : 'lk-trend--down';

		ob_start();
		?>
		<div class="lk-root lk-report" data-lk="report" data-lk-chart='<?php echo esc_attr( $chart_data ); ?>' data-lk-attempts='<?php echo esc_attr( $attempts_json ); ?>'>
			<div class="lk-report-filters">
				<div class="lk-field">
					<label><?php esc_html_e( 'Course', 'lovekin' ); ?></label>
					<select data-lk="report-course">
						<option value="all"><?php esc_html_e( 'All Courses', 'lovekin' ); ?></option>
						<?php foreach ( $course_options as $course_id => $course_title ) : ?>
							<option value="<?php echo esc_attr( $course_id ); ?>"><?php echo esc_html( $course_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="lk-field">
					<label><?php esc_html_e( 'Date Range', 'lovekin' ); ?></label>
					<select data-lk="report-range">
						<option value="30"><?php esc_html_e( 'Last 30 days', 'lovekin' ); ?></option>
						<option value="90"><?php esc_html_e( 'Last 90 days', 'lovekin' ); ?></option>
						<option value="180"><?php esc_html_e( 'Last 180 days', 'lovekin' ); ?></option>
						<option value="all" selected><?php esc_html_e( 'All time', 'lovekin' ); ?></option>
					</select>
				</div>
				<div class="lk-field">
					<label>&nbsp;</label>
					<button type="button" class="lk-button lk-button--ghost" disabled><?php esc_html_e( 'Download Summary (PDF)', 'lovekin' ); ?></button>
				</div>
			</div>

			<div class="lk-report-hero">
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Average Score', 'lovekin' ); ?></h3>
					<span class="lk-hero-value" data-lk="hero-average"><?php echo esc_html( number_format_i18n( $averages['overall'], 1 ) ); ?>%</span>
					<span class="lk-trend <?php echo esc_attr( $trend_class ); ?>">
						<?php
						printf(
							esc_html__( '%s%% vs previous 3', 'lovekin' ),
							number_format_i18n( $trend, 1 )
						);
						?>
					</span>
				</div>
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Assessments Taken', 'lovekin' ); ?></h3>
					<span class="lk-hero-value" data-lk="hero-count"><?php echo esc_html( (int) $averages['count'] ); ?></span>
				</div>
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Recent Avg (5)', 'lovekin' ); ?></h3>
					<span class="lk-hero-value" data-lk="hero-recent"><?php echo esc_html( number_format_i18n( $averages['recent'], 1 ) ); ?>%</span>
				</div>
			</div>

			<div class="lk-report-grid">
				<div class="lk-card">
					<h3><?php esc_html_e( 'Score Trend', 'lovekin' ); ?></h3>
					<canvas class="lk-chart" data-lk="chart-line"></canvas>
				</div>
				<div class="lk-card">
					<h3><?php esc_html_e( 'Score Distribution', 'lovekin' ); ?></h3>
					<canvas class="lk-chart" data-lk="chart-bar"></canvas>
				</div>
			</div>

			<div class="lk-card">
				<h3><?php esc_html_e( 'Progress Timeline', 'lovekin' ); ?></h3>
				<ul class="lk-timeline">
					<?php
					$timeline_attempts = array_slice( array_reverse( $attempts ), 0, 6 );
					if ( empty( $timeline_attempts ) ) :
						?>
						<li class="lk-empty"><?php esc_html_e( 'No progress to display yet.', 'lovekin' ); ?></li>
					<?php else : ?>
						<?php foreach ( $timeline_attempts as $attempt ) :
							$course = get_post( $attempt->course_id );
							$band   = self::get_remark_for_score( $attempt->score );
							?>
							<li>
								<span class="lk-timeline-dot" style="background: <?php echo esc_attr( $band['color'] ); ?>;"></span>
								<div>
									<strong><?php echo esc_html( $course ? $course->post_title : __( 'Course', 'lovekin' ) ); ?></strong>
									<span class="lk-meta"><?php echo esc_html( mysql2date( 'M j, Y', $attempt->created_at ) ); ?></span>
								</div>
								<span class="lk-score-pill" style="--lk-score-color: <?php echo esc_attr( $band['color'] ); ?>;">
									<?php echo esc_html( number_format_i18n( $attempt->score, 1 ) ); ?>%
								</span>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>

			<div class="lk-card">
				<h3><?php esc_html_e( 'Attempts', 'lovekin' ); ?></h3>
				<table class="lk-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Course', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Score', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Date', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Remark', 'lovekin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $attempts ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No attempts recorded yet.', 'lovekin' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( array_reverse( $attempts ) as $attempt ) :
								$course = get_post( $attempt->course_id );
								$band   = self::get_remark_for_score( $attempt->score );
								?>
								<tr data-course="<?php echo esc_attr( $attempt->course_id ); ?>" data-score="<?php echo esc_attr( $attempt->score ); ?>" data-timestamp="<?php echo esc_attr( strtotime( $attempt->created_at ) ); ?>">
									<td><?php echo esc_html( $course ? $course->post_title : __( 'Course', 'lovekin' ) ); ?></td>
									<td><span class="lk-score-pill" style="--lk-score-color: <?php echo esc_attr( $band['color'] ); ?>;"><?php echo esc_html( number_format_i18n( $attempt->score, 1 ) ); ?>%</span></td>
									<td><?php echo esc_html( mysql2date( 'M j, Y', $attempt->created_at ) ); ?></td>
									<td>
										<?php if ( $is_admin ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lk-remark-form">
												<input type="hidden" name="action" value="lk_update_remark" />
												<input type="hidden" name="attempt_id" value="<?php echo esc_attr( $attempt->id ); ?>" />
												<?php wp_nonce_field( 'lk_update_remark' ); ?>
												<textarea name="remark" rows="2" class="lk-input"><?php echo esc_textarea( $attempt->remark ? $attempt->remark : $band['remark'] ); ?></textarea>
												<button type="submit" class="lk-button lk-button--ghost"><?php esc_html_e( 'Save', 'lovekin' ); ?></button>
											</form>
										<?php else : ?>
											<?php echo esc_html( $attempt->remark ? $attempt->remark : $band['remark'] ); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_average_scores( $attempts ) {
		$count = count( $attempts );
		if ( 0 === $count ) {
			return array( 'overall' => 0, 'recent' => 0, 'count' => 0 );
		}
		$total = 0;
		foreach ( $attempts as $attempt ) {
			$total += (float) $attempt->score;
		}
		$recent_attempts = array_slice( $attempts, -5 );
		$recent_total = 0;
		foreach ( $recent_attempts as $attempt ) {
			$recent_total += (float) $attempt->score;
		}

		return array(
			'overall' => $total / $count,
			'recent'  => $recent_total / max( 1, count( $recent_attempts ) ),
			'count'   => $count,
		);
	}

	private static function get_chart_data( $attempts ) {
		$labels = array();
		$series = array();
		$distribution = array( '0-49' => 0, '50-74' => 0, '75-100' => 0 );

		foreach ( $attempts as $attempt ) {
			$labels[] = mysql2date( 'M j', $attempt->created_at );
			$series[] = (float) $attempt->score;
			if ( $attempt->score < 50 ) {
				$distribution['0-49']++;
			} elseif ( $attempt->score < 75 ) {
				$distribution['50-74']++;
			} else {
				$distribution['75-100']++;
			}
		}

		return array(
			'labels'       => $labels,
			'series'       => $series,
			'distribution' => $distribution,
		);
	}

	private static function get_trend_delta( $attempts ) {
		$recent = array_slice( $attempts, -3 );
		$previous = array_slice( $attempts, -6, 3 );
		if ( empty( $recent ) || empty( $previous ) ) {
			return 0;
		}
		$recent_total = 0;
		foreach ( $recent as $attempt ) {
			$recent_total += (float) $attempt->score;
		}
		$previous_total = 0;
		foreach ( $previous as $attempt ) {
			$previous_total += (float) $attempt->score;
		}
		$recent_avg = $recent_total / count( $recent );
		$previous_avg = $previous_total / count( $previous );
		return $recent_avg - $previous_avg;
	}
}
