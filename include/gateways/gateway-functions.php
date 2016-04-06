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

/**
 * Return list of active gateways
 *
 * @access      private
 * @return      array
*/
function leaky_paywall_get_enabled_payment_gateways() {

	$gateways = new Leaky_Paywall_Payment_Gateways;

	foreach( $gateways->enabled_gateways as $key => $gateway ) {

		if( is_array( $gateway ) ) {

			$gateways->enabled_gateways[ $key ] = $gateway['label'];

		}

	}

	return $gateways->enabled_gateways;
}