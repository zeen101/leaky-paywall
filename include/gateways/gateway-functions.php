<?php
/**
 * All helper functions used with Leaky Paywall payment gateways
 *
 * @package Leaky Paywall
 * @since 4.0.0
 */

/**
 * Load additional gateway include files
 *
 * @since       4.0.0
 */
function leaky_paywall_load_gateway_files() {
	foreach ( leaky_paywall_get_payment_gateways() as $key => $gateway ) {
		if ( file_exists( LEAKY_PAYWALL_PATH . 'include/gateways/' . $key . '/functions.php' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/gateways/' . $key . '/functions.php';
		}
	}
}
add_action( 'plugins_loaded', 'leaky_paywall_load_gateway_files', 9999 );

/**
 * Register default payment gateways
 *
 * @since       4.0.0
 * @return      array
 */
function leaky_paywall_get_payment_gateways() {
	$gateways = new Leaky_Paywall_Payment_Gateways();
	return $gateways->available_gateways;
}

/**
 * Send payment / subscription data to gateway
 *
 * @since 4.0.0
 *
 * @param string $gateway The gateway.
 * @param array  $subscription_data The subscription data.
 * @return array
 */
function leaky_paywall_send_to_gateway( $gateway, $subscription_data ) {

	// we don't have an actual gateway class for a free registration at this time, so we format the data as needed here.
	if ( 'free_registration' == $gateway ) {

		$free_level = get_leaky_paywall_subscription_level( $subscription_data['level_id'] );

		/**
		 * Filter the effective price of this registration.
		 *
		 * The gateway is supplied by the client, so this guard is what stops a
		 * forged free_registration submission from claiming a paid level. The
		 * stored level price is not authoritative on its own: extensions apply
		 * discounts further down the stack, so a paid level can legitimately
		 * resolve to $0 for a given registration (a 100% coupon, for example).
		 *
		 * Anything hooking this filter is part of that security boundary. Only
		 * reduce the price after validating the request data server-side —
		 * never on the presence of a POST field alone, since the whole request
		 * is attacker-controlled. Return the unmodified price to fail closed.
		 *
		 * @param float $price             The level's stored price.
		 * @param array $free_level        The subscription level.
		 * @param array $subscription_data The submitted subscription data.
		 */
		$effective_price = is_array( $free_level )
			? (float) apply_filters( 'leaky_paywall_free_registration_effective_price', (float) $free_level['price'], $free_level, $subscription_data )
			: null;

		if ( null === $effective_price || $effective_price >= 0.5 ) {
			leaky_paywall_errors()->add(
				'invalid_free_registration',
				__( 'This subscription level requires payment.', 'leaky-paywall' ),
				'register'
			);
			return array();
		}

		return array(
			'level_id'          => $subscription_data['level_id'],
			'subscriber_id'     => '',
			'subscriber_email'  => $subscription_data['user_email'],
			'existing_customer' => false,
			'price'             => 0,
			'description'       => $subscription_data['description'],
			'payment_gateway'   => 'free_registration',
			'payment_status'    => 'active',
			'interval'          => $subscription_data['interval'],
			'interval_count'    => $subscription_data['interval_count'],
			'site'              => $subscription_data['site'],
			'plan'              => $subscription_data['plan'],
			'recurring'         => false,
		);

	}

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$gateway  = $gateways->get_gateway( $gateway );

	$gateway = new $gateway['class']( $subscription_data );

	return $gateway->process_signup();

}

/**
 * Return list of active gateways
 *
 * @since       4.0.0
 *
 * @param integer $level_id The level id.
 * @return      array
 */
function leaky_paywall_get_enabled_payment_gateways( $level_id = '' ) {

	$gateways = new Leaky_Paywall_Payment_Gateways();

	foreach ( $gateways->enabled_gateways as $key => $gateway ) {

		if ( is_array( $gateway ) ) {

			$gateways->enabled_gateways[ $key ] = $gateway['label'];

		}
	}

	return apply_filters( 'leaky_paywall_enabled_gateways', $gateways->enabled_gateways, $level_id );
}


/**
 * Count subscribers on a payment gateway.
 *
 * @param array|string $gateway  Gateway slug, or slugs to count together.
 * @param array        $statuses Payment statuses to count. Defaults to the
 *                               statuses that represent a live subscription.
 * @return int
 */
function leaky_paywall_count_subscribers_by_gateway( $gateway, $statuses = array( 'active', 'pending_cancel', 'trial' ) ) {

	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	$meta_query = array(
		array(
			'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site,
			'value'   => (array) $gateway,
			'compare' => 'IN',
		),
	);

	if ( ! empty( $statuses ) ) {
		$meta_query[] = array(
			'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
			'value'   => (array) $statuses,
			'compare' => 'IN',
		);
	}

	$query = new WP_User_Query(
		array(
			'number'      => 1, // only the total is needed.
			'fields'      => 'ID',
			'count_total' => true,
			'meta_query'  => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	return (int) $query->get_total();
}

/**
 * Calls the load_fields() method for gateways when a gateway selection is made
 *
 * @access      public
 * @since       4.0.0
 *
 * @param array   $gateways The gateways.
 * @param integer $level_id The level id.
 */
function leaky_paywall_load_gateway_fields( $gateways, $level_id ) {

	foreach ( $gateways as $key => $gateway ) {

		$all_gateways = new Leaky_Paywall_Payment_Gateways();
		$gateway      = $all_gateways->get_gateway( $key );

		$gateway = new $gateway['class']();
		$gateway->init();

		// output is properly escaped in the fields method
		// phpcs:ignore
		echo $gateway->fields( $level_id );

	}

}
add_action( 'leaky_paywall_before_registration_submit_field', 'leaky_paywall_load_gateway_fields', 10, 2 );


/**
 * Load webhook processor for all gateways
 *
 * @access      public
 * @since       4.0.0
 * @return      void
 */
function leaky_paywall_process_gateway_webhooks() {

	$gateways = new Leaky_Paywall_Payment_Gateways();

	foreach ( $gateways->available_gateways  as $key => $gateway ) {

		if ( is_array( $gateway ) && isset( $gateway['class'] ) ) {

			$gateway = new $gateway['class']();
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

	if ( empty( $_GET['leaky-paywall-confirm'] ) ) {
		return;
	}

	$gateways = new Leaky_Paywall_Payment_Gateways();
	$gateway  = sanitize_text_field( wp_unslash( $_GET['leaky-paywall-confirm'] ) );

	if ( ! $gateways->is_gateway_enabled( $gateway ) ) {
		return;
	}

	$gateway = $gateways->get_gateway( $gateway );

	if ( is_array( $gateway ) && isset( $gateway['class'] ) ) {

		$gateway = new $gateway['class']();
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

	$gateways = new Leaky_Paywall_Payment_Gateways();

	foreach ( $gateways->enabled_gateways  as $key => $gateway ) {

		if ( is_array( $gateway ) && isset( $gateway['class'] ) ) {

			$gateway = new $gateway['class']();
			$gateway->scripts();

		}
	}

}
add_action( 'wp_enqueue_scripts', 'leaky_paywall_load_gateway_scripts', 100 );

/**
 * Add subscribe button to free level subcription cards
 *
 * @since  4.0.0
 *
 * @param string  $payment_options The payment options.
 * @param array   $level The level details.
 * @param integer $level_id The level id.
 * @return string   payment option output for subscription card
 */
function leaky_paywall_free_subscription_cards( $payment_options, $level, $level_id ) {

	if ( 0 != $level['price'] ) {
		return $payment_options;
	}

	$settings = get_leaky_paywall_settings();

	$output = '<div class="leaky-paywall-payment-button"><a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">' . __( 'Subscribe', 'leaky-paywall' ) . '</a></div>';

	return $payment_options . $output;
}
add_filter( 'leaky_paywall_subscription_options_payment_options', 'leaky_paywall_free_subscription_cards', 7, 3 );


/**
 * Filter checkout button text on the registration form
 *
 * @param string $method The gateway method.
 * @return string   button text
 */
function leaky_paywall_get_registration_checkout_button_text( $method = '' ) {

	if ( 'paypal' == $method ) {
		$text = __( 'Subscribe with Paypal', 'leaky-paywall' );
	} else {
		$text = __( 'Subscribe', 'leaky-paywall' );
	}

	return apply_filters( 'registration_checkout_button_text', $text );

}

/**
 * Claim a gateway event id so it is only processed once.
 *
 * Gateways retry any delivery they don't get a timely success response for, and
 * on multisite the same event can also reach more than one endpoint. Several
 * downstream effects are not idempotent (the SimpleCirc renewal handler adds
 * issues on every invoice.paid), so replays have to be dropped rather than
 * merely tolerated.
 *
 * The claim is stored as a site transient, which is network-wide on multisite
 * and therefore holds regardless of which site received the delivery or which
 * blog the request has switched to. It is deliberately short-lived: a handler
 * that fatals or times out leaves only the in-flight marker behind, which
 * expires and lets the gateway's own retry succeed. Call
 * leaky_paywall_complete_gateway_event() once the work is genuinely done to
 * extend the claim across the gateway's full retry window.
 *
 * @since 5.1.x
 *
 * @param string $event_id          The gateway's event id (e.g. a Stripe evt_ id).
 * @param int    $in_flight_seconds How long an unfinished claim is held. Default 5 minutes.
 * @return bool True if the caller owns this event and should process it, false if it is a replay.
 */
function leaky_paywall_claim_gateway_event( $event_id, $in_flight_seconds = 0 ) {

	global $wpdb;

	$event_id = (string) $event_id;

	// No stable id to dedupe on. Let the caller proceed rather than drop a
	// delivery that might be the only one.
	if ( '' === $event_id ) {
		return true;
	}

	if ( ! $in_flight_seconds ) {
		$in_flight_seconds = (int) apply_filters( 'leaky_paywall_gateway_event_in_flight_seconds', 5 * MINUTE_IN_SECONDS, $event_id );
	}

	$hash      = md5( $event_id );
	$lock_name = 'lp_evt_' . $hash;
	$key       = 'lp_evt_' . $hash;

	// Serialize concurrent deliveries of the same event: a gateway can retry
	// while the first attempt is still running.
	$got_lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );

	$already_claimed = (bool) get_site_transient( $key );

	if ( ! $already_claimed ) {
		set_site_transient( $key, 'processing', $in_flight_seconds );
	}

	if ( '1' === (string) $got_lock ) {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	return ! $already_claimed;
}

/**
 * Mark a claimed gateway event as fully processed.
 *
 * Extends the claim from leaky_paywall_claim_gateway_event() across the
 * gateway's retry window so a later retry of work we already did is dropped.
 * Only call this when the event was actually handled. Leaving the short
 * in-flight claim in place is the right outcome for an event we could not act
 * on yet (an unmatched customer, say), because the gateway's retry may find the
 * record it was waiting for.
 *
 * @since 5.1.x
 *
 * @param string $event_id The gateway's event id.
 * @param int    $ttl      How long to remember it. Default 3 days, which covers Stripe's retry schedule.
 * @return void
 */
function leaky_paywall_complete_gateway_event( $event_id, $ttl = 0 ) {

	$event_id = (string) $event_id;

	if ( '' === $event_id ) {
		return;
	}

	if ( ! $ttl ) {
		$ttl = (int) apply_filters( 'leaky_paywall_gateway_event_ttl', 3 * DAY_IN_SECONDS, $event_id );
	}

	set_site_transient( 'lp_evt_' . md5( $event_id ), 'done', $ttl );
}
