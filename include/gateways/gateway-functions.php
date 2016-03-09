<?php 

/**
 * Send payment / subscription data to gateway
 *
 * @since 3.5.1
 * @return array
 */

function leaky_paywall_send_to_gateway( $gateway, $subscription_data ) {

	$gateways = new Leaky_Paywall_Payment_Gateways;
	$gateway = $gateways->get_gateway( $gateway );
	$gateway = new $gateway['class']( $subscription_data );

	$gateway->process_signup();
	
}
