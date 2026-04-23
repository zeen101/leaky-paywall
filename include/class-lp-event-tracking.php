<?php
/**
 * LP Event Tracking
 *
 * Sends subscriber lifecycle events to the Leaky Paywall Insights platform.
 *
 * @package Leaky Paywall
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Event_Tracking {

	/**
	 * Singleton instance.
	 *
	 * @var LP_Event_Tracking|null
	 */
	private static $instance = null;

	/**
	 * Insights API base URL.
	 */
	private $api_url = 'https://insights.leakypaywall.com';

	/**
	 * Queued content-viewed / paywall-displayed events to batch-send on shutdown.
	 *
	 * @var array
	 */
	private $queued_events = array();

	/**
	 * Previous level IDs captured during `leaky_paywall_level_transition` so they
	 * can be attached as `previous_level` to the subsequent `Subscription Started`
	 * event fired by `on_new_subscriber`. Keyed by user ID.
	 *
	 * @var array<int, string>
	 */
	private $pending_previous_levels = array();

	public function __construct() {

		self::$instance = $this;

		$this->api_url = apply_filters( 'leaky_paywall_insights_api_url', $this->api_url );

		// Migrate from external plugin option if needed.
		$this->maybe_migrate_api_key();

		// AJAX handler for test connection.
		add_action( 'wp_ajax_lp_test_insights_connection', array( $this, 'ajax_test_connection' ) );

		// Bail early if not configured.
		if ( ! $this->is_configured() ) {
			return;
		}

		// Checkout started — fires when the registration form's payment section renders.
		add_action( 'leaky_paywall_before_registration_submit_field', array( $this, 'on_checkout_started' ), 10, 2 );

		// Subscription lifecycle.
		add_action( 'leaky_paywall_new_subscriber', array( $this, 'on_new_subscriber' ), 10, 6 );
		add_action( 'leaky_paywall_update_subscriber', array( $this, 'on_update_subscriber' ), 10, 5 );
		add_action( 'leaky_paywall_cancelled_subscriber', array( $this, 'on_cancelled_subscriber' ), 10, 2 );

		// Level and status transitions.
		add_action( 'leaky_paywall_level_transition', array( $this, 'on_level_transition' ), 10, 4 );
		add_action( 'leaky_paywall_status_transition', array( $this, 'on_status_transition' ), 10, 4 );

		// Payments.
		add_action( 'leaky_paywall_failed_payment', array( $this, 'on_failed_payment' ), 10, 1 );
		add_action( 'leaky_paywall_stripe_charge_succeeded', array( $this, 'on_payment_succeeded' ), 10, 2 );
		add_action( 'leaky_paywall_authorizenet_signup', array( $this, 'on_authorizenet_payment' ), 10, 1 );
		add_action( 'leaky_paywall_after_authorizenet_renewal', array( $this, 'on_authorizenet_renewal' ), 10, 2 );
		add_action( 'leaky_paywall_net_authorize_customer_subscription_failed', array( $this, 'on_authorizenet_failed' ), 10, 2 );

		// Content viewed & paywall displayed (batched).
		add_filter( 'leaky_paywall_current_user_can_access', array( $this, 'on_content_viewed' ), 10, 2 );
		add_action( 'leaky_paywall_is_restricted_content', array( $this, 'on_paywall_displayed' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush_queued_events' ) );

		// Login.
		add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );

		// UTM capture.
		add_action( 'wp_footer', array( $this, 'output_utm_capture_script' ) );
	}

	// ──────────────────────────────────────────────────────────────────────
	// Migration
	// ──────────────────────────────────────────────────────────────────────
	// Public API
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Get the singleton instance.
	 *
	 * @return LP_Event_Tracking|null
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Track a custom event.
	 *
	 * Use this from any LP extension to send a custom event to Insights.
	 *
	 * @param string       $event_name Event name, e.g. "Gift Subscription Purchased".
	 * @param WP_User|int  $user       WP_User object or user ID.
	 * @param array        $properties Optional event properties.
	 * @return bool True if the event was sent, false otherwise.
	 */
	public function track( $event_name, $user, $properties = array() ) {
		if ( ! $this->is_configured() ) {
			return false;
		}

		if ( is_int( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}

		$subscriber_data = $this->get_subscriber_data( $user );

		if ( ! $subscriber_data ) {
			// Build minimal data for users without LP meta.
			$subscriber_data = array(
				'email'        => $user->user_email,
				'wp_user_id'   => $user->ID,
				'display_name' => $user->display_name,
				'level_name'   => '',
				'status'       => 'unknown',
			);
		}

		$this->send_event( $event_name, $subscriber_data, $properties );
		return true;
	}

	// ──────────────────────────────────────────────────────────────────────
	// Migration
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Migrate API key from the external leaky-paywall-events plugin.
	 */
	private function maybe_migrate_api_key() {
		$old_key = get_option( 'lpe_api_token' );

		if ( ! $old_key ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		if ( ! empty( $settings['insights_api_key'] ) ) {
			return;
		}

		$settings['insights_api_key'] = sanitize_text_field( $old_key );
		update_leaky_paywall_settings( $settings );
		delete_option( 'lpe_api_token' );
	}

	// ──────────────────────────────────────────────────────────────────────
	// AJAX
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * AJAX handler for testing the Insights API connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'lp_test_insights', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'leaky-paywall' ) ) );
		}

		$result = $this->test_connection();
		wp_send_json( $result );
	}

	/**
	 * Send a test event to the Insights API.
	 *
	 * @return array Array with 'type' (success|error) and 'message'.
	 */
	public function test_connection() {
		$response = $this->send_events(
			array(
				array(
					'name'        => 'Connection Test',
					'occurred_at' => gmdate( 'c' ),
				),
			),
			true
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Connection failed: ', 'leaky-paywall' ) . $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 201 === $code ) {
			return array(
				'type'    => 'success',
				'message' => __( 'Connection successful! Test event sent.', 'leaky-paywall' ),
			);
		}

		if ( 401 === $code ) {
			return array(
				'type'    => 'error',
				'message' => __( 'Authentication failed. Check your API key.', 'leaky-paywall' ),
			);
		}

		return array(
			'type'    => 'error',
			/* translators: %d: HTTP response code */
			'message' => sprintf( __( 'Unexpected response (HTTP %d).', 'leaky-paywall' ), $code ),
		);
	}

	// ──────────────────────────────────────────────────────────────────────
	// Event Hooks
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Checkout form loaded by a logged-in user.
	 *
	 * Fires on the `leaky_paywall_before_registration_submit_field` action,
	 * which runs just before the payment gateway fields render. This gives
	 * us a signal that the user reached the payment step of checkout.
	 *
	 * Deduped to once per user per day via transient to keep the data clean.
	 *
	 * @param array $gateways Available gateways (unused).
	 * @param int   $level_id The selected level ID.
	 */
	public function on_checkout_started( $gateways, $level_id ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$transient_key = 'lp_checkout_started_' . $user->ID . '_' . intval( $level_id );
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, DAY_IN_SECONDS );

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			$subscriber_data = array(
				'id'    => $user->ID,
				'email' => $user->user_email,
			);
		}

		$current_level_id = leaky_paywall_subscriber_current_level_id( $user );
		$level            = get_leaky_paywall_subscription_level( $level_id );

		$properties = array_merge(
			array(
				'level_id'   => intval( $level_id ),
				'level_name' => $this->get_level_name( $level_id ),
				'price'      => isset( $level['price'] ) ? (float) $level['price'] : 0,
				'currency'   => $this->get_currency(),
				'is_upgrade' => $current_level_id !== false && intval( $current_level_id ) !== intval( $level_id ),
			),
			$this->get_utm_properties()
		);

		$this->send_event( 'Checkout Started', $subscriber_data, $properties );
	}

	/**
	 * New subscriber created (Registration + Subscription Started).
	 */
	public function on_new_subscriber( $user_id, $email, $meta, $customer_id, $meta_args, $user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$level_id = isset( $meta['level_id'] ) ? $meta['level_id'] : '';

		$properties = array_merge(
			array(
				'gateway'  => isset( $meta['payment_gateway'] ) ? $meta['payment_gateway'] : '',
				'amount'   => isset( $meta['price'] ) ? (float) $meta['price'] : 0,
				'currency' => $this->get_currency(),
				'plan'     => $this->get_level_name( $level_id ),
				'status'   => isset( $meta['payment_status'] ) ? $meta['payment_status'] : '',
			),
			$this->get_recurring_properties( $level_id ),
			$this->get_utm_properties()
		);

		// If this registration replaced an existing level, include the previous
		// level so Insights can identify free→paid (or any) conversions.
		if ( isset( $this->pending_previous_levels[ $user_id ] ) ) {
			$previous_level_id = $this->pending_previous_levels[ $user_id ];
			unset( $this->pending_previous_levels[ $user_id ] );

			$previous_level_name = $this->get_level_name( $previous_level_id );
			if ( '' !== $previous_level_name ) {
				$properties['previous_level']    = $previous_level_name;
				$properties['previous_level_id'] = $previous_level_id;
			}
		}

		$this->send_event( 'Registration', $subscriber_data, $properties );
		$this->send_event( 'Subscription Started', $subscriber_data, $properties );
	}

	/**
	 * Existing subscriber updated (Subscription Renewed).
	 */
	public function on_update_subscriber( $user_id, $email, $meta, $customer_id, $meta_args ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$level_id = isset( $meta['level_id'] ) ? $meta['level_id'] : '';

		$properties = array_merge(
			array(
				'gateway'  => isset( $meta['payment_gateway'] ) ? $meta['payment_gateway'] : '',
				'amount'   => isset( $meta['price'] ) ? (float) $meta['price'] : 0,
				'currency' => $this->get_currency(),
				'plan'     => $this->get_level_name( $level_id ),
				'status'   => isset( $meta['payment_status'] ) ? $meta['payment_status'] : '',
			),
			$this->get_recurring_properties( $level_id )
		);

		$this->send_event( 'Subscription Renewed', $subscriber_data, $properties );
	}

	/**
	 * Subscriber cancelled.
	 */
	public function on_cancelled_subscriber( $user, $gateway ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'gateway' => $gateway,
			'plan'    => $subscriber_data['level_name'],
		);

		$this->send_event( 'Subscription Cancelled', $subscriber_data, $properties );
	}

	/**
	 * Subscriber level changed.
	 */
	public function on_level_transition( $new_level_id, $old_level_id, $user_id, $source ) {
		// Registration-sourced transitions run immediately before the
		// `leaky_paywall_new_subscriber` hook fires. Stash the old level so
		// on_new_subscriber can attach it to the Subscription Started event.
		if ( 'registration' === $source && '' !== $old_level_id ) {
			$this->pending_previous_levels[ $user_id ] = $old_level_id;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'previous_level'    => $this->get_level_name( $old_level_id ),
			'new_level'         => $this->get_level_name( $new_level_id ),
			'previous_level_id' => $old_level_id,
			'new_level_id'      => $new_level_id,
			'source'            => $source,
		);

		$this->send_event( 'Level Changed', $subscriber_data, $properties );
	}

	/**
	 * Subscriber status changed.
	 */
	public function on_status_transition( $new_status, $old_status, $user_id, $source ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'previous_status' => $old_status,
			'new_status'      => $new_status,
			'source'          => $source,
		);

		$this->send_event( 'Status Changed', $subscriber_data, $properties );
	}

	/**
	 * Payment failed.
	 */
	public function on_failed_payment( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		// Deduplicate — Stripe sends charge.failed and invoice.payment_failed for the same failure.
		$transient_key = 'lp_event_payment_failed_' . $user->ID;
		if ( get_transient( $transient_key ) ) {
			return;
		}
		set_transient( $transient_key, 1, 5 * MINUTE_IN_SECONDS );

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'gateway'  => $subscriber_data['gateway'],
			'amount'   => ! empty( $subscriber_data['price'] ) ? (float) $subscriber_data['price'] : 0,
			'currency' => $this->get_currency(),
			'plan'     => $subscriber_data['level_name'],
		);

		$this->send_event( 'Payment Failed', $subscriber_data, $properties );
	}

	/**
	 * Stripe payment succeeded.
	 */
	public function on_payment_succeeded( $user, $stripe_object ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'gateway'  => 'stripe',
			'amount'   => isset( $stripe_object->amount ) ? $stripe_object->amount / 100 : 0,
			'currency' => isset( $stripe_object->currency ) ? strtoupper( $stripe_object->currency ) : $this->get_currency(),
			'plan'     => $subscriber_data['level_name'],
		);

		$this->send_event( 'Payment Succeeded', $subscriber_data, $properties );
	}

	/**
	 * Authorize.net initial payment.
	 */
	public function on_authorizenet_payment( $gateway_data ) {
		$email = ! empty( $gateway_data['subscriber_email'] ) ? $gateway_data['subscriber_email'] : '';
		$user  = get_user_by( 'email', $email );

		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'gateway'  => 'authorizenet',
			'amount'   => isset( $gateway_data['price'] ) ? floatval( $gateway_data['price'] ) : 0,
			'currency' => ! empty( $gateway_data['currency'] ) ? strtoupper( $gateway_data['currency'] ) : $this->get_currency(),
			'plan'     => $subscriber_data['level_name'],
		);

		$this->send_event( 'Payment Succeeded', $subscriber_data, $properties );
	}

	/**
	 * Authorize.net renewal payment via webhook.
	 */
	public function on_authorizenet_renewal( $user, $webhook_data ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$amount = 0;
		if ( ! empty( $webhook_data['payload']['authAmount'] ) ) {
			$amount = floatval( $webhook_data['payload']['authAmount'] );
		}

		$properties = array(
			'gateway'  => 'authorizenet',
			'amount'   => $amount,
			'currency' => $this->get_currency(),
			'plan'     => $subscriber_data['level_name'],
		);

		$this->send_event( 'Payment Succeeded', $subscriber_data, $properties );
	}

	/**
	 * Authorize.net subscription failed via webhook.
	 */
	public function on_authorizenet_failed( $user, $webhook_data ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'gateway'  => 'authorizenet',
			'amount'   => 0,
			'currency' => $this->get_currency(),
			'plan'     => $subscriber_data['level_name'],
		);

		$this->send_event( 'Payment Failed', $subscriber_data, $properties );
	}

	/**
	 * Content viewed — queued for batch sending on shutdown.
	 *
	 * @param mixed $can_access Unchanged access result.
	 * @param int   $post_id    Post ID.
	 * @return mixed
	 */
	public function on_content_viewed( $can_access, $post_id ) {
		if ( ! $can_access || ! is_user_logged_in() ) {
			return $can_access;
		}

		$user = wp_get_current_user();
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return $can_access;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return $can_access;
		}

		$category = $this->get_post_category( $post );

		$this->queued_events[] = $this->build_event_payload( 'Content Viewed', $subscriber_data, array(
			'post_id'    => $post->ID,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'category'   => $category,
			'url'        => get_permalink( $post->ID ),
		) );

		return $can_access;
	}

	/**
	 * Paywall displayed — queued for batch sending on shutdown.
	 */
	public function on_paywall_displayed( $post_id, $nag_type = 'subscribe' ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$category = $this->get_post_category( $post );

		$nag_label = $nag_type;
		if ( is_string( $nag_type ) && 0 === strpos( $nag_type, 'targeted:' ) ) {
			$nag_id    = (int) substr( $nag_type, 9 );
			$nag_title = get_the_title( $nag_id );
			$nag_label = $nag_title ? $nag_title : $nag_type;
		}

		$this->queued_events[] = $this->build_event_payload( 'Paywall Displayed', $subscriber_data, array(
			'post_id'    => $post->ID,
			'post_title' => $post->post_title,
			'post_type'  => $post->post_type,
			'category'   => $category,
			'url'        => get_permalink( $post->ID ),
			'nag_type'   => $nag_label,
		) );
	}

	/**
	 * WordPress login.
	 */
	public function on_login( $user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}

		$subscriber_data = $this->get_subscriber_data( $user );
		if ( ! $subscriber_data ) {
			return;
		}

		$properties = array(
			'login_url' => wp_get_referer() ? wp_get_referer() : '',
		);

		$this->send_event( 'Login', $subscriber_data, $properties );
	}

	/**
	 * Flush queued events on shutdown.
	 */
	public function flush_queued_events() {
		if ( empty( $this->queued_events ) ) {
			return;
		}

		$this->send_events( $this->queued_events );
		$this->queued_events = array();
	}

	// ──────────────────────────────────────────────────────────────────────
	// Helpers
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Extract subscriber data from a WP_User using Leaky Paywall meta.
	 *
	 * @param WP_User $user WordPress user.
	 * @return array|null Subscriber data or null if no LP level.
	 */
	private function get_subscriber_data( $user ) {
		$mode = function_exists( 'leaky_paywall_get_current_mode' ) ? leaky_paywall_get_current_mode() : 'live';
		$site = function_exists( 'leaky_paywall_get_current_site' ) ? leaky_paywall_get_current_site() : '';

		$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

		if ( '' === $level_id || false === $level_id ) {
			return null;
		}

		$status  = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
		$gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
		$price   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true );

		return array(
			'email'        => $user->user_email,
			'wp_user_id'   => $user->ID,
			'display_name' => $user->display_name,
			'level_name'   => $this->get_level_name( $level_id ),
			'status'       => $status ? $status : 'unknown',
			'gateway'      => $gateway ? $gateway : '',
			'price'        => $price ? $price : '',
		);
	}

	/**
	 * Get recurring-billing properties for a subscription level.
	 *
	 * Pulls the billing interval, interval count, and subscription length type
	 * from the LP level config so the Insights app can compute MRR/ARR.
	 *
	 * @param int|string $level_id The level ID.
	 * @return array Recurring properties, or empty array if unavailable.
	 */
	private function get_recurring_properties( $level_id ) {
		if ( '' === $level_id || ! function_exists( 'get_leaky_paywall_subscription_level' ) ) {
			return array();
		}

		$level = get_leaky_paywall_subscription_level( $level_id );

		if ( ! is_array( $level ) ) {
			return array();
		}

		return array(
			'interval'                 => isset( $level['interval'] ) ? $level['interval'] : '',
			'interval_count'           => isset( $level['interval_count'] ) ? (int) $level['interval_count'] : 0,
			'subscription_length_type' => isset( $level['subscription_length_type'] ) ? $level['subscription_length_type'] : '',
		);
	}

	/**
	 * Get the human-readable level name from a level ID.
	 */
	private function get_level_name( $level_id ) {
		if ( '' === $level_id || ! function_exists( 'get_leaky_paywall_subscription_level' ) ) {
			return '';
		}

		$level = get_leaky_paywall_subscription_level( $level_id );

		return isset( $level['label'] ) ? $level['label'] : '';
	}

	/**
	 * Get the site currency.
	 */
	private function get_currency() {
		if ( function_exists( 'leaky_paywall_get_currency' ) ) {
			return leaky_paywall_get_currency();
		}

		return 'USD';
	}

	/**
	 * Get the primary category for a post.
	 */
	private function get_post_category( $post ) {
		if ( 'article' === $post->post_type ) {
			$terms = get_the_terms( $post->ID, 'issuem_issue_categories' );
			return ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0]->name : '';
		}

		$categories = get_the_category( $post->ID );
		return ! empty( $categories ) ? $categories[0]->name : '';
	}

	/**
	 * Build an event payload array.
	 */
	private function build_event_payload( $name, $subscriber_data, $properties = array() ) {
		$payload = array(
			'name'         => $name,
			'occurred_at'  => gmdate( 'c' ),
			'email'        => $subscriber_data['email'],
			'wp_user_id'   => $subscriber_data['wp_user_id'],
			'display_name' => $subscriber_data['display_name'],
			'level_name'   => isset( $subscriber_data['level_name'] ) ? $subscriber_data['level_name'] : '',
			'status'       => isset( $subscriber_data['status'] ) ? $subscriber_data['status'] : 'unknown',
		);

		if ( ! empty( $properties ) ) {
			$payload['properties'] = array_filter( $properties, array( $this, 'filter_empty_values' ) );
		}

		return $payload;
	}

	/**
	 * Filter callback for removing empty values from properties.
	 */
	private function filter_empty_values( $value ) {
		return '' !== $value && null !== $value;
	}

	/**
	 * Output a small JS snippet in the footer that captures UTM params from the
	 * current URL and stores them in a 30-day cookie (lp_utm). Existing cookie
	 * values are only overwritten when new UTM params are present in the URL,
	 * so the first-touch source that brought the visitor is preserved until conversion.
	 */
	public function output_utm_capture_script() {
		$settings = get_leaky_paywall_settings();
		if ( 'on' !== ( $settings['insights_enable_utm_capture'] ?? 'on' ) ) {
			return;
		}
		?>
		<script>
		(function () {
			var params = new URLSearchParams(window.location.search);
			var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
			var data = {};
			keys.forEach(function (k) { var v = params.get(k); if (v) { data[k] = v; } });
			if (!Object.keys(data).length) { return; }
			var expires = new Date();
			expires.setDate(expires.getDate() + 30);
			document.cookie = 'lp_utm=' + encodeURIComponent(JSON.stringify(data))
				+ '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
		})();
		</script>
		<?php
	}

	/**
	 * Read UTM parameters from the lp_utm cookie set by output_utm_capture_script().
	 *
	 * @return array<string, string> Sanitized UTM properties, or an empty array.
	 */
	private function get_utm_properties() {
		if ( empty( $_COOKIE['lp_utm'] ) ) {
			return array();
		}

		$decoded = json_decode( stripslashes( $_COOKIE['lp_utm'] ), true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$allowed  = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
		$filtered = array();

		foreach ( $allowed as $key ) {
			if ( ! empty( $decoded[ $key ] ) ) {
				$filtered[ $key ] = sanitize_text_field( $decoded[ $key ] );
			}
		}

		return $filtered;
	}

	/**
	 * Send a single event immediately.
	 */
	private function send_event( $name, $subscriber_data, $properties = array() ) {
		$event = $this->build_event_payload( $name, $subscriber_data, $properties );
		$this->send_events( array( $event ) );
	}

	/**
	 * Send one or more events to the Insights API.
	 *
	 * @param array $events   Array of event payloads.
	 * @param bool  $blocking Whether to wait for a response.
	 * @return array|WP_Error|null Response, error, or null if non-blocking.
	 */
	private function send_events( $events, $blocking = false ) {
		$settings = get_leaky_paywall_settings();
		$token    = isset( $settings['insights_api_key'] ) ? $settings['insights_api_key'] : '';

		if ( ! $token ) {
			return null;
		}

		$response = wp_remote_post( $this->api_url . '/api/v1/events', array(
			'headers'  => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'     => wp_json_encode( array( 'events' => $events ) ),
			'timeout'  => $blocking ? 15 : 1,
			'blocking' => $blocking,
		) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_wp_error( $response ) ) {
			error_log( 'LP Event Tracking: ' . $response->get_error_message() );
		}

		return $blocking ? $response : null;
	}

	/**
	 * Check if event tracking is configured with an API key.
	 */
	private function is_configured() {
		$settings = get_leaky_paywall_settings();
		return ! empty( $settings['insights_api_key'] );
	}
}

new LP_Event_Tracking();

/**
 * Send a custom event to Leaky Paywall Insights.
 *
 * Usage:
 *   leaky_paywall_track_event( 'Gift Subscription Purchased', $user_id, array(
 *       'gateway' => 'stripe',
 *       'amount'  => 29.99,
 *       'plan'    => 'Annual Gift',
 *   ) );
 *
 * @param string       $event_name Event name.
 * @param WP_User|int  $user       WP_User object or user ID.
 * @param array        $properties Optional event properties.
 * @return bool True if the event was sent, false otherwise.
 */
function leaky_paywall_track_event( $event_name, $user, $properties = array() ) {
	$tracker = LP_Event_Tracking::instance();

	if ( ! $tracker ) {
		return false;
	}

	return $tracker->track( $event_name, $user, $properties );
}
