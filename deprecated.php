<?php
	
if ( !function_exists( 'leaky_paywall_hash' ) ) {
	function leaky_paywall_hash( $str ) {
        _deprecated_function( __FUNCTION__, 'CHANGEME', 'create_leaky_paywall_login_hash( $str )' );
		return create_leaky_paywall_login_hash( $str );
	}
}

if ( !function_exists( 'is_issuem_leaky_subscriber_logged_in' ) ) {
	function is_issuem_leaky_subscriber_logged_in() {
        _deprecated_function( __FUNCTION__, 'CHANGEME', 'leaky_paywall_has_user_paid()' );
		return leaky_paywall_has_user_paid();
	}
}

if ( !function_exists( 'get_leaky_paywall_subscriber_by_hash' ) ) {
	function get_leaky_paywall_subscriber_by_hash( $hash, $mode ) {
        _deprecated_function( __FUNCTION__, 'CHANGEME' );
        
        if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
	
			if ( empty( $mode ) ) {
                $settings = get_leaky_paywall_settings();
                $mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
            }

            $args = array(
                'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_hash',
                'meta_value' => $hash,
            );
            $users = get_users( $args );

            if ( !empty( $users ) ) {
                foreach ( $users as $user ) {
                    return $user;
                }
            }

        }
        return false;
	}
}


if ( !function_exists( 'leaky_paywall_old_payment_gateway_processing' ) ) {

    function leaky_paywall_old_payment_gateway_processing() {

        $response = leaky_paywall_maybe_process_payment();

        if ( is_wp_error( $response ) ) {
            $args = array(
                'response' => 401,
                'back_link' => true,
            );      
            wp_die( $response, '', $args );
        }
        
        if ( leaky_paywall_maybe_process_webhooks() ) {
            die(); //no point in loading the whole page for webhooks
        }
    }
}
add_action('leaky_paywall_before_process_requests', 'leaky_paywall_old_payment_gateway_processing' );


if ( !function_exists( 'leaky_paywall_process_free_registration' ) ) {
    function leaky_paywall_process_free_registration() {
        if ( isset( $_POST['leaky_paywall_user_login'] ) && wp_verify_nonce( $_POST['leaky_paywall_register_nonce'], 'leaky_paywall-register-nonce' ) ) {
            $user_login     = $_POST['leaky_paywall_user_login'];   
            $user_email     = $_POST['leaky_paywall_user_email'];
            $user_first     = $_POST['leaky_paywall_user_first'];
            $user_last      = $_POST['leaky_paywall_user_last'];
            $user_pass      = $_POST['leaky_paywall_user_pass'];
            $pass_confirm   = $_POST['leaky_paywall_user_pass_confirm'];
            $level_id       = $_POST['leaky_paywall_register_level_id'];
     
            // this is required for username checks
            require_once( ABSPATH . WPINC . '/user.php' );
            
            $settings = get_leaky_paywall_settings();
            
            $return = '';
            if ( $level = get_leaky_paywall_subscription_level( $level_id ) ) {
                if ( !empty( $level['price'] ) ) {
                    leaky_paywall_errors()->add( 'subscriptoin_level_not_free', __( 'Requested subscription level is not free', 'issuem-leaky-paywall' ) );
                }
            } else {
                leaky_paywall_errors()->add( 'invalid_subscription_level', __( 'Not a valid subscription level', 'issuem-leaky-paywall' ) );
            }
     
            if ( username_exists( $user_login ) ) {
                // Username already registered
                leaky_paywall_errors()->add( 'username_unavailable', __( 'Username already taken', 'issuem-leaky-paywall' ) );
            }
            if ( !validate_username($user_login) ) {
                // invalid username
                leaky_paywall_errors()->add( 'username_invalid', __( 'Invalid username', 'issuem-leaky-paywall' ) );
            }
            if ( empty( $user_login ) ) {
                // empty username
                leaky_paywall_errors()->add( 'username_empty', __( 'Please enter a username', 'issuem-leaky-paywall' ) );
            }
            if ( !is_email( $user_email ) ) {
                //invalid email
                leaky_paywall_errors()->add( 'email_invalid', __( 'Invalid email', 'issuem-leaky-paywall' ) );
            }
            if ( email_exists( $user_email ) ) {
                //Email address already registered
                leaky_paywall_errors()->add( 'email_used', __( 'Email already registered', 'issuem-leaky-paywall' ) );
            }
            if ( $user_pass == '' ) {
                // passwords do not match
                leaky_paywall_errors()->add( 'password_empty', __( 'Please enter a password', 'issuem-leaky-paywall' ) );
            }
            if ( $user_pass != $pass_confirm ) {
                // passwords do not match
                leaky_paywall_errors()->add( 'password_mismatch', __( 'Passwords do not match', 'issuem-leaky-paywall' ) );
            }
     
            $errors = leaky_paywall_errors()->get_error_messages();
     
            // only create the user in if there are no errors
            if ( empty( $errors ) ) {
                
                $userdata = array(
                    'user_login'        => $user_login,
                    'user_pass'         => $user_pass,
                    'user_email'        => $user_email,
                    'first_name'        => $user_first,
                    'last_name'         => $user_last,
                    'user_registered'   => date_i18n( 'Y-m-d H:i:s' ),
                );
                $userdata = apply_filters( 'leaky_paywall_userdata_before_user_create', $userdata );
                $user_id = wp_insert_user( $userdata );
                
                if ( $user_id ) {
                    leaky_paywall_email_subscription_status( $user_id, 'new', $userdata );
                    
                    $args = array(
                        'level_id'          => $level_id,
                        'subscriber_id'     => '',
                        'subscriber_email'  => $user_email,
                        'price'             => $level['price'],
                        'description'       => $level['label'],
                        'payment_gateway'   => 'free_registration',
                        'payment_status'    => 'active',
                        'interval'          => $level['interval'],
                        'interval_count'    => $level['interval_count'],
                    );

                    if ( isset( $level['site'] ) ) {
                        $args['site'] = $level['site'];
                    }
                    
                    //Mimic PayPal's Plan...
                    if ( !empty( $level['recurring'] ) && 'on' == $level['recurring'] )
                        $args['plan'] = $level['interval_count'] . ' ' . strtoupper( substr( $level['interval'], 0, 1 ) );
                    
                    $args['subscriber_email'] = $user_email;
                    leaky_paywall_update_subscriber( NULL, $user_email, 'free-' . time(), $args );
                    
                    do_action( 'leaky_paywall_after_free_user_created', $user_id, $_POST );
                    
                    // log the new user in
                    wp_setcookie( $user_login, $user_pass, true );
                    wp_set_current_user( $user_id, $user_login );
                    do_action( 'wp_login', $user_login );
     
                    // send the newly created user to the appropriate page after logging them in
                    if ( !empty( $settings['page_for_after_subscribe'] ) ) {
                            wp_safe_redirect( get_page_link( $settings['page_for_after_subscribe'] ) );
                    } else if ( !empty( $settings['page_for_profile'] ) ) {
                        wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
                    } else if ( !empty( $settings['page_for_subscription'] ) ) {
                        wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
                    }
                    
                    exit;
                }
     
            }
     
        }
        
    }
    
}


if ( !function_exists( 'leaky_paywall_maybe_process_webhooks' ) ) {
    
 function leaky_paywall_maybe_process_webhooks() {
                    
     if ( !empty( $_REQUEST['issuem-leaky-paywall-stripe-live-webhook'] ) )
         return issuem_process_stripe_webhook( 'live' );
            
     if ( !empty( $_REQUEST['issuem-leaky-paywall-stripe-test-webhook'] ) )
         return issuem_process_stripe_webhook( 'test' );
            
     if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-live-ipn'] ) )
         return issuem_process_paypal_standard_ipn( 'live' );
            
     if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-test-ipn'] ) )
         return issuem_process_paypal_standard_ipn( 'test' );
        
     return apply_filters( 'leaky_paywall_maybe_process_webhooks', false );
        
 }
    
}


if ( !function_exists( 'leaky_paywall_maybe_process_payment' ) ) {
    
 function leaky_paywall_maybe_process_payment() {

     if ( !empty( $_REQUEST['issuem-leaky-paywall-stripe-return'] ) )
         return leaky_paywall_process_stripe_payment();
        
     if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-return'] ) )
      return leaky_paywall_process_paypal_payment();
        
     if ( !empty( $_REQUEST['issuem-leaky-paywall-free-return'] ) ) {
         return leaky_paywall_process_free_registration();
     }
            
     return apply_filters( 'leaky_paywall_maybe_process_payment', false );
        
 }
    
}


if ( !function_exists( 'leaky_paywall_process_stripe_payment' ) ) {
    
    function leaky_paywall_process_stripe_payment() {
        
        if ( isset( $_POST['custom'] ) && !empty( $_POST['stripeToken'] ) ) {
        
            $settings = get_leaky_paywall_settings();
            $mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
            
            try {
                
                $token = $_POST['stripeToken'];
                $level = get_leaky_paywall_subscription_level( $_POST['custom'] );
                $amount = number_format( $level['price'], 2, '', '' );
                
                if ( is_multisite_premium() && !empty( $level['site'] ) && !is_main_site( $level['site'] ) ) {
                    $site = '_' . $level['site'];
                } else {
                    $site = '';
                }
                
                if ( is_user_logged_in() && !is_admin() ) {
                    //Update the existing user
                    $user_id = get_current_user_id();
                    $subscriber_id = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
                }
                
                if ( !empty( $subscriber_id ) ) {
                    $cu = Stripe_Customer::retrieve( get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true ) );
                }
                
                if ( empty( $cu ) ) {
                    if ( $user = get_user_by( 'email', $_POST['stripeEmail'] ) ) {
                        try {
                            $subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
                            if ( !empty( $subscriber_id ) ) {
                                $cu = Stripe_Customer::retrieve( $subscriber_id );
                            } else {
                                throw new Exception( __( 'Unable to find valid Stripe customer ID.', 'issuem-leaky-paywall' ) );
                            }
                        }
                        catch( Exception $e ) {
                            $cu = false;
                        }
                    }
                }
                    
                if ( !empty( $cu ) ) {
                    if ( true === $cu->deleted ) {
                        $cu = array();
                    } else {
                        $existing_customer = true;
                    }
                }
                
                $customer_array = array(
                    'email' => $_POST['stripeEmail'],
                    'card'  => $token,
                );
                $customer_array = apply_filters( 'leaky_paywall_process_stripe_payment_customer_array', $customer_array );
            
                if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] && !empty( $level['plan_id'] ) ) {
                
                    $customer_array['plan'] = $level['plan_id'];
                    if ( !empty( $cu ) ) {
                        $subscriptions = $cu->subscriptions->all( 'limit=1' );
                        
                        if ( !empty( $subscriptions->data ) ) {
                            foreach( $subscriptions->data as $subscription ) {
                                $sub = $cu->subscriptions->retrieve( $subscription->id );
                                $sub->plan = $level['plan_id'];
                                $sub->save();
                            }
                        } else {
                            $cu->subscriptions->create( array( 'plan' => $level['plan_id'] ) );
                        }
                        
                    } else {
                        $cu = Stripe_Customer::create( $customer_array );
                    }
                    
                } else {
                    
                    if ( empty( $cu ) ) {
                        $cu = Stripe_Customer::create( $customer_array );
                    } else {
                        $cu->cards->create( array( 'card' => $token ) );
                    }

                    $currency = $settings['leaky_paywall_currency'];

                    $charge_array['customer']    = $cu->id;
                    $charge_array['amount']      = $amount;
                    $charge_array['currency']    = apply_filters( 'leaky_paywall_stripe_currency', $currency );
                    $charge_array['description'] = $level['label'];
                    
                    $charge = Stripe_Charge::create( $charge_array );
                }
                                
                $customer_id = $cu->id;
                
                $args = array(
                    'level_id'          => $_POST['custom'],
                    'subscriber_id'     => $customer_id,
                    'subscriber_email'  => $_POST['stripeEmail'],
                    'price'             => $level['price'],
                    'description'       => $level['label'],
                    'payment_gateway'   => 'stripe',
                    'payment_status'    => 'active',
                    'interval'          => $level['interval'],
                    'interval_count'    => $level['interval_count'],
                    'site'              => !empty( $level['site'] ) ? $level['site'] : '',
                    'plan'              => !empty( $customer_array['plan'] ) ? $customer_array['plan'] : '',
                );
                    
                if ( is_user_logged_in() || !empty( $existing_customer ) ) {
                    $user_id = leaky_paywall_update_subscriber( NULL, $_POST['stripeEmail'], $customer_id, $args ); //if the email already exists, we want to update the subscriber, not create a new one
                } else {
                    $user_id = leaky_paywall_new_subscriber( NULL, $_POST['stripeEmail'], $customer_id, $args );
                }
                
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id, true );
                    
                // send the newly created user to the appropriate page after logging them in
                if ( !empty( $settings['page_for_after_subscribe'] ) ) {
                    wp_safe_redirect( get_page_link( $settings['page_for_after_subscribe'] ) );
                } else if ( !empty( $settings['page_for_profile'] ) ) {
                    wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
                } else if ( !empty( $settings['page_for_subscription'] ) ) {
                    wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
                }
                
            } catch ( Exception $e ) {
                
                return new WP_Error( 'broke', sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) );
                
            }
            
        }
        
        return false;
        
    }
    
}

if ( !function_exists( 'leaky_paywall_process_paypal_payment' ) ) {
    
    function leaky_paywall_process_paypal_payment() {
        
        if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-return'] ) ) {
        
            $settings = get_leaky_paywall_settings();
            $mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
                                
            if ( !empty( $_REQUEST['tx'] ) ) //if PDT is enabled
                $transaction_id = $_REQUEST['tx'];
            else if ( !empty( $_REQUEST['txn_id'] ) ) //if PDT is not enabled
                $transaction_id = $_REQUEST['txn_id'];
            else
                $transaction_id = NULL;
                
            if ( !empty( $_REQUEST['cm'] ) )
                $user_email = $_REQUEST['cm'];
            else
                $user_email = NULL;
    
            if ( !empty( $_REQUEST['amt'] ) ) //if PDT is enabled
                $transaction_amount = $_REQUEST['amt'];
            else if ( !empty( $_REQUEST['mc_gross'] ) ) //if PDT is not enabled
                $transaction_amount = $_REQUEST['mc_gross'];
            else
                $transaction_amount = NULL;
    
            if ( !empty( $_REQUEST['st'] ) ) //if PDT is enabled
                $transaction_status = $_REQUEST['st'];
            else if ( !empty( $_REQUEST['payment_status'] ) ) //if PDT is not enabled
                $transaction_status = $_REQUEST['payment_status'];
            else
                $transaction_status = NULL;
                        
            if ( !empty( $transaction_id ) && !empty( $transaction_amount ) && !empty( $transaction_status ) ) {
    
                try {

                    $customer_id = $transaction_id; //temporary, will be replaced with subscriber ID during IPN

                    switch( strtolower( $transaction_status ) ) {
                        
                        case 'denied' :
                            throw new Exception( __( 'Error: PayPal denied this payment.', 'issuem-leaky-paywall' ) );
                            break;
                        case 'failed' :
                            throw new Exception( __( 'Error: Payment failed.', 'issuem-leaky-paywall' ) );
                            break;
                        case 'completed':
                        case 'success':
                        case 'canceled_reversal':
                        case 'processed' :
                        default:
                            $args['payment_status'] = 'active';
                            break;
                        
                    }
                    
                    $paypal_api_url       = ( 'test' === $mode ) ? PAYPAL_NVP_API_SANDBOX_URL : PAYPAL_NVP_API_LIVE_URL;
                    $paypal_api_username  = ( 'test' === $mode ) ? $settings['paypal_sand_api_username'] : $settings['paypal_live_api_username'];
                    $paypal_api_password  = ( 'test' === $mode ) ? $settings['paypal_sand_api_password'] : $settings['paypal_live_api_password'];
                    $paypal_api_signature = ( 'test' === $mode ) ? $settings['paypal_sand_api_secret'] : $settings['paypal_live_api_secret'];
                    
                    $request = array(
                        'USER'          => trim( $paypal_api_username ),
                        'PWD'           => trim( $paypal_api_password ),
                        'SIGNATURE'     => trim( $paypal_api_signature ),
                        'VERSION'       => '96.0', //The PayPal API version
                        'METHOD'        => 'GetTransactionDetails',
                        'TRANSACTIONID' => $transaction_id,
                    );
                    $response = wp_remote_post( $paypal_api_url, array( 'body' => $request, 'httpversion' => '1.1' ) ); 
                    
                    if ( !is_wp_error( $response ) ) {
                    
                        $array = array();
                        parse_str( wp_remote_retrieve_body( $response ), $response_array );
                        
                        $transaction_status = $response_array['PAYMENTSTATUS'];
                        $level = get_leaky_paywall_subscription_level( $response_array['L_NUMBER0'] );
                                
                        if ( !is_email( $user_email ) ) {
                            $user_email = $response_array['EMAIL'];
                        }
                            
                        if ( $transaction_id != $response_array['TRANSACTIONID'] )
                            throw new Exception( __( 'Error: Transaction IDs do not match! %s, %s', 'issuem-leaky-paywall' ) );
                        
                        if ( number_format( $response_array['AMT'], '2', '', '' ) != number_format( $level['price'], '2', '', '' ) )
                            throw new Exception( sprintf( __( 'Error: Amount charged is not the same as the subscription total! %s | %s', 'issuem-leaky-paywall' ), $response_array['AMT'], $level['price'] ) );
    
                        $args = array(
                            'level_id'          => $response_array['L_NUMBER0'],
                            'subscriber_id'     => $customer_id,
                            'subscriber_email'  => $user_email,
                            'price'             => $level['price'],
                            'description'       => $level['label'],
                            'payment_gateway'   => 'paypal_standard',
                            'payment_status'    => 'active',
                            'interval'          => $level['interval'],
                            'interval_count'    => $level['interval_count'],
                            'site'              => !empty( $level['site'] ) ? $level['site'] : '',
                        );
                        
                        //Mimic PayPal's Plan...
                        if ( !empty( $level['recurring'] ) && 'on' == $level['recurring'] )
                            $args['plan'] = $level['interval_count'] . ' ' . strtoupper( substr( $level['interval'], 0, 1 ) );
                                    
                        if ( is_user_logged_in() || $user = get_user_by( 'email', $user_email ) ) {
                            $user_id = leaky_paywall_update_subscriber( NULL, $user_email, $customer_id, $args ); //if the email already exists, we want to update the subscriber, not create a new one
                        } else {
                            $user_id = leaky_paywall_new_subscriber( NULL, $user_email, $customer_id, $args );
                        }
                        
                        wp_set_current_user( $user_id );
                        wp_set_auth_cookie( $user_id, true );
                        
                    } else {
                        
                        throw new Exception( $response->get_error_message() );
                        
                    }
                        
                    // send the newly created user to the appropriate page after logging them in
                                    if ( !empty( $settings['page_for_after_subscribe'] ) ) {
                                            wp_safe_redirect( get_page_link( $settings['page_for_after_subscribe'] ) );
                                    } else if ( !empty( $settings['page_for_profile'] ) ) {
                        wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
                    } else if ( !empty( $settings['page_for_subscription'] ) ) {
                        wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
                    }
                        
                }
                catch ( Exception $e ) {
                    
                    return new WP_Error( 'broke', sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) );
    
                }
                
            }               
            
        }
        
        return false;
        
    }
    
}

if ( !function_exists( 'leaky_paywall_free_registration_form' ) ) {
    
    function leaky_paywall_free_registration_form() {

        $level_id = $_GET['issuem-leaky-paywall-free-form'];
        $settings = get_leaky_paywall_settings();
        
        $return = '';
        if ( $level = get_leaky_paywall_subscription_level( $level_id ) ) {
            if ( empty( $level['price'] ) ) {
                if( $codes = leaky_paywall_errors()->get_error_codes() ) {
                    echo '<div class="leaky_paywall_errors">';
                        // Loop error codes and display errors
                        foreach( $codes as $code ){
                            $message = leaky_paywall_errors()->get_error_message( $code );
                            $return .= '<span class="error"><strong>' . __('Error') . '</strong>: ' . $message . '</span><br/>';
                        }
                    echo '</div>';
                }   
                
                $return .= '<h3>' . sprintf( __( 'Register for %s', 'issuem-leaky-paywall' ), $level['label'] ) . '</h3>';
                $return .= '<form id="leaky_paywall_registration_form" class="leaky_paywall_form" action="" method="POST">';
                $return .= '<fieldset>';
                $return .= '<p>';
                $return .= '<label for="leaky_paywall_user_Login" class="leaky-paywall-field-label">' . __( 'Username', 'issuem-leaky-paywall' ) . '</label>';
                $return .= '<input name="leaky_paywall_user_login" id="leaky_paywall_user_login" class="required" type="text"/>';
                $return .= '</p>';
                $return .= '<p>';
                $return .= '<label for="leaky_paywall_user_email" class="leaky-paywall-field-label">' . __( 'Email', 'issuem-leaky-paywall'  ) . '</label>';
                $return .= '<input name="leaky_paywall_user_email" id="leaky_paywall_user_email" class="required" type="email"/>';
                $return .= '</p>';
                $return .= '<p>';
                $return .= '<label for="leaky_paywall_user_first" class="leaky-paywall-field-label">' . __( 'First Name', 'issuem-leaky-paywall'  ) . '</label>';
                $return .= '<input name="leaky_paywall_user_first" id="leaky_paywall_user_first" type="text"/>';
                $return .= '</p>';
                $return .= '<p>';
                $return .= '<label for="leaky_paywall_user_last" class="leaky-paywall-field-label">' . __( 'Last Name', 'issuem-leaky-paywall'  ) . '</label>';
                $return .= '<input name="leaky_paywall_user_last" id="leaky_paywall_user_last" type="text"/>';
                $return .= '</p>';
                $return .= '<p>';
                $return .= '<label for="password" class="leaky-paywall-field-label">' . __( 'Password', 'issuem-leaky-paywall'  ) . '</label>';
                $return .= '<input name="leaky_paywall_user_pass" id="password" class="required" type="password"/>';
                $return .= '</p>';
                $return .= '<p>';
                $return .= '<label for="password_again" class="leaky-paywall-field-label">' . __( 'Password Again', 'issuem-leaky-paywall'  ) . '</label>';
                $return .= '<input name="leaky_paywall_user_pass_confirm" id="password_again" class="required" type="password"/>';
                $return .= '</p>';
                $return  = apply_filters( 'leaky_paywall_after_registration_fields', $return );
                $return .= '<p>';
                $return .= '<input type="hidden" name="leaky_paywall_register_nonce" value="' . wp_create_nonce('leaky_paywall-register-nonce') . '"/>';
                $return .= '<input type="hidden" name="leaky_paywall_register_level_id" value="' . $level_id . '"/>';
                $return .= '<input type="submit" name="issuem-leaky-paywall-free-return" value="' . __( 'Register Now', 'issuem-leaky-paywall' ) . '"/>';
                $return .= '</p>';
                $return .= '</fieldset>';
                $return .= '</form>';
            } else {
                $return .= __( 'Requested subscription level is not free', 'issuem-leaky-paywall' );
            }
        } else {
            $return .= __( 'Not a valid subscription level', 'issuem-leaky-paywall' );
        }
        
        return $return;
    }
    
}



if ( !function_exists( 'issuem_process_paypal_standard_ipn' ) ) {

    /**
     * Processes a PayPal IPN
     *
     * @since 1.1.0
     *
     * @param array $request
     */
    function issuem_process_paypal_standard_ipn( $mode = 'live' ) {

        $site = '';
            $settings = get_leaky_paywall_settings();
        $payload['cmd'] = '_notify-validate';
        foreach( $_POST as $key => $value ) {
            $payload[$key] = stripslashes( $value );
        }
        $paypal_api_url = !empty( $_REQUEST['test_ipn'] ) ? PAYPAL_PAYMENT_SANDBOX_URL : PAYPAL_PAYMENT_LIVE_URL;
        $response = wp_remote_post( $paypal_api_url, array( 'body' => $payload, 'httpversion' => '1.1' ) );
        $body = wp_remote_retrieve_body( $response );
        
        if ( 'VERIFIED' === $body ) {
        
            if ( !empty( $_REQUEST['txn_type'] ) ) {
                
                $args= array(
                    'level_id'          => isset( $_REQUEST['item_number'] ) ? $_REQUEST['item_number'] : $_REQUEST['custom'], //should be universal for all PayPal IPNs we're capturing
                    'description'       => $_REQUEST['item_name'], //should be universal for all PayPal IPNs we're capturing
                    'payment_gateway'   => 'paypal_standard',
                );

                $level = get_leaky_paywall_subscription_level( $args['level_id'] );
                $args['interval'] = $level['interval'];
                $args['interval_count'] = $level['interval_count'];
                
                if ( is_multisite_premium() && !empty( $level['site'] ) && !is_main_site( $level['site'] ) ) {
                    $site = '_' . $level['site'];
                } else {
                    $site = '';
                }

                switch( $_REQUEST['txn_type'] ) {
                                                
                    case 'web_accept':
                        if ( isset( $_REQUEST['mc_gross'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['mc_gross'];
                        } else if ( isset( $_REQUEST['payment_gross'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['payment_gross'];
                        }
                        
                        if ( isset( $_REQUEST['txn_id'] ) ) { //subscr_payment
                            $args['subscr_id'] = $_REQUEST['txn_id'];
                        }
                        
                        $args['plan'] = '';
                        
                        if ( 'completed' === strtolower( $_REQUEST['payment_status'] ) ) {
                            $args['payment_status'] = 'active';
                        } else {
                            $args['payment_status'] = 'deactivated';
                        }
                        break;
                        
                    case 'subscr_signup':
                        if ( isset( $_REQUEST['mc_amount3'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['mc_amount3'];
                        } else if ( isset( $_REQUEST['amount3'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['amount3'];
                        }
                        
                        if ( isset( $_REQUEST['subscr_id'] ) ) { //subscr_payment
                            $args['subscr_id'] = $_REQUEST['subscr_id'];
                        }
                        
                        if ( isset( $_REQUEST['period3'] ) ) {
                            $args['plan'] = $_REQUEST['period3'];
                            $new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $args['plan'] ), strtotime( $_REQUEST['subscr_date'] ) ) );
                            $args['expires'] = $new_expiration;
                        }
                        
                        $args['payment_status'] = 'active'; //It's a signup, of course it's active!
                        break;
                        
                    case 'subscr_payment':
                        if ( isset( $_REQUEST['mc_gross'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['mc_gross'];
                        } else if ( isset( $_REQUEST['payment_gross'] ) ) { //subscr_payment
                            $args['price'] = $_REQUEST['payment_gross'];
                        }
                        
                        if ( !empty( $_REQUEST['subscr_id'] ) ) { //subscr_payment
                            $args['subscr_id'] = $_REQUEST['subscr_id'];
                        }
                        
                        if ( 'completed' === strtolower( $_REQUEST['payment_status'] ) ) {
                            $args['payment_status'] = 'active';
                        } else {
                            $args['payment_status'] = 'deactivated';
                        }

                        $user = get_leaky_paywall_subscriber_by_subscriber_id( $args['subscr_id'], $mode );
                        
                        if ( is_multisite_premium() ) {
                            if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['subscr_id'] ) ) {
                                $site = '_' . $site_id;
                            }
                        }
                        
                        if ( !empty( $user ) && 0 !== $user->ID 
                            && ( $plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true ) )
                            && 'completed' === strtolower( $_REQUEST['payment_status'] ) ) {
                            $args['plan'] = $plan;
                            $new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $plan ), strtotime( $_REQUEST['payment_date'] ) ) );
                            $args['expires'] = $new_expiration;
                        } else {
                            $args['plan'] = $level['interval_count'] . ' ' . strtoupper( substr( $level['interval'], 0, 1 ) );
                            $new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $args['plan'] ), strtotime( $_REQUEST['payment_date'] ) ) );
                            $args['expires'] = $new_expiration;
                        }
                        break;
                        
                    case 'subscr_cancel':
                        if ( isset( $_REQUEST['subscr_id'] ) ) { //subscr_payment
                            $user = get_leaky_paywall_subscriber_by_subscriber_id( $_REQUEST['subscr_id'], $mode );
                            if ( is_multisite_premium() ) {
                                if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['subscr_id'] ) ) {
                                    $site = '_' . $site_id;
                                }
                            }
                            if ( !empty( $user ) && 0 !== $user->ID ) {
                                update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
                            }
                        }
                        return true; //We don't need to process anymore
                        
                    case 'subscr_eot':
                        if ( isset( $_REQUEST['subscr_id'] ) ) { //subscr_payment
                            $user = get_leaky_paywall_subscriber_by_subscriber_id( $_REQUEST['subscr_id'], $mode );
                            if ( is_multisite_premium() ) {
                                if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['subscr_id'] ) ) {
                                    $site = '_' . $site_id;
                                }
                            }
                            if ( !empty( $user ) && 0 !== $user->ID ) {
                                update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'expired' );
                            }
                        }
                        return true; //We don't need to process anymore
                        
                    case 'recurring_payment_suspended_due_to_max_failed_payment':
                        if ( isset( $_REQUEST['recurring_payment_id'] ) ) { //subscr_payment
                            $user = get_leaky_paywall_subscriber_by_subscriber_id( $args['recurring_payment_id'], $mode );
                            if ( is_multisite_premium() ) {
                                if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['recurring_payment_id'] ) ) {
                                    $site = '_' . $site_id;
                                }
                            }
                            if ( !empty( $user ) && 0 !== $user->ID ) {
                                update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
                            }
                        } 
                        return true; //We don't need to process anymore
                        
                    case 'recurring_payment_suspended':
                        if ( isset( $_REQUEST['subscr_id'] ) ) { //subscr_payment
                            $user = get_leaky_paywall_subscriber_by_subscriber_id( $_REQUEST['subscr_id'], $mode );
                            if ( is_multisite_premium() ) {
                                if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['subscr_id'] ) ) {
                                    $site = '_' . $site_id;
                                }
                            }
                            if ( !empty( $user ) && 0 !== $user->ID ) {
                                update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'suspended' );
                            }
                        } else if ( isset( $_REQUEST['recurring_payment_id'] ) ) { //subscr_payment
                            $user = get_leaky_paywall_subscriber_by_subscriber_id( $args['recurring_payment_id'], $mode );
                            if ( is_multisite_premium() ) {
                                if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['recurring_payment_id'] ) ) {
                                    $site = '_' . $site_id;
                                }
                            }
                            if ( !empty( $user ) && 0 !== $user->ID ) {
                                update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'suspended' );
                            }
                        } 
                        return true; //We don't need to process anymore
                }
            
                if ( !empty( $_REQUEST['custom'] ) && is_email( $_REQUEST['custom'] ) ) {
                    $user = get_user_by( 'email', $_REQUEST['custom'] );
                    if ( empty( $user ) ) {
                        $user = get_leaky_paywall_subscriber_by_subscriber_email( $_REQUEST['custom'], $mode );
                        if ( is_multisite_premium() ) {
                            if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( $_REQUEST['custom'] ) ) {
                                $args['site'] = $site_id;
                            }
                        }
                    }
                }
                    
                if ( empty( $user ) && !empty( $_REQUEST['payer_email'] ) && is_email( $_REQUEST['payer_email'] ) ) {
                    $user = get_user_by( 'email', $_REQUEST['payer_email'] );
                    if ( empty( $user ) ) {
                        $user = get_leaky_paywall_subscriber_by_subscriber_email( $_REQUEST['payer_email'], $mode );
                        if ( is_multisite_premium() ) {
                            if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( $_REQUEST['payer_email'] ) ) {
                                $args['site'] = $site_id;
                            }
                        }
                    }
                }
                    
                if ( empty( $user ) && !empty( $_REQUEST['txn_id'] ) ) {
                    $user = get_leaky_paywall_subscriber_by_subscriber_id( $_REQUEST['txn_id'], $mode );
                    if ( is_multisite_premium() ) {
                        if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['txn_id'] ) ) {
                            $args['site'] = $site_id;
                        }
                    }
                }
                    
                if ( empty( $user ) && !empty( $_REQUEST['subscr_id'] ) ) {
                    $user = get_leaky_paywall_subscriber_by_subscriber_id( $_REQUEST['subscr_id'], $mode );
                    if ( is_multisite_premium() ) {
                        if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $_REQUEST['subscr_id'] ) ) {
                            $args['site'] = $site_id;
                        }
                    }
                }
                
                if ( !empty( $user ) ) {
                    //WordPress user exists
                    $args['subscriber_email'] = $user->user_email;
                    leaky_paywall_update_subscriber( NULL, $args['subscriber_email'], $args['subscr_id'], $args );
                } else {
                    //Need to create a new user
                    $args['subscriber_email'] = is_email( $_REQUEST['custom'] ) ? $_REQUEST['custom'] : $_REQUEST['payer_email'];
                    leaky_paywall_new_subscriber( NULL, $args['subscriber_email'], $args['subscr_id'], $args );
                }
                
            }
        
        } else {
            
            error_log( sprintf( __( 'Invalid IPN sent from PayPal: %s', 'issuem-leaky-paywall' ), maybe_serialize( $payload ) ) );

        }
        
        return true;
        
    }
    
}



if ( !function_exists( 'issuem_process_stripe_webhook' ) ) {
    
    function issuem_process_stripe_webhook( $mode = 'live' ) {
        
        $body = @file_get_contents('php://input');
        $stripe_event = json_decode( $body );
        $settings = get_leaky_paywall_settings();
            
        if ( isset( $stripe_event->type ) ) {
            
            $stripe_object = $stripe_event->data->object;
        
            if ( !empty( $stripe_object->customer ) ) {
                $user = get_leaky_paywall_subscriber_by_subscriber_id( $stripe_object->customer, $mode );
            }
        
            if ( !empty( $user ) ) {
                
                if ( is_multisite_premium() ) {
                    if ( $site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $stripe_object->customer ) ) {
                        $site = '_' . $site_id;
                    }
                }
        
                //https://stripe.com/docs/api#event_types
                switch( $stripe_event->type ) {
        
                    case 'charge.succeeded' :
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
                        break;
                    case 'charge.failed' :
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
                        break;
                    case 'charge.refunded' :
                        if ( $stripe_object->refunded )
                            update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
                        else
                            update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
                        break;
                    case 'charge.dispute.created' :
                    case 'charge.dispute.updated' :
                    case 'charge.dispute.closed' :
                        break;
                    case 'customer.deleted' :
                            update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
                        break;
                        
                    case 'invoice.payment_succeeded' :
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
                        break;
                        
                    case 'invoice.payment_failed' :
                            update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
                        break;
                    
                    case 'customer.subscription.updated' :
                        $expires = date_i18n( 'Y-m-d 23:59:59', $stripe_object->current_period_end );
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
                        break;
                        
                    case 'customer.subscription.created' :
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
                        break;
                        
                    case 'customer.subscription.deleted' :
                        update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
                        break;
                        
        
                };
                
            }
                
        }

        
    }
    
}



if ( !function_exists( 'leaky_paywall_pay_with_stripe' ) ) {

    function leaky_paywall_pay_with_stripe( $level, $level_id ) {
    
        $results = '';
        $settings = get_leaky_paywall_settings();
        $currency = apply_filters( 'leaky_paywall_stripe_currency', $settings['leaky_paywall_currency'] );
        if ( in_array( strtoupper( $currency ), array( 'BIF', 'DJF', 'JPY', 'KRW', 'PYG', 'VND', 'XAF', 'XPF', 'CLP', 'GNF', 'KMF', 'MGA', 'RWF', 'VUV', 'XOF' ) ) ) {
            //Zero-Decimal Currencies
            //https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
            $stripe_price = number_format( $level['price'], '0', '', '' );
        } else {
            $stripe_price = number_format( $level['price'], '2', '', '' ); //no decimals
        }
        $publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];

        if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
            
            try {
            
                $stripe_plan = false;
                $time = time();

                if ( !empty( $level['plan_id'] ) ) {
                    //We need to verify that the plan_id matches the level details, otherwise we need to update it
                    try {
                        $stripe_plan = Stripe_Plan::retrieve( $level['plan_id'] );
                    }
                    catch( Exception $e ) {
                        $stripe_plan = false;
                    }
                    
                }
                
                if ( !is_object( $stripe_plan ) || //If we don't have a stripe plan
                    ( //or the stripe plan doesn't match...
                        $stripe_price                   != $stripe_plan->amount 
                        || $level['interval']       != $stripe_plan->interval 
                        || $level['interval_count'] != $stripe_plan->interval_count
                    ) 
                ) {
                
                    $args = array(
                        'amount'            => esc_js( $stripe_price ),
                        'interval'          => esc_js( $level['interval'] ),
                        'interval_count'    => esc_js( $level['interval_count'] ),
                        'name'              => esc_js( $level['label'] ) . ' ' . $time,
                        'currency'          => esc_js( $currency ),
                        'id'                => sanitize_title_with_dashes( $level['label'] ) . '-' . $time,
                    );
                    
                    $stripe_plan = Stripe_Plan::create( $args );
                    $settings['levels'][$level_id]['plan_id'] = $stripe_plan->id;
                    update_leaky_paywall_settings( $settings );
                                    
                }
                
                $results .= '<form action="' . esc_url( add_query_arg( 'issuem-leaky-paywall-stripe-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
                              <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
                              <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
                                      data-key="' . esc_js( $publishable_key ) . '"
                                      data-plan="' . esc_js( $stripe_plan->id ) . '" 
                                      data-currency="' . esc_js( $currency ) . '" 
                                      data-description="' . esc_js( $level['label'] ) . '">
                              </script>
                              ' . apply_filters( 'leaky_paywall_pay_with_stripe_recurring_payment_form_after_script', '' ) . '
                            </form>';
                                
            } catch ( Exception $e ) {

                $results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';

            }
            
        } else {
                        
            $results .= '<form action="' . esc_url( add_query_arg( 'issuem-leaky-paywall-stripe-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '" method="post">
                          <input type="hidden" name="custom" value="' . esc_js( $level_id ) . '" />
                          <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
                                  data-key="' . esc_js( $publishable_key ) . '"
                                  data-amount="' . esc_js( $stripe_price ) . '" 
                                  data-currency="' . esc_js( $currency ) . '" 
                                  data-description="' . esc_js( $level['label'] ) . '">
                          </script>
                              ' . apply_filters( 'leaky_paywall_pay_with_stripe_non_recurring_payment_form_after_script', '' ) . '
                        </form>';
        
        }
    
        return '<div class="leaky-paywall-stripe-button leaky-paywall-payment-button">' . $results . '</div>';

    }

}

if ( !function_exists( 'leaky_paywall_pay_with_paypal_standard' ) ) {

    function leaky_paywall_pay_with_paypal_standard( $level, $level_id ) {
        
        $results = '';
        $settings = get_leaky_paywall_settings();
        $mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
        $paypal_sandbox = 'off' === $settings['test_mode'] ? '' : 'sandbox';
        $paypal_account = 'on' === $settings['test_mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
        $currency = $settings['leaky_paywall_currency'];
        $current_user = wp_get_current_user();
        if ( 0 !== $current_user->ID ) {
            $user_email = $current_user->user_email;
        } else {
            $user_email = 'no_lp_email_set';
        }
        if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
                                                                                    
            $results .= '<script src="' . LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
                            data-env="' . esc_js( $paypal_sandbox ) . '" 
                            data-callback="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-' . $mode . '-ipn', '1', get_site_url() . '/' ) ) . '"
                            data-return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '"
                            data-cancel_return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '" 
                            data-src="1" 
                            data-period="' . esc_js( strtoupper( substr( $level['interval'], 0, 1 ) ) ) . '" 
                            data-recurrence="' . esc_js( $level['interval_count'] ) . '" 
                            data-currency="' . esc_js( apply_filters( 'leaky_paywall_paypal_currency', $currency ) ) . '" 
                            data-amount="' . esc_js( $level['price'] ) . '" 
                            data-name="' . esc_js( $level['label'] ) . '" 
                            data-number="' . esc_js( $level_id ) . '"
                            data-button="subscribe" 
                            data-no_note="1" 
                            data-no_shipping="1" 
                            data-custom="' . esc_js( $user_email ) . '"
                        ></script>';
                                                
        } else {
                        
            $results .= '<script src="' . LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
                            data-env="' . esc_js( $paypal_sandbox ) . '" 
                            data-callback="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-' . $mode . '-ipn', '1', get_site_url() . '/' ) ) . '" 
                            data-return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '"
                            data-cancel_return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '" 
                            data-tax="0" 
                            data-shipping="0" 
                            data-currency="' . esc_js( apply_filters( 'leaky_paywall_paypal_currency', $currency ) ) . '" 
                            data-amount="' . esc_js( $level['price'] ) . '" 
                            data-quantity="1" 
                            data-name="' . esc_js( $level['label'] ) . '" 
                            data-number="' . esc_js( $level_id ) . '"
                            data-button="buynow" 
                            data-no_note="1" 
                            data-no_shipping="1" 
                            data-shipping="0" 
                            data-custom="' . esc_js( $user_email ) . '"
                        ></script>';
        
        }
        
        return '<div class="leaky-paywall-paypal-standard-button leaky-paywall-payment-button">' . $results . '</div>';
        
    }

}

if ( !function_exists( 'leaky_paywall_pay_with_email' ) ) {
    
    function leaky_paywall_pay_with_email( $level, $level_id ) {
        
        $settings = get_leaky_paywall_settings();
        $results = '<a href="' . get_page_link( $settings['page_for_register'] ) . '?level_id=' . $level_id . '">Subscribe</a>';

        return '<div class="leaky-paywall-payment-button">' . $results . '</div>';
        
    }
    
}