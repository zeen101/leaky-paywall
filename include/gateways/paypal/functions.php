<?php 

add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_paypal_subscription_cards', 7, 3 );

/**
 * Add the Paypal subscribe option to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_paypal_subscription_cards( $payment_options, $level, $level_id ) {

	if ( $level['price'] == 0 ) {
		return $payment_options;
	}

	$output = '';

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	if ( in_array( 'paypal_standard', array_keys( $enabled_gateways ) ) && $settings['enable_paypal_on_registration'] != 'on' ) {
		$output = leaky_paywall_paypal_button( $level, $level_id );
	}

	return $payment_options . $output;

}

/**
 * Add the Paypal subscribe or buy now button to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_paypal_button( $level, $level_id ) {

	$results = '';
	$settings = get_leaky_paywall_settings();
	$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
	$paypal_sandbox = 'off' === $settings['test_mode'] ? '' : 'sandbox';
	$paypal_account = 'on' === $settings['test_mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
	$currency = $settings['leaky_paywall_currency'];
	$current_user = wp_get_current_user();
	if ( 0 !== $current_user->ID ) {
		$user_email = $current_user->user_email;
	} else {
		$user_email = 'no_lp_email_set';
	}
	if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
																				
		$results .= '<script src="' . LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
						data-env="' . esc_js( $paypal_sandbox ) . '" 
						data-callback="' . esc_js( add_query_arg( 'listener', 'IPN', get_site_url() . '/' ) ) . '"
						data-return="' . esc_js( add_query_arg( 'leaky-paywall-confirm', 'paypal_standard', get_page_link( $settings['page_for_after_subscribe'] ) ) ) . '"
						data-cancel_return="' . esc_js( add_query_arg( 'leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_profile'] ) ) ) . '" 
						data-src="1" 
						data-period="' . esc_js( strtoupper( substr( $level['interval'], 0, 1 ) ) ) . '" 
						data-recurrence="' . esc_js( $level['interval_count'] ) . '" 
						data-currency="' . esc_js( apply_filters( 'leaky_paywall_paypal_currency', $currency ) ) . '" 
						data-amount="' . esc_js( $level['price'] ) . '" 
						data-name="' . esc_js( $level['label'] ) . '" 
						data-number="' . esc_js( $level_id ) . '"
						data-button="subscribe" 
						data-no_note="1" 
						data-no_shipping="1" 
						data-custom="' . esc_js( $user_email ) . '"
					></script>';
											
	} else {
					
		$results .= '<script src="' . LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
						data-env="' . esc_js( $paypal_sandbox ) . '" 
						data-callback="' . esc_js( add_query_arg( 'listener', 'IPN', get_site_url() . '/' ) ) . '" 
						data-return="' . esc_js( add_query_arg( 'leaky-paywall-confirm', 'paypal_standard', get_page_link( $settings['page_for_after_subscribe'] ) ) ) . '"
						data-cancel_return="' . esc_js( add_query_arg( 'leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_profile'] ) ) ) . '" 
						data-tax="0" 
						data-shipping="0" 
						data-currency="' . esc_js( apply_filters( 'leaky_paywall_paypal_currency', $currency ) ) . '" 
						data-amount="' . esc_js( $level['price'] ) . '" 
						data-quantity="1" 
						data-name="' . esc_js( $level['label'] ) . '" 
						data-number="' . esc_js( $level_id ) . '"
						data-button="buynow" 
						data-no_note="1" 
						data-no_shipping="1" 
						data-shipping="0" 
						data-custom="' . esc_js( $user_email ) . '"
					></script>';
	
	}

	if ( empty( $paypal_account ) ) {
		$results = 'Please enter your Paypal credentials in the Leaky Paywall settings.';
	}
	
	return '<div class="leaky-paywall-paypal-standard-button leaky-paywall-payment-button">' . $results . '</div>';
	
}
