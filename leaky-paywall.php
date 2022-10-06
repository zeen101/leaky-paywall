<?php
/**
 * Main PHP file used to for initial calls to Leaky Paywall classes and functions.
 *
 * @package Leaky Paywall
 * @since 1.0.0
 */

/*
Plugin Name: Leaky Paywall
Plugin URI: https://leakypaywall.com/
Description: The first and most flexible metered paywall for WordPress. Sell subscriptions without sacrificing search and social visibility.
Author: Leaky Paywall
Version: 4.18.6
Author URI: https://leakypaywall.com/
Tags: paywall, subscriptions, metered, membership, pay wall, content monetization, metered access, metered pay wall, paid content
Text Domain: leaky-paywall
Domain Path: /i18n
Requires at least: 5.6
Requires PHP: 7.0
*/

// Define global variables...
if ( ! defined( 'ZEEN101_STORE_URL' ) ) {
	define( 'ZEEN101_STORE_URL', 'https://zeen101.com' );
}

define( 'LEAKY_PAYWALL_NAME', 'Leaky Paywall for WordPress' );
define( 'LEAKY_PAYWALL_SLUG', 'leaky-paywall' );
define( 'LEAKY_PAYWALL_VERSION', '4.18.6' );
define( 'LEAKY_PAYWALL_DB_VERSION', '1.0.5' );
define( 'LEAKY_PAYWALL_URL', plugin_dir_url( __FILE__ ) );
define( 'LEAKY_PAYWALL_PATH', plugin_dir_path( __FILE__ ) );
define( 'LEAKY_PAYWALL_BASENAME', plugin_basename( __FILE__ ) );
define( 'LEAKY_PAYWALL_REL_DIR', dirname( LEAKY_PAYWALL_BASENAME ) );

define( 'LEAKY_PAYWALL_STRIPE_API_VERSION', '2020-03-02' );
define( 'LEAKY_PAYWALL_STRIPE_PARTNER_ID', 'pp_partner_IDILs8UMcYIw41' );

if ( ! defined( 'PAYPAL_LIVE_URL' ) ) {
	define( 'PAYPAL_LIVE_URL', 'https://www.paypal.com/' );
}
if ( ! defined( 'PAYPAL_SANDBOX_URL' ) ) {
	define( 'PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/' );
}
if ( ! defined( 'PAYPAL_PAYMENT_SANDBOX_URL' ) ) {
	define( 'PAYPAL_PAYMENT_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr' );
}
if ( ! defined( 'PAYPAL_PAYMENT_LIVE_URL' ) ) {
	define( 'PAYPAL_PAYMENT_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr' );
}
if ( ! defined( 'PAYPAL_NVP_API_SANDBOX_URL' ) ) {
	define( 'PAYPAL_NVP_API_SANDBOX_URL', 'https://api-3t.sandbox.paypal.com/nvp' );
}
if ( ! defined( 'PAYPAL_NVP_API_LIVE_URL' ) ) {
	define( 'PAYPAL_NVP_API_LIVE_URL', 'https://api-3t.paypal.com/nvp' );
}

/**
 * Instantiate Pigeon Pack class, require helper files
 *
 * @since 1.0.0
 */
function leaky_paywall_plugins_loaded() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( is_plugin_active( 'issuem/issuem.php' ) ) {
		define( 'ACTIVE_ISSUEM', true );
	} else {
		define( 'ACTIVE_ISSUEM', false );
	}

	require_once 'class.php';

	// Instantiate the Pigeon Pack class.
	if ( class_exists( 'Leaky_Paywall' ) ) {

		global $leaky_paywall;
		$leaky_paywall = new Leaky_Paywall();

		require_once LEAKY_PAYWALL_PATH . 'functions.php';
		require_once LEAKY_PAYWALL_PATH . 'shortcodes.php';
		require_once LEAKY_PAYWALL_PATH . 'subscriber-table.php';
		require_once LEAKY_PAYWALL_PATH . 'metaboxes.php';
		require_once LEAKY_PAYWALL_PATH . 'include/template-functions.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/dashboard-widgets.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/lp-transaction.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/lp-incomplete-user.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/tools.php';

		require_once LEAKY_PAYWALL_PATH . 'include/admin/settings/settings.php';

		include LEAKY_PAYWALL_PATH . 'include/license-key.php';
		include LEAKY_PAYWALL_PATH . 'include/error-tracking.php';
		include LEAKY_PAYWALL_PATH . 'include/registration-functions.php';
		include LEAKY_PAYWALL_PATH . 'include/rest-functions.php';
		include LEAKY_PAYWALL_PATH . 'include/class-restrictions.php';
		include LEAKY_PAYWALL_PATH . 'include/class-lp-transaction.php';
		include LEAKY_PAYWALL_PATH . 'include/class-lp-logging.php';
	//	include LEAKY_PAYWALL_PATH . 'include/class-lp-onboarding.php';

		// gateways.
		include LEAKY_PAYWALL_PATH . 'include/gateways/gateway-functions.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/stripe/functions.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/paypal/functions.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-stripe.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-stripe-checkout.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-paypal.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-manual.php';
		include LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateways.php';

		if ( ! class_exists( 'Stripe\Stripe' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/init.php';
		}

		// Internationalization.
		load_plugin_textdomain( 'leaky-paywall', false, LEAKY_PAYWALL_REL_DIR . '/i18n/' );
	}
}
add_action( 'plugins_loaded', 'leaky_paywall_plugins_loaded', 4815162342 ); // wait for the plugins to be loaded before init.
