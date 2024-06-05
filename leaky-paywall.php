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
Version: 4.21.2
Author URI: https://leakypaywall.com/
Tags: paywall, subscriptions, metered, membership, pay wall, content monetization, metered access, metered pay wall, paid content
Text Domain: leaky-paywall
Domain Path: /i18n
Requires at least: 5.6
Requires PHP: 7.4
*/

// Define global variables...
if ( ! defined( 'ZEEN101_STORE_URL' ) ) {
	define( 'ZEEN101_STORE_URL', 'https://zeen101.com' );
}

define( 'LEAKY_PAYWALL_NAME', 'Leaky Paywall for WordPress' );
define( 'LEAKY_PAYWALL_SLUG', 'leaky-paywall' );
define( 'LEAKY_PAYWALL_VERSION', '4.21.2' );
define( 'LEAKY_PAYWALL_DB_VERSION', '1.0.6' );
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

// Composer
require_once( LEAKY_PAYWALL_PATH . 'vendor/autoload.php' );
require_once( LEAKY_PAYWALL_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' );

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
		require_once LEAKY_PAYWALL_PATH . 'metaboxes.php';
		require_once LEAKY_PAYWALL_PATH . 'include/template-functions.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/subscriber-table.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/dashboard-widgets.php';
		
		if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '>=' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/admin/lp-transaction-table.php';
			include LEAKY_PAYWALL_PATH . 'include/class-lp-transaction-table.php';
		} else {
			require_once LEAKY_PAYWALL_PATH . 'include/admin/lp-transaction.php';
			include LEAKY_PAYWALL_PATH . 'include/class-lp-transaction.php';
		}

		require_once LEAKY_PAYWALL_PATH . 'include/admin/lp-incomplete-user.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/tools.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/insights/functions.php';

		require_once LEAKY_PAYWALL_PATH . 'include/admin/settings/settings.php';
		require_once LEAKY_PAYWALL_PATH . 'include/admin/insights/insights.php';

		include LEAKY_PAYWALL_PATH . 'include/license-key.php';
		include LEAKY_PAYWALL_PATH . 'include/error-tracking.php';
		include LEAKY_PAYWALL_PATH . 'include/registration-functions.php';
		include LEAKY_PAYWALL_PATH . 'include/rest-functions.php';
		include LEAKY_PAYWALL_PATH . 'include/class-restrictions.php';
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

		do_action( 'leaky_paywall_plugins_loaded' );
	}
}
add_action( 'plugins_loaded', 'leaky_paywall_plugins_loaded', 4815162342 ); // wait for the plugins to be loaded before init.

function leaky_paywall_activation() {

	create_leaky_paywall_transaction_tables();

}
register_activation_hook( __FILE__, 'leaky_paywall_activation' );

function create_leaky_paywall_transaction_tables() {

    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'lp_transactions';

	// Create the Leaky Paywall Transactions Table
    $sql = "CREATE TABLE $table_name (
        `ID`                     BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`                BIGINT(20) UNSIGNED NOT NULL,
        `email`                  LONGTEXT NOT NULL,
        `first_name`             LONGTEXT NOT NULL,
        `last_name`              LONGTEXT NOT NULL,
        `level_id`               BIGINT(20) UNSIGNED NOT NULL,
        `price`                  LONGTEXT NOT NULL,
        `currency`               LONGTEXT NOT NULL,
        `payment_gateway`        LONGTEXT NOT NULL,
        `payment_gateway_txn_id` LONGTEXT NOT NULL,
        `payment_status`         LONGTEXT NOT NULL,
        `transaction_status`     LONGTEXT NOT NULL,
        `is_recurring`           TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
		`date_updated`           DATETIME NULL,
		`date_created`           DATETIME NULL,
        PRIMARY KEY          (`ID`),
		KEY `payment_status` (`payment_status`(768)),
		KEY `user_id`        (`user_id`),
		KEY `date_created`   (`date_created`),
		KEY `date_updated`   (`date_updated`)
    ) $charset_collate;";

    dbDelta( $sql );

	// Create the Leaky Paywall Transaction Meta Table
    $table_name = $wpdb->prefix . 'lp_transaction_meta';

    $sql = "CREATE TABLE $table_name (
        `meta_id`        BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		`transaction_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        `meta_key`       VARCHAR(255) DEFAULT NULL,
        `meta_value`     LONGTEXT DEFAULT NULL,
        PRIMARY KEY          (`meta_id`),
		KEY `transaction_id` (`transaction_id`),
		KEY `meta_key`       (`meta_key`(191))
    ) $charset_collate;";

    dbDelta( $sql );

}

function migrate_lp_transaction_data( $page = 1 ) {

	global $wpdb;

	$limit = 100;

	$args = array(
		'post_type'       => 'lp_transaction',
		'post_status'     => 'publish',
		'number_of_posts' => $limit,
		'offset'          => --$page,
	);

	$transactions = get_posts( $args );

	$transaction_meta_map = [
		'_login'              => 'user_id', // moved to user_id
		'_email'              => 'email',
		'_first_name'         => 'first_name',
		'_last_name'          => 'last_name',
		'_level_id'           => 'level_id',
		'_price'              => 'price',
		'_currency'           => 'currency',
		'_status'             => 'payment_status', // moved to payment_status
		'_gateway'            => 'payment_gateway', // moved to payment_gateway
		'_gateway_txn_id'     => 'payment_gateway_txn_id', // moved to payment_gateway_txn_id
		'_transaction_status' => 'transaction_status',
		'_is_recurring'       => 'is_recurring',
	];

	$ignore_meta = [
		'_edit_lock'
	];

	foreach( $transactions as $transaction ) {

		$transaction_meta = get_post_meta( $transaction->ID );
		$insert_transaction = [
			'date_created' => $transaction->post_date_gmt,
			'date_updated' => $transaction->post_modified_gmt,
		];
		$insert_transaction_meta = [];

		foreach( $transaction_meta as $key => $value ) {

			if ( in_array( $key, $ignore_meta ) ) {

				continue;

			}
			
			if ( !empty( $transaction_meta_map[$key] ) ) {

				if ( '_login' == $key ) {

					$user = get_user_by( 'login', $value[0] );
					$insert_transaction[$transaction_meta_map[$key]] = $user->ID;

				} else {

					$insert_transaction[$transaction_meta_map[$key]] = $value[0];

				}

			} else {

				$insert_transaction_meta[$key] = $value[0];

			}
			
		}

		$new_transaction = new LP_Transaction( $insert_transaction );
		$transaction_id = $new_transaction->create();

		if ( !empty( $transaction_id ) ) {

			foreach( $insert_transaction_meta as $key => $value ) {
	
				LP_Transaction::update_meta( $transaction_id, $key, $value );
	
			}

		}

	}

	$query = "SELECT COUNT( * ) AS num_posts FROM {$wpdb->posts} WHERE post_type = 'lp_transaction'";
	$total = $wpdb->get_var( $query );

	if ( $limit * $page <= $total ) { //If we've processed less than the total number of transactions, we should continue processing

		$page++;
		as_enqueue_async_action( 'migrate_lp_transaction_data', [ $page ] );
	
	}
	
}
add_action( 'migrate_lp_transaction_data', 'migrate_lp_transaction_data' );