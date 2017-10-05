<?php 

/**
 * Load additional gateway include files
 *
 * @since       4.0.0
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
 * @since  		4.0.0	
 * @return      array
*/
function leaky_paywall_get_payment_gateways() {
	$gateways = new Leaky_Paywall_Payment_Gateways;
	return $gateways->available_gateways;
}


/**
 * Send payment / subscription data to gateway
 *
 * @since 4.0.0
 * @return array
 */

function leaky_paywall_send_to_gateway( $gateway, $subscription_data ) {

	// we don't have an actual gateway class for a free registration at this time, so we format the data as needed here
	if ( $gateway == 'free_registration' ) {
		
		return array(
			'level_id' => $subscription_data['level_id'],
			'subscriber_id' => '',
			'subscriber_email' => $subscription_data['user_email'],
			'existing_customer' => false,
			'price' => 0,
			'description' => $subscription_data['description'],
			'payment_gateway' => 'free_registration',
			'payment_status' => 'active',
			'length_unit' => $subscription_data['length_unit'],
			'length' => $subscription_data['length'],
			'site' => $subscription_data['site'],
			'plan' => $subscription_data['plan'],
			'recurring' => false
		);

	}

	$gateways = new Leaky_Paywall_Payment_Gateways;
	$gateway = $gateways->get_gateway( $gateway );

	$gateway = new $gateway['class']( $subscription_data );

	return $gateway->process_signup();
	
}

/**
 * Return list of active gateways
 *
 * @since 		4.0.0
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
 * @since       4.0.0
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
 * @since       4.0.0
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
 * @since       4.0.0
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

/**
 * Add subscribe button to free level subcription cards
 *
 * @since  4.0.0
 * @return string 	payment option output for subscription card
 */
function leaky_paywall_free_subscription_cards( $payment_options, $level, $level_id ) {
	
	if ( $level['price'] != 0 ) {
		return $payment_options;
	}

	$settings = get_leaky_paywall_settings();

	$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">Subscribe</a></div>';

	return $payment_options . $output; 
}
add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_free_subscription_cards', 7, 3 );