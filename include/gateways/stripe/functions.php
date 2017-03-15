<?php 

/**
 * Add the subscribe link to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_subscription_cards( $payment_options, $level, $level_id ) {

	if ( $level['price'] == 0 ) {
		return $payment_options;
	}

	$output = '';

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	if ( in_array( 'stripe', array_keys( $enabled_gateways ) ) ) {
		$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">Subscribe</a></div>';
	}

	if ( in_array( 'stripe_checkout', array_keys( $enabled_gateways ) ) ) {
		$output .= leaky_paywall_stripe_checkout_button( $level, $level_id );
	}

	return $payment_options . $output;

}
add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_stripe_subscription_cards', 7, 3 );


/**
 * Add the Stripe subscribe popup button to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_checkout_button( $level, $level_id ) {

	$results = '';
	$settings = get_leaky_paywall_settings();
	$currency = apply_filters( 'leaky_paywall_stripe_currency', $settings['leaky_paywall_currency'] );

	// @todo: make this a function so we can use it on the credit card form too
	if ( in_array( strtoupper( $currency ), array( 'BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF' ) ) ) {
		//Zero-Decimal Currencies
		//https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
		$stripe_price = number_format( $level['price'], '0', '', '' );
	} else {
		$stripe_price = number_format( $level['price'], '2', '', '' ); //no decimals
	}
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
	$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];

	if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
		
		try {

			$plan_args = array(
				'stripe_price'	=> $stripe_price,
				'currency'		=> $currency,
				'secret_key'	=> $secret_key
			);
		
	        $stripe_plan = leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args );
			
			$results .= '<form action="' . esc_url( add_query_arg( 'leaky-paywall-confirm', 'stripe_checkout', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
						  <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
						  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
								  data-key="' . esc_js( $publishable_key ) . '"
								  data-label="' . apply_filters('leaky_paywall_stripe_button_label', 'Subscribe' ) . '" 
								  data-plan="' . esc_js( $stripe_plan->id ) . '" 
								  data-currency="' . esc_js( $currency ) . '" 
								  data-description="' . esc_js( $level['label'] ) . '">
						  </script>
						  ' . apply_filters( 'leaky_paywall_pay_with_stripe_recurring_payment_form_after_script', '' ) . '
						</form>';
							
		} catch ( Exception $e ) {

			$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';

		}
		
	} else {
					
		$results .= '<form action="' . esc_url( add_query_arg( 'leaky-paywall-confirm', 'stripe_checkout', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
					  <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
					  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
							  data-key="' . esc_js( $publishable_key ) . '"
							  data-label="' . apply_filters('leaky_paywall_stripe_button_label', 'Subscribe' ) . '" 
							  data-amount="' . esc_js( $stripe_price ) . '" 
							  data-currency="' . esc_js( $currency ) . '" 
							  data-description="' . esc_js( $level['label'] ) . '">
					  </script>
						  ' . apply_filters( 'leaky_paywall_pay_with_stripe_non_recurring_payment_form_after_script', '' ) . '
					</form>';
	
	}

	return '<div class="leaky-paywall-stripe-button leaky-paywall-payment-button">' . $results . '</div>';

}


/**
 * Gets the stripe plan associated with the level, and creates one if it doesn't exist
 *
 * @since 4.0.0
 */
function leaky_paywall_get_stripe_plan( $level, $level_id , $plan_args ) {

	$settings = get_leaky_paywall_settings();
    $stripe_plan = false;
    $time = time();

    Stripe::setApiKey( $plan_args['secret_key'] );

	if ( !empty( $level['plan_id'] ) ) {
		//We need to verify that the plan_id matches the level details, otherwise we need to update it
		try {
            $stripe_plan = Stripe_Plan::retrieve( $level['plan_id'] );
        }
        catch( Exception $e ) {
            $stripe_plan = false;
        }
		
	}

	if ( !is_object( $stripe_plan ) || //If we don't have a stripe plan
		( //or the stripe plan doesn't match...
			$plan_args['stripe_price']	!= $stripe_plan->amount 
			|| $level['interval'] 		!= $stripe_plan->interval 
			|| $level['interval_count'] != $stripe_plan->interval_count
		) 
	) {
		
		$args = array(
            'amount'            => esc_js( $plan_args['stripe_price'] ),
            'interval'          => esc_js( $level['interval'] ),
            'interval_count'    => esc_js( $level['interval_count'] ),
            'name'              => esc_js( $level['label'] ) . ' ' . $time,
            'currency'          => esc_js( $plan_args['currency'] ),
            'id'                => sanitize_title_with_dashes( $level['label'] ) . '-' . $time,
        );
        	
        $stripe_plan = Stripe_Plan::create( apply_filters( 'leaky_paywall_create_stripe_plan', $args ) );

        $settings['levels'][$level_id]['plan_id'] = $stripe_plan->id;
        update_leaky_paywall_settings( $settings );
   
    }

    return $stripe_plan;

}