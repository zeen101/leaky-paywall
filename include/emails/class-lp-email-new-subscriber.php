<?php

/**
 * New subscriber welcome email.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Email_New_Subscriber extends LP_Email {

	public function __construct() {
		$this->id              = 'new_subscriber';
		$this->title           = __( 'New Subscriber Email', 'leaky-paywall' );
		$this->description     = __( 'Sent to the subscriber after they sign up.', 'leaky-paywall' );
		$this->recipient_type  = 'subscriber';
		$this->default_enabled = 'yes';
		$this->default_subject = __( 'Welcome to %sitename%', 'leaky-paywall' );
		$this->default_body    = $this->get_default_body();
		$this->template_tags   = array( '%blogname%', '%sitename%', '%username%', '%useremail%', '%password%', '%firstname%', '%lastname%', '%displayname%' );

		parent::__construct();
	}

	/**
	 * Trigger the new subscriber email.
	 *
	 * Preserves existing filters used by Per Level Emails, Double Opt-in, etc.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Must include 'password' and 'status'.
	 */
	public function trigger( $user_id, $args = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		if ( 'traditional' !== $settings['login_method'] ) {
			return;
		}

		$status   = isset( $args['status'] ) ? $args['status'] : 'new';
		$password = isset( $args['password'] ) ? $args['password'] : '';

		// Preserve existing filters (used by Per Level Emails at priority 20).
		$message = stripslashes( apply_filters( 'leaky_paywall_new_email_message', $this->body, $user_id ) );
		$subject = stripslashes( apply_filters( 'leaky_paywall_new_email_subject', $this->subject, $user_id ) );

		$user_info    = get_userdata( $user_id );
		$display_name = $user_info ? $user_info->display_name : '';

		$filtered_subject = $this->replace_tags( $subject, $user_id, $display_name, $password );
		$filtered_message = $this->replace_tags( $message, $user_id, $display_name, $password );
		$filtered_message = wpautop( make_clickable( $filtered_message ) );

		if ( ! apply_filters( 'leaky_paywall_send_' . $status . '_email', true, $user_id ) ) {
			return;
		}

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, $status );

		wp_mail( $user_info->user_email, $filtered_subject, $filtered_message, $headers, $attachments );
	}

	/**
	 * Default email body matching the existing default in settings.php.
	 *
	 * @return string
	 */
	private function get_default_body() {
		return 'Welcome to %sitename%, %firstname%!

Thank you for subscribing. Your account is now active and ready to use.

<b>Your Login Details</b>

Username: %username%
Password: %password%

You can log in anytime to access your subscription content and manage your account.

If you have any questions, simply reply to this email — we are happy to help.

Thank you for your support!

The %sitename% Team';
	}
}
