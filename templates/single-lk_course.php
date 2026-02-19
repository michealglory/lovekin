<?php
/**
 * Template for single LoveKin courses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lovekin_course_dashboard_url() {
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
		$course_id = get_the_ID();
		$file_url  = get_post_meta( $course_id, '_lk_course_file_url', true );
		$dashboard_url = lovekin_course_dashboard_url();
		$thumbnail = get_the_post_thumbnail_url( $course_id, 'large' );
		?>
		<div class="lk-root lk-course-template">
			<div class="lk-course-hero">
				<div class="lk-hero-inner">
					<p class="lk-eyebrow"><?php esc_html_e( 'Course', 'lovekin' ); ?></p>
					<h1 class="lk-title"><?php the_title(); ?></h1>
					<p class="lk-hero-meta"><?php esc_html_e( 'Grow with this course and take the assessment when ready.', 'lovekin' ); ?></p>
					<div class="lk-hero-actions">
						<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Return to Dashboard', 'lovekin' ); ?></a>
						<?php if ( $file_url ) : ?>
							<a class="lk-button lk-button--primary" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener">
								<?php esc_html_e( 'Download materials', 'lovekin' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="lk-card lk-course-card-full">
				<?php if ( $thumbnail ) : ?>
					<img class="lk-course-image" src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php the_title_attribute(); ?>" />
				<?php endif; ?>
				<div class="lk-course-content">
					<?php the_content(); ?>
				</div>
			</div>
		</div>
		<?php
	}
}

get_footer();
