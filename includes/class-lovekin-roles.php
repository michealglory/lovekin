<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoveKin_Roles {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_roles' ) );
	}

	public static function register_roles() {
		$primary_caps   = self::primary_caps();
		$secondary_caps = self::secondary_caps();

		add_role( 'lk_primary', __( 'Primary Member', 'lovekin' ), $primary_caps );
		add_role( 'lk_secondary', __( 'Secondary Member', 'lovekin' ), $secondary_caps );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$caps = $primary_caps + $secondary_caps + self::admin_caps();
			foreach ( $caps as $cap => $grant ) {
				$admin_role->add_cap( $cap, (bool) $grant );
			}
		}
	}

	private static function base_caps() {
		return array(
			'read'               => true,
			'lk_view_dashboard'  => true,
			'lk_take_assessments'=> true,
			'lk_view_reports'    => true,
			'lk_edit_profile'    => true,
			'lk_request_funding' => true,
			'lk_manage_archive'  => true,
			'lk_view_documents'  => true,
		);
	}

	private static function primary_caps() {
		return array_merge(
			self::base_caps(),
			array(
				'lk_view_assigned_members' => true,
				'lk_edit_remarks'          => true,
			)
		);
	}

	private static function secondary_caps() {
		return self::base_caps();
	}

	private static function admin_caps() {
		return array(
			'lk_manage_courses'        => true,
			'lk_manage_assessments'    => true,
			'lk_assign_relationships'  => true,
			'lk_review_funding'        => true,
			'lk_upload_documents'      => true,
			'lk_manage_settings'       => true,
			'lk_view_all_reports'      => true,
			'lk_manage_relationships'  => true,
			'edit_lk_course'           => true,
			'read_lk_course'           => true,
			'delete_lk_course'         => true,
			'edit_lk_courses'          => true,
			'edit_others_lk_courses'   => true,
			'edit_published_lk_courses'=> true,
			'edit_private_lk_courses'  => true,
			'publish_lk_courses'       => true,
			'read_private_lk_courses'  => true,
			'edit_lk_assessment'       => true,
			'read_lk_assessment'       => true,
			'delete_lk_assessment'     => true,
			'edit_lk_assessments'      => true,
			'edit_others_lk_assessments'=> true,
			'edit_published_lk_assessments'=> true,
			'edit_private_lk_assessments'  => true,
			'publish_lk_assessments'   => true,
			'read_private_lk_assessments'=> true,
		);
	}
}
