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

		ob_start();
		?>
		<div class="lk-root lk-dashboard" data-lk="dashboard">
			<div class="lk-dashboard-header">
				<div>
					<p class="lk-eyebrow"><?php esc_html_e( 'Welcome back', 'lovekin' ); ?></p>
					<h2 class="lk-title"><?php echo esc_html( $user->display_name ); ?></h2>
				</div>
				<div class="lk-quick-actions">
					<a class="lk-button" href="<?php echo esc_url( add_query_arg( 'lk_tab', 'courses' ) ); ?>"><?php esc_html_e( 'Explore Courses', 'lovekin' ); ?></a>
					<a class="lk-button lk-button--ghost" href="<?php echo esc_url( add_query_arg( 'lk_tab', 'reports' ) ); ?>"><?php esc_html_e( 'View Reports', 'lovekin' ); ?></a>
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
					<a class="lk-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'lk_tab', $key ) ); ?>" data-lk="tab" data-tab="<?php echo esc_attr( $key ); ?>">
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

		ob_start();
		?>
		<div class="lk-grid">
			<div class="lk-report-hero">
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Assessments Taken', 'lovekin' ); ?></h3>
					<span class="lk-hero-value"><?php echo esc_html( count( $all_attempts ) ); ?></span>
				</div>
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Average Score', 'lovekin' ); ?></h3>
					<span class="lk-hero-value"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?>%</span>
				</div>
				<div class="lk-hero-card">
					<h3><?php esc_html_e( 'Latest Score', 'lovekin' ); ?></h3>
					<span class="lk-hero-value"><?php echo esc_html( number_format_i18n( LoveKin_Assessments::get_latest_score_for_user( $user_id ) ?: 0, 1 ) ); ?>%</span>
				</div>
			</div>
			<div class="lk-card">
				<h3><?php esc_html_e( 'Recent Activity', 'lovekin' ); ?></h3>
				<ul class="lk-activity">
					<?php if ( empty( $attempts ) ) : ?>
						<li><?php esc_html_e( 'No assessments yet. Start with a course!', 'lovekin' ); ?></li>
					<?php else : ?>
						<?php foreach ( $attempts as $attempt ) :
							$course = get_post( $attempt->course_id );
							?>
							<li>
								<strong><?php echo esc_html( $course ? $course->post_title : __( 'Course', 'lovekin' ) ); ?></strong>
								<span><?php echo esc_html( number_format_i18n( $attempt->score, 1 ) ); ?>%</span>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</div>

			<?php if ( $primary ) : ?>
				<div class="lk-card">
					<h3><?php esc_html_e( 'Your Mentor', 'lovekin' ); ?></h3>
					<?php echo wp_kses_post( self::render_profile_card( $primary->primary_user_id ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $mentees ) ) : ?>
				<div class="lk-card">
					<h3><?php esc_html_e( 'My Mentees', 'lovekin' ); ?></h3>
					<div class="lk-card-grid">
						<?php foreach ( $mentees as $mentee ) : ?>
							<?php echo wp_kses_post( self::render_profile_card( $mentee->secondary_user_id, true ) ); ?>
						<?php endforeach; ?>
					</div>
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
						?>
						<div class="lk-course-card">
							<h4><?php echo esc_html( $course->post_title ); ?></h4>
							<p><?php echo esc_html( wp_trim_words( $course->post_content, 24 ) ); ?></p>
							<div class="lk-course-actions">
								<?php if ( $course_url ) : ?>
									<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $course_url ); ?>"><?php esc_html_e( 'View Course', 'lovekin' ); ?></a>
								<?php endif; ?>
								<?php if ( $file_url ) : ?>
									<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download PDF', 'lovekin' ); ?></a>
								<?php endif; ?>
							</div>
							<?php if ( $assessment ) : ?>
								<a class="lk-button" href="<?php echo esc_url( get_permalink( $assessment->ID ) ); ?>">
									<?php esc_html_e( 'Take Assessment', 'lovekin' ); ?>
								</a>
							<?php else : ?>
								<span class="lk-badge lk-badge--warning"><?php esc_html_e( 'Assessment coming soon', 'lovekin' ); ?></span>
							<?php endif; ?>
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
		if ( ! $membership_code ) {
			$membership_code = 'LK-' . $user_id . '-' . wp_rand( 1000, 9999 );
			update_user_meta( $user_id, 'lk_membership_code', $membership_code );
		}
		$occupation = get_user_meta( $user_id, 'lk_occupation', true );
		$position = get_user_meta( $user_id, 'lk_org_chart_position', true );
		$avatar_id = get_user_meta( $user_id, 'lk_profile_picture', true );
		$avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
		$status = in_array( 'lk_primary', (array) $user->roles, true ) ? 'Primary' : 'Secondary';

		ob_start();
		?>
		<div class="lk-root lk-profile" data-lk="profile">
			<div class="lk-card">
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

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-form">
					<input type="hidden" name="action" value="lk_update_profile" />
					<?php wp_nonce_field( 'lk_update_profile' ); ?>
					<label><?php esc_html_e( 'Profile Picture', 'lovekin' ); ?></label>
					<input type="file" name="profile_picture" accept="image/*" />

					<label><?php esc_html_e( 'Membership Code', 'lovekin' ); ?></label>
					<input type="text" name="membership_code" value="<?php echo esc_attr( $membership_code ); ?>" />

					<label><?php esc_html_e( 'Email', 'lovekin' ); ?></label>
					<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled />

					<label><?php esc_html_e( 'Occupation', 'lovekin' ); ?></label>
					<input type="text" name="occupation" value="<?php echo esc_attr( $occupation ); ?>" />

					<label><?php esc_html_e( 'Organization Position', 'lovekin' ); ?></label>
					<input type="text" name="position" value="<?php echo esc_attr( $position ); ?>" />

					<button type="submit" class="lk-button"><?php esc_html_e( 'Save Profile', 'lovekin' ); ?></button>
				</form>
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

		$membership_code = isset( $_POST['membership_code'] ) ? sanitize_text_field( wp_unslash( $_POST['membership_code'] ) ) : '';
		$occupation      = isset( $_POST['occupation'] ) ? sanitize_text_field( wp_unslash( $_POST['occupation'] ) ) : '';
		$position        = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '';

		if ( $membership_code ) {
			update_user_meta( $user_id, 'lk_membership_code', $membership_code );
		}
		update_user_meta( $user_id, 'lk_occupation', $occupation );
		update_user_meta( $user_id, 'lk_org_chart_position', $position );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
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
		$requests = LoveKin_Funding::get_user_requests( $user->ID );
		$current_url = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		ob_start();
		?>
		<div class="lk-root lk-card">
			<h3><?php esc_html_e( 'Funding Request', 'lovekin' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lk_submit_funding" />
				<?php wp_nonce_field( 'lk_submit_funding' ); ?>

				<div class="lk-form-grid">
					<input type="text" value="<?php echo esc_attr( $user->display_name ); ?>" disabled />
					<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled />
					<input type="text" value="<?php echo esc_attr( $membership_code ); ?>" disabled />
					<select name="format">
						<option value="FAC"><?php esc_html_e( 'Free-Access Community Format (FAC)', 'lovekin' ); ?></option>
						<option value="PC"><?php esc_html_e( 'Proportionate Community Format (PC)', 'lovekin' ); ?></option>
					</select>
					<input type="number" step="0.01" name="amount" placeholder="<?php esc_attr_e( 'Amount Required', 'lovekin' ); ?>" />
				</div>

				<textarea name="purpose" rows="4" placeholder="<?php esc_attr_e( 'Purpose of fund', 'lovekin' ); ?>"></textarea>
				<textarea name="account_details" rows="3" placeholder="<?php esc_attr_e( 'Account details', 'lovekin' ); ?>"></textarea>
				<button type="submit" class="lk-button"><?php esc_html_e( 'Submit Request', 'lovekin' ); ?></button>
			</form>
		</div>

		<div class="lk-root lk-card">
			<h3><?php esc_html_e( 'Request History', 'lovekin' ); ?></h3>
			<table class="lk-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Amount', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Status', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'lovekin' ); ?></th>
						<th><?php esc_html_e( 'Admin Notes', 'lovekin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $requests ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No requests yet.', 'lovekin' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $requests as $request ) : ?>
							<tr>
								<td><?php echo esc_html( number_format_i18n( $request->amount, 2 ) ); ?></td>
								<td><span class="lk-badge lk-badge--<?php echo esc_attr( $request->status ); ?>"><?php echo esc_html( ucfirst( $request->status ) ); ?></span></td>
								<td><?php echo esc_html( mysql2date( 'M j, Y', $request->created_at ) ); ?></td>
								<td><?php echo esc_html( $request->admin_notes ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_documents() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$docs = LoveKin_Documents::get_documents_for_user( get_current_user_id() );
		ob_start();
		?>
		<div class="lk-root lk-card">
			<h3><?php esc_html_e( 'Document Library', 'lovekin' ); ?></h3>
			<div class="lk-document-grid">
				<?php if ( empty( $docs ) ) : ?>
					<p><?php esc_html_e( 'No documents available.', 'lovekin' ); ?></p>
				<?php else : ?>
					<?php foreach ( $docs as $doc ) : ?>
						<div class="lk-document-card">
							<h4><?php echo esc_html( $doc->title ); ?></h4>
							<p><?php echo esc_html( $doc->description ); ?></p>
							<span class="lk-meta"><?php echo esc_html( strtoupper( $doc->file_type ) ); ?> · <?php echo esc_html( number_format_i18n( $doc->file_size / 1024, 1 ) ); ?> KB</span>
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
		ob_start();
		?>
		<div class="lk-card">
			<h3><?php esc_html_e( 'My Archive', 'lovekin' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-upload">
				<input type="hidden" name="action" value="lk_upload_archive" />
				<?php wp_nonce_field( 'lk_upload_archive' ); ?>
				<input type="text" name="folder" placeholder="<?php esc_attr_e( 'Folder name (optional)', 'lovekin' ); ?>" />
				<input type="file" name="archive_file" />
				<button type="submit" class="lk-button"><?php esc_html_e( 'Upload', 'lovekin' ); ?></button>
			</form>

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
							<tr>
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

	private static function render_profile_card( $user_id, $show_score = false ) {
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
		<?php
		return ob_get_clean();
	}

	private static function render_login_prompt() {
		return '<div class="lk-card">' . esc_html__( 'Please log in to access this feature.', 'lovekin' ) . '</div>';
	}
}
