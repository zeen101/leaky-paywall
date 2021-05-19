<?php

/**
 * Add the subscribe link to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_subscription_cards($payment_options, $level, $level_id)
{

	if ($level['price'] == 0) {
		return $payment_options;
	}

	$output = '';

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$enabled_gateways = $gateways->enabled_gateways;

	$settings = get_leaky_paywall_settings();

	if (in_array('stripe', array_keys($enabled_gateways))) {
		$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link($settings['page_for_register']) . '?level_id=' . $level_id . '">' . __('Subscribe', 'leaky-paywall') . '</a></div>';
	}

	if (in_array('stripe_checkout', array_keys($enabled_gateways))) {
		$output .= leaky_paywall_stripe_checkout_button($level, $level_id);
	}

	return $payment_options . $output;
}
add_filter('leaky_paywall_subscription_options_payment_options', 'leaky_paywall_stripe_subscription_cards', 7, 3);


/**
 * Add the Stripe subscribe popup button to the subscribe cards. 
 *
 * @since 4.0.0
 */
function leaky_paywall_stripe_checkout_button($level, $level_id)
{

	$results = '';
	$settings = get_leaky_paywall_settings();
	$currency = apply_filters('leaky_paywall_stripe_currency', leaky_paywall_get_currency());

	// @todo: make this a function so we can use it on the credit card form too
	if (in_array(strtoupper($currency), array('BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF'))) {
		//Zero-Decimal Currencies
		//https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
		$stripe_price = number_format($level['price'], '0', '', '');
	} else {
		$stripe_price = number_format($level['price'], '2', '', ''); //no decimals
	}
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
	$secret_key = ('on' === $settings['test_mode']) ? $settings['test_secret_key'] : $settings['live_secret_key'];

	if (!$secret_key) {
		return '<p>Please enter Stripe API keys in <a href="' . admin_url() . 'admin.php?page=issuem-leaky-paywall&tab=payments">your Leaky Paywall settings</a>.</p>';
	}

	if (!empty($level['recurring']) && 'on' === $level['recurring']) {

		try {

			$plan_args = array(
				'stripe_price'	=> $stripe_price,
				'currency'		=> $currency,
				'secret_key'	=> $secret_key
			);

			$stripe_plan = leaky_paywall_get_stripe_plan($level, $level_id, $plan_args);

			$results .= '<form action="' . esc_url(add_query_arg('leaky-paywall-confirm', 'stripe_checkout', get_page_link($settings['page_for_subscription']))) . '" method="post">
						  <input type="hidden" name="custom" value="' . esc_js($level_id) . '" />
						  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
								  data-key="' . esc_js($publishable_key) . '"
								  data-locale="auto"
								  data-label="' . apply_filters('leaky_paywall_stripe_button_label', __('Subscribe', 'leaky-paywall')) . '" 
								  data-plan="' . esc_js($stripe_plan->id) . '" 
								  data-currency="' . esc_js($currency) . '" 
								  data-description="' . esc_js($level['label']) . '">
						  </script>
						  ' . apply_filters('leaky_paywall_pay_with_stripe_recurring_payment_form_after_script', '') . '
						</form>';
		} catch (Exception $e) {

			$results = '<h1>' . sprintf(__('Error processing request: %s', 'issuem-leaky-paywall'), $e->getMessage()) . '</h1>';
		}
	} else {

		$results .= '<form action="' . esc_url(add_query_arg('leaky-paywall-confirm', 'stripe_checkout', get_page_link($settings['page_for_subscription']))) . '" method="post">
					  <input type="hidden" name="custom" value="' . esc_js($level_id) . '" />
					  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
							  data-key="' . esc_js($publishable_key) . '"
							  data-locale="auto"
							  data-label="' . apply_filters('leaky_paywall_stripe_button_label', __('Subscribe', 'leaky-paywall')) . '" 
							  data-amount="' . esc_js($stripe_price) . '" 
							  data-currency="' . esc_js($currency) . '" 
							  data-description="' . esc_js($level['label']) . '">
					  </script>
						  ' . apply_filters('leaky_paywall_pay_with_stripe_non_recurring_payment_form_after_script', '') . '
					</form>';
	}

	$results = '<button class="lp-stripe-checkout-button" data-level-id="' . $level_id . '">Subscribe</button>';

	return '<div class="leaky-paywall-stripe-button leaky-paywall-payment-button">' . $results . '</div>';
}

function leaky_paywall_get_stripe_public_key()
{

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();

	if ($mode == 'test') {
		$public_key = isset($settings['test_publishable_key']) ? trim($settings['test_publishable_key']) : '';
	} else {
		$public_key = isset($settings['live_publishable_key']) ? trim($settings['live_publishable_key']) : '';
	}

	return $public_key;
}

function leaky_paywall_get_stripe_secret_key()
{

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();

	if ($mode == 'test') {
		$secret_key = isset($settings['test_secret_key']) ? trim($settings['test_secret_key']) : '';
	} else {
		$secret_key = isset($settings['live_secret_key']) ? trim($settings['live_secret_key']) : '';
	}

	return $secret_key;
}


add_action('wp_ajax_nopriv_leaky_paywall_create_stripe_checkout_session', 'leaky_paywall_create_stripe_checkout_session');
add_action('wp_ajax_leaky_paywall_create_stripe_checkout_session', 'leaky_paywall_create_stripe_checkout_session');

function leaky_paywall_create_stripe_checkout_session()
{

	$level_id = $_POST['level_id'];
	$level = get_leaky_paywall_subscription_level($level_id);

	// if ( !is_numeric( $level ) ) {
	// 	exit;
	// }

	/*
	[label] => BiteSize - Yearly
    [deleted] => 0
    [description] => 
    [registration_form_description] => 
    [price] => 24.99
    [subscription_length_type] => limited
    [interval_count] => 1
    [interval] => year
    [post_types] => Array
        (
            [0] => Array
                (
                    [allowed] => unlimited
                    [allowed_value] => -1
                    [post_type] => article
                    [taxonomy] => all
                )

        )

    [id] => 4
	*/

	$settings = get_leaky_paywall_settings();

	// @todo: make this a function so we can use it on the credit card form too
	if (in_array(strtoupper(leaky_paywall_get_currency()), array('BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF'))) {
		//Zero-Decimal Currencies
		//https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
		$stripe_price = number_format($level['price'], '0', '', '');
	} else {
		$stripe_price = number_format($level['price'], '2', '', ''); //no decimals
	}

	if (isset($level['recurring']) && 'on' == $level['recurring']) {
		$mode = 'subscription';
	} else {
		$mode = 'payment';
	}

	if (!empty($settings['page_for_after_subscribe'])) {
		$redirect_url = get_page_link($settings['page_for_after_subscribe']);
	} else if (!empty($settings['page_for_profile'])) {
		$redirect_url = get_page_link($settings['page_for_profile']);
	} else if (!empty($settings['page_for_subscription'])) {
		$redirect_url = get_page_link($settings['page_for_subscription']);
	} else {
		$redirect_url = home_url();
	}

	leaky_paywall_initialize_stripe_api();

	$data = array(
		'cancel_url' => home_url('cancel'),
		'mode'	=> $mode,
		'payment_method_types' => array('card', 'alipay'),
		'success_url' => $redirect_url,
		'line_items'	=> array(
			array(
				'price_data'	=> array(
					'currency' => leaky_paywall_get_currency(),
					'product_data' => array('name' => $level['label']),
					'unit_amount' => $stripe_price
				),
				'quantity' => 1,
				'description' => $level['label']
			)
		)
	);

	if ('subscription' == $mode) {
		$data['line_items'] = array(
			array(
				'price_data'	=> array(
					'currency' => leaky_paywall_get_currency(),
					'product_data' => array('name' => $level['label']),
					'unit_amount' => $stripe_price,
					'recurring' => array(
						'interval'	=> $level['interval'],
						'interval_count' => $level['interval_count']
					)
				),
				'quantity' => 1,
				'description' => $level['label']
			)
		);
	} else {
		$data['line_items'] = array(
			array(
				'price_data'	=> array(
					'currency' => leaky_paywall_get_currency(),
					'product_data' => array('name' => $level['label']),
					'unit_amount' => $stripe_price
				),
				'quantity' => 1,
				'description' => $level['label']
			)
		);
	}

	try {
		$session = \Stripe\Checkout\Session::create($data);
	} catch (\Throwable $th) {

		echo '<pre>';
		print_r($th);
		echo '</pre>';
		die('testing');
	}

	$return = array(
		'session_id'  => $session->id,
	);

	wp_send_json($return);
}

add_action('wp_ajax_nopriv_leaky_paywall_create_stripe_payment_intent', 'leaky_paywall_create_stripe_payment_intent');
add_action('wp_ajax_leaky_paywall_create_stripe_payment_intent', 'leaky_paywall_create_stripe_payment_intent');

function leaky_paywall_create_stripe_payment_intent()
{

	$level_id = $_POST['level_id'];
	$level = get_leaky_paywall_subscription_level($level_id);


	leaky_paywall_initialize_stripe_api();


	try {
		$payment_intent = \Stripe\PaymentIntent::create(array(
			'amount' => leaky_paywall_get_stripe_amount($level['price']),
			'currency' => strtolower(leaky_paywall_get_currency()),
		));
	} catch (\Throwable $th) {
		leaky_paywall_log('error', 'stripe payment intent');

		wp_send_json(array(
			'error'  => $th->jsonBody
		));
	}

	wp_send_json(array('clientSecret' => $payment_intent->client_secret));
}


add_action('wp_ajax_nopriv_leaky_paywall_create_stripe_checkout_subscription', 'leaky_paywall_create_stripe_checkout_subscription');
add_action('wp_ajax_leaky_paywall_create_stripe_checkout_subscription', 'leaky_paywall_create_stripe_checkout_subscription');

function leaky_paywall_create_stripe_checkout_subscription()
{

	$level_id = $_POST['level_id'];
	$level = get_leaky_paywall_subscription_level($level_id);
	$customerId = $_POST['customerId'];
	$paymentMethodId = $_POST['paymentMethodId'];
	$planId = $_POST['planId'];
	$form_data = $_POST['formData'];
	parse_str($form_data, $fields);

	$settings = get_leaky_paywall_settings();

	leaky_paywall_initialize_stripe_api();

	$data = array(
		'invoice_settings' => array(
			'default_payment_method' => $paymentMethodId
		)
	);

	try {
		$payment_method = \Stripe\PaymentMethod::retrieve($paymentMethodId);
		$payment_method->attach(array('customer' => $customerId));

		$customer = \Stripe\Customer::retrieve($customerId);
		$customer->invoice_settings->default_payment_method = $paymentMethodId;
		$customer->save();
	} catch (\Throwable $th) {

		leaky_paywall_log('error 1', 'stripe checkout subscription');

		wp_send_json(array(
			'error'  => $th->jsonBody
		));
	}

	do_action('leaky_paywall_after_create_recurring_customer', $customer);

	$subscription_array = array(
		'customer' => $customerId,
		'items' => array(
			array(
				'plan' => $planId,
			),
		),
		'expand' => array('latest_invoice.payment_intent'),
	);

	$subscription_options = array(
		'idempotency_key' => $customerId
	);

	try {
		leaky_paywall_log('before get subs', 'stripe checkout subscription for ' . $customerId);
		leaky_paywall_log($customer, 'stripe checkout subscription for ' . $customerId);
		$subscriptions = $customer->subscriptions->all(array('limit' => '1')); // generating an error
		leaky_paywall_log('after get subs', 'stripe checkout subscription for ' . $customerId);

		if (empty($subscriptions->data)) {
			leaky_paywall_log('empty sub data', 'stripe checkout subscription for ' . $customerId);
			$subscription = \Stripe\Subscription::create(apply_filters('leaky_paywall_stripe_subscription_args', $subscription_array, $level, $fields), $subscription_options);
		} else {

			foreach ($subscriptions->data as $subscription) {
				$sub = $customer->subscriptions->retrieve($subscription->id);
				$sub->plan = $planId;
				do_action('leaky_paywall_before_update_stripe_subscription', $customer, $sub, $level);
				$sub->save();

				do_action('leaky_paywall_after_update_stripe_subscription', $customer, $sub, $level);
			}
		}
	} catch (\Throwable $th) {
		leaky_paywall_log('error 2', 'stripe checkout subscription');
		wp_send_json(array(
			'error'  => $th->jsonBody
		));
	}

	$return = array(
		'subscription'  => $subscription,
	);

	wp_send_json($return);
}

// https://stripe.com/docs/billing/subscriptions/fixed-price
add_action('wp_ajax_nopriv_leaky_paywall_retry_invoice_stripe_checkout_subscription', 'leaky_paywall_retry_invoice_stripe_checkout_subscription');
add_action('wp_ajax_leaky_paywall_retry_invoice_stripe_checkout_subscription', 'leaky_paywall_retry_invoice_stripe_checkout_subscription');

function leaky_paywall_retry_invoice_stripe_checkout_subscription()
{
	$level_id = $_POST['level_id'];
	$level = get_leaky_paywall_subscription_level($level_id);
	$customerId = $_POST['customerId'];
	$paymentMethodId = $_POST['paymentMethodId'];
	$invoiceId = $_POST['invoiceId'];
	$planId = $_POST['planId'];
	$form_data = $_POST['formData'];
	parse_str($form_data, $fields);

	$settings = get_leaky_paywall_settings();

	leaky_paywall_initialize_stripe_api();

	try {
		$payment_method = \Stripe\PaymentMethod::retrieve($paymentMethodId);
		$payment_method->attach(array('customer' => $customerId));

		$customer = \Stripe\Customer::retrieve($customerId);
		$customer->invoice_settings->default_payment_method = $paymentMethodId;
		$customer->save();
	} catch (\Throwable $th) {

		leaky_paywall_log($customerId, 'stripe error: retry invoice payment method');

		wp_send_json(array(
			'error'  => $th->jsonBody
		));
	}

	try {
		$invoice = \Stripe\Invoice::retrieve($invoiceId);
	} catch (\Throwable $th) {

		leaky_paywall_log($customerId, 'stripe error: retry invoice invoice');

		wp_send_json(array(
			'error'  => $th->jsonBody
		));
	}

	$return = array(
		'invoice'  => $invoice,
	);

	wp_send_json($return);
}

/**
 * Get Stripe Plan
 *
 * Gets the stripe plan associated with the level, and creates one if it doesn't exist
 *
 * @since       4.0.0
 * @param       array $level - the Leaky Paywall level
 * @param       int $level_id - the Leaky Paywall level id
 * @param       array $plan_args - the arguements for the plan
 * @return      obj - Stripe Plan object
 */
function leaky_paywall_get_stripe_plan($level, $level_id, $plan_args)
{

	$settings = get_leaky_paywall_settings();
	$stripe_plan = false;
	$match = false;
	$time = time();

	try {
		\Stripe\Stripe::setApiKey($plan_args['secret_key']);
	} catch (Exception $e) {
		return new WP_Error('missing_api_key', sprintf(__('Error processing request: %s', 'issuem-leaky-paywall'), $e->getMessage()));
	}

	if (!isset($level['plan_id'])) {
		$level['plan_id'] = array();
	}

	if (!is_array($level['plan_id'])) {
		$plan_temp = $level['plan_id'];
		$settings['levels'][$level_id]['plan_id'] = array($plan_temp);
		update_leaky_paywall_settings($settings);
	}

	if (!empty($level['plan_id'])) {

		foreach ((array)$level['plan_id'] as $plan_id) {

			//We need to verify that the plan_id matches the level details, otherwise we need to update it
			try {
				$stripe_plan = \Stripe\Plan::retrieve($plan_id);
			} catch (Exception $e) {
				$stripe_plan = false;
			}

			if (
				!is_object($stripe_plan) || //If we don't have a stripe plan
				( //or the stripe plan doesn't match...
					$plan_args['stripe_price']	!= $stripe_plan->amount
					|| $level['interval'] 		!= $stripe_plan->interval
					|| $level['interval_count'] != $stripe_plan->interval_count)
			) {
				// does not match
			} else {
				$match = $stripe_plan; // this plan matches, so send it back
				break;
			}
		}
	}

	if (!$match) {
		$stripe_plan = leaky_paywall_create_stripe_plan($level, $level_id, $plan_args);

		$settings['levels'][$level_id]['plan_id'][] = is_object($stripe_plan) ? $stripe_plan->id : false;
		update_leaky_paywall_settings($settings);
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
function leaky_paywall_create_stripe_plan($level, $level_id, $plan_args)
{

	$time = time();

	$args = array(
		'amount'            => esc_js($plan_args['stripe_price']),
		'interval'          => esc_js($level['interval']),
		'interval_count'    => esc_js($level['interval_count']),
		'name'              => esc_js(leaky_paywall_normalize_chars($level['label'])) . ' ' . $time,
		'currency'          => esc_js($plan_args['currency']),
		'id'                => sanitize_title_with_dashes(leaky_paywall_normalize_chars($level['label'])) . '-' . $time,
	);

	try {
		$stripe_plan = \Stripe\Plan::create(apply_filters('leaky_paywall_create_stripe_plan', $args, $level, $level_id));
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
function leaky_paywall_is_valid_stripe_subscription($subscription)
{

	$valid_status = apply_filters('leaky_paywall_valid_stripe_subscription_status', array('active'));

	if (in_array($subscription->status, $valid_status)) {
		return true;
	}

	return false;
}

/**
 * Initialize a call to the Stripe API with Leaky Paywall App Info
 *
 * @since 4.15.4
 */
function leaky_paywall_initialize_stripe_api()
{
	\Stripe\Stripe::setApiKey(leaky_paywall_get_stripe_secret_key());
	\Stripe\Stripe::setApiVersion(LEAKY_PAYWALL_STRIPE_API_VERSION);
	\Stripe\Stripe::setAppInfo(
		'WordPress Leaky Paywall',
		LEAKY_PAYWALL_VERSION,
		esc_url(site_url()),
		LEAKY_PAYWALL_STRIPE_PARTNER_ID
	);
}

/**
 * Get a stripe formatted price
 *
 * @since 4.16.2
 */
function leaky_paywall_get_stripe_amount($amount)
{

	if (in_array(strtoupper(leaky_paywall_get_currency()), array('BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF'))) {
		//Zero-Decimal Currencies
		//https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
		$stripe_price = number_format($amount, '0', '', '');
	} else {
		$stripe_price = number_format($amount, '2', '', ''); //no decimals
	}

	return $stripe_price;
}
