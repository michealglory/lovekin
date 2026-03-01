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
		add_shortcode( 'lovekin_login', array( __CLASS__, 'render_login_form' ) );
		add_shortcode( 'lovekin_register', array( __CLASS__, 'render_register_form' ) );
		add_shortcode( 'lovekin_protect', array( __CLASS__, 'render_protected_content' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		add_action( 'admin_post_lk_login', array( __CLASS__, 'handle_login_submission' ) );
		add_action( 'admin_post_nopriv_lk_login', array( __CLASS__, 'handle_login_submission' ) );
		add_action( 'admin_post_lk_register', array( __CLASS__, 'handle_register_submission' ) );
		add_action( 'admin_post_nopriv_lk_register', array( __CLASS__, 'handle_register_submission' ) );
		add_action( 'admin_post_lk_update_profile', array( __CLASS__, 'handle_profile_update' ) );
		add_action( 'admin_post_nopriv_lk_update_profile', array( __CLASS__, 'block_guest_submission' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_protected_page' ) );
		add_filter( 'login_errors', array( __CLASS__, 'filter_wp_login_errors' ) );
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
		wp_safe_redirect( self::build_login_url_with_notice( 'login_required' ) );
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
			self::enqueue_public_assets();
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
			'lovekin_login',
			'lovekin_register',
			'lovekin_protect',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				self::enqueue_public_assets();
				break;
			}
		}
	}

	private static function enqueue_public_assets() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/css/lovekin-public.css', array(), LOVEKIN_VERSION );
		wp_enqueue_script( 'lovekin-public', LOVEKIN_PLUGIN_URL . 'assets/js/lovekin-public.js', array( 'jquery' ), LOVEKIN_VERSION, true );
		wp_enqueue_script( 'lovekin-chart', 'https://cdn.jsdelivr.net/npm/chart.js', array(), LOVEKIN_VERSION, true );
		wp_localize_script(
			'lovekin-public',
			'lovekinPublic',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'remarkSaveNonce'  => wp_create_nonce( 'lk_update_remark_ajax' ),
				'savingText'       => __( 'Saving...', 'lovekin' ),
				'saveText'         => __( 'Save', 'lovekin' ),
				'remarkSaveFailed' => __( 'Unable to save remark. Please try again.', 'lovekin' ),
			)
		);
	}

	public static function maybe_redirect_protected_page() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$current_page_id  = (int) $post->ID;
		$login_page_id    = self::get_configured_auth_page_id( 'login_page_id' );
		$register_page_id = self::get_configured_auth_page_id( 'register_page_id' );
		$is_login_page    = ( $login_page_id && $current_page_id === $login_page_id ) || has_shortcode( $post->post_content, 'lovekin_login' );
		$is_register_page = ( $register_page_id && $current_page_id === $register_page_id ) || has_shortcode( $post->post_content, 'lovekin_register' );

		if ( is_user_logged_in() ) {
			if ( $is_login_page || $is_register_page ) {
				$dashboard_url = self::get_post_login_redirect_url( wp_get_current_user() );
				$current_url   = get_permalink( $current_page_id );
				if ( ! self::urls_share_path( $dashboard_url, $current_url ) ) {
					wp_safe_redirect( $dashboard_url );
					exit;
				}
			}
			return;
		}

		if ( $is_login_page || $is_register_page ) {
			return;
		}

		$explicit_protected_page_ids = apply_filters(
			'lovekin_protected_page_ids',
			array_filter(
				array(
					self::get_configured_auth_page_id( 'dashboard_page_id' ),
				)
			)
		);
		if ( in_array( $current_page_id, array_map( 'absint', $explicit_protected_page_ids ), true ) ) {
			wp_safe_redirect( self::build_login_url_with_notice( 'login_required' ) );
			exit;
		}

		$protected_shortcodes = apply_filters(
			'lovekin_protected_shortcodes',
			array(
				'lovekin_dashboard',
				'lovekin_profile',
				'lovekin_report',
				'lovekin_assessment',
				'lovekin_funding_request',
				'lovekin_documents',
				'lovekin_archive',
				'lovekin_protect',
			)
		);

		foreach ( $protected_shortcodes as $shortcode ) {
			if ( ! has_shortcode( $post->post_content, $shortcode ) ) {
				continue;
			}

			wp_safe_redirect( self::build_login_url_with_notice( 'login_required' ) );
			exit;
		}
	}

	public static function render_protected_content( $atts = array(), $content = '' ) {
		$atts = shortcode_atts(
			array(
				'roles' => '',
			),
			$atts,
			'lovekin_protect'
		);

		if ( ! is_user_logged_in() ) {
			return self::render_login_prompt();
		}

		$roles = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) $atts['roles'] ) ) ) );
		if ( empty( $roles ) ) {
			return do_shortcode( (string) $content );
		}

		$user = wp_get_current_user();
		if ( empty( array_intersect( $roles, (array) $user->roles ) ) ) {
			return '<div class="lk-card lk-alert lk-alert--error">' . esc_html__( 'You do not have permission to access this content.', 'lovekin' ) . '</div>';
		}

		return do_shortcode( (string) $content );
	}

	public static function filter_wp_login_errors( $error ) {
		$lk_auth = isset( $_REQUEST['lk_auth'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['lk_auth'] ) ) : '';
		if ( '1' !== $lk_auth ) {
			return $error;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) ) {
			return __( 'If an account matches those details, a password reset email has been sent.', 'lovekin' );
		}

		if ( in_array( $action, array( 'login', '' ), true ) ) {
			return __( 'Invalid login credentials. Please try again.', 'lovekin' );
		}

		return $error;
	}

	public static function handle_login_submission() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_post_login_redirect_url( wp_get_current_user() ) );
			exit;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_login_url(), array( 'lk_error' => 'invalid_request' ) ) );
			exit;
		}

		$nonce = isset( $_POST['lk_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_login_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lk_login' ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_login_url(), array( 'lk_error' => 'invalid_request' ) ) );
			exit;
		}

		$login_identifier = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$remember_me      = ! empty( $_POST['rememberme'] );

		if ( '' === $login_identifier || '' === $password ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_login_url(), array( 'lk_error' => 'missing_credentials' ) ) );
			exit;
		}

		$signon_login = $login_identifier;
		if ( is_email( $login_identifier ) ) {
			$user_by_email = get_user_by( 'email', $login_identifier );
			if ( $user_by_email ) {
				$signon_login = $user_by_email->user_login;
			}
		}

		$user = wp_signon(
			array(
				'user_login'    => $signon_login,
				'user_password' => $password,
				'remember'      => $remember_me,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_login_url(), array( 'lk_error' => 'invalid_login' ) ) );
			exit;
		}

		wp_safe_redirect( self::get_post_login_redirect_url( $user ) );
		exit;
	}

	public static function handle_register_submission() {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::get_post_login_redirect_url( wp_get_current_user() ) );
			exit;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'invalid_request' ) ) );
			exit;
		}

		$nonce = isset( $_POST['lk_register_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lk_register_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lk_register' ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'invalid_request' ) ) );
			exit;
		}

		$form_values = array(
			'first_name'   => isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '',
			'last_name'    => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			'username'     => isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '',
			'email'        => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone_number' => isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '',
			'occupation'   => isset( $_POST['occupation'] ) ? sanitize_text_field( wp_unslash( $_POST['occupation'] ) ) : '',
			'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'        => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'country'      => isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '',
		);
		$password         = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$password_confirm = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

		$required_fields = array(
			$form_values['first_name'],
			$form_values['last_name'],
			$form_values['username'],
			$form_values['email'],
			$password,
			$password_confirm,
			$form_values['phone_number'],
			$form_values['occupation'],
			$form_values['city'],
			$form_values['state'],
			$form_values['country'],
		);

		if ( in_array( '', $required_fields, true ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'required_fields' ) ) );
			exit;
		}

		if ( ! validate_username( $form_values['username'] ) || strlen( $form_values['username'] ) < 4 ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'invalid_username' ) ) );
			exit;
		}

		if ( ! is_email( $form_values['email'] ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'invalid_email' ) ) );
			exit;
		}

		if ( strlen( $password ) < 8 || ! preg_match( '/[A-Za-z]/', $password ) || ! preg_match( '/\d/', $password ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'weak_password' ) ) );
			exit;
		}

		if ( $password !== $password_confirm ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'password_mismatch' ) ) );
			exit;
		}

		if ( username_exists( $form_values['username'] ) || email_exists( $form_values['email'] ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'register_failed' ) ) );
			exit;
		}

		$display_name = trim( $form_values['first_name'] . ' ' . $form_values['last_name'] );
		if ( '' === $display_name ) {
			$display_name = $form_values['username'];
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $form_values['username'],
				'user_email'   => $form_values['email'],
				'user_pass'    => $password,
				'first_name'   => $form_values['first_name'],
				'last_name'    => $form_values['last_name'],
				'display_name' => $display_name,
				'role'         => self::get_default_registration_role(),
			)
		);

		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( self::add_auth_query_args( self::get_register_url(), array( 'lk_error' => 'register_failed' ) ) );
			exit;
		}

		update_user_meta( $user_id, 'lk_phone_number', $form_values['phone_number'] );
		update_user_meta( $user_id, 'lk_occupation', $form_values['occupation'] );
		update_user_meta( $user_id, 'lk_city', $form_values['city'] );
		update_user_meta( $user_id, 'lk_state', $form_values['state'] );
		update_user_meta( $user_id, 'lk_country', $form_values['country'] );
		self::ensure_membership_code( $user_id );

		wp_safe_redirect( self::add_auth_query_args( self::get_post_registration_redirect_url(), array( 'lk_notice' => 'registered' ) ) );
		exit;
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
		$tabs_panel_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'lk-dashboard-tabs-' ) : 'lk-dashboard-tabs-panel';

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
					<a class="lk-button lk-button--ghost" href="<?php echo esc_url( wp_logout_url( self::build_login_url_with_notice( 'logged_out' ) ) ); ?>"><?php esc_html_e( 'Log Out', 'lovekin' ); ?></a>
				</div>
				<button type="button" class="lk-menu-toggle" data-lk="menu-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $tabs_panel_id ); ?>">
					<span class="dashicons dashicons-menu" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Menu', 'lovekin' ); ?></span>
				</button>
			</div>

			<div id="<?php echo esc_attr( $tabs_panel_id ); ?>" class="lk-dashboard-tabs-wrap" data-lk="tabs-wrap">
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
		$attempts = LoveKin_Assessments::get_attempts_for_user( $user_id, 200 );
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
				<div class="lk-pagination-wrap" data-lk-pagination="recent-activity" data-lk-page-size="5">
					<ul class="lk-activity">
						<?php if ( empty( $attempts ) ) : ?>
							<li class="lk-empty"><?php esc_html_e( 'No assessments yet. Start with a course!', 'lovekin' ); ?></li>
						<?php else : ?>
							<?php foreach ( $attempts as $attempt ) :
								$course = get_post( $attempt->course_id );
								$band   = LoveKin_Reports::get_remark_for_score( $attempt->score );
								?>
								<li class="lk-activity-item" data-lk-page-row>
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
			$base_url = self::get_current_request_url();
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
						$latest_score = LoveKin_Assessments::get_latest_score_for_user_course( get_current_user_id(), $course->ID );
						$has_assessment = (bool) $assessment;
						$status_class   = $has_assessment ? 'lk-status-pill--assessment-open' : 'lk-status-pill--assessment-pending';
						$status_label   = $has_assessment ? __( 'Assessment Open', 'lovekin' ) : __( 'Assessment Pending', 'lovekin' );
						$status_icon    = $has_assessment ? 'dashicons-yes' : 'dashicons-update';
						?>
						<div class="lk-course-card">
							<div class="lk-course-body">
								<h4 class="lk-course-title"><?php echo esc_html( $course->post_title ); ?></h4>
								<span class="lk-status-pill <?php echo esc_attr( $status_class ); ?>">
									<span class="dashicons <?php echo esc_attr( $status_icon ); ?>"></span>
									<?php echo esc_html( $status_label ); ?>
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
		$membership_code = self::ensure_membership_code( $user_id );
		$occupation = get_user_meta( $user_id, 'lk_occupation', true );
		$position = get_user_meta( $user_id, 'lk_org_chart_position', true );
		$phone = get_user_meta( $user_id, 'lk_phone_number', true );
		$city = get_user_meta( $user_id, 'lk_city', true );
		$state = get_user_meta( $user_id, 'lk_state', true );
		$country = get_user_meta( $user_id, 'lk_country', true );
		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name  = get_user_meta( $user_id, 'last_name', true );
		$avatar_meta = get_user_meta( $user_id, 'lk_profile_picture', true );
		$avatar_url  = '';
		if ( $avatar_meta ) {
			if ( is_numeric( $avatar_meta ) ) {
				$avatar_url = wp_get_attachment_image_url( (int) $avatar_meta, 'thumbnail' );
			}
			if ( ! $avatar_url ) {
				$avatar_url = $avatar_meta;
			}
		}
		$status = 'Secondary';
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			$status = 'Admin';
		} elseif ( in_array( 'lk_primary', (array) $user->roles, true ) ) {
			$status = 'Primary';
		}
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
		$profile_error  = get_transient( 'lk_profile_error_' . $user_id );
		if ( $profile_notice ) {
			delete_transient( 'lk_profile_updated_' . $user_id );
		}
		if ( $profile_error ) {
			delete_transient( 'lk_profile_error_' . $user_id );
		}
		?>
		<div class="lk-root lk-profile" data-lk="profile">
			<?php if ( $profile_notice ) : ?>
				<div class="lk-alert lk-alert--success"><?php esc_html_e( 'Profile updated successfully.', 'lovekin' ); ?></div>
			<?php endif; ?>
			<?php if ( $profile_error ) : ?>
				<div class="lk-alert lk-alert--error"><?php echo esc_html( $profile_error ); ?></div>
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
							<span class="lk-badge <?php echo ( 'Primary' === $status || 'Admin' === $status ) ? 'lk-badge--primary' : 'lk-badge--success'; ?>"><?php echo esc_html( $status ); ?></span>
						</div>
					</div>
						<div class="lk-profile-meta">
							<p><strong><?php esc_html_e( 'Membership Code', 'lovekin' ); ?></strong><br><?php echo esc_html( $membership_code ); ?></p>
							<p><strong><?php esc_html_e( 'Email', 'lovekin' ); ?></strong><br><?php echo esc_html( $user->user_email ); ?></p>
							<p><strong><?php esc_html_e( 'Phone', 'lovekin' ); ?></strong><br><?php echo esc_html( $phone ? $phone : __( 'Not set', 'lovekin' ) ); ?></p>
							<p><strong><?php esc_html_e( 'Location', 'lovekin' ); ?></strong><br><?php echo esc_html( implode( ', ', array_filter( array( $city, $state, $country ) ) ) ?: __( 'Not set', 'lovekin' ) ); ?></p>
							<p><strong><?php esc_html_e( 'Occupation', 'lovekin' ); ?></strong><br><?php echo esc_html( $occupation ? $occupation : __( 'Not set', 'lovekin' ) ); ?></p>
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
							<div class="lk-file-selected" data-lk="file-name"></div>
							<span class="lk-help-text"><?php esc_html_e( 'PNG or JPG, up to 2MB recommended.', 'lovekin' ); ?></span>
						</div>

						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'First Name', 'lovekin' ); ?></label>
								<input type="text" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Last Name', 'lovekin' ); ?></label>
								<input type="text" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" />
							</div>
						</div>

						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'Email', 'lovekin' ); ?></label>
								<input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Phone Number', 'lovekin' ); ?></label>
								<input type="tel" name="phone_number" value="<?php echo esc_attr( $phone ); ?>" />
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

						<div class="lk-field-grid">
							<div class="lk-field">
								<label><?php esc_html_e( 'City', 'lovekin' ); ?></label>
								<input type="text" name="city" value="<?php echo esc_attr( $city ); ?>" />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'State', 'lovekin' ); ?></label>
								<input type="text" name="state" value="<?php echo esc_attr( $state ); ?>" />
							</div>
							<div class="lk-field">
								<label><?php esc_html_e( 'Country', 'lovekin' ); ?></label>
								<input type="text" name="country" value="<?php echo esc_attr( $country ); ?>" />
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
			wp_safe_redirect( self::build_login_url_with_notice( 'login_required' ) );
			exit;
		}

		if ( ! current_user_can( 'lk_edit_profile' ) ) {
			wp_safe_redirect( self::build_login_url_with_notice( 'login_required' ) );
			exit;
		}

		check_admin_referer( 'lk_update_profile' );
		$user_id = get_current_user_id();

		$profile_error = '';
		if ( ! empty( $_FILES['profile_picture']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$file = $_FILES['profile_picture'];
			$allowed = array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'webp' => 'image/webp',
			);
			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed );
			$max_size = 2 * 1024 * 1024;
			$upload_dir = wp_upload_dir();
			$upload_path = $upload_dir['path'];
			self::log_upload_debug(
				'profile_upload_check',
				array(
					'name'      => $file['name'] ?? '',
					'size'      => $file['size'] ?? 0,
					'error'     => $file['error'] ?? '',
					'check_ext' => $check['ext'] ?? '',
					'check_type'=> $check['type'] ?? '',
					'upload_path' => $upload_path,
				)
			);

			if ( ! current_user_can( 'upload_files' ) ) {
				$profile_error = __( 'You do not have permission to upload files.', 'lovekin' );
			} elseif ( ! wp_mkdir_p( $upload_path ) ) {
				$profile_error = __( 'Upload directory could not be created. Please check folder permissions.', 'lovekin' );
			} elseif ( ! wp_is_writable( $upload_path ) ) {
				$profile_error = __( 'Upload directory is not writable. Please check folder permissions.', 'lovekin' );
			} elseif ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
				$profile_error = __( 'Unsupported profile image type. Please upload a JPG, PNG, or WebP image.', 'lovekin' );
			} elseif ( $file['size'] > $max_size ) {
				$profile_error = __( 'Profile image exceeds the 2MB limit.', 'lovekin' );
			} else {
				$attachment_id = media_handle_upload( 'profile_picture', 0 );
				if ( is_wp_error( $attachment_id ) ) {
					self::log_upload_debug(
						'profile_upload_error',
						array(
							'message' => $attachment_id->get_error_message(),
						)
					);
					$profile_error = $attachment_id->get_error_message();
				} else {
					update_user_meta( $user_id, 'lk_profile_picture', $attachment_id );
				}
			}
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$occupation      = isset( $_POST['occupation'] ) ? sanitize_text_field( wp_unslash( $_POST['occupation'] ) ) : '';
		$position        = isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : '';
		$phone_number    = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
		$city            = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$state           = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$country         = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

		update_user_meta( $user_id, 'first_name', $first_name );
		update_user_meta( $user_id, 'last_name', $last_name );
		update_user_meta( $user_id, 'lk_occupation', $occupation );
		update_user_meta( $user_id, 'lk_org_chart_position', $position );
		update_user_meta( $user_id, 'lk_phone_number', $phone_number );
		update_user_meta( $user_id, 'lk_city', $city );
		update_user_meta( $user_id, 'lk_state', $state );
		update_user_meta( $user_id, 'lk_country', $country );

		if ( $first_name || $last_name ) {
			$display_name = trim( $first_name . ' ' . $last_name );
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
				)
			);
		}

		if ( $profile_error ) {
			set_transient( 'lk_profile_error_' . $user_id, $profile_error, 30 );
		} else {
			set_transient( 'lk_profile_updated_' . $user_id, 1, 30 );
		}
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
		$return_url = '';
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
		if ( $view_id && $view_id !== $user_id ) {
			$return_url = self::get_dashboard_url();
		}

		return LoveKin_Reports::render_report_view( $view_id, current_user_can( 'lk_view_all_reports' ) || current_user_can( 'lk_edit_remarks' ), $return_url );
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
		$membership_code = self::ensure_membership_code( $user->ID );
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
				<div class="lk-pagination-wrap" data-lk-pagination="funding-history" data-lk-page-size="5">
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
									<tr data-lk-page-row>
										<td>&#8358;<?php echo esc_html( number_format_i18n( $request->amount, 2 ) ); ?></td>
										<td><span class="lk-badge lk-badge--<?php echo esc_attr( $request->status ); ?>"><?php echo esc_html( ucfirst( $request->status ) ); ?></span></td>
										<td><?php echo esc_html( mysql2date( 'M j, Y', $request->created_at ) ); ?></td>
										<td><?php echo esc_html( $request->admin_notes ); ?></td>
										<td>
											<?php
											$supporting_url = LoveKin_Funding::get_supporting_file_url( $request->supporting_file );
											?>
											<?php if ( $supporting_url ) : ?>
												<a class="lk-button lk-button--ghost" href="<?php echo esc_url( $supporting_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
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
		$total_doc_bytes = 0;
		foreach ( $docs as $doc ) {
			$total_doc_bytes += (int) $doc->file_size;
			if ( ! empty( $doc->category ) ) {
				$doc_categories[ $doc->category ] = ucfirst( $doc->category );
			}
		}
		$doc_count = count( $docs );
		$total_doc_mb = $total_doc_bytes / ( 1024 * 1024 );
		ob_start();
		?>
		<div class="lk-root lk-card lk-documents-panel">
			<div class="lk-card-header lk-documents-header">
				<div>
					<h3><?php esc_html_e( 'Document Library', 'lovekin' ); ?></h3>
					<p class="lk-meta lk-documents-subtitle"><?php esc_html_e( 'Shared resources available to all members.', 'lovekin' ); ?></p>
				</div>
				<div class="lk-documents-summary">
					<span class="lk-badge lk-badge--primary">
						<?php
						printf(
							esc_html__( '%d files', 'lovekin' ),
							(int) $doc_count
						);
						?>
					</span>
					<span class="lk-meta">
						<?php
						printf(
							esc_html__( '%s MB total', 'lovekin' ),
							esc_html( number_format_i18n( $total_doc_mb, 1 ) )
						);
						?>
					</span>
				</div>
			</div>
			<div class="lk-toolbar lk-documents-toolbar">
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
			<div class="lk-document-grid lk-document-grid--refined">
				<?php if ( empty( $docs ) ) : ?>
					<div class="lk-empty-state">
						<h4><?php esc_html_e( 'No documents yet', 'lovekin' ); ?></h4>
						<p><?php esc_html_e( 'Shared documents uploaded by administrators will appear here.', 'lovekin' ); ?></p>
					</div>
				<?php else : ?>
					<?php foreach ( $docs as $doc ) : ?>
						<?php
						$title_for_search = strtolower( trim( $doc->title . ' ' . $doc->description . ' ' . $doc->category ) );
						?>
						<article class="lk-document-card lk-document-card--refined" data-category="<?php echo esc_attr( $doc->category ); ?>" data-title="<?php echo esc_attr( $title_for_search ); ?>">
							<div class="lk-document-card-head">
								<h4><?php echo esc_html( $doc->title ); ?></h4>
								<span class="lk-badge lk-badge--primary lk-doc-type"><?php echo esc_html( strtoupper( (string) $doc->file_type ) ); ?></span>
							</div>
							<?php if ( ! empty( $doc->description ) ) : ?>
								<p class="lk-document-desc"><?php echo esc_html( $doc->description ); ?></p>
							<?php else : ?>
								<p class="lk-document-desc"><?php esc_html_e( 'No description provided.', 'lovekin' ); ?></p>
							<?php endif; ?>
							<div class="lk-document-meta-row">
								<span class="lk-meta"><?php echo esc_html( ucfirst( $doc->category ) ); ?></span>
								<span class="lk-meta"><?php echo esc_html( number_format_i18n( $doc->file_size / 1024, 1 ) ); ?> KB</span>
								<span class="lk-meta"><?php echo esc_html( mysql2date( 'M j, Y', $doc->created_at ) ); ?></span>
							</div>
							<div class="lk-document-actions">
								<a class="lk-button lk-button--ghost" href="<?php echo esc_url( LoveKin_Documents::get_download_url( $doc->id ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
							</div>
						</article>
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

		$files    = LoveKin_Archive::get_user_files( get_current_user_id() );
		$settings = get_option( 'lovekin_upload_settings', array() );
		$quota_mb = isset( $settings['archive_quota_mb'] ) ? max( 1, (int) $settings['archive_quota_mb'] ) : 100;
		$max_mb   = isset( $settings['max_file_size_mb'] ) ? max( 1, (int) $settings['max_file_size_mb'] ) : 10;
		$allowed  = isset( $settings['allowed_types'] ) && is_array( $settings['allowed_types'] ) ? array_filter( array_map( 'strtolower', $settings['allowed_types'] ) ) : array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		if ( empty( $allowed ) ) {
			$allowed = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
		}
		$total_bytes = 0;
		$folders = array();
		foreach ( $files as $file ) {
			$total_bytes += (int) $file->file_size;
			if ( ! empty( $file->folder ) ) {
				$folders[ $file->folder ] = $file->folder;
			}
		}
		$used_mb        = $total_bytes / ( 1024 * 1024 );
		$remaining_mb   = max( 0, $quota_mb - $used_mb );
		$usage_percent = $quota_mb > 0 ? min( 100, ( $used_mb / $quota_mb ) * 100 ) : 0;
		$archive_notice = isset( $_GET['lk_archive'] ) ? sanitize_text_field( wp_unslash( $_GET['lk_archive'] ) ) : '';
		$notice_map     = array(
			'uploaded' => array(
				'class'   => 'lk-alert--success',
				'message' => __( 'File uploaded successfully.', 'lovekin' ),
			),
			'missing'  => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'Please choose a file before uploading.', 'lovekin' ),
			),
			'type'     => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'Unsupported file type. Please upload an allowed extension.', 'lovekin' ),
			),
			'size'     => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'File exceeds the configured upload size limit.', 'lovekin' ),
			),
			'quota'    => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'Archive quota exceeded. Delete older files or ask an admin to increase the quota.', 'lovekin' ),
			),
			'upload'   => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'Unable to upload file. Please try again.', 'lovekin' ),
			),
			'db'       => array(
				'class'   => 'lk-alert--error',
				'message' => __( 'File uploaded but could not be saved in the archive list. Please try again.', 'lovekin' ),
			),
		);
		$allowed_list   = strtoupper( implode( ', ', $allowed ) );
		$accept_attr    = '.' . implode( ',.', array_map( 'sanitize_key', $allowed ) );
		ob_start();
		?>
		<div class="lk-root lk-card lk-archive-card">
			<div class="lk-card-header lk-archive-header">
				<div>
					<h3><?php esc_html_e( 'My Archive', 'lovekin' ); ?></h3>
					<p class="lk-meta"><?php esc_html_e( 'Private workspace for your personal uploads.', 'lovekin' ); ?></p>
				</div>
				<div class="lk-archive-kpis">
					<span class="lk-badge lk-badge--primary">
						<?php
						printf(
							esc_html__( '%d files', 'lovekin' ),
							count( $files )
						);
						?>
					</span>
					<span class="lk-meta">
						<?php
						printf(
							esc_html__( '%1$s MB of %2$d MB used', 'lovekin' ),
							esc_html( number_format_i18n( $used_mb, 1 ) ),
							(int) $quota_mb
						);
						?>
					</span>
				</div>
			</div>
			<?php if ( $archive_notice && isset( $notice_map[ $archive_notice ] ) ) : ?>
				<div class="lk-alert <?php echo esc_attr( $notice_map[ $archive_notice ]['class'] ); ?>" data-lk-auto-hide="5000">
					<?php echo esc_html( $notice_map[ $archive_notice ]['message'] ); ?>
				</div>
			<?php endif; ?>

			<div class="lk-progress">
				<div class="lk-progress-bar" style="width: <?php echo esc_attr( $usage_percent ); ?>%;"></div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="lk-upload lk-archive-upload">
				<input type="hidden" name="action" value="lk_upload_archive" />
				<?php wp_nonce_field( 'lk_upload_archive' ); ?>
				<div class="lk-archive-upload-grid">
					<div class="lk-field">
						<label for="lk-archive-folder"><?php esc_html_e( 'Folder (optional)', 'lovekin' ); ?></label>
						<input id="lk-archive-folder" type="text" name="folder" placeholder="<?php esc_attr_e( 'e.g. Sermons, School, Certificates', 'lovekin' ); ?>" />
					</div>
					<div class="lk-field">
						<label><?php esc_html_e( 'Choose file', 'lovekin' ); ?></label>
						<label class="lk-file-picker" data-lk="file-picker">
							<input type="file" name="archive_file" accept="<?php echo esc_attr( $accept_attr ); ?>" required />
							<span class="lk-file-picker-button"><?php esc_html_e( 'Browse', 'lovekin' ); ?></span>
							<span class="lk-file-picker-name" data-lk="file-name"><?php esc_html_e( 'No file selected', 'lovekin' ); ?></span>
						</label>
						<span class="lk-help-text">
							<?php
							printf(
								esc_html__( 'Allowed: %1$s. Max file size: %2$d MB. Remaining space: %3$s MB.', 'lovekin' ),
								esc_html( $allowed_list ),
								(int) $max_mb,
								esc_html( number_format_i18n( $remaining_mb, 1 ) )
							);
							?>
						</span>
					</div>
				</div>
				<div class="lk-archive-upload-actions">
					<button type="submit" class="lk-button"><?php esc_html_e( 'Upload to Archive', 'lovekin' ); ?></button>
				</div>
			</form>

			<div class="lk-toolbar lk-archive-toolbar">
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

				<table class="lk-table lk-table--archive">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Folder', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Uploaded', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Size', 'lovekin' ); ?></th>
							<th><?php esc_html_e( 'Action', 'lovekin' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $files ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No files uploaded yet.', 'lovekin' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $files as $file ) : ?>
								<tr data-folder="<?php echo esc_attr( $file->folder ); ?>" data-name="<?php echo esc_attr( strtolower( $file->file_name ) ); ?>">
									<td data-label="<?php esc_attr_e( 'File', 'lovekin' ); ?>"><?php echo esc_html( $file->file_name ); ?></td>
									<td data-label="<?php esc_attr_e( 'Folder', 'lovekin' ); ?>"><?php echo esc_html( $file->folder ? $file->folder : __( 'Unfiled', 'lovekin' ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Uploaded', 'lovekin' ); ?>"><?php echo esc_html( mysql2date( 'M j, Y', $file->created_at ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Size', 'lovekin' ); ?>"><?php echo esc_html( number_format_i18n( $file->file_size / 1024, 1 ) ); ?> KB</td>
									<td data-label="<?php esc_attr_e( 'Action', 'lovekin' ); ?>">
										<div class="lk-table-actions">
											<a class="lk-button lk-button--ghost" href="<?php echo esc_url( add_query_arg( array( 'lk_archive_download' => $file->id ), home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Download', 'lovekin' ); ?></a>
											<a class="lk-button lk-button--danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lk_delete_archive&file_id=' . $file->id ), 'lk_delete_archive' ) ); ?>"><?php esc_html_e( 'Delete', 'lovekin' ); ?></a>
										</div>
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

		$avatar_meta = get_user_meta( $user_id, 'lk_profile_picture', true );
		$avatar_url  = '';
		if ( $avatar_meta ) {
			if ( is_numeric( $avatar_meta ) ) {
				$avatar_url = wp_get_attachment_image_url( (int) $avatar_meta, 'thumbnail' );
			}
			if ( ! $avatar_url ) {
				$avatar_url = $avatar_meta;
			}
		}
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

	public static function render_login_form( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'lovekin_login' );
		unset( $atts );

		$notice       = self::get_auth_notice_message();
		$error        = self::get_auth_error_message( 'login' );
		$register_url = self::get_register_url();
		$remember_me  = false;

		if ( is_user_logged_in() ) {
			$dashboard_url = self::get_post_login_redirect_url( wp_get_current_user() );
			return sprintf(
				'<div class="lk-root lk-card lk-auth-inline"><p>%1$s</p><a class="lk-button lk-button--primary" href="%2$s">%3$s</a></div>',
				esc_html__( 'You are already logged in.', 'lovekin' ),
				esc_url( $dashboard_url ),
				esc_html__( 'Go to Dashboard', 'lovekin' )
			);
		}

		ob_start();
		?>
		<div class="lk-root lk-auth-shell" data-lk="auth-shell">
			<div class="lk-card lk-auth-card">
				<div class="lk-auth-header">
					<p class="lk-eyebrow"><?php esc_html_e( 'LoveKin Portal', 'lovekin' ); ?></p>
					<h3><?php esc_html_e( 'Welcome Back', 'lovekin' ); ?></h3>
					<p class="lk-auth-subtitle"><?php esc_html_e( 'Sign in to access your dashboard, profile, and learning resources.', 'lovekin' ); ?></p>
				</div>
				<?php if ( $notice ) : ?>
					<div class="lk-alert lk-alert--info" data-lk-auto-hide="5000"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>
				<?php if ( $error ) : ?>
					<div class="lk-alert lk-alert--error" data-lk-auto-hide="6000"><?php echo esc_html( $error ); ?></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lk-form lk-auth-form" novalidate>
					<input type="hidden" name="action" value="lk_login" />
					<div class="lk-field">
						<label for="lk-login-username"><?php esc_html_e( 'Username or Email', 'lovekin' ); ?></label>
						<input id="lk-login-username" type="text" name="username" autocomplete="username" required />
					</div>
					<div class="lk-field">
						<label for="lk-login-password"><?php esc_html_e( 'Password', 'lovekin' ); ?></label>
						<input id="lk-login-password" type="password" name="password" autocomplete="current-password" required />
					</div>
					<div class="lk-auth-row">
						<label class="lk-auth-checkbox">
							<input type="checkbox" name="rememberme" value="1" <?php checked( $remember_me ); ?> />
							<span><?php esc_html_e( 'Remember me', 'lovekin' ); ?></span>
						</label>
						<a class="lk-auth-link" href="<?php echo esc_url( add_query_arg( 'lk_auth', '1', wp_lostpassword_url( self::get_login_url() ) ) ); ?>"><?php esc_html_e( 'Forgot password?', 'lovekin' ); ?></a>
					</div>
					<?php wp_nonce_field( 'lk_login', 'lk_login_nonce' ); ?>
					<button type="submit" class="lk-button lk-button--primary lk-auth-submit"><?php esc_html_e( 'Sign In', 'lovekin' ); ?></button>
				</form>
				<?php if ( $register_url ) : ?>
					<p class="lk-auth-switch">
						<?php esc_html_e( 'Need an account?', 'lovekin' ); ?>
						<a href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Register', 'lovekin' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_register_form( $atts = array() ) {
		$atts = shortcode_atts( array(), $atts, 'lovekin_register' );
		unset( $atts );

		$notice      = self::get_auth_notice_message();
		$error       = self::get_auth_error_message( 'register' );
		$form_values = array(
			'first_name'   => '',
			'last_name'    => '',
			'username'     => '',
			'email'        => '',
			'phone_number' => '',
			'occupation'   => '',
			'city'         => '',
			'state'        => '',
			'country'      => '',
		);

		if ( is_user_logged_in() ) {
			$dashboard_url = self::get_post_login_redirect_url( wp_get_current_user() );
			return sprintf(
				'<div class="lk-root lk-card lk-auth-inline"><p>%1$s</p><a class="lk-button lk-button--primary" href="%2$s">%3$s</a></div>',
				esc_html__( 'You are already logged in.', 'lovekin' ),
				esc_url( $dashboard_url ),
				esc_html__( 'Go to Dashboard', 'lovekin' )
			);
		}

		$login_url = self::get_login_url();
		ob_start();
		?>
		<div class="lk-root lk-auth-shell" data-lk="auth-shell">
			<div class="lk-card lk-auth-card lk-auth-card--register">
				<div class="lk-auth-header">
					<p class="lk-eyebrow"><?php esc_html_e( 'LoveKin Portal', 'lovekin' ); ?></p>
					<h3><?php esc_html_e( 'Create Account', 'lovekin' ); ?></h3>
					<p class="lk-auth-subtitle"><?php esc_html_e( 'Register as a secondary member to get started.', 'lovekin' ); ?></p>
				</div>
				<?php if ( $notice ) : ?>
					<div class="lk-alert lk-alert--info" data-lk-auto-hide="5000"><?php echo esc_html( $notice ); ?></div>
				<?php endif; ?>
				<?php if ( $error ) : ?>
					<div class="lk-alert lk-alert--error" data-lk-auto-hide="6000"><?php echo esc_html( $error ); ?></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lk-form lk-auth-form" novalidate>
					<input type="hidden" name="action" value="lk_register" />
					<div class="lk-field-grid">
						<div class="lk-field">
							<label for="lk-register-first-name"><?php esc_html_e( 'First Name', 'lovekin' ); ?></label>
							<input id="lk-register-first-name" type="text" name="first_name" value="<?php echo esc_attr( $form_values['first_name'] ); ?>" autocomplete="given-name" required />
						</div>
						<div class="lk-field">
							<label for="lk-register-last-name"><?php esc_html_e( 'Last Name', 'lovekin' ); ?></label>
							<input id="lk-register-last-name" type="text" name="last_name" value="<?php echo esc_attr( $form_values['last_name'] ); ?>" autocomplete="family-name" required />
						</div>
					</div>
					<div class="lk-field-grid">
						<div class="lk-field">
							<label for="lk-register-username"><?php esc_html_e( 'Username', 'lovekin' ); ?></label>
							<input id="lk-register-username" type="text" name="username" value="<?php echo esc_attr( $form_values['username'] ); ?>" autocomplete="username" required />
						</div>
						<div class="lk-field">
							<label for="lk-register-email"><?php esc_html_e( 'Email', 'lovekin' ); ?></label>
							<input id="lk-register-email" type="email" name="email" value="<?php echo esc_attr( $form_values['email'] ); ?>" autocomplete="email" required />
						</div>
					</div>
					<div class="lk-field-grid">
						<div class="lk-field">
							<label for="lk-register-phone"><?php esc_html_e( 'Phone Number', 'lovekin' ); ?></label>
							<input id="lk-register-phone" type="tel" name="phone_number" value="<?php echo esc_attr( $form_values['phone_number'] ); ?>" autocomplete="tel" required />
						</div>
						<div class="lk-field">
							<label for="lk-register-occupation"><?php esc_html_e( 'Occupation', 'lovekin' ); ?></label>
							<input id="lk-register-occupation" type="text" name="occupation" value="<?php echo esc_attr( $form_values['occupation'] ); ?>" required />
						</div>
					</div>
					<div class="lk-field-grid">
						<div class="lk-field">
							<label for="lk-register-city"><?php esc_html_e( 'City', 'lovekin' ); ?></label>
							<input id="lk-register-city" type="text" name="city" value="<?php echo esc_attr( $form_values['city'] ); ?>" autocomplete="address-level2" required />
						</div>
						<div class="lk-field">
							<label for="lk-register-state"><?php esc_html_e( 'State', 'lovekin' ); ?></label>
							<input id="lk-register-state" type="text" name="state" value="<?php echo esc_attr( $form_values['state'] ); ?>" autocomplete="address-level1" required />
						</div>
						<div class="lk-field">
							<label for="lk-register-country"><?php esc_html_e( 'Country', 'lovekin' ); ?></label>
							<input id="lk-register-country" type="text" name="country" value="<?php echo esc_attr( $form_values['country'] ); ?>" autocomplete="country-name" required />
						</div>
					</div>
					<div class="lk-field-grid">
						<div class="lk-field">
							<label for="lk-register-password"><?php esc_html_e( 'Password', 'lovekin' ); ?></label>
							<input id="lk-register-password" type="password" name="password" autocomplete="new-password" required />
							<span class="lk-help-text"><?php esc_html_e( 'Minimum 8 characters, include letters and numbers.', 'lovekin' ); ?></span>
						</div>
						<div class="lk-field">
							<label for="lk-register-confirm-password"><?php esc_html_e( 'Confirm Password', 'lovekin' ); ?></label>
							<input id="lk-register-confirm-password" type="password" name="confirm_password" autocomplete="new-password" required />
						</div>
					</div>
					<?php wp_nonce_field( 'lk_register', 'lk_register_nonce' ); ?>
					<button type="submit" class="lk-button lk-button--primary lk-auth-submit"><?php esc_html_e( 'Create Account', 'lovekin' ); ?></button>
				</form>
				<?php if ( $login_url ) : ?>
					<p class="lk-auth-switch">
						<?php esc_html_e( 'Already have an account?', 'lovekin' ); ?>
						<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'lovekin' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_dashboard_url() {
		$configured_url = self::get_configured_auth_page_url( 'dashboard_page_id' );
		if ( $configured_url ) {
			return $configured_url;
		}

		$fallback_url = self::get_page_url_by_shortcode( 'lovekin_dashboard' );
		return $fallback_url ? $fallback_url : home_url( '/' );
	}

	private static function get_login_url() {
		$configured_url = self::get_configured_auth_page_url( 'login_page_id' );
		if ( $configured_url ) {
			return $configured_url;
		}

		$fallback_url = self::get_page_url_by_shortcode( 'lovekin_login' );
		return $fallback_url ? $fallback_url : wp_login_url();
	}

	private static function get_register_url() {
		$configured_url = self::get_configured_auth_page_url( 'register_page_id' );
		if ( $configured_url ) {
			return $configured_url;
		}

		$fallback_url = self::get_page_url_by_shortcode( 'lovekin_register' );
		return $fallback_url ? $fallback_url : wp_registration_url();
	}

	private static function get_configured_auth_page_id( $setting_key ) {
		$settings = get_option( 'lovekin_auth_settings', array() );
		if ( ! is_array( $settings ) ) {
			return 0;
		}

		$page_id = isset( $settings[ $setting_key ] ) ? absint( $settings[ $setting_key ] ) : 0;
		if ( ! $page_id ) {
			return 0;
		}

		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return 0;
		}

		return $page_id;
	}

	private static function get_configured_auth_page_url( $setting_key ) {
		$page_id = self::get_configured_auth_page_id( $setting_key );
		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return $url ? $url : '';
	}

	private static function get_page_url_by_shortcode( $shortcode ) {
		static $cache = array();
		$shortcode = sanitize_key( $shortcode );
		if ( isset( $cache[ $shortcode ] ) ) {
			return $cache[ $shortcode ];
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 250,
			)
		);

		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, $shortcode ) ) {
				$url = get_permalink( $page->ID );
				$cache[ $shortcode ] = $url ? $url : '';
				return $cache[ $shortcode ];
			}
		}

		$cache[ $shortcode ] = '';
		return '';
	}

	private static function get_safe_redirect_url( $url, $fallback = '' ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		if ( '' === $url ) {
			return $fallback;
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$home_url_parts = wp_parse_url( home_url( '/' ) );
			$home_path      = isset( $home_url_parts['path'] ) ? rtrim( (string) $home_url_parts['path'], '/' ) : '';
			if ( $home_path && 0 === strpos( $url, $home_path . '/' ) ) {
				$scheme = isset( $home_url_parts['scheme'] ) ? $home_url_parts['scheme'] : ( is_ssl() ? 'https' : 'http' );
				$host   = $home_url_parts['host'] ?? '';
				$port   = isset( $home_url_parts['port'] ) ? ':' . (int) $home_url_parts['port'] : '';
				if ( $host ) {
					$url = $scheme . '://' . $host . $port . $url;
				} else {
					$url = home_url( $url );
				}
			} else {
				$url = home_url( $url );
			}
		}

		$safe_url = wp_validate_redirect( $url, '' );
		if ( ! $safe_url ) {
			return $fallback;
		}

		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$safe_host = wp_parse_url( $safe_url, PHP_URL_HOST );
		if ( $home_host && $safe_host && strtolower( $home_host ) !== strtolower( $safe_host ) ) {
			return $fallback;
		}

		return $safe_url;
	}

	private static function get_post_login_redirect_url( $user = null ) {
		$default = self::get_dashboard_url();
		$filtered = apply_filters( 'lovekin_login_redirect_url', $default, $user );
		return self::get_safe_redirect_url( $filtered, $default );
	}

	private static function get_post_registration_redirect_url( $user = null ) {
		$default = self::get_login_url();
		$filtered = apply_filters( 'lovekin_registration_redirect_url', $default, $user );
		return self::get_safe_redirect_url( $filtered, $default );
	}

	private static function urls_share_path( $left, $right ) {
		$left  = self::get_safe_redirect_url( $left, '' );
		$right = self::get_safe_redirect_url( $right, '' );
		if ( ! $left || ! $right ) {
			return false;
		}

		$left_parts  = wp_parse_url( $left );
		$right_parts = wp_parse_url( $right );
		if ( ! is_array( $left_parts ) || ! is_array( $right_parts ) ) {
			return false;
		}

		$left_host  = isset( $left_parts['host'] ) ? strtolower( (string) $left_parts['host'] ) : '';
		$right_host = isset( $right_parts['host'] ) ? strtolower( (string) $right_parts['host'] ) : '';
		if ( $left_host !== $right_host ) {
			return false;
		}

		$left_path  = isset( $left_parts['path'] ) ? untrailingslashit( (string) $left_parts['path'] ) : '';
		$right_path = isset( $right_parts['path'] ) ? untrailingslashit( (string) $right_parts['path'] ) : '';
		return $left_path === $right_path;
	}

	private static function get_default_registration_role() {
		$default_role = apply_filters( 'lovekin_registration_default_role', 'lk_secondary' );
		if ( ! is_string( $default_role ) || ! get_role( $default_role ) ) {
			$default_role = get_role( 'lk_secondary' ) ? 'lk_secondary' : 'subscriber';
		}
		return $default_role;
	}

	private static function get_current_request_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$request_uri = '/' . ltrim( (string) $request_uri, '/' );
		$scheme = is_ssl() ? 'https' : 'http';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! $host ) {
			return home_url( '/' );
		}

		return self::get_safe_redirect_url( $scheme . '://' . $host . $request_uri, home_url( '/' ) );
	}

	private static function build_login_url_with_notice( $notice = '' ) {
		$login_url = self::get_login_url();
		if ( $notice ) {
			$login_url = add_query_arg( 'lk_notice', sanitize_key( $notice ), $login_url );
		}
		return $login_url;
	}

	private static function add_auth_query_args( $url, $args = array() ) {
		$clean_args = array();
		foreach ( (array) $args as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			$clean_args[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
		}
		if ( empty( $clean_args ) ) {
			return $url;
		}
		return add_query_arg( $clean_args, $url );
	}

	private static function get_auth_notice_message() {
		$notice = isset( $_GET['lk_notice'] ) ? sanitize_key( wp_unslash( $_GET['lk_notice'] ) ) : '';
		switch ( $notice ) {
			case 'login_required':
				return __( 'Please log in to access that page.', 'lovekin' );
			case 'logged_out':
				return __( 'You have been logged out successfully.', 'lovekin' );
			case 'registered':
				return __( 'Registration successful. Please sign in with your new account.', 'lovekin' );
			default:
				return '';
		}
	}

	private static function get_auth_error_message( $context = '' ) {
		$error = isset( $_GET['lk_error'] ) ? sanitize_key( wp_unslash( $_GET['lk_error'] ) ) : '';
		if ( ! $error ) {
			return '';
		}

		if ( 'login' === $context ) {
			switch ( $error ) {
				case 'missing_credentials':
					return __( 'Please enter your username/email and password.', 'lovekin' );
				case 'invalid_request':
					return __( 'Invalid request. Please try again.', 'lovekin' );
				case 'invalid_login':
				default:
					return __( 'Invalid login credentials. Please try again or reset your password.', 'lovekin' );
			}
		}

		if ( 'register' === $context ) {
			switch ( $error ) {
				case 'required_fields':
					return __( 'Please complete all required fields.', 'lovekin' );
				case 'invalid_username':
					return __( 'Please enter a valid username (at least 4 characters).', 'lovekin' );
				case 'invalid_email':
					return __( 'Please enter a valid email address.', 'lovekin' );
				case 'weak_password':
					return __( 'Password must be at least 8 characters and include letters and numbers.', 'lovekin' );
				case 'password_mismatch':
					return __( 'Passwords do not match.', 'lovekin' );
				case 'invalid_request':
					return __( 'Invalid request. Please try again.', 'lovekin' );
				case 'register_failed':
				default:
					return __( 'We could not create your account with those details. Please review your information and try again.', 'lovekin' );
			}
		}

		return '';
	}

	private static function ensure_membership_code( $user_id ) {
		$code = get_user_meta( $user_id, 'lk_membership_code', true );
		$invalid = ! $code || preg_match( '/[^A-Za-z0-9]/', $code ) || stripos( $code, 'LK' ) !== 0;
		if ( $invalid ) {
			$code = sprintf( 'LK%04d%03d', absint( $user_id ), wp_rand( 100, 999 ) );
			update_user_meta( $user_id, 'lk_membership_code', $code );
		}
		return $code;
	}

	private static function log_upload_debug( $context, $data = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		$upload_dir = wp_upload_dir();
		$log_file = trailingslashit( $upload_dir['basedir'] ) . 'lovekin-debug.log';
		$payload = array(
			'time'    => current_time( 'mysql' ),
			'context' => $context,
			'data'    => $data,
		);
		$line = wp_json_encode( $payload ) . PHP_EOL;
		@file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	}

	private static function render_login_prompt() {
		$login_url = self::build_login_url_with_notice( 'login_required' );
		return sprintf(
			'<div class="lk-root lk-card lk-auth-inline"><p>%1$s</p><a class="lk-button lk-button--primary" href="%2$s">%3$s</a></div>',
			esc_html__( 'Please log in to access this feature.', 'lovekin' ),
			esc_url( $login_url ),
			esc_html__( 'Go to Login', 'lovekin' )
		);
	}
}
