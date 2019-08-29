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
		$this->level_price = $level['price'];

		if ( $this->recurring ) {

			$currency = leaky_paywall_get_currency();
			$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];

			// @todo: make this a function so we can use it on the credit card form too
			if ( in_array( strtoupper( $currency ), array( 'BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF' ) ) ) {
				//Zero-Decimal Currencies
				//https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
				$stripe_price = number_format( $level['price'], '0', '', '' );
			} else {
				$stripe_price = number_format( $level['price'], '2', '', '' ); //no decimals
			}

			$plan_args = array(
				'stripe_price'	=> $stripe_price,
				'currency'		=> $currency,
				'secret_key'	=> $secret_key
			);
		
	        $stripe_plan = leaky_paywall_get_stripe_plan( $level, $this->level_id, $plan_args );
	        $this->plan_id = $stripe_plan->id;

		} else {
			$this->plan_id = false;
		}

		// @todo: Fix: this will ignore coupons
		$this->amount      = $level['price'];
		$this->currency    = leaky_paywall_get_currency();
		$this->length_unit = $level['interval'];
		$this->length      = $level['interval_count'];

		if ( ! class_exists( 'Stripe' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/lib/Stripe.php';
		}

		$subscriber_data = parent::process_signup();

		do_action( 'leaky_paywall_after_process_stripe_checkout', $subscriber_data );

		leaky_paywall_subscriber_registration( $subscriber_data );

	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 */
	public function fields( $level_id ) {

	}

}