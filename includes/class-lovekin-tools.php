<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Tools {
	private const NOTICE_TRANSIENT = 'lk_demo_notice';

	public static function init() {
		add_action( 'admin_post_lk_generate_demo_data', array( __CLASS__, 'handle_generate_demo' ) );
		add_action( 'admin_post_lk_erase_demo_data', array( __CLASS__, 'handle_erase_demo' ) );
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		$notice     = self::consume_notice();
		$demo_users = self::get_demo_users_for_display();
		?>
		<div class="wrap lk-admin-page">
			<h1><?php esc_html_e( 'LoveKin Tools', 'lovekin' ); ?></h1>

			<?php if ( $notice ) : ?>
				<?php $notice_class = 'error' === $notice['type'] ? 'notice notice-error' : 'notice notice-success'; ?>
				<div class="<?php echo esc_attr( $notice_class ); ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<div class="lk-admin-card">
				<h2><?php esc_html_e( 'Generate Demo Data', 'lovekin' ); ?></h2>
				<p><?php esc_html_e( 'Reset and recreate demo records. Generates exactly 6 items each for users, courses, assessments, attempts, relationships, and funding requests.', 'lovekin' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lk_generate_demo_data" />
					<?php wp_nonce_field( 'lk_generate_demo_data' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Demo Data', 'lovekin' ); ?></button>
				</form>
			</div>

			<div class="lk-admin-card">
				<h2><?php esc_html_e( 'Erase Demo Content', 'lovekin' ); ?></h2>
				<p><?php esc_html_e( 'Deletes only demo-tagged content and linked demo rows. Real content is preserved.', 'lovekin' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Erase all demo content now? This cannot be undone.', 'lovekin' ) ); ?>');">
					<input type="hidden" name="action" value="lk_erase_demo_data" />
					<?php wp_nonce_field( 'lk_erase_demo_data' ); ?>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Erase Demo Content', 'lovekin' ); ?></button>
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
									<td><?php echo esc_html( implode( ', ', (array) $demo_user->roles ) ); ?></td>
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

		$erased = self::erase_demo_data();
		$counts = self::generate_demo_data();

		$expected = self::expected_counts();
		$mismatch = false;
		foreach ( $expected as $key => $expected_count ) {
			if ( (int) ( $counts[ $key ] ?? 0 ) !== (int) $expected_count ) {
				$mismatch = true;
				break;
			}
		}

		$created_summary = self::format_counts( $counts );
		$erased_summary  = self::format_counts( $erased );

		if ( $mismatch ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: 1: erased summary, 2: created summary */
					__( 'Demo reset ran, but some counts did not reach 6. Erased: %1$s. Created: %2$s.', 'lovekin' ),
					$erased_summary,
					$created_summary
				)
			);
		} else {
			self::set_notice(
				'success',
				sprintf(
					/* translators: 1: erased summary, 2: created summary */
					__( 'Demo data reset and recreated. Erased: %1$s. Created: %2$s.', 'lovekin' ),
					$erased_summary,
					$created_summary
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-tools' ) );
		exit;
	}

	public static function handle_erase_demo() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'lovekin' ) );
		}

		check_admin_referer( 'lk_erase_demo_data' );

		$erased = self::erase_demo_data();
		$total  = array_sum( array_map( 'intval', $erased ) );
		if ( $total <= 0 ) {
			self::set_notice( 'success', __( 'No demo content found to erase.', 'lovekin' ) );
		} else {
			self::set_notice(
				'success',
				sprintf(
					/* translators: %s: erased counts */
					__( 'Demo content erased: %s.', 'lovekin' ),
					self::format_counts( $erased )
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=lovekin-tools' ) );
		exit;
	}

	private static function generate_demo_data() {
		$counts = array(
			'users'         => 0,
			'courses'       => 0,
			'assessments'   => 0,
			'attempts'      => 0,
			'relationships' => 0,
			'funding'       => 0,
		);

		$user_map = self::create_demo_users();
		$counts['users'] = count( $user_map['all'] );

		$course_ids            = self::create_demo_courses();
		$counts['courses']     = count( $course_ids );

		$assessment_ids         = self::create_demo_assessments( $course_ids );
		$counts['assessments']  = count( $assessment_ids );

		$counts['relationships'] = self::create_demo_relationships( $user_map['primary'], $user_map['secondary'] );
		$counts['attempts']      = self::create_demo_attempts( $user_map['secondary'], $course_ids, $assessment_ids );
		$counts['funding']       = self::create_demo_funding( $user_map['secondary'] );

		return $counts;
	}

	private static function erase_demo_data() {
		$counts = array(
			'users'         => 0,
			'courses'       => 0,
			'assessments'   => 0,
			'attempts'      => 0,
			'relationships' => 0,
			'funding'       => 0,
		);

		$demo_user_ids       = self::get_demo_user_ids();
		$demo_course_ids     = self::get_demo_course_ids();
		$demo_assessment_ids = self::get_demo_assessment_ids( $demo_course_ids );

		global $wpdb;
		$attempts_table = $wpdb->prefix . 'lk_attempts';
		$rel_table      = $wpdb->prefix . 'lk_relationships';
		$funding_table  = $wpdb->prefix . 'lk_funding_requests';

		$counts['attempts'] += self::delete_rows_by_in( $attempts_table, 'user_id', $demo_user_ids );
		$counts['attempts'] += self::delete_rows_by_in( $attempts_table, 'course_id', $demo_course_ids );
		$counts['attempts'] += self::delete_rows_by_in( $attempts_table, 'assessment_id', $demo_assessment_ids );

		$counts['relationships'] += self::delete_rows_by_in( $rel_table, 'primary_user_id', $demo_user_ids );
		$counts['relationships'] += self::delete_rows_by_in( $rel_table, 'secondary_user_id', $demo_user_ids );

		$counts['funding'] += self::delete_rows_by_in( $funding_table, 'user_id', $demo_user_ids );

		foreach ( $demo_assessment_ids as $assessment_id ) {
			if ( wp_delete_post( $assessment_id, true ) ) {
				$counts['assessments']++;
			}
		}

		foreach ( $demo_course_ids as $course_id ) {
			if ( wp_delete_post( $course_id, true ) ) {
				$counts['courses']++;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$current_user_id = get_current_user_id();
		foreach ( $demo_user_ids as $user_id ) {
			if ( $user_id === $current_user_id ) {
				continue;
			}
			if ( wp_delete_user( $user_id ) ) {
				$counts['users']++;
			}
		}

		return $counts;
	}

	private static function create_demo_users() {
		$definitions = array(
			array(
				'user_login'   => 'lk_demo_primary_01',
				'user_email'   => 'lk_demo_primary_01@example.com',
				'display_name' => 'Demo Primary 01',
				'role'         => 'lk_primary',
				'password'     => 'LoveKinDemo!101',
			),
			array(
				'user_login'   => 'lk_demo_primary_02',
				'user_email'   => 'lk_demo_primary_02@example.com',
				'display_name' => 'Demo Primary 02',
				'role'         => 'lk_primary',
				'password'     => 'LoveKinDemo!102',
			),
			array(
				'user_login'   => 'lk_demo_primary_03',
				'user_email'   => 'lk_demo_primary_03@example.com',
				'display_name' => 'Demo Primary 03',
				'role'         => 'lk_primary',
				'password'     => 'LoveKinDemo!103',
			),
			array(
				'user_login'   => 'lk_demo_secondary_01',
				'user_email'   => 'lk_demo_secondary_01@example.com',
				'display_name' => 'Demo Secondary 01',
				'role'         => 'lk_secondary',
				'password'     => 'LoveKinDemo!201',
			),
			array(
				'user_login'   => 'lk_demo_secondary_02',
				'user_email'   => 'lk_demo_secondary_02@example.com',
				'display_name' => 'Demo Secondary 02',
				'role'         => 'lk_secondary',
				'password'     => 'LoveKinDemo!202',
			),
			array(
				'user_login'   => 'lk_demo_secondary_03',
				'user_email'   => 'lk_demo_secondary_03@example.com',
				'display_name' => 'Demo Secondary 03',
				'role'         => 'lk_secondary',
				'password'     => 'LoveKinDemo!203',
			),
		);

		$result = array(
			'primary'   => array(),
			'secondary' => array(),
			'all'       => array(),
		);

		foreach ( $definitions as $definition ) {
			$user_id = self::get_or_create_demo_user( $definition );
			if ( ! $user_id ) {
				continue;
			}

			$result['all'][] = $user_id;
			if ( 'lk_primary' === $definition['role'] ) {
				$result['primary'][] = $user_id;
			} else {
				$result['secondary'][] = $user_id;
			}
		}

		$result['all']       = self::sanitize_id_list( $result['all'] );
		$result['primary']   = self::sanitize_id_list( $result['primary'] );
		$result['secondary'] = self::sanitize_id_list( $result['secondary'] );

		return $result;
	}

	private static function get_or_create_demo_user( $definition ) {
		$existing = get_user_by( 'login', $definition['user_login'] );
		if ( ! $existing ) {
			$existing = get_user_by( 'email', $definition['user_email'] );
		}

		$user_id = 0;
		if ( $existing ) {
			$user_id = (int) $existing->ID;
			if ( ! self::is_demo_user( $user_id ) ) {
				return 0;
			}
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $definition['display_name'],
					'role'         => $definition['role'],
				)
			);
			wp_set_password( $definition['password'], $user_id );
		} else {
			$created = wp_insert_user(
				array(
					'user_login'   => $definition['user_login'],
					'user_email'   => $definition['user_email'],
					'display_name' => $definition['display_name'],
					'user_pass'    => $definition['password'],
					'role'         => $definition['role'],
				)
			);
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$user_id = (int) $created;
		}

		if ( $user_id <= 0 ) {
			return 0;
		}

		self::ensure_membership_code( $user_id );
		update_user_meta( $user_id, 'lk_demo_user', 1 );
		update_user_meta( $user_id, 'lk_demo_password', $definition['password'] );

		return $user_id;
	}

	private static function create_demo_courses() {
		$course_defs = array(
			array(
				'title'   => 'Demo Course 01: Leadership Basics',
				'content' => 'Placeholder lesson content for demo course 01.',
			),
			array(
				'title'   => 'Demo Course 02: Community Service',
				'content' => 'Placeholder lesson content for demo course 02.',
			),
			array(
				'title'   => 'Demo Course 03: Mentorship Fundamentals',
				'content' => 'Placeholder lesson content for demo course 03.',
			),
			array(
				'title'   => 'Demo Course 04: Conflict Resolution',
				'content' => 'Placeholder lesson content for demo course 04.',
			),
			array(
				'title'   => 'Demo Course 05: Financial Stewardship',
				'content' => 'Placeholder lesson content for demo course 05.',
			),
			array(
				'title'   => 'Demo Course 06: Project Planning',
				'content' => 'Placeholder lesson content for demo course 06.',
			),
		);

		$course_ids = array();
		foreach ( $course_defs as $index => $course_def ) {
			$demo_key = sprintf( 'lk_demo_course_%02d', $index + 1 );
			$course_id = self::get_or_create_demo_post(
				'lk_course',
				$demo_key,
				array(
					'post_title'   => $course_def['title'],
					'post_content' => $course_def['content'],
				)
			);
			if ( $course_id ) {
				$course_ids[] = $course_id;
			}
		}

		return self::sanitize_id_list( $course_ids );
	}

	private static function create_demo_assessments( $course_ids ) {
		$assessment_ids = array();
		foreach ( $course_ids as $index => $course_id ) {
			$assessment_number = $index + 1;
			$demo_key          = sprintf( 'lk_demo_assessment_%02d', $assessment_number );
			$assessment_id     = self::get_or_create_demo_post(
				'lk_assessment',
				$demo_key,
				array(
					'post_title'   => sprintf( 'Demo Assessment %02d', $assessment_number ),
					'post_content' => '',
				)
			);
			if ( ! $assessment_id ) {
				continue;
			}

			update_post_meta( $assessment_id, '_lk_course_id', $course_id );
			update_post_meta( $assessment_id, '_lk_questions', self::get_demo_questions( $assessment_number ) );
			$assessment_ids[] = $assessment_id;
		}

		return self::sanitize_id_list( $assessment_ids );
	}

	private static function create_demo_relationships( $primary_ids, $secondary_ids ) {
		if ( count( $primary_ids ) < 3 || count( $secondary_ids ) < 3 ) {
			return 0;
		}

		$pairs = array(
			array( 0, 0 ),
			array( 0, 1 ),
			array( 1, 1 ),
			array( 1, 2 ),
			array( 2, 0 ),
			array( 2, 2 ),
		);

		global $wpdb;
		$table = $wpdb->prefix . 'lk_relationships';
		$count = 0;

		foreach ( $pairs as $pair ) {
			$primary_index   = (int) $pair[0];
			$secondary_index = (int) $pair[1];
			if ( ! isset( $primary_ids[ $primary_index ], $secondary_ids[ $secondary_index ] ) ) {
				continue;
			}

			$inserted = $wpdb->insert(
				$table,
				array(
					'primary_user_id'   => $primary_ids[ $primary_index ],
					'secondary_user_id' => $secondary_ids[ $secondary_index ],
					'created_at'        => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s' )
			);
			if ( false !== $inserted ) {
				$count++;
			}
		}

		return $count;
	}

	private static function create_demo_attempts( $secondary_ids, $course_ids, $assessment_ids ) {
		if ( empty( $secondary_ids ) ) {
			return 0;
		}

		$max = min( 6, count( $course_ids ), count( $assessment_ids ) );
		if ( $max <= 0 ) {
			return 0;
		}

		$scores = array( 78, 84, 69, 91, 74, 87 );

		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';
		$count = 0;

		for ( $i = 0; $i < $max; $i++ ) {
			$secondary_id = $secondary_ids[ $i % count( $secondary_ids ) ];
			$inserted     = $wpdb->insert(
				$table,
				array(
					'user_id'       => $secondary_id,
					'course_id'     => $course_ids[ $i ],
					'assessment_id' => $assessment_ids[ $i ],
					'score'         => $scores[ $i ],
					'answers_json'  => wp_json_encode( array( '0' => 'A', '1' => 'B' ) ),
					'remark'        => '',
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%f', '%s', '%s', '%s' )
			);

			if ( false !== $inserted ) {
				$count++;
			}
		}

		return $count;
	}

	private static function create_demo_funding( $secondary_ids ) {
		if ( empty( $secondary_ids ) ) {
			return 0;
		}

		$amounts = array( 35000, 50000, 42000, 61000, 47000, 55000 );
		$formats = array( 'FAC', 'FAD', 'TMT', 'FAC', 'FAD', 'TMT' );

		global $wpdb;
		$table = $wpdb->prefix . 'lk_funding_requests';
		$count = 0;

		for ( $i = 0; $i < 6; $i++ ) {
			$secondary_id = $secondary_ids[ $i % count( $secondary_ids ) ];
			$inserted     = $wpdb->insert(
				$table,
				array(
					'user_id'         => $secondary_id,
					'format'          => $formats[ $i ],
					'purpose'         => sprintf( 'Demo funding request %02d placeholder purpose.', $i + 1 ),
					'amount'          => $amounts[ $i ],
					'account_details' => wp_json_encode(
						array(
							'name'   => sprintf( 'Demo Member %02d', $i + 1 ),
							'number' => sprintf( '000000%04d', $i + 1 ),
							'bank'   => 'LoveKin Demo Bank',
						)
					),
					'membership_code' => get_user_meta( $secondary_id, 'lk_membership_code', true ),
					'status'          => 'pending',
					'created_at'      => current_time( 'mysql' ),
					'updated_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false !== $inserted ) {
				$count++;
			}
		}

		return $count;
	}

	private static function get_demo_questions( $assessment_number ) {
		return array(
			array(
				'question' => sprintf( 'Demo Question %02d-A: What is a helpful next step?', $assessment_number ),
				'options'  => array( 'Seek feedback', 'Ignore guidance', 'Avoid practice', 'Skip planning' ),
				'correct'  => 'A',
			),
			array(
				'question' => sprintf( 'Demo Question %02d-B: Which behavior shows progress?', $assessment_number ),
				'options'  => array( 'Avoid reflection', 'Delay action', 'Apply lessons', 'Work alone always' ),
				'correct'  => 'C',
			),
		);
	}

	private static function get_or_create_demo_post( $post_type, $demo_key, $post_args ) {
		$existing_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'lk_demo_key',
						'value' => $demo_key,
					),
				),
			)
		);

		$post_id   = 0;
		$post_data = array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'post_title'  => (string) ( $post_args['post_title'] ?? '' ),
			'post_content'=> (string) ( $post_args['post_content'] ?? '' ),
		);

		if ( ! empty( $existing_ids ) ) {
			$post_data['ID'] = (int) $existing_ids[0];
			$updated          = wp_update_post( $post_data, true );
			if ( is_wp_error( $updated ) ) {
				return 0;
			}
			$post_id = (int) $updated;
		} else {
			$created = wp_insert_post( $post_data, true );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			$post_id = (int) $created;
		}

		if ( $post_id <= 0 ) {
			return 0;
		}

		update_post_meta( $post_id, 'lk_demo_content', 1 );
		update_post_meta( $post_id, 'lk_demo_key', $demo_key );

		return $post_id;
	}

	private static function get_demo_users_for_display() {
		$user_ids = self::get_demo_user_ids();
		if ( empty( $user_ids ) ) {
			return array();
		}

		return get_users(
			array(
				'include' => $user_ids,
				'orderby' => 'user_login',
				'order'   => 'ASC',
			)
		);
	}

	private static function get_demo_user_ids() {
		$ids = get_users(
			array(
				'meta_key'   => 'lk_demo_user',
				'meta_value' => 1,
				'fields'     => 'ids',
			)
		);

		global $wpdb;
		$legacy_pattern = $wpdb->esc_like( 'lk_demo_' ) . '%';
		$legacy_ids     = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} WHERE user_login LIKE %s",
				$legacy_pattern
			)
		);

		return self::sanitize_id_list( array_merge( (array) $ids, (array) $legacy_ids ) );
	}

	private static function get_demo_course_ids() {
		$tagged_ids = get_posts(
			array(
				'post_type'      => 'lk_course',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'lk_demo_content',
						'value' => 1,
					),
				),
			)
		);

		$legacy_titles = array(
			'Community Impact',
			'Leadership & Service',
			'Foundations of Mentorship',
		);

		global $wpdb;
		$legacy_ids = array();
		if ( ! empty( $legacy_titles ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $legacy_titles ), '%s' ) );
			$sql          = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lk_course' AND post_status <> 'trash' AND post_title IN ($placeholders)",
				$legacy_titles
			);
			$legacy_ids = $wpdb->get_col( $sql );
		}

		return self::sanitize_id_list( array_merge( (array) $tagged_ids, (array) $legacy_ids ) );
	}

	private static function get_demo_assessment_ids( $course_ids = array() ) {
		$tagged_ids = get_posts(
			array(
				'post_type'      => 'lk_assessment',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'lk_demo_content',
						'value' => 1,
					),
				),
			)
		);

		$legacy_ids = array();
		$course_ids = self::sanitize_id_list( $course_ids );
		if ( ! empty( $course_ids ) ) {
			global $wpdb;
			$placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
			$sql          = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'lk_assessment'
					AND p.post_status <> 'trash'
					AND p.post_title LIKE %s
					AND pm.meta_key = '_lk_course_id'
					AND CAST(pm.meta_value AS UNSIGNED) IN ($placeholders)",
				array_merge( array( 'Assessment for %' ), $course_ids )
			);
			$legacy_ids = $wpdb->get_col( $sql );
		}

		return self::sanitize_id_list( array_merge( (array) $tagged_ids, (array) $legacy_ids ) );
	}

	private static function delete_rows_by_in( $table, $column, $ids ) {
		$ids = self::sanitize_id_list( $ids );
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;
		$column       = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $column );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} IN ($placeholders)", $ids );
		$deleted      = $wpdb->query( $sql );

		return false === $deleted ? 0 : (int) $deleted;
	}

	private static function expected_counts() {
		return array(
			'users'         => 6,
			'courses'       => 6,
			'assessments'   => 6,
			'attempts'      => 6,
			'relationships' => 6,
			'funding'       => 6,
		);
	}

	private static function format_counts( $counts ) {
		return sprintf(
			/* translators: 1: users, 2: courses, 3: assessments, 4: attempts, 5: relationships, 6: funding */
			__( '%1$d users, %2$d courses, %3$d assessments, %4$d attempts, %5$d relationships, %6$d funding requests', 'lovekin' ),
			(int) ( $counts['users'] ?? 0 ),
			(int) ( $counts['courses'] ?? 0 ),
			(int) ( $counts['assessments'] ?? 0 ),
			(int) ( $counts['attempts'] ?? 0 ),
			(int) ( $counts['relationships'] ?? 0 ),
			(int) ( $counts['funding'] ?? 0 )
		);
	}

	private static function set_notice( $type, $message ) {
		$type = 'error' === $type ? 'error' : 'success';
		set_transient(
			self::NOTICE_TRANSIENT,
			array(
				'type'    => $type,
				'message' => (string) $message,
			),
			60
		);
	}

	private static function consume_notice() {
		$notice = get_transient( self::NOTICE_TRANSIENT );
		if ( false === $notice ) {
			return null;
		}
		delete_transient( self::NOTICE_TRANSIENT );

		if ( is_string( $notice ) ) {
			return array(
				'type'    => 'success',
				'message' => $notice,
			);
		}

		if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
			return array(
				'type'    => 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success',
				'message' => (string) $notice['message'],
			);
		}

		return null;
	}

	private static function is_demo_user( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( (int) get_user_meta( $user_id, 'lk_demo_user', true ) === 1 ) {
			return true;
		}
		return 0 === strpos( (string) $user->user_login, 'lk_demo_' );
	}

	private static function sanitize_id_list( $ids ) {
		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		$ids = array_values( array_unique( $ids ) );
		return $ids;
	}

	private static function ensure_membership_code( $user_id ) {
		$code    = get_user_meta( $user_id, 'lk_membership_code', true );
		$invalid = ! $code || preg_match( '/[^A-Za-z0-9]/', $code ) || stripos( $code, 'LK' ) !== 0;
		if ( $invalid ) {
			$code = sprintf( 'LK%04d%03d', absint( $user_id ), wp_rand( 100, 999 ) );
			update_user_meta( $user_id, 'lk_membership_code', $code );
		}
	}
}
