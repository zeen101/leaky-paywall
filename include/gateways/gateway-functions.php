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
 * @since 3.7.0
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
 * Load webhook processor for all gateways
 *
 * @access      public
 * @since       4.0.0
 * @return      void
*/
function leaky_paywall_process_gateway_webhooks() {

	$gateways = new Leaky_Paywall_Payment_Gateways;

	foreach( $gateways->available_gateways  as $key => $gateway ) {

		if( is_array( $gateway ) && isset( $gateway['class'] ) ) {

			$gateway = new $gateway['class'];
			$gateway->process_webhooks();

		}

	}

}
add_action( 'init', 'leaky_paywall_process_gateway_webhooks', -99999 );

/**
 * Process gateway confirmaions
 *
 * @access      public
 * @since       3.7.0
 * @return      void
*/
function leaky_paywall_process_gateway_confirmations() {

	if( empty( $_GET['leaky-paywall-confirm'] ) ) {
		return;
	}

	$gateways = new Leaky_Paywall_Payment_Gateways;
	$gateway  = sanitize_text_field( $_GET['leaky-paywall-confirm'] );

	if( ! $gateways->is_gateway_enabled( $gateway ) ) {
		return;
	}

	$gateway = $gateways->get_gateway( $gateway );

	if( is_array( $gateway ) && isset( $gateway['class'] ) ) {

		$gateway = new $gateway['class'];
		$gateway->process_confirmation();

	}

}
add_action( 'wp', 'leaky_paywall_process_gateway_confirmations', -99999 );


/**
 * Load scripts for all gateways
 *
 * @access      public
 * @since       3.7.0
 * @return      void
*/
function leaky_paywall_load_gateway_scripts() {

	$gateways = new Leaky_Paywall_Payment_Gateways;

	foreach( $gateways->enabled_gateways  as $key => $gateway ) {

		if( is_array( $gateway ) && isset( $gateway['class'] ) ) {

			$gateway = new $gateway['class'];
			$gateway->scripts();

		}

	}

}
add_action( 'wp_enqueue_scripts', 'leaky_paywall_load_gateway_scripts', 100 );
