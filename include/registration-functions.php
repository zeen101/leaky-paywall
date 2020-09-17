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
 * @since       4.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Regsiter a new user
 *
 * @since 4.0.0
 */
function leaky_paywall_process_registration() {
	
	if ( !isset( $_POST['leaky_paywall_register_nonce'] ) ) {
		return;
	}	

	if ( !wp_verify_nonce( $_POST['leaky_paywall_register_nonce'], 'leaky-paywall-register-nonce' ) ) {
		return;
	}

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();
	$level_id = isset( $_POST['level_id'] ) ? absint( $_POST['level_id'] ) : false;
	$level = get_leaky_paywall_subscription_level( $level_id );

	// get the selected payment method
	// leaving this here for backwards compatibility
	if ( ! isset( $_POST['gateway'] ) ) {
		$gateway = 'paypal';
	} else {
		$gateway = sanitize_text_field( $_POST['gateway'] );
	}

	if ( isset( $_POST['payment_method'] ) ) {
		$gateway = sanitize_text_field( $_POST['payment_method'] );
	}
	
	/** 
	 * Validate the Form
	 */
	// already done via ajax for Stripe transactions
	
	$user_data = leaky_paywall_validate_user_data();
	
	

	// retrieve all error messages, if any
	$errors = leaky_paywall_errors()->get_error_messages();

	// only send to gateway if their are no errors
	if ( ! empty( $errors ) ) {
		return;
	}

	$subscription_data = apply_filters( 'leaky_paywall_subscription_data', array(
		'amount'			=> sanitize_text_field( $_POST['level_price'] ),
		'description'		=> sanitize_text_field( $_POST['description'] ),
		'user_id'			=> $user_data['id'],
		'user_name'			=> $user_data['login'],
		'user_email'		=> $user_data['email'],
		'first_name'		=> $user_data['first_name'],
		'last_name'			=> $user_data['last_name'],
		'level_id'			=> $level_id,
		'subscriber_id' 	=> '',
		'created' 			=> date( 'Y-m-d H:i:s' ),
		'price'				=> sanitize_text_field( $_POST['level_price'] ),
		'plan'				=> isset( $_POST['plan_id'] ) ? sanitize_text_field( $_POST['plan_id'] ) : '',
		'currency'			=> leaky_paywall_get_currency(),
		'interval_count'	=> sanitize_text_field( $_POST['interval_count'] ),
		'interval'			=> sanitize_text_field( $_POST['interval'] ),
		'recurring'			=> sanitize_text_field( $_POST['recurring'] ),
		'site'				=> sanitize_text_field( $_POST['site'] ),
		'new_user'			=> $user_data['need_new'],
		'payment_gateway' 	=> $gateway,
		'mode'				=> $mode,
		'post_data'			=> $_POST
	) );

	/** 
	 * Send all data to the gateway for processing
	 */
	$gateway_data = leaky_paywall_send_to_gateway( $gateway, $subscription_data );
	
	// Validate extra fields in gateways
	do_action( 'leaky_paywall_form_errors', $_POST, $level_id );

	// retrieve all error messages, if any
	$errors = leaky_paywall_errors()->get_error_messages();

	// only create the user if there are no errors
	if ( ! empty( $errors ) ) {
		return;
	}

	/** 
	 * Merge all data before creating/updating the subscriber
	 */
	$subscriber_data = apply_filters( 'leaky_paywall_registration_user_meta', array_merge( $user_data, $gateway_data ), $user_data );

	if ( apply_filters( 'leaky_paywall_use_alternative_subscriber_registration', false, $subscriber_data, $level ) ) {
		do_action( 'leaky_paywall_alternative_subscriber_registration', $subscriber_data, $level );
	} else {
		leaky_paywall_subscriber_registration( $subscriber_data );
	}
	
}
add_action( 'init', 'leaky_paywall_process_registration', 100 );

/**
 * Complete the registration process after data is processed by gateway.
 * This allows both the registration form and subscribe card buttons to prepare
 * the subscriber data as needed, and then hand off the subscriber creation here. 
 *
 * @since 4.9.3
 */
function leaky_paywall_subscriber_registration( $subscriber_data ) {

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	/**
	* Create or update the WP user for this subscriber
	*/
	if ( is_user_logged_in() || !empty( $subscriber_data['existing_customer'] ) ) {
		$status = 'update';
		$user_id = leaky_paywall_update_subscriber( NULL,  $subscriber_data['subscriber_email'], $subscriber_data['subscriber_id'], $subscriber_data );
	} else {
		$status = 'new';

		// create password here so we can send it to the email
		if ( !$subscriber_data['password'] ) {
			$subscriber_data['password'] = wp_generate_password();
		} 
		
		$user_id = leaky_paywall_new_subscriber( NULL,  $subscriber_data['subscriber_email'], $subscriber_data['subscriber_id'], $subscriber_data );
	}

	if ( empty( $user_id ) ) {
		leaky_paywall_errors()->add( 'user_not_created', __( 'A user could not be created. Please check your details and try again.', 'leaky-paywall' ), 'register' );
		return;
	}

	$subscriber_data['user_id'] = $user_id;
	
	if ( leaky_paywall_is_free_registration( $subscriber_data ) ) {
		do_action( 'leaky_paywall_after_free_user_created', $user_id, $_POST );
	}

	do_action( 'leaky_paywall_form_processing', $_POST, $user_id, $subscriber_data['price'], $mode, $site, $subscriber_data['level_id'] );
	
	$transaction = new LP_Transaction( $subscriber_data );
	$transaction_id = $transaction->create();
	$subscriber_data['transaction_id'] = $transaction_id;

	leaky_paywall_cleanup_incomplete_user( $subscriber_data['email'] );

	// Send email notifications
	leaky_paywall_email_subscription_status( $user_id, $status, $subscriber_data );

	// log the user in
	leaky_paywall_log_in_user( $user_id );

	do_action( 'leaky_paywall_after_process_registration', $subscriber_data );

	// send the newly created user to the appropriate page after logging them in
	wp_safe_redirect( leaky_paywall_get_redirect_url( $settings, $subscriber_data ) );

	exit;

}

/** 
 * Validate first step of multistep registration form
 *
 * @since  4.0.0
 */
add_action( 'wp_ajax_nopriv_leaky_paywall_process_user_registration_validation', 'leaky_paywall_process_user_registration_validation' );
add_action( 'wp_ajax_leaky_paywall_process_user_registration_validation', 'leaky_paywall_process_user_registration_validation' );

function leaky_paywall_process_user_registration_validation() {

	$form_data = $_POST['form_data'];

	parse_str( $form_data, $fields );

	$user = array();
	$errors = array();
	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();
	$level_id = $fields['level_id'];
	$level = get_leaky_paywall_subscription_level( $level_id );

	if ( is_user_logged_in() ) {
		$userdata 			      = get_userdata( get_current_user_id() );
		$user['id']			      = $userdata->ID;
		$user['login']		      = $userdata->user_login;
		$user['email']		      = $userdata->user_email;
		$user['first_name']       = sanitize_text_field( $fields['first_name']);
		$user['last_name']        = sanitize_text_field( $fields['last_name']);
		$user['level_id']         = $level_id;
		$user['need_new']         = false;
	} else {
		$user['id']					= 0;
		$user['login']				= $settings['remove_username_field'] == 'off' ? sanitize_text_field( $fields['username'] ) : sanitize_text_field( $fields['email_address'] );
		$user['password']			= sanitize_text_field( $fields['password'] );
		$user['confirm_password']	= sanitize_text_field( $fields['confirm_password'] );
		$user['email']				= sanitize_text_field( $fields['email_address'] );
		$user['first_name']			= sanitize_text_field( $fields['first_name']);
		$user['last_name']			= sanitize_text_field( $fields['last_name']);
		$user['level_id']           = $level_id;
		$user['need_new']			= true;
	}

	if ( empty( $user['first_name'] ) ) {
		$errors['firstname_empty'] = array(
			'message' =>  __( 'Please enter your first name', 'leaky-paywall' )
		);
	}
	
	if ( empty( $user['last_name'] ) ) {
		$errors['lastname_empty'] = array(
			'message' =>  __( 'Please enter your last name', 'leaky-paywall' )
		);
	}
	
	if ( ! is_email( $user['email'] ) ) {
		$errors['email_invalid'] = array(
			'message' =>  __( 'Invalid email', 'leaky-paywall' )
		);
	}

	if ( $settings['remove_username_field'] == 'off' ) {
		if ( ! validate_username( $user['login'] ) ) {
			$errors['username_invalid'] = array(
				'message' =>  __( 'Invalid username', 'leaky-paywall' )
			);
		}
	}

	if ( 0 == $user['id'] && empty( $user['password'] ) ) {
		$errors['password_empty'] = array(
			'message' =>  __( 'Please enter a password', 'leaky-paywall' )
		);
	}
	
	if ( 0 == $user['id'] && $user['password'] !== $user['confirm_password'] ) {
		$errors['password_mismatch'] = array(
			'message' =>  __( 'Passwords do not match', 'leaky-paywall' )
		);
	}

	// validate other fields


	if ( !empty($errors)) {
		$return = array(
			'errors'  => $errors,
		);
		wp_send_json( $return );
	}

	// if stripe payment method is not active, we are done
	$enabled_gateways = leaky_paywall_get_enabled_payment_gateways();

	if ( ! in_array( 'stripe', array_keys( $enabled_gateways ) ) ) {
		$return = array(
			'success'  => 1,
		);
		
		wp_send_json( $return );
	}
	
	
	// create Stripe customer
	$customer_array = array(
		'name'		  => $user['first_name'] . ' ' . $user['last_name'],
		'email'       => $user['email'],
		'description' => $level['label']
	);

	$customer_array = apply_filters( 'leaky_paywall_process_stripe_payment_customer_array', $customer_array );

	\Stripe\Stripe::setApiKey( leaky_paywall_get_stripe_secret_key() );

	try {
		$cu = \Stripe\Customer::create( $customer_array );
	} catch (\Throwable $th) {
		$errors['stripe_customer'] = array(
			'message' =>  __( 'Could not create customer.', 'leaky-paywall' )
		);
	}

	if ( !empty($errors)) {
		$return = array(
			'errors'  => $errors,
		);
		wp_send_json( $return );
	}

	// need something to store the data on
	leaky_paywall_create_incomplete_user( $user, $cu );

	// create a paymentIntent (if not recurring)
	if ( isset( $level['recurring'] ) && 'on' == $level['recurring'] ) {
		$return = array(
			'success'  => 1,
			'customer_id' => $cu->id,
		);
		
		wp_send_json( $return );
	}

	$stripe_price = number_format( $level['price'], 2, '', '' );

	$intent_args = apply_filters( 'leaky_paywall_payment_intent_args', array(
		'amount'	=> $stripe_price,
		'currency'	=> leaky_paywall_get_currency(),
		'setup_future_usage' => 'off_session',
		'customer'	=> $cu->id,
		'description' 	=> $level['label']
	), $level );

	try {
		$intent = \Stripe\PaymentIntent::create( $intent_args );
	} catch (\Throwable $th) {
		$errors['payment_intent'] = array(
			'message' =>  __( 'Could not create payment intent.', 'leaky-paywall' )
		);
	}
	
	if ( !empty($errors)) {
		$return = array(
			'errors'  => $errors,
		);
		wp_send_json( $return );
	}
	
	$return = array(
		'success'  => 1,
		'pi_client' => $intent->client_secret,
		'pi_id' => $intent->id
	);
	
	wp_send_json( $return );

}

/** 
 * Validate and setup the user data for registration
 *
 * @since  4.0.0
 */
function leaky_paywall_validate_user_data() {

	$user = array();
	$settings = get_leaky_paywall_settings();

	if ( ! is_user_logged_in() ) {
		$user['id']					= 0;
		$user['login']				= $settings['remove_username_field'] == 'off' ? sanitize_text_field( $_POST['username'] ) : sanitize_text_field( $_POST['email_address'] );
		$user['password']			= sanitize_text_field( $_POST['password'] );
		$user['confirm_password']	= sanitize_text_field( $_POST['confirm_password'] );
		$user['email']				= sanitize_text_field( $_POST['email_address'] );
		$user['first_name']			= sanitize_text_field( $_POST['first_name']);
		$user['last_name']			= sanitize_text_field( $_POST['last_name']);
		$user['need_new']			= true;
	} else {
		$userdata 			      = get_userdata( get_current_user_id() );
		$user['id']			      = $userdata->ID;
		$user['login']		      = $userdata->user_login;
		$user['email']		      = $userdata->user_email;
		$user['first_name']       = sanitize_text_field( $_POST['first_name']);
		$user['last_name']        = sanitize_text_field( $_POST['last_name']);
		$user['need_new']         = false;
	}

	if ( 'stripe' == $_POST['payment_method'] ) {
		return apply_filters( 'leaky_paywall_user_registration_data', $user );
	}

	if ( empty( $user['first_name'] ) ) {
		// empty first name
		leaky_paywall_errors()->add( 'firstname_empty', __( 'Please enter your first name', 'leaky-paywall' ), 'register' );
	}
	
	if ( empty( $user['last_name'] ) ) {
		// empty last name
		leaky_paywall_errors()->add( 'lastname_empty', __( 'Please enter your last name', 'leaky-paywall' ), 'register' );
	}
	
	if ( ! is_email( $user['email'] ) ) {
		//invalid email
		leaky_paywall_errors()->add( 'email_invalid', __( 'Invalid email', 'leaky-paywall' ), 'register' );
	}
	
	if ( $settings['remove_username_field'] == 'off' ) {
		if ( ! validate_username( $user['login'] ) ) {
			// invalid username
			leaky_paywall_errors()->add( 'username_invalid', __( 'Invalid username', 'leaky-paywall' ), 'register' );
		}
	}
	
	
	if ( ! is_user_logged_in() && empty( $user['password'] ) ) {
		// password is empty
		leaky_paywall_errors()->add( 'password_empty', __( 'Please enter a password', 'leaky-paywall' ), 'register' );
	}
	
	if ( ! is_user_logged_in() && $user['password'] !== $user['confirm_password'] ) {
		// passwords do not match
		leaky_paywall_errors()->add( 'password_mismatch', __( 'Passwords do not match', 'leaky-paywall' ), 'register' );
	}

	if ( $user['need_new'] ) {
		
		if ( email_exists( $user['email'] ) ) {
			//Email address already registered
			leaky_paywall_errors()->add( 'email_used', __( 'Email already registered. Please <a href="' . get_page_link( $settings['page_for_login'] ) . '">login</a>', 'leaky-paywall' ), 'register' );
		}
		
		if ( username_exists( $user['login'] ) ) {
			// Username already registered
			leaky_paywall_errors()->add( 'username_unavailable', __( 'Username already taken', 'leaky-paywall' ), 'register' );
		}
		
		if ( empty( $user['login'] ) ) {
			// empty username
			leaky_paywall_errors()->add( 'username_empty', __( 'Please enter a username', 'leaky-paywall' ), 'register' );
		}

	}

	return apply_filters( 'leaky_paywall_user_registration_data', $user );

}

/**
 * Validate a potential username
 *
 * @access      public
 * @since       4.0.0
 * @param       string $username The username to validate
 * @return      bool
 */
function leaky_paywall_validate_username( $username = '' ) {
	$sanitized = sanitize_user( $username, false );
	$valid = ( $sanitized == strtolower( $username ) );
	return (bool) apply_filters( 'leaky_paywall_validate_username', $valid, $username );
}

/**
 * Display the credit card fields on the registration form
 *
 * @since  4.0.0 
 */
if( ! function_exists( 'leaky_paywall_card_form' ) ) {
	function leaky_paywall_card_form() {

		?>

		<div class="leaky-paywall-card-details">

		 <fieldset id="leaky-paywall-credit-card-form">

			  <p class="form-row">
			    <label><?php printf( __( 'Name on Card', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="card_name" class="card-name" value="<?php leaky_paywall_old_form_value( 'card_name' ) ?>" />
			  </p>

			  <p class="form-row">
			    <label><?php printf( __( 'Card Number', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="card_num" class="card-num" value="<?php leaky_paywall_old_form_value( 'card_num' ) ?>" />
			  </p>

			  <p class="form-row">
			    <label><?php printf( __( 'CVC', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="4" name="cvc" class="cvc" value="<?php leaky_paywall_old_form_value( 'cvc' ) ?>" />
			  </p>

			  <p class="form-row">
			    <label><?php printf( __( 'Card Zip or Postal Code', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="card_zip" class="card-zip" value="<?php leaky_paywall_old_form_value( 'card_zip' ) ?>" />
			  </p>

			  <p class="form-row">
			    <label><?php printf( __( 'Expiration (MM/YYYY)', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="2" name="exp_month" class="exp-month" value="<?php leaky_paywall_old_form_value( 'exp_month' ) ?>" /> /  <input type="text" size="4" name="exp_year" class="exp-year" value="<?php leaky_paywall_old_form_value( 'exp_year' ) ?>" />
			  </p>

		  </fieldset>

		</div>
			 
		<?php 
	}
}

/**
 * Display the full credit card fields on the registration form
 *
 * @since  4.0.0 
 */
if( ! function_exists( 'leaky_paywall_card_form_full' ) ) {
	function leaky_paywall_card_form_full() {
		?>

		<div class="leaky-paywall-card-details">
		   <p class="form-row">
		        <label><?php _e( 'Card Number', 'rcp' ); ?></label>
		        <input type="text" size="20" maxlength="20" name="card_num" class="card-num card-number" />
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Card CVC', 'rcp' ); ?></label>
		        <input type="text" size="4" maxlength="4" name="cvc" class="cvc" />
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Address', 'rcp' ); ?></label>
		        <input type="text" size="20" name="card_address" class="card-address" />
		    </p>
		   <p class="form-row">
		        <label><?php _e( 'City', 'rcp' ); ?></label>
		        <input type="text" size="20" name="card_city" class="card-city" />
		    </p>
		   <p class="form-row">
		        <label><?php _e( 'State or Providence', 'rcp' ); ?></label>
		        <input type="text" size="20" name="card_state" class="card-state" />
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Country', 'rcp' ); ?></label>
		        <select name="card_country" class="card-country">
		            <option value="">Country</option>
		            <option value="US">United States</option>
		            <option value="CA">Canada</option>
		            <option value="AF">Afghanistan</option>
		            <option value="AL">Albania</option>
		            <option value="DZ">Algeria</option>
		            <option value="AS">American Samoa</option>
		            <option value="AD">Andorra</option>
		            <option value="AO">Angola</option>
		            <option value="AI">Anguilla</option>
		            <option value="AQ">Antarctica</option>
		            <option value="AG">Antigua and Barbuda</option>
		            <option value="AR">Argentina</option>
		            <option value="AM">Armenia</option>
		            <option value="AW">Aruba</option>
		            <option value="AU">Australia</option>
		            <option value="AT">Austria</option>
		            <option value="AZ">Azerbaidjan</option>
		            <option value="BS">Bahamas</option>
		            <option value="BH">Bahrain</option>
		            <option value="BD">Bangladesh</option>
		            <option value="BB">Barbados</option>
		            <option value="BY">Belarus</option>
		            <option value="BE">Belgium</option>
		            <option value="BZ">Belize</option>
		            <option value="BJ">Benin</option>
		            <option value="BM">Bermuda</option>
		            <option value="BT">Bhutan</option>
		            <option value="BO">Bolivia</option>
		            <option value="BA">Bosnia-Herzegovina</option>
		            <option value="BW">Botswana</option>
		            <option value="BV">Bouvet Island</option>
		            <option value="BR">Brazil</option>
		            <option value="IO">British Indian Ocean Territory</option>
		            <option value="BN">Brunei Darussalam</option>
		            <option value="BG">Bulgaria</option>
		            <option value="BF">Burkina Faso</option>
		            <option value="BI">Burundi</option>
		            <option value="KH">Cambodia</option>
		            <option value="CM">Cameroon</option>
		            <option value="CV">Cape Verde</option>
		            <option value="KY">Cayman Islands</option>
		            <option value="CF">Central African Republic</option>
		            <option value="TD">Chad</option>
		            <option value="CL">Chile</option>
		            <option value="CN">China</option>
		            <option value="CX">Christmas Island</option>
		            <option value="CC">Cocos (Keeling) Islands</option>
		            <option value="CO">Colombia</option>
		            <option value="KM">Comoros</option>
		            <option value="CG">Congo</option>
		            <option value="CK">Cook Islands</option>
		            <option value="CR">Costa Rica</option>
		            <option value="HR">Croatia</option>
		            <option value="CU">Cuba</option>
		            <option value="CY">Cyprus</option>
		            <option value="CZ">Czech Republic</option>
		            <option value="DK">Denmark</option>
		            <option value="DJ">Djibouti</option>
		            <option value="DM">Dominica</option>
		            <option value="DO">Dominican Republic</option>
		            <option value="TP">East Timor</option>
		            <option value="EC">Ecuador</option>
		            <option value="EG">Egypt</option>
		            <option value="SV">El Salvador</option>
		            <option value="GQ">Equatorial Guinea</option>
		            <option value="ER">Eritrea</option>
		            <option value="EE">Estonia</option>
		            <option value="ET">Ethiopia</option>
		            <option value="FK">Falkland Islands</option>
		            <option value="FO">Faroe Islands</option>
		            <option value="FJ">Fiji</option>
		            <option value="FI">Finland</option>
		            <option value="CS">Former Czechoslovakia</option>
		            <option value="SU">Former USSR</option>
		            <option value="FR">France</option>
		            <option value="FX">France (European Territory)</option>
		            <option value="GF">French Guyana</option>
		            <option value="TF">French Southern Territories</option>
		            <option value="GA">Gabon</option>
		            <option value="GM">Gambia</option>
		            <option value="GE">Georgia</option>
		            <option value="DE">Germany</option>
		            <option value="GH">Ghana</option>
		            <option value="GI">Gibraltar</option>
		            <option value="GB">Great Britain</option>
		            <option value="GR">Greece</option>
		            <option value="GL">Greenland</option>
		            <option value="GD">Grenada</option>
		            <option value="GP">Guadeloupe (French)</option>
		            <option value="GU">Guam (USA)</option>
		            <option value="GT">Guatemala</option>
		            <option value="GN">Guinea</option>
		            <option value="GW">Guinea Bissau</option>
		            <option value="GY">Guyana</option>
		            <option value="HT">Haiti</option>
		            <option value="HM">Heard and McDonald Islands</option>
		            <option value="HN">Honduras</option>
		            <option value="HK">Hong Kong</option>
		            <option value="HU">Hungary</option>
		            <option value="IS">Iceland</option>
		            <option value="IN">India</option>
		            <option value="ID">Indonesia</option>
		            <option value="INT">International</option>
		            <option value="IR">Iran</option>
		            <option value="IQ">Iraq</option>
		            <option value="IE">Ireland</option>
		            <option value="IL">Israel</option>
		            <option value="IT">Italy</option>
		            <option value="CI">Ivory Coast (Cote D&#39;Ivoire)</option>
		            <option value="JM">Jamaica</option>
		            <option value="JP">Japan</option>
		            <option value="JO">Jordan</option>
		            <option value="KZ">Kazakhstan</option>
		            <option value="KE">Kenya</option>
		            <option value="KI">Kiribati</option>
		            <option value="KW">Kuwait</option>
		            <option value="KG">Kyrgyzstan</option>
		            <option value="LA">Laos</option>
		            <option value="LV">Latvia</option>
		            <option value="LB">Lebanon</option>
		            <option value="LS">Lesotho</option>
		            <option value="LR">Liberia</option>
		            <option value="LY">Libya</option>
		            <option value="LI">Liechtenstein</option>
		            <option value="LT">Lithuania</option>
		            <option value="LU">Luxembourg</option>
		            <option value="MO">Macau</option>
		            <option value="MK">Macedonia</option>
		            <option value="MG">Madagascar</option>
		            <option value="MW">Malawi</option>
		            <option value="MY">Malaysia</option>
		            <option value="MV">Maldives</option>
		            <option value="ML">Mali</option>
		            <option value="MT">Malta</option>
		            <option value="MH">Marshall Islands</option>
		            <option value="MQ">Martinique (French)</option>
		            <option value="MR">Mauritania</option>
		            <option value="MU">Mauritius</option>
		            <option value="YT">Mayotte</option>
		            <option value="MX">Mexico</option>
		            <option value="FM">Micronesia</option>
		            <option value="MD">Moldavia</option>
		            <option value="MC">Monaco</option>
		            <option value="MN">Mongolia</option>
		            <option value="MS">Montserrat</option>
		            <option value="MA">Morocco</option>
		            <option value="MZ">Mozambique</option>
		            <option value="MM">Myanmar</option>
		            <option value="NA">Namibia</option>
		            <option value="NR">Nauru</option>
		            <option value="NP">Nepal</option>
		            <option value="NL">Netherlands</option>
		            <option value="AN">Netherlands Antilles</option>
		            <option value="NT">Neutral Zone</option>
		            <option value="NC">New Caledonia (French)</option>
		            <option value="NZ">New Zealand</option>
		            <option value="NI">Nicaragua</option>
		            <option value="NE">Niger</option>
		            <option value="NG">Nigeria</option>
		            <option value="NU">Niue</option>
		            <option value="NF">Norfolk Island</option>
		            <option value="KP">North Korea</option>
		            <option value="MP">Northern Mariana Islands</option>
		            <option value="NO">Norway</option>
		            <option value="OM">Oman</option>
		            <option value="PK">Pakistan</option>
		            <option value="PW">Palau</option>
		            <option value="PA">Panama</option>
		            <option value="PG">Papua New Guinea</option>
		            <option value="PY">Paraguay</option>
		            <option value="PE">Peru</option>
		            <option value="PH">Philippines</option>
		            <option value="PN">Pitcairn Island</option>
		            <option value="PL">Poland</option>
		            <option value="PF">Polynesia (French)</option>
		            <option value="PT">Portugal</option>
		            <option value="PR">Puerto Rico</option>
		            <option value="QA">Qatar</option>
		            <option value="RE">Reunion (French)</option>
		            <option value="RO">Romania</option>
		            <option value="RU">Russian Federation</option>
		            <option value="RW">Rwanda</option>
		            <option value="GS">S. Georgia & S. Sandwich Isls.</option>
		            <option value="SH">Saint Helena</option>
		            <option value="KN">Saint Kitts & Nevis Anguilla</option>
		            <option value="LC">Saint Lucia</option>
		            <option value="PM">Saint Pierre and Miquelon</option>
		            <option value="ST">Saint Tome (Sao Tome) and Principe</option>
		            <option value="VC">Saint Vincent & Grenadines</option>
		            <option value="WS">Samoa</option>
		            <option value="SM">San Marino</option>
		            <option value="SA">Saudi Arabia</option>
		            <option value="SN">Senegal</option>
		            <option value="SC">Seychelles</option>
		            <option value="SL">Sierra Leone</option>
		            <option value="SG">Singapore</option>
		            <option value="SK">Slovak Republic</option>
		            <option value="SI">Slovenia</option>
		            <option value="SB">Solomon Islands</option>
		            <option value="SO">Somalia</option>
		            <option value="ZA">South Africa</option>
		            <option value="KR">South Korea</option>
		            <option value="ES">Spain</option>
		            <option value="LK">Sri Lanka</option>
		            <option value="SD">Sudan</option>
		            <option value="SR">Suriname</option>
		            <option value="SJ">Svalbard and Jan Mayen Islands</option>
		            <option value="SZ">Swaziland</option>
		            <option value="SE">Sweden</option>
		            <option value="CH">Switzerland</option>
		            <option value="SY">Syria</option>
		            <option value="TJ">Tadjikistan</option>
		            <option value="TW">Taiwan</option>
		            <option value="TZ">Tanzania</option>
		            <option value="TH">Thailand</option>
		            <option value="TG">Togo</option>
		            <option value="TK">Tokelau</option>
		            <option value="TO">Tonga</option>
		            <option value="TT">Trinidad and Tobago</option>
		            <option value="TN">Tunisia</option>
		            <option value="TR">Turkey</option>
		            <option value="TM">Turkmenistan</option>
		            <option value="TC">Turks and Caicos Islands</option>
		            <option value="TV">Tuvalu</option>
		            <option value="UG">Uganda</option>
		            <option value="UA">Ukraine</option>
		            <option value="AE">United Arab Emirates</option>
		            <option value="GB">United Kingdom</option>
		            <option value="UY">Uruguay</option>
		            <option value="MIL">USA Military</option>
		            <option value="UM">USA Minor Outlying Islands</option>
		            <option value="UZ">Uzbekistan</option>
		            <option value="VU">Vanuatu</option>
		            <option value="VA">Vatican City State</option>
		            <option value="VE">Venezuela</option>
		            <option value="VN">Vietnam</option>
		            <option value="VG">Virgin Islands (British)</option>
		            <option value="VI">Virgin Islands (USA)</option>
		            <option value="WF">Wallis and Futuna Islands</option>
		            <option value="EH">Western Sahara</option>
		            <option value="YE">Yemen</option>
		            <option value="YU">Yugoslavia</option>
		            <option value="ZR">Zaire</option>
		            <option value="ZM">Zambia</option>
		            <option value="ZW">Zimbabwe</option>
		        </select>
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Card ZIP or Postal Code', 'rcp' ); ?></label>
		        <input type="text" size="10" name="card_zip" class="card-zip" />
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Name on Card', 'rcp' ); ?></label>
		        <input type="text" size="20" name="card_name" class="card-name" />
		    </p>
		    <p class="form-row">
		        <label><?php _e( 'Expiration (MM/YYYY)', 'rcp' ); ?></label>
		        <select name="card_exp_month" class="ccard-expiry-month">
		            <?php for( $i = 1; $i <= 12; $i++ ) : ?>
		                <option value="<?php echo $i; ?>"><?php echo $i . ' - ' . leaky_paywall_get_month_name( $i ); ?></option>
		            <?php endfor; ?>
		        </select>
		        <span class="expiry_separator"> / </span>
		        <select name="card_exp_year" class="card-expiry-year">
		            <?php
		            $year = date( 'Y' );
		            for( $i = $year; $i <= $year + 10; $i++ ) : ?>
		                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
		            <?php endfor; ?>
		        </select>
		    </p>
		</div>
		<?php 

	}
}

/**
 * Converts the month number to the month name
 *
 * @access public
 * @since  4.0.0
 *
 * @param  int $n Month number.
 * @return string The name of the month.
 */
if( ! function_exists( 'leaky_paywall_get_month_name' ) ) {
	function leaky_paywall_get_month_name($n) {
		$timestamp = mktime(0, 0, 0, $n, 1, 2005);

		return date_i18n( "F", $timestamp );
	}
}

/**
 * Converts the month number to the month name
 *
 * @since  4.9.3
 *
 * @param  int $user_id ID of the user.
 */
if ( ! function_exists( 'leaky_paywall_log_in_user' ) ) {
	function leaky_paywall_log_in_user( $user_id ) {

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

	}
}

/**
 * Converts the month number to the month name
 *
 * @since  4.9.3
 *
 * @param  array $settings Leaky Paywall settings.
 * @param  array $subscriber_data data of current subscriber
 * @return 
 */
if ( ! function_exists( 'leaky_paywall_get_redirect_url' ) ) {
	function leaky_paywall_get_redirect_url( $settings, $subscriber_data ) {

		if ( !empty( $settings['page_for_after_subscribe'] ) ) {
			$redirect_url = get_page_link( $settings['page_for_after_subscribe'] );
		} else if ( !empty( $settings['page_for_profile'] ) ) {
			$redirect_url = get_page_link( $settings['page_for_profile'] );
		} else if ( !empty( $settings['page_for_subscription'] ) ) {
			$redirect_url = get_page_link( $settings['page_for_subscription'] );
		}

		return apply_filters( 'leaky_paywall_redirect_url', add_query_arg( 'lp_txn_id', $subscriber_data['transaction_id'], $redirect_url ), $subscriber_data );

	}
}


add_action( 'wp_ajax_nopriv_leaky_paywall_validate_registration', 'leaky_paywall_validate_frontend_registration' );
add_action( 'wp_ajax_leaky_paywall_validate_registration', 'leaky_paywall_validate_frontend_registration' );

function leaky_paywall_validate_frontend_registration() {

	if ( isset( $_POST['email'] ) ) {

		$email = trim( $_POST['email'] );

		if ( !is_email( $email ) ) {

			$return = array(
			    'message'  => 'This email is invalid. Please enter a different email.',
			    'status'       => 'error'
			);
			 
			wp_send_json($return);
		}

		if ( email_exists( $email ) ) {

			$return = array(
			    'message'  => 'This email already exists. Please login or enter a different email.',
			    'status'       => 'error'
			);
			 
			wp_send_json($return);
		}

	}

	if ( isset( $_POST['username'] ) ) {

		$username = trim( $_POST['username'] );

		if ( username_exists( $username ) ) {

			$return = array(
			    'message'  => 'Username already taken.',
			    'status'       => 'error'
			);
			 
			wp_send_json($return);

		}
	}

	die();
	
}


function leaky_paywall_create_incomplete_user( $user_data, $customer_data ) {

	$data = array(
		'post_title'    => wp_strip_all_tags( $user_data['email'] ),
		'post_content'  => '',
		'post_status'   => 'publish',
		'post_author'   => 1,
		'post_type'		=> 'lp_incomplete_user'
	);
	
	// Insert the post into the database
	$incomplete_user = wp_insert_post( $data );

	update_post_meta( $incomplete_user, '_user_data', $user_data );
	update_post_meta( $incomplete_user, '_customer_data', $customer_data );

}

function leaky_paywall_cleanup_incomplete_user( $email ) {

	$incomplete_users = get_posts(array(
		'post_type' => 'lp_incomplete_user',
		's'	=> $email,
		'posts_per_page' => 99
	));

	if ( empty( $incomplete_users ) ) {
		return;
	}

	foreach( $incomplete_users as $incomplete ) {
		wp_trash_post( $incomplete->ID );
	}
}