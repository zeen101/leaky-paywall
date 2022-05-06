<?php
/**
 * Stripe Checkout Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
 */

  /**
   * This class extends the gateway class for Stripe Checkout
   *
   * @since 1.0.0
   */
class Leaky_Paywall_Payment_Gateway_Stripe_Checkout extends Leaky_Paywall_Payment_Gateway_Stripe {

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_confirmation() {

	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 *
	 * @param integer $level_id The level id.
	 */
	public function fields( $level_id ) {

		$level = get_leaky_paywall_subscription_level( $level_id );
		$price        = $level['price'];

		if ( $price < 1 ) {
			return;
		}

		ob_start();

		?>
		<a class="button" id="checkout"><?php esc_html_e( 'Continue to Payment', 'leaky-paywall' ); ?></a>

		<style>
			#leaky-paywall-submit, .leaky-paywall-payment-method-container, .leaky-paywall-card-details {
				display: none;
			}
		</style>

		<?php

		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}
}
