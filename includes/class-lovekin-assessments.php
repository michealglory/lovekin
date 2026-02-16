<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Assessments {
	public static function init() {
		add_action( 'admin_post_lk_submit_assessment', array( __CLASS__, 'handle_submission' ) );
		add_action( 'admin_post_nopriv_lk_submit_assessment', array( __CLASS__, 'block_guest_submission' ) );
	}

	public static function block_guest_submission() {
		wp_safe_redirect( wp_login_url() );
		exit;
	}

	public static function get_assessment_for_course( $course_id ) {
		$assessments = get_posts(
			array(
				'post_type'      => 'lk_assessment',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_lk_course_id',
				'meta_value'     => absint( $course_id ),
			)
		);

		return $assessments ? $assessments[0] : null;
	}

	public static function handle_submission() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( ! current_user_can( 'lk_take_assessments' ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		check_admin_referer( 'lk_submit_assessment' );

		$user_id       = get_current_user_id();
		$assessment_id = isset( $_POST['assessment_id'] ) ? absint( $_POST['assessment_id'] ) : 0;
		$course_id     = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		$answers       = isset( $_POST['answers'] ) ? (array) wp_unslash( $_POST['answers'] ) : array();

		if ( ! $assessment_id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$assessment_post = get_post( $assessment_id );
		if ( ! $assessment_post || 'lk_assessment' !== $assessment_post->post_type ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$linked_course = (int) get_post_meta( $assessment_id, '_lk_course_id', true );
		if ( $linked_course ) {
			$course_id = $linked_course;
		}

		$questions = get_post_meta( $assessment_id, '_lk_questions', true );
		if ( ! is_array( $questions ) ) {
			$questions = array();
		}

		$total_questions = count( $questions );
		$correct_count   = 0;
		$sanitized_answers = array();

		foreach ( $questions as $index => $question ) {
			$selected = isset( $answers[ $index ] ) ? sanitize_text_field( $answers[ $index ] ) : '';
			$correct  = isset( $question['correct'] ) ? $question['correct'] : '';
			$sanitized_answers[] = array(
				'question' => sanitize_text_field( $question['question'] ?? '' ),
				'selected' => $selected,
				'correct'  => $correct,
			);
			if ( $selected && $correct && $selected === $correct ) {
				$correct_count++;
			}
		}

		$score = 0;
		if ( $total_questions > 0 ) {
			$score = round( ( $correct_count / $total_questions ) * 100, 2 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';
		$wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'assessment_id' => $assessment_id,
				'score'         => $score,
				'answers_json'  => wp_json_encode( $sanitized_answers ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%f', '%s', '%s' )
		);

		$attempt_id = $wpdb->insert_id;
		if ( $attempt_id ) {
			update_user_meta( $user_id, 'lk_last_attempt_' . $assessment_id, $attempt_id );
			update_user_meta( $user_id, 'lk_last_attempt', $attempt_id );
		}

		$redirect_url = get_permalink( $assessment_id );
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function get_attempt( $attempt_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$attempt_id
			)
		);
	}

	public static function get_attempts_for_user( $user_id, $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
				$user_id,
				$limit
			)
		);
	}

	public static function get_latest_score_for_user_course( $user_id, $course_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT score FROM {$table} WHERE user_id = %d AND course_id = %d ORDER BY created_at DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	public static function get_latest_score_for_user( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lk_attempts';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT score FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);
	}
}
