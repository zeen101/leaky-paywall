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
		$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">' . __( 'Subscribe', 'leaky-paywall' ) . '</a></div>';
	}

	if ( in_array( 'stripe_checkout', array_keys( $enabled_gateways ) ) ) {
		// $output .= leaky_paywall_stripe_checkout_button( $level, $level_id );
		$output .= leaky_paywall_stripe_checkout_v3_button( $level, $level_id );

	}

	return $payment_options . $output;

}
add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_stripe_subscription_cards', 7, 3 );


/**
 * Add the Stripe subscribe popup button to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_checkout_v3_button( $level, $level_id ) {

	$settings = get_leaky_paywall_settings();
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
	$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];

	\Stripe\Stripe::setApiKey( $secret_key );

	if ( !empty( $settings['page_for_after_subscribe'] ) ) {
		$success_url = get_page_link( $settings['page_for_after_subscribe'] );
	} else if ( !empty( $settings['page_for_profile'] ) ) {
		$success_url = get_page_link( $settings['page_for_profile'] );
	} else {
		$success_url = home_url();
	}

	$currency = apply_filters( 'leaky_paywall_stripe_currency', leaky_paywall_get_currency() );

	$session = \Stripe\Checkout\Session::create([
	  'payment_method_types' => ['card'],
	  'line_items' => [[
	    'name' => $level['label'],
	    'amount' => $level['price'] * 100,
	    'currency' => $currency,
	    'quantity' => 1,
	  ]],
	  'success_url' => $success_url,
	  'cancel_url' => get_page_link( $settings['page_for_subscription'] ),
	]);

    ob_start(); ?>
    
    	<a class="stripe-subscribe-<?php echo $level_id; ?>" href="#">Subscribe</a>

    	<script>
    		( function( $ )  {

				$(document).ready( function() {
					
					$('.stripe-subscribe-<?php echo $level_id; ?>').click(function(e) {
						e.preventDefault();

						var stripe = Stripe( '<?php echo $publishable_key; ?>' );

						stripe.redirectToCheckout({
						  sessionId: '<?php echo $session->id; ?>'
						}).then(function (result) {
						  // If `redirectToCheckout` fails due to a browser or network
						  // error, display the localized error message to your customer
						  // using `result.error.message`.
						});

					});
				});

			})( jQuery );
    	</script>
    
    <?php  $content = ob_get_contents();
	ob_end_clean();

	return $content; 

}

/**
 * Add the Stripe subscribe popup button to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_checkout_button( $level, $level_id ) {

	$results = '';
	$settings = get_leaky_paywall_settings();
	$currency = apply_filters( 'leaky_paywall_stripe_currency', leaky_paywall_get_currency() );

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

	if ( !$secret_key ) {
		return '<p>Please enter Stripe API keys in <a href="' . admin_url() . 'admin.php?page=issuem-leaky-paywall&tab=payments">your Leaky Paywall settings</a>.</p>';
	}
	
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
								  data-locale="auto"
								  data-label="' . apply_filters('leaky_paywall_stripe_button_label', __( 'Subscribe', 'leaky-paywall' ) ) . '" 
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
							  data-locale="auto"
							  data-label="' . apply_filters('leaky_paywall_stripe_button_label', __( 'Subscribe', 'leaky-paywall' ) ) . '" 
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
    $match = false;
    $time = time();

    try {
    	\Stripe\Stripe::setApiKey( $plan_args['secret_key'] );
    } catch (Exception $e) {
    	return new WP_Error( 'missing_api_key', sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) );
    }

   	if ( !isset( $level['plan_id'] ) ) {
   		$level['plan_id'] = array();
   	}

    if ( !is_array( $level['plan_id'] ) ) {
    	$plan_temp = $level['plan_id'];
    	$settings['levels'][$level_id]['plan_id'] = array( $plan_temp );
    	update_leaky_paywall_settings( $settings );
    }

	if ( !empty( $level['plan_id'] ) ) {

		foreach( (array)$level['plan_id'] as $plan_id ) {

			//We need to verify that the plan_id matches the level details, otherwise we need to update it
			try {
	            $stripe_plan = \Stripe\Plan::retrieve( $plan_id );
	        }
	        catch( Exception $e ) {
	            $stripe_plan = false;
	        }

	        if ( !is_object( $stripe_plan ) || //If we don't have a stripe plan
	        	( //or the stripe plan doesn't match...
	        		$plan_args['stripe_price']	!= $stripe_plan->amount 
	        		|| $level['interval'] 		!= $stripe_plan->interval 
	        		|| $level['interval_count'] != $stripe_plan->interval_count
	        	) 
	        ) {
	        	// does not match
	        } else {
	        	$match = $stripe_plan; // this plan matches, so send it back
	        }

		}

	}

	if ( !$match ) {
		$stripe_plan = leaky_paywall_create_stripe_plan( $level, $level_id , $plan_args );
        
        $settings['levels'][$level_id]['plan_id'][] = $stripe_plan->id;
        update_leaky_paywall_settings( $settings );
	} else {
		$stripe_plan = $match;
	}

    return $stripe_plan;

}


/**
 * Create a stripe plan
 *
 * @since 4.9.3
 */
function leaky_paywall_create_stripe_plan( $level, $level_id , $plan_args ) {

	$time = time();

	$args = array(
        'amount'            => esc_js( $plan_args['stripe_price'] ),
        'interval'          => esc_js( $level['interval'] ),
        'interval_count'    => esc_js( $level['interval_count'] ),
        'name'              => esc_js( leaky_paywall_normalize_chars( $level['label'] ) ) . ' ' . $time,
        'currency'          => esc_js( $plan_args['currency'] ),
        'id'                => sanitize_title_with_dashes( leaky_paywall_normalize_chars( $level['label'] ) ) . '-' . $time,
    );

    try {
    	$stripe_plan = \Stripe\Plan::create( apply_filters( 'leaky_paywall_create_stripe_plan', $args, $level, $level_id ) );
    } catch (Exception $e) {
    	$stripe_plan = false;
    }

    return $stripe_plan;
    	
}

/**
 * Check if the status of a subscription is valid
 *
 * @since 4.10.3
 */
function leaky_paywall_is_valid_stripe_subscription( $subscription ) {

	$valid_status = apply_filters( 'leaky_paywall_valid_stripe_subscription_status', array( 'active' ) );

	if ( in_array( $subscription->status, $valid_status ) ) {
		return true;
	}

	return false;

}