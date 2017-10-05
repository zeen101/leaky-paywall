<?php
/**
 * Stripe Checkout Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
*/

class Leaky_Paywall_Payment_Gateway_Stripe_Checkout extends Leaky_Paywall_Payment_Gateway_Stripe {

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_confirmation() {

		if ( empty( $_GET['leaky-paywall-confirm'] ) && $_GET['leaky-paywall-confirm'] != 'stripe_checkout' ) {
			return false;
		}

		$settings = get_leaky_paywall_settings();

		$this->email = $_POST['stripeEmail'];
		$this->level_id = $_POST['custom'];

		$level = get_leaky_paywall_subscription_level( $this->level_id );

		$this->level_name  = $level['label'];
		$this->recurring   = !empty( $level['recurring'] ) ? $level['recurring'] : false;
		$this->plan_id     = !empty( $level['plan_id'] ) ? $level['plan_id'] : false;
		$this->level_price = $level['price'];

		// @todo: Fix: this will ignore coupons
		$this->amount      = $level['price'];
		$this->currency    = $settings['leaky_paywall_currency'];
		$this->length_unit = $level['interval'];
		$this->length      = $level['interval_count'];

		if ( ! class_exists( 'Stripe' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/lib/Stripe.php';
		}

		$subscriber_data = parent::process_signup();

		do_action( 'leaky_paywall_after_process_stripe_checkout', $subscriber_data );

		leaky_paywall_subscriber_registration( $subscriber_data );

		/// exit after this


		// if ( is_user_logged_in() || !empty( $gateway_data['existing_customer'] ) ) {
		// 	//if the email already exists, we want to update the subscriber, not create a new one
		// 	$user_id = leaky_paywall_update_subscriber( NULL,  $subscriber_data['subscriber_email'], $subscriber_data['subscriber_id'], $subscriber_data ); 
		// 	$status = 'update';
		// } else {
		// 	// create the new customer as a leaky paywall subscriber
		// 	$user_id = leaky_paywall_new_subscriber( NULL,  $subscriber_data['subscriber_email'], $subscriber_data['subscriber_id'], $subscriber_data );
		// 	$status = 'new';
		// }

		// if ( empty( $user_id ) ) {
		// 	leaky_paywall_errors()->add( 'user_not_created', __( 'A user could not be created. Please check your details and try again.', 'leaky-paywall' ), 'register' );
		// 	return;
		// }

		// // Send email notifications
		// leaky_paywall_email_subscription_status( $user_id, $status, $subscriber_data );

		// // log the user in
		// leaky_paywall_log_in_user( $user_id );

		
		// // send the newly created user to the appropriate page after logging them in
		// wp_redirect( leaky_paywall_get_redirect_url( $settings, $subscriber_data ) );

		// exit;

	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 */
	public function fields() {

	}

}