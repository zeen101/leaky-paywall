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
				
			$query = 'SELECT * FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_subscribers WHERE email = %s AND mode = %s';
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
			return $wpdb->get_row( $wpdb->prepare( $query, $email, $mode ) );
		
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
	
		global $wpdb;
			
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			$settings = get_issuem_leaky_paywall_settings();
				
			$query = 'SELECT * FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_subscribers WHERE hash = %s AND mode = %s';
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
			return $wpdb->get_row( $wpdb->prepare( $query, $hash, $mode ) );
		
		}
		
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
	
		global $wpdb;
	
		return $wpdb->insert( $wpdb->prefix . 'issuem_leaky_paywall_logins',
								array(
										'email'		=> $email,
										'hash'		=> $hash,
										'created'	=> date( 'Y-m-d H:i:s' ),
								),
								array(
										'%s',
										'%s',
										'%s',
								) 
							);
		
	}
	
}

if ( !function_exists( 'verify_issuem_leaky_paywall_hash' ) ) {

	/**
	 * Verifies hash is valid for login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb var or false
	 */
	function verify_issuem_leaky_paywall_hash( $hash ) {
	
		global $wpdb;
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			$query = 'SELECT count(*) FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_logins WHERE hash = %s';
		
			return $wpdb->get_var( $wpdb->prepare( $query, $hash ) );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'get_issuem_leaky_paywall_email_from_hash' ) ) {

	/**
	 * Gets logging in user's email address from login link's hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return string email from $wpdb or false if invalid hash or expired link
	 */
	function get_issuem_leaky_paywall_email_from_hash( $hash ) {
		
		global $wpdb;
			
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			$query = 'SELECT email, created FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_logins WHERE hash = %s';
			
			$row = $wpdb->get_row( $wpdb->prepare( $query, $hash ) );
			
			if ( !is_null( $row ) ) {
					
				if ( time() - strtotime( $row->created ) > ( 60 * 60 ) ) {
					
					kill_issuem_leaky_paywall_login_hash( $hash );
					return false; //Hash is an hour old, therefore it's expired
					
				}
			
				return $row->email; 
				
			}
		
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
					$expires = $customer->expires;
					
					if ( 'stripe' === $customer->payment_gateway ) {
								
						$cu = Stripe_Customer::retrieve( $customer->subscriber_id );
											
						if ( !empty( $cu ) )
							if ( true === $cu->deleted )
								return false;
						
						if ( !empty( $customer->plan ) ) {
										
							if ( isset( $cu->subscription ) ) {
								
								if ( 'active' === $cu->subscription->status )
									return 'subscription';
						
							}
							
							return false;
							
						}
						
						$ch = Stripe_Charge::all( array( 'count' => 1, 'customer' => $customer->subscriber_id ) );
												
						if ( '0000-00-00 00:00:00' !== $expires ) {
							
							if ( strtotime( $expires ) > time() )
								if ( true === $ch->data[0]->paid && false === $ch->data[0]->refunded )
									return $expires;
							else
								return false;
									
						} else {
						
							return 'unlimited';
							
						}
					
					} else if ( 'paypal_standard' === $customer->payment_gateway ) {
						
						if ( '0000-00-00 00:00:00' === $expires )
							return 'unlimited';
						
						if ( !empty( $customer->plan ) && 'active' == $customer->payment_status )
							return 'subscription';
							
						switch( $customer->payment_status ) {
						
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
						
					} else if ( 'manual' === $customer->payment_gateway ) {
							
						switch( $customer->payment_status ) {
						
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
		
		wp_mail( "lew@lewayotte.com", 'IPN' , print_r( $_REQUEST, true ) );
		
		if ( !empty( $_REQUEST['txn_type'] ) ) {
			$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $_REQUEST['custom'] );
			
			if ( !empty( $subscriber ) ) {
				
				switch( $_REQUEST['txn_type'] ) {
				
					case 'web_accept':
						switch( strtolower( $_REQUEST['payment_status'] ) ) {
						
							case 'completed' :
							case 'reversed' :
								issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'payment_status', strtolower( $_REQUEST['payment_status'] ) );
								break;			
						}
						break;
						
					case 'subscr_signup':
						$period = $_REQUEST['period3'];
						issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'plan', strtoupper( $period ) );
						break;
						
					case 'subscr_payment':
						if ( $_REQUEST['txn_id'] === $subscriber->subscriber_id )
							issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'subscriber_id', $_REQUEST['subscr_id'] );
							
						if ( !empty( $subscriber->plan ) ) {// @todo
							$new_expiration = date( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $subscriber->plan ), strtotime( $_REQUEST['payment_date'] ) ) );
							switch( strtolower( $_REQUEST['payment_status'] ) ) {
								case 'completed' :
									issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'expires', $new_expiration );
									break;
							}
						}
						break;
						
					case 'subscr_cancel':
						issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'payment_status', 'canceled' );
						break;
						
					case 'subscr_eot':
						issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'payment_status', 'expired' );
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
	 * @return mixed $wpdb insert ID or false
	 */
	function issuem_leaky_paywall_new_subscriber( $hash, $email, $customer, $args ) {
		
		global $wpdb;
		
		if ( is_email( $email ) ) {
			
			$settings = get_issuem_leaky_paywall_settings();
			
			$expires = '0000-00-00 00:00:00';
			
			if ( isset( $customer->subscription ) ) { //only stripe
			
				$insert = array(
					'hash'			  => $hash,
					'email'			  => $email,
					'subscriber_id'   => $customer->id,
					'price'			  => number_format( $customer->subscription->plan->amount, '2', '.', '' ),
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
				
				$insert = array(
					'hash'			  => $hash,
					'email'			  => $email,
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
			
			do_action( 'issuem_leaky_paywall_new_subscriber', $email, $insert, $customer, $args );
			
			return $wpdb->insert( $wpdb->prefix . 'issuem_leaky_paywall_subscribers',
								$insert,
								array(
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
								) 
							);
		
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
			
			if ( isset( $customer->subscription ) ) { //only stripe
			
				$update = array(
					'hash'			  => $hash,
					'email'			  => $email,
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
				
				$update = array(
					'hash'			  => $hash,
					'email'			  => $email,
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
			
			do_action( 'issuem_leaky_paywall_update_subscriber', $email, $update, $customer, $args);
			
			return $wpdb->update( $wpdb->prefix . 'issuem_leaky_paywall_subscribers',
								$update,
								array( 'email' => $email ),
								array(
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
										'%s',
								),
								array( '%s' )
							);
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'issuem_leaky_paywall_update_subscriber_column' ) ) {

	/**
	 * Updates an existing subscriber to subscriber table
	 *
	 * @since 1.1.0
	 *
	 * @param string $email Address of user you want to update
	 * @param string $column The MySQL column you want ot update
	 * @param array $value New value
	 * @return mixed $wpdb update ID or false
	 */
	function issuem_leaky_paywall_update_subscriber_column( $email, $column, $value ) {
		
		global $wpdb;
		
		if ( is_email( $email ) ) {
			
			$update = array( $column => $value );
			
			do_action( 'issuem_leaky_paywall_update_subscriber_column', $email, $column, $value );
			
			return $wpdb->update( $wpdb->prefix . 'issuem_leaky_paywall_subscribers',
								$update,
								array( 'email' => $email ),
								array( '%s' ),
								array( '%s' )
							);
		
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
					
					if ( 'stripe' === $customer->payment_gateway ) {
					
						try {
							
							$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
							
							$expires = $customer->expires;
														
							$cu = Stripe_Customer::retrieve( $customer->subscriber_id );
								
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
					
					} else if ( 'paypal_standard' === $customer->payment_gateway ) {

						$paypal_url   = 'test' === $customer->mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
						$paypal_email = 'test' === $customer->mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
						$form .= '<p>' . sprintf( __( 'You must cancel your account through PayPal. Please click this unsubscribe button to complete the cancellation process.', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
						$form .= '<p><a href="' . $paypal_url . '?cmd=_subscr-find&alias=' . urlencode( $paypal_email ) . '"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_unsubscribe_LG.gif" border="0"></a></p>';
					}
				}
				
			}
			
			
		}
		
		return $form;
		
	}
	
}

if ( !function_exists( 'kill_issuem_leaky_paywall_login_hash' ) ) {
	
	/**
	 * Removed entry from logins table, for expired hashes
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 */
	function kill_issuem_leaky_paywall_login_hash( $hash ) {
		
		global $wpdb;
	
		if ( !empty( $_SESSION['issuem_lp_hash'] ) )
			unset( $_SESSION['issuem_lp_hash'] );
			
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_logins WHERE hash = %s', $hash ) );
		
		}
		
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
		
		while( verify_issuem_leaky_paywall_hash( $hash ) )
			$hash = issuem_leaky_paywall_hash( $hash ); // I did this on purpose...
			
		return $hash; // doesn't have to be too secure, just want a pretty random and very unique string
		
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
			
		if ( !empty( $_SESSION['issuem_lp_subscriber'] ) ) {

			setcookie( 'issuem_lp_subscriber', $_SESSION['issuem_lp_subscriber'], strtotime( apply_filters( 'issuem_leaky_paywall_logged_in_cookie_expiry', '+1 year' ) ), '/' );
			
		}
		
		if ( !empty( $_COOKIE['issuem_lp_subscriber'] ) ) {
			
			if ( empty( $_SESSION['issuem_lp_email'] ) ) 
				$_SESSION['issuem_lp_email'] = issuem_leaky_paywall_get_email_from_subscriber_hash( $_COOKIE['issuem_lp_subscriber'] );
			
			if ( false !== $expires = issuem_leaky_paywall_has_user_paid( $_SESSION['issuem_lp_email'] ) )
				return true;
			
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
	
		global $wpdb;
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			$settings = get_issuem_leaky_paywall_settings();
				
			$query = 'SELECT email FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_subscribers WHERE hash = %s AND mode = %s';
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
			return $wpdb->get_var( $wpdb->prepare( $query, $hash, $mode ) );
		
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
	
		global $wpdb;
		
		if ( !empty( $args ) ) {
			
			$join = apply_filters( 'issuem_leaky_paywall_subscriber_query_join', '' );
			
			$where = '';
			$where_array = array();
			
			if ( !empty( $args['search'] ) ) {
				$search = $args['search'] ;
				$wild = ( trim( $search, '*%' ) != $search );
				$search = str_replace( '*', '%', trim( $search ) );
					
				if ( $wild ) {
					$search_type = 'LIKE';
				} else {
					$search_type = '=';
				}
				
				$columns = apply_filters( 'issuem_leaky_paywall_search_susbcriber_columns', array( 'hash', 'email', 'subscriber_id', 'price', 'description', 'plan', 'created', 'expires', 'mode', 'payment_gateway', 'payment_status' ) ); 
				
				if ( !empty( $columns ) ) {
					
					foreach ( $columns as $column ) {
					
						$where_array[] .= sprintf( "lps.`%s` %s '%s'", $column, $search_type, $search );
						
					}
						
					$where_array = apply_filters( 'issuem_leaky_paywall_search_susbcriber_where_array', $where_array, $search_type, $search ); 
					
					if ( !empty( $where_array ) ) 
						$where = 'where ' . join( ' OR ', $where_array );
				
				}
			}
			
			switch ( strtoupper( $args['order'] ) ) {
				case 'DESC':
					$order = 'DESC';
					break;
			
				case 'ASC':
				default:
					$order = 'ASC';	
					break;
			}
			
			$offset = !empty( $args['offset'] ) ? absint( $args['offset'] ) : 0;
			$limit =  !empty( $args['number'] ) ? absint( $args['number'] ) : 20;
			
			$sql = "SELECT DISTINCT lps.* 
						 FROM " . $wpdb->prefix . "issuem_leaky_paywall_subscribers as lps
						 {$join}
						 {$where} 
						 order by {$args['orderby']}
						 {$order} 
						 limit {$offset}, {$limit}
						";
						
			return $wpdb->get_results( $sql );

		} else {
			
			return $wpdb->query( 'SELECT * FROM ' . $wpdb->prefix . 'issuem_leaky_paywall_subscribers' );
			
		}
		
		return false;
		
	}
	
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
