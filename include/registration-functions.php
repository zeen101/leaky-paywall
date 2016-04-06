<?php 
/**
 * Registration Functions
 *
 * Processes the registration form
 *
 * @package     Leaky Paywall
 * @subpackage  Login Functions
 * @copyright   Copyright (c) 2016, Zeen101 Development Team
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Regsiter a new user
 *
 * @since 3.7.0
 */
function leaky_paywall_process_registration() {

	if ( !isset( $_POST['leaky_paywall_register_nonce'] ) && !wp_verify_nonce( $_POST['leaky_paywall_register_nonce'], 'leaky-paywall-register-nonce' ) ) {
		return;
	}	

	$settings = get_leaky_paywall_settings();
	global $user_ID;

	$level_id = isset( $_POST['level_id'] ) ? absint( $_POST['level_id'] ) : false;
	

	// get the selected payment method
	if ( ! isset( $_POST['gateway'] ) ) {
		$gateway = 'paypal';
	} else {
		$gateway = sanitize_text_field( $_POST['gateway'] );
	}

	
	/** 
	 * Validate the Form
	 */
	
	// validate user data
	$user_data = leaky_paywall_validate_user_data();




	// Validate extra fields in gateways

	do_action( 'leaky_paywall_form_errors', $_POST );

	// retrieve all error messages, if any
	$errors = leaky_paywall_errors()->get_error_messages();



	// only create the user if there are no errors
	if ( ! empty( $errors ) ) {
		return;
	}

	// create a new user
	if ( $user_data['need_new'] ) {

		$user_data['id'] = wp_insert_user( array(
				'user_login'			=> $user_data['login'],
				'user_pass'				=> $user_data['password'],
				'user_email'			=> $user_data['email'],
				'first_name'			=> $user_data['first_name'],
				'last_name'				=> $user_data['last_name'],
				'display_name'			=> $user_data['first_name'] . ' ' . $user_data['last_name'],
				'user_registered'		=> date( 'Y-m-d H:i:s' )
			) 
		);
	}

	if ( empty( $user_data['id'] ) ) {
		return;
	}

	// setup the subscriber object
	// $subscriber = new Leaky_Paywall_Subscriber( $user_data['id'] );

	if ( $user_data['id'] ) {

		$meta = array(
			'level_id' 			=> $level_id,
			'price' 			=> sanitize_text_field( $_POST['level_price'] ),
			'description' 		=> sanitize_text_field( $_POST['description'] ),
			'plan' 				=> sanitize_text_field( $_POST['plan_id'] ),
			'created' 			=> date( 'Y-m-d H:i:s' ),
			'subscriber_id' 	=> '',
			//'expires' 			=> $expires,
			'payment_gateway' 	=> $gateway,
			//'payment_status' 	=> $meta_args['payment_status'],
		);

		$level = get_level_by_level_id( $level_id );
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		if ( is_multisite_premium() && !empty( $level['site'] ) && !is_main_site( $level['site'] ) ) {
			$site = '_' . $level['site'];
		} else {
			$site = '';
		}
		
		foreach( $meta as $key => $value ) {

			update_user_meta( $user_data['id'], '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
			
		}

		
		do_action( 'leaky_paywall_form_processing', $_POST, $user_data['id'], $price );

		if ( $price > '0' ) {

			if ( !empty( $discount ) ) {
				// record usage of discount code
			}

			// log the new user in
			// get redirect url

			$subscription_data = array(
				'price'		=> $price,
				'discount'	=> $discount,
				'subscription_id'	=> $subscription->id,
				'subscription_name'	=> $subscription->name,
				'key'				=> $subscription_key,
				'user_id'			=> $user_data['id'],
				'user_name'			=> $user_data['login'],
				'user_email'		=> $user_data['email'],
				'currency'			=> $settings['currency'],
				'auto_renew'		=> $auto_renew,
				'return_url'		=> $redirect,
				'new_user'			=> $user_data['need_new'],
				'post_data'			=> $_POST
			);

			// send all data to the gateway for processing
			leaky_paywall_send_to_gateway( $gateway, apply_filters( 'leaky_paywall_subscription_data', $subscription_data ) );

		} else {
			// process a free or trial subscription
			
			// @todo add all the logic for this stuff here
		}
	}
}



add_action( 'init', 'leaky_paywall_process_registration', 100 );


/** 
 * Validate and setup the user data for registration
 *
 * @since  3.7.0
 */
function leaky_paywall_validate_user_data() {

	$user = array();

	if ( ! is_user_logged_in() ) {
		$user['id']					= 0;
		$user['login']				= sanitize_text_field( $_POST['username'] );
		$user['password']			= sanitize_text_field( $_POST['password'] );
		$user['confirm_password']	= sanitize_text_field( $_POST['confirm_password'] );
		$user['email']				= sanitize_text_field( $_POST['email_address'] );
		$user['first_name']			= sanitize_text_field( $_POST['first_name']);
		$user['last_name']			= sanitize_text_field( $_POST['last_name']);
		$user['need_new']			= true;
	} else {
		$userdata 			= get_userdata( get_current_user_id() );
		$user['id']			= $userdata->ID;
		$user['login']		= $userdata->user_login;
		$user['email']		= $userdata->user_email;
		$user['need_new']	= false;
	}

	if ( $user['need_new'] ) {

		if( empty( $user['first_name'] ) ) {
			// empty first name
			leaky_paywall_errors()->add( 'firstname_empty', __( 'Please enter your first name', 'leaky_paywall' ), 'register' );
		}
		if( empty( $user['last_name'] ) ) {
			// empty last name
			leaky_paywall_errors()->add( 'lastname_empty', __( 'Please enter your last name', 'leaky_paywall' ), 'register' );
		}
		if( ! is_email( $user['email'] ) ) {
			//invalid email
			leaky_paywall_errors()->add( 'email_invalid', __( 'Invalid email', 'leaky_paywall' ), 'register' );
		}
		if( email_exists( $user['email'] ) ) {
			//Email address already registered
			leaky_paywall_errors()->add( 'email_used', __( 'Email already registered', 'leaky_paywall' ), 'register' );
		}
		if( username_exists( $user['login'] ) ) {
			// Username already registered
			leaky_paywall_errors()->add( 'username_unavailable', __( 'Username already taken', 'leaky_paywall' ), 'register' );
		}
		if( ! leaky_paywall_validate_username( $user['login'] ) ) {
			// invalid username
			leaky_paywall_errors()->add( 'username_invalid', __( 'Invalid username', 'leaky_paywall' ), 'register' );
		}
		if( empty( $user['login'] ) ) {
			// empty username
			leaky_paywall_errors()->add( 'username_empty', __( 'Please enter a username', 'leaky_paywall' ), 'register' );
		}
		if( empty( $user['password'] ) ) {
			// password is empty
			leaky_paywall_errors()->add( 'password_empty', __( 'Please enter a password', 'leaky_paywall' ), 'register' );
		}
		if( $user['password'] !== $user['confirm_password'] ) {
			// passwords do not match
			leaky_paywall_errors()->add( 'password_mismatch', __( 'Passwords do not match', 'leaky_paywall' ), 'register' );
		}

	}

	return apply_filters( 'leaky_paywall_user_registration_data', $user );

}

/**
 * Validate a potential username
 *
 * @access      public
 * @since       3.7.0
 * @param       string $username The username to validate
 * @return      bool
 */
function leaky_paywall_validate_username( $username = '' ) {
	$sanitized = sanitize_user( $username, false );
	$valid = ( $sanitized == $username );
	return (bool) apply_filters( 'leaky_paywall_validate_username', $valid, $username );
}