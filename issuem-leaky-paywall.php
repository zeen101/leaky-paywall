<?php
/**
 * Main PHP file used to for initial calls to zeen101's Leak Paywall classes and functions.
 *
 * @package zeen101's Leak Paywall
 * @since 1.0.0
 */
 
/*
Plugin Name: Leaky Paywall
Plugin URI: http://leakypw.com/
Description: A premium leaky paywall add-on for WordPress and IssueM.
Author: IssueM Development Team
Version: 1.2.0
Author URI: http://leakypw.com/
Tags:
*/

//Define global variables...
if ( !defined( 'ZEEN101_STORE_URL' ) )
	define( 'ZEEN101_STORE_URL',			'http://zeen101.com' );
	
define( 'LEAKY_PAYWALL_NAME', 		'Leaky Paywall for WordPress' );
define( 'LEAKY_PAYWALL_SLUG', 		'issuem-leaky-paywall' );
define( 'LEAKY_PAYWALL_VERSION',		'1.2.0' );
define( 'LEAKY_PAYWALL_DB_VERSION',	'1.0.4' );
define( 'LEAKY_PAYWALL_URL',			plugin_dir_url( __FILE__ ) );
define( 'LEAKY_PAYWALL_PATH', 		plugin_dir_path( __FILE__ ) );
define( 'LEAKY_PAYWALL_BASENAME',	plugin_basename( __FILE__ ) );
define( 'LEAKY_PAYWALL_REL_DIR',		dirname( LEAKY_PAYWALL_BASENAME ) );

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
		define( 'ACTIVE_LP', true );
	else
		define( 'ACTIVE_LP', false );

	require_once( 'class.php' );

	// Instantiate the Pigeon Pack class
	if ( class_exists( 'Leaky_Paywall' ) ) {
		
		global $leaky_paywall;
		$leaky_paywall = new Leaky_Paywall();
		
		require_once( 'functions.php' );
		require_once( 'shortcodes.php' );
		require_once( 'subscriber-table.php' );
		require_once( 'metaboxes.php' );
			
		//Internationalization
		load_plugin_textdomain( 'issuem-leaky-paywall', false, LEAKY_PAYWALL_REL_DIR . '/i18n/' );
			
	}

}
add_action( 'plugins_loaded', 'leaky_paywall_plugins_loaded', 4815162342 ); //wait for the plugins to be loaded before init
