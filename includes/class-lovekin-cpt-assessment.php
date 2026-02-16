<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_CPT_Assessment {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'add_meta_boxes_lk_assessment', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_lk_assessment', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_meta' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Assessments', 'lovekin' ),
			'singular_name'      => __( 'Assessment', 'lovekin' ),
			'add_new'            => __( 'Add New', 'lovekin' ),
			'add_new_item'       => __( 'Add New Assessment', 'lovekin' ),
			'edit_item'          => __( 'Edit Assessment', 'lovekin' ),
			'new_item'           => __( 'New Assessment', 'lovekin' ),
			'view_item'          => __( 'View Assessment', 'lovekin' ),
			'search_items'       => __( 'Search Assessments', 'lovekin' ),
			'not_found'          => __( 'No assessments found', 'lovekin' ),
			'not_found_in_trash' => __( 'No assessments found in trash', 'lovekin' ),
			'menu_name'          => __( 'Assessments', 'lovekin' ),
		);

		register_post_type(
			'lk_assessment',
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-clipboard',
				'has_archive'        => true,
				'supports'           => array( 'title', 'editor' ),
				'capability_type'    => array( 'lk_assessment', 'lk_assessments' ),
				'map_meta_cap'       => true,
				'show_in_menu'       => false,
				'capabilities'       => array(
					'edit_post'          => 'edit_lk_assessment',
					'read_post'          => 'read_lk_assessment',
					'delete_post'        => 'delete_lk_assessment',
					'edit_posts'         => 'edit_lk_assessments',
					'edit_others_posts'  => 'edit_others_lk_assessments',
					'edit_published_posts' => 'edit_published_lk_assessments',
					'edit_private_posts' => 'edit_private_lk_assessments',
					'publish_posts'      => 'publish_lk_assessments',
					'read_private_posts' => 'read_private_lk_assessments',
				),
				'register_meta_box_cb' => array( __CLASS__, 'meta_boxes' ),
				'rewrite'            => array( 'slug' => 'lovekin-assessment' ),
			)
		);
	}

	public static function register_meta() {
		register_post_meta(
			'lk_assessment',
			'_lk_course_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => function() {
					return current_user_can( 'lk_manage_assessments' ) || current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'lk_assessment',
			'_lk_questions',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_questions' ),
				'auth_callback'     => function() {
					return current_user_can( 'lk_manage_assessments' ) || current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'lk_assessment_course',
			__( 'Linked Course', 'lovekin' ),
			array( __CLASS__, 'render_course_meta' ),
			'lk_assessment',
			'side',
			'high'
		);

		add_meta_box(
			'lk_assessment_questions',
			__( 'Assessment Questions', 'lovekin' ),
			array( __CLASS__, 'render_questions_meta' ),
			'lk_assessment',
			'normal',
			'high'
		);
	}

	public static function render_course_meta( $post ) {
		wp_nonce_field( 'lk_assessment_meta', 'lk_assessment_meta_nonce' );
		$selected = (int) get_post_meta( $post->ID, '_lk_course_id', true );
		$courses  = get_posts(
			array(
				'post_type'      => 'lk_course',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
			)
		);
		?>
		<p class="lk-admin-helper"><?php esc_html_e( 'Each course can have one assessment. Select the linked course below.', 'lovekin' ); ?></p>
		<select name="lk_course_id" class="widefat" data-lk="assessment-course">
			<option value="0"><?php esc_html_e( 'Select a course', 'lovekin' ); ?></option>
			<?php foreach ( $courses as $course ) : ?>
				<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $selected, $course->ID ); ?>>
					<?php echo esc_html( $course->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function render_questions_meta( $post ) {
		$questions = get_post_meta( $post->ID, '_lk_questions', true );
		if ( ! is_array( $questions ) ) {
			$questions = array();
		}
		?>
		<div class="lk-question-builder" data-lk="question-builder">
			<div class="lk-question-list" data-lk="question-list">
				<?php foreach ( $questions as $index => $question ) :
					$question_text = isset( $question['question'] ) ? $question['question'] : '';
					$options       = isset( $question['options'] ) ? (array) $question['options'] : array( '', '', '', '' );
					$correct       = isset( $question['correct'] ) ? $question['correct'] : 'A';
					?>
					<div class="lk-question-card" data-lk="question-item">
						<div class="lk-question-header">
							<strong><?php echo esc_html( sprintf( __( 'Question %d', 'lovekin' ), $index + 1 ) ); ?></strong>
							<button type="button" class="button button-link-delete" data-lk="remove-question"><?php esc_html_e( 'Remove', 'lovekin' ); ?></button>
						</div>
						<textarea name="lk_questions[<?php echo esc_attr( $index ); ?>][question]" class="widefat" rows="3" placeholder="<?php esc_attr_e( 'Question text', 'lovekin' ); ?>"><?php echo esc_textarea( $question_text ); ?></textarea>
						<div class="lk-option-grid">
							<?php foreach ( array( 'A', 'B', 'C', 'D' ) as $opt_index => $label ) : ?>
								<div class="lk-option-item">
									<label><?php echo esc_html( $label ); ?></label>
									<input type="text" class="widefat" name="lk_questions[<?php echo esc_attr( $index ); ?>][options][<?php echo esc_attr( $opt_index ); ?>]" value="<?php echo esc_attr( $options[ $opt_index ] ?? '' ); ?>" />
								</div>
							<?php endforeach; ?>
						</div>
						<div class="lk-correct-select">
							<label><?php esc_html_e( 'Correct Answer', 'lovekin' ); ?></label>
							<select name="lk_questions[<?php echo esc_attr( $index ); ?>][correct]">
								<?php foreach ( array( 'A', 'B', 'C', 'D' ) as $label ) : ?>
									<option value="<?php echo esc_attr( $label ); ?>" <?php selected( $correct, $label ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" data-lk="add-question"><?php esc_html_e( 'Add Question', 'lovekin' ); ?></button>
		</div>
		<?php
	}

	public static function sanitize_questions( $value ) {
		$sanitized = array();
		if ( ! is_array( $value ) ) {
			return $sanitized;
		}

		foreach ( $value as $question ) {
			$question_text = isset( $question['question'] ) ? sanitize_textarea_field( $question['question'] ) : '';
			$options       = array();
			foreach ( array( 0, 1, 2, 3 ) as $index ) {
				$options[] = isset( $question['options'][ $index ] ) ? sanitize_text_field( $question['options'][ $index ] ) : '';
			}
			$correct = isset( $question['correct'] ) ? sanitize_text_field( $question['correct'] ) : 'A';
			if ( '' !== $question_text ) {
				$sanitized[] = array(
					'question' => $question_text,
					'options'  => $options,
					'correct'  => in_array( $correct, array( 'A', 'B', 'C', 'D' ), true ) ? $correct : 'A',
				);
			}
		}

		return $sanitized;
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['lk_assessment_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lk_assessment_meta_nonce'] ) ), 'lk_assessment_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$course_id = isset( $_POST['lk_course_id'] ) ? absint( $_POST['lk_course_id'] ) : 0;
		update_post_meta( $post_id, '_lk_course_id', $course_id );

		$questions = isset( $_POST['lk_questions'] ) ? (array) wp_unslash( $_POST['lk_questions'] ) : array();
		$questions = self::sanitize_questions( $questions );
		update_post_meta( $post_id, '_lk_questions', $questions );

		if ( 'publish' === $post->post_status && 0 === $course_id ) {
			remove_action( 'save_post_lk_assessment', array( __CLASS__, 'save_meta' ), 10 );
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			add_action( 'save_post_lk_assessment', array( __CLASS__, 'save_meta' ), 10, 2 );
			set_transient( 'lk_assessment_notice', __( 'Assessment was saved as draft because no course was linked.', 'lovekin' ), 30 );
		}
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = '';
		if ( $screen && ! empty( $screen->post_type ) ) {
			$post_type = $screen->post_type;
		} elseif ( isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) );
		} elseif ( isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
			$post_type = $post_id ? get_post_type( $post_id ) : '';
		}

		if ( 'lk_assessment' !== $post_type ) {
			return;
		}

		wp_enqueue_style( 'lovekin-admin', LOVEKIN_PLUGIN_URL . 'assets/css/lovekin-admin.css', array(), LOVEKIN_VERSION );
		wp_enqueue_script( 'lovekin-admin', LOVEKIN_PLUGIN_URL . 'assets/js/lovekin-admin.js', array( 'jquery' ), LOVEKIN_VERSION, true );
	}

	public static function admin_notice() {
		$notice = get_transient( 'lk_assessment_notice' );
		if ( $notice ) {
			delete_transient( 'lk_assessment_notice' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $notice ) );
		}
	}
}
