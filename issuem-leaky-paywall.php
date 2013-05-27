<?php
/**
 * Main PHP file used to for initial calls to IssueM's Leak Paywall classes and functions.
 *
 * @package IssueM's Leak Paywall
 * @since 1.0.0
 */
 
/*
Plugin Name: IssueM's Leaky Paywall
Plugin URI: http://issuem.com/
Description: A premium leaky paywall add-on for IssueM.
Author: IssueM Development Team
Version: 1.0.0
Author URI: http://issuem.com/
Tags:
*/

//Define global variables...
define( 'ISSUEM_LEAKY_PAYWALL_PLUGIN_SLUG', 	'1.0.0' );
define( 'ISSUEM_LEAKY_PAYWALL_VERSION', 		'1.0.0' );
define( 'ISSUEM_LEAKY_PAYWALL_DB_VERSION', 		'1.0.0' );
define( 'ISSUEM_LEAKY_PAYWALL_PLUGIN_URL', 		plugin_dir_url( __FILE__ ) );
define( 'ISSUEM_LEAKY_PAYWALL_PLUGIN_PATH', 	plugin_dir_path( __FILE__ ) );
define( 'ISSUEM_LEAKY_PAYWALL_BASENAME', 		plugin_basename( __FILE__ ) );
define( 'ISSUEM_LEAKY_PAYWALL_REL_DIR', 		dirname( ISSUEM_LEAKY_PAYWALL_BASENAME ) );

/**
 * Instantiate Pigeon Pack class, require helper files
 *
 * @since 1.0.0
 */
function issuem_leaky_paywall_plugins_loaded() {

	if ( is_admin() ) {
		
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( !is_plugin_active( 'issuem/issuem.php' ) ) {
			
			add_action( 'admin_notices', 'activate_issuem_admin_notice' );
			
		}
		
	}

	require_once( 'class.php' );

	// Instantiate the Pigeon Pack class
	if ( class_exists( 'IssueM_Leaky_Paywall' ) ) {
		
		global $dl_pluginissuem_leaky_paywall;
		
		$dl_pluginissuem_leaky_paywall = new IssueM_Leaky_Paywall();
		
		require_once( 'functions.php' );
		require_once( 'shortcodes.php' );
			
		//Internationalization
		load_plugin_textdomain( 'issuem-leaky-paywall', false, ISSUEM_LEAKY_PAYWALL_REL_DIR . '/i18n/' );
			
	}

}
add_action( 'plugins_loaded', 'issuem_leaky_paywall_plugins_loaded', 4815162342 ); //wait for the plugins to be loaded before init