<?php
/**
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

if ( !function_exists( 'get_leaky_paywall_settings' ) ) {

	/**
	 * Helper function to get zeen101's Leaky Paywall settings for current site
	 *
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function get_leaky_paywall_settings() {
	
		global $leaky_paywall;
		
		return $leaky_paywall->get_settings();
		
	}
	
}
 
if ( !function_exists( 'update_leaky_paywall_settings' ) ) {

	/**
	 * Helper function to save zeen101's Leaky Paywall settings for current site
	 *
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function update_leaky_paywall_settings( $settings ) {
	
		global $leaky_paywall;
		
		return $leaky_paywall->update_settings( $settings );
		
	}
	
}

if (!function_exists('is_multisite_premium') ) {

	function is_multisite_premium() {
		if ( is_multisite() ) {
			return true;
		}
		//if ( is_multisite() && function_exists( 'is_leaky_paywall_multisite' ) ) {
		//	return is_leaky_paywall_multisite();
		//}
		return false;
	}
}

if ( !function_exists( 'get_leaky_paywall_subscribers_site_id_by_subscriber_id'  ) ) {
	
	function get_leaky_paywall_subscribers_site_id_by_subscriber_id( $subscriber_id, $mode = false ) {
		$site_id = '';
		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		}
		
		if ( is_multisite_premium() ) {
			global $wpdb;
			$results = $wpdb->get_col( 
				$wpdb->prepare( 
					"
					SELECT      $wpdb->usermeta.meta_key
					FROM        $wpdb->usermeta
					WHERE       $wpdb->usermeta.meta_key LIKE %s 
					            AND $wpdb->usermeta.meta_value = %s
					",
					'_issuem_leaky_paywall_' . $mode . '_subscriber_id%', 
					$subscriber_id
				) 
			); 
			if ( !empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( preg_match( '/_issuem_leaky_paywall_' . $mode . '_subscriber_id(_(.+))/', $result, $matches ) ) {
						return $matches[2]; //should be the site ID that matches for this subscriber_id
					}
				}
			}
		}
		
		return $site_id;
	}
	
}

if ( !function_exists( 'get_leaky_paywall_subscribers_site_id_by_subscriber_email'  ) ) {
	
	function get_leaky_paywall_subscribers_site_id_by_subscriber_email( $subscriber_email, $mode = false ) {
		$site_id = '';
		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		}
		
		if ( is_multisite_premium() ) {
			global $wpdb;
			$results = $wpdb->get_col( 
				$wpdb->prepare( 
					"
					SELECT      $wpdb->usermeta.meta_key
					FROM        $wpdb->usermeta
					WHERE       $wpdb->usermeta.meta_key LIKE %s 
					            AND $wpdb->usermeta.meta_value = %s
					",
					'_issuem_leaky_paywall_' . $mode . '_subscriber_email%', 
					$subscriber_email
				) 
			); 
			if ( !empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( preg_match( '/_issuem_leaky_paywall_' . $mode . '_subscriber_email(_(.+))/', $result, $matches ) ) {
						return $matches[2]; //should be the site ID that matches for this subscriber_id
					}
				}
			}
		}
		
		return $site_id;
	}
	
}

if ( !function_exists( 'get_leaky_paywall_subscriber_by_subscriber_id' ) ) {
	
	function get_leaky_paywall_subscriber_by_subscriber_id( $subscriber_id, $mode = false, $blog_id = false ) {
		$site = '';
		
		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		}
		
		if ( is_multisite_premium() ) {
			if ( empty( $blog_id ) ) {
				if ( $blog_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $subscriber_id ) ) {
					$site = '_' . $blog_id;
				}
			} else {
				$site = '_' . $blog_id;
			}
		}
		
		$args = array(
			'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
			'meta_value' => $subscriber_id,
		);
		$users = get_users( $args );
		
		if ( !empty( $users ) ) {
			foreach ( $users as $user ) {
				return $user;
			}
		}
		
		return false;
	}
	
}

if ( !function_exists( 'get_leaky_paywall_subscriber_by_subscriber_email' ) ) {
	
	function get_leaky_paywall_subscriber_by_subscriber_email( $subscriber_email, $mode = false, $blog_id = false ) {
		$site = '';
		
		if ( is_email( $subscriber_email ) ) {
			if ( empty( $mode ) ) {
				$settings = get_leaky_paywall_settings();
				$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			}
		
			if ( is_multisite_premium() ) {
				if ( empty( $blog_id ) ) {
					if ( $blog_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( $subscriber_email ) ) {
						$site = '_' . $blog_id;
					}
				} else {
					$site = '_' . $blog_id;
				}
			}
			
			$args = array(
				'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_subscriber_email' . $site,
				'meta_value' => $subscriber_email,
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

if ( !function_exists( 'add_leaky_paywall_login_hash' ) ) {

	/**
	 * Adds unique hash to login table for user's login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logging" in
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb insert ID or false
	 */
	function add_leaky_paywall_login_hash( $email, $hash ) {
	
		$expiration = apply_filters( 'leaky_paywall_login_link_expiration', 60 * 60 ); //1 hour
		set_transient( '_lpl_' . $hash, $email, $expiration );
			
	}
	
}

if ( !function_exists( 'is_leaky_paywall_login_hash_unique' ) ) {

	/**
	 * Verifies hash is valid for login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb var or false
	 */
	function is_leaky_paywall_login_hash_unique( $hash ) {
	
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
			
			return !( false !== get_transient( '_lpl_' . $hash ) );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'verify_leaky_paywall_login_hash' ) ) {

	/**
	 * Verifies hash is valid length and hasn't expired
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return mixed $wpdb var or false
	 */
	function verify_leaky_paywall_login_hash( $hash ) {
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			return (bool) get_transient( '_lpl_' . $hash );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'get_leaky_paywall_email_from_login_hash' ) ) {

	/**
	 * Gets logging in user's email address from login link's hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in
	 * @return string email from $wpdb or false if invalid hash or expired link
	 */
	function get_leaky_paywall_email_from_login_hash( $hash ) {
		
		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
				
			return get_transient( '_lpl_' . $hash );
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_has_user_paid' ) ) {

	/**
	 * Verified if user has paid through Stripe
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logged" in
	 * @return mixed Expiration date or subscriptions status or false if not paid
	 */
	function leaky_paywall_has_user_paid( $email=false, $blog_id=null ) {
		
		$settings = get_leaky_paywall_settings();
		$paid = false;
		$canceled = false;
		$expired = false;
		$sites = array( '' ); //Empty String for non-Multisite, so we cycle through "sites" one time with no $site set
		if ( is_multisite_premium() ) {
			if ( is_null( $blog_id ) ){
				global $blog_id;			
				if ( !is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			} else if ( is_int( $blog_id ) ) {
				$sites = array( '_' . $blog_id );
			} else if ( empty( $blog_id ) ) {
				$sites = array( '' );
			} else {
				$sites = array( $blog_id );
			}
		}
		
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		if ( empty( $email ) ) {
			$user = wp_get_current_user();
		} else {
			if ( is_email( $email ) ) {
				$user = get_user_by( 'email', $email );
			} else {
				return false;
			}
		}
		
		foreach ( $sites as $site ) {
		
			$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
			$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
			$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
			
			if ( !$paid ) {
				
				if ( 'stripe' === $payment_gateway ) {

					if ( ! class_exists( 'Stripe' ) ) {
						require_once LEAKY_PAYWALL_PATH . 'include/stripe/lib/Stripe.php';
					}
			
					if ( $mode == 'test' ) {
						$secret_key = isset( $settings['test_secret_key'] ) ? trim( $settings['test_secret_key'] ) : '';
					} else {
						$secret_key = isset( $settings['live_secret_key'] ) ? trim( $settings['live_secret_key'] ) : '';
					}
			
					Stripe::setApiKey( $secret_key );
					
					try {
						if ( empty( $subscriber_id ) ) {
							switch( $payment_status ) {
								case 'Active':
								case 'active':
								case 'refunded':
								case 'refund':
									if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
										return 'unlimited';
									}
									
									if ( strtotime( $expires ) < time() ) {
										$expired = $expires;
									} else {
										$paid = true;
									}
									break;
								case 'cancelled':
								case 'canceled':
									if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
										$expired = true;
									} else {
										$canceled = true;
									}
								case 'reversed':
								case 'buyer_complaint':
								case 'denied' :
								case 'expired' :
								case 'failed' :
								case 'voided' :
								case 'deactivated' :
									continue;
									break;
							}
						}
						
						$cu = Stripe_Customer::retrieve( $subscriber_id );
						
						if ( !empty( $cu ) ) {
							if ( !empty( $cu->deleted ) && true === $cu->deleted ) {
								$canceled = true;
							}
						}
						
						if ( !empty( $plan ) ) {
							if ( isset( $cu->subscriptions ) ) {
								$subscriptions = $cu->subscriptions->all( 'limit=1' );
								foreach( $subscriptions->data as $subscription ) {
									if ( 'active' === $subscription->status ) {
										return 'subscription';
									}
								}
							}
						}
						
						$ch = Stripe_Charge::all( array( 'count' => 1, 'customer' => $subscriber_id ) );
										
						if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
							return 'unlimited';
						} else {
							if ( strtotime( $expires ) < time() ) {
								if ( true === $ch->data[0]->paid && false === $ch->data[0]->refunded ) {
									$expired = $expires;
								}
							} else {
								$paid = true;
							}
						}
					} catch ( Exception $e ) {
						$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
					}
					
				} else if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
					
					if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
						return 'unlimited';
					}
					
					if ( !empty( $plan ) && 'active' == $payment_status ) {
						return 'subscription';
					}
						
					switch( $payment_status ) {
					
						case 'active':
						case 'refunded':
						case 'refund':
							if ( strtotime( $expires ) < time() ) {
								$expired = $expires;
							} else {
								$paid = true;
							}
							break;
						case 'cancelled':
						case 'canceled':
							$canceled = true;
						case 'reversed':
						case 'buyer_complaint':
						case 'denied' :
						case 'expired' :
						case 'failed' :
						case 'voided' :
						case 'deactivated' :
							continue;
							break;
						
					}
					
				} else if ( 'manual' === $payment_gateway ) {
						
					switch( $payment_status ) {
					
						case 'Active':
						case 'active':
						case 'refunded':
						case 'refund':
							if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
								return 'unlimited';
							}
								
							if ( strtotime( $expires ) < time() ) {
								$expired = $expires;
							} else {
								$paid = true;
							}
							break;
						case 'cancelled':
						case 'canceled':
							if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
								$expired = true;
							} else {
								$canceled = true;
							}
						case 'reversed':
						case 'buyer_complaint':
						case 'denied' :
						case 'expired' :
						case 'failed' :
						case 'voided' :
						case 'deactivated' :
							continue;
							break;
						
					}			
					
				} else if ( 'free_registration' === $payment_gateway ) {
					
					switch( $payment_status ) {
					
						case 'Active':
						case 'active':
						case 'refunded':
						case 'refund':
							if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
								return 'unlimited';
							}
								
							if ( strtotime( $expires ) < time() ) {
								$expired = $expires;
							} else {
								$paid = true;
							}
							break;
						case 'cancelled':
						case 'canceled':
							if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
								$expired = true;
							} else {
								$canceled = true;
							}
						case 'reversed':
						case 'buyer_complaint':
						case 'denied' :
						case 'expired' :
						case 'failed' :
						case 'voided' :
						case 'deactivated' :
							continue;
							break;
					
					}
					
				} else {
	
					$paid = apply_filters( 'leaky_paywall_has_user_paid', $paid, $payment_gateway, $payment_status, $subscriber_id, $plan, $expires, $user, $mode, $site );
					
				}
				
			}
			
		}

		if ( $canceled ) {
			return false;
		}

		if ( $expired ) {
			return false;
		}
	
		return $paid;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_set_expiration_date' ) ) {

	/**
	 * Set a user's expiration data
	 * @param  int $user_id the user id
	 * @param  array $data    information about the subscription
	 * 
	 */
	function leaky_paywall_set_expiration_date( $user_id, $data ) {

		if ( empty( $user_id ) ) {
			return;
		}

		if ( !empty( $data['length_unit'] ) && isset( $data['length'] ) && 1 <= $data['length'] ) {
			$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $data['length'] . ' ' . $data['length_unit'] ) ); //we're generous, give them the whole day!
		} else {
			$expires = '0000-00-00 00:00:00';
		}

		update_user_meta( $user_id, '_issuem_leaky_paywall_' . $data['mode'] . '_expires' . $data['site'], $expires );

	}

}

if ( !function_exists( 'leaky_paywall_new_subscriber' ) ) {

	/**
	 * Adds new subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param deprecated $hash
	 * @param string $email address of user "logged" in
	 * @param int $customer_id 
	 * @param array $meta_args Arguments passed from type of subscriber
	 * @param string $login optional login name to use instead of email address
	 * @return mixed $wpdb insert ID or false
	 */
	function leaky_paywall_new_subscriber( $hash='deprecated', $email, $customer_id, $meta_args, $login='' ) {

		if ( !is_email( $email ) ) {
			return false;
		}
			
		$settings = get_leaky_paywall_settings();
		
		if ( is_multisite_premium() && !is_main_site( $meta_args['site'] ) ) {
			$site = '_' . $meta_args['site'];
		} else {
			$site = '';
		}
		unset( $meta_args['site'] );
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		$expires = '0000-00-00 00:00:00';

		if ( $user = get_user_by( 'email', $email ) ) { 
			//the user already exists
			//grab the ID for later
			$user_id = $user->ID;
			$userdata = get_userdata( $user_id );
		} else {
	
			//the user doesn't already exist
			//create a new user with their email address as their username
			//grab the ID for later
			if ( empty( $login ) ) {
				$parts = explode( '@', $email );
				$login = $parts[0];
			}
			
			//Avoid collisions
			while ( $user = get_user_by( 'login', $login ) ) { 
				$login = $user->user_login . '_' . substr( uniqid(), 5 );
			} 
			
			$password = wp_generate_password();
            $userdata = array(
			    'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => $password,
				'user_registered'	=> date_i18n( 'Y-m-d H:i:s' ),
			);

            $userdata = apply_filters( 'leaky_paywall_userdata_before_user_create', $userdata );
			$user_id = wp_insert_user( $userdata );

		}
		
		if ( !empty( $user_id ) ) {
			
			if ( !empty( $meta_args['interval'] ) && isset( $meta_args['interval_count'] ) && 1 <= $meta_args['interval_count'] ) {
				$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $meta_args['interval_count'] . ' ' . $meta_args['interval'] ) ); //we're generous, give them the whole day!
			} else if ( !empty( $meta_args['expires'] ) ) {
				$expires = $meta_args['expires'];
			}			
			
			$meta = array(
				'level_id' 			=> $meta_args['level_id'],
				'subscriber_id' 	=> $customer_id,
				'price' 			=> $meta_args['price'],
				'description' 		=> $meta_args['description'],
				'plan' 				=> $meta_args['plan'],
				'created' 			=> date( 'Y-m-d H:i:s' ),
				'expires' 			=> $expires,
				'payment_gateway' 	=> $meta_args['payment_gateway'],
				'payment_status' 	=> $meta_args['payment_status'],
			);
			
			$meta = apply_filters( 'leaky_paywall_new_subscriber_meta', $meta, $email, $customer_id, $meta_args );

			// remove any extra underscores from site variable
			$site = str_replace( '__', '_', $site );
		
			foreach( $meta as $key => $value ) {

				update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
				
			}
				
			do_action( 'leaky_paywall_new_subscriber', $user_id, $email, $meta, $customer_id, $meta_args );

			// We only need to send a new user email to newly created users. If userdata is an object, then the user already exists in the system and they have already received a new user email. 
			if ( is_array( $userdata ) ) {
				leaky_paywall_email_subscription_status( $user_id, 'new', $userdata );
			}
			
			return $user_id;
			
		}

		return false;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_update_subscriber' ) ) {

	/**
	 * Updates an existing subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param deprecated $hash
	 * @param string $email address of user "logged" in
	 * @param int $customer_id Customer ID
	 * @param array $meta_args Arguments passed from type of subscriber
	 * @return mixed $wpdb insert ID or false
	 */
	function leaky_paywall_update_subscriber( $hash='deprecated', $email, $customer_id, $meta_args ) {
				
		if ( is_email( $email ) ) {
			$settings = get_leaky_paywall_settings();
			
			if ( is_multisite_premium() && !is_main_site( $meta_args['site'] ) ) {
				$site = '_' . $meta_args['site'];
			} else {
				$site = '';
			}
			unset( $meta_args['site'] );
			
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			$expires = '0000-00-00 00:00:00';
			
			if ( is_user_logged_in() && !is_admin() ) {
				//Update the existing user
				$user_id = get_current_user_id();
			} elseif ( $user = get_user_by( 'email', $email ) ) { 
				//the user already exists
				//grab the ID for later
				$user_id = $user->ID;
			} else {
				return false; //User does not exist, cannot update
			}
			
			if ( !empty( $meta_args['interval'] ) && isset( $meta_args['interval_count'] ) && 1 <= $meta_args['interval_count'] ) {
				$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $meta_args['interval_count'] . ' ' . $meta_args['interval'] ) ); //we're generous, give them the whole day!
			} else if ( !empty( $meta_args['expires'] ) ) {
				$expires = $meta_args['expires'];
			}
			
			$meta = array(
				'level_id' 			=> $meta_args['level_id'],
				'subscriber_id' 	=> $customer_id,
				'price' 			=> $meta_args['price'],
				'description' 		=> $meta_args['description'],
				'plan' 				=> $meta_args['plan'],
				'expires' 			=> $expires,
				'payment_gateway' 	=> $meta_args['payment_gateway'],
				'payment_status' 	=> $meta_args['payment_status'],
			);
			
			$meta = apply_filters( 'leaky_paywall_update_subscriber_meta', $meta, $email, $customer_id, $meta_args );
		
			foreach( $meta as $key => $value ) {
				update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
			}
			
			$user_id = wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
				
			do_action( 'leaky_paywall_update_subscriber', $user_id, $email, $meta, $customer_id, $meta_args );
		
			return $user_id;
		
		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_translate_payment_gateway_slug_to_name' ) ) {
	
	function leaky_paywall_translate_payment_gateway_slug_to_name( $slug ) {
	
		$return = 'Unknown';
		
		switch( $slug ) {
		
			case 'stripe':
				$return = 'Stripe';
				break;
				
			case 'paypal_standard':
			case 'paypal-standard':
				$return = 'PayPal';
				break;
				
			case 'free_registration':
				$return = 'Free Registration';
				break;
				
			case 'manual':
				$return = __( 'Manually Added', 'issue-leaky-paywall' );
				break;
			
		}
		
		return apply_filters( 'leaky_paywall_translate_payment_gateway_slug_to_name', $return, $slug );
		
	}
	
}

if ( !function_exists( 'leaky_paywall_cancellation_confirmation' ) ) {

	/**
	 * Cancels a subscriber from Stripe subscription plan
	 *
	 * @since 1.0.0
	 *
	 * @return string Cancellation form output
	 */
	function leaky_paywall_cancellation_confirmation() {
		
		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		$form = '';

		if ( is_user_logged_in() ) {
			
			global $blog_id;
			if ( is_multisite_premium() && !is_main_site( $blog_id ) ) {
				$site = '_' . $blog_id;
			} else {
				$site = '';
			}
			
			if ( !empty( $_REQUEST['payment_gateway'] ) ) {
				$payment_gateway = $_REQUEST['payment_gateway'];
			} else {
				return '<p>' . __( 'No payment gateway defined.', 'issuem-leaky-paywall' ) . '</p>';
			}
			
			if ( !empty( $_REQUEST['subscriber_id'] ) ) {
				$subscriber_id = $_REQUEST['subscriber_id'];
			} else {
				return '<p>' . __( 'No subscriber ID defined.', 'issuem-leaky-paywall' ) . '</p>';
			}
			
			if ( isset( $_REQUEST['cancel'] ) && empty( $_REQUEST['cancel'] ) ) {
	
				$form = '<h3>' . __( 'Cancel Subscription', 'issuem-leaky-paywall' ) . '</h3>';
	
				$form .= '<p>' . __( 'Cancellations take effect at the end of your billing cycle, and we can’t give partial refunds for unused time in the billing cycle. If you still wish to cancel now, you may proceed, or you can come back later.', 'issuem-leaky-paywall' ) . '</p>';
				$form .= '<p>' . sprintf( __( ' Thank you for the time you’ve spent subscribed to %s. We hope you’ll return someday. ', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
				$form .= '<a href="' . esc_url( add_query_arg( array( 'cancel' => 'confirm' ) ) ) . '">' . __( 'Yes, cancel my subscription!', 'issuem-leaky-paywall' ) . '</a> | <a href="' . get_home_url() . '">' . __( 'No, get me outta here!', 'issuem-leak-paywall' ) . '</a>';
				
			} else if ( !empty( $_REQUEST['cancel'] ) && 'confirm' === $_REQUEST['cancel'] ) {
				
				$user = wp_get_current_user();
									
				if ( 'stripe' === $payment_gateway ) {
				
					try {
						
						$secret_key = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
													
						$cu = Stripe_Customer::retrieve( $subscriber_id );
							
						if ( !empty( $cu ) )
							if ( true === $cu->deleted )
								throw new Exception( __( 'Unable to find valid Stripe customer ID to unsubscribe. Please contact support', 'issuem-leaky-paywall' ) );
								
						$subscriptions = $cu->subscriptions->all( 'limit=1' );

						foreach( $subscriptions->data as $susbcription ) {
							$sub = $cu->subscriptions->retrieve( $susbcription->id );
							$results = $sub->cancel();
						}
											
						if ( !empty( $results->status ) && 'canceled' === $results->status ) {
							
							$form .= '<p>' . sprintf( __( 'Your subscription has been successfully canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, 'Canceled' );

						} else {
						
							$form .= '<p>' . sprintf( __( 'ERROR: An error occured when trying to unsubscribe you from your account, please try again. If you continue to have trouble, please contact us. Thank you.', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
							
						}
						
						$form .= '<a href="' . get_home_url() . '">' . sprintf( __( 'Return to %s...', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
						
					} catch ( Exception $e ) {
					
						$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
						
					}
				
				} else if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {

					$paypal_url   = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
					$paypal_email = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
					$form .= '<p>' . sprintf( __( 'You must cancel your account through PayPal. Please click this unsubscribe button to complete the cancellation process.', 'issuem-leaky-paywall' ), $settings['site_name'] ) . '</p>';
					$form .= '<p><a href="' . $paypal_url . '?cmd=_subscr-find&alias=' . urlencode( $paypal_email ) . '"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_unsubscribe_LG.gif" border="0"></a></p>';
					
				} else {
					
					$form .= '<p>' . __( 'Unable to determine your payment method. Please contact support for help canceling your account.', 'issuem-leaky-paywall' ) . '</p>';
					
				}
				
			}
			
		} else {
			
			$form .= '<p>' . __( 'You must be logged in to cancel your account.', 'issuem-leaky-paywall' ) . '</p>';
			
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
		
		$settings = get_leaky_paywall_settings();
		
		$login_url = get_page_link( $settings['page_for_login'] );
		$login_hash = create_leaky_paywall_login_hash( $email );
		
		add_leaky_paywall_login_hash( $email, $login_hash );
		
		$message  = 'Log into ' . $settings['site_name']  . ' by opening this link:' . "\r\n";
		$message .= esc_url( add_query_arg( 'r', $login_hash, $login_url ) ) . "\r\n";
		$message .= 'This link will expire after an hour and can only be used once. To log into multiple browsers, send a login request from each one.' . "\r\n";
		$message .= " - " . $settings['site_name'] . "'s passwordless login system" . "\r\n";
		
		$message = apply_filters( 'leaky_paywall_login_email_message', $message );
		
		$headers = 'From: ' . $settings['from_name'] .' <' . $settings['from_email'] . '>' . "\r\n";
		
		return wp_mail( $email, __( 'Log into ' . get_bloginfo( 'name' ), 'issuem-leaky-paywall' ), $message, $headers );
		
	}
	
}

if ( !function_exists( 'create_leaky_paywall_login_hash' ) ) {

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
	function create_leaky_paywall_login_hash( $str ) {
	
		if ( defined( SECURE_AUTH_SALT ) )
			$salt[] = SECURE_AUTH_SALT;
			
		if ( defined( AUTH_SALT ) )
			$salt[] = AUTH_SALT;
		
		$salt[] = get_bloginfo( 'name' );
		$salt[] = time();
		
		$hash = md5( md5( implode( $salt ) ) . md5( $str ) );
		
		while( !is_leaky_paywall_login_hash_unique( $hash ) )
			$hash = create_leaky_paywall_login_hash( $hash ); // I did this on purpose...
			
		return $hash; // doesn't have to be too secure, just want a pretty random and very unique string
		
	}
	
}

if ( !function_exists( 'leaky_paywall_attempt_login' ) ) {

	function leaky_paywall_attempt_login( $login_hash ) {

		if ( false !== $email = get_leaky_paywall_email_from_login_hash( $login_hash ) ) {

			if ( $user = get_user_by( 'email', $email ) ) {

				delete_transient( '_lpl_' . $login_hash ); //one time use
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID, true );

			}

		}

	}

}

if ( !function_exists( 'leaky_paywall_subscriber_restrictions' ) ) {
	
	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 2.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function leaky_paywall_subscriber_restrictions() {
	
		$settings = get_leaky_paywall_settings();
		$restrictions = $settings['restrictions']['post_types']; //defaults
		if ( is_multisite_premium() ) {
			$restriction_levels = leaky_paywall_subscriber_current_level_ids();
			if ( !empty( $restriction_levels ) ) {

				$restrictions = array();
				$merged_restrictions = array();
				foreach( $restriction_levels as $restriction_level ) {
					if ( !empty( $settings['levels'][$restriction_level]['post_types'] ) ) {
						$restrictions = array_merge( $restrictions, $settings['levels'][$restriction_level]['post_types'] );
					}
				}
				$merged_restrictions = array();
				foreach( $restrictions as $key => $restriction ) {
					if ( empty( $merged_restrictions ) ) {
						$merged_restrictions[$key] = $restriction;
						continue;
					} else {
						$post_type_found = false;
						foreach( $merged_restrictions as $tmp_key => $tmp_restriction ) {
							if ( $restriction['post_type'] === $tmp_restriction['post_type'] ) {
								$post_type_found = true;
								$post_type_found_key = $tmp_key;
								break;
							}
						}
						if ( !$post_type_found ) {
							$merged_restrictions[$key] = $restriction;
						} else {
							if ( -1 == $restriction['allowed_value'] ) { //-1 is unlimited, just use it
								$merged_restrictions[$post_type_found_key] = $restriction;
							} else if ( $merged_restrictions[$post_type_found_key]['allowed_value'] < $restriction['allowed_value'] ) {
								$merged_restrictions[$post_type_found_key] = $restriction;
							}
						}
					}
				}
				$restrictions = $merged_restrictions;
				
			}
		} else {
			if ( false !== $restriction_level = leaky_paywall_subscriber_current_level_id() ) {
					
				if ( !empty( $settings['levels'][$restriction_level]['post_types'] ) ) {
					$restrictions = $settings['levels'][$restriction_level]['post_types'];
				}
				
			}
		}
		return apply_filters( 'leaky_paywall_subscriber_restrictions', $restrictions );
		
	}
}

if ( !function_exists( 'leaky_paywall_subscriber_current_level_id' ) ) {
	
	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 2.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function leaky_paywall_subscriber_current_level_id() {
	
		if ( leaky_paywall_has_user_paid() ) {
			$settings = get_leaky_paywall_settings();
				
			$sites = array( '' );
			if ( is_multisite_premium() ) {
				global $blog_id;
				if ( !is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}
			
			$user = wp_get_current_user();
			
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

			foreach ( $sites as $site ) {
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
				$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
				if ( is_numeric( $level_id ) ) {
					return $level_id;
				}
			}
			
		}
		
		return false;
		
	}
}

if ( !function_exists( 'leaky_paywall_subscriber_current_level_ids' ) ) {
	
	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 3.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function leaky_paywall_subscriber_current_level_ids() {
		$level_ids = array();
		if ( leaky_paywall_has_user_paid() ) {
			$settings = get_leaky_paywall_settings();
				
			$sites = array( '' );
			if ( is_multisite_premium() ) {
				global $blog_id;
				if ( !is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}
			
			$user = wp_get_current_user();
			
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			foreach ( $sites as $site ) {
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
				$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
				$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
				if ( 'active' === $status && is_numeric( $level_id ) ) {
					$level_ids[] = $level_id;
				}
			}
		}

		return $level_ids;		
	}
}

if ( !function_exists( 'leaky_paywall_subscriber_query' ) ){

	/**
	 * Gets leaky paywall subscribers
	 *
	 * @since 1.1.0
	 *
	 * @param array $args Leaky Paywall Subscribers
	 * @return mixed $wpdb var or false if invalid hash
	 */
	function leaky_paywall_subscriber_query( $args, $blog_id = false ) {
	
		if ( !empty( $args ) ) {
			$site = '';
			$settings = get_leaky_paywall_settings();
			if ( !empty( $blog_id ) && is_multisite_premium() ) {
				$site = '_' . $blog_id;
			}

			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
			
			if ( !empty( $args['search'] ) ) {
			
				$search = trim( $args['search'] );
			
				if ( is_email( $search ) ) {
					
					$args['meta_query'] = array(
						array(
							'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
							'compare' => 'EXISTS',
						),
					);
					$args['search'] = $search;
					$args['search_columns'] = array( 'user_login', 'user_email' );
					
				} else {
						
					$args['meta_query'] = array(
						'relation' => 'AND',
						array(
							'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
							'compare' => 'EXISTS',
						),
						array(
							'value'   => $search,
							'compare' => 'LIKE',
						),
					);
					unset( $args['search'] );
					
				}
			
			} else {
				$args['meta_query'] = array(
					array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
						'compare' => 'EXISTS',
					),
				);
			}
			
			if ( !empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] )
				unset( $args['meta_query'] );
			
			$users = get_users( $args );
			return $users;

		}
		
		return false;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_server_pdf_download' ) ) {

	function leaky_paywall_server_pdf_download( $download_id ) {
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

if ( !function_exists( 'build_leaky_paywall_subscription_levels_row' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_leaky_paywall_subscription_levels_row( $level=array(), $row_key='' ) {
		
		global $leaky_paywall;
		
			$settings = get_leaky_paywall_settings();
		$return = '';
	
		$default = array(
			'label' 					=> '',
			'price' 					=> '',
			'subscription_length_type' 	=> 'limited',
			'interval_count' 			=> 1,
			'interval' 					=> 'month',
			'recurring' 				=> 'off',
			'plan_id' 					=> '',
			'post_types' => array(
				array(
					'post_type' 		=> ACTIVE_ISSUEM ? 'article' : 'post',
					'allowed' 			=> 'unlimited',
					'allowed_value' 	=> -1,
				)
			),
			'deleted' 					=> 0,
			'site' 						=> 'all',
		);
		$level = wp_parse_args( $level, $default );
    	
    	if ( empty( $level['recurring'] ) )
    		$level['recurring'] = 'off';
    		
    	if ( empty( $level['deleted'] ) ) {
			$return  = '<table class="issuem-leaky-paywall-subscription-level-row-table leaky-paywall-table">';
			$return .= '<tr>';
			$return .= '<th>';
			$return .= '<label for="level-name-' . $row_key . '">' . __( 'Subscription Name', 'issuem-leaky-paywall' ) . '</label>';
			$return .= '<p class="description">' . sprintf( __( 'Subscription ID: %s', 'issuem-leaky-paywall' ), $row_key ) . '</p>';
			$return .= '</th>';
			$return .= '<td>';
			$return .= '<input id="level-name-' . $row_key . '" type="text" name="levels[' . $row_key . '][label]" value="' . htmlspecialchars( stripcslashes( $level['label'] ) ) . '" />';
			$return .= '<span class="delete-x delete-subscription-level">&times;</span>';
			$return .= '<input type="hidden" class="deleted-subscription" name="levels[' . $row_key . '][deleted]" value="' . $level['deleted'] . '">';
			$return .= '</td>';
			$return .= '</tr>';
			    
			$return .= '<tr>';		
			$return .= '<th><label for="level-recurring-' . $row_key . '">' . __( 'Recurring?', 'issuem-leaky-paywall' ) . '</label></th>';
			$return .= '<td>';
			$return .= '<input id="level-recurring-' . $row_key . '" class="stripe-recurring" type="checkbox" name="levels[' . $row_key . '][recurring]" value="on" ' . checked( 'on', $level['recurring'], false ) . ' />';
			$return .= '</td>';
			$return .= '</tr>';	
							
			$return .= '<tr>';	
			$return .= '<th><label for="level-price-' . $row_key . '">' . __( 'Subscription Price', 'issuem-leaky-paywall' ) . '</label></th>';
			$return .= '<td>';
			$return .= '<input id="level-price-' . $row_key . '" type="text" class="small-text" name="levels[' . $row_key . '][price]" value="' . stripcslashes( $level['price'] ) . '" />';	
			$return .= '<p class="description">' . __( '0 for Free Subscriptions', 'issuem-leaky-paywall' ) . '</p>';
			$return .= '</td>';
			$return .= '</tr>';	
					
			$return .= '<tr>';	
			$return .= '<th><label for="level-interval-count-' . $row_key . '">' . __( 'Subscription Length', 'issuem-leaky-paywall' ) . '</label></th>';
			$return .= '<td>';
	
			$return .= '<select class="subscription_length_type" name="levels[' . $row_key . '][subscription_length_type]">';						
				$return .= '<option value="unlimited" ' . selected( 'unlimited', $level['subscription_length_type'], false ) . '>' . __( 'Forever', 'issuem-leaky-paywall' ) . '</option>';
				$return .= '<option value="limited" ' . selected( 'limited', $level['subscription_length_type'], false ) . '>' . __( 'Limited for...', 'issuem-leaky-paywall' ) . '</option>';
			$return .= '</select>';
				
			if ( 'unlimited' == $level['subscription_length_type'] ) {
				$subscription_length_input_style = 'display: none;';
			} else {
				$subscription_length_input_style = '';
			}
	
			$return .= '<div class="interval_div" style="' . $subscription_length_input_style . '">';
			$return .= '<input id="level-interval-count-' . $row_key . '" type="text" class="interval_count small-text" name="levels[' . $row_key . '][interval_count]" value="' . stripcslashes( $level['interval_count'] ) . '" />';	
			$return .= '<select id="interval" name="levels[' . $row_key . '][interval]">';
	        $return .= '  <option value="day" ' . selected( 'day' === $level['interval'], true, false ) . '>' . __( 'Day(s)', 'issuem-leaky-paywall' ) . '</option>';
	        $return .= '  <option value="week" ' . selected( 'week' === $level['interval'], true, false ) . '>' . __( 'Week(s)', 'issuem-leaky-paywall' ) . '</option>';
	        $return .= '  <option value="month" ' . selected( 'month' === $level['interval'], true, false ) . '>' . __( 'Month(s)', 'issuem-leaky-paywall' ) . '</option>';
	        $return .= '  <option value="year" ' . selected( 'year' === $level['interval'], true, false ) . '>' . __( 'Year(s)', 'issuem-leaky-paywall' ) . '</option>';
	        $return .= '</select>';
	        $return .= '</div>';
	        $return .= '</td>';
			$return .= '</tr>';
	        		
			$return .= '<tr>';
			$return .= '<th>' . __( 'Access Options', 'issuem-leaky-paywall' ) . '</th>';
			$return .= '<td id="issuem-leaky-paywall-subsciption-row-' . $row_key . '-post-types">';
			$last_key = -1;
			if ( !empty( $level['post_types'] ) ) {
				foreach( $level['post_types'] as $select_post_key => $select_post_type ) {
					$return .= build_leaky_paywall_subscription_row_post_type( $select_post_type, $select_post_key, $row_key );
					$last_key = $select_post_key;
				}
			}
			$return .= '</td>';
			$return .= '</tr>';
			
			$return .= '<tr>';
			$return .= '<th>&nbsp;</th>';
			$return .= '<td>';
	        $return .= '<script type="text/javascript" charset="utf-8">';
	        $return .= '    var leaky_paywall_subscription_row_' . $row_key . '_last_post_type_key = ' . $last_key;
	        $return .= '</script>';
			$return .= '<p><input data-row-key="' . $row_key . '" class="button-secondary" id="add-subscription-row-post-type" class="add-new-issuem-leaky-paywall-row-post-type" type="submit" name="add_leaky_paywall_subscription_row_post_type" value="' . __( 'Add New Post Type', 'issuem-leaky-paywall' ) . '" /></p>';
			if ( $leaky_paywall->is_site_wide_enabled() ) {
				$return .= '<p class="description">' . __( 'Post Types that are not native the to the site currently being viewed are marked with an asterisk.', 'issuem-leaky-paywall' ) . '</p>';
			}
			$return .= '</td>';
			$return .= '</tr>';
			
			if ( is_multisite_premium() ) {
		        		
				$return .= '<tr>';
				$return .= '<th>' . __( 'Site', 'issuem-leaky-paywall' ) . '</th>';
				$return .= '<td id="issuem-leaky-paywall-subsciption-row-' . $row_key . '-site">';
				$return .= '<select id="site" name="levels[' . $row_key . '][site]">';
		        $return .= '  <option value="all" ' . selected( 'all' === $level['site'], true, false ) . '>' . __( 'All Sites', 'issuem-leaky-paywall' ) . '</option>';
				$sites = wp_get_sites();
				foreach( $sites as $site ) {
					$site_details = get_blog_details( $site['blog_id'] );
					$return .= '  <option value="' . $site['blog_id'] . '" ' . selected( $site['blog_id'] === $level['site'], true, false ) . '>' . $site_details->blogname . '</option>';
				}
		        $return .= '</select>';
				$return .= '</td>';
				$return .= '</tr>';
			
			}
			
			$return .= apply_filters( 'build_leaky_paywall_subscription_levels_row_addon_filter', '', $level, $row_key );
			
			$return .= '</table>';
		}
		
		return $return;
		
	}
	
}
 
if ( !function_exists( 'build_leaky_paywall_subscription_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_subscription_row_ajax() {
		if ( isset( $_REQUEST['row-key'] ) )
			die( build_leaky_paywall_subscription_levels_row( array(), $_REQUEST['row-key'] ) );
		else
			die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row', 'build_leaky_paywall_subscription_row_ajax' );
	
}
 
if ( !function_exists( 'build_leaky_paywall_subscription_row_post_type' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_leaky_paywall_subscription_row_post_type( $select_post_type=array(), $select_post_key='', $row_key='' ) {

		$default_select_post_type = array(
			'post_type' 	=> ACTIVE_ISSUEM ? 'article' : 'post',
			'allowed' 		=> 'unlimited',
			'allowed_value' => -1,
			'site' 			=> 0,
		);
		$select_post_type = wp_parse_args( $select_post_type, $default_select_post_type );
		

		$return  = '<div class="issuem-leaky-paywall-row-post-type">';
		
		$return .= '<select class="allowed_type" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed]">';						
			$return .= '<option value="unlimited" ' . selected( 'unlimited', $select_post_type['allowed'], false ) . '>' . __( 'Unlimited', 'issuem-leaky-paywall' ) . '</option>';
			$return .= '<option value="limited" ' . selected( 'limited', $select_post_type['allowed'], false ) . '>' . __( 'Limit to...', 'issuem-leaky-paywall' ) . '</option>';
		$return .= '</select>';
			
		if ( 'unlimited' == $select_post_type['allowed'] ) {
			$allowed_value_input_style = 'display: none;';
		} else {
			$allowed_value_input_style = '';
		}
			    
		$return .= '<div class="allowed_value_div" style="' . $allowed_value_input_style . '">';
		$return .= '<input type="text" class="allowed_value small-text" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed_value]" value="' . $select_post_type['allowed_value'] . '" placeholder="' . __( '#', 'issuem-leaky-paywall' ) . '" />';
		$return .= '</div>';
		
		$return .= '<select class="select_level_post_type" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][post_type]">';
		$post_types = get_post_types( array(), 'objects' );
		$post_types_names = get_post_types( array(), 'names' );
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		if ( in_array( $select_post_type['post_type'], $post_types_names ) ) {
			foreach ( $post_types as $post_type ) {
				if ( in_array( $post_type->name, $hidden_post_types ) ) 
					continue;
				$return .= '<option value="' . $post_type->name . '" ' . selected( $post_type->name, $select_post_type['post_type'], false ) . '>' . $post_type->labels->name . '</option>';
	        }
        } else {
			$return .= '<option value="' . $select_post_type['post_type'] . '">' . $select_post_type['post_type'] . ' &#42;</option>';
        }
		$return .= '</select>';
				
		$return .= '<span class="delete-x delete-post-type-row">&times;</span>';
		
		$return .= '</div>';
		
		return $return;
		
	}
	
}

if ( !function_exists( 'build_leaky_paywall_subscription_row_post_type_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_subscription_row_post_type_ajax() {
	
		if ( isset( $_REQUEST['select-post-key'] ) && isset( $_REQUEST['row-key'] ) ) {
			$settings = get_leaky_paywall_settings();
			
			if ( is_multisite_premium() && preg_match( '#^' . network_admin_url() . '#i', $_SERVER['HTTP_REFERER'] ) ) {
				if ( !defined( 'WP_NETWORK_ADMIN' ) ) {
					define( 'WP_NETWORK_ADMIN', true );
				}
			}
			
			die( build_leaky_paywall_subscription_row_post_type( array(), $_REQUEST['select-post-key'], $_REQUEST['row-key'] ) );
		}
		die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row-post-type', 'build_leaky_paywall_subscription_row_post_type_ajax' );
	
}

if ( !function_exists( 'build_leaky_paywall_default_restriction_row' ) ) {

	/**
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the issuem options.
	 */
	function build_leaky_paywall_default_restriction_row( $restriction=array(), $row_key='' ) {

		if ( empty( $restriction ) ) {
			$restriction = array(
				'post_type' 	=> '',
				'allowed_value' => '0',
			);
		}
    	
		$return  = '<div class="issuem-leaky-paywall-restriction-row">';
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
		$return .= '</div>';
		
		return $return;
		
	}
	
}
 
if ( !function_exists( 'build_leaky_paywall_default_restriction_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_default_restriction_row_ajax() {
	
		if ( isset( $_REQUEST['row-key'] ) )
			die( build_leaky_paywall_default_restriction_row( array(), $_REQUEST['row-key'] ) );
		else
			die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-restriction-row', 'build_leaky_paywall_default_restriction_row_ajax' );
	
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

if ( !function_exists( 'get_leaky_paywall_subscription_level' ) ) {
	
	function get_leaky_paywall_subscription_level( $level_id ) {
		
		$settings = get_leaky_paywall_settings();
		
		$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
		if ( isset( $settings['levels'][$level_id] ) ) {
			return apply_filters( 'get_leaky_paywall_subscription_level', $settings['levels'][$level_id], $level_id );
		}
		
		return false;
	}
}

if ( !function_exists( 'leaky_paywall_subscription_options' ) ) {
	
	function leaky_paywall_subscription_options() {
		
		global $blog_id;
		
		$settings = get_leaky_paywall_settings();
		$current_level_ids = leaky_paywall_subscriber_current_level_ids();
		
		$results = apply_filters( 'leaky_paywall_subscription_options', '' );
		//If someone wants to completely override this, they can with the above filter
		if ( empty( $results ) ) {
					
			$has_allowed_value = false;
			$results .= '<h2>' . __( 'Subscription Options', 'issuem-leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_subscription_options_header', '' );
			
			if ( !empty( $settings['levels'] ) ) {
			
				$results .= apply_filters( 'leaky_paywall_before_subscription_options', '' );
				
				$results .= '<div class="leaky_paywall_subscription_options">';
				foreach( $settings['levels'] as $level_id => $level ) {
					
					if ( !empty( $level['deleted'] ) )
						continue;

					
					
					if ( is_multisite_premium() && !empty( $level['site'] ) && 'all' != $level['site'] && $blog_id != $level['site'] )
						continue;
						
					$level = apply_filters( 'leaky_paywall_subscription_options_level', $level, $level_id );
										
					$payment_options = '';
					$allowed_content = '';
					
					if ( in_array( $level_id, $current_level_ids ) ) {
						$current_level = 'current-level';
					} else {
						$current_level = '';
					}
					
					$results .= '<div id="option-' . $level_id . '" class="leaky_paywall_subscription_option ' . $current_level. '">';
					$results .= '<h3>' . stripslashes( $level['label'] ) . '</h3>';
					
					$results .= '<div class="leaky_paywall_subscription_allowed_content">';
					foreach( $level['post_types'] as $post_type ) {
					
						/* @todo: We may need to change the site ID during this process, some sites may have different post types enabled */
						$post_type_obj = get_post_type_object( $post_type['post_type'] );
						if ( !empty( $post_type_obj ) ) {
							if ( 0 <= $post_type['allowed_value'] ) {
								$has_allowed_value = true;
								$allowed_content .= '<p>'  . sprintf( __( 'Access %s %s*', 'issuem-leaky-paywall' ), $post_type['allowed_value'], $post_type_obj->labels->name ) .  '</p>';
							} else {
								$allowed_content .= '<p>' . sprintf( __( 'Unlimited %s', 'issuem-leaky-paywall' ), $post_type_obj->labels->name ) . '</p>';
							}
						}
							
					}
					$results .= apply_filters( 'leaky_paywall_subscription_options_allowed_content', $allowed_content, $level_id, $level );
					$results .= '</div>';
					
					$currency = $settings['leaky_paywall_currency'];
					$currencies = leaky_paywall_supported_currencies();
					
					$results .= '<div class="leaky_paywall_subscription_price">';
					$results .= '<p>';
					if ( !empty( $level['price'] ) ) {
						if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] && apply_filters( 'leaky_paywall_subscription_options_price_recurring_on', true, $current_level ) ) {
							$results .= '<strong>' . sprintf( __( '%s%s %s (recurring)', 'issuem-leaky-paywall' ), $currencies[$currency]['symbol'], number_format( $level['price'], 2 ), leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
							$results .= apply_filters( 'leaky_paywall_before_subscription_options_recurring_price', '' );
						} else {
							$results .= '<strong>' . sprintf( __( '%s%s %s', 'issuem-leaky-paywall' ), $currencies[$currency]['symbol'], number_format( $level['price'], 2 ), leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
							$results .= apply_filters( 'leaky_paywall_before_subscription_options_non_recurring_price', '' );
						}
						
						if ( !empty( $level['trial_period'] ) ) {
							$results .= '<span class="leaky-paywall-trial-period">' . sprintf( __( 'Free for the first %s day(s)', 'issuem-leaky-paywall' ), $level['trial_period'] ) . '</span>';
						}
					} else {
						$results .= '<strong>' . __( 'Free', 'issuem-leaky-paywall' ) . '</strong>';
					}
					
					$results .= '</p>';
					$results .= '</div>';
					
					//Don't show payment options if the users is currently subscribed to this level
					if ( !in_array( $level_id, $current_level_ids ) ) {
						$results .= '<div class="leaky_paywall_subscription_payment_options">';
						
						if ( !empty( $level['price'] ) ) {
							if ( empty( $current_level_ids ) ) {
								//New Account
								// if ( in_array( 'stripe', $settings['payment_gateway'] ) ) {
								// 	$payment_options .= leaky_paywall_pay_with_stripe( $level, $level_id );
								// }
								
								// if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) || in_array( 'paypal-standard', $settings['payment_gateway'] ) ) {
								// 	if ( !empty( $payment_options ) ) {
								// 		$payment_options .= '<div class="paypal-description">' . __( 'or pay with PayPal', 'issuem-leaky-paywall' ) . '</div>';
								// 	}
								// 	$payment_options .= leaky_paywall_pay_with_paypal_standard( $level, $level_id );
								// }
								$results .= apply_filters( 'leaky_paywall_subscription_options_payment_options', $payment_options, $level, $level_id );
							} else {
								//Upgrade
								$user = wp_get_current_user();
								$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
								
								if ( !empty( $user->ID ) ) {
									
									if ( is_multisite_premium() && !empty( $level['site'] ) && !is_main_site( $level['site'] ) ) {
										$site = '_' . $level['site'];
									} else {
										$site = '';
									}
									$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );		
									
									switch( $payment_gateway ) {
										
										case 'stripe':
											$payment_options .= leaky_paywall_pay_with_stripe( $level, $level_id );
											break;
											
										case 'paypal_standard':
										case 'paypal-standard':
											$payment_options .= leaky_paywall_pay_with_paypal_standard( $level, $level_id );
											break;
											
										default:
											if ( in_array( 'stripe', $settings['payment_gateway'] ) ) {
												$payment_options .= leaky_paywall_pay_with_stripe( $level, $level_id );
											}
											
											if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) || in_array( 'paypal-standard', $settings['payment_gateway'] ) ) {
												if ( !empty( $payment_options ) ) {
													$payment_options .= '<div class="paypal-description">' . __( 'or pay with PayPal', 'issuem-leaky-paywall' ) . '</div>';
												}
												$payment_options .= leaky_paywall_pay_with_paypal_standard( $level, $level_id );
											}
											break;
										
									}
									
									$results .= apply_filters( 'leaky_paywall_subscription_options_payment_options', $payment_options, $level, $level_id );							
								}
							}
						} else {
							$results .= apply_filters( 'leaky_paywall_subscription_options_payment_options', $payment_options, $level, $level_id );							
						}
						$results .= '</div>';
					} else {
						$results .= '<div class="leaky_paywall_subscription_current_level">';
						$results .= __( 'Current Subscription', 'issuem-leaky-paywall' );
						$results .= '</div>';
					}
					
					$results .= '</div>';
				
				}
				$results .= '</div>';
				
				$results .= apply_filters( 'leaky_paywall_subscription_options_after_subscription_options', '' );
				
				if ( $has_allowed_value ) {

					$results .= '<div class="leaky_paywall_subscription_limit_details">';
					$results .= '*' . ucfirst( leaky_paywall_human_readable_interval( $settings['cookie_expiration'], $settings['cookie_expiration_interval'] ) );
					$results .= '</div>';
				
				}
				
			}
				
			$results .= apply_filters( 'leaky_paywall_subscription_options_footer', '' );
		
		}
		
		return $results;
		
	}
	
}

if ( !function_exists( 'leaky_paywall_jquery_datepicker_format' ) ) { 

	/**
	 * Pass a PHP date format string to this function to return its jQuery datepicker equivalent
	 *
	 * @since 1.1.0
	 * @param string $date_format PHP Date Format
	 * @return string jQuery datePicker Format
	*/
	function leaky_paywall_jquery_datepicker_format( $date_format ) {
		
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

if ( !function_exists( 'leaky_paywall_add_lost_password_link' ) ) {

	function leaky_paywall_add_lost_password_link() {

		$settings = get_leaky_paywall_settings();
		return '<a href="' . wp_lostpassword_url() . '">' . __( 'Lost Password?', 'issuem-leaky-paywall' ) . '</a>';

	}

}

if ( !function_exists( 'leaky_paywall_payment_gateways' ) ) {
	
	function leaky_paywall_payment_gateways() {
		$gateways = array(
			'manual' 			=> __( 'Manual', 'issuem-leaky-paywall' ),
			'stripe' 			=> __( 'Stripe', 'issuem-leaky-paywall' ),
			'paypal_standard' 	=> __( 'PayPal Standard', 'issuem-leaky-paywall' ),
			'free_registration' => __( 'Free Registration', 'issuem-leaky-paywall' ),
		);
		return apply_filters( 'leaky_paywall_subscriber_payment_gateways', $gateways );
	}

}

if ( !function_exists( 'leaky_paywall_human_readable_interval' ) ) {
	function leaky_paywall_human_readable_interval( $interval_count, $interval ) {
		
		if ( 0 >= $interval_count )
			return __( 'for life', 'issuem-leaky-paywall' );
	
		if ( 1 < $interval_count )
			$interval .= 's';
		
		if ( 1 == $interval_count )
			return __( 'every', 'issuem-leaky-paywall' ) . ' ' . $interval;
		else
			return __( 'every', 'issuem-leaky-paywall' ) . ' ' . $interval_count . ' ' . $interval;
		
	}
}

if ( !function_exists( 'leaky_paywall_email_subscription_status' ) ) {

    function leaky_paywall_email_subscription_status( $user_id, $status = 'new', $args = '' ) {
    	
    	// if the args come through as a WP User object, then the user already exists in the system and we don't know their password
        if ( !empty( $args ) && is_array( $args ) ) {
            $password = $args['user_pass'];
        } else {
	        $password = '';
        }

        $settings = get_leaky_paywall_settings();

        $user_info = get_userdata( $user_id );
        $message = '';
        $admin_message = '';

        $admin_emails = array();
        $admin_emails = get_option( 'admin_email' );

        $site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
        $from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : $site_name;
        $from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

        $headers  = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
        $headers .= "Reply-To: ". $from_email . "\r\n";

        switch ( $status ) {

            case 'new' :

                // new user subscribe email
                $message = '<html>
                                <head>
                                	<title>' . $settings['new_email_subject']  . '</title>
                                </head>
                                <body>' . $settings['new_email_body'] . '</body>
                            </html>';


                if ( isset( $args ) ) {
                    // $message .= "\r\n" . 'Your username is: ' . $args['user_login'] . "\r\n";
                    // $message .= "\r\n" . 'Your temporary password is: ' . $password . '. Please log in and update your password.';
                }

                add_filter( 'wp_mail_content_type', 'leaky_paywall_set_email_content_type' );

                $filtered_subject = leaky_paywall_filter_email_tags( $settings['new_email_subject'], $user_id, $user_info->display_name, $password );
                $filtered_message = leaky_paywall_filter_email_tags( $message, $user_id, $user_info->display_name, $password );
                
				if ( 'traditional' === $settings['login_method'] ) {
                    wp_mail( $user_info->user_email, $filtered_subject, $filtered_message , $headers );
				}

                remove_filter( 'wp_mail_content_type', 'leaky_paywall_set_email_content_type' );

                // new user subscribe admin email
                $admin_message = 'A new user has signed up on ' . $site_name . '. Congratulations!';

                wp_mail( $admin_emails, sprintf( __( 'New subscription on %s', 'issuem-leaky-paywall' ), $site_name ), $admin_message, $headers );                        

            break;

            default:
            break;
            
        }

    }

}

if ( !function_exists( 'leaky_paywall_set_email_content_type' ) ) {

    function leaky_paywall_set_email_content_type( $content_type ) {
        return 'text/html';
    }

}

if ( !function_exists( 'leaky_paywall_filter_email_tags' ) ) {

    function leaky_paywall_filter_email_tags( $message, $user_id, $display_name, $password ) {

        $settings = get_leaky_paywall_settings();

        $user = get_userdata( $user_id );

        $site_name = stripslashes_deep( html_entity_decode( get_bloginfo('name'), ENT_COMPAT, 'UTF-8' ) );

        $message = str_replace('%blogname%', $site_name, $message);
        $message = str_replace('%sitename%', $site_name, $message);
        $message = str_replace('%username%', $user->user_login, $message);
        $message = str_replace('%firstname%', $user->user_firstname, $message);
        $message = str_replace('%lastname%', $user->user_lastname, $message);
        $message = str_replace('%displayname%', $display_name, $message);
        $message = str_replace('%password%', $password, $message);

        return $message;

    }
}

if ( !function_exists( 'leaky_paywall_supported_currencies' ) ) {
	
	function leaky_paywall_supported_currencies() {
		$currencies = array(
			'AED' => array( 'symbol' => '&#1583;.&#1573;', 'label' => __( 'UAE dirham', 'issuem-leaky-paywall' ) ),
			'AFN' => array( 'symbol' => 'Afs', 'label' => __( 'Afghan afghani', 'issuem-leaky-paywall' ) ),
			'ALL' => array( 'symbol' => 'L', 'label' => __( 'Albanian lek', 'issuem-leaky-paywall' ) ),
			'AMD' => array( 'symbol' => 'AMD', 'label' => __( 'Armenian dram', 'issuem-leaky-paywall' ) ),
			'ANG' => array( 'symbol' => 'NA&#402;', 'label' => __( 'Netherlands Antillean gulden', 'issuem-leaky-paywall' ) ),
			'AOA' => array( 'symbol' => 'Kz', 'label' => __( 'Angolan kwanza', 'issuem-leaky-paywall' ) ),
			'ARS' => array( 'symbol' => '$', 'label' => __( 'Argentine peso', 'issuem-leaky-paywall' ) ),
			'AUD' => array( 'symbol' => '$', 'label' => __( 'Australian dollar', 'issuem-leaky-paywall' ) ),
			'AWG' => array( 'symbol' => '&#402;', 'label' => __( 'Aruban florin', 'issuem-leaky-paywall' ) ),
			'AZN' => array( 'symbol' => 'AZN', 'label' => __( 'Azerbaijani manat', 'issuem-leaky-paywall' ) ),
			'BAM' => array( 'symbol' => 'KM', 'label' => __( 'Bosnia and Herzegovina konvertibilna marka', 'issuem-leaky-paywall' ) ),
			'BBD' => array( 'symbol' => 'Bds$', 'label' => __( 'Barbadian dollar', 'issuem-leaky-paywall' ) ),
			'BDT' => array( 'symbol' => '&#2547;', 'label' => __( 'Bangladeshi taka', 'issuem-leaky-paywall' ) ),
			'BGN' => array( 'symbol' => 'BGN', 'label' => __( 'Bulgarian lev', 'issuem-leaky-paywall' ) ),
			'BIF' => array( 'symbol' => 'FBu', 'label' => __( 'Burundi franc', 'issuem-leaky-paywall' ) ),
			'BMD' => array( 'symbol' => 'BD$', 'label' => __( 'Bermudian dollar', 'issuem-leaky-paywall' ) ),
			'BND' => array( 'symbol' => 'B$', 'label' => __( 'Brunei dollar', 'issuem-leaky-paywall' ) ),
			'BOB' => array( 'symbol' => 'Bs.', 'label' => __( 'Bolivian boliviano', 'issuem-leaky-paywall' ) ),
			'BRL' => array( 'symbol' => 'R$', 'label' => __( 'Brazilian real', 'issuem-leaky-paywall' ) ),
			'BSD' => array( 'symbol' => 'B$', 'label' => __( 'Bahamian dollar', 'issuem-leaky-paywall' ) ),
			'BWP' => array( 'symbol' => 'P', 'label' => __( 'Botswana pula', 'issuem-leaky-paywall' ) ),
			'BZD' => array( 'symbol' => 'BZ$', 'label' => __( 'Belize dollar', 'issuem-leaky-paywall' ) ),
			'CAD' => array( 'symbol' => '$', 'label' => __( 'Canadian dollar', 'issuem-leaky-paywall' ) ),
			'CDF' => array( 'symbol' => 'F', 'label' => __( 'Congolese franc', 'issuem-leaky-paywall' ) ),
			'CHF' => array( 'symbol' => 'CHF', 'label' => __( 'Swiss franc', 'issuem-leaky-paywall' ) ),
			'CLP' => array( 'symbol' => '$', 'label' => __( 'Chilean peso', 'issuem-leaky-paywall' ) ),
			'CNY' => array( 'symbol' => '&#165;', 'label' => __( 'Chinese Yuan Renminbi', 'issuem-leaky-paywall' ) ),
			'COP' => array( 'symbol' => 'Col$', 'label' => __( 'Colombian peso', 'issuem-leaky-paywall' ) ),
			'CRC' => array( 'symbol' => '&#8353;', 'label' => __( 'Costa Rican colon', 'issuem-leaky-paywall' ) ),
			'CVE' => array( 'symbol' => 'Esc', 'label' => __( 'Cape Verdean escudo', 'issuem-leaky-paywall' ) ),
			'CZK' => array( 'symbol' => 'K&#269;', 'label' => __( 'Czech koruna', 'issuem-leaky-paywall' ) ),
			'DJF' => array( 'symbol' => 'Fdj', 'label' => __( 'Djiboutian franc', 'issuem-leaky-paywall' ) ),
			'DKK' => array( 'symbol' => 'Kr', 'label' => __( 'Danish krone', 'issuem-leaky-paywall' ) ),
			'DOP' => array( 'symbol' => 'RD$', 'label' => __( 'Dominican peso', 'issuem-leaky-paywall' ) ),
			'DZD' => array( 'symbol' => '&#1583;.&#1580;', 'label' => __( 'Algerian dinar', 'issuem-leaky-paywall' ) ),
			'EEK' => array( 'symbol' => 'KR', 'label' => __( 'Estonian kroon', 'issuem-leaky-paywall' ) ),
			'EGP' => array( 'symbol' => '&#163;', 'label' => __( 'Egyptian pound', 'issuem-leaky-paywall' ) ),
			'ETB' => array( 'symbol' => 'Br', 'label' => __( 'Ethiopian birr', 'issuem-leaky-paywall' ) ),
			'EUR' => array( 'symbol' => '&#8364;', 'label' => __( 'European Euro', 'issuem-leaky-paywall' ) ),
			'FJD' => array( 'symbol' => 'FJ$', 'label' => __( 'Fijian dollar', 'issuem-leaky-paywall' ) ),
			'FKP' => array( 'symbol' => '&#163;', 'label' => __( 'Falkland Islands pound', 'issuem-leaky-paywall' ) ),
			'GBP' => array( 'symbol' => '&#163;', 'label' => __( 'British pound', 'issuem-leaky-paywall' ) ),
			'GEL' => array( 'symbol' => 'GEL', 'label' => __( 'Georgian lari', 'issuem-leaky-paywall' ) ),
			'GIP' => array( 'symbol' => '&#163;', 'label' => __( 'Gibraltar pound', 'issuem-leaky-paywall' ) ),
			'GMD' => array( 'symbol' => 'D', 'label' => __( 'Gambian dalasi', 'issuem-leaky-paywall' ) ),
			'GNF' => array( 'symbol' => 'FG', 'label' => __( 'Guinean franc', 'issuem-leaky-paywall' ) ),
			'GTQ' => array( 'symbol' => 'Q', 'label' => __( 'Guatemalan quetzal', 'issuem-leaky-paywall' ) ),
			'GYD' => array( 'symbol' => 'GY$', 'label' => __( 'Guyanese dollar', 'issuem-leaky-paywall' ) ),
			'HKD' => array( 'symbol' => 'HK$', 'label' => __( 'Hong Kong dollar', 'issuem-leaky-paywall' ) ),
			'HNL' => array( 'symbol' => 'L', 'label' => __( 'Honduran lempira', 'issuem-leaky-paywall' ) ),
			'HRK' => array( 'symbol' => 'kn', 'label' => __( 'Croatian kuna', 'issuem-leaky-paywall' ) ),
			'HTG' => array( 'symbol' => 'G', 'label' => __( 'Haitian gourde', 'issuem-leaky-paywall' ) ),
			'HUF' => array( 'symbol' => 'Ft', 'label' => __( 'Hungarian forint', 'issuem-leaky-paywall' ) ),
			'IDR' => array( 'symbol' => 'Rp', 'label' => __( 'Indonesian rupiah', 'issuem-leaky-paywall' ) ),
			'ILS' => array( 'symbol' => '&#8362;', 'label' => __( 'Israeli new sheqel', 'issuem-leaky-paywall' ) ),
			'INR' => array( 'symbol' => '&#8329;', 'label' => __( 'Indian rupee', 'issuem-leaky-paywall' ) ),
			'ISK' => array( 'symbol' => 'kr', 'label' => __( 'Icelandic króna', 'issuem-leaky-paywall' ) ),
			'JMD' => array( 'symbol' => 'J$', 'label' => __( 'Jamaican dollar', 'issuem-leaky-paywall' ) ),
			'JPY' => array( 'symbol' => '&#165;', 'label' => __( 'Japanese yen', 'issuem-leaky-paywall' ) ),
			'KES' => array( 'symbol' => 'KSh', 'label' => __( 'Kenyan shilling', 'issuem-leaky-paywall' ) ),
			'KGS' => array( 'symbol' => '&#1089;&#1086;&#1084;', 'label' => __( 'Kyrgyzstani som', 'issuem-leaky-paywall' ) ),
			'KHR' => array( 'symbol' => '&#6107;', 'label' => __( 'Cambodian riel', 'issuem-leaky-paywall' ) ),
			'KMF' => array( 'symbol' => 'KMF', 'label' => __( 'Comorian franc', 'issuem-leaky-paywall' ) ),
			'KRW' => array( 'symbol' => 'W', 'label' => __( 'South Korean won', 'issuem-leaky-paywall' ) ),
			'KYD' => array( 'symbol' => 'KY$', 'label' => __( 'Cayman Islands dollar', 'issuem-leaky-paywall' ) ),
			'KZT' => array( 'symbol' => 'T', 'label' => __( 'Kazakhstani tenge', 'issuem-leaky-paywall' ) ),
			'LAK' => array( 'symbol' => 'KN', 'label' => __( 'Lao kip', 'issuem-leaky-paywall' ) ),
			'LBP' => array( 'symbol' => '&#163;', 'label' => __( 'Lebanese lira', 'issuem-leaky-paywall' ) ),
			'LKR' => array( 'symbol' => 'Rs', 'label' => __( 'Sri Lankan rupee', 'issuem-leaky-paywall' ) ),
			'LRD' => array( 'symbol' => 'L$', 'label' => __( 'Liberian dollar', 'issuem-leaky-paywall' ) ),
			'LSL' => array( 'symbol' => 'M', 'label' => __( 'Lesotho loti', 'issuem-leaky-paywall' ) ),
			'LTL' => array( 'symbol' => 'Lt', 'label' => __( 'Lithuanian litas', 'issuem-leaky-paywall' ) ),
			'LVL' => array( 'symbol' => 'Ls', 'label' => __( 'Latvian lats', 'issuem-leaky-paywall' ) ),
			'MAD' => array( 'symbol' => 'MAD', 'label' => __( 'Moroccan dirham', 'issuem-leaky-paywall' ) ),
			'MDL' => array( 'symbol' => 'MDL', 'label' => __( 'Moldovan leu', 'issuem-leaky-paywall' ) ),
			'MGA' => array( 'symbol' => 'FMG', 'label' => __( 'Malagasy ariary', 'issuem-leaky-paywall' ) ),
			'MKD' => array( 'symbol' => 'MKD', 'label' => __( 'Macedonian denar', 'issuem-leaky-paywall' ) ),
			'MNT' => array( 'symbol' => '&#8366;', 'label' => __( 'Mongolian tugrik', 'issuem-leaky-paywall' ) ),
			'MOP' => array( 'symbol' => 'P', 'label' => __( 'Macanese pataca', 'issuem-leaky-paywall' ) ),
			'MRO' => array( 'symbol' => 'UM', 'label' => __( 'Mauritanian ouguiya', 'issuem-leaky-paywall' ) ),
			'MUR' => array( 'symbol' => 'Rs', 'label' => __( 'Mauritian rupee', 'issuem-leaky-paywall' ) ),
			'MVR' => array( 'symbol' => 'Rf', 'label' => __( 'Maldivian rufiyaa', 'issuem-leaky-paywall' ) ),
			'MWK' => array( 'symbol' => 'MK', 'label' => __( 'Malawian kwacha', 'issuem-leaky-paywall' ) ),
			'MXN' => array( 'symbol' => '$', 'label' => __( 'Mexican peso', 'issuem-leaky-paywall' ) ),
			'MYR' => array( 'symbol' => 'RM', 'label' => __( 'Malaysian ringgit', 'issuem-leaky-paywall' ) ),
			'MZN' => array( 'symbol' => 'MT', 'label' => __( 'Mozambique Metical', 'issuem-leaky-paywall' ) ),
			'NAD' => array( 'symbol' => 'N$', 'label' => __( 'Namibian dollar', 'issuem-leaky-paywall' ) ),
			'NGN' => array( 'symbol' => '&#8358;', 'label' => __( 'Nigerian naira', 'issuem-leaky-paywall' ) ),
			'NIO' => array( 'symbol' => 'C$', 'label' => __( 'Nicaraguan Córdoba', 'issuem-leaky-paywall' ) ),
			'NOK' => array( 'symbol' => 'kr', 'label' => __( 'Norwegian krone', 'issuem-leaky-paywall' ) ),
			'NPR' => array( 'symbol' => 'NRs', 'label' => __( 'Nepalese rupee', 'issuem-leaky-paywall' ) ),
			'NZD' => array( 'symbol' => 'NZ$', 'label' => __( 'New Zealand dollar', 'issuem-leaky-paywall' ) ),
			'PAB' => array( 'symbol' => 'B./', 'label' => __( 'Panamanian balboa', 'issuem-leaky-paywall' ) ),
			'PEN' => array( 'symbol' => 'S/.', 'label' => __( 'Peruvian nuevo sol', 'issuem-leaky-paywall' ) ),
			'PGK' => array( 'symbol' => 'K', 'label' => __( 'Papua New Guinean kina', 'issuem-leaky-paywall' ) ),
			'PHP' => array( 'symbol' => '&#8369;', 'label' => __( 'Philippine peso', 'issuem-leaky-paywall' ) ),
			'PKR' => array( 'symbol' => 'Rs.', 'label' => __( 'Pakistani rupee', 'issuem-leaky-paywall' ) ),
			'PLN' => array( 'symbol' => 'z&#322;', 'label' => __( 'Polish zloty', 'issuem-leaky-paywall' ) ),
			'PYG' => array( 'symbol' => '&#8370;', 'label' => __( 'Paraguayan guarani', 'issuem-leaky-paywall' ) ),
			'QAR' => array( 'symbol' => 'QR', 'label' => __( 'Qatari riyal', 'issuem-leaky-paywall' ) ),
			'RON' => array( 'symbol' => 'L', 'label' => __( 'Romanian leu', 'issuem-leaky-paywall' ) ),
			'RSD' => array( 'symbol' => 'din.', 'label' => __( 'Serbian dinar', 'issuem-leaky-paywall' ) ),
			'RUB' => array( 'symbol' => 'R', 'label' => __( 'Russian ruble', 'issuem-leaky-paywall' ) ),
			'RWF' => array( 'symbol' => 'R&#8355;', 'label' => 'Rwandan Franc' ),
			'SAR' => array( 'symbol' => 'SR', 'label' => __( 'Saudi riyal', 'issuem-leaky-paywall' ) ),
			'SBD' => array( 'symbol' => 'SI$', 'label' => __( 'Solomon Islands dollar', 'issuem-leaky-paywall' ) ),
			'SCR' => array( 'symbol' => 'SR', 'label' => __( 'Seychellois rupee', 'issuem-leaky-paywall' ) ),
			'SEK' => array( 'symbol' => 'kr', 'label' => __( 'Swedish krona', 'issuem-leaky-paywall' ) ),
			'SGD' => array( 'symbol' => 'S$', 'label' => __( 'Singapore dollar', 'issuem-leaky-paywall' ) ),
			'SHP' => array( 'symbol' => '&#163;', 'label' => __( 'Saint Helena pound', 'issuem-leaky-paywall' ) ),
			'SLL' => array( 'symbol' => 'Le', 'label' => __( 'Sierra Leonean leone', 'issuem-leaky-paywall' ) ),
			'SOS' => array( 'symbol' => 'Sh.', 'label' => __( 'Somali shilling', 'issuem-leaky-paywall' ) ),
			'SRD' => array( 'symbol' => '$', 'label' => __( 'Surinamese dollar', 'issuem-leaky-paywall' ) ),
			'STD' => array( 'symbol' => 'STD', 'label' => __( 'São Tomé and Príncipe Dobra', 'issuem-leaky-paywall' ) ),
			'SVC' => array( 'symbol' => '$', 'label' => __( 'El Salvador Colon', 'issuem-leaky-paywall' ) ),
			'SZL' => array( 'symbol' => 'E', 'label' => __( 'Swazi lilangeni', 'issuem-leaky-paywall' ) ),
			'THB' => array( 'symbol' => '&#3647;', 'label' => __( 'Thai baht', 'issuem-leaky-paywall' ) ),
			'TJS' => array( 'symbol' => 'TJS', 'label' => __( 'Tajikistani somoni', 'issuem-leaky-paywall' ) ),
			'TOP' => array( 'symbol' => 'T$', 'label' => __( "Tonga Pa'anga", 'issuem-leaky-paywall' ) ),
			'TRY' => array( 'symbol' => 'TRY', 'label' => __( 'Turkish new lira', 'issuem-leaky-paywall' ) ),
			'TTD' => array( 'symbol' => 'TT$', 'label' => __( 'Trinidad and Tobago dollar', 'issuem-leaky-paywall' ) ),
			'TWD' => array( 'symbol' => 'NT$', 'label' => __( 'New Taiwan dollar', 'issuem-leaky-paywall' ) ),
			'TZS' => array( 'symbol' => 'TZS', 'label' => __( 'Tanzanian shilling', 'issuem-leaky-paywall' ) ),
			'UAH' => array( 'symbol' => 'UAH', 'label' => __( 'Ukrainian hryvnia', 'issuem-leaky-paywall' ) ),
			'UGX' => array( 'symbol' => 'USh', 'label' => __( 'Ugandan shilling', 'issuem-leaky-paywall' ) ),
			'USD' => array( 'symbol' => '$', 'label' => __( 'United States dollar', 'issuem-leaky-paywall' ) ),
			'UYU' => array( 'symbol' => '$U', 'label' => __( 'Uruguayan peso', 'issuem-leaky-paywall' ) ),
			'UZS' => array( 'symbol' => 'UZS', 'label' => __( 'Uzbekistani som', 'issuem-leaky-paywall' ) ),
			'VND' => array( 'symbol' => '&#8363;', 'label' => __( 'Vietnamese dong', 'issuem-leaky-paywall' ) ),
			'VUV' => array( 'symbol' => 'VT', 'label' => __( 'Vanuatu vatu', 'issuem-leaky-paywall' ) ),
			'WST' => array( 'symbol' => 'WS$', 'label' => __( 'Samoan tala', 'issuem-leaky-paywall' ) ),
			'XAF' => array( 'symbol' => 'CFA', 'label' => __( 'Central African CFA franc', 'issuem-leaky-paywall' ) ),
			'XCD' => array( 'symbol' => 'EC$', 'label' => __( 'East Caribbean dollar', 'issuem-leaky-paywall' ) ),
			'XOF' => array( 'symbol' => 'CFA', 'label' => __( 'West African CFA franc', 'issuem-leaky-paywall' ) ),
			'XPF' => array( 'symbol' => 'F', 'label' => __( 'CFP franc', 'issuem-leaky-paywall' ) ),
			'YER' => array( 'symbol' => 'YER', 'label' => __( 'Yemeni rial', 'issuem-leaky-paywall' ) ),
			'ZAR' => array( 'symbol' => 'R', 'label' => __( 'South African rand', 'issuem-leaky-paywall' ) ),
			'ZMW' => array( 'symbol' => 'ZK', 'label' => __( 'Zambian kwacha', 'issuem-leaky-paywall' ) ),
		);
	
		return apply_filters( 'leaky_paywall_supported_currencies', $currencies );
	}
}

if ( !function_exists( 'zeen101_dot_com_leaky_rss_feed_check' ) ) {

	/**
	 * Check zeen101.com for new RSS items in the leaky blast feed, to update users of latest Leaky Paywall news
	 *
	 * @since 1.1.1
	 */
	function zeen101_dot_com_leaky_rss_feed_check() {
			
		include_once( ABSPATH . WPINC . '/feed.php' );
	
		$output = '';
		$feedurl = 'http://zeen101.com/feed/?post_type=blast&target=leaky-paywall';

		$rss = fetch_feed( $feedurl );

		if ( $rss && !is_wp_error( $rss ) ) {
	
			$rss_items = $rss->get_items( 0, 1 );
	
			foreach ( $rss_items as $item ) {
	
				$last_rss_item = get_option( 'last_zeen101_dot_com_leaky_rss_item' );
				
				$latest_rss_item = $item->get_content();
	
				if ( $last_rss_item !== $latest_rss_item ) {

					$current_user = wp_get_current_user();

					update_option( 'last_zeen101_dot_com_leaky_rss_item', $latest_rss_item );

					update_user_meta( $current_user->ID, 'leaky_paywall_rss_item_notice_link', 0 );
				}

			}
	
		}	
				
	}
	add_action( 'zeen101_dot_com_leaky_rss_feed_check', 'zeen101_dot_com_leaky_rss_feed_check' );

	if ( !wp_next_scheduled( 'zeen101_dot_com_leaky_rss_feed_check' ) )
		wp_schedule_event( time(), 'daily', 'zeen101_dot_com_leaky_rss_feed_check' );
	
}

/**
 * Helper function to convert object to array
 *
 * @since 3.7.0
 * @return array
 */
if ( ! function_exists( 'object_to_array' ) ) {

	function object_to_array ($object) {

	    if(!is_object($object) && !is_array($object))
	        return $object;

	    return array_map('objectToArray', (array) $object);
	}

}

/**
 * Allow csv files to be uploaded via the media uploader
 *
 * @since  3.7.1
 */
add_filter('upload_mimes', 'leaky_paywall_upload_mimes');

function leaky_paywall_upload_mimes ( $existing_mimes=array() ) {
    $existing_mimes['csv'] = 'text/csv';
    return $existing_mimes;
}

/**
 * Convert csv file to array
 * @param  string $filename  csv file name
 * @param  string $delimiter separator for data fields
 * @return array            array of data from csv
 */
function leaky_paywall_csv_to_array( $filename = '', $delimiter = ',' ) {

	if (!file_exists($filename) || !is_readable($filename)) {
		
		// return FALSE;
	}

	$header = NULL;
	$data = array();

	if (($handle = fopen($filename, 'r')) !== FALSE) {
		while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {

			if (!$header) {
				$header = $row;
			} else {
				$data[] = array_combine($header, $row);
			}

		}
		fclose($handle);
	}
	return $data;

}
