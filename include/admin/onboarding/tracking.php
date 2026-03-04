<?php

/**
 * Leaky Paywall - Usage Tracking
 *
 * Collects anonymous usage data and sends it to the Leaky Paywall app
 * when the site admin has opted in during onboarding.
 *
 * @package Leaky Paywall
 * @since   4.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEAKY_PAYWALL_TRACKING_URL' ) ) {
	define( 'LEAKY_PAYWALL_TRACKING_URL', 'https://app.leakypaywall.com' );
}

/**
 * Allow manual trigger for testing: ?leaky_paywall_tracking_send=1
 */
add_action(
	'admin_notices',
	function () {
		if ( isset( $_GET['leaky_paywall_tracking_send'] ) && current_user_can( 'manage_options' ) ) {
			leaky_paywall_tracking_send();
		}
	}
);

/**
 * Hook into the weekly cron event to send tracking data.
 */
add_action( 'leaky_paywall_tracking_send', 'leaky_paywall_tracking_send' );

/**
 * Collect and send usage tracking data to the Leaky Paywall app.
 */
function leaky_paywall_tracking_send() {

	$allow_tracking = get_option( 'leaky_paywall_tracking_allow' );
	if ( ! $allow_tracking ) {
		return;
	}

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$data      = leaky_paywall_tracking_collect_data();
	$site_url  = get_bloginfo( 'url' );
	$site_hash = md5( $site_url );

	$tracking_url = trailingslashit( LEAKY_PAYWALL_TRACKING_URL ) . 'api/v1/tracking';

	$response = wp_remote_post(
		$tracking_url,
		array(
			'timeout'   => 30,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'      => wp_json_encode( array(
				'site_hash' => $site_hash,
				'site_url'  => $site_url,
				'data'      => $data,
			) ),
		)
	);

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_wp_error( $response ) ) {
		error_log( 'Leaky Paywall tracking error: ' . $response->get_error_message() );
	}

	if ( ! is_wp_error( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $body->api_key ) ) {
			$settings = get_leaky_paywall_settings();
			if ( empty( $settings['lp_app_api_key'] ) || $settings['lp_app_api_key'] !== $body->api_key ) {
				$settings['lp_app_api_key'] = sanitize_text_field( $body->api_key );
				update_leaky_paywall_settings( $settings );
			}
		}
	}
}

/**
 * Collect all tracking data points.
 *
 * @return array Array of key/value pairs for the tracking API.
 */
function leaky_paywall_tracking_collect_data() {

	$data = array();

	// System information.
	$data[] = array( 'key' => 'php_version', 'value' => phpversion() );
	$data[] = array( 'key' => 'wp_version', 'value' => get_bloginfo( 'version' ) );
	$data[] = array( 'key' => 'environment', 'value' => wp_get_environment_type() );
	$data[] = array( 'key' => 'multisite', 'value' => is_multisite() ? '1' : '0' );
	$data[] = array( 'key' => 'locale', 'value' => get_locale() );

	// Theme.
	$theme  = wp_get_theme();
	$data[] = array( 'key' => 'theme', 'value' => $theme->parent() ? $theme->parent()->get( 'Name' ) : $theme->get( 'Name' ) );

	// Plugin version.
	if ( defined( 'LEAKY_PAYWALL_VERSION' ) ) {
		$data[] = array( 'key' => 'lp_version', 'value' => LEAKY_PAYWALL_VERSION );
	}

	// Onboarding data.
	$country = get_option( 'leaky_paywall_country' );
	if ( $country ) {
		$data[] = array( 'key' => 'country', 'value' => $country );
	}

	$publication_types = get_option( 'leaky_paywall_publication_types' );
	if ( is_array( $publication_types ) && ! empty( $publication_types ) ) {
		$data[] = array( 'key' => 'publication_types', 'value' => implode( ', ', $publication_types ) );
	}

	// Installation date.
	$install_date = get_option( 'leaky_paywall_onboarding_install_time' );
	if ( $install_date ) {
		$data[] = array( 'key' => 'install_date', 'value' => (string) $install_date );
	}

	// Active plugins count.
	$active_plugins = get_option( 'active_plugins', array() );
	$data[]         = array( 'key' => 'active_plugins_count', 'value' => (string) count( $active_plugins ) );

	// Leaky Paywall add-ons.
	$lp_addons = leaky_paywall_tracking_get_addons();
	if ( ! empty( $lp_addons ) ) {
		$data[] = array( 'key' => 'lp_addons', 'value' => implode( ', ', $lp_addons ) );
	}

	// Admin email.
	$data[] = array( 'key' => 'admin_email', 'value' => get_option( 'admin_email' ) );

	// Subscription level count.
	$levels = leaky_paywall_get_levels();
	$data[] = array( 'key' => 'level_count', 'value' => (string) count( $levels ) );

	// Total registered users.
	$user_counts = count_users();
	$data[] = array( 'key' => 'total_users', 'value' => (string) $user_counts['total_users'] );

	// Transaction count.
	$transaction_counts = wp_count_posts( 'lp_transaction' );
	$data[] = array( 'key' => 'transaction_count', 'value' => (string) ( $transaction_counts->publish ?? 0 ) );

	// Active payment gateways.
	$settings = get_leaky_paywall_settings();
	if ( ! empty( $settings['payment_gateway'] ) && is_array( $settings['payment_gateway'] ) ) {
		$data[] = array( 'key' => 'payment_gateways', 'value' => implode( ', ', $settings['payment_gateway'] ) );
	}

	return $data;
}

/**
 * Get list of active Leaky Paywall add-on plugins.
 *
 * @return array List of add-on folder names.
 */
function leaky_paywall_tracking_get_addons() {

	$addons      = array();
	$all_plugins = get_plugins();

	foreach ( $all_plugins as $plugin_path => $plugin_data ) {
		$folder_name = explode( '/', $plugin_path )[0];
		if (
			strpos( $folder_name, 'leaky-paywall' ) === 0
			&& 'leaky-paywall' !== $folder_name
			&& is_plugin_active( $plugin_path )
		) {
			$addons[] = str_replace( 'leaky-paywall-', '', $folder_name );
		}
	}

	return $addons;
}
