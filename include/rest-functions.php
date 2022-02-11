<?php
/**
 * REST Functions
 *
 * Handle setting up REST API integration
 *
 * @package     Leaky Paywall
 * @copyright   Copyright (c) 2016, Zeen101 Development Team
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.16.17
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function meta_rest_api() {

	$settings = get_leaky_paywall_settings();

	if ( 'on' === $settings['enable_rest_api'] ) {
		register_rest_field( 'user', 'leaky_paywall_meta', array(
			'get_callback'      => 'lp_rest_get_post_meta',
		) );
	}
    
}
add_action( 'rest_api_init', 'meta_rest_api' );


function lp_rest_get_post_meta( $user, $field_name, $request ) {

	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();
    $level_id = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
    $subscriber_id = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
    $price = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_price' . $site, true );
    $plan = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
    $created = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_created' . $site, true );
    $expires = get_user_meta(  $user['id'], '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
    $has_access = true;
    $payment_gateway = get_user_meta( $user['id'], '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
    $payment_status = get_user_meta( $user['id'], '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );

    return array(
        'level_id'  => $level_id,
        'subscriber_id' => $subscriber_id,
        'price' => $price,
        'plan' => $plan,
        'created' => $created,
        'expires' => $expires,
        'has_access' => $has_access,
        'payment_gateway' => $payment_gateway,
        'payment_status' => $payment_status
    );
}