<?php
/**
 * All helper functions used with Leaky Paywall and PayPal
 *
 * @package Leaky Paywall
 * @since 1.0.0
 */

/**
 * Add the Paypal subscribe option to the subscribe cards.
 *
 * @since 4.0.0
 *
 * @param string  $payment_options The payment options.
 * @param array   $level The level.
 * @param integer $level_id The level id.
 */
function leaky_paywall_paypal_subscription_cards( $payment_options, $level, $level_id ) {

	if ( 0 == $level['price'] ) {
		return $payment_options;
	}

	$output = '';

	$gateways         = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	return '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">' . __( 'Subscribe', 'leaky-paywall' ) . '</a></div>';

}
add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_paypal_subscription_cards', 7, 3 );