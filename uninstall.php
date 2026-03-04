<?php
/**
 * Leaky Paywall uninstall handler.
 *
 * Removes plugin options and transients when the plugin is deleted
 * via the WordPress admin.
 *
 * @package Leaky Paywall
 * @since 5.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin options.
$options = array(
	'issuem-leaky-paywall',
	'leaky_paywall_onboarding_redirect',
	'leaky_paywall_onboarding_install_time',
	'leaky_paywall_tracking_allow',
	'leaky_paywall_publication_types',
	'leaky_paywall_country',
	'leaky_paywall_status_migration_v1',
	'leaky_paywall_email_new_subscriber_settings',
	'leaky_paywall_email_admin_new_subscriber_settings',
	'leaky_paywall_email_renewal_reminder_settings',
	'lp_email_settings_migrated',
	'lp-listbuilder',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up the tracking cron.
wp_clear_scheduled_hook( 'leaky_paywall_tracking_send' );
