<?php
/**
 * All helper functions used with Leaky Paywall and Stripe
 *
 * @package Leaky Paywall
 * @since 1.0.0
 */

/**
 * Check whether a recent duplicate signup attempt exists for this email + plan.
 *
 * Searches Stripe for any OTHER customer with the same email who has a subscription
 * on the same plan/price created within the dedup window. Returns true if found.
 *
 * Prevents the "I hit submit, saw an error, hit submit again, got charged twice" trap
 * — common on ACH where the post-payment feedback lag tempts subscribers to retry.
 *
 * Filterable window via leaky_paywall_duplicate_signup_window_minutes (default 60).
 * Set the filter to 0 to disable the check entirely.
 *
 * Fails open: if the Stripe lookup throws, allows the signup. Better to risk a rare
 * duplicate than block a legitimate signup on a transient API error.
 *
 * @since 5.x
 *
 * @param string                $email             Subscriber email.
 * @param string                $plan_id           Stripe price/plan ID being purchased.
 * @param string                $skip_customer_id  Customer ID to ignore (the one this attempt is using).
 * @param \Stripe\StripeClient  $stripe            Initialized Stripe client.
 *
 * @return bool True if a duplicate was found in the dedup window.
 */
function leaky_paywall_check_recent_duplicate_signup( $email, $plan_id, $skip_customer_id, $stripe ) {

	if ( empty( $email ) || empty( $plan_id ) ) {
		return false;
	}

	$window_minutes = (int) apply_filters( 'leaky_paywall_duplicate_signup_window_minutes', 60 );
	if ( $window_minutes <= 0 ) {
		return false;
	}

	$threshold = time() - ( $window_minutes * 60 );

	try {
		$connect_params = leaky_paywall_get_stripe_connect_params();

		$search = $stripe->customers->search(
			array(
				'query' => 'email:"' . addcslashes( $email, '"\\' ) . '"',
				'limit' => 100,
			),
			$connect_params
		);

		if ( empty( $search->data ) ) {
			return false;
		}

		foreach ( $search->data as $existing_customer ) {
			if ( ! empty( $skip_customer_id ) && $existing_customer->id === $skip_customer_id ) {
				continue;
			}

			$subs = $stripe->subscriptions->all(
				array(
					'customer' => $existing_customer->id,
					'status'   => 'all',
					'limit'    => 100,
				),
				$connect_params
			);

			foreach ( $subs->data as $sub ) {
				if ( ! isset( $sub->created ) || $sub->created < $threshold ) {
					continue;
				}
				if ( in_array( $sub->status, array( 'canceled', 'incomplete_expired' ), true ) ) {
					continue;
				}
				$price_id = isset( $sub->items->data[0]->price->id )
					? $sub->items->data[0]->price->id
					: ( isset( $sub->items->data[0]->plan->id ) ? $sub->items->data[0]->plan->id : '' );
				if ( $price_id === $plan_id ) {
					return true;
				}
			}
		}
	} catch ( \Throwable $e ) {
		leaky_paywall_log( $e->getMessage(), 'duplicate signup check failed' );
		return false;
	}

	return false;
}

/**
 * Add the subscribe link to the subscribe cards.
 *
 * @since 4.0.0
 *
 * @param string  $payment_options Payment options.
 * @param array   $level The level details.
 * @param integer $level_id The level id.
 */
function leaky_paywall_stripe_subscription_cards( $payment_options, $level, $level_id ) {

	if ( 0 == $level['price'] ) {
		return $payment_options;
	}

	$output = '';

	$gateways         = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	if ( in_array( 'stripe', array_keys( $enabled_gateways ) ) ) {
		$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">' . __( 'Subscribe', 'leaky-paywall' ) . '</a></div>';
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
 *
 * @param array   $level The level.
 * @param integer $level_id The level id.
 */
function leaky_paywall_stripe_checkout_button( $level, $level_id ) {

	$results  = '';
	$settings = get_leaky_paywall_settings();
	$currency = apply_filters( 'leaky_paywall_stripe_currency', leaky_paywall_get_currency() );

	// @todo: make this a function so we can use it on the credit card form too.
	if ( in_array( strtoupper( $currency ), array( 'BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF' ) ) ) {
		// Zero-Decimal Currencies.
		// https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support  .
		$stripe_price = number_format( floatval( $level['price'] ), '0', '', '' );
	} else {
		$stripe_price = number_format( floatval( $level['price'] ), '2', '', '' ); // no decimals.
	}
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
	$secret_key      = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];

	if ( ! $secret_key ) {
		return '<p>Please enter Stripe API keys in <a href="' . admin_url() . 'admin.php?page=leaky-paywall-settings&tab=payments">your Leaky Paywall settings</a>.</p>';
	}

	if ( ! empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {

		try {

			$plan_args = array(
				'stripe_price' => $stripe_price,
				'currency'     => $currency,
				'secret_key'   => $secret_key,
			);

			$stripe_plan = leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args );

			$results .= '<form action="' . esc_url( add_query_arg( 'leaky-paywall-confirm', 'stripe_checkout', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
						  <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
						  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
								  data-key="' . esc_js( $publishable_key ) . '"
								  data-locale="auto"
								  data-label="' . apply_filters( 'leaky_paywall_stripe_button_label', __( 'Subscribe', 'leaky-paywall' ) ) . '"
								  data-plan="' . esc_js( $stripe_plan->id ) . '"
								  data-currency="' . esc_js( $currency ) . '"
								  data-description="' . esc_js( $level['label'] ) . '">
						  </script>
						  ' . apply_filters( 'leaky_paywall_pay_with_stripe_recurring_payment_form_after_script', '' ) . '
						</form>';
		} catch ( \Throwable $th ) {

			/* Translators: %s - Error message. */
			$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'leaky-paywall' ), $th->getMessage() ) . '</h1>';
		}
	} else {

		$results .= '<form action="' . esc_url( add_query_arg( 'leaky-paywall-confirm', 'stripe_checkout', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
					  <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
					  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
							  data-key="' . esc_js( $publishable_key ) . '"
							  data-locale="auto"
							  data-label="' . apply_filters( 'leaky_paywall_stripe_button_label', __( 'Subscribe', 'leaky-paywall' ) ) . '"
							  data-amount="' . esc_js( $stripe_price ) . '"
							  data-currency="' . esc_js( $currency ) . '"
							  data-description="' . esc_js( $level['label'] ) . '">
					  </script>
						  ' . apply_filters( 'leaky_paywall_pay_with_stripe_non_recurring_payment_form_after_script', '' ) . '
					</form>';
	}

	$results = '<button class="lp-stripe-checkout-button" data-level-id="' . $level_id . '">Subscribe</button>';

	return '<div class="leaky-paywall-stripe-button leaky-paywall-payment-button">' . $results . '</div>';
}

/**
 * Get stripe public key
 */
function leaky_paywall_get_stripe_public_key() {
	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();

	if ( 'test' === $mode ) {
		$public_key = isset( $settings['test_publishable_key'] ) ? trim( $settings['test_publishable_key'] ) : '';
	} else {
		$public_key = isset( $settings['live_publishable_key'] ) ? trim( $settings['live_publishable_key'] ) : '';
	}

	return $public_key;
}

/**
 * Get stripe secret key
 */
function leaky_paywall_get_stripe_secret_key() {
	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();

	if ( 'test' === $mode ) {
		$secret_key = isset( $settings['test_secret_key'] ) ? trim( $settings['test_secret_key'] ) : '';
	} else {
		$secret_key = isset( $settings['live_secret_key'] ) ? trim( $settings['live_secret_key'] ) : '';
	}

	return $secret_key;
}


add_action( 'wp_ajax_nopriv_leaky_paywall_process_apple_pay', 'leaky_paywall_process_apple_pay' );
add_action( 'wp_ajax_leaky_paywall_process_apple_pay', 'leaky_paywall_process_apple_pay' );

/**
 * Create a stripe payment intent (used with Apple Pay)
 */
function leaky_paywall_process_apple_pay() {

	if (
		! isset( $_POST['register_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['register_nonce'] ) ), 'lp_register_nonce' )
	) {
		wp_send_json(
			array(
				'error' => __( 'There was an error. Please try again.', 'leaky-paywall' )
			)
		);
	}

	$level_id = isset( $_POST['level_id'] ) ? sanitize_text_field( wp_unslash( $_POST['level_id'] ) ) : '';
	$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	$cus_id = isset($_POST['cusId']) ? sanitize_text_field(wp_unslash($_POST['cusId'])) : '';
	$level    = get_leaky_paywall_subscription_level( $level_id );

	$stripe = leaky_paywall_initialize_stripe_api();

	// one time
	$data = apply_filters('leaky_paywall_stripe_payment_intent_args', array(
		'amount'   => leaky_paywall_get_stripe_amount($level['price']),
		'currency' => strtolower(leaky_paywall_get_currency()),
		'receipt_email' => $email,
		'setup_future_usage' => 'off_session',
	));

	if ( $cus_id ) {
		$data['customer'] = $cus_id;
	}

	try {
		$payment_intent = $stripe->paymentIntents->create(
			$data
		);

	} catch (\Throwable $th) {
		leaky_paywall_log('error', 'stripe payment intent');

		wp_send_json(
			array(
				'error' => $th->getMessage(),
			)
		);
	}

	wp_send_json( array( 'clientSecret' => $payment_intent->client_secret ) );
}


add_action( 'wp_ajax_nopriv_leaky_paywall_create_stripe_checkout_subscription', 'leaky_paywall_create_stripe_checkout_subscription' );
add_action( 'wp_ajax_leaky_paywall_create_stripe_checkout_subscription', 'leaky_paywall_create_stripe_checkout_subscription' );

/**
 * Create a stripe subscription invoice
 */
function leaky_paywall_create_stripe_checkout_subscription() {

	$level_id          = isset( $_POST['level_id'] ) ? sanitize_text_field( wp_unslash( $_POST['level_id'] ) ) : '';
	$level             = get_leaky_paywall_subscription_level( $level_id );
	$customer_id       = isset( $_POST['customerId'] ) ? sanitize_text_field( wp_unslash( $_POST['customerId'] ) ) : '';
	$payment_method_id = isset( $_POST['paymentMethodId'] ) ? sanitize_text_field( wp_unslash( $_POST['paymentMethodId'] ) ) : '';
	$plan_id           = isset( $_POST['planId'] ) ? sanitize_text_field( wp_unslash( $_POST['planId'] ) ) : '';
	$form_data         = isset( $_POST['formData'] ) ? htmlspecialchars_decode( wp_kses_post( wp_unslash( $_POST['formData'] ) ) ) : '';
	parse_str( $form_data, $fields );

	if (
		! isset( $fields['leaky_paywall_register_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $fields['leaky_paywall_register_nonce'] ), 'leaky-paywall-register-nonce' )
	) {
		wp_send_json(
			array(
				'error' => __( 'There was an error. Please try again.', 'leaky-paywall' )
			)
		);
	}

	// Prevent duplicate subscriptions: if the logged-in user already has an active
	// subscription at this level, stop before creating a new Stripe subscription.
	if ( is_user_logged_in() && leaky_paywall_user_has_active_subscription_at_level( null, $level_id ) ) {
		wp_send_json(
			array(
				'error' => __( 'You are already subscribed to this level.', 'leaky-paywall' ),
			)
		);
	}

	$stripe = leaky_paywall_initialize_stripe_api();

	// Prevent duplicate signups across Stripe customers (e.g., guest retries on ACH).
	$dedup_email = isset( $fields['email_address'] ) ? sanitize_email( $fields['email_address'] ) : '';
	if ( leaky_paywall_check_recent_duplicate_signup( $dedup_email, $plan_id, $customer_id, $stripe ) ) {
		wp_send_json(
			array(
				'error' => apply_filters(
					'leaky_paywall_duplicate_signup_message',
					__( 'It looks like a signup for this email was started a few minutes ago. Please check your inbox for confirmation, or contact us if you need help.', 'leaky-paywall' )
				),
			)
		);
	}

	try {
		$payment_method = $stripe->paymentMethods->retrieve( $payment_method_id, [], leaky_paywall_get_stripe_connect_params() );
		$payment_method->attach( array( 'customer' => $customer_id ) );

		$customer = $stripe->customers->retrieve( $customer_id, [], leaky_paywall_get_stripe_connect_params() );
		$customer->invoice_settings->default_payment_method = $payment_method_id;
		$customer->save();
	} catch ( \Throwable $th ) {

		leaky_paywall_log( 'error 1', 'stripe checkout subscription' );

		wp_send_json(
			array(
				'error' => $th->getMessage(),
			)
		);
	}

	do_action( 'leaky_paywall_after_create_recurring_customer', $customer );

	$subscription_array = array(
		'customer' => $customer_id,
		'items'    => array(
			array(
				'price' => $plan_id,
			),
		),
		'expand'   => array( 'latest_invoice.payment_intent' ),
	);

	// $subscription_params = apply_filters( 'leaky_paywall_stripe_subscription_params', [], $level, $fields );

	try {
		leaky_paywall_log( 'before get subs', 'stripe checkout subscription for ' . $customer_id );
		leaky_paywall_log( $customer, 'stripe checkout subscription for ' . $customer_id );
		$subscriptions = $stripe->subscriptions->all( array( 'limit' => '1', 'customer' => $customer_id ), leaky_paywall_get_stripe_connect_params() );
		leaky_paywall_log( 'after get subs', 'stripe checkout subscription for ' . $customer_id );

		if ( empty( $subscriptions->data ) ) {
			leaky_paywall_log( 'empty sub data', 'stripe checkout subscription for ' . $customer_id );
			$subscription = $stripe->subscriptions->create( apply_filters( 'leaky_paywall_stripe_subscription_args', $subscription_array, $level, $fields ), leaky_paywall_get_stripe_connect_params() );
		} else {

			foreach ( $subscriptions->data as $subscription ) {

				$sub = $stripe->subscriptions->update( $subscription->id, array(
					'items' => array(
						array(
							'id'    => $subscription->items->data[0]->id,
							'price' => $plan_id,
						),
					),
				) );

				do_action( 'leaky_paywall_after_update_stripe_subscription', $customer, $sub, $level );
			}
		}
	} catch ( \Stripe\Exception\ApiErrorException $e ) {
		leaky_paywall_log( 'error 2', 'stripe checkout subscription' );
		leaky_paywall_log( $form_data, 'stripe checkout subscription form data error 2' );
		wp_send_json(
			array(
				'error' => $e->getMessage(),
			)
		);
	}

	$return = array(
		'subscription' => $subscription,
	);

	wp_send_json( $return );
}

function leaky_paywall_create_stripe_subscription( $cu, $fields ) {

	$level_id          = $fields['level_id'];
	$level             = get_leaky_paywall_subscription_level($level_id);
	$customer_id       = $cu->id;
	$plan_id           = $fields['plan_id'];

	$stripe = leaky_paywall_initialize_stripe_api();

	do_action( 'leaky_paywall_before_create_stripe_subscription', $cu, $fields );

	$subscription_array = array(
		'customer' => $customer_id,
		'items'    => array(
			array(
				'price' => $plan_id,
			),
		),
		'payment_behavior' => 'default_incomplete',
		'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
		'expand' => ['latest_invoice.payment_intent'],
	);

	try {
		leaky_paywall_log('before get subs', 'stripe subscription for ' . $customer_id);
		leaky_paywall_log($cu, 'stripe subscription for ' . $customer_id);
		$subscriptions = $stripe->subscriptions->all(array('limit' => '1', 'customer' => $customer_id), leaky_paywall_get_stripe_connect_params());
		leaky_paywall_log('after get subs', 'stripe subscription for ' . $customer_id);

		if (empty($subscriptions->data)) {
			leaky_paywall_log('empty sub data', 'stripe subscription for ' . $customer_id);
			$subscription = $stripe->subscriptions->create(apply_filters('leaky_paywall_stripe_subscription_args', $subscription_array, $level, $fields), leaky_paywall_get_stripe_connect_params() );
		} else {
			// Update existing subscription to new plan with immediate proration.
			foreach ( $subscriptions->data as $existing_sub ) {
				$update_args = apply_filters( 'leaky_paywall_before_update_stripe_subscription_args', array(
					'items' => array(
						array(
							'id'    => $existing_sub->items->data[0]->id,
							'price' => $plan_id,
						),
					),
					'proration_behavior' => 'always_invoice',
				), $level );

				// If the subscription was set to cancel at period end (pending_cancel),
				// clear that flag so the plan switch doesn't inherit the cancellation intent.
				if ( ! empty( $existing_sub->cancel_at_period_end ) ) {
					$update_args['cancel_at_period_end'] = false;

					$switching_user = get_user_by( 'email', $fields['email_address'] );
					if ( $switching_user ) {
						leaky_paywall_set_subscriber_status( $switching_user->ID, 'active', 'plan_switch' );
					}
				}

				$update_args['expand'] = array( 'latest_invoice' );

				$sub = $stripe->subscriptions->update(
					$existing_sub->id,
					$update_args,
					leaky_paywall_get_stripe_connect_params()
				);

				// Store the actual amount Stripe charged on the incomplete user.
				if ( isset( $sub->latest_invoice->amount_paid ) ) {
					$actual_price = $sub->latest_invoice->amount_paid / 100;
					$incomplete_id = leaky_paywall_get_incomplete_user_from_email( $fields['email_address'] );
					if ( $incomplete_id ) {
						update_post_meta( $incomplete_id, '_stripe_amount_paid', number_format( $actual_price, 2, '.', '' ) );
					}
				}

				do_action( 'leaky_paywall_after_update_stripe_subscription', $cu, $sub, $level );
			}
			return 'subscription_updated';
		}
	} catch (\Throwable $th) {
		leaky_paywall_log($th->getMessage(), 'stripe subscription - error 2');
		leaky_paywall_log($fields, 'stripe subscription form data error 2');
		return false;
	}

	if ( isset($subscription->latest_invoice->payment_intent->client_secret)) {
		return $subscription->latest_invoice->payment_intent->client_secret;
	}

	if (isset($subscription->pending_setup_intent->client_secret)) {
		return $subscription->pending_setup_intent->client_secret;
	}

	if ( isset( $subscription->pending_setup_intent)) {
		// get setup intent client secret
		$intent = $stripe->setupIntents->retrieve( $subscription->pending_setup_intent, [], leaky_paywall_get_stripe_connect_params() );
		return $intent->client_secret;
	}

}


/**
 * Get Stripe Plan
 *
 * Gets the stripe plan associated with the level, and creates one if it doesn't exist
 *
 * @since       4.0.0
 * @param       array $level - the Leaky Paywall level.
 * @param       int   $level_id - the Leaky Paywall level id.
 * @param       array $plan_args - the arguements for the plan.
 * @return      obj - Stripe Plan object
 */
function leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args ) {

	$settings    = get_leaky_paywall_settings();
	$stripe_plan = false;
	$match       = false;

	if ( ! isset( $level['plan_id'] ) ) {
		$level['plan_id'] = array();
	}

	$stripe = leaky_paywall_initialize_stripe_api();

	if ( ! is_array( $level['plan_id'] ) ) {
		$plan_temp                                  = $level['plan_id'];
		$settings['levels'][ $level_id ]['plan_id'] = array( $plan_temp );
		update_leaky_paywall_settings( $settings );
	}

	if ( ! empty( $level['plan_id'] ) ) {

		$reversed_plans = array_reverse( $level['plan_id'] );

		foreach ( $reversed_plans as $plan_id ) {

			if ( !$plan_id ) {
				continue; // fixes null or whitespace error
			}

			// We need to verify that the plan_id matches the level details, otherwise we need to update it.
			try {
			//	$plan_params = apply_filters('leaky_paywall_stripe_plan_params', [], $level, $plan_args);
				$stripe_plan = $stripe->plans->retrieve($plan_id, [], leaky_paywall_get_stripe_connect_params() );
			} catch ( \Throwable $th ) {
				leaky_paywall_log($th->getMessage(), 'lp - error retrieving stripe plan for ' . $plan_id);
				$stripe_plan = false;
			}

			if (
				! is_object( $stripe_plan ) || // If we don't have a stripe plan.
				( // or the stripe plan doesn't match...
					$plan_args['stripe_price'] != $stripe_plan->amount
					|| $level['interval'] != $stripe_plan->interval
					|| $level['interval_count'] != $stripe_plan->interval_count )
			) {
				// does not match.
			} else {
				$match = $stripe_plan; // this plan matches, so send it back.
				break;
			}
		}
	}

	if ( ! $match ) {
		$stripe_plan = leaky_paywall_create_stripe_plan( $level, $level_id, $plan_args );

		$settings['levels'][ $level_id ]['plan_id'][] = is_object( $stripe_plan ) ? $stripe_plan->id : false;
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
 *
 * @param array   $level The level.
 * @param integer $level_id The level id.
 * @param array   $plan_args The plan details.
 */
function leaky_paywall_create_stripe_plan( $level, $level_id, $plan_args ) {

	$stripe = leaky_paywall_initialize_stripe_api();

	$time = time();

	$args = array(
		'amount'         => esc_js( $plan_args['stripe_price'] ),
		'interval'       => esc_js( $level['interval'] ),
		'interval_count' => esc_js( $level['interval_count'] ),
		'name'           => esc_js( leaky_paywall_normalize_chars( $level['label'] ) ) . ' ' . $time,
		'currency'       => esc_js( $plan_args['currency'] ),
		'id'             => sanitize_title_with_dashes( leaky_paywall_normalize_chars( $level['label'] ) ) . '-' . $time,
	);

	// $plan_params = apply_filters( 'leaky_paywall_stripe_plan_params', [], $level, $plan_args );

	try {
		$stripe_plan = $stripe->plans->create( apply_filters( 'leaky_paywall_create_stripe_plan', $args, $level, $level_id ), leaky_paywall_get_stripe_connect_params() );
		leaky_paywall_log( $args, 'lp create stripe plan success' );
	} catch ( \Throwable $th ) {
		leaky_paywall_log( $args, 'lp create stripe plan error' );
		leaky_paywall_log( $th->getMessage(), 'lp create stripe plan error' );
		$stripe_plan = false;
	}

	return $stripe_plan;
}

/**
 * Check if the status of a subscription is valid
 *
 * @since 4.10.3
 *
 * @param object $subscription The subscription object.
 */
function leaky_paywall_is_valid_stripe_subscription( $subscription ) {

	$valid_status = apply_filters( 'leaky_paywall_valid_stripe_subscription_status', array( 'active' ) );

	if ( in_array( $subscription->status, $valid_status ) ) {
		return true;
	}

	return false;
}

/**
 * Initialize a call to the Stripe API with Leaky Paywall App Info
 *
 * @since 4.15.4
 */
function leaky_paywall_initialize_stripe_api() {

	$secret_key = leaky_paywall_get_stripe_secret_key();

	if ( !$secret_key ) {
		return false;
	}

	$stripe = new \Stripe\StripeClient(leaky_paywall_get_stripe_secret_key());

	\Stripe\Stripe::setApiKey( leaky_paywall_get_stripe_secret_key() );
	\Stripe\Stripe::setApiVersion( LEAKY_PAYWALL_STRIPE_API_VERSION );
	\Stripe\Stripe::setAppInfo(
		'WordPress Leaky Paywall',
		LEAKY_PAYWALL_VERSION,
		esc_url( site_url() ),
		LEAKY_PAYWALL_STRIPE_PARTNER_ID
	);

	return $stripe;
}

/**
 * Get a stripe formatted price
 *
 * @since 4.16.2
 *
 * @param string $amount The amount.
 */
function leaky_paywall_get_stripe_amount( $amount ) {

	if ( in_array( strtoupper( leaky_paywall_get_currency() ), array( 'BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF' ) ) ) {
		// Zero-Decimal Currencies.
		// https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support .
		$stripe_price = number_format( (float) $amount, '0', '', '' );
	} else {
		$stripe_price = number_format( (float) $amount, '2', '', '' ); // no decimals.
	}

	return $stripe_price;
}


function leaky_paywall_sync_stripe_subscription( $user ) {

	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();
	$subscriber_id = lp_get_subscriber_meta('subscriber_id', $user);

	if (!$subscriber_id) {
		return;
	}

	$stripe = leaky_paywall_initialize_stripe_api();

	try {
		$cus = $stripe->customers->retrieve( $subscriber_id, [], leaky_paywall_get_stripe_connect_params() );

		if ( !is_object( $cus ) ) {
			return;
		}

		// Prefer the meaningful subscription over "the most recently created."
		// A customer with cancel-and-resubscribe history can have a canceled
		// subscription created after their current active one — defaulting to
		// status=all + limit=1 was returning that stale canceled sub and flipping
		// the user to expired even though they're paying right now. Try active,
		// then trialing; only fall back to status=all if nothing's live.
		$subscriptions = null;
		foreach ( array( 'active', 'trialing', 'all' ) as $status_filter ) {
			$subscriptions = $stripe->subscriptions->all( array(
				'customer' => $cus->id,
				'limit'    => '1',
				'status'   => $status_filter,
			), leaky_paywall_get_stripe_connect_params() );

			if ( ! empty( $subscriptions->data ) ) {
				break;
			}
		}

		if ( empty( $subscriptions->data ) ) {
			return;
		}

		foreach ($subscriptions->data as $subscription) {

			$current_period_end = $subscription->current_period_end;
			$plan = isset( $subscription->plan->id ) ? $subscription->plan->id : '';

			// Only advance the expiration date for subscription statuses that
			// indicate a valid, paid billing cycle. Stripe advances
			// current_period_end when a renewal invoice is generated, even if
			// the charge fails — so past_due/unpaid subscriptions would otherwise
			// get their expiration pushed forward without a successful payment.
			$is_payment_valid = in_array( $subscription->status, array( 'active', 'trialing' ), true );

			if ( $current_period_end && $is_payment_valid ) {
				$expires = date_i18n('Y-m-d 23:59:59', $current_period_end);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires);
			}

			if ( $plan ) {
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, $plan);
			}

			if ( $subscription->status == 'active' && ! empty( $subscription->cancel_at_period_end ) ) {
				leaky_paywall_set_subscriber_status( $user->ID, 'pending_cancel', 'stripe_sync' );
			} elseif ( $subscription->status == 'active' ) {
				leaky_paywall_set_subscriber_status( $user->ID, 'active', 'stripe_sync' );
			} elseif ( $subscription->status == 'trialing' ) {
				leaky_paywall_set_subscriber_status( $user->ID, 'trial', 'stripe_sync' );
			} elseif ( $subscription->status == 'canceled' ) {
				// Distinguish voluntary "cancel at period end" from involuntary
				// cancellations (failed payment, admin cancel, fraud). Stripe leaves
				// current_period_end set to the NEXT billing cycle even when a renewal
				// payment fails — trusting that date for involuntary cancels gave
				// subscribers up to a year of free access. Only respect
				// current_period_end as a grace window when the cancel was the
				// subscriber's own choice.
				$voluntary = ! empty( $subscription->cancel_at_period_end );
				if ( ! $voluntary && isset( $subscription->cancellation_details->reason ) ) {
					$voluntary = ( 'cancellation_requested' === $subscription->cancellation_details->reason );
				}

				$expires_key = '_issuem_leaky_paywall_' . $mode . '_expires' . $site;

				if ( $voluntary && $current_period_end && $current_period_end > time() ) {
					leaky_paywall_set_subscriber_status( $user->ID, 'pending_cancel', 'stripe_sync' );
					update_user_meta( $user->ID, $expires_key, date_i18n( 'Y-m-d 23:59:59', $current_period_end ) );
				} else {
					leaky_paywall_set_subscriber_status( $user->ID, 'expired', 'stripe_sync' );
					// Clamp expires to NOW so a stale future date from an unpaid
					// renewal period doesn't keep granting access.
					$current_expires    = get_user_meta( $user->ID, $expires_key, true );
					$current_expires_ts = $current_expires ? strtotime( $current_expires ) : 0;
					if ( ! $current_expires_ts || $current_expires_ts > time() ) {
						update_user_meta( $user->ID, $expires_key, date_i18n( 'Y-m-d 23:59:59', time() ) );
					}
				}
			} elseif ( 'past_due' === $subscription->status ) {
				leaky_paywall_set_subscriber_status( $user->ID, 'past_due', 'stripe_sync' );
			} elseif ( in_array( $subscription->status, array( 'incomplete_expired', 'unpaid' ), true ) ) {
				leaky_paywall_set_subscriber_status( $user->ID, 'deactivated', 'stripe_sync' );
			}
		}

	} catch (\Throwable $th) {
		leaky_paywall_log($th->getMessage(), 'leaky paywall - stripe sync error');
		return;
	}
}


add_action( 'leaky_paywall_before_process_stripe_webhook', 'leaky_paywall_process_stripe_checkout_webhook' );
/**
* Process a Stripe Checkout successful webhook
*
* @since 4.18.0
*
* @param object stripe_event
*/
function leaky_paywall_process_stripe_checkout_webhook( $stripe_event ) {

	if ($stripe_event->type != 'checkout.session.completed') {
		return;
	}

	$stripe_object = $stripe_event->data->object;

	leaky_paywall_log( $stripe_object->customer, 'stripe checkout event customer' );

	// Dedup by checkout session ID. cs_xxx IDs are unique per checkout in
	// Stripe, so a prior transaction stamped with this session is a duplicate
	// webhook delivery (Stripe retries) or a re-submission of the same flow.
	$existing_transaction = get_posts( array(
		'post_type'      => 'lp_transaction',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array( 'key' => '_gateway_txn_id', 'value' => $stripe_object->id ),
		),
	) );

	if ( ! empty( $existing_transaction ) ) {
		leaky_paywall_log( $stripe_object->id, 'stripe checkout - transaction already exists for session, skipping' );
		return;
	}

	$incomplete_id = leaky_paywall_get_incomplete_user_from_email($stripe_object->customer_details->email);

	if (!$incomplete_id) {
		leaky_paywall_log($stripe_object->customer, 'stripe checkout event no incomplete found');
		return;
	}

	// leaky_paywall_create_subscriber_from_incomplete_user( $email );


	$user_data = get_post_meta($incomplete_id, '_user_data', true);
	$field_data = get_post_meta($incomplete_id, '_field_data', true);

	// Clean up incomplete user immediately to prevent the redirect handler from racing.
	leaky_paywall_cleanup_incomplete_user($user_data['email']);

	$user = get_user_by('email', $user_data['email']);
	$level = get_leaky_paywall_subscription_level($user_data['level_id']);
	$plan_id = '';

	if ($user) {
		$existing_customer = true;
		$status = 'update';
	} else {
		$existing_customer = false;
		$status = 'new';
	}

	if (isset($level['recurring']) && 'on' == $level['recurring']) {

		$plan_args = array(
			'stripe_price'	=> $stripe_object->amount_total,
			'currency'		=> leaky_paywall_get_currency(),
			'secret_key'	=> leaky_paywall_get_stripe_secret_key()
		);

		$stripe_plan = leaky_paywall_get_stripe_plan($level, $user_data['level_id'], $plan_args);

		if ($stripe_plan) {
			$plan_id = $stripe_plan->id;
		}

	}

	$subscriber_data = array(
		'email' => $user_data['email'],
		'password' => isset( $user_data['password'] ) ? $user_data['password'] : '',
		'first_name'	=> $user_data['first_name'],
		'last_name'	=> $user_data['last_name'],
		'level_id'	=> $user_data['level_id'],
		'description' => $level['label'],
		'subscriber_id'	=> $stripe_object->customer,
		'payment_gateway_txn_id' => $stripe_object->id,
		'created'	=> gmdate('Y-m-d H:i:s'),
		'price'	=> $stripe_object->amount_total / 100,
		'plan'	=> $plan_id,
		'interval_count' => $level['interval_count'],
		'interval'	=> $level['interval'],
		'recurring'	=> false,
		'currency' => leaky_paywall_get_currency(),
		'new_user'	=> true,
		'payment_gateway'	=> 'stripe',
		'payment_status'	=> 'active',
		'site'	=> leaky_paywall_get_current_site(),
		'mode' => leaky_paywall_get_current_mode(),
	);

	if ( $existing_customer ) {
		$subscriber_data['need_new'] = false;
	} else {
		$subscriber_data['need_new'] = true;
	}

	if (apply_filters('leaky_paywall_use_alternative_subscriber_registration', false, $subscriber_data, $level)) {
		do_action('leaky_paywall_alternative_subscriber_registration', $subscriber_data, $level);
	} else {

		if ($existing_customer) {
			$user_id = leaky_paywall_update_subscriber(NULL, $user_data['email'], $stripe_object->customer, $subscriber_data);
		} else {
			$user_id = leaky_paywall_new_subscriber(NULL, $user_data['email'], $stripe_object->customer, $subscriber_data);
		}

		$subscriber_data['user_id'] = $user_id;

		$transaction = new LP_Transaction($subscriber_data);
		$transaction_id = $transaction->create();
		$subscriber_data['transaction_id'] = $transaction_id;

		update_post_meta( $transaction_id, '_field_data', $field_data );

		if (isset($field_data['lp_nag_loc'])) {
			update_post_meta($transaction_id, '_nag_location_id', $field_data['lp_nag_loc']);
		}

		do_action('leaky_paywall_after_stripe_checkout_completed', $subscriber_data);

		// Send email notifications
		leaky_paywall_email_subscription_status($user_id, $status, $subscriber_data);

	}

}

add_action( 'leaky_paywall_before_process_stripe_webhook', 'leaky_paywall_process_stripe_subscription_payment_element_webhook' );

/**
 * Webhook fallback for the Payment Element flow.
 *
 * Catches payment_intent.succeeded events when the browser-side redirect
 * handler didn't run — typically because the user paid with Stripe Link
 * (or another inline-completing method) and the JS confirmation callback
 * never reached the form.submit() that finalizes registration server-side,
 * or because the user closed the tab before being redirected back.
 */
function leaky_paywall_process_stripe_subscription_payment_element_webhook( $stripe_event ) {

	if ( 'payment_intent.succeeded' !== $stripe_event->type ) {
		return;
	}

	$pi     = $stripe_event->data->object;
	$stripe = leaky_paywall_initialize_stripe_api();

	// Give the browser's form POST a head start before finalizing here. With
	// Stripe Link the payment confirms inline and this webhook can arrive ~1s
	// before the browser submits the registration form. Without this delay the
	// data-poor webhook path wins the race (no $_POST = no shipping address or
	// form-driven custom fields), deletes the incomplete-user record, and the
	// browser POST then errors out in process_signup(). Letting the browser
	// finalize first means it captures the full form data, and finalize() below
	// then bails on its existing-transaction / incomplete-user checks. Mirrors
	// the sleep already used in the charge.succeeded / invoice.paid path.
	//
	// Skip the wait when this PaymentIntent has already been finalized (browser
	// path already done, or a webhook retry) so legitimate fallbacks and retries
	// aren't delayed.
	$already_finalized = get_posts( array(
		'post_type'      => 'lp_transaction',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'post_status'    => 'publish',
		'meta_query'     => array(
			array( 'key' => '_gateway_txn_id', 'value' => $pi->id ),
		),
	) );

	if ( empty( $already_finalized ) ) {
		$delay = (int) apply_filters( 'leaky_paywall_stripe_webhook_finalize_delay', 5, $pi );
		if ( $delay > 0 ) {
			sleep( $delay );
		}
	}

	leaky_paywall_finalize_subscription_from_payment_intent( $pi, $stripe, 'webhook' );
}

/**
 * Finalize a subscription from a successful Stripe Payment Intent.
 *
 * Shared by the browser redirect handler (leaky_paywall_maybe_process_payment_intent_redirect_url)
 * and the payment_intent.succeeded webhook handler. Either path can win the
 * race; the 60-second dedup at the top stops the loser from creating a
 * second transaction.
 *
 * Does NOT log the user in or redirect — the redirect handler does both
 * after this returns; the webhook handler does neither (no browser context).
 *
 * @param object $pi     Stripe PaymentIntent object (status must be 'succeeded').
 * @param object $stripe Initialized Stripe client.
 * @param string $source 'redirect' or 'webhook' — for logging only.
 * @return array|null    Subscriber + transaction info on success, null if the function bailed.
 */
function leaky_paywall_finalize_subscription_from_payment_intent( $pi, $stripe, $source ) {

	if ( ! isset( $pi->status ) || 'succeeded' !== $pi->status ) {
		return null;
	}

	if ( empty( $pi->customer ) ) {
		return null;
	}

	// Bail if this PaymentIntent is for a subscription renewal. This function
	// runs initial-signup logic; if it fires for a renewal, an orphan
	// lp_incomplete_user record for the subscriber's email causes every
	// renewal to create a duplicate "Initial Subscription Payment" transaction
	// and send a new-subscriber admin email. Renewal PIs always have an
	// associated invoice with billing_reason=subscription_cycle.
	if ( ! empty( $pi->invoice ) ) {
		try {
			$invoice = $stripe->invoices->retrieve( $pi->invoice, [], leaky_paywall_get_stripe_connect_params() );
			if ( isset( $invoice->billing_reason ) && 'subscription_cycle' === $invoice->billing_reason ) {
				leaky_paywall_log( $pi->customer, "stripe {$source} - PI is a subscription_cycle renewal, skipping initial-signup finalize" );
				return null;
			}
		} catch ( \Throwable $th ) {
			leaky_paywall_log( $th->getMessage(), "stripe {$source} - could not retrieve invoice for renewal gate" );
		}
	}

	// Dedup by PaymentIntent ID. PI IDs are unique forever in Stripe, so any
	// prior transaction stamped with this PI is a duplicate finalize attempt
	// regardless of how much time has passed. Catches:
	//   - browser redirect + payment_intent.succeeded webhook racing
	//   - user re-submitting the form (same customer, same PI re-confirmed)
	//   - user reloading or back-buttoning the redirect URL
	$existing_transaction = get_posts( array(
		'post_type'      => 'lp_transaction',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array( 'key' => '_gateway_txn_id', 'value' => $pi->id ),
		),
	) );

	if ( ! empty( $existing_transaction ) ) {
		leaky_paywall_log( $pi->id, "stripe {$source} - transaction already exists for PI, skipping" );
		return null;
	}

	try {
		$cu = $stripe->customers->retrieve( $pi->customer, [], leaky_paywall_get_stripe_connect_params() );
	} catch ( \Throwable $th ) {
		leaky_paywall_log( $th->getMessage(), "lp error - retrieving customer in {$source} flow" );
		return null;
	}

	if ( ! isset( $cu->email ) ) {
		return null;
	}

	$incomplete_id = leaky_paywall_get_incomplete_user_from_email( $cu->email );

	if ( ! $incomplete_id ) {
		leaky_paywall_log( $pi->customer, "stripe {$source} - no incomplete user found for {$cu->email}" );
		return null;
	}

	$user_data  = get_post_meta( $incomplete_id, '_user_data', true );
	$field_data = get_post_meta( $incomplete_id, '_field_data', true );

	if ( empty( $user_data['email'] ) ) {
		leaky_paywall_log( $pi->customer, "stripe {$source} - incomplete user has no email" );
		return null;
	}

	// Single atomic claim, keyed on the PaymentIntent and shared with the
	// charge.succeeded / invoice.paid webhook path, so only one finalize runs
	// per payment regardless of which path wins the race.
	if ( ! leaky_paywall_claim_registration( $pi->id ) ) {
		leaky_paywall_log( $pi->id, "stripe {$source} - registration already claimed, skipping" );
		return null;
	}

	// Clean up incomplete user immediately so the other handler bails on lookup
	// if it races in here. Belt-and-suspenders with the dedup above.
	leaky_paywall_cleanup_incomplete_user( $cu->email );

	$user    = get_user_by( 'email', $user_data['email'] );
	$level   = get_leaky_paywall_subscription_level( $user_data['level_id'] );
	$plan_id = '';

	$existing_customer = (bool) $user;
	$status            = $existing_customer ? 'update' : 'new';

	if ( isset( $level['recurring'] ) && 'on' === $level['recurring'] ) {
		try {
			$subscriptions = $stripe->subscriptions->all(
				array( 'customer' => $cu->id, 'limit' => '1' ),
				leaky_paywall_get_stripe_connect_params()
			);
			foreach ( $subscriptions->data as $subscription ) {
				$plan_id = $subscription->plan->id;
			}
		} catch ( \Throwable $th ) {
			// Non-fatal — proceed without plan_id.
		}
	}

	$subscriber_data = array(
		'email'                  => $user_data['email'],
		'password'               => isset( $user_data['password'] ) ? $user_data['password'] : '',
		'first_name'             => isset( $user_data['first_name'] ) ? $user_data['first_name'] : '',
		'last_name'              => isset( $user_data['last_name'] ) ? $user_data['last_name'] : '',
		'level_id'               => $user_data['level_id'],
		'description'            => isset( $level['label'] ) ? $level['label'] : '',
		'subscriber_id'          => $pi->customer,
		'payment_gateway_txn_id' => $pi->id,
		'created'                => gmdate( 'Y-m-d H:i:s' ),
		'price'                  => $pi->amount / 100,
		'plan'                   => $plan_id,
		'interval_count'         => isset( $level['interval_count'] ) ? $level['interval_count'] : 0,
		'interval'               => isset( $level['interval'] ) ? $level['interval'] : '',
		'recurring'              => false,
		'currency'               => leaky_paywall_get_currency(),
		'new_user'               => true,
		'payment_gateway'        => 'stripe',
		'payment_status'         => 'active',
		'site'                   => leaky_paywall_get_current_site(),
		'mode'                   => leaky_paywall_get_current_mode(),
		'need_new'               => ! $existing_customer,
	);

	if ( $existing_customer ) {
		$user_id = leaky_paywall_update_subscriber( null, $user_data['email'], $pi->customer, $subscriber_data );
	} else {
		$user_id = leaky_paywall_new_subscriber( null, $user_data['email'], $pi->customer, $subscriber_data );
	}

	if ( ! $user_id ) {
		leaky_paywall_log( $pi->customer, "stripe {$source} - failed to create/update WP user" );
		return null;
	}

	$subscriber_data['user_id'] = $user_id;

	$transaction    = new LP_Transaction( $subscriber_data );
	$transaction_id = $transaction->create();

	if ( ! $transaction_id ) {
		leaky_paywall_log( $pi->customer, "stripe {$source} - failed to create LP transaction" );
		return null;
	}

	$subscriber_data['transaction_id'] = $transaction_id;

	update_post_meta( $transaction_id, '_field_data', $field_data );

	if ( isset( $field_data['lp_nag_loc'] ) ) {
		update_post_meta( $transaction_id, '_nag_location_id', $field_data['lp_nag_loc'] );
	}

	leaky_paywall_email_subscription_status( $user_id, $status, $subscriber_data );

	do_action( 'leaky_paywall_after_process_registration', $subscriber_data );

	leaky_paywall_log( $pi->customer, "stripe {$source} - subscription finalized for {$user_data['email']}" );

	return array(
		'user_id'         => $user_id,
		'subscriber_data' => $subscriber_data,
		'field_data'      => $field_data,
		'status'          => $status,
		'transaction_id'  => $transaction_id,
	);
}

add_action( 'init', 'leaky_paywall_maybe_process_payment_intent_redirect_url' );

function leaky_paywall_maybe_process_payment_intent_redirect_url() {

	if ( ! isset( $_GET['payment_intent'] ) ) {
		return;
	}

	$settings = get_leaky_paywall_settings();
	$pi_id    = sanitize_text_field( wp_unslash( $_GET['payment_intent'] ) );
	$stripe   = leaky_paywall_initialize_stripe_api();

	try {
		$pi = $stripe->paymentIntents->retrieve( $pi_id, [], leaky_paywall_get_stripe_connect_params() );
	} catch ( \Throwable $th ) {
		leaky_paywall_log( $th->getMessage(), 'lp error - retrieving payment intent from redirect url' );
		return;
	}

	$result = leaky_paywall_finalize_subscription_from_payment_intent( $pi, $stripe, 'redirect' );

	if ( ! $result ) {
		return;
	}

	// Browser-only tail: log the user in, clear paywall state, redirect them.
	$user_id         = $result['user_id'];
	$subscriber_data = $result['subscriber_data'];
	$transaction_id  = $result['transaction_id'];

	leaky_paywall_log_in_user( $user_id );

	$restrictions = new Leaky_Paywall_Restrictions();
	$restrictions->clear_cookie();

	if ( isset( $_COOKIE['lp_nag_loc'] ) ) {
		update_post_meta( $transaction_id, '_nag_location_id', absint( $_COOKIE['lp_nag_loc'] ) );
	}

	wp_safe_redirect( leaky_paywall_get_redirect_url( $settings, $subscriber_data ) );
	exit;
}

function leaky_paywall_get_stripe_checkout_success_url() {

    $settings = get_leaky_paywall_settings();

	if ( ! empty( $settings['page_for_after_subscribe'] ) ) {
		$redirect_url = get_page_link( $settings['page_for_after_subscribe'] );
	} elseif ( ! empty( $settings['page_for_profile'] ) ) {
		$redirect_url = get_page_link( $settings['page_for_profile'] );
	} elseif ( ! empty( $settings['page_for_login'] ) ) {
		$redirect_url = get_page_link( $settings['page_for_login'] );
	} else {
		$redirect_url = home_url();
	}

	return apply_filters( 'leaky_paywall_stripe_checkout_success_url', $redirect_url );

}


add_action( 'init', 'leaky_paywall_maybe_generate_stripe_customer_portal' );

function leaky_paywall_maybe_generate_stripe_customer_portal() {

	if (

		! isset( $_POST['stripe_customer_portal_field'] )

		|| ! wp_verify_nonce( sanitize_key( $_POST['stripe_customer_portal_field'] ), 'stripe_customer_portal_submit' )

	) {

		return;

	}

	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();
	$settings = get_leaky_paywall_settings();
	$subscriber_id    = get_user_meta( get_current_user_id(), '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );

	if ( !$subscriber_id ) {
		return;
	}

	$stripe = leaky_paywall_initialize_stripe_api();

	try {
		$session = $stripe->billingPortal->sessions->create([
			'customer' => $subscriber_id,
			'return_url' => get_page_link( $settings['page_for_profile'] ),
		], leaky_paywall_get_stripe_connect_params());
	} catch (\Throwable $th) {
		leaky_paywall_log($th->getMessage(), 'lp error - generate stripe customer portal session');
	}

	// Redirect to the customer portal.
	header("Location: " . $session->url);
	exit();

}

add_action('admin_init', 'leaky_paywall_connect_maybe_process_refresh');

function leaky_paywall_connect_maybe_process_refresh()
{

	if (!isset($_GET['connect_refresh'])) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ('true' == $_GET['connect_refresh']) {
		// Redirect to the payments tab so the admin can re-initiate Stripe Connect.
		wp_safe_redirect( admin_url( 'admin.php?page=leaky-paywall-settings&tab=payments' ) );
		exit;
	}
}


add_action('admin_init', 'leaky_paywall_connect_maybe_process_return');

function leaky_paywall_connect_maybe_process_return()
{

	if (!isset($_GET['connected_account_id'])) {
		return;
	}

	if (! is_user_logged_in()) {
		wp_safe_redirect(wp_login_url(admin_url()));
		exit;
	}

	if (! current_user_can(apply_filters('manage_leaky_paywall_settings', 'manage_options'))) {
		wp_die(esc_html__('Insufficient permissions.', 'leaky-paywall'), 403);
	}

	$incoming_state = isset($_GET['lp_connect_state']) ? sanitize_text_field(wp_unslash($_GET['lp_connect_state'])) : '';
	$stored_state = get_transient('lp_connect_state_' . get_current_user_id());

	if (empty($incoming_state) || empty($stored_state) || ! hash_equals($stored_state, $incoming_state)) {
		wp_die(esc_html__('Invalid or expired connect session.', 'leaky-paywall'), 400);
	}

	$settings = get_leaky_paywall_settings();
	$connected_account_id = sanitize_text_field(wp_unslash($_GET['connected_account_id']));

	if (! preg_match('/^acct_[A-Za-z0-9]+$/', $connected_account_id)) {
		wp_die(esc_html__('Invalid account id.', 'leaky-paywall'), 400);
	}

	$settings['connected_account_id'] = $connected_account_id;

	$onboarding_completed = true;

	if ( $onboarding_completed ) {

		$base_url = apply_filters( 'leaky_paywall_app_url', 'https://app.leakypaywall.com' );

		$lp_credentials_url = add_query_arg(
			array(
				'api_key'    => $settings['lp_app_api_key'],
				'account_id' => $connected_account_id,
			),
			$base_url . '/api/v1/connect/credentials'
		);

		$response = wp_remote_get(esc_url_raw($lp_credentials_url), ['timeout' => 15]);

		if (is_wp_error($response)) {
			wp_die(esc_html($response->get_error_message()), 502);
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300 || empty($body)) {
			wp_die(esc_html__('Failed to retrieve credentials.', 'leaky-paywall'), 502);
		}

		$data = json_decode($body);

		if (! is_object($data)) {
			wp_die(esc_html__('Invalid credentials response.', 'leaky-paywall'), 502);
		}

		if ( isset( $data->public_key ) ) {
			$settings['live_publishable_key'] = $data->public_key;
		}

		if ( isset( $data->secret_key ) ) {
			$settings['live_secret_key'] = $data->secret_key;
		}

		$settings['test_mode'] = 'off';

		// Cache the display name so test mode can show it without a Stripe call —
		// test API keys can't access a live connected account.
		if ( ! empty( $settings['live_secret_key'] ) ) {
			try {
				$temp_client = new \Stripe\StripeClient( $settings['live_secret_key'] );
				$account     = $temp_client->accounts->retrieve( $connected_account_id );
				if ( isset( $account->settings->dashboard->display_name ) ) {
					$settings['connected_account_display_name'] = $account->settings->dashboard->display_name;
				}
			} catch ( \Throwable $th ) {
				leaky_paywall_log( $th->getMessage(), 'leaky paywall - could not cache connected account display name' );
			}
		}

		update_leaky_paywall_settings($settings);

		delete_transient('lp_connect_state_' . get_current_user_id());
	}

	wp_safe_redirect(admin_url('admin.php?page=leaky-paywall-settings&tab=payments'));
	exit;
}

/**
 * Sync email change to Stripe customer.
 *
 * @since 5.0.1
 *
 * @param int    $user_id   WordPress user ID.
 * @param string $old_email The previous email address.
 * @param string $new_email The new email address.
 */
function leaky_paywall_stripe_sync_email_change( $user_id, $old_email, $new_email ) {

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return;
	}

	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	$gateway = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );

	if ( false === stripos( $gateway, 'stripe' ) ) {
		return;
	}

	$customer_id = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );

	if ( empty( $customer_id ) ) {
		return;
	}

	try {
		$stripe = leaky_paywall_initialize_stripe_api();
		$stripe->customers->update(
			$customer_id,
			array( 'email' => $new_email ),
			leaky_paywall_get_stripe_connect_params()
		);
		leaky_paywall_log( $new_email, 'stripe email sync: updated customer ' . $customer_id );
	} catch ( \Exception $e ) {
		leaky_paywall_log( $e->getMessage(), 'stripe email sync: error for customer ' . $customer_id );
	}
}
add_action( 'leaky_paywall_subscriber_email_changed', 'leaky_paywall_stripe_sync_email_change', 10, 3 );

/**
 * Update billing address on both the Stripe customer and their default payment method.
 *
 * @param \Stripe\StripeClient $stripe
 * @param string               $customer_id  Stripe customer ID.
 * @param array                $billing      Keys: name, line1, line2, city, state, postal_code, country.
 */
function leaky_paywall_stripe_update_billing_address( $stripe, $customer_id, $billing ) {
	$address = array(
		'line1'       => $billing['line1'] ?? '',
		'line2'       => $billing['line2'] ?? '',
		'city'        => $billing['city'] ?? '',
		'state'       => $billing['state'] ?? '',
		'postal_code' => $billing['postal_code'] ?? '',
		'country'     => $billing['country'] ?? '',
	);
	$name = $billing['name'] ?? '';

	$update_args = array( 'address' => $address );
	if ( $name ) {
		$update_args['name'] = $name;
	}
	$stripe->customers->update( $customer_id, $update_args, leaky_paywall_get_stripe_connect_params() );

	// Also update the billing details on all payment methods attached to the customer.
	$billing_details = array( 'address' => $address );
	if ( $name ) {
		$billing_details['name'] = $name;
	}

	$payment_methods = $stripe->paymentMethods->all(
		array( 'customer' => $customer_id ),
		leaky_paywall_get_stripe_connect_params()
	);

	foreach ( $payment_methods->data as $pm ) {
		$stripe->paymentMethods->update(
			$pm->id,
			array( 'billing_details' => $billing_details ),
			leaky_paywall_get_stripe_connect_params()
		);
	}
}

/**
 * After registration, sync the billing address captured by Stripe's Address Element
 * to the Stripe customer record and to user meta.
 *
 * @param array $subscriber_data
 */
function leaky_paywall_stripe_sync_billing_address( $subscriber_data ) {
	$settings = get_leaky_paywall_settings();

	if ( 'on' !== $settings['stripe_billing_address'] ) {
		return;
	}

	if ( empty( $subscriber_data['payment_gateway'] ) || 'stripe' !== $subscriber_data['payment_gateway'] ) {
		return;
	}

	$customer_id = isset( $subscriber_data['subscriber_id'] ) ? $subscriber_data['subscriber_id'] : '';
	$user_id     = isset( $subscriber_data['user_id'] ) ? absint( $subscriber_data['user_id'] ) : 0;

	if ( empty( $customer_id ) || empty( $user_id ) ) {
		return;
	}

	$billing_name        = isset( $_POST['lp_billing_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_line1       = isset( $_POST['lp_billing_line1'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_line1'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_line2       = isset( $_POST['lp_billing_line2'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_line2'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_city        = isset( $_POST['lp_billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_city'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_state       = isset( $_POST['lp_billing_state'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_postal_code = isset( $_POST['lp_billing_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_postal_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$billing_country     = isset( $_POST['lp_billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( empty( $billing_line1 ) && empty( $billing_postal_code ) ) {
		return;
	}

	$address = array(
		'line1'       => $billing_line1,
		'line2'       => $billing_line2,
		'city'        => $billing_city,
		'state'       => $billing_state,
		'postal_code' => $billing_postal_code,
		'country'     => $billing_country,
	);

	update_user_meta( $user_id, '_lp_billing_address', array_merge( array( 'name' => $billing_name ), $address ) );

	try {
		$stripe      = leaky_paywall_initialize_stripe_api();
		$update_args = array( 'address' => $address );
		if ( ! empty( $billing_name ) ) {
			$update_args['name'] = $billing_name;
		}
		$stripe->customers->update( $customer_id, $update_args, leaky_paywall_get_stripe_connect_params() );
		leaky_paywall_log( $customer_id, 'stripe billing address sync: updated customer' );
	} catch ( \Exception $e ) {
		leaky_paywall_log( $e->getMessage(), 'stripe billing address sync: error for customer ' . $customer_id );
	}
}
add_action( 'leaky_paywall_after_process_registration', 'leaky_paywall_stripe_sync_billing_address', 10, 1 );

/**
 * Capture Stripe Tax data on a transaction after it is created.
 *
 * Looks up the most recent invoice for the Stripe customer and stores
 * the tax and subtotal amounts on the transaction post meta.
 *
 * @since 5.1.0
 */
function leaky_paywall_stripe_capture_tax_on_transaction( $transaction_id, $user ) {
	$settings = get_leaky_paywall_settings();

	if ( 'on' !== $settings['stripe_automatic_tax'] ) {
		return;
	}

	$gateway = get_post_meta( $transaction_id, '_gateway', true );

	if ( ! in_array( $gateway, array( 'stripe', 'stripe_checkout' ), true ) ) {
		return;
	}

	$customer_id = get_post_meta( $transaction_id, '_subscriber_id', true );

	if ( empty( $customer_id ) ) {
		return;
	}

	$stripe = leaky_paywall_initialize_stripe_api();

	if ( ! $stripe ) {
		return;
	}

	try {
		$invoices = $stripe->invoices->all(
			array( 'customer' => $customer_id, 'limit' => 1 ),
			leaky_paywall_get_stripe_connect_params()
		);

		if ( ! empty( $invoices->data ) ) {
			$invoice = $invoices->data[0];

			if ( isset( $invoice->tax ) && $invoice->tax > 0 ) {
				update_post_meta( $transaction_id, '_tax_amount', $invoice->tax / 100 );
				update_post_meta( $transaction_id, '_subtotal', $invoice->subtotal / 100 );
			}
		}
	} catch ( \Exception $e ) {
		leaky_paywall_log( $e->getMessage(), 'stripe tax capture on transaction' );
	}
}
add_action( 'leaky_paywall_after_create_transaction', 'leaky_paywall_stripe_capture_tax_on_transaction', 10, 2 );

/**
 * AJAX handler for Stripe Tax preview via Invoice Preview API.
 *
 * @since 5.1.0
 */
function leaky_paywall_stripe_tax_preview() {
	check_ajax_referer( 'lp_tax_preview', 'nonce' );

	$settings = get_leaky_paywall_settings();
	$level_id = absint( $_POST['level_id'] );
	$level    = get_leaky_paywall_subscription_level( $level_id );

	if ( ! $level ) {
		wp_send_json_error( 'Invalid level.' );
	}

	$address = array(
		'line1'       => sanitize_text_field( wp_unslash( $_POST['line1'] ) ),
		'city'        => sanitize_text_field( wp_unslash( $_POST['city'] ) ),
		'state'       => sanitize_text_field( wp_unslash( $_POST['state'] ) ),
		'postal_code' => sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ),
		'country'     => sanitize_text_field( wp_unslash( $_POST['country'] ) ),
	);

	$stripe = leaky_paywall_initialize_stripe_api();

	if ( ! $stripe ) {
		wp_send_json_error( 'Stripe API could not be initialized.' );
	}

	$customer_id   = isset( $_POST['customer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) : '';
	$client_secret = '';

	// Update the Stripe customer's address so automatic_tax can resolve their location
	// when the subscription or payment intent is created/confirmed.
	if ( ! empty( $customer_id ) ) {
		try {
			$stripe->customers->update(
				$customer_id,
				array( 'address' => $address ),
				leaky_paywall_get_stripe_connect_params()
			);
		} catch ( \Exception $e ) {
			leaky_paywall_log( $e->getMessage(), 'stripe tax preview: could not update customer address' );
			wp_send_json_error( 'Could not update billing address.' );
		}

		// Create the subscription now that the customer has an address.
		// Subscription creation was deferred from step 1 so automatic_tax can resolve the location.
		// Only create if one doesn't already exist (address may change multiple times).
		if ( isset( $level['recurring'] ) && 'on' === $level['recurring'] ) {
			$existing_subs = $stripe->subscriptions->all(
				array( 'customer' => $customer_id, 'status' => 'incomplete', 'limit' => 1 ),
				leaky_paywall_get_stripe_connect_params()
			);

			if ( empty( $existing_subs->data ) ) {
				try {
					$stripe_price = number_format( (float) $level['price'], 2, '', '' );
					$plan_args    = array(
						'stripe_price' => $stripe_price,
						'currency'     => leaky_paywall_get_currency(),
						'secret_key'   => leaky_paywall_get_stripe_secret_key(),
					);

					$stripe_plan = leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args );

					if ( ! $stripe_plan ) {
						wp_send_json_error( 'Could not retrieve Stripe plan.' );
					}

					$subscription = $stripe->subscriptions->create(
						array(
							'customer'         => $customer_id,
							'items'            => array( array( 'plan' => $stripe_plan->id ) ),
							'automatic_tax'    => array( 'enabled' => true ),
							'payment_behavior' => 'default_incomplete',
							'payment_settings' => array( 'save_default_payment_method' => 'on_subscription' ),
							'expand'           => array( 'latest_invoice.payment_intent' ),
						),
						leaky_paywall_get_stripe_connect_params()
					);

					$client_secret = $subscription->latest_invoice->payment_intent->client_secret;
				} catch ( \Exception $e ) {
					leaky_paywall_log( $e->getMessage(), 'stripe tax preview: could not create subscription' );
					wp_send_json_error( 'Could not create subscription.' );
				}
			} else {
				// Subscription already exists — retrieve its client_secret for the payment form.
				$existing_sub = $stripe->subscriptions->retrieve(
					$existing_subs->data[0]->id,
					array( 'expand' => array( 'latest_invoice.payment_intent' ) ),
					leaky_paywall_get_stripe_connect_params()
				);

				if ( ! empty( $existing_sub->latest_invoice->payment_intent ) ) {
					$client_secret = $existing_sub->latest_invoice->payment_intent->client_secret;
				}
			}
		}
	}

	try {
		$preview_args = array(
			'automatic_tax' => array( 'enabled' => true ),
		);

		if ( ! empty( $customer_id ) ) {
			$preview_args['customer'] = $customer_id;
		} else {
			$preview_args['customer_details'] = array(
				'address'    => $address,
				'tax_exempt' => 'none',
			);
		}

		if ( isset( $level['recurring'] ) && 'on' === $level['recurring'] ) {
			$stripe_price = number_format( (float) $level['price'], 2, '', '' );
			$plan_args    = array(
				'stripe_price' => $stripe_price,
				'currency'     => leaky_paywall_get_currency(),
				'secret_key'   => leaky_paywall_get_stripe_secret_key(),
			);

			$stripe_plan = leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args );

			if ( ! $stripe_plan ) {
				wp_send_json_error( 'Could not retrieve Stripe plan.' );
			}

			$preview_args['subscription_items'] = array(
				array( 'price' => $stripe_plan->id, 'quantity' => 1 ),
			);
		} else {
			$stripe_price = number_format( (float) $level['price'], 2, '', '' );
			$currency     = leaky_paywall_get_currency();
			$tax_behavior = isset( $settings['stripe_tax_behavior'] ) ? $settings['stripe_tax_behavior'] : 'exclusive';

			$preview_args['invoice_items'] = array(
				array(
					'amount'       => (int) $stripe_price,
					'currency'     => $currency,
					'tax_behavior' => $tax_behavior,
					'description'  => $level['label'],
				),
			);
		}

		$preview = $stripe->invoices->upcoming(
			$preview_args,
			leaky_paywall_get_stripe_connect_params()
		);

		// For non-recurring levels, create an invoice with automatic_tax so Stripe
		// records the tax breakdown. The invoice generates a payment intent we can confirm.
		// Only create if one doesn't already exist (address may change multiple times).
		if ( empty( $client_secret ) && ( ! isset( $level['recurring'] ) || 'on' !== $level['recurring'] ) && ! empty( $customer_id ) ) {

			// Check for an existing open/draft invoice for this customer.
			$existing_invoices = $stripe->invoices->all(
				array( 'customer' => $customer_id, 'status' => 'open', 'limit' => 1 ),
				leaky_paywall_get_stripe_connect_params()
			);

			if ( ! empty( $existing_invoices->data ) ) {
				// Invoice already exists — return its payment intent client_secret.
				$existing_invoice = $existing_invoices->data[0];
				if ( ! empty( $existing_invoice->payment_intent ) ) {
					$pi = $stripe->paymentIntents->retrieve(
						$existing_invoice->payment_intent,
						[],
						leaky_paywall_get_stripe_connect_params()
					);
					$client_secret = $pi->client_secret;
				}
			} else {
				$stripe_price = number_format( (float) $level['price'], 2, '', '' );
				$currency     = leaky_paywall_get_currency();
				$tax_behavior = isset( $settings['stripe_tax_behavior'] ) ? $settings['stripe_tax_behavior'] : 'exclusive';

				// Create a draft invoice with automatic tax.
				$invoice = $stripe->invoices->create(
					array(
						'customer'      => $customer_id,
						'automatic_tax' => array( 'enabled' => true ),
						'currency'      => $currency,
						'description'   => $level['label'],
					),
					leaky_paywall_get_stripe_connect_params()
				);

				// Add a line item to the invoice.
				$stripe->invoiceItems->create(
					array(
						'customer'     => $customer_id,
						'invoice'      => $invoice->id,
						'amount'       => (int) $stripe_price,
						'currency'     => $currency,
						'description'  => $level['label'],
						'tax_behavior' => $tax_behavior,
					),
					leaky_paywall_get_stripe_connect_params()
				);

				// Finalize the invoice to generate the payment intent.
				$finalized = $stripe->invoices->finalizeInvoice(
					$invoice->id,
					array( 'expand' => array( 'payment_intent' ) ),
					leaky_paywall_get_stripe_connect_params()
				);

				if ( ! empty( $finalized->payment_intent ) ) {
					$client_secret = $finalized->payment_intent->client_secret;
				}
			}
		}

		$response = array(
			'subtotal' => $preview->subtotal,
			'tax'      => $preview->tax,
			'total'    => $preview->total,
			'currency' => strtoupper( $preview->currency ),
		);

		if ( ! empty( $client_secret ) ) {
			$response['client_secret'] = $client_secret;
		}

		wp_send_json_success( $response );

	} catch ( \Exception $e ) {
		leaky_paywall_log( $e->getMessage(), 'stripe tax preview error' );
		wp_send_json_error( $e->getMessage() );
	}
}
add_action( 'wp_ajax_nopriv_leaky_paywall_stripe_tax_preview', 'leaky_paywall_stripe_tax_preview' );
add_action( 'wp_ajax_leaky_paywall_stripe_tax_preview', 'leaky_paywall_stripe_tax_preview' );

function leaky_paywall_ensure_app_api_key() {
	$settings = get_leaky_paywall_settings();

	if ( ! empty( $settings['lp_app_api_key'] ) ) {
		return $settings['lp_app_api_key'];
	}

	$base_url = apply_filters( 'leaky_paywall_app_url', 'https://app.leakypaywall.com' );

	$response = wp_remote_post(
		$base_url . '/api/v1/register',
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( array( 'site_url' => home_url() ) ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return '';
	}

	$data = json_decode( wp_remote_retrieve_body( $response ) );

	if ( isset( $data->api_key ) ) {
		$settings['lp_app_api_key'] = sanitize_text_field( $data->api_key );
		update_leaky_paywall_settings( $settings );
		return $settings['lp_app_api_key'];
	}

	return '';
}

function leaky_paywall_get_stripe_connect_params() {

	$params   = [];
	$settings = get_leaky_paywall_settings();

	if ( 'live' === leaky_paywall_get_current_mode()
		&& ! empty( $settings['connected_account_id'] ) ) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}

	return apply_filters( 'leaky_paywall_stripe_connect_params', $params );
}

add_filter('leaky_paywall_payment_intent_params', 'leaky_paywall_connect_adjust_intent_params', 50, 2);

function leaky_paywall_connect_adjust_intent_params( $params, $level ) {
	$settings = get_leaky_paywall_settings();
	if ( 'live' === leaky_paywall_get_current_mode() && ! empty( $settings['connected_account_id'] ) ) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}

	return $params;
}

add_filter('leaky_paywall_payment_intent_args', 'leaky_paywall_connect_adjust_intent_args', 50, 2);

function leaky_paywall_connect_adjust_intent_args($args, $level)
{
	$settings = get_leaky_paywall_settings();
	if ( 'live' === leaky_paywall_get_current_mode() && ! empty( $settings['connected_account_id'] ) ) {
		$fee = round( $level['price'] * 0.1, 2 ) * 100;
		$args['application_fee_amount'] = $fee;
	}

	return $args;
}

add_filter('leaky_paywall_process_stripe_payment_customer_params', 'leaky_paywall_connect_adjust_customer_params', 99, 2 );

function leaky_paywall_connect_adjust_customer_params($params, $fields) {

	$settings = get_leaky_paywall_settings();
	if ( ! empty( $settings['connected_account_id'] ) ) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}
	return $params;
}


add_filter('leaky_paywall_stripe_subscription_params', 'leaky_paywall_connect_adjust_subscription_params', 99, 3 );

function leaky_paywall_connect_adjust_subscription_params( $params, $level, $fields ) {
	$settings = get_leaky_paywall_settings();

	if ( isset($settings['connected_account_id'] )) {
		if ($settings['connected_account_id']) {
			$params['stripe_account'] = $settings['connected_account_id'];
		}
	}

	return $params;
}

add_filter('leaky_paywall_stripe_plan_params', 'leaky_paywall_connect_adjust_plan_params', 99, 3 );

function leaky_paywall_connect_adjust_plan_params( $params, $level, $plan_args ) {
	$settings = get_leaky_paywall_settings();

	if ( isset($settings['connected_account_id']) ) {
		if ($settings['connected_account_id']) {
			$params['stripe_account'] = $settings['connected_account_id'];
		}
	}

	return $params;
}


add_filter('leaky_paywall_stripe_subscription_args', 'leaky_paywall_connect_adjust_subscription_args', 99, 3 );

function leaky_paywall_connect_adjust_subscription_args($subscription_array, $level, $fields) {
	$settings = get_leaky_paywall_settings();

	if ( 'live' === leaky_paywall_get_current_mode() && ! empty( $settings['connected_account_id'] ) ) {
		$subscription_array['application_fee_percent'] = 10;
	}

	return $subscription_array;
}

add_action( 'admin_init', 'leaky_paywall_stripe_disconnect');

function leaky_paywall_stripe_disconnect() {

	if (! isset($_GET['action']) ) {
		return;
	}

	if ( sanitize_text_field( $_GET['action'] ) != 'lp_stripe_disconnect' ) {
		return;
	}

	if (
		! isset($_GET['_wpnonce']) ||
		! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'lp_stripe_disconnect_action' )
	) {
		return;
	}

	$settings = get_leaky_paywall_settings();

	$settings['connected_account_id'] = '';
	$settings['live_secret_key'] = '';
	$settings['live_publishable_key'] = '';

	update_leaky_paywall_settings( $settings );

	wp_safe_redirect( admin_url( 'admin.php?page=leaky-paywall-settings&tab=payments&lp_stripe_disconnected=1' ) );
	exit;
}

add_action('admin_init', 'leaky_paywall_manually_process_incomplete_user');

function leaky_paywall_manually_process_incomplete_user()
{

	if (!isset($_GET['lp_iu_email'])) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$email = sanitize_email($_GET['lp_iu_email']);

	if ( !is_email( $email ) ) {
		return;
	}

	leaky_paywall_create_subscriber_from_incomplete_user( $email );
}