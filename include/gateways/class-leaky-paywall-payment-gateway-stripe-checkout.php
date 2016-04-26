<?php
/**
 * Stripe Checkout Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.70.0
*/

class Leaky_Paywall_Payment_Gateway_Stripe_Checkout extends Leaky_Paywall_Payment_Gateway_Stripe {

	/**
	 * Process registration
	 *
	 * @since 3.7.0
	 */
	public function process_signup() {

		if( ! empty( $_POST['leaky_paywall_stripe_checkout'] ) ) {

			// $this->auto_renew = '2' === rcp_get_auto_renew_behavior() ? false : true;
	
		}

		parent::process_signup();

	}

	/**
	 * Print fields for this gateway
	 *
	 * @return string
	 */
	public function fields() {

		
		
	}

}