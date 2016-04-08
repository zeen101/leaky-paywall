<?php 

/**
 * Load additional gateway include files
 *
 * @since       3.7.0
*/
function leaky_paywall_load_gateway_files() {
	foreach( leaky_paywall_get_payment_gateways() as $key => $gateway ) {
		if( file_exists( LEAKY_PAYWALL_PATH . 'include/gateways/' . $key . '/functions.php' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/gateways/' . $key . '/functions.php';
		}
	}
}
add_action( 'plugins_loaded', 'leaky_paywall_load_gateway_files', 9999 );

/**
 * Register default payment gateways
 *
 * @return      array
*/
function leaky_paywall_get_payment_gateways() {
	$gateways = new Leaky_Paywall_Payment_Gateways;
	return $gateways->available_gateways;
}


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


/**
 * Calls the load_fields() method for gateways when a gateway selection is made
 *
 * @access      public
 * @since       2.1
 */
function leaky_paywall_load_gateway_fields( $gateways ) {



	foreach( $gateways as $key => $gateway ) {

		$all_gateways = new Leaky_Paywall_Payment_Gateways;
		$gateway = $all_gateways->get_gateway( $key );

		$gateway = new $gateway['class'];
		$gateway->init();

		echo $gateway->fields();

	}
	
}
add_action( 'leaky_paywall_before_registration_submit_field', 'leaky_paywall_load_gateway_fields', 10, 1 );


/**
 * Load scripts for all gateways
 *
 * @access      public
 * @since       3.7.0
 * @return      void
*/
function leaky_paywall_load_gateway_scripts() {

	// global $rcp_options;

	// if( ! rcp_is_registration_page() && ! defined( 'RCP_LOAD_SCRIPTS_GLOBALLY' ) ) {
	// 	return;
	// }

	$gateways = new Leaky_Paywall_Payment_Gateways;

	foreach( $gateways->enabled_gateways  as $key => $gateway ) {

		if( is_array( $gateway ) && isset( $gateway['class'] ) ) {

			$gateway = new $gateway['class'];
			$gateway->scripts();

		}

	}

}
add_action( 'wp_enqueue_scripts', 'leaky_paywall_load_gateway_scripts', 100 );
