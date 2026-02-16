<?php
/**
 * Template for single LoveKin assessments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lovekin_find_dashboard_url() {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		)
	);
	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'lovekin_dashboard' ) ) {
			$url = get_permalink( $page->ID );
			return $url ? $url : home_url( '/' );
		}
	}
	return home_url( '/' );
}

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		$assessment_id = get_the_ID();
		$course_id     = (int) get_post_meta( $assessment_id, '_lk_course_id', true );
		$course        = $course_id ? get_post( $course_id ) : null;
		$dashboard_url = lovekin_find_dashboard_url();
		$user_id       = get_current_user_id();
		$attempt_count = 0;
		$attempt_avg   = 0;

		if ( $user_id && $course_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'lk_attempts';
			$attempt_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND course_id = %d",
					$user_id,
					$course_id
				)
			);
			$attempt_avg = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(score) FROM {$table} WHERE user_id = %d AND course_id = %d",
					$user_id,
					$course_id
				)
			);
		}
		?>
		<div class="lk-root lk-assessment-template">
			<div class="lk-assessment-hero">
				<div class="lk-hero-inner">
					<p class="lk-eyebrow"><?php esc_html_e( 'Assessment', 'lovekin' ); ?></p>
					<h1 class="lk-title"><?php the_title(); ?></h1>
					<?php if ( $course ) : ?>
						<p class="lk-hero-meta"><?php echo esc_html( $course->post_title ); ?></p>
					<?php endif; ?>
					<div class="lk-hero-stats">
						<div>
							<span><?php esc_html_e( 'Attempts', 'lovekin' ); ?></span>
							<strong><?php echo esc_html( $attempt_count ); ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Avg Score', 'lovekin' ); ?></span>
							<strong><?php echo esc_html( $attempt_count ? number_format_i18n( $attempt_avg, 1 ) . '%' : '--' ); ?></strong>
						</div>
					</div>
					<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Return to Dashboard', 'lovekin' ); ?></a>
				</div>
			</div>
			<div class="lk-card lk-assessment-card">
				<?php the_content(); ?>
			</div>
		</div>
		<?php
	}
}

get_footer();
