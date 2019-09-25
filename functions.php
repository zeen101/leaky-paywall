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



if (!function_exists('is_level_deleted') ) {

	function is_level_deleted( $level_id ) {

		$settings = get_leaky_paywall_settings();
		$level = $settings["levels"][$level_id];

		if($level['deleted'] == 1) {
			return true;
		}

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


if ( !function_exists( 'leaky_paywall_user_has_access' ) ) {
	
	/**
	 * Determine if a user has access based on their expiration date and their payment status. 
	 *
	 * @since 4.9.3
	 *
	 * @param object $user object from WordPress database
	 * @return bool true if the user has access or false if they have either expired or their payment status is set to deactived
	 */
	function leaky_paywall_user_has_access( $user = null ) {

		if ( null == $user ) {
			$user = wp_get_current_user();
		}

		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();
		$unexpired = false;

		$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
		$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );


		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
			$unexpired = true;
		} else {
			// $date_format = get_option( 'date_format' );
			// $expires = mysql2date( $date_format, $expires );

			if ( strtotime( $expires ) > time() ) {
				$unexpired = true;
			}
		}

		if ( $unexpired && $payment_status != 'deactivated' ) {
			$has_access = true;
		} else {
			$has_access = false;
		}

		if ( !is_user_logged_in() ) {
			$has_access = false;
		}

		return apply_filters( 'leaky_paywall_user_has_access', $has_access, $user );
		
	}

}

if ( !function_exists( 'leaky_paywall_get_current_mode' ) ) {
	
	function leaky_paywall_get_current_mode() {

		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		return apply_filters( 'leaky_paywall_current_mode', $mode );
		
	}

}

if ( !function_exists( 'leaky_paywall_get_current_site' ) ) {
	
	function leaky_paywall_get_current_site() {

		global $blog_id;

		if ( is_multisite_premium() && !is_main_site( $blog_id ) ) {
			$site = '_' . $blog_id;
		} else {
			$site = '';
		}

		return apply_filters( 'leaky_paywall_current_site', $site );
		
	}

}

if ( !function_exists( 'leaky_paywall_get_currency' ) ) {
	
	/**
	 * Get the currency value set in the Leaky Paywall settings
	 *
	 * @since 4.9.3
	 *
	 * @return string Currency code (i.e USD)
	 */
	function leaky_paywall_get_currency() {

		$settings = get_leaky_paywall_settings();
		$currency = $settings['leaky_paywall_currency'];

		return apply_filters( 'leaky_paywall_currency', $currency );
		
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
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		if ( empty( $email ) ) {
			$user = wp_get_current_user();
			if ( $user->ID == 0 ) { // no user
				return false;
			}
		} else {
			if ( is_email( $email ) ) {
				$user = get_user_by( 'email', $email );

				if ( !$user ) { // no user found with that email address
					return false;
				}

			} else {
				return false;
			}
		}
		
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
		
		foreach ( $sites as $site ) {
		
			$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
			$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
			$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );

			if ( $payment_gateway !== 'stripe' ) {

				if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
					if ( !empty( $plan ) && 'active' == $payment_status ) {
						return 'subscription';
					}
				}

				switch( $payment_status ) {
				
					case 'Active':
					case 'active':
					case 'refunded':
					case 'refund':
						$expires = apply_filters( 'leaky_paywall_has_user_paid_expires', $expires, $payment_gateway, $payment_status, $subscriber_id, $plan, $expires, $user, $mode, $site );
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
						break;
					
				}		

			} else {

				// check with Stripe to make sure the user has an active subscription
				
				if ( $mode == 'test' ) {
					$secret_key = isset( $settings['test_secret_key'] ) ? trim( $settings['test_secret_key'] ) : '';
				} else {
					$secret_key = isset( $settings['live_secret_key'] ) ? trim( $settings['live_secret_key'] ) : '';
				}
				
				\Stripe\Stripe::setApiKey( $secret_key );
				
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
								break;
							case 'reversed':
							case 'buyer_complaint':
							case 'denied' :
							case 'expired' :
							case 'failed' :
							case 'voided' :
							case 'deactivated' :
								break;
						}
					} else {
						$cu = \Stripe\Customer::retrieve( $subscriber_id );
						
						if ( !empty( $cu ) ) {
							if ( !empty( $cu->deleted ) && true === $cu->deleted ) {
								$canceled = true;
							}
						}
						
						if ( !empty( $plan ) ) {
							if ( isset( $cu->subscriptions ) ) {
								$subscriptions = $cu->subscriptions->all( array('limit' => '1') );
								foreach( $subscriptions->data as $subscription ) {
									if ( leaky_paywall_is_valid_stripe_subscription( $subscription ) ) {
										return 'subscription';
									}
								}
							}
						}
						
						$ch = \Stripe\Charge::all( array( 'count' => 1, 'customer' => $subscriber_id ) );
										
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
					}

				} catch ( Exception $e ) {
					$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'leaky-paywall' ), $e->getMessage() ) . '</h1>';
				}

			}
	
		} // end foreach 

		if ( is_bool( $canceled ) && $canceled ) {
			$paid = false;
		}

		if ( is_bool( $expired ) && $expired ) {
			$paid = false;
		}
	
		return apply_filters( 'leaky_paywall_has_user_paid', $paid, $payment_gateway, $payment_status, $subscriber_id, $plan, $expires, $user, $mode, $site );
		
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

		if ( is_multisite_premium() && !is_main_site( $data['site'] ) ) {
			$site = '_' . $data['site'];
		} else {
			$site = '';
		}

		$mode = leaky_paywall_get_current_mode();

		if ( isset( $data['expires'] ) && $data['expires'] ) {
			$expires = $data['expires'];
		} else if ( !empty( $data['interval'] ) && isset( $data['interval_count'] ) && 1 <= $data['interval_count'] ) {
			$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $data['interval_count'] . ' ' . $data['interval'] ) ); //we're generous, give them the whole day!
		} else {
			$expires = '0000-00-00 00:00:00';
		}

		update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, apply_filters( 'leaky_paywall_set_expiration_date', $expires, $data, $user_id ) );

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

		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		$expires = '0000-00-00 00:00:00';

		if ( $user = get_user_by( 'email', $email ) ) { 
			//the user already exists
			//grab the ID for later
			$user_id = $user->ID;
			$userdata = get_userdata( $user_id );
		} else {

			//the user doesn't already exist

			// if they submitted a custom login name, use that
			if ( isset( $meta_args['login'] ) ) {
				$login = $meta_args['login'];
			}

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

			if ( isset( $meta_args['password'] ) ) {
				$password = $meta_args['password'];
			} else {
				$password = wp_generate_password();
			}
			
            $userdata = array(
			    'user_login' 		=> $login,
				'user_email' 		=> $email,
				'user_pass' 		=> $password,
				'first_name'		=> isset( $meta_args['first_name'] ) ? $meta_args['first_name'] : '',
				'last_name'			=> isset( $meta_args['last_name'] ) ? $meta_args['last_name'] : '',
				'display_name'		=> isset( $meta_args['first_name'] ) ? $meta_args['first_name'] . ' ' . $meta_args['last_name'] : '',
				'user_registered'	=> date_i18n( 'Y-m-d H:i:s' ),
			);

            $userdata = apply_filters( 'leaky_paywall_userdata_before_user_create', $userdata );
			$user_id = wp_insert_user( $userdata );

		}
		
		if ( empty( $user_id ) ) {
			leaky_paywall_log( $meta_args, 'could not create user');
			return false;
		}

		leaky_paywall_set_expiration_date( $user_id, $meta_args );
		unset( $meta_args['site'] );	

		if ( isset( $meta_args['created'] ) && $meta_args['created'] ) {
			$created_date = strtotime( $meta_args['created'] );
			$meta_args['created'] = date( 'Y-m-d H:i:s', $created_date );
		} else {
			$meta_args['created'] = date( 'Y-m-d H:i:s' );
		}

		

		// set free level subscribers to active
		if ( $meta_args['price'] == '0' ) {
			$meta_args['payment_status'] = 'active';
		}
		
		$meta = apply_filters( 'leaky_paywall_new_subscriber_meta', $meta_args, $email, $customer_id, $meta_args );

		// remove any extra underscores from site variable
		$site = str_replace( '__', '_', $site );
	
		foreach( $meta as $key => $value ) {

			// do not want to store their password as plain text
			if ( $key == 'confirm_password' || $key == 'password' ) {
				continue;
			}

			update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
			
		}
			
		do_action( 'leaky_paywall_new_subscriber', $user_id, $email, $meta, $customer_id, $meta_args, $userdata );
		
		return $user_id;
		
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
				
		if ( !is_email( $email ) ) {
			return false;
		}
			
		$settings = get_leaky_paywall_settings();
		
		if ( is_multisite_premium() && !is_main_site( $meta_args['site'] ) ) {
			$site = '_' . $meta_args['site'];
		} else {
			$site = '';
		}
		
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

		leaky_paywall_set_expiration_date( $user_id, $meta_args );
		unset( $meta_args['site'] );
			
		
		// if ( !empty( $meta_args['interval'] ) && isset( $meta_args['interval_count'] ) && 1 <= $meta_args['interval_count'] ) {
		// 	$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $meta_args['interval_count'] . ' ' . $meta_args['interval'] ) ); //we're generous, give them the whole day!
		// } else if ( !empty( $meta_args['expires'] ) ) {
		// 	$expires = $meta_args['expires'];
		// }
		
		$meta = array(
			'level_id' 			=> $meta_args['level_id'],
			'subscriber_id' 	=> $customer_id,
			'price' 			=> $meta_args['price'],
			'description' 		=> $meta_args['description'],
			'plan' 				=> $meta_args['plan'],
			// 'expires' 			=> $expires,
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
	
}


/**
 * Get all valid and active Leaky Paywall levels
 *
 * @since 4.9.0
 *
 * @return array List of active levels
 */
function leaky_paywall_get_levels() {

	$settings = get_leaky_paywall_settings();
	$blog_id = get_current_blog_id();

	$level_list = array();

	foreach( $settings['levels'] as $key => $level ) {
		
		if ( !empty( $level['deleted'] ) ) {
			continue;
		}

		if ( is_multisite_premium() && !empty( $level['site'] ) && 'all' != $level['site'] && $blog_id != $level['site'] ) {
			continue;
		}

		if ( !is_numeric( $key ) ) {
			continue;
		}

		$level_list[$key] = $level;

	}

	return $level_list;
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
				$return = __( 'Manually Added', 'leaky-paywall' );
				break;
			default:
				$return = $slug;
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
				return '<p>' . __( 'No payment gateway defined.', 'leaky-paywall' ) . '</p>';
			}
			
			if ( !empty( $_REQUEST['subscriber_id'] ) ) {
				$subscriber_id = $_REQUEST['subscriber_id'];
			} else {
				return '<p>' . __( 'No subscriber ID defined.', 'leaky-paywall' ) . '</p>';
			}
			
			if ( isset( $_REQUEST['cancel'] ) && empty( $_REQUEST['cancel'] ) ) {
	
				$form = '<h3>' . __( 'Cancel Subscription', 'leaky-paywall' ) . '</h3>';

				$cancel_description = '<p>' . __( 'Cancellations take effect at the end of your billing cycle, and we can’t give partial refunds for unused time in the billing cycle. If you still wish to cancel now, you may proceed, or you can come back later.', 'leaky-paywall' ) . '</p>';
				$cancel_description .= '<p>' . sprintf( __( ' Thank you for the time you’ve spent subscribed to %s. We hope you’ll return someday. ', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';

				$form .= apply_filters( 'leaky_paywall_cancel_subscription_description', $cancel_description );

				$form .= '<a href="' . esc_url( add_query_arg( array( 'cancel' => 'confirm' ) ) ) . '">' . __( 'Yes, cancel my subscription!', 'leaky-paywall' ) . '</a> | <a href="' . get_page_link( $settings['page_for_profile'] ) . '">' . __( 'No, get me outta here!', 'leak-paywall' ) . '</a>';
				
			} else if ( !empty( $_REQUEST['cancel'] ) && 'confirm' === $_REQUEST['cancel'] ) {
				
				$user = wp_get_current_user();
									
				if ( 'stripe' === $payment_gateway ) {
				
					try {
						
						$secret_key = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
													
						$cu = \Stripe\Customer::retrieve( $subscriber_id );
							
						if ( !empty( $cu ) )
							if ( true === $cu->deleted )
								throw new Exception( __( 'Unable to find valid Stripe customer ID to unsubscribe. Please contact support', 'leaky-paywall' ) );

						$subscriptions = $cu->subscriptions->all( array('limit' => '1') );

						foreach( $subscriptions->data as $susbcription ) {
							$sub = $cu->subscriptions->retrieve( $susbcription->id );
							$results = $sub->cancel();
						}
											
						if ( !empty( $results->status ) && 'canceled' === $results->status ) {
							
							$form .= '<p>' . sprintf( __( 'Your subscription has been successfully canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
							//We are creating plans with the site of '_all', even on single sites.  This is a quick fix but needs to be readdressed.
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, 'Canceled' );
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan_all', 'Canceled' );

							do_action('leaky_paywall_cancelled_subscriber', $user, 'stripe' );

						} else {
						
							$form .= '<p>' . sprintf( __( 'ERROR: An error occured when trying to unsubscribe you from your account, please try again. If you continue to have trouble, please contact us. Thank you.', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
							
						}
						
						$form .= '<a href="' . get_home_url() . '">' . sprintf( __( 'Return to %s...', 'leak-paywall' ), $settings['site_name'] ) . '</a>';
						
					} catch ( Exception $e ) {
					
						$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'leaky-paywall' ), $e->getMessage() ) . '</h1>';
						
					}
				
				} else if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {

					$paypal_url   = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
					$paypal_email = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
					$form .= '<p>' . sprintf( __( 'You must cancel your account through PayPal. Please click this unsubscribe button to complete the cancellation process.', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
					$form .= '<p><a href="' . $paypal_url . '?cmd=_subscr-find&alias=' . urlencode( $paypal_email ) . '"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_unsubscribe_LG.gif" border="0"></a></p>';
					
				} else {
					
					$form .= '<p>' . __( 'Unable to determine your payment method. Please contact support for help canceling your account.', 'leaky-paywall' ) . '</p>';
					
				}
				
			}
			
		} else {
			
			$form .= '<p>' . __( 'You must be logged in to cancel your account.', 'leaky-paywall' ) . '</p>';
			
		}
		
		return apply_filters( 'leaky_paywall_cancellation_confirmation', $form );
		
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
		
		return wp_mail( $email, __( 'Log into ' . get_bloginfo( 'name' ), 'leaky-paywall' ), $message, $headers );
		
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

		if ( isset( $settings['restrictions']['post_types'] ) ) {
			$restrictions = $settings['restrictions']['post_types']; //defaults
		} else {
			$restrictions = '';
		}
		
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

				if ( 'trial' === $status && is_numeric( $level_id ) ) {
					$level_ids[] = $level_id;
				}

				// if status is cancelled but they aren't expired yet
				if ( 'canceled' === $status && is_numeric( $level_id ) ) {
		
					$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
					$expired_timestamp = strtotime( $expires );

					if ( $expired_timestamp > current_time( 'timestamp' ) ) {
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
							'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
							'compare' => 'EXISTS',
						),
					);
					$args['search'] = $search;
					$args['search_columns'] = array( 'user_login', 'user_email' );
					
				} else {
						
					$args['meta_query'] = array(
						'relation' => 'AND',
						array(
							'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
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
						'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
						'compare' => 'EXISTS',
					),
				);
			}
			
			if ( !empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] )
				unset( $args['meta_query'] );

			if ( isset( $_GET['filter-level'] ) && 'lpsubs' == $_GET['user-type'] ) {

				$level = esc_attr( $_GET['filter-level'] );

				if ( 'all' != $level ) {

					$args['meta_query'][] = array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
						'value'   => $level,
						'compare' => 'LIKE',
					);

				}
				
			}

			if ( isset( $_GET['filter-status'] ) && 'lpsubs' == $_GET['user-type'] ) {

				$status = esc_attr( $_GET['filter-status'] );

				if ( 'all' != $status ) {

					$args['meta_query'][] = array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
						'value'   => $status,
						'compare' => 'LIKE',
					);

				}
				
			}

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

	    wp_redirect( $url );
	    die();
	    	
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

	                do_action( 'leaky_paywall_before_download_pdf', $url );
	
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
					$output = '<h3>' . __( 'Error Downloading PDF', 'leaky-paywall' ) . '</h3>';
		
					$output .= '<p>' . sprintf( __( 'Download Error: Invalid response: %s', 'leaky-paywall' ), wp_remote_retrieve_response_code( $response ) ) . '</p>';
					$output .= '<a href="' . get_home_url() . '">' . __( 'Home', 'leaky-paywall' ) . '</a>';
	            	
		            wp_die( $output );
	            }
	        } else {
				$output = '<h3>' . __( 'Error Downloading PDF', 'leaky-paywall' ) . '</h3>';
	
				$output .= '<p>' . sprintf( __( 'Download Error: %s', 'leaky-paywall' ), $response->get_error_message() ) . '</p>';
				$output .= '<a href="' . get_home_url() . '">' . __( 'Home', 'leak-paywall' ) . '</a>';
            	
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
	
		$default = array(
			'label' 							=> '',
			'description'						=> '',
			'registration_form_description'		=> '',
			'price' 							=> '',
			'subscription_length_type' 			=> 'limited',
			'interval_count' 					=> 1,
			'interval' 							=> 'month',
			'recurring' 						=> 'off',
			'hide_subscribe_card'				=> 'off',
			'plan_id' 							=> array(),
			'post_types' => array(
				array(
					'post_type' 				=> ACTIVE_ISSUEM ? 'article' : 'post',
					'allowed' 					=> 'unlimited',
					'allowed_value' 			=> -1,
				)
			),
			'deleted' 							=> 0,
			'site' 								=> 'all',
		);
		$level = wp_parse_args( $level, $default );
    	
    	if ( empty( $level['recurring'] ) )
    		$level['recurring'] = 'off';
    		
    	if ( !empty( $level['deleted'] ) ) {
	    	$deleted = 'hidden';
	    } else {
			$deleted = '';
	    }

	    ob_start();
	    ?>
	    
		<table class="issuem-leaky-paywall-subscription-level-row-table leaky-paywall-table <?php echo $deleted; ?>">
			<tr>
				<th>
					<label for="level-name-<?php echo $row_key; ?>"><?php _e( 'Subscription Name', 'leaky-paywall' ); ?></label>
					<p class="description"><?php _e( 'Subscription ID: ', 'leaky-paywall' ); ?><?php echo $row_key; ?></p>
				</th>
				<td>
					<input id="level-name-<?php echo $row_key; ?>" type="text" name="levels[<?php echo $row_key; ?>][label]" value="<?php echo htmlspecialchars( stripcslashes( $level['label'] ) ); ?>" />
					<span class="delete-x delete-subscription-level">&times;</span>
					<input type="hidden" class="deleted-subscription" name="levels[<?php echo $row_key; ?>][deleted]" value="<?php echo $level['deleted']; ?>">
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-description-<?php echo $row_key; ?>"><?php _e( 'Subscribe Card Description', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<textarea id="level-description-<?php echo $row_key; ?>" name="levels[<?php echo $row_key; ?>][description]" class="large-text"><?php echo stripslashes( $level['description'] ); ?></textarea>
					<p class="description"><?php _e( 'If entered, this will replace the auto-generated access description on the subscribe cards. HTML allowed.', 'leaky-paywall' ); ?></p>
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-registration-form-description-<?php echo $row_key; ?>"><?php _e( 'Registration Form Description', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<textarea id="level-registration-form-description-<?php echo $row_key; ?>" name="levels[<?php echo $row_key; ?>][registration_form_description]" class="large-text"><?php echo stripslashes( $level['registration_form_description'] ); ?></textarea>
					<p class="description"><?php _e( 'If entered, this will replace the auto-generated content access description on the registration form. HTML allowed.', 'leaky-paywall' ); ?></p>
				</td>
			</tr>
		    
			<?php if ( is_leaky_paywall_recurring() ) {
		    	?>
		    	<tr>		
		    		<th>
		    			<label for="level-recurring-<?php echo $row_key; ?>"><?php _e( 'Recurring', 'leaky-paywall' ); ?></label>
		    		</th>
		    		<td>
		    			<input id="level-recurring-<?php echo $row_key; ?>" class="stripe-recurring" type="checkbox" name="levels[<?php echo $row_key; ?>][recurring]" value="on" <?php echo checked( 'on', $level['recurring'], false ); ?> /> Enable recurring payments<br>
		    			<span style="color: #999; font-size: 11px;" class="recurring-help <?php echo checked( 'on', $level['recurring'], false ) ? '' : 'hidden'; ?>">Webhooks must be setup in your Stripe account for recurring payments to work properly. <a target="_blank" href="https://zeen101.helpscoutdocs.com/article/120-leaky-paywall-recurring-payments">See documentation here.</a></span>

		    			<?php 

		    				if ( is_array( $level['plan_id'] ) ) {
		    					foreach( $level['plan_id'] as $plan_id ) {
		    						?>
		    						<input type="hidden" class="level-plan_id-<?php echo $row_key; ?>" name="levels[<?php echo $row_key; ?>][plan_id][]" value="<?php echo $plan_id; ?>">
		    						<?php 
		    					}
		    				} else {
		    					?>
		    					<input type="hidden" id="level-plan_id-<?php echo $row_key; ?>" name="levels[<?php echo $row_key; ?>][plan_id]" value="<?php echo $level['plan_id']; ?>">
		    					<?php 
		    				}
		    				
		    			?>
		    			
		    		</td>
		    	</tr>
		    	<?php 
		    } ?>
			
			<tr>		
				<th>
					<label for="level-hide-subscribe-card-<?php echo $row_key; ?>"><?php _e( 'Hide Subscribe Card', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<input id="level-hide-subscribe-card-<?php echo $row_key; ?>" class="hide-subscribe- card" type="checkbox" name="levels[<?php echo $row_key; ?>][hide_subscribe_card]" value="on" <?php echo checked( 'on', $level['hide_subscribe_card'], false ); ?> /> Do not display subscribe card on subscribe page
				</td>
			</tr>
						
			<tr>
				<th>
					<label for="level-price-<?php echo $row_key; ?>"><?php _e( 'Subscription Price', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<input id="level-price-<?php echo $row_key; ?>" type="text" class="small-text" name="levels[<?php echo $row_key; ?>][price]" value="<?php echo stripcslashes( $level['price'] ); ?>" />
					<p class="description"><?php _e( '0 for Free Subscriptions', 'leaky-paywall' ); ?></p>
				</td>
			</tr>
				
			<tr>
				<th>
					<label for="level-interval-count-<?php echo $row_key; ?>"><?php _e( 'Subscription Length', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<select class="subscription_length_type" name="levels[<?php echo $row_key; ?>][subscription_length_type]">					
						<option value="unlimited" <?php echo selected( 'unlimited', $level['subscription_length_type'], false ); ?>><?php _e( 'Forever', 'leaky-paywall' ); ?></option>
					<option value="limited" <?php echo selected( 'limited', $level['subscription_length_type'], false ); ?>> <?php _e( 'Limited for...', 'leaky-paywall' ); ?></option>
					</select>
				
					<?php 
						if ( 'unlimited' == $level['subscription_length_type'] ) {
							$subscription_length_input_style = 'display: none;';
						} else {
							$subscription_length_input_style = '';
						}
					?>

					<div class="interval_div" style="<?php echo $subscription_length_input_style; ?>">
						<input id="level-interval-count-<?php echo $row_key; ?>" type="text" class="interval_count small-text" name="levels[<?php echo $row_key; ?>][interval_count]" value="<?php echo stripcslashes( $level['interval_count'] ); ?>" />	
						<select id="interval" name="levels[<?php echo $row_key; ?>][interval]">
	        				<option value="day" <?php echo selected( 'day' === $level['interval'], true, false ); ?>><?php _e( 'Day(s)', 'leaky-paywall' ); ?></option>
	        				<option value="week" <?php echo selected( 'week' === $level['interval'], true, false ); ?>><?php _e( 'Week(s)', 'leaky-paywall' ); ?></option>
	        				<option value="month" <?php echo selected( 'month' === $level['interval'], true, false ); ?>><?php _e( 'Month(s)', 'leaky-paywall' ); ?></option>
	        				<option value="year" <?php echo selected( 'year' === $level['interval'], true, false ); ?>><?php _e( 'Year(s)', 'leaky-paywall' ); ?></option>
	        			</select>
       				</div>
        		</td>
			</tr>
        		
			<tr>
				<th><?php _e( 'Access Options', 'leaky-paywall' ); ?></th>
				<td id="issuem-leaky-paywall-subsciption-row-<?php echo $row_key; ?>-post-types">

					<table class="leaky-paywall-interal-setting-table">
						<tr>
							<th>Number Allowed</th>
							<th>Post Type</th>
							<th>Taxonomy <span style="font-weight: normal; font-size: 11px; color: #999;"> Category,tag,etc.</span></th>
							<th>&nbsp;</th>
						</tr>
					
						<?php 
							$last_key = -1;
							if ( !empty( $level['post_types'] ) ) {
								foreach( $level['post_types'] as $select_post_key => $select_post_type ) {
									
									build_leaky_paywall_subscription_row_post_type( $select_post_type, $select_post_key, $row_key );
									
									$last_key = $select_post_key;
								}
							}
						?>
					</table>
				</td>
			</tr>
		
			<tr>
				<th>&nbsp;</th>
				<td>
        			<script>
        				var leaky_paywall_subscription_row_<?php echo $row_key; ?>_last_post_type_key = <?php echo absint($last_key); ?>;
        			</script>
					<p><input data-row-key="<?php echo $row_key; ?>" class="button-secondary" id="add-subscription-row-post-type" class="add-new-issuem-leaky-paywall-row-post-type" type="submit" name="add_leaky_paywall_subscription_row_post_type" value="<?php _e( '+ Add Access Option', 'leaky-paywall' ); ?>" /></p>
					<?php 	
						if ( $leaky_paywall->is_site_wide_enabled() ) {
							echo '<p class="description">';
							_e( 'Post Types that are not native the to the site currently being viewed are marked with an asterisk.', 'leaky-paywall' ); 
							echo '</p>';
						}
					?>
				</td>
			</tr>
			
			<?php 
			if ( is_multisite_premium() ) {
		        ?>
				<tr>
					<th><?php _e( 'Site', 'leaky-paywall' ); ?></th>
					<td id="issuem-leaky-paywall-subsciption-row-<?php echo $row_key; ?>-site">
						<select id="site" name="levels[<?php echo $row_key; ?>][site]">
							<?php 
							if ( is_super_admin() ) {
								?>
						        <option value="all" <?php echo selected( 'all', $level['site'], false ); ?>><?php _e( 'All Sites', 'leaky-paywall' ); ?></option>
						        <?php 
								$sites = get_sites();
								foreach( $sites as $site ) {
									$site_details = get_blog_details( $site->id );
									?>
									<option value="<?php echo $site->id; ?>" <?php echo selected( $site->id, $level['site'], false ); ?>><?php echo $site_details->blogname; ?></option>
									<?php 
								}
							} else {
								$site_details = get_blog_details( get_current_blog_id() );
								?>
								<option value="<?php echo get_current_blog_id(); ?>" <?php echo selected( get_current_blog_id(), $level['site'], false ); ?>><?php echo $site_details->blogname; ?></option>
								<?php 
							} ?>

	       
	        			</select>
					</td>
				</tr>
				
				<?php 
			}

			// leaving for backwards compatibility, but it will deprecated
			echo apply_filters( 'build_leaky_paywall_subscription_levels_row_addon_filter', '', $level, $row_key );

			do_action( 'leaky_paywall_after_subscription_levels_row', $level, $row_key );
		
		echo '</table>';

		$content = ob_get_contents();
		ob_end_clean();

		return $content; 
		
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
			'taxonomy'		=> ''
		);
		$select_post_type = wp_parse_args( $select_post_type, $default_select_post_type );
		

		echo '<tr class="issuem-leaky-paywall-row-post-type">';
		
		echo '<td><select class="allowed_type" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed]">';						
			echo '<option value="unlimited" ' . selected( 'unlimited', $select_post_type['allowed'], false ) . '>' . __( 'Unlimited', 'leaky-paywall' ) . '</option>';
			echo '<option value="limited" ' . selected( 'limited', $select_post_type['allowed'], false ) . '>' . __( 'Limit to...', 'leaky-paywall' ) . '</option>';
		echo '</select>';
			
		if ( 'unlimited' == $select_post_type['allowed'] ) {
			$allowed_value_input_style = 'display: none;';
		} else {
			$allowed_value_input_style = '';
		}
			    
		echo '<div class="allowed_value_div" style="' . $allowed_value_input_style . '">';
		echo '<input type="number" class="allowed_value small-text" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][allowed_value]" value="' . $select_post_type['allowed_value'] . '" placeholder="' . __( '#', 'leaky-paywall' ) . '" />';
		echo '</div></td>';
		
		echo '<td><select class="select_level_post_type" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][post_type]">';
		$post_types = get_post_types( array(), 'objects' );
		$post_types_names = get_post_types( array(), 'names' );
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		if ( in_array( $select_post_type['post_type'], $post_types_names ) ) {
			foreach ( $post_types as $post_type ) {
				if ( in_array( $post_type->name, $hidden_post_types ) ) 
					continue;
				echo '<option value="' . $post_type->name . '" ' . selected( $post_type->name, $select_post_type['post_type'], false ) . '>' . $post_type->labels->name . '</option>';
	        }
        } else {
			echo '<option value="' . $select_post_type['post_type'] . '">' . $select_post_type['post_type'] . ' &#42;</option>';
        }
		echo '</select></td>';

		// get taxonomies for this post type
		echo '<td><select style="width: 100%;" name="levels[' . $row_key . '][post_types][' . $select_post_key . '][taxonomy]">';
		$tax_post_type = $select_post_type['post_type'] ? $select_post_type['post_type'] : 'post';
		$taxes = get_object_taxonomies( $tax_post_type, 'objects' );
		$hidden_taxes = array( 'post_format' );

		echo '<option value="all" ' . selected( 'all', $select_post_type['taxonomy'], false ) . '>All</option>';
		
		foreach( $taxes as $tax ) {

			if ( in_array( $tax->name, $hidden_taxes ) ) {
				continue;
			}

			// create option group for this taxonomy
			echo '<optgroup label="' . $tax->label . '">';

			// create options for this taxonomy
			$terms = get_terms( array(
				'taxonomy' => $tax->name,
				'hide_empty'	=> false
			));

			foreach( $terms as $term ) {
				echo '<option value="' . $term->term_id . '" ' . selected( $term->term_id, $select_post_type['taxonomy'], false ) . '>' . $term->name . '</option>';
			}

			echo '</optgroup>';

		}
		echo '</select></td>';
				
		echo '<td><span class="delete-x delete-post-type-row">&times;</span></td>';
		
		echo '</tr>';
		
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


		$settings = get_leaky_paywall_settings();

		if ( empty( $restriction ) ) {
			$restriction = array(
				'post_type' 	=> '',
				'taxonomy'	=> '',
				'allowed_value' => '0',
			);
		}

		if ( !isset( $restriction['taxonomy'] ) ) {
			$restriction['taxonomy'] = 'all';
		}
    	
		// $return  = '<div class="issuem-leaky-paywall-restriction-row">';
		echo '<tr class="issuem-leaky-paywall-restriction-row">';
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item', 'lp_transaction', 'custom_css' );
		$post_types = get_post_types( array(), 'objects' );
	    // $return .= '<label for="restriction-post-type-' . $row_key . '">' . __( 'Number of', 'leaky-paywall' ) . '</label> ';
		echo '<td><select class="leaky-paywall-restriction-post-type" id="restriction-post-type-' . $row_key . '" name="restrictions[post_types][' . $row_key . '][post_type]">';
		foreach ( $post_types as $post_type ) {
		
			if ( in_array( $post_type->name, $hidden_post_types ) ) {
				continue;
			}

			echo '<option value="' . $post_type->name . '" ' . selected( $post_type->name, $restriction['post_type'], false ) . '>' . $post_type->labels->name . '</option>';
		
		}

		echo '</select></td>';

		// get taxonomies for this post type
		echo '<td><select style="width: 100%;" name="restrictions[post_types][' . $row_key . '][taxonomy]">';
		$tax_post_type = $restriction['post_type'] ? $restriction['post_type'] : 'post';
		$taxes = get_object_taxonomies( $tax_post_type, 'objects' );
		$hidden_taxes = array( 'post_format' );

		echo '<option value="all" ' . selected( 'all', $restriction['taxonomy'], false ) . '>All</option>';
		
		foreach( $taxes as $tax ) {

			if ( in_array( $tax->name, $hidden_taxes ) ) {
				continue;
			}

			// create option group for this taxonomy
			echo '<optgroup label="' . $tax->label . '">';

			// create options for this taxonomy
			$terms = get_terms( array(
				'taxonomy' => $tax->name,
				'hide_empty'	=> false
			));

			foreach( $terms as $term ) {
				echo '<option value="' . $term->term_id . '" ' . selected( $term->term_id, $restriction['taxonomy'], false ) . '>' . $term->name . '</option>';
			}

			echo '</optgroup>';

		}
		echo '</select></td>';

		echo '<td>';

		if ( 'on' == $settings['enable_combined_restrictions'] ) {

			echo '<p class="allowed-number-helper-text" style="color: #555; font-size: 12px;">Using combined restrictions.</p>';
			echo '<input style="display: none;" id="restriction-allowed-' . $row_key . '" type="number" class="small-text restriction-allowed-number-setting" name="restrictions[post_types][' . $row_key . '][allowed_value]" value="' . $restriction['allowed_value'] . '" />';
		} else {
			echo '<p class="allowed-number-helper-text" style="color: #555; font-size: 12px; display: none;">Using combined restrictions.</p>';
			echo '<input id="restriction-allowed-' . $row_key . '" type="number" class="small-text restriction-allowed-number-setting" name="restrictions[post_types][' . $row_key . '][allowed_value]" value="' . $restriction['allowed_value'] . '" />';
		}

		echo '</td>';

		echo '<td><span class="delete-x delete-restriction-row">&times;</span></td>';
		
		echo '</tr>';
		
	}
	
}

add_action( 'wp_ajax_leaky-paywall-get-restriction-row-post-type-taxonomies', 'leaky_paywall_get_restriction_row_post_type_taxonomies' );

/**
 * Get the taxonomies for the selected post type in a restriction setting row
 *
 * @since 4.7.5
 */
function leaky_paywall_get_restriction_row_post_type_taxonomies() {

	$post_type = $_REQUEST['post_type'];

	$taxes = get_object_taxonomies( $post_type, 'objects' );
	$hidden_taxes = array( 'post_format' );

	 ob_start(); ?>
    
    	<select style="width: 100%;">
    		<option value="all">All</option>
    		
    	<?php 
    		foreach( $taxes as $tax ) {

				if ( in_array( $tax->name, $hidden_taxes ) ) {
					continue;
				}

				// create option group for this taxonomy
				echo '<optgroup label="' . $tax->label . '">';

				// create options for this taxonomy
				$terms = get_terms( array(
					'taxonomy' => $tax->name,
					'hide_empty'	=> false
				));

				foreach( $terms as $term ) {
					echo '<option value="' . $term->term_id . '">' . $term->name . '</option>';
				}

				echo '</optgroup>';

			}

    	?>

    	</select>
    
    <?php  $content = ob_get_contents();
	ob_end_clean();

	wp_send_json($content);
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
			$results .= '<h2 class="subscription-options-title">' . __( 'Subscription Options', 'leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_subscription_options_header', '' );
			
			if ( !empty( $settings['levels'] ) ) {
			
				$results .= apply_filters( 'leaky_paywall_before_subscription_options', '' );
				
				$results .= '<div class="leaky_paywall_subscription_options">';
				foreach( apply_filters( 'leaky_paywall_subscription_levels', $settings['levels'] ) as $level_id => $level ) {
					
					if ( !empty( $level['deleted'] ) )
						continue;

					if ( isset( $level['hide_subscribe_card'] ) && 'on' == $level['hide_subscribe_card'] ) {
						continue;
					}
					
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
					$results .= '<h3>' . apply_filters( 'leaky_paywall_subscription_option_title', stripslashes( $level['label'] ) ) . '</h3>';
					
					$results .= '<div class="leaky_paywall_subscription_allowed_content">';

					if ( !empty( $level['post_types'] && !$level['description'] ) ) {
						foreach( $level['post_types'] as $post_type ) {

							if ( isset( $post_type['taxonomy'] ) ) {

								$term = get_term_by( 'term_taxonomy_id', $post_type['taxonomy'] );

								if ( is_object( $term ) ) {
									$name = $term->name;
								} else {
									$name = '';
								}

								$post_type_obj = get_post_type_object( $post_type['post_type'] );
								if ( !empty( $post_type_obj ) ) {
									if ( 0 <= $post_type['allowed_value'] ) {
										$has_allowed_value = true;

										if ( $post_type['allowed_value'] == 1 ) {
											$plural = '';
										} else {
											$plural = 's';
										}

										$allowed_content .= '<p>'  . sprintf( __( 'Access %s %s %s*', 'leaky-paywall' ), $post_type['allowed_value'], $name, $post_type_obj->labels->singular_name . $plural ) .  '</p>';
									} else {
										$allowed_content .= '<p>' . sprintf( __( 'Unlimited %s %s', 'leaky-paywall' ), $name, $post_type_obj->labels->name ) . '</p>';
									}
								}

							} else {

								/* @todo: We may need to change the site ID during this process, some sites may have different post types enabled */
								$post_type_obj = get_post_type_object( $post_type['post_type'] );
								if ( !empty( $post_type_obj ) ) {
									if ( 0 <= $post_type['allowed_value'] ) {
										$has_allowed_value = true;

										if ( $post_type['allowed_value'] == 1 ) {
											$plural = '';
										} else {
											$plural = 's';
										}

										$allowed_content .= '<p>'  . sprintf( __( 'Access %s %s*', 'leaky-paywall' ), $post_type['allowed_value'], $post_type_obj->labels->singular_name . $plural ) .  '</p>';
									} else {
										$allowed_content .= '<p>' . sprintf( __( 'Unlimited %s', 'leaky-paywall' ), $post_type_obj->labels->name ) . '</p>';
									}
								}

							}

						}
					} else {
						$allowed_content = stripslashes( $level['description'] );
					}
					$results .= apply_filters( 'leaky_paywall_subscription_options_allowed_content', $allowed_content, $level_id, $level );
					$results .= '</div>';
					
					$subscription_price = '';
					
					$subscription_price .= '<div class="leaky_paywall_subscription_price">';
					$subscription_price .= '<p>';
					if ( !empty( $level['price'] ) ) {
						if ( !empty( $level['recurring'] ) && 'on' === $level['recurring'] && apply_filters( 'leaky_paywall_subscription_options_price_recurring_on', true, $current_level ) ) {
							$subscription_price .= '<strong>' . leaky_paywall_get_level_display_price( $level ) . ' ' . leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) . ' ' . __( '(recurring)', 'leaky-paywall' ) . '</strong>';
							$subscription_price .= apply_filters( 'leaky_paywall_before_subscription_options_recurring_price', '' );
						} else {
							$subscription_price .= '<strong>' . sprintf( __( '%s %s', 'leaky-paywall' ), leaky_paywall_get_level_display_price( $level ), leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
							$subscription_price .= apply_filters( 'leaky_paywall_before_subscription_options_non_recurring_price', '' );
						}
						
						if ( !empty( $level['trial_period'] ) ) {
							$subscription_price .= '<span class="leaky-paywall-trial-period">' . sprintf( __( 'Free for the first %s day(s)', 'leaky-paywall' ), $level['trial_period'] ) . '</span>';
						}
					} else {
						$subscription_price .= '<strong>' . __( 'Free', 'leaky-paywall' ) . '</strong>';
					}

					
					$subscription_price .= '</p>';
					$subscription_price .= '</div>';

					$results .= apply_filters( 'leaky_paywall_subscription_options_subscription_price', $subscription_price, $level_id, $level );
					
					
					$subscription_action = '';
					$subscription_action .= '<div class="leaky_paywall_subscription_payment_options">';

					//Don't show payment options if the users is currently subscribed to this level
					if ( in_array( $level_id, $current_level_ids ) ) {
						
						$subscription_action .= '<div class="leaky_paywall_subscription_current_level"><span>';
						$subscription_action .= __( 'Your Current Subscription', 'leaky-paywall' );
						$subscription_action .= '</span></div>';

					} 

					$subscription_action .= apply_filters( 'leaky_paywall_subscription_options_payment_options', $payment_options, $level, $level_id );
					$subscription_action .= '</div>';

					$results .= apply_filters( 'leaky_paywall_subscription_options_subscription_action', $subscription_action, $level_id, $current_level_ids, $payment_options );
					
					$results .= '</div>';
				
				}

				$results .= apply_filters( 'leaky_paywall_subscription_options_after_last_subscription_option', '' );
				
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
		return '<a href="' . wp_lostpassword_url() . '">' . __( 'Lost Password?', 'leaky-paywall' ) . '</a>';

	}

}

if ( !function_exists( 'leaky_paywall_payment_gateways' ) ) {
	
	function leaky_paywall_payment_gateways() {
		$gateways = array(
			'manual' 			=> __( 'Manual', 'leaky-paywall' ),
			'stripe' 			=> __( 'Stripe', 'leaky-paywall' ),
			'paypal_standard' 	=> __( 'PayPal Standard', 'leaky-paywall' ),
			'free_registration' => __( 'Free Registration', 'leaky-paywall' ),
		);
		return apply_filters( 'leaky_paywall_subscriber_payment_gateways', $gateways );
	}

}

if ( !function_exists( 'leaky_paywall_human_readable_interval' ) ) {
	function leaky_paywall_human_readable_interval( $interval_count, $interval ) {
		
		if ( 0 >= $interval_count )
			return __( 'for life', 'leaky-paywall' );
	
		if ( 1 < $interval_count )
			$interval .= 's';

		switch ( $interval ) {
			case 'day':
				$interval_str = __( 'day', 'leaky-paywall' );
			break;
			case 'days':
				$interval_str = __( 'days', 'leaky-paywall' );
			break;
			case 'week':
				$interval_str = __( 'week', 'leaky-paywall' );
			break;
			case 'weeks':
				$interval_str = __( 'weeks', 'leaky-paywall' );
			break;
			case 'month':
				$interval_str = __( 'month', 'leaky-paywall' );
			break;
			case 'months':
				$interval_str = __( 'months', 'leaky-paywall' );
			break;
			case 'year':
				$interval_str = __( 'year', 'leaky-paywall' );
			break;
			case 'years':
				$interval_str = __( 'years', 'leaky-paywall' );
			break;
			default:
				$interval_str = $interval;
			break;
		}
		
		if ( 1 == $interval_count )
			return __( 'every', 'leaky-paywall' ) . ' ' . $interval_str;
		else
			return __( 'every', 'leaky-paywall' ) . ' ' . $interval_count . ' ' . $interval_str;
		
	}
}

if ( !function_exists( 'leaky_paywall_email_subscription_status' ) ) {

    function leaky_paywall_email_subscription_status( $user_id, $status = 'new', $args = '' ) {

    	// if the args come through as a WP User object, then the user already exists in the system and we don't know their password
        if ( !empty( $args ) && is_array( $args ) ) {
            $password = $args['password'];
        } else {
	        $password = '';
        }

        $settings = get_leaky_paywall_settings();

        $mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

        $user_info = get_userdata( $user_id );
        $message = '';
        $admin_message = '';
        $headers = array();

        $admin_emails = array();
        $admin_emails = get_option( 'admin_email' );

        $site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
        $from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : $site_name;
        $from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

        $headers[]  = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>";
        $headers[] = "Reply-To: " . $from_email;
        $headers[] = "Content-Type: text/html; charset=UTF-8";

        do_action( 'leaky_paywall_before_email_status', $user_id, $status );

        switch ( $status ) {

            case 'new':
            case 'update':
				
				$message = stripslashes( apply_filters( 'leaky_paywall_new_email_message', $settings['new_email_body'], $user_id ) );
				$subject = stripslashes( apply_filters( 'leaky_paywall_new_email_subject', $settings['new_email_subject'], $user_id ) ); 

                $filtered_subject = leaky_paywall_filter_email_tags( $subject, $user_id, $user_info->display_name, $password );
                $filtered_message = leaky_paywall_filter_email_tags( $message, $user_id, $user_info->display_name, $password );

                $filtered_message = wpautop( make_clickable( $filtered_message ) );

				if ( 'traditional' === $settings['login_method'] && 'off' === $settings['new_subscriber_email']  ) {
                    wp_mail( $user_info->user_email, $filtered_subject, $filtered_message , $headers );
				}

				if ( 'off' === $settings['new_subscriber_admin_email'] ) {
					// new user subscribe admin email

					$level_id = get_user_meta( $user_info->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
					$level_name = stripcslashes( $settings['levels'][$level_id]['label'] );

					$admin_raw_message = '<p>A new user has signed up on ' . $site_name . '.</p>
					<h3>Subscriber details</h3>
					<ul>
					<li><strong>Subscription:</strong> ' . $level_name . '</li>';

					if ( $user_info->first_name ) {
						$admin_raw_message .= '<li><strong>Name:</strong> ' . $user_info->first_name . ' ' . $user_info->last_name . '</li>';
					}

					$admin_raw_message .= '<li><strong>Email:</strong> ' . $user_info->user_email . '</li>
					</ul>
					';

					$admin_message = apply_filters( 'leaky_paywall_new_subscriber_admin_email', $admin_raw_message, $user_info );

					wp_mail( $admin_emails, sprintf( __( 'New subscription on %s', 'leaky-paywall' ), $site_name ), $admin_message, $headers );           
				}

            break;

            case 'renewal_reminder':

            	$message = stripslashes( apply_filters( 'leaky_paywall_renewal_reminder_email_message', $settings['renewal_reminder_email_body'], $user_id ) );

                $filtered_subject = leaky_paywall_filter_email_tags( $settings['renewal_reminder_email_subject'], $user_id, $user_info->display_name, $password );
                $filtered_message = leaky_paywall_filter_email_tags( $message, $user_id, $user_info->display_name, $password );

                $filtered_message = wpautop( make_clickable( $filtered_message ) );

				if ( 'traditional' === $settings['login_method']  ) {
                    wp_mail( $user_info->user_email, $filtered_subject, $filtered_message , $headers );
				}

            break;

            default:
            break;
            
        }

    }

}


// Register cron job on plugin activation.
function leaky_paywall_process_renewal_reminder_schedule(){
	$timestamp = wp_next_scheduled('leaky_paywall_process_renewal_reminder');

	if($timestamp == false){
		wp_schedule_event(time(), 'daily', 'leaky_paywall_process_renewal_reminder');
	}
}
add_action( 'admin_init', 'leaky_paywall_process_renewal_reminder_schedule' );


// Remove our renewal reminder scheduled event if Leaky Paywall is deactivated
function leaky_paywall_process_renewal_reminder_deactivation() {
	wp_clear_scheduled_hook('leaky_paywall_process_renewal_reminder');
}
register_deactivation_hook(__FILE__, 'leaky_paywall_process_renewal_reminder_deactivation');


add_action('leaky_paywall_process_renewal_reminder', 'leaky_paywall_maybe_send_renewal_reminder');

/**
 * Process renewal reminder email for each Leaky Paywall subscriber
 *
 * @since 4.9.3
 */
function leaky_paywall_maybe_send_renewal_reminder() {
	
	global $blog_id;

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	if ( 'on' === $settings['renewal_reminder_email'] ) {
		return;
	}

	// do not send an email if the body of the email is empty
	if ( !$settings['renewal_reminder_email_body'] ) {
		return; 
	}

	leaky_paywall_log( current_time('Y-m-d'), 'process renewal reminder' );

	$days_before = (int) $settings['renewal_reminder_days_before'];

	$args = array(
		'number' => -1
	);

	$users = leaky_paywall_subscriber_query( $args, $blog_id );

	if ( empty( $users ) ) {
		return;
	}

	foreach( $users as $user ) {

		$user_id = $user->ID;
		$expiration = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true);
   		$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
   		
   		// do not send renewal reminders to users with recurring plans
   		if ( !empty($plan) ) {
   			continue;
   		}
		
		// user does not have an expiration date sent, so we can't do the calculations needed
		if ( empty( $expiration ) || '0000-00-00 00:00:00' === $expiration ) {
			continue;
		}

		// if expiration is the past, continue
		if ( strtotime($expiration) < current_time('timestamp')) {
			continue;
		}
		
		$date_differ = leaky_paywall_date_difference( $expiration, date('Y-m-d H:i:s') );
		$already_emailed = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_renewal_emailed' . $site, true ) ? true : false;

		if ( ($date_differ <= $days_before) && $already_emailed == false) {	
			leaky_paywall_email_subscription_status( $user_id, 'renewal_reminder' );
			update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_renewal_emailed' . $site, current_time('timestamp') );
		}
		
	}

	
}


if ( !function_exists( 'leaky_paywall_date_difference' ) ) {

	/**
	 * Calculate the differce between two date values
	 *
	 * @since 4.9.3
	 */
	function leaky_paywall_date_difference( $date_1, $date_2, $differenceFormat = '%a' ) {

	    $datetime1 = date_create($date_1); // expiration 
	    $datetime2 = date_create($date_2); // today
	    
	    $interval = date_diff($datetime1, $datetime2);
	    
	    return $interval->format($differenceFormat);   
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
			'AED' => array( 'symbol' => '&#1583;.&#1573;', 'label' => __( 'UAE dirham', 'leaky-paywall' ), 'country' => __( 'UAE', 'leaky-paywall' ) ),
			'AFN' => array( 'symbol' => 'Afs', 'label' => __( 'Afghan afghani', 'leaky-paywall' ), 'country' => __( 'Afghanistan', 'leaky-paywall' ) ),
			'ALL' => array( 'symbol' => 'L', 'label' => __( 'Albanian lek', 'leaky-paywall' ), 'country' => __( 'Albania', 'leaky-paywall' ) ),
			'AMD' => array( 'symbol' => 'AMD', 'label' => __( 'Armenian dram', 'leaky-paywall' ), 'country' => __( 'Armenia', 'leaky-paywall' ) ),
			'ANG' => array( 'symbol' => 'NA&#402;', 'label' => __( 'Netherlands Antillean gulden', 'leaky-paywall' ), 'country' => __( 'Netherlands', 'leaky-paywall' ) ),
			'AOA' => array( 'symbol' => 'Kz', 'label' => __( 'Angolan kwanza', 'leaky-paywall' ), 'country' => __( 'Angolia', 'leaky-paywall' ) ),
			'ARS' => array( 'symbol' => '$', 'label' => __( 'Argentine peso', 'leaky-paywall' ), 'country' => __( 'Argentina', 'leaky-paywall' ) ),
			'AUD' => array( 'symbol' => '$', 'label' => __( 'Australian dollar', 'leaky-paywall' ), 'country' => __( 'Australia', 'leaky-paywall' ) ),
			'AWG' => array( 'symbol' => '&#402;', 'label' => __( 'Aruban florin', 'leaky-paywall' ), 'country' => __( 'Aruba', 'leaky-paywall' ) ),
			'AZN' => array( 'symbol' => 'AZN', 'label' => __( 'Azerbaijani manat', 'leaky-paywall' ), 'country' => __( 'Azerbaij', 'leaky-paywall' ) ),
			'BAM' => array( 'symbol' => 'KM', 'label' => __( 'Bosnia and Herzegovina konvertibilna marka', 'leaky-paywall' ), 'country' => __( 'Bosnia', 'leaky-paywall' ) ),
			'BBD' => array( 'symbol' => 'Bds$', 'label' => __( 'Barbadian dollar', 'leaky-paywall' ), 'country' => __( 'Barbadian', 'leaky-paywall' ) ),
			'BDT' => array( 'symbol' => '&#2547;', 'label' => __( 'Bangladeshi taka', 'leaky-paywall' ), 'country' => __( 'Bangladesh', 'leaky-paywall' ) ),
			'BGN' => array( 'symbol' => 'BGN', 'label' => __( 'Bulgarian lev', 'leaky-paywall' ), 'country' => __( 'Bulgaria', 'leaky-paywall' ) ),
			'BIF' => array( 'symbol' => 'FBu', 'label' => __( 'Burundi franc', 'leaky-paywall' ), 'country' => __( 'Burundi', 'leaky-paywall' ) ),
			'BMD' => array( 'symbol' => 'BD$', 'label' => __( 'Bermudian dollar', 'leaky-paywall' ), 'country' => __( 'Bermuda', 'leaky-paywall' ) ),
			'BND' => array( 'symbol' => 'B$', 'label' => __( 'Brunei dollar', 'leaky-paywall' ), 'country' => __( 'Brunei', 'leaky-paywall' ) ),
			'BOB' => array( 'symbol' => 'Bs.', 'label' => __( 'Bolivian boliviano', 'leaky-paywall' ), 'country' => __( 'Bolivia', 'leaky-paywall' ) ),
			'BRL' => array( 'symbol' => 'R$', 'label' => __( 'Brazilian real', 'leaky-paywall' ), 'country' => __( 'Brazil', 'leaky-paywall' ) ),
			'BSD' => array( 'symbol' => 'B$', 'label' => __( 'Bahamian dollar', 'leaky-paywall' ), 'country' => __( 'Bahamas', 'leaky-paywall' ) ),
			'BWP' => array( 'symbol' => 'P', 'label' => __( 'Botswana pula', 'leaky-paywall' ), 'country' => __( 'Botswana', 'leaky-paywall' ) ),
			'BZD' => array( 'symbol' => 'BZ$', 'label' => __( 'Belize dollar', 'leaky-paywall' ), 'country' => __( 'Belize', 'leaky-paywall' ) ),
			'CAD' => array( 'symbol' => '$', 'label' => __( 'Canadian dollar', 'leaky-paywall' ), 'country' => __( 'Canada', 'leaky-paywall' ) ),
			'CDF' => array( 'symbol' => 'F', 'label' => __( 'Congolese franc', 'leaky-paywall' ), 'country' => __( 'Congo', 'leaky-paywall' ) ),
			'CHF' => array( 'symbol' => 'CHF', 'label' => __( 'Swiss franc', 'leaky-paywall' ), 'country' => __( 'Switzerland', 'leaky-paywall' ) ),
			'CLP' => array( 'symbol' => '$', 'label' => __( 'Chilean peso', 'leaky-paywall' ), 'country' => __( 'Chili', 'leaky-paywall' ) ),
			'CNY' => array( 'symbol' => '&#165;', 'label' => __( 'Chinese Yuan Renminbi', 'leaky-paywall' ),'country' => __( 'Chinese Yuan', 'leaky-paywall' ) ),
			'COP' => array( 'symbol' => 'Col$', 'label' => __( 'Colombian peso', 'leaky-paywall' ),'country' => __( 'Colombia', 'leaky-paywall' ) ),
			'CRC' => array( 'symbol' => '&#8353;', 'label' => __( 'Costa Rican colon', 'leaky-paywall' ),'country' => __( 'Costa Rica', 'leaky-paywall' ) ),
			'CVE' => array( 'symbol' => 'Esc', 'label' => __( 'Cape Verdean escudo', 'leaky-paywall' ),'country' => __( 'Cape Verde', 'leaky-paywall' ) ),
			'CZK' => array( 'symbol' => 'K&#269;', 'label' => __( 'Czech koruna', 'leaky-paywall' ),'country' => __( 'Czech', 'leaky-paywall' ) ),
			'DJF' => array( 'symbol' => 'Fdj', 'label' => __( 'Djiboutian franc', 'leaky-paywall' ),'country' => __( 'Djibouti', 'leaky-paywall' ) ),
			'DKK' => array( 'symbol' => 'kr', 'label' => __( 'Danish krone', 'leaky-paywall' ),'country' => __( 'Danish', 'leaky-paywall' ) ),
			'DOP' => array( 'symbol' => 'RD$', 'label' => __( 'Dominican peso', 'leaky-paywall' ),'country' => __( 'Dominican Republic', 'leaky-paywall' ) ),
			'DZD' => array( 'symbol' => '&#1583;.&#1580;', 'label' => __( 'Algerian dinar', 'leaky-paywall' ),'country' => __( 'Algeria', 'leaky-paywall' ) ),
			'EEK' => array( 'symbol' => 'KR', 'label' => __( 'Estonian kroon', 'leaky-paywall' ),'country' => __( 'Estonia', 'leaky-paywall' ) ),
			'EGP' => array( 'symbol' => '&#163;', 'label' => __( 'Egyptian pound', 'leaky-paywall' ),'country' => __( 'Egypt', 'leaky-paywall' ) ),
			'ETB' => array( 'symbol' => 'Br', 'label' => __( 'Ethiopian birr', 'leaky-paywall' ),'country' => __( 'Ethiopia', 'leaky-paywall' ) ),
			'EUR' => array( 'symbol' => '&#8364;', 'label' => __( 'European Euro', 'leaky-paywall' ), 'country' => __( 'Euro', 'leaky-paywall' ) ),
			'FJD' => array( 'symbol' => 'FJ$', 'label' => __( 'Fijian dollar', 'leaky-paywall' ), 'country' => __( 'Fiji', 'leaky-paywall' ) ),
			'FKP' => array( 'symbol' => '&#163;', 'label' => __( 'Falkland Islands pound', 'leaky-paywall' ), 'country' => __( 'Falkland Islands', 'leaky-paywall' ) ),
			'GBP' => array( 'symbol' => '&#163;', 'label' => __( 'British pound', 'leaky-paywall' ), 'country' => __( 'Great Britian', 'leaky-paywall' ) ),
			'GEL' => array( 'symbol' => 'GEL', 'label' => __( 'Georgian lari', 'leaky-paywall' ), 'country' => __( 'Georgia', 'leaky-paywall' ) ),
			'GIP' => array( 'symbol' => '&#163;', 'label' => __( 'Gibraltar pound', 'leaky-paywall' ), 'country' => __( 'Gibraltar', 'leaky-paywall' ) ),
			'GMD' => array( 'symbol' => 'D', 'label' => __( 'Gambian dalasi', 'leaky-paywall' ), 'country' => __( 'Gambia', 'leaky-paywall' ) ),
			'GNF' => array( 'symbol' => 'FG', 'label' => __( 'Guinean franc', 'leaky-paywall' ), 'country' => __( 'Guinea', 'leaky-paywall' ) ),
			'GTQ' => array( 'symbol' => 'Q', 'label' => __( 'Guatemalan quetzal', 'leaky-paywall' ), 'country' => __( 'Guatemala', 'leaky-paywall' ) ),
			'GYD' => array( 'symbol' => 'GY$', 'label' => __( 'Guyanese dollar', 'leaky-paywall' ), 'country' => __( 'Guyanese', 'leaky-paywall' ) ),
			'HKD' => array( 'symbol' => 'HK$', 'label' => __( 'Hong Kong dollar', 'leaky-paywall' ), 'country' => __( 'Hong Kong', 'leaky-paywall' ) ),
			'HNL' => array( 'symbol' => 'L', 'label' => __( 'Honduran lempira', 'leaky-paywall' ), 'country' => __( 'Honduras', 'leaky-paywall' ) ),
			'HRK' => array( 'symbol' => 'kn', 'label' => __( 'Croatian kuna', 'leaky-paywall' ), 'country' => __( 'Croatia', 'leaky-paywall' ) ),
			'HTG' => array( 'symbol' => 'G', 'label' => __( 'Haitian gourde', 'leaky-paywall' ), 'country' => __( 'Haiti', 'leaky-paywall' ) ),
			'HUF' => array( 'symbol' => 'Ft', 'label' => __( 'Hungarian forint', 'leaky-paywall' ), 'country' => __( 'Hungary', 'leaky-paywall' ) ),
			'IDR' => array( 'symbol' => 'Rp', 'label' => __( 'Indonesian rupiah', 'leaky-paywall' ), 'country' => __( 'Idonesia', 'leaky-paywall' ) ),
			'ILS' => array( 'symbol' => '&#8362;', 'label' => __( 'Israeli new sheqel', 'leaky-paywall' ), 'country' => __( 'Israel', 'leaky-paywall' ) ),
			'INR' => array( 'symbol' => '&#8377;', 'label' => __( 'Indian rupee', 'leaky-paywall' ), 'country' => __( 'India', 'leaky-paywall' ) ),
			'ISK' => array( 'symbol' => 'kr', 'label' => __( 'Icelandic króna', 'leaky-paywall' ), 'country' => __( 'Iceland', 'leaky-paywall' ) ),
			'JMD' => array( 'symbol' => 'J$', 'label' => __( 'Jamaican dollar', 'leaky-paywall' ), 'country' => __( 'Jamaica', 'leaky-paywall' ) ),
			'JPY' => array( 'symbol' => '&#165;', 'label' => __( 'Japanese yen', 'leaky-paywall' ), 'country' => __( 'Japan', 'leaky-paywall' ) ),
			'KES' => array( 'symbol' => 'KSh', 'label' => __( 'Kenyan shilling', 'leaky-paywall' ), 'country' => __( 'Kenya', 'leaky-paywall' ) ),
			'KGS' => array( 'symbol' => '&#1089;&#1086;&#1084;', 'label' => __( 'Kyrgyzstani som', 'leaky-paywall' ), 'country' => __( 'Kyrgyzstan', 'leaky-paywall' ) ),
			'KHR' => array( 'symbol' => '&#6107;', 'label' => __( 'Cambodian riel', 'leaky-paywall' ), 'country' => __( 'Cambodia', 'leaky-paywall' ) ),
			'KMF' => array( 'symbol' => 'KMF', 'label' => __( 'Comorian franc', 'leaky-paywall' ), 'country' => __( 'Comorian', 'leaky-paywall' ) ),
			'KRW' => array( 'symbol' => 'W', 'label' => __( 'South Korean won', 'leaky-paywall' ), 'country' => __( 'South Korea', 'leaky-paywall' ) ),
			'KYD' => array( 'symbol' => 'KY$', 'label' => __( 'Cayman Islands dollar', 'leaky-paywall' ), 'country' => __( 'Cayman Islands', 'leaky-paywall' ) ),
			'KZT' => array( 'symbol' => 'T', 'label' => __( 'Kazakhstani tenge', 'leaky-paywall' ), 'country' => __( 'Kazakhstan', 'leaky-paywall' ) ),
			'LAK' => array( 'symbol' => 'KN', 'label' => __( 'Lao kip', 'leaky-paywall' ), 'country' => __( 'Loa', 'leaky-paywall' ) ),
			'LBP' => array( 'symbol' => '&#163;', 'label' => __( 'Lebanese lira', 'leaky-paywall' ), 'country' => __( 'Lebanese', 'leaky-paywall' ) ),
			'LKR' => array( 'symbol' => 'Rs', 'label' => __( 'Sri Lankan rupee', 'leaky-paywall' ), 'country' => __( 'Sri Lanka', 'leaky-paywall' ) ),
			'LRD' => array( 'symbol' => 'L$', 'label' => __( 'Liberian dollar', 'leaky-paywall' ), 'country' => __( 'Liberia', 'leaky-paywall' ) ),
			'LSL' => array( 'symbol' => 'M', 'label' => __( 'Lesotho loti', 'leaky-paywall' ), 'country' => __( 'Lesotho', 'leaky-paywall' ) ),
			'LTL' => array( 'symbol' => 'Lt', 'label' => __( 'Lithuanian litas', 'leaky-paywall' ), 'country' => __( 'Lithuania', 'leaky-paywall' ) ),
			'LVL' => array( 'symbol' => 'Ls', 'label' => __( 'Latvian lats', 'leaky-paywall' ), 'country' => __( 'Latvia', 'leaky-paywall' ) ),
			'MAD' => array( 'symbol' => 'MAD', 'label' => __( 'Moroccan dirham', 'leaky-paywall' ), 'country' => __( 'Morocco', 'leaky-paywall' ) ),
			'MDL' => array( 'symbol' => 'MDL', 'label' => __( 'Moldovan leu', 'leaky-paywall' ), 'country' => __( 'Moldova', 'leaky-paywall' ) ),
			'MGA' => array( 'symbol' => 'FMG', 'label' => __( 'Malagasy ariary', 'leaky-paywall' ), 'country' => __( 'Malagasy', 'leaky-paywall' ) ),
			'MKD' => array( 'symbol' => 'MKD', 'label' => __( 'Macedonian denar', 'leaky-paywall' ), 'country' => __( 'Macedonia', 'leaky-paywall' ) ),
			'MNT' => array( 'symbol' => '&#8366;', 'label' => __( 'Mongolian tugrik', 'leaky-paywall' ), 'country' => __( 'Mongolia', 'leaky-paywall' ) ),
			'MOP' => array( 'symbol' => 'P', 'label' => __( 'Macanese pataca', 'leaky-paywall' ), 'country' => __( 'Macanese', 'leaky-paywall' ) ),
			'MRO' => array( 'symbol' => 'UM', 'label' => __( 'Mauritanian ouguiya', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MUR' => array( 'symbol' => 'Rs', 'label' => __( 'Mauritian rupee', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MVR' => array( 'symbol' => 'Rf', 'label' => __( 'Maldivian rufiyaa', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MWK' => array( 'symbol' => 'MK', 'label' => __( 'Malawian kwacha', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MXN' => array( 'symbol' => '$', 'label' => __( 'Mexican peso', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MYR' => array( 'symbol' => 'RM', 'label' => __( 'Malaysian ringgit', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'MZN' => array( 'symbol' => 'MT', 'label' => __( 'Mozambique Metical', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NAD' => array( 'symbol' => 'N$', 'label' => __( 'Namibian dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NGN' => array( 'symbol' => '&#8358;', 'label' => __( 'Nigerian naira', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NIO' => array( 'symbol' => 'C$', 'label' => __( 'Nicaraguan Córdoba', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NOK' => array( 'symbol' => 'kr', 'label' => __( 'Norwegian krone', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NPR' => array( 'symbol' => 'NRs', 'label' => __( 'Nepalese rupee', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'NZD' => array( 'symbol' => 'NZ$', 'label' => __( 'New Zealand dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PAB' => array( 'symbol' => 'B./', 'label' => __( 'Panamanian balboa', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PEN' => array( 'symbol' => 'S/.', 'label' => __( 'Peruvian nuevo sol', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PGK' => array( 'symbol' => 'K', 'label' => __( 'Papua New Guinean kina', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PHP' => array( 'symbol' => '&#8369;', 'label' => __( 'Philippine peso', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PKR' => array( 'symbol' => 'Rs.', 'label' => __( 'Pakistani rupee', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PLN' => array( 'symbol' => 'z&#322;', 'label' => __( 'Polish zloty', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'PYG' => array( 'symbol' => '&#8370;', 'label' => __( 'Paraguayan guarani', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'QAR' => array( 'symbol' => 'QR', 'label' => __( 'Qatari riyal', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'RON' => array( 'symbol' => 'L', 'label' => __( 'Romanian leu', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'RSD' => array( 'symbol' => 'din.', 'label' => __( 'Serbian dinar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'RUB' => array( 'symbol' => 'R', 'label' => __( 'Russian ruble', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'RWF' => array( 'symbol' => 'R&#8355;', 'label' => __( 'Rwandan Franc' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SAR' => array( 'symbol' => 'SR', 'label' => __( 'Saudi riyal', 'leaky-paywall' ) ),
			'SBD' => array( 'symbol' => 'SI$', 'label' => __( 'Solomon Islands dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SCR' => array( 'symbol' => 'SR', 'label' => __( 'Seychellois rupee', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SEK' => array( 'symbol' => 'kr', 'label' => __( 'Swedish krona', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SGD' => array( 'symbol' => 'S$', 'label' => __( 'Singapore dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SHP' => array( 'symbol' => '&#163;', 'label' => __( 'Saint Helena pound', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SLL' => array( 'symbol' => 'Le', 'label' => __( 'Sierra Leonean leone', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SOS' => array( 'symbol' => 'Sh.', 'label' => __( 'Somali shilling', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SRD' => array( 'symbol' => '$', 'label' => __( 'Surinamese dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'STD' => array( 'symbol' => 'STD', 'label' => __( 'São Tomé and Príncipe Dobra', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SVC' => array( 'symbol' => '$', 'label' => __( 'El Salvador Colon', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'SZL' => array( 'symbol' => 'E', 'label' => __( 'Swazi lilangeni', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'THB' => array( 'symbol' => '&#3647;', 'label' => __( 'Thai baht', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TJS' => array( 'symbol' => 'TJS', 'label' => __( 'Tajikistani somoni', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TOP' => array( 'symbol' => 'T$', 'label' => __( "Tonga Pa'anga", 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TRY' => array( 'symbol' => 'TRY', 'label' => __( 'Turkish new lira', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TTD' => array( 'symbol' => 'TT$', 'label' => __( 'Trinidad and Tobago dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TWD' => array( 'symbol' => 'NT$', 'label' => __( 'New Taiwan dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'TZS' => array( 'symbol' => 'TZS', 'label' => __( 'Tanzanian shilling', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'UAH' => array( 'symbol' => 'UAH', 'label' => __( 'Ukrainian hryvnia', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'UGX' => array( 'symbol' => 'USh', 'label' => __( 'Ugandan shilling', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'USD' => array( 'symbol' => '$', 'label' => __( 'United States dollar', 'leaky-paywall' ), 'country' => __( 'United States', 'leaky-paywall' ) ),
			'UYU' => array( 'symbol' => '$U', 'label' => __( 'Uruguayan peso', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'UZS' => array( 'symbol' => 'UZS', 'label' => __( 'Uzbekistani som', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'VND' => array( 'symbol' => '&#8363;', 'label' => __( 'Vietnamese dong', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'VUV' => array( 'symbol' => 'VT', 'label' => __( 'Vanuatu vatu', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'WST' => array( 'symbol' => 'WS$', 'label' => __( 'Samoan tala', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'XAF' => array( 'symbol' => 'CFA', 'label' => __( 'Central African CFA franc', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'XCD' => array( 'symbol' => 'EC$', 'label' => __( 'East Caribbean dollar', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'XOF' => array( 'symbol' => 'CFA', 'label' => __( 'West African CFA franc', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'XPF' => array( 'symbol' => 'F', 'label' => __( 'CFP franc', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'YER' => array( 'symbol' => 'YER', 'label' => __( 'Yemeni rial', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'ZAR' => array( 'symbol' => 'R', 'label' => __( 'South African rand', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
			'ZMW' => array( 'symbol' => 'ZK', 'label' => __( 'Zambian kwacha', 'leaky-paywall' ), 'country' => __( '', 'leaky-paywall' ) ),
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


function leaky_paywall_old_form_value( $input, $echo = true ) {

	$value = '';

	if ( isset( $_POST[$input] ) && $_POST[$input] ) {
		$value = esc_attr( $_POST[$input] );
	}

	if ( $echo ) {
		echo $value;
	} else {
		return $value;
	}

}


/**
 * Get the current site's selected currency symbol
 *
 * @since 4.5.2
 * @return string
 */
if ( ! function_exists( 'leaky_paywall_get_current_currency_symbol' ) ) {

	function leaky_paywall_get_current_currency_symbol() {

		$settings = get_leaky_paywall_settings();
		$currency = leaky_paywall_get_currency();
		$currencies = leaky_paywall_supported_currencies();

		return $currencies[$currency]['symbol'];

	}
}

/**
 * Check if the current registration has an amount equal to zero (and thus free)
 *
 * @since 4.7.1
 * @return bool
 */
if ( ! function_exists( 'leaky_paywall_is_free_registration' ) ) {

	function leaky_paywall_is_free_registration( $meta ) {

		if ( $meta['price'] > 0 ) {
			$is_free = false;
		} else {
			$is_free = true;
		}

		return apply_filters( 'leaky_paywall_is_free_registration', $is_free, $meta );

	}
}

/**
 * Determine if the current subscriber can view the content
 *
 * @since 4.7.1
 * @return bool
 */
function leaky_paywall_subscriber_can_view() {

	$restricted = new Leaky_Paywall_Restrictions();
	return $restricted->subscriber_can_view();

}

/**
 * Log Leaky Paywall events and data to a file
 *
 * @since 4.7.1
 * @return bool
 */
function leaky_paywall_log( $data, $event ) {

	$str = '';

	if ( is_object( $data ) ) {
		$data = json_decode(json_encode($data), true);
	}

	if ( is_array( $data ) ) {
		foreach( $data as $key => $value ) {
			$str .= $key . ': ' . $value . ',';
		}
	} else {
		$str = $data;
	}

	$file = plugin_dir_path( __FILE__ ) . '/lp-log.txt'; 
	$open = fopen( $file, "a" ); 
	$write = fputs( $open, $event . " - " . current_time('mysql') . "\r\n" . $str . "\r\n" ); 
	fclose( $open );

}

add_action( 'show_user_profile', 'leaky_paywall_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'leaky_paywall_show_extra_profile_fields' );

function leaky_paywall_show_extra_profile_fields( $user ) { 

	$settings = get_leaky_paywall_settings();
	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
	if ( $level_id ) {
		$level = get_leaky_paywall_subscription_level( $level_id );
	}
	$description = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_description' . $site, true );
	$gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
	$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
	$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
	$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
	$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );

	if ( !$level_id ) {
		return;
	}
	?>

	<h3>Leaky Paywall</h3>

	<table class="form-table">	

		<tr>
			<th><label for="twitter">Level ID</label></th>

			<td>
				<?php echo esc_attr( $level_id ); ?>
				
			</td>
		</tr>

		<tr>
			<th><label for="twitter">Level Description</label></th>

			<td>
				<?php echo $level['label']; ?>
				
			</td>
		</tr>

		<tr>
			<th><label for="twitter">Payment Gateway</label></th>

			<td>
				<?php echo esc_attr( $gateway ); ?>
				
			</td>
		</tr>

		<tr>
			<th><label for="twitter">Payment Status</label></th>

			<td>
				<?php echo esc_attr( $status ); ?>
				
			</td>
		</tr>

		<tr>
			<th><label for="twitter">Expires</label></th>

			<td>
				<?php echo esc_attr( $expires ); ?>
				
			</td>
		</tr>

		<?php if ( $plan ) {
			?>
			<tr>
				<th><label for="twitter">Plan</label></th>

				<td>
					<?php echo esc_attr( $plan ); ?>
					
				</td>
			</tr>
			<?php 
		} ?>
		

		<?php if ( $subscriber_id ) {
			?>
			<tr>
				<th><label for="twitter">Subscriber ID</label></th>

				<td>
					<?php echo esc_attr( $subscriber_id ); ?>
					
				</td>
			</tr>
			<?php 
		} ?>
		

	</table>
<?php }

/**
 * Add settings link to plugin table for Leaky Paywall
 *
 * @since 4.10.4
 * @param  $links default plugin links
 * @return  array $links
 */
function leaky_paywall_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=issuem-leaky-paywall">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
  	return $links;
}
add_filter( 'plugin_action_links_' . LEAKY_PAYWALL_BASENAME, 'leaky_paywall_plugin_add_settings_link' );

/**
 * Plugin row meta links for add ons
 *
 * @since 4.10.4
 * @param array $input already defined meta links
 * @param string $file plugin file path and name being processed
 * @return array $input
 */
function leaky_paywall_plugin_row_meta( $input, $file ) {
	
	if ( $file != 'leaky-paywall/leaky-paywall.php' ) {
		return $input;
	}

	$lp_link = esc_url( add_query_arg( array(
			'utm_source'   => 'plugins-page',
			'utm_medium'   => 'plugin-row',
			'utm_campaign' => 'admin',
		), 'https://zeen101.com/for-developers/leakypaywall/leaky-paywall-add-ons/' )
	);

	$links = array(
		'<a href="' . $lp_link . '">' . esc_html__( 'Add-Ons', 'leaky-paywall' ) . '</a>',
	);

	$input = array_merge( $input, $links );

	return $input;
}
add_filter( 'plugin_row_meta', 'leaky_paywall_plugin_row_meta', 10, 2 );

function is_leaky_paywall_recurring() {

	$settings = get_leaky_paywall_settings();
	$recurring = false;

	if ( !isset( $settings['post_4106'] ) ) {
		$recurring = true;
	}

	if ( is_plugin_active( 'leaky-paywall-recurring-payments/leaky-paywall-recurring-payments.php' ) ) {
		$recurring = true;
	}

	return $recurring;
	
}


add_action( 'init', 'leaky_paywall_maybe_delete_user' );

function leaky_paywall_maybe_delete_user() {

	if ( !isset( $_POST['leaky-paywall-delete-account-nonce'] ) ) {
		return;
	}
				
	if ( !wp_verify_nonce( $_POST['leaky-paywall-delete-account-nonce'], 'leaky-paywall-delete-account' ) ) {
		return;
	}

	$settings = get_leaky_paywall_settings();

	require_once(ABSPATH.'wp-admin/includes/user.php' );

	$user = wp_get_current_user();

	if ( in_array('subscriber', $user->roles ) ) {

		wp_delete_user( $user->ID );

		do_action( 'leaky_paywall_after_user_deleted', $user );

		$admin_message = '';
        $headers = array();

        $admin_emails = array();
        $admin_emails = get_option( 'admin_email' );

        $site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
        $from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : $site_name;
        $from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

        $headers[]  = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>";
        $headers[] = "Reply-To: " . $from_email;
        $headers[] = "Content-Type: text/html; charset=UTF-8";

		$admin_message = '<p>The user ' . $user->user_email . ' has deleted their account.</p>';

		wp_mail( $admin_emails, sprintf( __( 'User Account Deleted on %s', 'leaky-paywall' ), $site_name ), $admin_message, $headers );

		wp_die('<p>Your account has been deleted. Your access and information has been removed.</p><p><a href="' . home_url() . '">Continue</a></p>', 'Account Deleted' );
		
	}

	wp_die('<p>Your user role cannot be deleted from the My Account page. Please contact a site administrator.</p><p><a href="' . home_url() . '">Continue</a></p>', 'Account Deleted' );

}

function leaky_paywall_get_level_display_price( $level ) {

	$settings = get_leaky_paywall_settings();

	$currency_position = $settings['leaky_paywall_currency_position'];
	$thousand_separator = $settings['leaky_paywall_thousand_separator'];
	$decimal_separator = $settings['leaky_paywall_decimal_separator'];
	$decimal_number = empty( $settings['leaky_paywall_decimal_number'] ) ? '0' : $settings['leaky_paywall_decimal_number'];
	$currency_symbol = leaky_paywall_get_current_currency_symbol();

	$price = $level['price'];
	$broken_price = explode('.', $price);

	$before_decimal = $broken_price[0];
	$after_decimal = substr( isset( $broken_price[1] ) ? $broken_price[1] : '', 0, $decimal_number );

	if ( !$after_decimal && $decimal_number == 2 ) {
		$after_decimal = '00';
	}

	if ( $price > 0 ) {

		$decimal = $after_decimal ? $decimal_separator : '';
		$formatted_number = number_format( $before_decimal, 0, '', $thousand_separator ) . $decimal . $after_decimal;

		switch ( $currency_position ) {
			case 'left':
				$display_price = $currency_symbol . $formatted_number;
				break;
			case 'right':
				$display_price = $formatted_number . $currency_symbol;
				break;
			case 'left_space':
				$display_price = $currency_symbol . ' ' . $formatted_number;
				break;
			case 'right_space':
				$display_price =$formatted_number . ' ' .  $currency_symbol;
				break;
			default:
				$display_price = $currency_symbol . $formatted_number;
				break;
		}
		
	} else {
		$display_price = 'Free';
	}

	return apply_filters( 'leaky_paywall_display_price', $display_price, $level );
}


/**
 * Replace language-specific characters by ASCII-equivalents.
 * @param string $s
 * @return string
 */
function leaky_paywall_normalize_chars($s) {
    $replace = array(
        'ъ'=>'-', 'Ь'=>'-', 'Ъ'=>'-', 'ь'=>'-',
        'Ă'=>'A', 'Ą'=>'A', 'À'=>'A', 'Ã'=>'A', 'Á'=>'A', 'Æ'=>'A', 'Â'=>'A', 'Å'=>'A', 'Ä'=>'Ae',
        'Þ'=>'B',
        'Ć'=>'C', 'ץ'=>'C', 'Ç'=>'C',
        'È'=>'E', 'Ę'=>'E', 'É'=>'E', 'Ë'=>'E', 'Ê'=>'E',
        'Ğ'=>'G',
        'İ'=>'I', 'Ï'=>'I', 'Î'=>'I', 'Í'=>'I', 'Ì'=>'I',
        'Ł'=>'L',
        'Ñ'=>'N', 'Ń'=>'N',
        'Ø'=>'O', 'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe',
        'Ş'=>'S', 'Ś'=>'S', 'Ș'=>'S', 'Š'=>'S',
        'Ț'=>'T',
        'Ù'=>'U', 'Û'=>'U', 'Ú'=>'U', 'Ü'=>'Ue',
        'Ý'=>'Y',
        'Ź'=>'Z', 'Ž'=>'Z', 'Ż'=>'Z',
        'â'=>'a', 'ǎ'=>'a', 'ą'=>'a', 'á'=>'a', 'ă'=>'a', 'ã'=>'a', 'Ǎ'=>'a', 'а'=>'a', 'А'=>'a', 'å'=>'a', 'à'=>'a', 'א'=>'a', 'Ǻ'=>'a', 'Ā'=>'a', 'ǻ'=>'a', 'ā'=>'a', 'ä'=>'ae', 'æ'=>'ae', 'Ǽ'=>'ae', 'ǽ'=>'ae',
        'б'=>'b', 'ב'=>'b', 'Б'=>'b', 'þ'=>'b',
        'ĉ'=>'c', 'Ĉ'=>'c', 'Ċ'=>'c', 'ć'=>'c', 'ç'=>'c', 'ц'=>'c', 'צ'=>'c', 'ċ'=>'c', 'Ц'=>'c', 'Č'=>'c', 'č'=>'c', 'Ч'=>'ch', 'ч'=>'ch',
        'ד'=>'d', 'ď'=>'d', 'Đ'=>'d', 'Ď'=>'d', 'đ'=>'d', 'д'=>'d', 'Д'=>'D', 'ð'=>'d',
        'є'=>'e', 'ע'=>'e', 'е'=>'e', 'Е'=>'e', 'Ə'=>'e', 'ę'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'Ē'=>'e', 'Ė'=>'e', 'ė'=>'e', 'ě'=>'e', 'Ě'=>'e', 'Є'=>'e', 'Ĕ'=>'e', 'ê'=>'e', 'ə'=>'e', 'è'=>'e', 'ë'=>'e', 'é'=>'e',
        'ф'=>'f', 'ƒ'=>'f', 'Ф'=>'f',
        'ġ'=>'g', 'Ģ'=>'g', 'Ġ'=>'g', 'Ĝ'=>'g', 'Г'=>'g', 'г'=>'g', 'ĝ'=>'g', 'ğ'=>'g', 'ג'=>'g', 'Ґ'=>'g', 'ґ'=>'g', 'ģ'=>'g',
        'ח'=>'h', 'ħ'=>'h', 'Х'=>'h', 'Ħ'=>'h', 'Ĥ'=>'h', 'ĥ'=>'h', 'х'=>'h', 'ה'=>'h',
        'î'=>'i', 'ï'=>'i', 'í'=>'i', 'ì'=>'i', 'į'=>'i', 'ĭ'=>'i', 'ı'=>'i', 'Ĭ'=>'i', 'И'=>'i', 'ĩ'=>'i', 'ǐ'=>'i', 'Ĩ'=>'i', 'Ǐ'=>'i', 'и'=>'i', 'Į'=>'i', 'י'=>'i', 'Ї'=>'i', 'Ī'=>'i', 'І'=>'i', 'ї'=>'i', 'і'=>'i', 'ī'=>'i', 'ĳ'=>'ij', 'Ĳ'=>'ij',
        'й'=>'j', 'Й'=>'j', 'Ĵ'=>'j', 'ĵ'=>'j', 'я'=>'ja', 'Я'=>'ja', 'Э'=>'je', 'э'=>'je', 'ё'=>'jo', 'Ё'=>'jo', 'ю'=>'ju', 'Ю'=>'ju',
        'ĸ'=>'k', 'כ'=>'k', 'Ķ'=>'k', 'К'=>'k', 'к'=>'k', 'ķ'=>'k', 'ך'=>'k',
        'Ŀ'=>'l', 'ŀ'=>'l', 'Л'=>'l', 'ł'=>'l', 'ļ'=>'l', 'ĺ'=>'l', 'Ĺ'=>'l', 'Ļ'=>'l', 'л'=>'l', 'Ľ'=>'l', 'ľ'=>'l', 'ל'=>'l',
        'מ'=>'m', 'М'=>'m', 'ם'=>'m', 'м'=>'m',
        'ñ'=>'n', 'н'=>'n', 'Ņ'=>'n', 'ן'=>'n', 'ŋ'=>'n', 'נ'=>'n', 'Н'=>'n', 'ń'=>'n', 'Ŋ'=>'n', 'ņ'=>'n', 'ŉ'=>'n', 'Ň'=>'n', 'ň'=>'n',
        'о'=>'o', 'О'=>'o', 'ő'=>'o', 'õ'=>'o', 'ô'=>'o', 'Ő'=>'o', 'ŏ'=>'o', 'Ŏ'=>'o', 'Ō'=>'o', 'ō'=>'o', 'ø'=>'o', 'ǿ'=>'o', 'ǒ'=>'o', 'ò'=>'o', 'Ǿ'=>'o', 'Ǒ'=>'o', 'ơ'=>'o', 'ó'=>'o', 'Ơ'=>'o', 'œ'=>'oe', 'Œ'=>'oe', 'ö'=>'oe',
        'פ'=>'p', 'ף'=>'p', 'п'=>'p', 'П'=>'p',
        'ק'=>'q',
        'ŕ'=>'r', 'ř'=>'r', 'Ř'=>'r', 'ŗ'=>'r', 'Ŗ'=>'r', 'ר'=>'r', 'Ŕ'=>'r', 'Р'=>'r', 'р'=>'r',
        'ș'=>'s', 'с'=>'s', 'Ŝ'=>'s', 'š'=>'s', 'ś'=>'s', 'ס'=>'s', 'ş'=>'s', 'С'=>'s', 'ŝ'=>'s', 'Щ'=>'sch', 'щ'=>'sch', 'ш'=>'sh', 'Ш'=>'sh', 'ß'=>'ss',
        'т'=>'t', 'ט'=>'t', 'ŧ'=>'t', 'ת'=>'t', 'ť'=>'t', 'ţ'=>'t', 'Ţ'=>'t', 'Т'=>'t', 'ț'=>'t', 'Ŧ'=>'t', 'Ť'=>'t', '™'=>'tm',
        'ū'=>'u', 'у'=>'u', 'Ũ'=>'u', 'ũ'=>'u', 'Ư'=>'u', 'ư'=>'u', 'Ū'=>'u', 'Ǔ'=>'u', 'ų'=>'u', 'Ų'=>'u', 'ŭ'=>'u', 'Ŭ'=>'u', 'Ů'=>'u', 'ů'=>'u', 'ű'=>'u', 'Ű'=>'u', 'Ǖ'=>'u', 'ǔ'=>'u', 'Ǜ'=>'u', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'У'=>'u', 'ǚ'=>'u', 'ǜ'=>'u', 'Ǚ'=>'u', 'Ǘ'=>'u', 'ǖ'=>'u', 'ǘ'=>'u', 'ü'=>'ue',
        'в'=>'v', 'ו'=>'v', 'В'=>'v',
        'ש'=>'w', 'ŵ'=>'w', 'Ŵ'=>'w',
        'ы'=>'y', 'ŷ'=>'y', 'ý'=>'y', 'ÿ'=>'y', 'Ÿ'=>'y', 'Ŷ'=>'y',
        'Ы'=>'y', 'ž'=>'z', 'З'=>'z', 'з'=>'z', 'ź'=>'z', 'ז'=>'z', 'ż'=>'z', 'ſ'=>'z', 'Ж'=>'zh', 'ж'=>'zh'
    );
    return strtr($s, $replace);
}

// add leaky paywall links to admin toolbar
add_action('admin_bar_menu', 'leaky_paywall_add_toolbar_items', 100);
	
function leaky_paywall_add_toolbar_items( $admin_bar ){

	if ( !current_user_can( 'edit_user' ) ) {
		return;
	}

    $admin_bar->add_menu( array(
        'id'    => 'leaky-paywall-toolbar',
        'title' => 'Leaky Paywall',
        'href'  => admin_url() . 'admin.php?page=issuem-leaky-paywall',
        'meta'  => array(
            'title' => __('Leaky Paywall'),            
        ),
    ));
    $admin_bar->add_menu( array(
        'id'    => 'leaky-paywall-toolbar-settings',
        'parent' => 'leaky-paywall-toolbar',
        'title' => 'Settings',
        'href'  => admin_url() . 'admin.php?page=issuem-leaky-paywall',
        'meta'  => array(
            'title' => __('Settings'),
            'target' => '',
            'class' => 'my_menu_item_class'
        ),
    ));
    $admin_bar->add_menu( array(
        'id'    => 'leaky-paywall-toolbar-subscribers',
        'parent' => 'leaky-paywall-toolbar',
        'title' => 'Subscribers',
        'href'  => admin_url() . 'admin.php?page=leaky-paywall-subscribers',
        'meta'  => array(
            'title' => __('Subscribers'),
            'target' => '',
            'class' => 'my_menu_item_class'
        ),
    ));

    $admin_bar->add_menu( array(
        'id'    => 'leaky-paywall-toolbar-transactions',
        'parent' => 'leaky-paywall-toolbar',
        'title' => 'Transactions',
        'href'  => admin_url() . 'edit.php?post_type=lp_transaction',
        'meta'  => array(
            'title' => __('Transactions'),
            'target' => '',
            'class' => 'my_menu_item_class'
        ),
    ));

    $admin_bar->add_menu( array(
        'id'    => 'leaky-paywall-toolbar-add-ons',
        'parent' => 'leaky-paywall-toolbar',
        'title' => 'Add-Ons',
        'href'  => admin_url() . 'admin.php?page=leaky-paywall-addons',
        'meta'  => array(
            'title' => __('Add-Ons'),
            'target' => '',
            'class' => 'my_menu_item_class'
        ),
    ));
}

add_action( 'admin_notices', 'leaky_paywall_display_rate_us_notice', 20 );

function leaky_paywall_display_rate_us_notice() {

	$notice_id = 'lp_rate_us_feedback';

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// delete_user_meta( get_current_user_id(), $notice_id );

	$current_user_has_viewed = get_user_meta( get_current_user_id(), $notice_id, true );

	if ( 'dashboard' !== get_current_screen()->id || $current_user_has_viewed ) {
		return;
	}

	$site = leaky_paywall_get_current_site();

	$args = array(
		'number' => 6,
		'meta_query'	=> array(
			array(
				'key'     => '_issuem_leaky_paywall_live_level_id' . $site,
				'compare' => 'EXISTS',
			),
		)
	);

	$wp_user_search = new WP_User_Query( $args );
	$total_live_subscribers = count( $wp_user_search->get_results() );

	if ( 5 >= $total_live_subscribers ) {
		return;
	}


	$dismiss_url = add_query_arg( [
		'action' => 'leaky_paywall_set_admin_notice_viewed',
		'notice_id' => esc_attr( $notice_id ),
	], admin_url() );

	?>
	<div class="notice updated is-dismissible leaky-paywall-message leaky-paywall-message-dismissed" data-notice_id="<?php echo esc_attr( $notice_id ); ?>">
		<div class="leaky-paywall-message-inner">
			
			<div class="leaky-paywall-message-content">
				<p><strong><?php echo __( 'Congrats!', 'leaky-paywall' ); ?></strong> <?php _e( 'You have more than 5 subscribers with <strong>Leaky Paywall</strong>. If you can, please help us by leaving a five star review on WordPress.org.', 'leaky-paywall' ); ?></p>
				<p class="leaky-paywall-message-actions">
					<a href="https://wordpress.org/support/plugin/leaky-paywall/reviews/?filter=5/#new-post" target="_blank" class="button button-primary"><?php _e( 'Leave a Review', 'leaky-paywall' ); ?></a>
					<a href="<?php echo esc_url_raw( $dismiss_url ); ?>" class="button leaky-paywall-button-notice-dismiss"><?php _e( 'Hide', 'leaky-paywall' ); ?></a>
				</p>
			</div>
		</div>
	</div>
	<?php 
}

add_action( 'admin_init', 'leaky_paywall_update_admin_notice_viewed' );

function leaky_paywall_update_admin_notice_viewed() {

	if ( !isset( $_GET['action'] ) ) {
		return;
	}

	if ( $_GET['action'] != 'leaky_paywall_set_admin_notice_viewed' ) {
		return;
	}

	update_user_meta( get_current_user_id(), sanitize_text_field( $_GET['notice_id'] ), true );

}