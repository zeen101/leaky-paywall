<?php 

add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_stripe_add_signup_link', 7, 3 );

/**
 * Add the subscribe link to the subscribe cards. 
 * @todo add a plugin option for setting the page that the payment form shortcode is on
 *
 * @since 1.0.0
 */
function leaky_paywall_stripe_add_signup_link( $payment_options, $level, $level_id ) {

	$output = '';

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	if ( in_array( 'stripe', array_keys( $enabled_gateways ) ) ) {
		$output = '<a href="' . $settings['page_for_register'] . '/?level_id=' . $level_id . '">Subscribe</a>';
	}

	return $payment_options . $output;

}