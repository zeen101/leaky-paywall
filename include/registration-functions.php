<?php 


/**
 * Regsiter a new user
 *
 * @since 3.5.1
 */
function leaky_paywall_process_registration() {

	if ( !isset( $_POST['leaky_paywall_register_nonce'] ) && !wp_verify_nonce( $_POST['leaky_paywall_register_nonce'], 'leaky-paywall-register-nonce' ) ) {
		return;
	}	

	$settings = get_leaky_paywall_settings();
	global $user_ID;

	$subscription_id = isset( $_POST['leaky_paywall_level'] ) ? absint( $_POST['leaky_paywall_level'] ) : false;
	$discount 		= '';

	// get the selected payment method
	if ( ! isset( $_POST['leaky_paywall_gateway'] ) ) {
		$gateway = 'paypal';
	} else {
		$gateway = sanitize_text_field( $_POST['leaky_paywall_gateway'] );
	}

	// validate the form
	
	$user_data = leaky_paywall_validate_user_data();

	if ( ! empty( $discount ) ) {
		// do discount stuff
	}

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

	// setup the subscriber object
	$subscriber = new Leaky_Paywall_Subscriber( $user_data['id'] );

	if ( $user_data['id'] ) {

		// @todo add all the user meta values
		
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
 * @since  3.5.1
 */
function leaky_paywall_validate_user_data() {

	$user = array();

	if ( ! is_user_logged_in() ) {
		$user['id']				= 0;
		$user['login']			= sanitize_text_field( $_POST['leaky_paywall_user_login'] );
		$user['email']			= sanitize_text_field( $_POST['leaky_paywall_user_email'] );
		// @todo add more user data 
		
		$user['need_new']		= true;
	} else {
		$userdata 			= get_userdata( get_current_user_id() );
		$user['id']			= $userdata->ID;
		$user['login']		= $userdata->user_login;
		$user['email']		= $userdata->user_email;
		$user['need_new']	= false;
	}

	if ( $user['need_new'] ) {

		if ( username_exists( $user['login'] ) ) {
			// username already registered
			leaky_paywall_errors()->add( 'username_unavailable', __( 'Username already taken', 'issuem-leaky-paywall' ), 'register' );
		}
		// @todo add more error messages

	}

	return apply_filters( 'leaky_paywall_user_registration_data', $user );

}