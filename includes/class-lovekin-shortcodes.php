<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Shortcodes {
	public static function init() {
		add_shortcode( 'lovekin_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_shortcode( 'lovekin_profile', array( __CLASS__, 'render_profile' ) );
		add_shortcode( 'lovekin_report', array( __CLASS__, 'render_report' ) );
		add_shortcode( 'lovekin_assessment', array( __CLASS__, 'render_assessment' ) );
		add_shortcode( 'lovekin_funding_request', array( __CLASS__, 'render_funding' ) );
		add_shortcode( 'lovekin_documents', array( __CLASS__, 'render_documents' ) );
		add_shortcode( 'lovekin_archive', array( __CLASS__, 'render_archive' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'admin_post_lk_update_profile', array( __CLASS__, 'handle_profile_update' ) );
		add_action( 'admin_post_nopriv_lk_update_profile', array( __CLASS__, 'block_guest_submission' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_assessment_to_content' ) );
		add_filter( 'template_include', array( __CLASS__, 'assessment_template' ) );
	}

	public static function assessment_template( $template ) {
		if ( is_singular( 'lk_assessment' ) ) {
			$plugin_template = LOVEKIN_PLUGIN_DIR . 'templates/single-lk_assessment.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		if ( is_singular( 'lk_course' ) ) {
			$plugin_template = LOVEKIN_PLUGIN_DIR . 'templates/single-lk_course.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	public static function append_assessment_to_content( $content ) {
		if ( is_admin() ) {
			return $content;
		}

		if ( ! is_singular( 'lk_assessment' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$assessment_html = self::render_assessment( array( 'assessment_id' => get_the_ID() ) );
		return $content . $assessment_html;

		return $content;
	}

	public static function block_guest_submission() {
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	public static function maybe_enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		if ( is_singular( 'lk_assessment' ) ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/css/lovekin-public.css', array(), LOVEKIN_VERSION );
			wp_enqueue_script( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/js/lovekin-public.js', array( 'jquery' ), LOVEKIN_VERSION, true );
			wp_enqueue_script( 'lovekin-chart', 'https://cdn.jsdelivr.net/npm/chart.js', array(), LOVEKIN_VERSION, true );
			return;
		}

		$shortcodes = array(
			'lovekin_dashboard',
			'lovekin_profile',
			'lovekin_report',
			'lovekin_assessment',
			'lovekin_funding_request',
			'lovekin_documents',
			'lovekin_archive',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				wp_enqueue_style( 'dashicons' );
				wp_enqueue_style( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/css/lovekin-public.css', array(), LOVEKIN_VERSION );
				wp_enqueue_script( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/js/lovekin-public.js', array( 'jquery' ), LOVEKIN_VERSION, true );
				wp_enqueue_script( 'lovekin-chart', 'https://cdn.jsdelivr.net/npm/chart.js', array(), LOVEKIN_VERSION, true );
				break;
			}
		}
	}

	public static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$user = wp_get_current_user();
		$tab  = isset( $_GET['lk_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_tab'] ) ) : 'home';
		$course_id = isset( $_GET['lk_course'] ) ? absint( $_GET['lk_course'] ) : 0;
		global $post;
		$page_url = $post ? get_permalink( $post->ID ) : home_url( '/' );
		if ( $page_url && 0 !== strpos( $page_url, 'http' ) ) {
			$page_url = home_url( $page_url );
		}
		$base_url = remove_query_arg( array( 'lk_profile', 'lk_funding', 'lk_archive', 'lk_course', 'lk_user', 'lk_result' ), $page_url );

		ob_start();
		?>
		<div class="lk-root lk-dashboard" data-lk="dashboard">
			<div class="lk-dashboard-header">
				<div>
					<p class="lk-eyebrow"><?php esc_html_e( 'Welcome back', 'lovekin' ); ?></p>
					<h2 class="lk-title"><?php echo esc_html( $user->display_name ); ?></h2>
				</div>
				<div class="lk-quick-actions">
					<a class="lk-button" href="<?php echo esc_url( add_query_arg( 'lk_tab', 'courses', $base_url ) ); ?>"><?php esc_html_e( 'Explore Courses', 'lovekin' ); ?></a>
					<a class="lk-button lk-button--ghost" href="<?php echo esc_url( add_query_arg( 'lk_tab', 'reports', $base_url ) ); ?>"><?php esc_html_e( 'View Reports', 'lovekin' ); ?></a>
				</div>
			</div>

			<div class="lk-dashboard-tabs" data-lk="tabs">
				<?php
				$tabs = array(
					'home'     => __( 'Home', 'lovekin' ),
					'courses'  => __( 'Courses', 'lovekin' ),
					'profile'  => __( 'Profile', 'lovekin' ),
					'reports'  => __( 'Reports', 'lovekin' ),
					'funding'  => __( 'Funding', 'lovekin' ),
					'documents'=> __( 'Documents', 'lovekin' ),
					'archive'  => __( 'Archive', 'lovekin' ),
				);
				foreach ( $tabs as $key => $label ) :
					?>
					<a class="lk-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'lk_tab', $key, $base_url ) ); ?>" data-lk="tab" data-tab="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="lk-dashboard-content">
				<?php
				switch ( $tab ) {
					case 'courses':
						echo self::render_courses( $course_id, $page_url );
						break;
					case 'profile':
						echo self::render_profile();
						break;
					case 'reports':
						echo self::render_report();
						break;
					case 'funding':
						echo self::render_funding();
						break;
					case 'documents':
						echo self::render_documents();
						break;
					case 'archive':
						echo self::render_archive();
						break;
					default:
						echo self::render_home();
						break;
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_home() {
		$user_id = get_current_user_id();
		$attempts = LoveKin_Assessments::get_attempts_for_user( $user_id, 5 );
		$primary  = LoveKin_Relationships::get_primary_for_secondary( $user_id );
		$mentees  = LoveKin_Relationships::get_secondaries_for_primary( $user_id );
		$all_attempts = LoveKin_Reports::get_user_attempts( $user_id );
		$average = 0;
		if ( ! empty( $all_attempts ) ) {
			$total = 0;
			foreach ( $all_attempts as $attempt ) {
				$total += (float) $attempt->score;
			}
			$average = $total / count( $all_attempts );
		}
		$course_ids = array();
		foreach ( $all_attempts as $attempt ) {
			$course_ids[ (int) $attempt->course_id ] = true;
		}
		$course_count = count( $course_ids );
		$latest_score = LoveKin_Assessments::get_latest_score_for_user( $user_id );
		$at_risk_members = self::get_at_risk_members( $user_id );

		ob_start();
		?>
		<div class="lk-grid">
			<div class="lk-stat-grid">
				<div class="lk-stat-card">
					<span class="lk-icon dashicons dashicons-forms"></span>
					<div>
						<span class="lk-stat-label"><?php esc_html_e( 'Assessments Taken', 'lovekin' ); ?></span>
						<span class="lk-stat-value"><?php echo esc_html( count( $all_attempts ) ); ?></span>
						<span class="lk-stat-meta">
							<?php
							printf(
								esc_html__( 'Across %d courses', 'lovekin' ),
								(int) $course_count
							);
							?>
						</span>
					</div>
				</div>
				<div class="lk-stat-card">
					<span class="lk-icon dashicons dashicons-chart-line"></span>
					<div>
						<span class="lk-stat-label"><?php esc_html_e( 'Average Score', 'lovekin' ); ?></span>
						<span class="lk-stat-value"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?>%</span>
						<span class="lk-stat-meta"><?php esc_html_e( 'All attempts', 'lovekin' ); ?></span>
					</div>
				</div>
				<div class="lk-stat-card">
					<span class="lk-icon dashicons dashicons-star-filled"></span>
					<div>
						<span class="lk-stat-label"><?php esc_html_e( 'Latest Score', 'lovekin' ); ?></span>
						<span class="lk-stat-value"><?php echo esc_html( number_format_i18n( $latest_score ?: 0, 1 ) ); ?>%</span>
						<span class="lk-stat-meta"><?php esc_html_e( 'Most recent attempt', 'lovekin' ); ?></span>
					</div>
				</div>
				<div class="lk-stat-card">
					<span class="lk-icon dashicons dashicons-welcome-learn-more"></span>
					<div>
						<span class="lk-stat-label"><?php esc_html_e( 'Courses Completed', 'lovekin' ); ?></span>
						<span class="lk-stat-value"><?php echo esc_html( $course_count ); ?></span>
						<span class="lk-stat-meta"><?php esc_html_e( 'Courses with attempts', 'lovekin' ); ?></span>
					</div>
				</div>
			</div>

			<div class="lk-card">
				<div class="lk-card-header">
					<h3><?php esc_html_e( 'Recent Activity', 'lovekin' ); ?></h3>
				</div>
				<ul class="lk-activity">
					<?php if ( empty( $attempts ) ) : ?>
						<li class="lk-empty"><?php esc_html_e( 'No assessments yet. Start with a course!', 'lovekin' ); ?></li>
					<?php else : ?>
						<?php foreach ( $attempts as $attempt ) :
							$course = get_post( $attempt->course_id );
							$band   = LoveKin_Reports::get_remark_for_score( $attempt->score );
							?>
							<li class="lk-activity-item">
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

			<?php if ( $primary ) : ?>
				<div class="lk-card">
					<div class="lk-card-header">
						<h3><?php esc_html_e( 'Your Mentor', 'lovekin' ); ?></h3>
					</div>
					<?php
					$mentor = get_user_by( 'id', $primary->primary_user_id );
					$actions = array();
					if ( $mentor && $mentor->user_email ) {
						$actions[] = array(
							'label' => __( 'Email Mentor', 'lovekin' ),
							'url'   => 'mailto:' . $mentor->user_email,
							'class' => 'lk-button lk-button--ghost',
						);
					}
					echo wp_kses_post( self::render_profile_card( $primary->primary_user_id, false, $actions ) );
					?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $mentees ) ) : ?>
				<div class="lk-card">
					<div class="lk-card-header">
						<h3><?php esc_html_e( 'My Mentees', 'lovekin' ); ?></h3>
						<span class="lk-meta">
							<?php
							printf(
								esc_html__( '%d members', 'lovekin' ),
								(int) count( $mentees )
							);
							?>
						</span>
					</div>
					<div class="lk-card-grid">
						<?php foreach ( $mentees as $mentee ) : ?>
							<?php
							$report_link = add_query_arg(
								array(
									'lk_tab'  => 'reports',
									'lk_user' => $mentee->secondary_user_id,
								)
							);
							$actions = array(
								array(
									'label' => __( 'View Report', 'lovekin' ),
									'url'   => $report_link,
									'class' => 'lk-button lk-button--ghost',
								),
							);
							echo wp_kses_post( self::render_profile_card( $mentee->secondary_user_id, true, $actions ) );
							?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $at_risk_members ) ) : ?>
				<div class="lk-card">
					<div class="lk-card-header">
						<h3><?php esc_html_e( 'At-Risk Members', 'lovekin' ); ?></h3>
						<span class="lk-badge lk-badge--danger"><?php esc_html_e( 'Needs attention', 'lovekin' ); ?></span>
					</div>
					<ul class="lk-activity">
						<?php foreach ( $at_risk_members as $risk ) : ?>
							<li class="lk-activity-item">
								<div>
									<strong><?php echo esc_html( $risk['user']->display_name ); ?></strong>
									<span class="lk-meta">
										<?php
										printf(
											esc_html__( 'Avg last %d attempts', 'lovekin' ),
											(int) $risk['count']
										);
										?>
									</span>
								</div>
								<div class="lk-activity-actions">
									<span class="lk-score-pill" style="--lk-score-color: var(--lk-danger);">
										<?php echo esc_html( number_format_i18n( $risk['average'], 1 ) ); ?>%
									</span>
									<a class="lk-button lk-button--ghost" href="<?php echo esc_url( add_query_arg( array( 'lk_tab' => 'reports', 'lk_user' => $risk['user']->ID ) ) ); ?>">
										<?php esc_html_e( 'View Report', 'lovekin' ); ?>
									</a>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_courses( $course_id, $base_url = '' ) {
		$course_posts = get_posts(
			array(
				'post_type'      => 'lk_course',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		if ( empty( $base_url ) ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
			$base_url    = home_url( $request_uri );
		}

		ob_start();
		?>
		<div class="lk-root lk-card">
			<h3><?php esc_html_e( 'Courses', 'lovekin' ); ?></h3>
			<div class="lk-course-grid">
				<?php if ( empty( $course_posts ) ) : ?>
					<p><?php esc_html_e( 'No courses available yet.', 'lovekin' ); ?></p>
				<?php else : ?>
					<?php foreach ( $course_posts as $course ) :
						$assessment = LoveKin_Assessments::get_assessment_for_course( $course->ID );
						$file_url = get_post_meta( $course->ID, '_lk_course_file_url', true );
						$course_url = get_permalink( $course->ID );
						$latest_score = LoveKin_Assessments::get_latest_score_for_user_course( get_current_user_id(), $course->ID );
						$has_assessment = (bool) $assessment;
						?>
						<div class="lk-course-card">
							<div class="lk-course-body">
								<h4 class="lk-course-title"><?php echo esc_html( $course->post_title ); ?></h4>
								<span class="lk-status-pill <?php echo $has_assessment ? 'lk-status-pill--ready' : 'lk-status-pill--soon'; ?>">
									<span class="dashicons <?php echo $has_assessment ? 'dashicons-yes' : 'dashicons-hourglass'; ?>"></span>
									<?php echo esc_html( $has_assessment ? __( 'Ready', 'lovekin' ) : __( 'Coming soon', 'lovekin' ) ); ?>
								</span>
								<p class="lk-course-desc"><?php echo esc_html( wp_trim_words( $course->post_content, 32 ) ); ?></p>
								<div class="lk-course-metrics">
									<?php if ( $latest_score ) : ?>
										<span><?php esc_html_e( 'Last score:', 'lovekin' ); ?> <?php echo esc_html( number_format_i18n( $latest_score, 1 ) ); ?>%</span>
									<?php else : ?>
										<span><?php esc_html_e( 'Not attempted yet', 'lovekin' ); ?></span>
									<?php endif; ?>
								</div>
								<?php if ( $file_url ) : ?>
									<a class="lk-button lk-button--ghost lk-button--icon" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener">
										<span class="dashicons dashicons-download"></span>
										<?php esc_html_e( 'Download materials', 'lovekin' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="lk-course-actions">
								<?php if ( $has_assessment ) : ?>
									<a class="lk-button lk-button--primary" href="<?php echo esc_url( get_permalink( $assessment->ID ) ); ?>">
										<?php esc_html_e( 'Take Assessment', 'lovekin' ); ?>
									</a>
									<?php if ( $course_url ) : ?>
										<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $course_url ); ?>"><?php esc_html_e( 'View Course', 'lovekin' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									<?php if ( $course_url ) : ?>
										<a class="lk-button lk-button--primary" href="<?php echo esc_url( $course_url ); ?>"><?php esc_html_e( 'View Course', 'lovekin' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $course_id ) : ?>
			<?php echo wp_kses_post( self::render_assessment( array( 'course_id' => $course_id ) ) ); ?>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	public static function render_profile() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$user_id = get_current_user_id();
		$user    = get_user_by( 'id', $user_id );
		$membership_code = get_user_meta( $user_id, 'lk_membership_code', true );
		if ( ! $membership_code || false !== strpos( $membership_code, '-' ) ) {
			$membership_code = 'LK' . $user_id . wp_rand( 1000, 9999 );
			update_user_meta( $user_id, 'lk_membership_code', $membership_code );
		}
		$occupation = get_user_meta( $user_id, 'lk_occupation', true );
		$position = get_user_meta( $user_id, 'lk_org_chart_position', true );
		$avatar_id = get_user_meta( $user_id, 'lk_profile_picture', true );
		$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
		$status = in_array( 'lk_primary', (array) $user->roles, true ) ? 'Primary' : 'Secondary';
		$profile_attempts = LoveKin_Reports::get_user_attempts( $user_id );
		$profile_average = 0;
		if ( ! empty( $profile_attempts ) ) {
			$total = 0;
			foreach ( $profile_attempts as $attempt ) {
				$total += (float) $attempt->score;
			}
			$profile_average = $total / count( $profile_attempts );
		}
		$profile_latest = LoveKin_Assessments::get_latest_score_for_user( $user_id );

		ob_start();
		$profile_notice = get_transient( 'lk_profile_updated_' . $user_id );
		if ( $profile_notice ) {
			delete_transient( 'lk_profile_updated_' . $user_id );
		}
		?>
		<div class="lk-root lk-profile" data-lk="profile">
			<?php if ( $profile_notice ) : ?>
				<div class="lk-alert lk-alert--success"><?php esc_html_e( 'Profile updated successfully.', 'lovekin' ); ?></div>
			<?php endif; ?>
			<div class="lk-profile-grid">
				<div class="lk-card lk-profile-aside">
					<div class="lk-profile-header">
						<div class="lk-avatar" style="background-image: url('<?php echo esc_url( $avatar_url ); ?>');">
							<?php if ( ! $avatar_url ) : ?>
								<span><?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?></span>
							<?php endif; ?>
						</div>
						<div>
							<h3><?php echo esc_html( $user->display_name ); ?></h3>
							<span class="lk-badge <?php echo 'Primary' === $status ? 'lk-badge--primary' : 'lk-badge--success'; ?>"><?php echo esc_html( $status ); ?></span>
						</div>
					</div>
					<div class="lk-profile-meta">
						<p><strong><?php esc_html_e( 'Membership Code', 'lovekin' ); ?></strong><br><?php echo esc_html( $membership_code ); ?></p>
						<p><strong><?php esc_html_e( 'Email', 'lovekin' ); ?></strong><br><?php echo esc_html( $user->user_email ); ?></p>
					</div>
					<div class="lk-profile-stats">
						<div>
							<span><?php esc_html_e( 'Assessments', 'lovekin' ); ?></span>
							<strong><?php echo esc_html( count( $profile_attempts ) ); ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Average', 'lovekin' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $profile_average, 1 ) ); ?>%</strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Latest', 'lovekin' ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $profile_latest ?: 0, 1 ) ); ?>%</strong>
						</div>
					</div>
				</div>

				<div class="lk-card lk-profile-form">
					<h3><?php esc_html_e( 'Edit Profile', 'lovekin' ); ?></h3>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-form">
						<input type="hidden" name="action" value="lk_update_profile" />
						<?php wp_nonce_field( 'lk_update_profile' ); ?>

						<div class="lk-field">
							<label><?php esc_html_e( 'Profile Picture', 'lovekin' ); ?></label>
							<div class="lk-dropzone" data-lk="dropzone">
								<input type="file" name="profile_picture" accept="image/*" />
								<span><?php esc_html_e( 'Drag and drop or click to upload', 'lovekin' ); ?></span>
							</div>
							<span class="lk-help-text"><?php esc_html_e( 'PNG or JPG, up to 2MB recommended.', 'lovekin' ); ?></span>
						</div>

						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'Email', 'lovekin' ); ?></label>
								<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled />
							</div>
						</div>

						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'Occupation', 'lovekin' ); ?></label>
								<input type="text" name="occupation" value="<?php echo esc_attr( $occupation ); ?>" />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Organization Position', 'lovekin' ); ?></label>
								<input type="text" name="position" value="<?php echo esc_attr( $position ); ?>" />
							</div>
						</div>

						<button type="submit" class="lk-button"><?php esc_html_e( 'Save Profile', 'lovekin' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function handle_profile_update() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! current_user_can( 'lk_edit_profile' ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		check_admin_referer( 'lk_update_profile' );
		$user_id = get_current_user_id();

		if ( ! empty( $_FILES['profile_picture']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attachment_id = media_handle_upload( 'profile_picture', 0 );
			if ( ! is_wp_error( $attachment_id ) ) {
				update_user_meta( $user_id, 'lk_profile_picture', $attachment_id );
			}
		}

		$occupation      = isset( $_POST['occupation'] ) ? sanitize_text_field( wp_unslash( $_POST['occupation'] ) ) : '';
		$position        = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '';

		update_user_meta( $user_id, 'lk_occupation', $occupation );
		update_user_meta( $user_id, 'lk_org_chart_position', $position );

		set_transient( 'lk_profile_updated_' . $user_id, 1, 30 );
		$redirect = wp_get_referer() ? wp_get_referer() : home_url();
		$redirect = remove_query_arg( array( 'lk_profile' ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_report() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$user_id = get_current_user_id();
		$view_id = $user_id;
		if ( isset( $_GET['lk_user'] ) ) {
			$requested = absint( $_GET['lk_user'] );
			$relationship = LoveKin_Relationships::get_primary_for_secondary( $requested );
			if ( $relationship && (int) $relationship->primary_user_id === $user_id ) {
				$view_id = $requested;
			}
			if ( current_user_can( 'lk_view_all_reports' ) ) {
				$view_id = $requested;
			}
		}

		return LoveKin_Reports::render_report_view( $view_id, current_user_can( 'lk_view_all_reports' ) || current_user_can( 'lk_edit_remarks' ) );
	}

	public static function render_assessment( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$atts = shortcode_atts( array( 'course_id' => 0, 'assessment_id' => 0 ), $atts );
		$course_id     = absint( $atts['course_id'] );
		$assessment_id = absint( $atts['assessment_id'] );
		if ( is_singular( 'lk_assessment' ) ) {
			$assessment_id = get_the_ID();
		}

		if ( $course_id && ! $assessment_id ) {
			$assessment = LoveKin_Assessments::get_assessment_for_course( $course_id );
			$assessment_id = $assessment ? $assessment->ID : 0;
		}

		if ( ! $assessment_id ) {
			$assessments = get_posts( array(
				'post_type'      => 'lk_assessment',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
			) );
			if ( empty( $assessments ) ) {
				return '<div class="lk-card">' . esc_html__( 'No assessments are available yet.', 'lovekin' ) . '</div>';
			}
			ob_start();
			?>
			<div class="lk-card">
				<h3><?php esc_html_e( 'Available Assessments', 'lovekin' ); ?></h3>
				<ul>
					<?php foreach ( $assessments as $assessment_item ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $assessment_item->ID ) ); ?>"><?php echo esc_html( $assessment_item->post_title ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			return ob_get_clean();
		}

		$assessment_post = get_post( $assessment_id );
		if ( ! $assessment_post || 'lk_assessment' !== $assessment_post->post_type ) {
			return '<div class="lk-card">' . esc_html__( 'Assessment not found.', 'lovekin' ) . '</div>';
		}

		$questions = get_post_meta( $assessment_id, '_lk_questions', true );
		if ( ! is_array( $questions ) || empty( $questions ) ) {
			return '<div class="lk-card">' . esc_html__( 'Assessment has no questions yet.', 'lovekin' ) . '</div>';
		}

		$course_id = $course_id ? $course_id : (int) get_post_meta( $assessment_id, '_lk_course_id', true );
		$user_id = get_current_user_id();
		$attempt_id = absint( get_user_meta( $user_id, 'lk_last_attempt_' . $assessment_id, true ) );
		if ( ! $attempt_id && isset( $_GET['lk_result'] ) ) {
			$attempt_id = absint( $_GET['lk_result'] );
		}

		ob_start();
		?>
		<?php $assessment_class = is_singular( 'lk_assessment' ) ? 'lk-root lk-assessment lk-assessment-inner' : 'lk-root lk-card lk-assessment'; ?>
		<div class="<?php echo esc_attr( $assessment_class ); ?>" data-lk="assessment">
			<h3><?php esc_html_e( 'Assessment', 'lovekin' ); ?></h3>

			<?php if ( $attempt_id ) : ?>
				<?php $attempt = LoveKin_Assessments::get_attempt( $attempt_id ); ?>
				<?php if ( $attempt && (int) $attempt->user_id === (int) get_current_user_id() ) : ?>
					<div class="lk-assessment-result">
						<p class="lk-result-label"><?php esc_html_e( 'Last assessment score', 'lovekin' ); ?></p>
						<?php echo wp_kses_post( self::render_assessment_result( $attempt ) ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lk_submit_assessment" />
				<?php wp_nonce_field( 'lk_submit_assessment' ); ?>
				<input type="hidden" name="assessment_id" value="<?php echo esc_attr( $assessment_id ); ?>" />
				<input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>" />

				<div id="lk-assessment-questions">
				<?php foreach ( $questions as $index => $question ) : ?>
					<div class="lk-question">
						<div class="lk-question-title">
							<span><?php echo esc_html( sprintf( __( 'Question %d of %d', 'lovekin' ), $index + 1, count( $questions ) ) ); ?></span>
							<strong><?php echo esc_html( $question['question'] ); ?></strong>
						</div>
						<div class="lk-option-list">
							<?php foreach ( $question['options'] as $opt_index => $option ) :
								$label = array( 'A', 'B', 'C', 'D' )[ $opt_index ];
								?>
								<label class="lk-option">
									<input type="radio" name="answers[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $label ); ?>" />
									<span><?php echo esc_html( $label . '. ' . $option ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				</div>

				<button type="submit" class="lk-button lk-button--primary" data-lk="submit-assessment"><?php esc_html_e( 'Submit Assessment', 'lovekin' ); ?></button>
			</form>

		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_assessment_result( $attempt ) {
		$band = LoveKin_Reports::get_remark_for_score( $attempt->score );
		$message = $attempt->score >= 75 ? __( 'Excellent work! Keep the momentum going.', 'lovekin' ) : ( $attempt->score >= 50 ? __( 'Progressing well. You are on track.', 'lovekin' ) : __( 'Keep practicing. You can do this!', 'lovekin' ) );
		$answers = json_decode( $attempt->answers_json, true );
		if ( ! is_array( $answers ) ) {
			$answers = array();
		}
		ob_start();
		?>
		<div class="lk-result">
			<div class="lk-result-score" style="--lk-score-color: <?php echo esc_attr( $band['color'] ); ?>;">
				<span><?php echo esc_html( number_format_i18n( $attempt->score, 1 ) ); ?>%</span>
			</div>
			<p><?php echo esc_html( $message ); ?></p>
			<div class="lk-result-actions">
				<a class="lk-button lk-button--ghost" href="<?php echo esc_url( get_permalink( $attempt->assessment_id ) . '#lk-assessment-questions' ); ?>"><?php esc_html_e( 'Retake Assessment', 'lovekin' ); ?></a>
			</div>
			<?php // Answer review intentionally hidden per UX direction. ?>

		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_funding() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$user = wp_get_current_user();
		$membership_code = get_user_meta( $user->ID, 'lk_membership_code', true );
		if ( ! $membership_code || false !== strpos( $membership_code, '-' ) ) {
			$membership_code = 'LK' . $user->ID . wp_rand( 1000, 9999 );
			update_user_meta( $user->ID, 'lk_membership_code', $membership_code );
		}
		$requests = LoveKin_Funding::get_user_requests( $user->ID );
		$funding_notice = isset( $_GET['lk_funding'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_funding'] ) ) : '';

		ob_start();
		?>
		<div class="lk-root lk-funding">
			<?php if ( $funding_notice ) : ?>
				<?php if ( 'submitted' === $funding_notice ) : ?>
					<div class="lk-alert lk-alert--success"><?php esc_html_e( 'Funding request submitted successfully.', 'lovekin' ); ?></div>
				<?php elseif ( 'missing' === $funding_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Please complete all required fields before submitting.', 'lovekin' ); ?></div>
				<?php elseif ( 'type' === $funding_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Unsupported file type for supporting document.', 'lovekin' ); ?></div>
				<?php elseif ( 'size' === $funding_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Supporting document exceeds the maximum file size.', 'lovekin' ); ?></div>
				<?php elseif ( 'upload' === $funding_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Unable to upload supporting document. Please try again.', 'lovekin' ); ?></div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="lk-card">
				<div class="lk-card-header">
					<h3><?php esc_html_e( 'Funding Request', 'lovekin' ); ?></h3>
					<span class="lk-meta"><?php esc_html_e( 'Complete all steps to submit', 'lovekin' ); ?></span>
				</div>
				<div class="lk-stepper" data-lk="funding-stepper">
					<div class="lk-step is-active" data-step="1"><?php esc_html_e( 'Request Details', 'lovekin' ); ?></div>
					<div class="lk-step" data-step="2"><?php esc_html_e( 'Purpose & Account', 'lovekin' ); ?></div>
					<div class="lk-step" data-step="3"><?php esc_html_e( 'Review & Submit', 'lovekin' ); ?></div>
				</div>
				<div class="lk-form-error" data-lk="form-error" style="display:none;"></div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" data-lk="funding-form">
					<input type="hidden" name="action" value="lk_submit_funding" />
					<?php wp_nonce_field( 'lk_submit_funding' ); ?>

					<div class="lk-step-panel is-active" data-step="1">
						<div class="lk-form-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'Full Name', 'lovekin' ); ?></label>
								<input type="text" value="<?php echo esc_attr( $user->display_name ); ?>" disabled />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Email Address', 'lovekin' ); ?></label>
								<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Membership Code', 'lovekin' ); ?></label>
								<input type="text" value="<?php echo esc_attr( $membership_code ); ?>" disabled />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Bank Format', 'lovekin' ); ?></label>
								<select name="format" required>
									<option value="FAC"><?php esc_html_e( 'Free-Access Community Format (FAC)', 'lovekin' ); ?></option>
									<option value="PC"><?php esc_html_e( 'Proportionate Community Format (PC)', 'lovekin' ); ?></option>
								</select>
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Amount Required', 'lovekin' ); ?></label>
								<div class="lk-input-group">
									<span class="lk-input-prefix">&#8358;</span>
									<input type="number" step="0.01" name="amount" placeholder="<?php esc_attr_e( 'Amount Required', 'lovekin' ); ?>" required />
								</div>
							</div>
						</div>
						<div class="lk-step-actions">
							<button type="button" class="lk-button" data-lk="next-step"><?php esc_html_e( 'Next', 'lovekin' ); ?></button>
						</div>
					</div>

					<div class="lk-step-panel" data-step="2">
						<div class="lk-field">
							<label><?php esc_html_e( 'Purpose of Fund', 'lovekin' ); ?></label>
							<textarea name="purpose" rows="4" placeholder="<?php esc_attr_e( 'Purpose of fund', 'lovekin' ); ?>" required></textarea>
						</div>
						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'Account Name', 'lovekin' ); ?></label>
								<input type="text" name="account_name" placeholder="<?php esc_attr_e( 'Account name', 'lovekin' ); ?>" required />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Account Number', 'lovekin' ); ?></label>
								<input type="text" name="account_number" placeholder="<?php esc_attr_e( 'Account number', 'lovekin' ); ?>" required />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Bank Name', 'lovekin' ); ?></label>
								<input type="text" name="bank_name" placeholder="<?php esc_attr_e( 'Bank name', 'lovekin' ); ?>" required />
							</div>
						</div>
						<div class="lk-field">
							<label><?php esc_html_e( 'Supporting Document (optional)', 'lovekin' ); ?></label>
							<input type="file" name="supporting_document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
							<span class="lk-help-text"><?php esc_html_e( 'Upload PDF, DOCX, or image files.', 'lovekin' ); ?></span>
						</div>
						<div class="lk-step-actions">
							<button type="button" class="lk-button lk-button--ghost" data-lk="prev-step"><?php esc_html_e( 'Back', 'lovekin' ); ?></button>
							<button type="button" class="lk-button" data-lk="next-step"><?php esc_html_e( 'Review', 'lovekin' ); ?></button>
						</div>
					</div>

					<div class="lk-step-panel" data-step="3">
						<div class="lk-review-card">
							<h4><?php esc_html_e( 'Review your request', 'lovekin' ); ?></h4>
							<ul class="lk-review-list">
								<li><strong><?php esc_html_e( 'Format', 'lovekin' ); ?>:</strong> <span data-lk-review="format"></span></li>
								<li><strong><?php esc_html_e( 'Amount', 'lovekin' ); ?>:</strong> <span data-lk-review="amount"></span></li>
								<li><strong><?php esc_html_e( 'Purpose', 'lovekin' ); ?>:</strong> <span data-lk-review="purpose"></span></li>
								<li><strong><?php esc_html_e( 'Account Name', 'lovekin' ); ?>:</strong> <span data-lk-review="account_name"></span></li>
								<li><strong><?php esc_html_e( 'Account Number', 'lovekin' ); ?>:</strong> <span data-lk-review="account_number"></span></li>
								<li><strong><?php esc_html_e( 'Bank Name', 'lovekin' ); ?>:</strong> <span data-lk-review="bank_name"></span></li>
							</ul>
						</div>
						<div class="lk-step-actions">
							<button type="button" class="lk-button lk-button--ghost" data-lk="prev-step"><?php esc_html_e( 'Back', 'lovekin' ); ?></button>
							<button type="submit" class="lk-button"><?php esc_html_e( 'Submit Request', 'lovekin' ); ?></button>
						</div>
					</div>
				</form>
			</div>

			<div class="lk-card">
				<div class="lk-card-header">
					<h3><?php esc_html_e( 'Request History', 'lovekin' ); ?></h3>
				</div>
			<table class="lk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Amount', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Admin Notes', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Document', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Account Details', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $requests ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No requests yet.', 'lovekin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $requests as $request ) : ?>
							<tr>
								<td>&#8358;<?php echo esc_html( number_format_i18n( $request->amount, 2 ) ); ?></td>
								<td><span class="lk-badge lk-badge--<?php echo esc_attr( $request->status ); ?>"><?php echo esc_html( ucfirst( $request->status ) ); ?></span></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y', $request->created_at ) ); ?></td>
								<td><?php echo esc_html( $request->admin_notes ); ?></td>
								<td>
									<?php if ( ! empty( $request->supporting_file ) ) : ?>
										<a class="lk-button lk-button--ghost" href="<?php echo esc_url( LoveKin_Funding::get_download_url( $request->id ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
									<?php else : ?>
										<?php esc_html_e( '-', 'lovekin' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$details = LoveKin_Funding::parse_account_details( $request->account_details );
									printf(
										'%s<br>%s<br>%s',
										esc_html( $details['name'] ),
										esc_html( $details['number'] ),
										esc_html( $details['bank'] )
									);
									?>
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

	public static function render_documents() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$docs = LoveKin_Documents::get_documents_for_user( get_current_user_id() );
		$doc_categories = array();
		foreach ( $docs as $doc ) {
			if ( ! empty( $doc->category ) ) {
				$doc_categories[ $doc->category ] = ucfirst( $doc->category );
			}
		}
		ob_start();
		?>
		<div class="lk-root lk-card">
			<div class="lk-card-header">
				<h3><?php esc_html_e( 'Document Library', 'lovekin' ); ?></h3>
				<div class="lk-toolbar">
					<div class="lk-search">
						<span class="dashicons dashicons-search"></span>
						<input type="search" placeholder="<?php esc_attr_e( 'Search documents', 'lovekin' ); ?>" data-lk="doc-search" />
					</div>
					<select data-lk="doc-filter">
						<option value="all"><?php esc_html_e( 'All Categories', 'lovekin' ); ?></option>
						<?php foreach ( $doc_categories as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="lk-document-grid">
				<?php if ( empty( $docs ) ) : ?>
					<p class="lk-empty"><?php esc_html_e( 'No documents available.', 'lovekin' ); ?></p>
				<?php else : ?>
					<?php foreach ( $docs as $doc ) : ?>
						<div class="lk-document-card" data-category="<?php echo esc_attr( $doc->category ); ?>" data-title="<?php echo esc_attr( strtolower( $doc->title ) ); ?>">
							<h4><?php echo esc_html( $doc->title ); ?></h4>
							<p><?php echo esc_html( $doc->description ); ?></p>
							<span class="lk-meta"><?php echo esc_html( strtoupper( $doc->file_type ) ); ?> · <?php echo esc_html( number_format_i18n( $doc->file_size / 1024, 1 ) ); ?> KB · <?php echo esc_html( ucfirst( $doc->category ) ); ?></span>
							<a class="lk-button lk-button--ghost" href="<?php echo esc_url( LoveKin_Documents::get_download_url( $doc->id ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_archive() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$files = LoveKin_Archive::get_user_files( get_current_user_id() );
		$settings = get_option( 'lovekin_upload_settings', array() );
		$quota_mb = isset( $settings['archive_quota_mb'] ) ? (int) $settings['archive_quota_mb'] : 100;
		$total_bytes = 0;
		$folders = array();
		foreach ( $files as $file ) {
			$total_bytes += (int) $file->file_size;
			if ( ! empty( $file->folder ) ) {
				$folders[ $file->folder ] = $file->folder;
			}
		}
		$used_mb = $total_bytes / ( 1024 * 1024 );
		$usage_percent = $quota_mb > 0 ? min( 100, ( $used_mb / $quota_mb ) * 100 ) : 0;
		$archive_notice = isset( $_GET['lk_archive'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_archive'] ) ) : '';
		ob_start();
		?>
		<div class="lk-card">
			<div class="lk-card-header">
				<h3><?php esc_html_e( 'My Archive', 'lovekin' ); ?></h3>
				<span class="lk-meta">
					<?php
					printf(
						esc_html__( '%.1f MB of %d MB used', 'lovekin' ),
						$used_mb,
						$quota_mb
					);
					?>
				</span>
			</div>
			<?php if ( $archive_notice ) : ?>
				<?php if ( 'uploaded' === $archive_notice ) : ?>
					<div class="lk-alert lk-alert--success"><?php esc_html_e( 'File uploaded successfully.', 'lovekin' ); ?></div>
				<?php elseif ( 'type' === $archive_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Unsupported file type.', 'lovekin' ); ?></div>
				<?php elseif ( 'size' === $archive_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'File exceeds the maximum size.', 'lovekin' ); ?></div>
				<?php elseif ( 'upload' === $archive_notice ) : ?>
					<div class="lk-alert lk-alert--error"><?php esc_html_e( 'Unable to upload file. Please try again.', 'lovekin' ); ?></div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="lk-progress">
				<div class="lk-progress-bar" style="width: <?php echo esc_attr( $usage_percent ); ?>%;"></div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-upload">
				<input type="hidden" name="action" value="lk_upload_archive" />
				<?php wp_nonce_field( 'lk_upload_archive' ); ?>
				<input type="text" name="folder" placeholder="<?php esc_attr_e( 'Folder name (optional)', 'lovekin' ); ?>" />
				<input type="file" name="archive_file" />
				<button type="submit" class="lk-button"><?php esc_html_e( 'Upload', 'lovekin' ); ?></button>
			</form>

			<div class="lk-toolbar">
				<div class="lk-search">
					<span class="dashicons dashicons-search"></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Search files', 'lovekin' ); ?>" data-lk="archive-search" />
				</div>
				<select data-lk="archive-folder">
					<option value="all"><?php esc_html_e( 'All folders', 'lovekin' ); ?></option>
					<?php foreach ( $folders as $folder_name ) : ?>
						<option value="<?php echo esc_attr( $folder_name ); ?>"><?php echo esc_html( $folder_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<table class="lk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'File', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Folder', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Size', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Action', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $files ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No files uploaded yet.', 'lovekin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $files as $file ) : ?>
							<tr data-folder="<?php echo esc_attr( $file->folder ); ?>" data-name="<?php echo esc_attr( strtolower( $file->file_name ) ); ?>">
								<td><?php echo esc_html( $file->file_name ); ?></td>
								<td><?php echo esc_html( $file->folder ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $file->file_size / 1024, 1 ) ); ?> KB</td>
								<td>
									<a class="lk-button lk-button--ghost" href="<?php echo esc_url( add_query_arg( array( 'lk_archive_download' => $file->id ), home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
									<a class="lk-button lk-button--danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lk_delete_archive&file_id=' . $file->id ), 'lk_delete_archive' ) ); ?>"><?php esc_html_e( 'Delete', 'lovekin' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_profile_card( $user_id, $show_score = false, $actions = array() ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return '';
		}

		$avatar_id  = get_user_meta( $user_id, 'lk_profile_picture', true );
		$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
		$score      = $show_score ? LoveKin_Assessments::get_latest_score_for_user( $user_id ) : null;

		ob_start();
		?>
		<div class="lk-profile-card">
			<div class="lk-profile-main">
				<div class="lk-avatar" style="background-image: url('<?php echo esc_url( $avatar_url ); ?>');">
					<?php if ( ! $avatar_url ) : ?>
						<span><?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?></span>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php echo esc_html( $user->display_name ); ?></strong>
					<p><?php echo esc_html( $user->user_email ); ?></p>
					<?php if ( $show_score ) : ?>
						<span class="lk-meta"><?php esc_html_e( 'Latest score:', 'lovekin' ); ?> <?php echo esc_html( $score ? number_format_i18n( $score, 1 ) . '%' : '--' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( ! empty( $actions ) ) : ?>
				<div class="lk-profile-actions">
					<?php foreach ( $actions as $action ) : ?>
						<a class="<?php echo esc_attr( $action['class'] ); ?>" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_at_risk_members( $user_id ) {
		$members = array();
		if ( current_user_can( 'lk_view_all_reports' ) ) {
			$members = get_users(
				array(
					'role__in' => array( 'lk_primary', 'lk_secondary' ),
				)
			);
		} else {
			$mentees = LoveKin_Relationships::get_secondaries_for_primary( $user_id );
			foreach ( $mentees as $mentee ) {
				$user = get_user_by( 'id', $mentee->secondary_user_id );
				if ( $user ) {
					$members[] = $user;
				}
			}
		}

		if ( empty( $members ) ) {
			return array();
		}

		$at_risk = array();
		foreach ( $members as $member ) {
			$recent_attempts = LoveKin_Assessments::get_attempts_for_user( $member->ID, 3 );
			if ( empty( $recent_attempts ) ) {
				continue;
			}
			$total = 0;
			foreach ( $recent_attempts as $attempt ) {
				$total += (float) $attempt->score;
			}
			$average = $total / count( $recent_attempts );
			if ( $average < 50 ) {
				$at_risk[] = array(
					'user'    => $member,
					'average' => $average,
					'count'   => count( $recent_attempts ),
				);
			}
		}

		return $at_risk;
	}

	private static function render_login_prompt() {
		return '<div class="lk-card">' . esc_html__( 'Please log in to access this feature.', 'lovekin' ) . '</div>';
	}
}
