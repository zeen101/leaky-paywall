<?php
/**
 * @package IssueM's Leaky Paywall
 * @since 1.0.0
 */
 
if ( !function_exists( 'get_issuem_leaky_paywall_settings' ) ) {

	/**
	 * Helper function to get IssueM's Leaky Paywall settings for current site
	 *
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function get_issuem_leaky_paywall_settings() {
	
		global $dl_pluginissuem_leaky_paywall;
		
		return $dl_pluginissuem_leaky_paywall->get_settings();
		
	}
	
}

if ( !function_exists( 'get_issuem_leaky_paywall_subscriber_by_email' ) ) {

	/**
	 * Gets Subscriber infromation from user's email address
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logged" in
	 * @return mixed $wpdb row object or false
	 */
	function get_issuem_leaky_paywall_subscriber_by_email( $email ) {
	
		global $wpdb;
			
		if ( is_email( $email ) ) {
			
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

			$user = get_user_by( 'email', $email );
			
			if ( !empty ( $user ) ) {
				$hash = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_hash', true );
				$subcriber = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_' . $hash, true );
				return $subcriber;
			}
			
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'get_issuem_leaky_paywall_subscriber_by_hash' ) ) {

	/**
	 * Gets Subscriber infromation from user's unique hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logged" in
	 * @return mixed $wpdb row object or false
	 */
	function get_issuem_leaky_paywall_subscriber_by_hash( $hash ) {

		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			$args = array(
				'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_hash',
				'meta_value' => $hash,
			);
			$users = get_users( $args );
		
			if ( !empty( $users ) ) {
				foreach ( $users as $user ) {
					//should really only be one
					$subcriber = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_' . $hash, true );
					return $subscriber;
				}
			}
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'add_issuem_leaky_paywall_hash' ) ) {

	/**
	 * Adds unique hash to login table for user's login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logging" in
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb insert ID or false
	 */
	function add_issuem_leaky_paywall_hash( $email, $hash ) {
	
		$expiration = apply_filters( 'leaky_paywall_login_link_expiration', 60 * 60 ); //1 hour
		set_transient( '_lpl_' . $hash, $email, $expiration );
			
	}
	
}

if ( !function_exists( 'verify_unique_issuem_leaky_paywall_hash' ) ) {

	/**
	 * Verifies hash is valid for login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb var or false
	 */
	function verify_unique_issuem_leaky_paywall_hash( $hash ) {
	
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			return ( false !== get_transient( '_lpl_' . $hash ) );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'verify_issuem_leaky_paywall_hash' ) ) {

	/**
	 * Verifies hash is valid length and hasn't expired
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb var or false
	 */
	function verify_issuem_leaky_paywall_hash( $hash ) {
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			return (bool) get_transient( '_lpl_' . $hash );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'get_issuem_leaky_paywall_email_from_login_hash' ) ) {

	/**
	 * Gets logging in user's email address from login link's hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return string email from $wpdb or false if invalid hash or expired link
	 */
	function get_issuem_leaky_paywall_email_from_login_hash( $hash ) {
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			return get_transient( '_lpl_' . $hash );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_has_user_paid' ) ) {

	/**
	 * Verified if user has paid through Stripe
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logged" in
	 * @return mixed Expiration date or subscriptions status or false if not paid
	 */
	function issuem_leaky_paywall_has_user_paid( $email ) {
		
		$settings = get_issuem_leaky_paywall_settings();
		
		if ( is_email( $email ) ) {
			
			if ( $customer = get_issuem_leaky_paywall_subscriber_by_email( $email ) ) {
		
				try {
					
					$settings = get_issuem_leaky_paywall_settings();
					$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
					$expires = $customer['expires'];
					
					if ( 'stripe' === $customer['payment_gateway'] ) {
								
						$cu = Stripe_Customer::retrieve( $customer['subscriber_id'] );
											
						if ( !empty( $cu ) )
							if ( !empty( $cu->deleted ) && true === $cu->deleted )
								return false;
						
						if ( !empty( $customer['lplan'] ) ) {
										
							if ( isset( $cu->subscription ) ) {
								
								if ( 'active' === $cu->subscription->status )
									return 'subscription';
						
							}
							
							return false;
							
						}
						
						$ch = Stripe_Charge::all( array( 'count' => 1, 'customer' => $customer['subscriber_id'] ) );
												
						if ( '0000-00-00 00:00:00' !== $expires ) {
							
							if ( strtotime( $expires ) > time() )
								if ( true === $ch->data[0]->paid && false === $ch->data[0]->refunded )
									return $expires;
							else
								return false;
									
						} else {
						
							return 'unlimited';
							
						}
					
					} else if ( 'paypal_standard' === $customer['payment_gateway'] ) {
						
						if ( '0000-00-00 00:00:00' === $expires )
							return 'unlimited';
						
						if ( !empty( $customer['plan'] ) && 'active' == $customer['payment_status'] )
							return 'subscription';
							
						switch( $customer['payment_status'] ) {
						
							case 'active':
							case 'refunded':
							case 'refund':
								if ( strtotime( $expires ) > time() )
									return $expires;
								else
									return false;
								break;
							case 'canceled':
								return 'canceled';
							case 'reversed':
							case 'buyer_complaint':
							case 'denied' :
							case 'expired' :
							case 'failed' :
							case 'voided' :
							case 'deactivated' :
								return false;
								break;
							
						}
						
					} else if ( 'manual' === $customer['payment_gateway'] ) {
							
						switch( $customer['payment_status'] ) {
						
							case 'active':
							case 'refunded':
							case 'refund':
								if ( $expires === '0000-00-00 00:00:00' )
									return 'unlimited';
									
								if ( strtotime( $expires ) > time() )
									return $expires;
								else
									return false;
								break;
							case 'canceled':
								if ( $expires === '0000-00-00 00:00:00' )
									return false;
								else
									return 'canceled';
							case 'reversed':
							case 'buyer_complaint':
							case 'denied' :
							case 'expired' :
							case 'failed' :
							case 'voided' :
							case 'deactivated' :
								return false;
								break;
							
						}
						
					}
					
				} catch ( Exception $e ) {
				
					echo '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>'; 
					
				}
				
				return false;
									
			}
			
		}
	
		return false;
		
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
	function issuem_process_paypal_standard_ipn() {
		
		$settings = get_issuem_leaky_paywall_settings();
		
		$subscriber_id = !empty( $_REQUEST['subscr_id'] ) ? $_REQUEST['subscr_id'] : false;
		$subscriber_id = !empty( $_REQUEST['recurring_payment_id'] ) ? $_REQUEST['recurring_payment_id'] : $subscriber_id;
		
		if ( !empty( $_REQUEST['txn_type'] ) ) {
			$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $_REQUEST['custom'] );
			
			if ( !empty( $subscriber ) ) {
				
				switch( $_REQUEST['txn_type'] ) {
				
					case 'web_accept':
						switch( strtolower( $_REQUEST['payment_status'] ) ) {
						
							case 'completed' :
							case 'reversed' :
								$subscriber['payment_status'] = strtolower( $_REQUEST['payment_status'] );
								break;			
						}
						break;
						
					case 'subscr_signup':
						$period = $_REQUEST['period3'];
						$subscriber['plan'] = strtoupper( $period );
						break;
						
					case 'subscr_payment':
						if ( $_REQUEST['txn_id'] === $subscriber['subscriber_id'] )
							$subscriber['subscriber_id'] = $_REQUEST['subscr_id'];
							
						if ( !empty( $subscriber['plan'] ) ) {// @todo
							$new_expiration = date( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $subscriber['plan'] ), strtotime( $_REQUEST['payment_date'] ) ) );
							switch( strtolower( $_REQUEST['payment_status'] ) ) {
								case 'completed' :
									$subscriber['expires'] = $new_expiration;
									break;
							}
						}
						break;
						
					case 'subscr_cancel':
						$subscriber['payment_status'] = 'canceled';
						break;
						
					case 'subscr_eot':
						$subscriber['payment_status'] = 'expired';
						break;
					
				}
				
			} else {
			
				error_log( sprintf( __( 'Unable to find PayPal subscriber: %s', 'issuem-leaky-paywall' ), maybe_serialize( $_REQUEST ) ) );
				
			}
			
		}
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_new_subscriber' ) ) {

	/**
	 * Adds new subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logged" in
	 * @param string $email address of user "logged" in
	 * @param object $customer Stripe object
	 * @param array $args Arguments passed from type of subscriber
	 * @param string $login optional login name to use instead of email address
	 * @return mixed $wpdb insert ID or false
	 */
	function issuem_leaky_paywall_new_subscriber( $hash, $email, $customer, $args, $login='' ) {
		
		if ( is_email( $email ) ) {
			
			$settings = get_issuem_leaky_paywall_settings();
			
			$expires = '0000-00-00 00:00:00';
			
			if ( $user = get_user_by( 'email', $email ) ) { 
				//the user already exists
				//grab the ID for later
				$user_id = $user->ID;
			} else {
				//the user doesn't already exist
				//create a new user with their email address as their username
				//grab the ID for later
				if ( empty( $login ) ) {
					$parts = explode( '@', $email );
					$login = $parts[0];
				}
                $userdata = array(
				    'user_login' => $login,
					'user_email' => $email,
					'user_pass'  => wp_generate_password(),
				);
				$user_id = wp_insert_user( $userdata ) ;
			}
			
			if ( isset( $customer->subscription ) ) { //only stripe
			
				$meta = array(
					'user_id'         => $user_id,
					'hash'			  => $hash,
					'subscriber_id'   => $customer->id,
					'price'			  => number_format( $customer->subscription->plan->amount / 100, '2', '.', '' ),
					'description' 	  => $customer->subscription->plan->name,
					'plan'		 	  => $customer->subscription->plan->id,
					'created'		  => date( 'Y-m-d H:i:s', $customer->subscription->start ),
					'expires'		  => $expires,
					'mode'			  => 'off' === $settings['test_mode'] ? 'live' : 'test',
					'payment_gateway' => 'stripe',
					'payment_status'  => $args['payment_status'],
				);		
						
			} else {
				
				if ( 0 !== $args['interval'] )
					$expires = date( 'Y-m-d 23:59:59', strtotime( '+' . $args['interval_count'] . ' ' . $args['interval'] ) ); //we're generous, give them the whole day!
				else if ( !empty( $args['expires'] ) )
					$expires = $args['expires'];
				
				$meta = array(
					'user_id'         => $user_id,
					'hash'			  => $hash,
					'subscriber_id'   => $customer->id,
					'price'			  => $args['price'],
					'description'	  => $args['description'],
					'plan'			  => '',
					'created'		  => date( 'Y-m-d H:i:s' ),
					'expires'		  => $expires,
					'mode'			  => 'off' === $settings['test_mode'] ? 'live' : 'test',
					'payment_gateway' => $args['payment_gateway'],
					'payment_status'  => $args['payment_status'],
				);

			}
			
			$meta = apply_filters( 'issuem_leaky_paywall_new_subscriber_meta', $meta, $email, $customer, $args );

            update_user_meta( $user_id, '_issuem_leaky_paywall_' . $meta['mode'] . '_hash', $hash );
            update_user_meta( $user_id, '_issuem_leaky_paywall_' . $meta['mode'] . '_' . $hash, $meta );
			
			do_action( 'issuem_leaky_paywall_new_subscriber', $email, $meta, $customer, $args );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_update_subscriber' ) ) {

	/**
	 * Updates an existing subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logged" in
	 * @param string $email address of user "logged" in
	 * @param object $customer Stripe object
	 * @param array $args Arguments passed from type of subscriber
	 * @return mixed $wpdb insert ID or false
	 */
	function issuem_leaky_paywall_update_subscriber( $hash, $email, $customer, $args ) {
		
		global $wpdb;
		
		if ( is_email( $email ) ) {
			
			$settings = get_issuem_leaky_paywall_settings();
			
			$expires = '0000-00-00 00:00:00';
			
			if ( $user = get_user_by( 'email', $email ) ) { 
				//the user already exists
				//grab the ID for later
				$user_id = $user->ID;
			} else {
				//the user doesn't already exist
				//create a new user with their email address as their username
				//grab the ID for later
                $parts = explode( '@', $email );
                $userdata = array(
				    'user_login' => $parts[0],
					'user_email' => $email,
					'user_pass'  => wp_generate_password(),
				);
				$user_id = wp_insert_user( $userdata ) ;
			}
						
			if ( isset( $customer->subscription ) ) { //only stripe
			
				$meta = array(
					'user_id'         => $user_id,
					'hash'			  => $hash,
					'subscriber_id'   => $customer->id,
					'price'			  => number_format( $customer->subscription->plan->amount, '2', '.', '' ),
					'description'	  => $customer->subscription->plan->name,
					'plan'			  => $customer->subscription->plan->id,
					'created'		  => date( 'Y-m-d H:i:s', $customer->subscription->start ),
					'expires'		  => $expires,
					'mode'			  => 'off' === $settings['test_mode'] ? 'live' : 'test',
					'payment_gateway' => 'stripe',
					'payment_status'  => $args['payment_status'],
				);		
						
			} else {
				
				if ( 0 !== $args['interval'] )
					$expires = date( 'Y-m-d 23:59:59', strtotime( '+' . $args['interval_count'] . ' ' . $args['interval'] ) ); //we're generous, give them the whole day!
				else if ( !empty( $args['expires'] ) )
					$expires = $args['expires'];
				
				$meta = array(
					'user_id'         => $user_id,
					'hash'			  => $hash,
					'subscriber_id'   => $customer->id,
					'price'			  => $args['price'],
					'description'	  => $args['description'],
					'plan'			  => '',
					'created'		  => date( 'Y-m-d H:i:s' ),
					'expires'		  => $expires,
					'mode'			  => 'off' === $settings['test_mode'] ? 'live' : 'test',
					'payment_gateway' => $args['payment_gateway'],
					'payment_status'  => $args['payment_status'],
				);

			}
			
			$meta = apply_filters( 'issuem_leaky_paywall_update_subscriber_meta', $meta, $email, $customer, $args );

            update_user_meta( $user_id, '_issuem_leaky_paywall_' . $meta['mode'] . '_hash', $hash );
            update_user_meta( $user_id, '_issuem_leaky_paywall_' . $meta['mode'] . '_' . $hash, $meta );
			
			do_action( 'issuem_leaky_paywall_update_subscriber', $email, $meta, $customer, $args );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_translate_payment_gateway_slug_to_name' ) ) {
	
	function issuem_translate_payment_gateway_slug_to_name( $slug ) {
		
		switch( $slug ) {
		
			case 'stripe':
				$return = 'Stripe';
				break;
				
			case 'paypal_standard':
				$return = 'PayPal';
				break;
				
			case 'manual':
				$return = __( 'Manually Added', 'issue-leaky-paywall' );
				break;
			
		}
		
		return apply_filters( 'issuem_translate_payment_gateway_slug_to_name', $return, $slug );
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_cancellation_confirmation' ) ) {

	/**
	 * Cancels a subscriber from Stripe subscription plan
	 *
	 * @since 1.0.0
	 *
	 * @return string Cancellation form output
	 */
	function issuem_leaky_paywall_cancellation_confirmation() {
		
		$settings = get_issuem_leaky_paywall_settings();
		
		$form = '';

		if ( isset( $_REQUEST['cancel'] ) && empty( $_REQUEST['cancel'] ) ) {

			$form = '<h3>' . __( 'Cancel Subscription', 'issuem-leaky-paywall' ) . '</h3>';

			$form .= '<p>' . __( 'Cancellations take effect at the end of your billing cycle, and we can’t give partial refunds for unused time in the billing cycle. If you still wish to cancel now, you may proceed, or you can come back later.', 'issuem-leaky-paywall' ) . '</p>';
			$form .= '<p>' . sprintf( __( ' Thank you for the time you’ve spent subscribed to %s. We hope you’ll return someday. ', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
			$form .= '<a href="' . add_query_arg( array( 'cancel' => 'confirm' ) ) . '">' . __( 'Yes, cancel my subscription!', 'issuem-leaky-paywall' ) . '</a> | <a href="' . get_home_url() . '">' . __( 'No, get me outta here!', 'issuem-leak-paywall' ) . '</a>';
			
			
		} else if ( !empty( $_REQUEST['cancel'] ) && 'confirm' === $_REQUEST['cancel'] ) {
		
			if ( isset( $_COOKIE['issuem_lp_subscriber'] ) ) {
				
				if ( $customer = get_issuem_leaky_paywall_subscriber_by_hash( $_COOKIE['issuem_lp_subscriber'] ) ) {
					
					if ( 'stripe' === $customer['payment_gateway'] ) {
					
						try {
							
							$secret_key = ( 'test' === $customer['mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
							
							$expires = $customer['expires'];
														
							$cu = Stripe_Customer::retrieve( $customer['subscriber_id'] );
								
							if ( !empty( $cu ) )
								if ( true === $cu->deleted )
									throw new Exception( __( 'Unable to find valid Stripe customer ID to unsubscribe. Please contact support', 'issuem-leaky-paywall' ) );
							
							$results = $cu->cancelSubscription();
												
							if ( 'canceled' === $results->status ) {
								
								$form .= '<p>' . sprintf( __( 'Your subscription has been successfully canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
								
								unset( $_SESSION['issuem_lp_hash'] );
								unset( $_SESSION['issuem_lp_email'] );
								unset( $_SESSION['issuem_lp_subscriber'] );
								setcookie( 'issuem_lp_subscriber', null, 0, '/' );
								
							} else {
							
								$form .= '<p>' . sprintf( __( 'ERROR: An error occured when trying to unsubscribe you from your account, please try again. If you continue to have trouble, please contact us. Thank you.', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
								
							}
							
							$form .= '<a href="' . get_home_url() . '">' . sprintf( __( 'Return to %s...', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
							
						} catch ( Exception $e ) {
						
							$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
							
						}
					
					} else if ( 'paypal_standard' === $customer['payment_gateway'] ) {

						$paypal_url   = 'test' === $customer['mode'] ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
						$paypal_email = 'test' === $customer['mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
						$form .= '<p>' . sprintf( __( 'You must cancel your account through PayPal. Please click this unsubscribe button to complete the cancellation process.', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
						$form .= '<p><a href="' . $paypal_url . '?cmd=_subscr-find&alias=' . urlencode( $paypal_email ) . '"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_unsubscribe_LG.gif" border="0"></a></p>';
					}
				}
				
			}
			
			
		}
		
		return $form;
		
	}
	
}

if ( !function_exists( 'send_leaky_paywall_email' ) ) {

	/**
	 * Function to generate and send leaky paywall login email to user
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address of user requesting login link
	 * @return bool True if successful, false if failed
	 */
	function send_leaky_paywall_email( $email ) {
	
		if ( !is_email( $email ) )
			return false; //We already checked, but want to be absolutely sure
			
		global $wpdb;
		
		$settings = get_issuem_leaky_paywall_settings();
		
		$login_url = get_page_link( $settings['page_for_login'] );
		$login_hash = issuem_leaky_paywall_hash( $email );
		
		add_issuem_leaky_paywall_hash( $email, $login_hash );
		
		$message  = 'Log into ' . $settings['site_name']  . ' by opening this link:' . "\r\n";
		$message .= add_query_arg( 'r', $login_hash, $login_url ) . "\r\n";
		$message .= 'This link will expire after an hour and can only be used once. To log into multiple browsers, send a login request from each one.' . "\r\n";
		$message .= " - " . $settings['site_name'] . "'s passwordless login system" . "\r\n";
		
		$message = apply_filters( 'leaky_paywall_login_email_message', $message );
		
		$headers = 'From: ' . $settings['from_name'] .' <' . $settings['from_email'] . '>' . "\r\n";
		
		return wp_mail( $email, __( 'Log into ' . get_bloginfo( 'name' ), 'issuem-leaky-paywall' ), $message, $headers );
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_hash' ) ) {

	/**
	 * Creates a 32-character hash string
	 *
	 * Generally used to create a unique hash for each subscriber, stored in the database
	 * and used for campaign links
	 *
	 * @since 1.0.0
	 *
	 * @param string $str String you want to hash
	 */
	function issuem_leaky_paywall_hash( $str ) {
	
		if ( defined( SECURE_AUTH_SALT ) )
			$salt[] = SECURE_AUTH_SALT;
			
		if ( defined( AUTH_SALT ) )
			$salt[] = AUTH_SALT;
		
		$salt[] = get_bloginfo( 'name' );
		$salt[] = time();
		
		$hash = md5( md5( implode( $salt ) ) . md5( $str ) );
		
		while( verify_unique_issuem_leaky_paywall_hash( $hash ) )
			$hash = issuem_leaky_paywall_hash( $hash ); // I did this on purpose...
			
		return $hash; // doesn't have to be too secure, just want a pretty random and very unique string
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_attempt_login' ) ) {

	function issuem_leaky_paywall_attempt_login( $login_hash ) {

		$_SESSION['issuem_lp_hash'] = $login_hash;

		if ( false !== $email = get_issuem_leaky_paywall_email_from_login_hash( $login_hash ) ) {

			$_SESSION['issuem_lp_email'] = $email;

			if ( false !== $expires = issuem_leaky_paywall_has_user_paid( $email ) ) {

				if ( $customer = get_issuem_leaky_paywall_subscriber_by_email( $email ) ) {

					if ( 'active' === $customer['payment_status'] ) {

						$_SESSION['issuem_lp_subscriber'] = $customer['hash'];
						setcookie( 'issuem_lp_subscriber', $_SESSION['issuem_lp_subscriber'], strtotime( apply_filters( 'issuem_leaky_paywall_logged_in_cookie_expiry', '+1 year' ) ), '/' );
						delete_transient( '_lpl_' . $login_hash ); //one time use
						wp_set_current_user( $customer['user_id'] );
						wp_set_auth_cookie( $customer['user_id'] );
						
					}

				}

			}

		}

	}

}

if ( !function_exists( 'is_issuem_leaky_subscriber_logged_in' ) ) {

	/**
	 * Checks if current user is logged in as a leaky paywall subscriber
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if logged in, else false
	 */
	function is_issuem_leaky_subscriber_logged_in() {
		
		if ( is_user_logged_in() && empty( $_SESSION['issuem_lp_subscriber'] ) ) {
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			$user_id = get_current_user_id();
			if ( $hash = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_hash', true ) ) {
				$_SESSION['issuem_lp_subscriber'] = $hash;
			}
		}
	
		if ( !empty( $_SESSION['issuem_lp_subscriber'] ) && empty( $_COOKIE['issuem_lp_subscriber'] ) ) {
		
			$_COOKIE['issuem_lp_subscriber'] = $_SESSION['issuem_lp_subscriber'];
			setcookie( 'issuem_lp_subscriber', $_SESSION['issuem_lp_subscriber'], strtotime( apply_filters( 'issuem_leaky_paywall_logged_in_cookie_expiry', '+1 year' ) ), '/' );

		}
			
		if ( !empty( $_COOKIE['issuem_lp_subscriber'] ) ) {

			$_SESSION['issuem_lp_subscriber'] = $_COOKIE['issuem_lp_subscriber'];
			
			if ( empty( $_SESSION['issuem_lp_email'] ) ) 
				$_SESSION['issuem_lp_email'] = issuem_leaky_paywall_get_email_from_subscriber_hash( $_COOKIE['issuem_lp_subscriber'] );
				
			if ( !is_user_logged_in() ) {
				//For the off-chance a user gets automatically logged out of WordPress, but remains logged in via Leaky Paywall...
	
				if ( $customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) ) {

					wp_set_current_user( $customer['user_id'] );
					wp_set_auth_cookie( $customer['user_id'] );
				
				}
			
			}
			
			if ( false !== $expires = issuem_leaky_paywall_has_user_paid( $_SESSION['issuem_lp_email'] ) )
				return true;
			
		}
				
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_susbscriber_restrictions' ) ) {
	
	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 2.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function issuem_leaky_paywall_susbscriber_restrictions() {
	
		$settings = get_issuem_leaky_paywall_settings();
		$restriction_level = issuem_leaky_paywall_susbscriber_current_level_id();
		
		if ( !empty( $settings['levels'][$restriction_level] ) )
			return $settings['levels'][$restriction_level];
		
		return false;
		
	}
}

if ( !function_exists( 'issuem_leaky_paywall_susbscriber_current_level_id' ) ) {
	
	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 2.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function issuem_leaky_paywall_susbscriber_current_level_id() {
	
		if ( is_issuem_leaky_subscriber_logged_in() ) {
			
			if ( $customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) ) {
			
				$settings = get_issuem_leaky_paywall_settings();
				$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

				return get_user_meta( $customer['user_id'], '_issuem_leaky_paywall_' . $mode . '_level_id', true );
			
			}
			
		}
		
		return false;
		
	}
}

if ( !function_exists( 'issuem_leaky_paywall_get_email_from_subscriber_hash' ) ){

	/**
	 * Gets email address from subscriber's hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logged" in
	 * @return mixed $wpdb var or false if invalid hash
	 */
	function issuem_leaky_paywall_get_email_from_subscriber_hash( $hash ) {
	
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			$args = array(
				'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_hash',
				'meta_value' => $hash,
			);
			$users = get_users( $args );
		
			if ( !empty( $users ) ) {
				foreach ( $users as $user ) {
					//should really only be one
					return $user->user_email;
				}
			}
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_subscriber_query' ) ){

	/**
	 * Gets leaky paywall subscribers
	 *
	 * @since 1.1.0
	 *
	 * @param array $args Leaky Paywall Subscribers
	 * @return mixed $wpdb var or false if invalid hash
	 */
	function issuem_leaky_paywall_subscriber_query( $args ) {
	
		if ( !empty( $args ) ) {
			
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			if ( !empty( $args['search'] ) ) {
				$args['meta_query'] = array(
					'relation' => 'AND',
					array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_hash',
						'compare' => 'EXISTS',
					),
					array(
						'value'   => $args['search'],
						'compare' => 'LIKE',
					),
				);
				unset( $args['search'] );
			} else {
				$args['meta_query'] = array(
					array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_hash',
						'compare' => 'EXISTS',
					),
				);
			}
			$users = get_users( $args );
			return $users;

		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_logout_process' ) ) {
	
	/**
	 * Removes all cookies and session variables for Leaky Paywall subscriber
	 *
	 * @since 2.0.0
	 */
	function issuem_leaky_paywall_logout_process() {
		unset( $_SESSION['issuem_lp_hash'] );
		unset( $_SESSION['issuem_lp_email'] );
		unset( $_SESSION['issuem_lp_subscriber'] );
		setcookie( 'issuem_lp_subscriber', null, 0, '/' );
	}
	add_action( 'wp_logout', 'issuem_leaky_paywall_logout_process' ); //hook into the WP logout process
}

if ( !function_exists( 'issuem_leaky_paywall_server_pdf_download' ) ) {

	function issuem_leaky_paywall_server_pdf_download( $download_id ) {
	    // Grab the download info
	    $url = wp_get_attachment_url( $download_id );
	    	
	    // Attempt to grab file
	    if ( $response = wp_remote_head( str_replace( ' ', '%20', $url ) ) ) {
	        if ( ! is_wp_error( $response ) ) {
	            $valid_response_codes = array(
	                200,
	            );
	            if ( in_array( wp_remote_retrieve_response_code( $response ), (array) $valid_response_codes ) ) {
		
	                // Get Resource Headers
	                $headers = wp_remote_retrieve_headers( $response );
	
	                // White list of headers to pass from original resource
	                $passthru_headers = array(
	                    'accept-ranges',
	                    'content-length',
	                    'content-type',
	                );
	
	                // Set Headers for download from original resource
	                foreach ( (array) $passthru_headers as $header ) {
	                    if ( isset( $headers[$header] ) )
	                        header( esc_attr( $header ) . ': ' . esc_attr( $headers[$header] ) );
	                }
	
	                // Set headers to force download
	                header( 'Content-Description: File Transfer' );
	                header( 'Content-Disposition: attachment; filename=' . basename( $url ) );
	                header( 'Content-Transfer-Encoding: binary' );
	                header( 'Expires: 0' );
	                header( 'Cache-Control: must-revalidate' );
	                header( 'Pragma: public' );
	
	                // Clear buffer
	                flush();
	
	                // Deliver the file: readfile, curl, redirect
	                if ( ini_get( 'allow_url_fopen' ) ) {
	                    // Use readfile if allow_url_fopen is on
	                    readfile( str_replace( ' ', '%20', $url )  );
	                } else if ( is_callable( 'curl_init' ) ) {
	                    // Use cURL if allow_url_fopen is off and curl is available
	                    $ch = curl_init( str_replace( ' ', '%20', $url ) );
	                    curl_exec( $ch );
	                    curl_close( $ch );
	                } else {
	                    // Just redirect to the file becuase their host <strike>sucks</strike> doesn't support allow_url_fopen or curl.
	                    wp_redirect( str_replace( ' ', '%20', $url ) );
	                }
	                die();
	
	            } else {
					$output = '<h3>' . __( 'Error Downloading PDF', 'issuem-leaky-paywall' ) . '</h3>';
		
					$output .= '<p>' . sprintf( __( 'Download Error: Invalid response: %s', 'issuem-leaky-paywall' ), wp_remote_retrieve_response_code( $response ) ) . '</p>';
					$output .= '<a href="' . get_home_url() . '">' . __( 'Home', 'issuem-leak-paywall' ) . '</a>';
	            	
		            wp_die( $output );
	            }
	        } else {
				$output = '<h3>' . __( 'Error Downloading PDF', 'issuem-leaky-paywall' ) . '</h3>';
	
				$output .= '<p>' . sprintf( __( 'Download Error: %s', 'issuem-leaky-paywall' ), $response->get_error_message() ) . '</p>';
				$output .= '<a href="' . get_home_url() . '">' . __( 'Home', 'issuem-leak-paywall' ) . '</a>';
            	
	            wp_die( $output );
	        }
	    }
	}
}

if ( !function_exists( 'build_issuem_leaky_paywall_subscription_levels_row' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_issuem_leaky_paywall_subscription_levels_row( $level=array(), $row_key='' ) {
	
		if ( empty( $level ) ) {
			$level = array(
				'label' 				=> '',
				'price' 				=> '',
				'interval_count' 		=> 1,
				'interval' 				=> 'month',
				'recurring' 			=> 'off',
				'plan_id' 				=> '',
				'post_types' => array(
					array(
						'post_type' 	=> ISSUEM_ACTIVE_LP ? 'article' : 'post',
						'allowed' 		=> 'unlimited',
						'allowed_value' => -1
					)
				)
			);
		}
    	
    	if ( empty( $level['recurring'] ) )
    		$level['recurring'] = 'off';
		
		$return  = '<table class="issuem-leaky-paywall-subscription-level-row-table leaky-paywall-table">';
		$return .= '<tr>';
		$return .= '<th><label for="level-name-' . $row_key . '">' . __( 'Subscription Name', 'issuem-leaky-paywall' ) . '</label></th>';
		$return .= '<td>';
		$return .= '<input id="level-name-' . $row_key . '" type="text" name="levels[' . $row_key . '][label]" value="' . htmlspecialchars( stripcslashes( $level['label'] ) ) . '" />';
		$return .= '<span class="delete-x delete-subscription-level">&times;</span>';
		$return .= '</td>';
		$return .= '</tr>';
		    
		$return .= '<tr>';		
		$return .= '<th><label for="level-recurring-' . $row_key . '">' . __( 'Recurring?', 'issuem-leaky-paywall' ) . '</label></th>';
		$return .= '<td><input id="level-recurring-' . $row_key . '" class="stripe-recurring" type="checkbox" name="levels[' . $row_key . '][recurring]" value="on" ' . checked( 'on', $level['recurring'], false ) . ' /></td>';
		$return .= '</tr>';	
						
		$return .= '<tr>';	
		$return .= '<th><label for="level-price-' . $row_key . '">' . __( 'Subscription Price', 'issuem-leaky-paywall' ) . '</label></th>';
		$return .= '<td><input id="level-price-' . $row_key . '" type="text" class="small-text" name="levels[' . $row_key . '][price]" value="' . stripcslashes( $level['price'] ) . '" /></td>';	
		$return .= '</tr>';	
				
		$return .= '<tr>';	
		$return .= '<th><label for="level-interval-count-' . $row_key . '">' . __( 'Subscription Length', 'issuem-leaky-paywall' ) . '</label></th>';
		$return .= '<td>';
		$return .= __( 'For', 'issuem-leaky-paywall' ) . ' <input id="level-interval-count-' . $row_key . '" type="text" class="small-text" name="levels[' . $row_key . '][interval_count]" value="' . stripcslashes( $level['interval_count'] ) . '" />';	
		$return .= '<select id="interval" name="levels[' . $row_key . '][interval]">';
        $return .= '  <option value="day" ' . selected( 'day' === $level['interval'], true, false ) . '>' . __( 'Day(s)', 'issuem-leaky-paywall' ) . '</option>';
        $return .= '  <option value="week" ' . selected( 'week' === $level['interval'], true, false ) . '>' . __( 'Week(s)', 'issuem-leaky-paywall' ) . '</option>';
        $return .= '  <option value="month" ' . selected( 'month' === $level['interval'], true, false ) . '>' . __( 'Month(s)', 'issuem-leaky-paywall' ) . '</option>';
        $return .= '  <option value="year" ' . selected( 'year' === $level['interval'], true, false ) . '>' . __( 'Year(s)', 'issuem-leaky-paywall' ) . '</option>';
        $return .= '</select>';
        $return .= '<p class="description">' . __( 'Enter 0 for unlimited access.', 'issuem-leaky-paywall' ) . '</p>';
        $return .= '</td>';
		$return .= '</tr>';
        		
		$return .= '<tr>';
		$return .= '<th>' . __( 'Access Options', 'issuem-leaky-paywall' ) . '</th>';
		$return .= '<td id="issuem-leaky-paywall-subsciption-row-' . $row_key . '-post-types">';
		$last_key = -1;
		if ( !empty( $level['post_types'] ) ) {
			foreach( $level['post_types'] as $select_post_key => $select_post_type ) {
				$return .= build_issuem_leaky_paywall_subscription_row_post_type( $select_post_type, $select_post_key, $row_key );
				$last_key = $select_post_key;
			}
		}
		$return .= '</td>';
		$return .= '</tr>';
		
		$return .= '<tr>';
		$return .= '<th>&nbsp;</th>';
		$return .= '<td>';
        $return .= '<script type="text/javascript" charset="utf-8">';
        $return .= '    var issuem_leaky_paywall_subscription_row_' . $row_key . '_last_post_type_key = ' . $last_key;
        $return .= '</script>';
		$return .= '<p><input data-row-key="' . $row_key . '" class="button-secondary" id="add-subscription-row-post-type" class="add-new-issuem-leaky-paywall-row-post-type" type="submit" name="add_issuem_leaky_paywall_subscription_row_post_type" value="' . __( 'Add New Post Type', 'issuem-leaky-paywall' ) . '" /></p>';
		$return .= '</td>';
		$return .= '</tr>';
		
		$return .= '</table>';
		
		return $return;
		
	}
	
}
 
if ( !function_exists( 'build_issuem_leaky_paywall_subscription_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_issuem_leaky_paywall_subscription_row_ajax() {
		if ( isset( $_REQUEST['row-key'] ) )
			die( build_issuem_leaky_paywall_subscription_levels_row( array(), $_REQUEST['row-key'] ) );
		else
			die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row', 'build_issuem_leaky_paywall_subscription_row_ajax' );
	
}
 
if ( !function_exists( 'build_issuem_leaky_paywall_subscription_row_post_type' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_issuem_leaky_paywall_subscription_row_post_type( $select_post_type=array(), $select_post_key='', $row_key='' ) {

		if ( empty( $select_post_type ) ) {
			$select_post_type = array(
				'post_type' 	=> ISSUEM_ACTIVE_LP ? 'article' : 'post',
				'allowed' 		=> 'unlimited',
				'allowed_value' => -1
			);
		}
		
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		$post_types = get_post_types( array(), 'objects' );

		$return  = '<div class="issuem-leaky-paywall-row-post-type">';
		$return .= '<label>' . __( 'Post Type', 'issuem-leaky-paywall' ) . '</label>';
		$return .= '<select name="levels[' . $row_key . '][post_types][' . $select_post_key . '][post_type]">';
		
		foreach ( $post_types as $post_type ) {
			
			if ( in_array( $post_type->name, $hidden_post_types ) ) 
				continue;
				
			$return .= '<option value="' . $post_type->name . '" ' . selected( $post_type->name, $select_post_type['post_type'], false ) . '>' . $post_type->labels->name . '</option>';
		
        }
		$return .= '</select>';
		
		$return .= '<select class="allowed_type" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed]">';						
			$return .= '<option value="unlimited" ' . selected( 'unlimited', $select_post_type['allowed'], false ) . '>' . __( 'Unlimited Access', 'issuem-leaky-paywall' ) . '</option>';
			$return .= '<option value="limited" ' . selected( 'limited', $select_post_type['allowed'], false ) . '>' . __( 'Limited Access', 'issuem-leaky-paywall' ) . '</option>';
		$return .= '</select>';
			
		if ( 'unlimited' == $select_post_type['allowed'] ) {
			$allowed_value_input_style = 'display: none;';
		} else {
			$allowed_value_input_style = '';
		}
			    
		$return .= '<input type="text" class="allowed_value small-text" style="' . $allowed_value_input_style . '" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed_value]" value="' . $select_post_type['allowed_value'] . '" />';
				
		$return .= '<span class="delete-x delete-post-type-row">&times;</span>';
		$return .= '</div>';
		
		return $return;
		
	}
	
}
 
if ( !function_exists( 'build_issuem_leaky_paywall_subscription_row_post_type_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_issuem_leaky_paywall_subscription_row_post_type_ajax() {
	
		if ( isset( $_REQUEST['select-post-key'] ) && isset( $_REQUEST['row-key'] ) )
			die( build_issuem_leaky_paywall_subscription_row_post_type( array(), $_REQUEST['select-post-key'], $_REQUEST['row-key'] ) );
		else
			die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row-post-type', 'build_issuem_leaky_paywall_subscription_row_post_type_ajax' );
	
}

if ( !function_exists( 'build_issuem_leaky_paywall_default_restriction_row' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_issuem_leaky_paywall_default_restriction_row( $restriction=array(), $row_key='' ) {

		if ( empty( $restriction ) ) {
			$restriction = array(
				'post_type' 	=> '',
				'allowed_value' => '0',
			);
		}
    	
		$return  = '<tr class="issuem-leaky-paywall-restriction-row">';
		$return .= '<th><label for="restriction-post-type-' . $row_key . '">' . __( 'Restriction', 'issuem-leaky-paywall' ) . '</label></th>';
		
		$return .= '<td>';
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		$post_types = get_post_types( array(), 'objects' );
	    $return .= '<label for="restriction-post-type-' . $row_key . '">' . __( 'Number of', 'issuem-leaky-paywall' ) . '</label> ';
		$return .= '<select id="restriction-post-type-' . $row_key . '" name="restrictions[post_types][' . $row_key . '][post_type]">';
		foreach ( $post_types as $post_type ) {
		
			if ( in_array( $post_type->name, $hidden_post_types ) ) 
				continue;
			
			$return .= '<option value="' . $post_type->name . '" ' . selected( $post_type->name, $restriction['post_type'], false ) . '>' . $post_type->labels->name . '</option>';
		
		}
		$return .= '</select> ';
		
	    $return .= '<label for="restriction-allowed-' . $row_key . '">' . __( 'allowed:', 'issuem-leaky-paywall' ) . '</label> ';
		$return .= '<input id="restriction-allowed-' . $row_key . '" type="text" class="small-text" name="restrictions[post_types][' . $row_key . '][allowed_value]" value="' . $restriction['allowed_value'] . '" />';

		$return .= '<span class="delete-x delete-restriction-row">&times;</span>';
		$return .= '</td>';
		$return .= '</tr>';
		
		return $return;
		
	}
	
}
 
if ( !function_exists( 'build_issuem_leaky_paywall_default_restriction_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_issuem_leaky_paywall_default_restriction_row_ajax() {
	
		if ( isset( $_REQUEST['row-key'] ) )
			die( build_issuem_leaky_paywall_default_restriction_row( array(), $_REQUEST['row-key'] ) );
		else
			die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-restriction-row', 'build_issuem_leaky_paywall_default_restriction_row_ajax' );
	
}

if ( !function_exists( 'wp_print_r' ) ) { 

	/**
	 * Helper function used for printing out debug information
	 *
	 * HT: Glenn Ansley @ iThemes.com
	 *
	 * @since 1.0.0
	 *
	 * @param int $args Arguments to pass to print_r
	 * @param bool $die TRUE to die else FALSE (default TRUE)
	 */
    function wp_print_r( $args, $die = true ) { 
	
        $echo = '<pre>' . print_r( $args, true ) . '</pre>';
		
        if ( $die ) die( $echo );
        	else echo $echo;
		
    }   
	
}

if ( !function_exists( 'issuem_leaky_paywall_maybe_process_payment' ) ) {
	
	function issuem_leaky_paywall_maybe_process_payment() {
		
		$settings = get_issuem_leaky_paywall_settings();
		$results = '';
	
		if ( !empty( $_POST['stripeToken'] ) ) {
			
			try {

				$token = $_POST['stripeToken'];
				
				if ( $existing_customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) )
					$cu = Stripe_Customer::retrieve( $existing_customer['subscriber_id'] );
					
				if ( !empty( $cu ) )
					if ( true === $cu->deleted )
						$cu = array();
									
				$customer_array = array(
						'email' => $_SESSION['issuem_lp_email'],
						'card'  => $token,
				);
			
				if ( 'on' === $recurring && !empty( $plan_id ) ) {
					
					$customer_array['plan'] = $plan_id;	
					
					if ( !empty( $cu ) )
						$cu->updateSubscription( array( 'plan' => $plan_id ) );
					else
						$cu = Stripe_Customer::create( $customer_array );
					
				} else {
				
					if ( !empty( $cu ) ) {
						
						$cu->card = $token;
						$cu->save();
						
					} else {
							
						$cu = Stripe_Customer::create( $customer_array );
						
					}
					
					$charge = Stripe_Charge::create(array(
						'customer' 		=> $cu->id,
						'amount'   		=> $stripe_price,
						'currency' 		=> apply_filters( 'issuem_leaky_paywall_stripe_currency', 'usd' ), //currently Stripe only supports USD and CAD
						'description'	=> $description,
					));
				
				}
				
				$unique_hash = issuem_leaky_paywall_hash( $_SESSION['issuem_lp_email'] );
				
				$args['payment_status'] = 'active';
					
				if ( !empty( $existing_customer ) )
					issuem_leaky_paywall_update_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args ); //if the email already exists, we want to update the subscriber, not create a new one
				else
					issuem_leaky_paywall_new_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args );
					
				$_SESSION['issuem_lp_subscriber'] = $unique_hash;
				
				$results .= '<h1>' . __( 'Successfully subscribed!' , 'issuem-leaky-paywall' ) . '</h1>';
				
			} catch ( Exception $e ) {
				
				$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
				
			}
			
		} else if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-return'] ) ) {
						
			if ( !empty( $_REQUEST['tx'] ) ) //if PDT is enabled
				$transaction_id = $_REQUEST['tx'];
			else if ( !empty( $_REQUEST['txn_id'] ) ) //if PDT is not enabled
				$transaction_id = $_REQUEST['txn_id'];
			else
				$transaction_id = NULL;
				
			if ( !empty( $_REQUEST['cm'] ) )
				$transient_transaction_id = $_REQUEST['cm'];
			else
				$transient_transaction_id = NULL;
	
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
	
					$cu = new stdClass;
					$cu->id = $transaction_id; //temporary, will be replaced with subscriber ID during IPN
				
					if ( number_format( $transaction_amount, '2', '', '' ) != number_format( $price, '2', '', '' ) )
						throw new Exception( sprintf( __( 'Error: Amount charged is not the same as the cart total! %s | %s', 'issuem-leaky-paywall' ), $AMT, $transaction_object->total ) );
						
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
						
					$error = false;
	
				}
				catch ( Exception $e ) {
					
					$error = true;
					$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
	
				}
				
				if ( !$error ) {
	
					$unique_hash = issuem_leaky_paywall_hash( $_SESSION['issuem_lp_email'] );
					
					if ( $existing_customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) )
						issuem_leaky_paywall_update_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args ); //if the email already exists, we want to update the subscriber, not create a new one
					else
						issuem_leaky_paywall_new_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args );
						
					$_SESSION['issuem_lp_subscriber'] = $unique_hash;
					
					$results .= '<h1>' . __( 'Successfully subscribed!' , 'issuem-leaky-paywall' ) . '</h1>';
					
				}
				
			}				
			
		}
		
		return $results;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_subscription_options' ) ) {
	
	function issuem_leaky_paywall_subscription_options() {
		
		$settings = get_issuem_leaky_paywall_settings();
		$current_level_id = issuem_leaky_paywall_susbscriber_current_level_id();
		
		$results = apply_filters( 'issuem_leaky_paywall_subscription_options', '' );
		//If someone wants to completely override this, they can with the above filter
		if ( empty( $results ) ) {
		
			$results .= '<h2>' . __( 'Subscription Options', 'issuem-leaky-paywall' ) . '</h2>';
			
			$results .= '<div class="leaky_paywall_subscription_options">';
			foreach( $settings['levels'] as $key => $level ) {
			
				$payment_options = '';
				$allowed_content = '';
				
				if ( (string)$key === $current_level_id )
					$current_level = 'current-level';
				else
					$current_level = '';
			
				$results .= '<div class="leaky_paywall_subscription_option ' . $current_level. '">';
				$results .= '<h3>' . $level['label'] . '</h3>';
				
				$results .= '<div class="leaky_paywall_subscription_allowed_content">';
				foreach( $level['post_types'] as $post_type ) {
				
					$post_type_obj = get_post_type_object( $post_type['post_type'] );
				
					if ( 0 <= $post_type['allowed_value'] )
						$allowed_content .= '<p>'  . sprintf( __( 'Access %s %s', 'issuem-leaky-paywall' ), $post_type['allowed_value'], $post_type_obj->labels->name ) .  '</p>';
					else
						$allowed_content .= '<p>' . sprintf( __( 'Unlimited %s', 'issuem-leaky-paywall' ), $post_type_obj->labels->name ) . '</p>';
						
				}
				$results .= apply_filters( 'issuem_leaky_paywall_subscription_options_allowed_content', $allowed_content, $level );
				$results .= '</div>';
				
				$results .= '<div class="leaky_paywall_subscription_price">';
				$results .= '<p>';
				if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] )
					$results .= '<strong>' . sprintf( __( '$%s %s (recurring)', 'issuem-leaky-paywall' ), number_format( $level['price'], 2 ), issuem_leaky_paywall::human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
				else
					$results .= '<strong>' . sprintf( __( '$%s %s', 'issuem-leaky-paywall' ), number_format( $level['price'], 2 ), issuem_leaky_paywall::human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
				
				if ( !empty( $level['trial_period'] ) ) {
					$results .= '<span class="leaky-paywall-trial-period">' . sprintf( __( 'Free for the first %s day(s)', 'issuem-leaky-paywall' ), $level['trial_period'] ) . '</span>';
				}
				$results .= '</p>';
				$results .= '</div>';
								
				//Don't show payment options if the users is currently subscribed to this level
				if ( (string)$key !== $current_level_id ) {
					$results .= '<div class="leaky_paywall_subscription_payment_options">';
					if ( in_array( 'stripe', $settings['payment_gateway'] ) )
						$payment_options .= issuem_leaky_paywall_pay_with_stripe( $level, $key );
						
					if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) )
						$payment_options .= issuem_leaky_paywall_pay_with_paypal_standard( $level, $key );
						
					$results .= apply_filters( 'issuem_leaky_paywall_subscription_options_payment_options', $payment_options, $level );
					$results .= '</div>';
				} else {
					$results .= '<div class="leaky_paywall_subscription_current_level">';
					$results .= __( 'Current Subscription', 'issuem-leaky-paywall' );
					$results .= '</div>';
				}
				
				$results .= '</div>';
			
			
			}
			$results .= '</div>';
		
		}
		
		return $results;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_pay_with_stripe' ) ) {

	function issuem_leaky_paywall_pay_with_stripe( $level, $level_id ) {
	
		$results = '';
		$settings = get_issuem_leaky_paywall_settings();
		$stripe_price = number_format( $level['price'], '2', '', '' ); //no decimals
		$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];

		if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
			
			try {
			
				if ( empty( $level['plan_id'] ) ) {
					//If no plan ID has been set for this plan, we need to create one with the Stripe API and save it
					
				} else {
					//We need to verify that the plan_id matches the level details, otherwise we need to update it
					$stripe_plan = Stripe_Plan::retrieve( $level['plan_id'] );
					
				}
				
				$results .= '<form action="" method="post">
							  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
									  data-key="' . esc_js( $publishable_key ) . '"
									  data-plan="' . esc_js( $stripe_price ) . '" 
									  data-description="' . esc_js( $level['label'] ) . '">
							  </script>
							</form>';
								
			} catch ( Exception $e ) {

				$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';

			}
			
		} else {
						
			$results .= '<form action="" method="post">
						  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
								  data-key="' . esc_js( $publishable_key ) . '"
								  data-amount="' . esc_js( $stripe_price ) . '" 
								  data-description="' . esc_js( $level['label'] ) . '">
						  </script>
						</form>';
		
		}
	
		return '<div class="leaky-paywall-stripe-button leaky-paywall-payment-button">' . $results . '</div>';

	}

}

if ( !function_exists( 'issuem_leaky_paywall_pay_with_paypal_standard' ) ) {

	function issuem_leaky_paywall_pay_with_paypal_standard( $level, $level_id ) {
		
		$results = '';
		$settings = get_issuem_leaky_paywall_settings();
		$mode = 'on' === $settings['test_mode'] ? 'sandbox' : '';
		$paypal_account = 'on' === $settings['test_mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
		
		if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
																					
			$results .= '<script src="' . ISSUEM_LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
							data-env="' . esc_js( $mode ) . '" 
							data-callback="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-ipn', '1', get_site_url() ) . '/' ) . '"
							data-return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '"
							data-cancel_return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '" 
							data-src="1" 
							data-period="' . esc_js( strtoupper( substr( $level['interval'], 0, 1 ) ) ) . '" 
							data-recurrence="' . esc_js( $level['interval_count'] ) . '" 
							data-currency="' . esc_js( apply_filters( 'issuem_leaky_paywall_paypal_currency', 'USD' ) ) . '" 
							data-amount="' . esc_js( $level['price'] ) . '" 
							data-name="' . esc_js( $level['label'] ) . '" 
							data-button="subscribe" 
							data-no_note="1" 
							data-no_shipping="1" 
							data-shipping="0" 
						></script>';
												
		} else {
						
			$results .= '<script src="' . ISSUEM_LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . esc_js( $paypal_account ) . '" 
							data-env="' . esc_js( $mode ) . '" 
							data-callback="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-ipn', '1', get_site_url() . '/' ) ) . '" 
							data-return="' . esc_js( add_query_arg( 'issuem-leaky-paywall-paypal-standard-return', '1', get_page_link( $settings['page_for_subscription'] ) ) ) . '"
							data-cancel_return="' . esc_js( get_page_link( $settings['page_for_subscription'] ) ) . '" 
							data-tax="0" 
							data-shipping="0" 
							data-currency="' . esc_js( apply_filters( 'issuem_leaky_paywall_paypal_currency', 'USD' ) ) . '" 
							data-amount="' . esc_js( $level['price'] ) . '" 
							data-quantity="1" 
							data-name="' . esc_js( $level['label'] ) . '" 
							data-button="buynow" 
							data-no_note="1" 
							data-no_shipping="1" 
							data-shipping="0" 
						></script>';
		
		}
		
		return '<div class="leaky-paywall-paypal-standard-button leaky-paywall-payment-button">' . $results . '</div>';
		
	}

}

if ( !function_exists( 'issuem_leaky_paywall_jquery_datepicker_format' ) ) { 

	/**
	 * Pass a PHP date format string to this function to return its jQuery datepicker equivalent
	 *
	 * @since 1.1.0
	 * @param string $date_format PHP Date Format
	 * @return string jQuery datePicker Format
	*/
	function issuem_leaky_paywall_jquery_datepicker_format( $date_format ) {
		
		//http://us2.php.net/manual/en/function.date.php
		//http://api.jqueryui.com/datepicker/#utility-formatDate
		$php_format = array(
			//day
			'/d/', //Day of the month, 2 digits with leading zeros
			'/D/', //A textual representation of a day, three letters
			'/j/', //Day of the month without leading zeros
			'/l/', //A full textual representation of the day of the week
			//'/N/', //ISO-8601 numeric representation of the day of the week (added in PHP 5.1.0)
			//'/S/', //English ordinal suffix for the day of the month, 2 characters
			//'/w/', //Numeric representation of the day of the week
			'/z/', //The day of the year (starting from 0)
			
			//week
			//'/W/', //ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0)
			
			//month
			'/F/', //A full textual representation of a month, such as January or March
			'/m/', //Numeric representation of a month, with leading zeros
			'/M/', //A short textual representation of a month, three letters
			'/n/', //numeric month no leading zeros
			//'t/', //Number of days in the given month
			
			//year
			//'/L/', //Whether it's a leap year
			//'/o/', //ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead. (added in PHP 5.1.0)
			'/Y/', //A full numeric representation of a year, 4 digits
			'/y/', //A two digit representation of a year
		);
		
		$datepicker_format = array(
			//day
			'dd', //day of month (two digit)
			'D',  //day name short
			'd',  //day of month (no leading zero)
			'DD', //day name long
			//'',   //N - Equivalent does not exist in datePicker
			//'',   //S - Equivalent does not exist in datePicker
			//'',   //w - Equivalent does not exist in datePicker
			'z' => 'o',  //The day of the year (starting from 0)
			
			//week
			//'',   //W - Equivalent does not exist in datePicker
			
			//month
			'MM', //month name long
			'mm', //month of year (two digit)
			'M',  //month name short
			'm',  //month of year (no leading zero)
			//'',   //t - Equivalent does not exist in datePicker
			
			//year
			//'',   //L - Equivalent does not exist in datePicker
			//'',   //o - Equivalent does not exist in datePicker
			'yy', //year (four digit)
			'y',  //month name long
		);
		
		return preg_replace( $php_format, $datepicker_format, preg_quote( $date_format ) );
	}
	
}
