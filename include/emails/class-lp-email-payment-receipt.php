<?php

/**
 * Payment receipt email.
 *
 * Sent to the subscriber after any real payment: initial signup and every
 * recurring renewal, across all gateways. Driven off an lp_transaction record
 * rather than a user, so renewals (which never route through the registration
 * flow) are covered by the same code.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Email_Payment_Receipt extends LP_Email {

	public function __construct() {
		$this->id              = 'payment_receipt';
		$this->title           = __( 'Payment Receipt Email', 'leaky-paywall' );
		$this->description     = __( 'Sent to the subscriber after a payment, including subscription renewals.', 'leaky-paywall' );
		$this->recipient_type  = 'subscriber';
		$this->default_enabled = 'no';
		// translators: %sitename% is a Leaky Paywall template tag replaced with the site name; keep it verbatim.
		$this->default_subject = __( 'Your receipt from %sitename%', 'leaky-paywall' );
		$this->default_body    = $this->get_default_body();
		$this->template_tags   = array(
			'%firstname%',
			'%lastname%',
			'%displayname%',
			'%sitename%',
			'%amount%',
			'%currency%',
			'%subtotal%',
			'%tax%',
			'%payment_date%',
			'%payment_method%',
			'%payment_type%',
			'%level_name%',
			'%invoice_number%',
			'%transaction_id%',
			'%next_renewal_date%',
			'%account_url%',
			'%receipt_url%',
		);

		parent::__construct();
	}

	/**
	 * Send the receipt for a transaction.
	 *
	 * @param int   $user_id User ID. May be 0; the transaction's email is the
	 *                       source of truth for the recipient.
	 * @param array $args    Requires 'transaction_id'. Optional 'force' to send
	 *                       even when the email type is disabled (admin resend).
	 * @return bool
	 */
	public function trigger( $user_id, $args = array() ) {

		$transaction_id = isset( $args['transaction_id'] ) ? absint( $args['transaction_id'] ) : 0;

		if ( ! $transaction_id ) {
			return false;
		}

		$force = ! empty( $args['force'] );

		if ( ! $force && ! $this->is_enabled() ) {
			return false;
		}

		$email = get_post_meta( $transaction_id, '_email', true );

		if ( ! $user_id && $email ) {
			$user    = get_user_by( 'email', $email );
			$user_id = $user ? $user->ID : 0;
		}

		$user_info = $user_id ? get_userdata( $user_id ) : null;

		if ( ! $email && $user_info ) {
			$email = $user_info->user_email;
		}

		if ( ! is_email( $email ) ) {
			return false;
		}

		$display_name = $user_info ? $user_info->display_name : '';

		$subject = stripslashes( $this->subject );
		$message = stripslashes( apply_filters( 'leaky_paywall_payment_receipt_email_message', $this->body, $transaction_id ) );

		// User / site tags. leaky_paywall_filter_email_tags() needs a real user;
		// skip it when the transaction has no matching WP account and let the
		// transaction tags carry the email.
		if ( $user_id ) {
			$subject = leaky_paywall_filter_email_tags( $subject, $user_id, $display_name, '' );
			$message = leaky_paywall_filter_email_tags( $message, $user_id, $display_name, '' );
		}

		$subject = leaky_paywall_replace_payment_receipt_tags( $subject, $transaction_id );
		$message = leaky_paywall_replace_payment_receipt_tags( $message, $transaction_id );

		$message = wpautop( make_clickable( $message ) );

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, 'payment_receipt' );

		$sent = wp_mail( $email, $subject, $message, $headers, $attachments );

		if ( $sent ) {
			update_post_meta( $transaction_id, '_lp_receipt_sent', time() );
		}

		return $sent;
	}

	/**
	 * Default receipt body. Deliberately plain, tune after.
	 *
	 * @return string
	 */
	private function get_default_body() {
		return 'Hi %firstname|there%,

Here is your receipt for your payment at %sitename%.

Type: %payment_type%
Amount: %amount%
Date: %payment_date%
Item: %level_name%
Payment method: %payment_method%
Invoice number: %invoice_number%

You can view your account any time at %account_url%.

Thank you for your support.

The %sitename% Team';
	}
}
