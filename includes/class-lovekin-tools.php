<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Tools {
	public static function init() {
		add_action( 'admin_post_lk_generate_demo_data', array( __CLASS__, 'handle_generate_demo' ) );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$notice = get_transient( 'lk_demo_notice' );
		if ( $notice ) {
			delete_transient( 'lk_demo_notice' );
		}
		$demo_users = get_users(
			array(
				'meta_key'   => 'lk_demo_user',
				'meta_value' => 1,
				'fields'     => array( 'ID', 'user_login', 'user_email', 'display_name' ),
			)
		);
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'LoveKin Tools', 'lovekin' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<div class="lk-admin-card">
				<h2><?php esc_html_e( 'Generate Demo Data', 'lovekin' ); ?></h2>
				<p><?php esc_html_e( 'Create demo users, courses, assessments, attempts, relationships, and funding requests for testing.', 'lovekin' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lk_generate_demo_data" />
					<?php wp_nonce_field( 'lk_generate_demo_data' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Demo Data', 'lovekin' ); ?></button>
				</form>
			</div>

			<?php if ( ! empty( $demo_users ) ) : ?>
				<div class="lk-admin-card">
					<h2><?php esc_html_e( 'Demo Users', 'lovekin' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Username', 'lovekin' ); ?></th>
								<th><?php esc_html_e( 'Email', 'lovekin' ); ?></th>
								<th><?php esc_html_e( 'Role', 'lovekin' ); ?></th>
								<th><?php esc_html_e( 'Password', 'lovekin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $demo_users as $demo_user ) : ?>
								<tr>
									<td><?php echo esc_html( $demo_user->user_login ); ?></td>
									<td><?php echo esc_html( $demo_user->user_email ); ?></td>
									<td><?php echo esc_html( implode( ', ', get_userdata( $demo_user->ID )->roles ) ); ?></td>
									<td><?php echo esc_html( get_user_meta( $demo_user->ID, 'lk_demo_password', true ) ?: __( 'Use reset password', 'lovekin' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_generate_demo() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_generate_demo_data' );

		$counts = self::generate_demo_data();
		$summary = sprintf(
			'Demo data created: %d users, %d courses, %d assessments, %d attempts, %d relationships, %d funding requests.',
			$counts['users'],
			$counts['courses'],
			$counts['assessments'],
			$counts['attempts'],
			$counts['relationships'],
			$counts['funding']
		);
		set_transient( 'lk_demo_notice', $summary, 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-tools' ) );
		exit;
	}

	private static function generate_demo_data() {
		$counts = array(
			'users' => 0,
			'courses' => 0,
			'assessments' => 0,
			'attempts' => 0,
			'relationships' => 0,
			'funding' => 0,
		);

		$users = array(
			array(
				'user_login' => 'lk_demo_primary_01',
				'user_email' => 'lk_primary_01@example.com',
				'display_name' => 'Primary Member One',
				'role' => 'lk_primary',
			),
			array(
				'user_login' => 'lk_demo_primary_02',
				'user_email' => 'lk_primary_02@example.com',
				'display_name' => 'Primary Member Two',
				'role' => 'lk_primary',
			),
			array(
				'user_login' => 'lk_demo_secondary_01',
				'user_email' => 'lk_secondary_01@example.com',
				'display_name' => 'Secondary Member One',
				'role' => 'lk_secondary',
			),
			array(
				'user_login' => 'lk_demo_secondary_02',
				'user_email' => 'lk_secondary_02@example.com',
				'display_name' => 'Secondary Member Two',
				'role' => 'lk_secondary',
			),
			array(
				'user_login' => 'lk_demo_secondary_03',
				'user_email' => 'lk_secondary_03@example.com',
				'display_name' => 'Secondary Member Three',
				'role' => 'lk_secondary',
			),
		);

		$user_ids = array();
		foreach ( $users as $user ) {
			$user_id = self::get_or_create_user( $user );
			if ( $user_id ) {
				$counts['users']++;
				$user_ids[] = $user_id;
			}
		}

		$course_defs = array(
			array(
				'title'   => 'Community Impact',
				'content' => 'Measure and grow impact within the community with practical tools and reflection prompts.',
			),
			array(
				'title'   => 'Leadership & Service',
				'content' => 'Develop leadership habits for service and growth through real-world examples.',
			),
			array(
				'title'   => 'Foundations of Mentorship',
				'content' => 'Build strong mentorship foundations with community values and guided practice.',
			),
		);

		$course_ids = array();
		foreach ( $course_defs as $course_def ) {
			$existing = get_page_by_title( $course_def['title'], OBJECT, 'lk_course' );
			if ( $existing ) {
				$course_ids[] = $existing->ID;
				continue;
			}
			$course_id = wp_insert_post(
				array(
					'post_type'    => 'lk_course',
					'post_status'  => 'publish',
					'post_title'   => $course_def['title'],
					'post_content' => $course_def['content'],
				)
			);
			if ( $course_id ) {
				$counts['courses']++;
				$course_ids[] = $course_id;
			}
		}

		$assessment_ids = array();
		foreach ( $course_ids as $course_id ) {
			$existing_assessments = get_posts(
				array(
					'post_type'  => 'lk_assessment',
					'meta_key'   => '_lk_course_id',
					'meta_value' => $course_id,
					'fields'     => 'ids',
				)
			);
			if ( ! empty( $existing_assessments ) ) {
				$assessment_ids[ $course_id ] = $existing_assessments[0];
				continue;
			}

			$assessment_id = wp_insert_post(
				array(
					'post_type'   => 'lk_assessment',
					'post_status' => 'publish',
					'post_title'  => 'Assessment for ' . get_the_title( $course_id ),
					'post_content'=> '',
				)
			);
			if ( $assessment_id ) {
				update_post_meta( $assessment_id, '_lk_course_id', $course_id );
				update_post_meta(
					$assessment_id,
					'_lk_questions',
					array(
						array(
							'question' => 'What is one key takeaway from this course?',
							'options'  => array( 'Reflect daily', 'Skip practice', 'Avoid feedback', 'Ignore community' ),
							'correct'  => 'A',
						),
						array(
							'question' => 'Which action best supports growth?',
							'options'  => array( 'Stay isolated', 'Seek feedback', 'Avoid learning', 'Skip sessions' ),
							'correct'  => 'B',
						),
					)
				);
				$counts['assessments']++;
				$assessment_ids[ $course_id ] = $assessment_id;
			}
		}

		global $wpdb;
		$rel_table = $wpdb->prefix . 'lk_relationships';
		$attempts_table = $wpdb->prefix . 'lk_attempts';
		$funding_table  = $wpdb->prefix . 'lk_funding_requests';

		$primary_id = $user_ids[0] ?? 0;
		$secondary_ids = array_slice( $user_ids, 2 );
		foreach ( $secondary_ids as $secondary_id ) {
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$rel_table} WHERE primary_user_id = %d AND secondary_user_id = %d",
					$primary_id,
					$secondary_id
				)
			);
			if ( ! $exists ) {
				$wpdb->insert(
					$rel_table,
					array(
						'primary_user_id'   => $primary_id,
						'secondary_user_id' => $secondary_id,
						'created_at'        => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
				$counts['relationships']++;
			}
		}

		foreach ( $secondary_ids as $secondary_id ) {
			foreach ( $course_ids as $course_id ) {
				$assessment_id = $assessment_ids[ $course_id ] ?? 0;
				if ( ! $assessment_id ) {
					continue;
				}
				$score = wp_rand( 45, 95 );
				$wpdb->insert(
					$attempts_table,
					array(
						'user_id'       => $secondary_id,
						'course_id'     => $course_id,
						'assessment_id' => $assessment_id,
						'score'         => $score,
						'answers_json'  => wp_json_encode( array( '0' => 'A', '1' => 'B' ) ),
						'remark'        => '',
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%f', '%s', '%s', '%s' )
				);
				$counts['attempts']++;
			}
		}

		foreach ( $secondary_ids as $secondary_id ) {
			$wpdb->insert(
				$funding_table,
				array(
					'user_id'         => $secondary_id,
					'format'          => 'FAC',
					'purpose'         => 'Support community project materials.',
					'amount'          => wp_rand( 20000, 150000 ),
					'account_details' => wp_json_encode(
						array(
							'name'   => 'Demo Member',
							'number' => '0123456789',
							'bank'   => 'LoveKin Bank',
						)
					),
					'membership_code' => get_user_meta( $secondary_id, 'lk_membership_code', true ),
					'status'          => 'pending',
					'created_at'      => current_time( 'mysql' ),
					'updated_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			$counts['funding']++;
		}

		return $counts;
	}

	private static function get_or_create_user( $user ) {
		$existing = get_user_by( 'login', $user['user_login'] );
		if ( ! $existing ) {
			$existing = get_user_by( 'email', $user['user_email'] );
		}

		if ( $existing ) {
			$user_id = $existing->ID;
			if ( ! in_array( $user['role'], (array) $existing->roles, true ) ) {
				wp_update_user(
					array(
						'ID'   => $user_id,
						'role' => $user['role'],
					)
				);
			}
			self::ensure_membership_code( $user_id );
			update_user_meta( $user_id, 'lk_demo_user', 1 );
			return $user_id;
		}

		$password = wp_generate_password( 12, false );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $user['user_login'],
				'user_email'   => $user['user_email'],
				'display_name' => $user['display_name'],
				'user_pass'    => $password,
				'role'         => $user['role'],
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		self::ensure_membership_code( $user_id );
		update_user_meta( $user_id, 'lk_demo_user', 1 );
		update_user_meta( $user_id, 'lk_demo_password', $password );
		return $user_id;
	}

	private static function ensure_membership_code( $user_id ) {
		$code = get_user_meta( $user_id, 'lk_membership_code', true );
		$invalid = ! $code || preg_match( '/[^A-Za-z0-9]/', $code ) || stripos( $code, 'LK' ) !== 0;
		if ( $invalid ) {
			$code = sprintf( 'LK%04d%03d', absint( $user_id ), wp_rand( 100, 999 ) );
			update_user_meta( $user_id, 'lk_membership_code', $code );
		}
	}
}
