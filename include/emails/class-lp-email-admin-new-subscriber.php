<?php

/**
 * Admin new subscriber notification email.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Email_Admin_New_Subscriber extends LP_Email {

	public function __construct() {
		$this->id                 = 'admin_new_subscriber';
		$this->title              = __( 'Admin New Subscriber Email', 'leaky-paywall' );
		$this->description        = __( 'Sent to the admin when a new subscriber signs up.', 'leaky-paywall' );
		$this->recipient_type     = 'admin';
		$this->default_enabled    = 'yes';
		$this->default_subject    = 'New subscription on ' . stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
		$this->default_body       = '';
		$this->default_recipients = get_option( 'admin_email' );

		parent::__construct();
	}

	/**
	 * Trigger the admin notification email.
	 *
	 * The body is auto-generated with subscriber details, not user-configurable.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Must include 'status'.
	 */
	public function trigger( $user_id, $args = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( empty( $this->recipients ) ) {
			return;
		}

		$status = isset( $args['status'] ) ? $args['status'] : 'new';

		if ( ! apply_filters( 'leaky_paywall_send_' . $status . '_admin_email', true, $user_id ) ) {
			return;
		}

		$user_info  = get_userdata( $user_id );
		$mode       = leaky_paywall_get_current_mode();
		$site       = leaky_paywall_get_current_site();
		$site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
		$level_id   = get_user_meta( $user_info->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
		$level      = get_leaky_paywall_subscription_level( $level_id );
		$level_name = isset( $level['label'] ) ? $level['label'] : '';

		$admin_message = '<p>A new user has signed up on ' . esc_html( $site_name ) . '.</p>
			<h3>Subscriber details</h3>
			<ul>
			<li><strong>Subscription:</strong> ' . esc_html( $level_name ) . '</li>';

		if ( $user_info->first_name ) {
			$admin_message .= '<li><strong>Name:</strong> ' . esc_html( $user_info->first_name . ' ' . $user_info->last_name ) . '</li>';
		}

		$admin_message .= '<li><strong>Email:</strong> ' . esc_html( $user_info->user_email ) . '</li></ul>';

		$admin_message = apply_filters( 'leaky_paywall_new_subscriber_admin_email', $admin_message, $user_info );

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, $status );

		wp_mail( $this->recipients, $this->subject, $admin_message, $headers, $attachments );
	}

	/**
	 * No body field for this email — the content is auto-generated.
	 */
	protected function output_body_field() {}
}
