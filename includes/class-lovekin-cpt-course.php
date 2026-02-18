<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_CPT_Course {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_lk_course', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_meta' ) );
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Courses', 'lovekin' ),
			'singular_name'      => __( 'Course', 'lovekin' ),
			'add_new'            => __( 'Add New', 'lovekin' ),
			'add_new_item'       => __( 'Add New Course', 'lovekin' ),
			'edit_item'          => __( 'Edit Course', 'lovekin' ),
			'new_item'           => __( 'New Course', 'lovekin' ),
			'view_item'          => __( 'View Course', 'lovekin' ),
			'search_items'       => __( 'Search Courses', 'lovekin' ),
			'not_found'          => __( 'No courses found', 'lovekin' ),
			'not_found_in_trash' => __( 'No courses found in trash', 'lovekin' ),
			'menu_name'          => __( 'Courses', 'lovekin' ),
		);

		register_post_type(
			'lk_course',
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-welcome-learn-more',
				'has_archive'        => false,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'capability_type'    => array( 'lk_course', 'lk_courses' ),
				'map_meta_cap'       => true,
				'show_in_menu'       => false,
				'capabilities'       => array(
					'edit_post'          => 'edit_lk_course',
					'read_post'          => 'read_lk_course',
					'delete_post'        => 'delete_lk_course',
					'edit_posts'         => 'edit_lk_courses',
					'delete_posts'       => 'delete_lk_courses',
					'edit_others_posts'  => 'edit_others_lk_courses',
					'edit_published_posts' => 'edit_published_lk_courses',
					'edit_private_posts' => 'edit_private_lk_courses',
					'delete_others_posts' => 'delete_others_lk_courses',
					'delete_published_posts' => 'delete_published_lk_courses',
					'delete_private_posts' => 'delete_private_lk_courses',
					'publish_posts'      => 'publish_lk_courses',
					'read_private_posts' => 'read_private_lk_courses',
				),
				'rewrite'            => array( 'slug' => 'lovekin-course' ),
			)
		);
	}

	public static function register_meta() {
		register_post_meta(
			'lk_course',
			'_lk_course_file_url',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => function() {
					return current_user_can( 'lk_manage_courses' ) || current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function meta_boxes() {
		add_meta_box(
			'lk_course_materials',
			__( 'Course Materials', 'lovekin' ),
			array( __CLASS__, 'render_meta' ),
			'lk_course',
			'side',
			'default'
		);
	}

	public static function render_meta( $post ) {
		wp_nonce_field( 'lk_course_meta', 'lk_course_meta_nonce' );
		$file_url = get_post_meta( $post->ID, '_lk_course_file_url', true );
		?>
		<p class="description"><?php esc_html_e( 'Add a PDF or resource URL for this course (e.g. media library file URL).', 'lovekin' ); ?></p>
		<input type="url" name="lk_course_file_url" class="widefat" value="<?php echo esc_url( $file_url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/course.pdf', 'lovekin' ); ?>" />
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['lk_course_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lk_course_meta_nonce'] ) ), 'lk_course_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file_url = isset( $_POST['lk_course_file_url'] ) ? esc_url_raw( wp_unslash( $_POST['lk_course_file_url'] ) ) : '';
		update_post_meta( $post_id, '_lk_course_file_url', $file_url );
	}
}
