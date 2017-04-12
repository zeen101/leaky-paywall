<?php
/**
 * Main PHP file used to for initial calls to zeen101's Leak Paywall classes and functions.
 *
 * @package zeen101's Leak Paywall
 * @since 1.0.0
 */
 
/*
Plugin Name: Leaky Paywall
Plugin URI: https://zeen101.com/
Description: The #1 metered paywall for WordPress. Sell subscriptions without sacrificing search and social visibility.
Author: zeen101 Development Team
Version: 4.6.1
Author URI: https://zeen101.com/
Tags: memberships, subscriptions, restrict access, restrict content, paywall, stripe, paypal
Text Domain: issuem-leaky-paywall
*/

//Define global variables...
if ( !defined( 'ZEEN101_STORE_URL' ) )
	define( 'ZEEN101_STORE_URL',	'http://zeen101.com' );
	
define( 'LEAKY_PAYWALL_NAME', 		'Leaky Paywall for WordPress' );
define( 'LEAKY_PAYWALL_SLUG', 		'leaky-paywall' );
define( 'LEAKY_PAYWALL_VERSION',	'4.6.1' );
define( 'LEAKY_PAYWALL_DB_VERSION',	'1.0.4' );
define( 'LEAKY_PAYWALL_URL',		plugin_dir_url( __FILE__ ) );
define( 'LEAKY_PAYWALL_PATH', 		plugin_dir_path( __FILE__ ) );
define( 'LEAKY_PAYWALL_BASENAME',	plugin_basename( __FILE__ ) );
define( 'LEAKY_PAYWALL_REL_DIR',	dirname( LEAKY_PAYWALL_BASENAME ) );

if ( !defined( 'PAYPAL_LIVE_URL' ) )
	define( 'PAYPAL_LIVE_URL', 'https://www.paypal.com/' );
if ( !defined( 'PAYPAL_SANDBOX_URL' ) )
	define( 'PAYPAL_SANDBOX_URL', 'https://www.sandbox.paypal.com/' );
if ( !defined( 'PAYPAL_PAYMENT_SANDBOX_URL' ) )
	define( 'PAYPAL_PAYMENT_SANDBOX_URL', 'https://www.sandbox.paypal.com/cgi-bin/webscr' );
if ( !defined( 'PAYPAL_PAYMENT_LIVE_URL' ) )
	define( 'PAYPAL_PAYMENT_LIVE_URL', 'https://www.paypal.com/cgi-bin/webscr' );
if ( !defined( 'PAYPAL_NVP_API_SANDBOX_URL' ) )
	define( 'PAYPAL_NVP_API_SANDBOX_URL', 'https://api-3t.sandbox.paypal.com/nvp' );
if ( !defined( 'PAYPAL_NVP_API_LIVE_URL' ) )
	define( 'PAYPAL_NVP_API_LIVE_URL', 'https://api-3t.paypal.com/nvp' );

/**
 * Instantiate Pigeon Pack class, require helper files
 *
 * @since 1.0.0
 */
function leaky_paywall_plugins_loaded() {
	
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if ( is_plugin_active( 'issuem/issuem.php' ) )
		define( 'ACTIVE_ISSUEM', true );
	else
		define( 'ACTIVE_ISSUEM', false );

	require_once( 'class.php' );

	// Instantiate the Pigeon Pack class
	if ( class_exists( 'Leaky_Paywall' ) ) {
		
		global $leaky_paywall;
		$leaky_paywall = new Leaky_Paywall();
		
		require_once( 'functions.php' );
		require_once( 'deprecated.php' );
		require_once( 'shortcodes.php' );
		require_once( 'subscriber-table.php' );
		require_once( 'metaboxes.php' );
		require_once( 'include/admin/dashboard-widgets.php' );

		// license key
		include( LEAKY_PAYWALL_PATH . 'include/license-key.php' );

		// error tracking
		include( LEAKY_PAYWALL_PATH . 'include/error-tracking.php' );

		// registration
		include( LEAKY_PAYWALL_PATH . 'include/registration-functions.php' );

		// gateways
		include( LEAKY_PAYWALL_PATH . 'include/gateways/gateway-functions.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/stripe/functions.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/paypal/functions.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-stripe.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-stripe-checkout.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateway-paypal.php' );
		include( LEAKY_PAYWALL_PATH . 'include/gateways/class-leaky-paywall-payment-gateways.php' );

		//Internationalization
		load_plugin_textdomain( 'issuem-leaky-paywall', false, LEAKY_PAYWALL_REL_DIR . '/i18n/' );
			
	}

}
add_action( 'plugins_loaded', 'leaky_paywall_plugins_loaded', 4815162342 ); //wait for the plugins to be loaded before init
