<?php
/**
 * All helper functions used with Leaky Paywall and Stripe
 *
 * @package Leaky Paywall
 * @since 1.0.0
 */

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
		return '<p>Please enter Stripe API keys in <a href="' . admin_url() . 'admin.php?page=issuem-leaky-paywall&tab=payments">your Leaky Paywall settings</a>.</p>';
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
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['register_nonce'] ) ), 'lp_register_nonce' )
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
		|| ! wp_verify_nonce( sanitize_text_field( $fields['leaky_paywall_register_nonce'] ), 'leaky-paywall-register-nonce' )
	) {
		wp_send_json(
			array(
				'error' => __( 'There was an error. Please try again.', 'leaky-paywall' )
			)
		);
	}

	$stripe = leaky_paywall_initialize_stripe_api();

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
				'plan' => $plan_id,
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
					'plan' => $plan_id
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
				'plan' => $plan_id,
			),
		),
		'payment_behavior' => 'default_incomplete',
		'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
		'expand' => ['latest_invoice.payment_intent'],
	);

// 	$subscription_params = apply_filters('leaky_paywall_stripe_subscription_params', [], $level, $fields);

	try {
		leaky_paywall_log('before get subs', 'stripe subscription for ' . $customer_id);
		leaky_paywall_log($cu, 'stripe subscription for ' . $customer_id);
		$subscriptions = $stripe->subscriptions->all(array('limit' => '1', 'customer' => $customer_id), leaky_paywall_get_stripe_connect_params());
		leaky_paywall_log('after get subs', 'stripe subscription for ' . $customer_id);

		if (empty($subscriptions->data)) {
			leaky_paywall_log('empty sub data', 'stripe subscription for ' . $customer_id);
			$subscription = $stripe->subscriptions->create(apply_filters('leaky_paywall_stripe_subscription_args', $subscription_array, $level, $fields), leaky_paywall_get_stripe_connect_params() );
		} else {
			leaky_paywall_errors()->add('subscription_exists', __('A subscription already exists for this user.', 'leaky-paywall'), 'register');
			return false;
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
		$stripe_price = number_format( $amount, '0', '', '' );
	} else {
		$stripe_price = number_format( $amount, '2', '', '' ); // no decimals.
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

		$subscriptions = $stripe->subscriptions->all(array(
			'customer' => $cus->id,
			'limit' => '1'
		), leaky_paywall_get_stripe_connect_params() );

		if (empty($subscriptions)) {
			return;
		}

		foreach ($subscriptions->data as $subscription) {

			$current_period_end = $subscription->current_period_end;
			$plan = $subscription->plan->id;

			if ($current_period_end) {
				$expires = date_i18n('Y-m-d 23:59:59', $current_period_end);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, $plan);
			}

			if ( $subscription->status == 'active' ) {
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
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

	$incomplete_id = leaky_paywall_get_incomplete_user_from_email($stripe_object->customer_details->email);

	if (!$incomplete_id) {
		leaky_paywall_log($stripe_object->customer, 'stripe checkout event no incomplete found');
		return;
	}

	// leaky_paywall_create_subscriber_from_incomplete_user( $email );


	$user_data = get_post_meta($incomplete_id, '_user_data', true);
	$field_data = get_post_meta($incomplete_id, '_field_data', true);
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

		leaky_paywall_cleanup_incomplete_user($user_data['email']);

		// Send email notifications
		leaky_paywall_email_subscription_status($user_id, $status, $subscriber_data);

	}

}

add_action('leaky_paywall_before_process_stripe_webhook', 'leaky_paywall_process_stripe_subscription_payment_element_webhook');

function leaky_paywall_process_stripe_subscription_payment_element_webhook($stripe_event) {

	// if ($stripe_event->type != 'customer.subscription.updated') {
	// 	return;
	// }

	if ($stripe_event->type != 'payment_intent.succeeded') {
		return;
	}

	return;

	// Stripe says to create the sale in the database here, but we need to log the user in after submission. The webhook can't do that.  Maybe we attempt to create sale here if it wasn't already created by pi flow.  If the transaction already exists, then exit.

	$stripe_object = $stripe_event->data->object;

	leaky_paywall_log($stripe_object->customer, 'stripe subscription updated event customer');

	$stripe = leaky_paywall_initialize_stripe_api();

	$incomplete_id = '';

	try {
		$cu = $stripe->customers->retrieve($stripe_object->customer, [], leaky_paywall_get_stripe_connect_params());
		$incomplete_id = leaky_paywall_get_incomplete_user_from_email($cu->email);
	} catch (\Throwable $th) {
		//throw $th;
	}

	if (!$incomplete_id) {
		leaky_paywall_log($stripe_object->customer, 'stripe subscription updated event no incomplete found');
		return;
	}

	$user_data = get_post_meta($incomplete_id, '_user_data', true);
	$field_data = get_post_meta($incomplete_id, '_field_data', true);
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

		$price = $stripe_object->plan->amount;
		$plan = $stripe_object->plan->id;
	} else {

		$price = $stripe_object->amount;
		$plan = '';

	}

	$subscriber_data = array(
		'email' => $user_data['email'],
		'password' => isset($user_data['password']) ? $user_data['password'] : '',
		'first_name'	=> $user_data['first_name'],
		'last_name'	=> $user_data['last_name'],
		'level_id'	=> $user_data['level_id'],
		'description' => $level['label'],
		'subscriber_id'	=> $stripe_object->customer,
		'created'	=> gmdate('Y-m-d H:i:s'),
		'price'	=> $price / 100,
		'plan'	=> $plan,
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

	if ($existing_customer) {
		$subscriber_data['need_new'] = false;
	} else {
		$subscriber_data['need_new'] = true;
	}

	if ($existing_customer) {
		$user_id = leaky_paywall_update_subscriber(NULL, $user_data['email'], $stripe_object->customer, $subscriber_data);
	} else {
		$user_id = leaky_paywall_new_subscriber(NULL, $user_data['email'], $stripe_object->customer, $subscriber_data);
	}

	$subscriber_data['user_id'] = $user_id;

	$transaction = new LP_Transaction($subscriber_data);
	$transaction_id = $transaction->create();
	$subscriber_data['transaction_id'] = $transaction_id;

	update_post_meta($transaction_id, '_field_data', $field_data);

	if (isset($field_data['lp_nag_loc'])) {
		update_post_meta($transaction_id, '_nag_location_id', $field_data['lp_nag_loc']);
	}

	// do_action('leaky_paywall_after_stripe_subscription_completed', $subscriber_data);

	leaky_paywall_cleanup_incomplete_user($user_data['email']);

	// Send email notifications


}

add_action( 'init', 'leaky_paywall_maybe_process_payment_intent_redirect_url' );

function leaky_paywall_maybe_process_payment_intent_redirect_url() {

	if ( !isset( $_GET['payment_intent'] ) ) {
		return;
	}

	$settings = get_leaky_paywall_settings();

	$pi_id = sanitize_text_field( $_GET['payment_intent'] );

	$stripe = leaky_paywall_initialize_stripe_api();

	try {
		$pi = $stripe->paymentIntents->retrieve($pi_id, [], leaky_paywall_get_stripe_connect_params());
	} catch (\Throwable $th) {
		leaky_paywall_log( $th->getMessage(), 'lp error - retrieving payment intent from redirect url');
	}

	if ( !isset($pi->status) ) {
		return;
	}

	if ( 'succeeded' != $pi->status ) {
		return;
	}

	$incomplete_id = '';

	try {
		$cu = $stripe->customers->retrieve($pi->customer, [], leaky_paywall_get_stripe_connect_params());
	} catch (\Throwable $th) {
		leaky_paywall_log($th->getMessage(), 'lp error - retrieving customer from payment intent redirect url');
	}

	if ( !isset( $cu->email ) ) {
		return;
	}

	$incomplete_id = leaky_paywall_get_incomplete_user_from_email($cu->email);

	if ( !$incomplete_id ) {
		return;
	}

	$user_data = get_post_meta($incomplete_id, '_user_data', true);
	$field_data = get_post_meta($incomplete_id, '_field_data', true);
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

		try {
			$subscriptions = $stripe->subscriptions->all(array(
				'customer' => $cu->id,
				'limit' => '1'
			), leaky_paywall_get_stripe_connect_params());

			foreach ($subscriptions->data as $subscription) {
				// get subscription plan id
				$plan_id = $subscription->plan->id;
			}

		} catch (\Throwable $th) {
			//throw $th;
		}

	}

	$subscriber_data = array(
		'email' => $user_data['email'],
		'password' => isset($user_data['password']) ? $user_data['password'] : '',
		'first_name'	=> $user_data['first_name'],
		'last_name'	=> $user_data['last_name'],
		'level_id'	=> $user_data['level_id'],
		'description' => $level['label'],
		'subscriber_id'	=> $pi->customer,
		'created'	=> gmdate('Y-m-d H:i:s'),
		'price'	=> $pi->amount / 100,
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

	if ($existing_customer) {
		$subscriber_data['need_new'] = false;
	} else {
		$subscriber_data['need_new'] = true;
	}

	if ($existing_customer) {
		$user_id = leaky_paywall_update_subscriber(NULL, $user_data['email'], $pi->customer, $subscriber_data);
	} else {
		$user_id = leaky_paywall_new_subscriber(NULL, $user_data['email'], $pi->customer, $subscriber_data);
	}

	$subscriber_data['user_id'] = $user_id;

	$transaction = new LP_Transaction($subscriber_data);
	$transaction_id = $transaction->create();
	$subscriber_data['transaction_id'] = $transaction_id;

	update_post_meta($transaction_id, '_field_data', $field_data);

	if (isset($field_data['lp_nag_loc'])) {
		update_post_meta($transaction_id, '_nag_location_id', $field_data['lp_nag_loc']);
	}

	leaky_paywall_cleanup_incomplete_user($user_data['email']);

	leaky_paywall_email_subscription_status($user_id, $status, $subscriber_data);

	// log the user in.
	leaky_paywall_log_in_user($user_id);

	do_action('leaky_paywall_after_process_registration', $subscriber_data);

	$restrictions = new Leaky_Paywall_Restrictions();
	$restrictions->clear_cookie();

	if (isset($_COOKIE['lp_nag_loc'])) {
		update_post_meta($transaction_id, '_nag_location_id', absint($_COOKIE['lp_nag_loc']));
	}

	// send the newly created user to the appropriate page after logging them in.
	wp_safe_redirect(leaky_paywall_get_redirect_url($settings, $subscriber_data));
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

		|| ! wp_verify_nonce( sanitize_text_field( $_POST['stripe_customer_portal_field'] ), 'stripe_customer_portal_submit' )

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

	if ('true' == $_GET['connect_refresh']) {
		wp_redirect($this->get_connect_url());
		exit;
	}
}


add_action('admin_init', 'leaky_paywall_connect_maybe_process_return');

function leaky_paywall_connect_maybe_process_return()
{

	if (!isset($_GET['connected_account_id'])) {
		return;
	}

	$settings = get_leaky_paywall_settings();
	$connected_account_id = sanitize_text_field($_GET['connected_account_id']);
	$settings['connected_account_id'] = $connected_account_id;

	// $stripe = leaky_paywall_initialize_stripe_api();
	$onboarding_completed = true;

	/*
	try {
		$stripe_account = $stripe->accounts->retrieve($connected_account_id);

		if ( $stripe_account->details_submitted == 1 ) {
			// they have completed onboarding
			$onboarding_completed = true;
		}

	} catch (\Throwable $th) {
		//throw $th;
	}
		*/

	if ( $onboarding_completed ) {

		$lp_credentials_url = add_query_arg(
			array(
				'mode'         => leaky_paywall_get_current_mode(),
				'account_id'             => $connected_account_id,
				'customer_site_url' => rawurlencode(home_url()),
			),
			'https://leakypaywall.com/?lp_gateway_connect_credentials=stripe_connect'
		);

		$response = wp_remote_get(esc_url_raw($lp_credentials_url));

		$data = json_decode($response['body']);

		if ( isset( $data->public_key ) ) {
			// use mode
			$array_key = leaky_paywall_get_current_mode() . '_publishable_key';
			$settings[$array_key] = $data->public_key;
		}

		if ( isset( $data->secret_key ) ) {
			$array_key = leaky_paywall_get_current_mode() . '_secret_key';
			$settings[$array_key] = $data->secret_key;
		}

	}

	update_leaky_paywall_settings($settings);

}

function leaky_paywall_get_stripe_connect_params() {

	$params = [];
	$settings = get_leaky_paywall_settings();

	if ( isset($settings['connected_account_id'])) {
		if ($settings['connected_account_id']) {
			$params['stripe_account'] = $settings['connected_account_id'];
		}
	}

	return apply_filters( 'leaky_paywall_stripe_connect_params', $params );
}

add_filter('leaky_paywall_payment_intent_params', 'leaky_paywall_connect_adjust_intent_params', 50, 2);

function leaky_paywall_connect_adjust_intent_params( $params, $level ) {
	$settings = get_leaky_paywall_settings();
	if ($settings['connected_account_id']) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}

	return $params;
}

add_filter('leaky_paywall_payment_intent_args', 'leaky_paywall_connect_adjust_intent_args', 50, 2);

function leaky_paywall_connect_adjust_intent_args($args, $level)
{
	$settings = get_leaky_paywall_settings();
	if ($settings['connected_account_id']) {
		$fee = round( $level['price'] * 0.1, 2 ) * 100;
		$args['application_fee_amount'] = $fee;
	}

	return $args;
}

add_filter('leaky_paywall_process_stripe_payment_customer_params', 'leaky_paywall_connect_adjust_customer_params', 99, 2 );

function leaky_paywall_connect_adjust_customer_params($params, $fields) {

	$settings = get_leaky_paywall_settings();
	if ($settings['connected_account_id']) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}
	return $params;
}


add_filter('leaky_paywall_stripe_subscription_params', 'leaky_paywall_connect_adjust_subscription_params', 99, 3 );

function leaky_paywall_connect_adjust_subscription_params( $params, $level, $fields ) {
	$settings = get_leaky_paywall_settings();

	if ($settings['connected_account_id']) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}

	return $params;
}

add_filter('leaky_paywall_stripe_plan_params', 'leaky_paywall_connect_adjust_plan_params', 99, 3 );

function leaky_paywall_connect_adjust_plan_params( $params, $level, $plan_args ) {
	$settings = get_leaky_paywall_settings();

	if ($settings['connected_account_id']) {
		$params['stripe_account'] = $settings['connected_account_id'];
	}

	return $params;
}


add_filter('leaky_paywall_stripe_subscription_args', 'leaky_paywall_connect_adjust_subscription_args', 99, 3 );

function leaky_paywall_connect_adjust_subscription_args($subscription_array, $level, $fields) {
	$settings = get_leaky_paywall_settings();

	if ($settings['connected_account_id']) {
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

	/*
	if (
		! isset($_GET['_wpnonce']) ||
		! wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), 'lp_stripe_disconnect_action' )
	) {
		return;
	}
		*/

	$settings = get_leaky_paywall_settings();

	$settings['connected_account_id'] = '';
	$settings['live_secret_key'] = '';
	$settings['live_publishable_key'] = '';
	$settings['test_secret_key'] = '';
	$settings['test_publishable_key'] = '';

	foreach( $settings['payment_gateway'] as $key => $value ) {
		if ( $value === 'stripe' ) {
			unset( $settings['payment_gateway'][$key] );
		}
	}

	update_leaky_paywall_settings( $settings );

}

add_action('admin_init', 'leaky_paywall_manually_process_incomplete_user');

function leaky_paywall_manually_process_incomplete_user()
{

	if (!isset($_GET['lp_iu_email'])) {
		return;
	}

	$email = sanitize_email($_GET['lp_iu_email']);

	if ( !is_email( $email ) ) {
		return;
	}

	leaky_paywall_create_subscriber_from_incomplete_user( $email );
}