<?php
/**
 * All helper functions used with Leaky Paywall
 *
 * @package Leaky Paywall
 * @since 1.0.0
 */

if ( ! function_exists( 'get_leaky_paywall_settings' ) ) {

	/**
	 * Helper function to get Leaky Paywall settings for current site
	 *
	 * @since 1.0.0
	 *
	 * @return mixed Value set for the Leaky paywall settings.
	 */
	function get_leaky_paywall_settings() {
		global $leaky_paywall;
		return $leaky_paywall->get_settings();
	}
}

if ( ! function_exists( 'update_leaky_paywall_settings' ) ) {

	/**
	 * Helper function to save zeen101's Leaky Paywall settings for current site
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The settings array.
	 * @return mixed Value set for the issuem options.
	 */
	function update_leaky_paywall_settings( $settings ) {
		global $leaky_paywall;
		return $leaky_paywall->update_settings( $settings );
	}
}

if ( ! function_exists( 'is_multisite_premium' ) ) {
	/**
	 * Check if multisite
	 */
	function is_multisite_premium() {
		if ( is_multisite() ) {
			return true;
		}
		return false;
	}
}



if ( ! function_exists( 'is_level_deleted' ) ) {
	/**
	 * Check if a level is deleted
	 *
	 * @param integer $level_id The level id.
	 * @return bool
	 */
	function is_level_deleted( $level_id ) {

		$level = get_leaky_paywall_subscription_level( $level_id );

		if ( isset( $level['deleted'] ) && 1 === $level['deleted'] ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'get_leaky_paywall_subscribers_site_id_by_subscriber_id' ) ) {
	/**
	 * Get subscriber's site id by their subscriber id
	 *
	 * @param string $subscriber_id The subscriber's subscriber id.
	 * @param string $mode The payment mode.
	 * @return string The site id
	 */
	function get_leaky_paywall_subscribers_site_id_by_subscriber_id( $subscriber_id, $mode = false ) {
		$site_id = '';
		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode     = leaky_paywall_get_current_mode();
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
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( preg_match( '/_issuem_leaky_paywall_' . $mode . '_subscriber_id(_(.+))/', $result, $matches ) ) {
						return $matches[2]; // should be the site ID that matches for this subscriber_id.
					}
				}
			}
		}

		return $site_id;
	}
}

if ( ! function_exists( 'get_leaky_paywall_subscribers_site_id_by_subscriber_email' ) ) {
	/**
	 * Get subscriber's site id by their email
	 *
	 * @param string $subscriber_email The subscriber's email.
	 * @param string $mode The payment mode.
	 * @return string The site id
	 */
	function get_leaky_paywall_subscribers_site_id_by_subscriber_email( $subscriber_email, $mode = false ) {
		$site_id = '';
		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode     = 'off' === $settings['test_mode'] ? 'live' : 'test';
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
			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					if ( preg_match( '/_issuem_leaky_paywall_' . $mode . '_subscriber_email(_(.+))/', $result, $matches ) ) {
						return $matches[2]; // should be the site ID that matches for this subscriber_id.
					}
				}
			}
		}

		return $site_id;
	}
}

if ( ! function_exists( 'get_leaky_paywall_subscriber_by_subscriber_id' ) ) {

	/**
	 * Get a subscriber by subscriber id
	 *
	 * @param string  $subscriber_id The subscriber id.
	 * @param string  $mode The payment mode.
	 * @param integer $blog_id The blog id.
	 * @return object The subscriber
	 */
	function get_leaky_paywall_subscriber_by_subscriber_id( $subscriber_id, $mode = false, $blog_id = false ) {
		$site = '';

		if ( empty( $mode ) ) {
			$settings = get_leaky_paywall_settings();
			$mode     = 'off' === $settings['test_mode'] ? 'live' : 'test';
		}

		if ( is_multisite_premium() ) {
			if ( empty( $blog_id ) ) {
				$blog_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $subscriber_id );
				if ( $blog_id ) {
					$site = '_' . $blog_id;
				}
			} else {
				$site = '_' . $blog_id;
			}
		}

		$args  = array(
			'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
			'meta_value' => $subscriber_id,
		);
		$users = get_users( $args );

		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				return $user;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'get_leaky_paywall_subscriber_by_subscriber_email' ) ) {

	function get_leaky_paywall_subscriber_by_subscriber_email( $subscriber_email, $mode = false, $blog_id = false ) {
		$site = '';

		if ( is_email( $subscriber_email ) ) {
			if ( empty( $mode ) ) {
				$settings = get_leaky_paywall_settings();
				$mode     = 'off' === $settings['test_mode'] ? 'live' : 'test';
			}

			if ( is_multisite_premium() ) {
				if ( empty( $blog_id ) ) {
					$blog_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( $subscriber_email );
					if ( $blog_id ) {
						$site = '_' . $blog_id;
					}
				} else {
					$site = '_' . $blog_id;
				}
			}

			$args  = array(
				'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_subscriber_email' . $site,
				'meta_value' => $subscriber_email,
			);
			$users = get_users( $args );

			if ( ! empty( $users ) ) {
				foreach ( $users as $user ) {
					return $user;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'add_leaky_paywall_login_hash' ) ) {

	/**
	 * Adds unique hash to login table for user's login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $email address of user "logging" in.
	 * @param string $hash of user "logging" in.
	 * @return mixed $wpdb insert ID or false
	 */
	function add_leaky_paywall_login_hash( $email, $hash ) {

		$expiration = apply_filters( 'leaky_paywall_login_link_expiration', 60 * 60 ); // 1 hour.
		set_transient( '_lpl_' . $hash, $email, $expiration );
	}
}

if ( ! function_exists( 'is_leaky_paywall_login_hash_unique' ) ) {

	/**
	 * Verifies hash is valid for login link
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in.
	 * @return mixed $wpdb var or false
	 */
	function is_leaky_paywall_login_hash_unique( $hash ) {

		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { // verify we get a valid 32 character md5 hash.

			return ! ( false !== get_transient( '_lpl_' . $hash ) );
		}

		return false;
	}
}

if ( ! function_exists( 'verify_leaky_paywall_login_hash' ) ) {

	/**
	 * Verifies hash is valid length and hasn't expired
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in.
	 * @return mixed $wpdb var or false
	 */
	function verify_leaky_paywall_login_hash( $hash ) {

		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { // verify we get a valid 32 character md5 hash.

			return (bool) get_transient( '_lpl_' . $hash );
		}

		return false;
	}
}

if ( ! function_exists( 'get_leaky_paywall_email_from_login_hash' ) ) {

	/**
	 * Gets logging in user's email address from login link's hash
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash of user "logging" in.
	 * @return string email from $wpdb or false if invalid hash or expired link
	 */
	function get_leaky_paywall_email_from_login_hash( $hash ) {

		if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { // verify we get a valid 32 character md5 hash.
			return get_transient( '_lpl_' . $hash );
		}

		return false;
	}
}


if ( ! function_exists( 'leaky_paywall_user_has_access' ) ) {

	/**
	 * Determine if a user has access based on their expiration date and their payment status.
	 *
	 * @since 4.9.3
	 *
	 * @param object $user object from WordPress database.
	 * @return bool true if the user has access or false if they have either expired or their payment status is set to deactived
	 */
	function leaky_paywall_user_has_access( $user = null ) {

		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		$settings  = get_leaky_paywall_settings();
		$mode      = leaky_paywall_get_current_mode();
		$site      = leaky_paywall_get_current_site();
		$unexpired = false;

		$expires        = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
		$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );

		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
			$unexpired = true;
		} else {
			if ( strtotime( $expires ) > time() ) {
				$unexpired = true;
			}
		}

		if ( $unexpired && $payment_status && 'deactivated' !== $payment_status ) {
			$has_access = true;
		} else {
			$has_access = false;
		}

		if ( ! is_user_logged_in() ) {
			$has_access = false;
		}

		if ( leaky_paywall_user_can_bypass_paywall_by_role( $user ) ) {
			$has_access = true;
		}

		return apply_filters( 'leaky_paywall_user_has_access', $has_access, $user );
	}
}

/**
 * Determine if a user has access based on their user role.
 *
 * @since 4.14.5
 *
 * @param object $user User object from WordPress database.
 * @return bool true if the user has access based on their role or false they do not
 */
function leaky_paywall_user_can_bypass_paywall_by_role( $user ) {

	$settings   = get_leaky_paywall_settings();
	$roles      = (array) $user->roles;
	$can_bypass = false;

	foreach ( $roles as $role ) {

		if ( in_array( $role, $settings['bypass_paywall_restrictions'], true ) ) {
			$can_bypass = true;
		}
	}

	return $can_bypass;
}

/**
 * Get the current Leaky Paywall mode setting.  Lives in the Payments tab
 *
 * @return string live or test
 */
function leaky_paywall_get_current_mode() {
	$settings = get_leaky_paywall_settings();
	$mode     = 'off' === $settings['test_mode'] ? 'live' : 'test';

	return apply_filters( 'leaky_paywall_current_mode', $mode );
}

/**
 * Get the current Leaky Paywall site id, if multisite
 *
 * @return string the id of the site
 */
function leaky_paywall_get_current_site() {
	if ( is_multisite_premium() && ! is_main_site() ) {
		$site = '_' . get_current_blog_id();
	} else {
		$site = '';
	}

	return apply_filters( 'leaky_paywall_current_site', $site );
}


if ( ! function_exists( 'leaky_paywall_get_currency' ) ) {

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

if ( ! function_exists( 'leaky_paywall_has_user_paid' ) ) {

	/**
	 * Verified if user has paid through Stripe
	 *
	 * @since 1.0.0
	 *
	 * @param string  $email address of user "logged" in.
	 * @param integer $blog_id The blog id.
	 * @return mixed Expiration date or subscriptions status or false if not paid
	 */
	function leaky_paywall_has_user_paid( $email = false, $blog_id = null ) {

		$settings = get_leaky_paywall_settings();
		$paid     = false;
		$canceled = false;
		$expired  = false;
		$sites    = array( '' ); // Empty String for non-Multisite, so we cycle through "sites" one time with no $site set.
		$mode     = 'off' === $settings['test_mode'] ? 'live' : 'test';

		if ( empty( $email ) ) {
			$user = wp_get_current_user();
			if ( 0 === $user->ID ) { // no user.
				return false;
			}
		} else {
			if ( is_email( $email ) ) {
				$user = get_user_by( 'email', $email );

				if ( ! $user ) { // no user found with that email address.
					return false;
				}
			} else {
				return false;
			}
		}

		if ( is_multisite_premium() ) {
			if ( is_null( $blog_id ) ) {
				global $blog_id;
				if ( ! is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			} elseif ( is_int( $blog_id ) ) {
				$sites = array( '_' . $blog_id );
			} elseif ( empty( $blog_id ) ) {
				$sites = array( '' );
			} else {
				$sites = array( $blog_id );
			}
		}

		foreach ( $sites as $site ) {

			$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			$expires         = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
			$payment_status  = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
			$plan            = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );

			if ( 'stripe' !== $payment_gateway ) {

				if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
					if ( ! empty( $plan ) && 'active' === $payment_status ) {
						return 'subscription';
					}
				}

				switch ( $payment_status ) {

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
					/* phpcs:ignore to cover any spelling. */
					case 'cancelled':
					case 'canceled':
						if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
							$expired = true;
						} else {
							$canceled = true;
						}
					case 'reversed':
					case 'buyer_complaint':
					case 'denied':
					case 'expired':
					case 'failed':
					case 'voided':
					case 'deactivated':
						break;
				}
			} else {

				// check with Stripe to make sure the user has an active subscription.

				$stripe = leaky_paywall_initialize_stripe_api();

				try {
					if ( empty( $subscriber_id ) ) {
						switch ( $payment_status ) {
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
							case 'denied':
							case 'expired':
							case 'failed':
							case 'voided':
							case 'deactivated':
								break;
						}
					} else {
						$cu = $stripe->customers->retrieve( $subscriber_id );

						if ( ! empty( $cu ) ) {
							if ( ! empty( $cu->deleted ) && true === $cu->deleted ) {
								$canceled = true;
							}
						}

						if ( ! empty( $plan ) ) {
							if ( isset( $cu->subscriptions ) ) {
								$subscriptions = $cu->subscriptions->all( array( 'limit' => '1' ) );
								foreach ( $subscriptions->data as $subscription ) {
									if ( leaky_paywall_is_valid_stripe_subscription( $subscription ) ) {
										return 'subscription';
									}
								}
							}
						}

						$ch = $stripe->charges->all(
							array(
								'count'    => 1,
								'customer' => $subscriber_id,
							)
						);

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
					/* Translators: %s - error message */
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

if ( ! function_exists( 'leaky_paywall_set_expiration_date' ) ) {

	/**
	 * Set a user's expiration data
	 *
	 * @param  int   $user_id the user id.
	 * @param  array $data    information about the subscription.
	 */
	function leaky_paywall_set_expiration_date( $user_id, $data ) {

		if ( empty( $user_id ) ) {
			return;
		}

		$expires  = '0000-00-00 00:00:00'; // default to never expire.
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		if ( isset( $data['expires'] ) && $data['expires'] ) {
			$expires = $data['expires'];
		} elseif ( ! empty( $data['interval'] ) && isset( $data['interval_count'] ) && 1 <= $data['interval_count'] ) {
			$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . $data['interval_count'] . ' ' . $data['interval'] ) ); // we're generous, give them the whole day!
		}

		if ( 'on' === $settings['add_expiration_dates'] ) {

			$current_expires = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );

			if ( $current_expires ) {
				// if they already have an expiration date and aren't expired, add on to their current expiration date.
				if ( strtotime( $current_expires ) > time() ) {
					$expires = date_i18n( 'Y-m-d 23:59:59', strtotime( $current_expires . ' +' . $data['interval_count'] . ' ' . $data['interval'] ) );
				}
			}
		}

		update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, apply_filters( 'leaky_paywall_set_expiration_date', $expires, $data, $user_id ) );
	}
}

if ( ! function_exists( 'leaky_paywall_new_subscriber' ) ) {

	/**
	 * Adds new subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param deprecated $hash No longer used.
	 * @param string     $email address of user "logged" in.
	 * @param int        $customer_id The customer id.
	 * @param array      $meta_args Arguments passed from type of subscriber.
	 * @param string     $login optional login name to use instead of email address.
	 * @return mixed $wpdb insert ID or false
	 */
	function leaky_paywall_new_subscriber( $hash = 'deprecated', $email, $customer_id, $meta_args, $login = '' ) {

		if ( ! is_email( $email ) ) {
			return false;
		}

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();
		$user     = get_user_by( 'email', $email );

		if ( $user ) {
			// the user already exists.
			// grab the ID for later.
			$user_id  = $user->ID;
			$userdata = get_userdata( $user_id );
		} else {

			// the user doesn't already exist.

			// if they submitted a custom login name, use that.
			if ( isset( $meta_args['login'] ) ) {
				$login = $meta_args['login'];
			}

			// create a new user with their email address as their username.
			// grab the ID for later.
			if ( empty( $login ) ) {
				$parts = explode( '@', $email );
				$login = $parts[0];
			}

			// Avoid collisions.
			$user = get_user_by( 'login', $login );
			while ( $user ) {
				$login = $user->user_login . '_' . substr( uniqid(), 5 );
				$user = get_user_by( 'login', $login );
			}

			if ( isset( $meta_args['password'] ) ) {
				$password = $meta_args['password'];
			} else {
				$password = wp_generate_password();
			}

			$userdata = array(
				'user_login'      => $login,
				'user_email'      => $email,
				'user_pass'       => $password,
				'first_name'      => isset( $meta_args['first_name'] ) ? $meta_args['first_name'] : '',
				'last_name'       => isset( $meta_args['last_name'] ) ? $meta_args['last_name'] : '',
				'display_name'    => isset( $meta_args['first_name'] ) ? $meta_args['first_name'] . ' ' . $meta_args['last_name'] : '',
				'user_registered' => date_i18n( 'Y-m-d H:i:s' ),
			);

			$userdata = apply_filters( 'leaky_paywall_userdata_before_user_create', $userdata );
			$user_id  = wp_insert_user( $userdata );
		}

		if ( empty( $user_id ) ) {
			leaky_paywall_log( $meta_args, 'could not create user' );
			return false;
		} else {
			$logged_userdata = $userdata;
			
			if ( is_array( $logged_userdata ) ) {
				unset( $logged_userdata['user_pass']);
			}
			
			leaky_paywall_log( $logged_userdata, 'leaky paywall - new subscriber created' );
		}

		leaky_paywall_set_expiration_date( $user_id, $meta_args );
		unset( $meta_args['site'] );

		if ( isset( $meta_args['created'] ) && $meta_args['created'] ) {
			$created_date         = strtotime( $meta_args['created'] );
			$meta_args['created'] = gmdate( 'Y-m-d H:i:s', $created_date );
		} else {
			$meta_args['created'] = gmdate( 'Y-m-d H:i:s' );
		}

		// set free level subscribers to active.
		if ( '0' === $meta_args['price'] ) {
			$meta_args['payment_status'] = 'active';
		}

		$meta = apply_filters( 'leaky_paywall_new_subscriber_meta', $meta_args, $email, $customer_id, $meta_args );

		// remove any extra underscores from site variable.
		$site = str_replace( '__', '_', $site );

		foreach ( $meta as $key => $value ) {

			// do not want to store their password as plain text.
			if ( 'confirm_password' === $key || 'password' === $key ) {
				continue;
			}

			update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
		}

		do_action( 'leaky_paywall_new_subscriber', $user_id, $email, $meta, $customer_id, $meta_args, $userdata );

		return $user_id;
	}
}

if ( ! function_exists( 'leaky_paywall_update_subscriber' ) ) {

	/**
	 * Updates an existing subscriber to subscriber table
	 *
	 * @since 1.0.0
	 *
	 * @param deprecated $hash No longer used.
	 * @param string     $email address of user "logged" in.
	 * @param int        $customer_id Customer ID.
	 * @param array      $meta_args Arguments passed from type of subscriber.
	 * @return mixed $wpdb insert ID or false
	 */
	function leaky_paywall_update_subscriber( $hash = 'deprecated', $email, $customer_id, $meta_args ) {

		if ( ! is_email( $email ) ) {
			return false;
		}

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		$expires = '0000-00-00 00:00:00';
		$user    = get_user_by( 'email', $email );

		if ( is_user_logged_in() && ! is_admin() ) {
			// Update the existing user.
			$user_id = get_current_user_id();
		} elseif ( $user ) {
			// the user already exists.
			// grab the ID for later.
			$user_id = $user->ID;
		} else {
			return false; // User does not exist, cannot update.
		}

		$level = get_leaky_paywall_subscription_level( $meta_args['level_id'] );

		// do not update levels if it is a pay per post purchase.
		if ( isset( $level['pay_per_post'] ) ) {
			return $user_id;
		}

    	$current_level_id = get_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

		leaky_paywall_set_expiration_date( $user_id, $meta_args );
		unset( $meta_args['site'] );

		$meta = array(
			'level_id'        => $meta_args['level_id'],
			'subscriber_id'   => $customer_id,
			'price'           => $meta_args['price'],
			'description'     => $meta_args['description'],
			'plan'            => $meta_args['plan'],
			'payment_gateway' => $meta_args['payment_gateway'],
			'payment_status'  => $meta_args['payment_status'],
		);

		$meta = apply_filters( 'leaky_paywall_update_subscriber_meta', $meta, $email, $customer_id, $meta_args );

		do_action( 'leaky_paywall_before_update_subscriber', $user_id, $current_level_id, $meta );

		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
		}

		$user_id = wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $email,
			)
		);

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
	$blog_id  = get_current_blog_id();

	$level_list = array();

	foreach ( $settings['levels'] as $key => $level ) {

		if ( ! empty( $level['deleted'] ) ) {
			continue;
		}

		if ( is_multisite_premium() && ! empty( $level['site'] ) && 'all' !== $level['site'] && $blog_id !== $level['site'] ) {
			continue;
		}

		if ( ! is_numeric( $key ) ) {
			continue;
		}

		$level_list[ $key ] = $level;
	}

	return $level_list;
}


if ( ! function_exists( 'leaky_paywall_translate_payment_gateway_slug_to_name' ) ) {

	/**
	 * Translate a payment gateway slug to a name
	 *
	 * @param string $slug The slug.
	 * @return string The name of the gateway
	 */
	function leaky_paywall_translate_payment_gateway_slug_to_name( $slug ) {

		$return = 'Unknown';

		switch ( $slug ) {

			case 'stripe':
				$return = 'Stripe';
				break;

			case 'paypal_standard':
			case 'paypal-standard':
				$return = 'PayPal';
				break;

			case 'free_registration':
				$return = __( 'Free Registration', 'leaky-paywall' );
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

if ( ! function_exists( 'leaky_paywall_cancellation_confirmation' ) ) {

	/**
	 * Cancels a subscriber from Stripe subscription plan
	 *
	 * @since 1.0.0
	 *
	 * @return string Cancellation form output
	 * @throws Exception Generated during a Stripe call.
	 */
	function leaky_paywall_cancellation_confirmation() {
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();
		$form     = '';

		if ( is_user_logged_in() ) {

			if ( ! empty( $_REQUEST['payment_gateway'] ) ) {
				$payment_gateway = sanitize_text_field( wp_unslash( $_REQUEST['payment_gateway'] ) );
			} else {
				return '<p>' . __( 'No payment gateway defined.', 'leaky-paywall' ) . '</p>';
			}

			if ( ! empty( $_REQUEST['subscriber_id'] ) ) {
				$subscriber_id = sanitize_text_field( wp_unslash( $_REQUEST['subscriber_id'] ) );
			} else {
				return '<p>' . __( 'No subscriber ID defined.', 'leaky-paywall' ) . '</p>';
			}

			if ( isset( $_REQUEST['cancel'] ) && empty( $_REQUEST['cancel'] ) ) {

				$form               = '<h3>' . __( 'Cancel Subscription', 'leaky-paywall' ) . '</h3>';
				$cancel_description = '<p>' . __( 'Cancellations take effect at the end of your billing cycle, and we can’t give partial refunds for unused time in the billing cycle. If you still wish to cancel now, you may proceed, or you can come back later.', 'leaky-paywall' ) . '</p>';

				/* Translators: %s - site name */
				$cancel_description .= '<p>' . sprintf( __( ' Thank you for the time you’ve spent subscribed to %s. We hope you’ll return someday. ', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';

				$form .= apply_filters( 'leaky_paywall_cancel_subscription_description', $cancel_description );

				$form .= '<a href="' . esc_url( add_query_arg( array( 'cancel' => 'confirm' ) ) ) . '">' . __( 'Yes, cancel my subscription!', 'leaky-paywall' ) . '</a> | <a href="' . get_page_link( $settings['page_for_profile'] ) . '">' . __( 'No, get me outta here!', 'leaky-paywall' ) . '</a>';
			} elseif ( ! empty( $_REQUEST['cancel'] ) && 'confirm' === $_REQUEST['cancel'] ) {

				$user = wp_get_current_user();

				if ( 'stripe' === $payment_gateway ) {

					try {

						$stripe = leaky_paywall_initialize_stripe_api();

						$cu = $stripe->customers->retrieve( $subscriber_id );

						if ( ! empty( $cu ) ) {
							if ( isset( $cu->deleted ) && true === $cu->deleted ) {
								throw new Exception( __( 'Unable to find valid Stripe customer ID to unsubscribe. Please contact support', 'leaky-paywall' ) );
							}
						}
					
						if ( null == $cu ) {
							throw new Exception( __( 'No subscriptions found for customer ID. Please contact support', 'leaky-paywall' ) );
						}

						// $subscriptions = $cu->subscriptions->all( array( 'limit' => '1' ) );

						$subscriptions = $stripe->subscriptions->all( array( 
							'customer' => $cu->id,
							'limit' => '1' 
						) );

						if ( ! empty( $subscriptions->data ) ) {
							foreach ( $subscriptions->data as $subscription ) {
								$sub = $stripe->subscriptions->retrieve( $subscription->id );
								$results = $sub->cancel();
							}
						} else {
							// no subscriptions found for stripe customer.
							leaky_paywall_log( 'no subscriptions found', 'leaky paywall canceled stripe subscription for ' . $user->user_email );
						}

						if ( ! empty( $results->status ) && 'canceled' === $results->status ) {
							/* Translators: %s - site name */
							$form .= '<p>' . sprintf( __( 'Your subscription has been successfully canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
							// We are creating plans with the site of '_all', even on single sites.  This is a quick fix but needs to be readdressed.
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, 'Canceled' );
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan_all', 'Canceled' );
							update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );

							do_action( 'leaky_paywall_cancelled_subscriber', $user, 'stripe' );
							leaky_paywall_log( $results, 'leaky paywall canceled stripe subscription for ' . $user->user_email );
						} else {
							$form .= '<p>' . sprintf( __( 'ERROR: An error occured when trying to unsubscribe you from your account, please try again. If you continue to have trouble, please contact us. Thank you.', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
						}

						/* Translators: %s - site name */
						$form .= '<a href="' . get_home_url() . '">' . sprintf( __( 'Return to %s...', 'leaky-paywall' ), $settings['site_name'] ) . '</a>';
					} catch ( \Throwable $th ) {

						/* Translators: %s - error message */
						$form = '<h3>' . sprintf( __( 'Error processing request: %s. Please contact support.', 'leaky-paywall' ), $th->getMessage() ) . '</h3>';
						leaky_paywall_log( $th->getMessage(), 'leaky paywall stripe cancel error - for user ' . $user->user_email );
					}
				} elseif ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {

					$paypal_url   = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
					$paypal_email = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
					$form        .= '<p>' . sprintf( __( 'You must cancel your account through PayPal. Please click this unsubscribe button to complete the cancellation process.', 'leaky-paywall' ), $settings['site_name'] ) . '</p>';
					$form        .= '<p><a href="' . $paypal_url . '?cmd=_subscr-find&alias=' . rawurlencode( $paypal_email ) . '"><img src="' . LEAKY_PAYWALL_URL . 'images/btn_unsubscribe_LG.gif" border="0"></a></p>';
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

if ( ! function_exists( 'create_leaky_paywall_login_hash' ) ) {

	/**
	 * Creates a 32-character hash string
	 *
	 * Generally used to create a unique hash for each subscriber, stored in the database
	 * and used for campaign links.
	 *
	 * @since 1.0.0
	 *
	 * @param string $str String you want to hash.
	 */
	function create_leaky_paywall_login_hash( $str ) {

		if ( defined( 'SECURE_AUTH_SALT' ) ) {
			$salt[] = SECURE_AUTH_SALT;
		}

		if ( defined( 'AUTH_SALT' ) ) {
			$salt[] = AUTH_SALT;
		}

		$salt[] = get_bloginfo( 'name' );
		$salt[] = time();

		$hash = md5( md5( implode( $salt ) ) . md5( $str ) );

		while ( ! is_leaky_paywall_login_hash_unique( $hash ) ) {
			$hash = create_leaky_paywall_login_hash( $hash ); // I did this on purpose...
		}

		return $hash; // doesn't have to be too secure, just want a pretty random and very unique string.

	}
}

if ( ! function_exists( 'leaky_paywall_attempt_login' ) ) {
	/**
	 * Attempt a login
	 *
	 * @param string $login_hash The login hash.
	 */
	function leaky_paywall_attempt_login( $login_hash ) {
		$email = get_leaky_paywall_email_from_login_hash( $login_hash );
		if ( false !== $email ) {
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				delete_transient( '_lpl_' . $login_hash ); // one time use.
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID, true );
			}
		}
	}
}

if ( ! function_exists( 'leaky_paywall_subscriber_restrictions' ) ) {

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
			$restrictions = $settings['restrictions']['post_types']; // defaults.
		} else {
			$restrictions = '';
		}

		if ( is_multisite_premium() ) {
			$restriction_levels = leaky_paywall_subscriber_current_level_ids();
			if ( ! empty( $restriction_levels ) ) {

				$restrictions        = array();
				$merged_restrictions = array();
				foreach ( $restriction_levels as $restriction_level ) {
					if ( ! empty( $settings['levels'][ $restriction_level ]['post_types'] ) ) {
						$restrictions = array_merge( $restrictions, $settings['levels'][ $restriction_level ]['post_types'] );
					}
				}
				$merged_restrictions = array();
				foreach ( $restrictions as $key => $restriction ) {
					if ( empty( $merged_restrictions ) ) {
						$merged_restrictions[ $key ] = $restriction;
						continue;
					} else {
						$post_type_found = false;
						foreach ( $merged_restrictions as $tmp_key => $tmp_restriction ) {
							if ( $restriction['post_type'] === $tmp_restriction['post_type'] ) {
								$post_type_found     = true;
								$post_type_found_key = $tmp_key;
								break;
							}
						}
						if ( ! $post_type_found ) {
							$merged_restrictions[ $key ] = $restriction;
						} else {
							if ( -1 === $restriction['allowed_value'] ) { // -1 is unlimited, just use it.
								$merged_restrictions[ $post_type_found_key ] = $restriction;
							} elseif ( $merged_restrictions[ $post_type_found_key ]['allowed_value'] < $restriction['allowed_value'] ) {
								$merged_restrictions[ $post_type_found_key ] = $restriction;
							}
						}
					}
				}
				$restrictions = $merged_restrictions;
			}
		} else {
			$restriction_level = leaky_paywall_subscriber_current_level_id();
			if ( false !== $restriction_level ) {

				if ( ! empty( $settings['levels'][ $restriction_level ]['post_types'] ) ) {
					$restrictions = $settings['levels'][ $restriction_level ]['post_types'];
				}
			}
		}
		return apply_filters( 'leaky_paywall_subscriber_restrictions', $restrictions );
	}
}

if ( ! function_exists( 'leaky_paywall_subscriber_current_level_id' ) ) {

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
				if ( ! is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}

			$user = wp_get_current_user();

			$mode = leaky_paywall_get_current_mode();

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

if ( ! function_exists( 'leaky_paywall_subscriber_current_level_ids' ) ) {

	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 3.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	function leaky_paywall_subscriber_current_level_ids() {
		$level_ids = array();
		$settings  = get_leaky_paywall_settings();

		$sites = array( '' );
		if ( is_multisite_premium() ) {
			global $blog_id;
			if ( ! is_main_site( $blog_id ) ) {
				$sites = array( '_all', '_' . $blog_id );
			} else {
				$sites = array( '_all', '_' . $blog_id, '' );
			}
		}

		$user = wp_get_current_user();
		$mode = leaky_paywall_get_current_mode();

		foreach ( $sites as $site ) {
			$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
			$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
			$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
			$status   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );

			if ( 'active' === $status && is_numeric( $level_id ) && leaky_paywall_user_has_access( $user ) ) {
				$level_ids[] = $level_id;
			}

			if ( 'trial' === $status && is_numeric( $level_id ) && leaky_paywall_user_has_access( $user ) ) {
				$level_ids[] = $level_id;
			}

			if ( 'canceled' === $status && is_numeric( $level_id ) && leaky_paywall_user_has_access( $user ) ) {
				$level_ids[] = $level_id;
			}
		}

		return apply_filters( 'leaky_paywall_subscriber_current_level_ids', $level_ids );
	}
}

if ( ! function_exists( 'leaky_paywall_subscriber_query' ) ) {

	/**
	 * Gets leaky paywall subscribers
	 *
	 * @since 1.1.0
	 *
	 * @param array   $args Leaky Paywall Subscribers.
	 * @param integer $blog_id The blog id.
	 * @return mixed $wpdb var or false if invalid hash.
	 */
	function leaky_paywall_subscriber_query( $args, $blog_id = false ) {

		if ( ! empty( $args ) ) {
			$site     = '';
			$settings = get_leaky_paywall_settings();
			if ( ! empty( $blog_id ) && is_multisite_premium() ) {
				$site = '_' . $blog_id;
			}

			$mode = leaky_paywall_get_current_mode();

			if ( ! empty( $args['search'] ) ) {

				$search = trim( $args['search'] );

				if ( is_email( $search ) ) {

					$args['meta_query']     = array(
						array(
							'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
							'compare' => 'EXISTS',
						),
					);
					$args['search']         = $search;
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

			if ( ! empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
				unset( $args['meta_query'] );
			}

			if ( isset( $_GET['filter-level'] ) && 'lpsubs' === $_GET['user-type'] ) {

				$level = sanitize_text_field( wp_unslash( $_GET['filter-level'] ) );

				if ( 'all' !== $level ) {

					$args['meta_query'][] = array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
						'value'   => $level,
						'compare' => 'LIKE',
					);
				}
			}

			if ( isset( $_GET['filter-status'] ) && 'lpsubs' === $_GET['user-type'] ) {

				$status = sanitize_text_field( wp_unslash( $_GET['filter-status'] ) );

				if ( 'all' !== $status ) {

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

if ( ! function_exists( 'leaky_paywall_server_pdf_download' ) ) {
	/**
	 * Download PDF
	 *
	 * @param integer $download_id The download id of the pdf.
	 */
	function leaky_paywall_server_pdf_download( $download_id ) {
		// Grab the download info.
		$url = wp_get_attachment_url( $download_id );

		wp_safe_redirect( $url );
		die();

		// Attempt to grab file.
		$response = wp_remote_head( str_replace( ' ', '%20', $url ) );
		if ( $response ) {
			if ( ! is_wp_error( $response ) ) {
				$valid_response_codes = array(
					200,
				);
				if ( in_array( wp_remote_retrieve_response_code( $response ), (array) $valid_response_codes, true ) ) {

					// Get Resource Headers.
					$headers = wp_remote_retrieve_headers( $response );

					// White list of headers to pass from original resource.
					$passthru_headers = array(
						'accept-ranges',
						'content-length',
						'content-type',
					);

					// Set Headers for download from original resource.
					foreach ( (array) $passthru_headers as $header ) {
						if ( isset( $headers[ $header ] ) ) {
							header( esc_attr( $header ) . ': ' . esc_attr( $headers[ $header ] ) );
						}
					}

					// Set headers to force download.
					header( 'Content-Description: File Transfer' );
					header( 'Content-Disposition: attachment; filename=' . basename( $url ) );
					header( 'Content-Transfer-Encoding: binary' );
					header( 'Expires: 0' );
					header( 'Cache-Control: must-revalidate' );
					header( 'Pragma: public' );

					// Clear buffer.
					flush();

					do_action( 'leaky_paywall_before_download_pdf', $url );

					// Deliver the file: readfile, curl, redirect.
					if ( ini_get( 'allow_url_fopen' ) ) {
						// Use readfile if allow_url_fopen is on.
						readfile( str_replace( ' ', '%20', $url ) );
					} elseif ( is_callable( 'curl_init' ) ) {
						// Use cURL if allow_url_fopen is off and curl is available.
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

					wp_die( wp_kses_post( $output ) );
				}
			} else {
				$output = '<h3>' . __( 'Error Downloading PDF', 'leaky-paywall' ) . '</h3>';

				/* Translators: %s - error message */
				$output .= '<p>' . sprintf( __( 'Download Error: %s', 'leaky-paywall' ), $response->get_error_message() ) . '</p>';
				$output .= '<a href="' . get_home_url() . '">' . __( 'Home', 'leaky-paywall' ) . '</a>';

				wp_die( wp_kses_post( $output ) );
			}
		}
	}
}

if ( ! function_exists( 'build_leaky_paywall_subscription_levels_row' ) ) {

	/**
	 * Build subscription level row
	 *
	 * @since 1.0.0
	 *
	 * @param array   $level The level.
	 * @param integer $row_key The row key.
	 * @return string The HTML for the level row
	 */
	function build_leaky_paywall_subscription_levels_row( $level = array(), $row_key = '' ) {

		global $leaky_paywall;
		$settings = get_leaky_paywall_settings();

		$default = array(
			'label'                         => '',
			'description'                   => '',
			'registration_form_description' => '',
			'price'                         => '',
			'subscription_length_type'      => 'limited',
			'interval_count'                => 1,
			'interval'                      => 'month',
			'recurring'                     => 'off',
			'hide_subscribe_card'           => 'off',
			'plan_id'                       => array(),
			'post_types'                    => array(
				array(
					'post_type'     => ACTIVE_ISSUEM ? 'article' : 'post',
					'allowed'       => 'unlimited',
					'allowed_value' => -1,
				),
			),
			'deleted'                       => 0,
			'site'                          => 'all',
		);
		$level   = wp_parse_args( $level, $default );

		if ( empty( $level['recurring'] ) ) {
			$level['recurring'] = 'off';
		}

		if ( ! empty( $level['deleted'] ) ) {
			$deleted = 'hidden';
		} else {
			$deleted = '';
		}

		ob_start();
		?>

		<div class="leaky-paywall-subscription-level-row-header <?php echo esc_attr( $deleted ); ?>">
			<p class="leaky-paywall-subscription-level-row-header-title"><?php echo esc_html( $level['label'] ); ?> <span class="leaky-paywall-subscription-level-row-header-title-id">ID: <?php echo esc_html( $row_key ); ?></span></p>
			<p class="leaky-paywall-subscription-level-row-header-toggler"><span class="dashicons dashicons-arrow-up"></span><span class="dashicons dashicons-arrow-down"></span></p>
		</div>

		<table class="issuem-leaky-paywall-subscription-level-row-table leaky-paywall-table <?php echo esc_attr( $deleted ); ?>">
			<?php 
			if ( isset( $settings['page_for_register'] ) && $settings['page_for_register'] ) {
				?>
					<tr>
						<th>
							<label for="level-name-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Direct Sign Up Link', 'leaky-paywall' ); ?></label>
						</th>
						<td>
							<p><?php echo esc_url( get_page_link( $settings['page_for_register'] ) ) . '?level_id=' . esc_attr( $row_key ); ?></p>
						</td>
					</tr>	
				<?php 
			} ?>
		
		
			<tr>
				<th>
					<label for="level-name-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Subscription Level Name', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<input id="level-name-<?php echo esc_attr( $row_key ); ?>" type="text" class="regular-text" name="levels[<?php echo esc_attr( $row_key ); ?>][label]" value="<?php echo esc_attr( $level['label'] ); ?>" />
					<span class="delete-x delete-subscription-level">&times;</span>
					<input type="hidden" class="deleted-subscription" name="levels[<?php echo esc_attr( $row_key ); ?>][deleted]" value="<?php echo esc_attr( $level['deleted'] ); ?>">
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-description-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Subscribe Card Description', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<textarea id="level-description-<?php echo esc_attr( $row_key ); ?>" name="levels[<?php echo esc_attr( $row_key ); ?>][description]" class="large-text"><?php echo esc_textarea( $level['description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'If entered, this will replace the auto-generated access description on the subscribe cards. HTML allowed.', 'leaky-paywall' ); ?></p>
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-registration-form-description-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Registration Form Description', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<textarea id="level-registration-form-description-<?php echo esc_attr( $row_key ); ?>" name="levels[<?php echo esc_attr( $row_key ); ?>][registration_form_description]" class="large-text"><?php echo esc_textarea( $level['registration_form_description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'If entered, this will replace the auto-generated content access description on the registration form. HTML allowed.', 'leaky-paywall' ); ?></p>
				</td>
			</tr>

			<?php
			if ( is_leaky_paywall_recurring() ) {
				?>
				<tr>
					<th>
						<label for="level-recurring-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Recurring', 'leaky-paywall' ); ?></label>
					</th>
					<td>
						<input id="level-recurring-<?php echo esc_attr( $row_key ); ?>" class="stripe-recurring" type="checkbox" name="levels[<?php echo esc_attr( $row_key ); ?>][recurring]" value="on" <?php echo checked( 'on', $level['recurring'], false ); ?> /> Enable recurring payments<br>
						<span style="color: #999; font-size: 11px;" class="recurring-help <?php echo checked( 'on', $level['recurring'], false ) ? '' : 'hidden'; ?>">Webhooks must be setup in your Stripe account for recurring payments to work properly. <a target="_blank" href="https://zeen101.helpscoutdocs.com/article/120-leaky-paywall-recurring-payments">See documentation here.</a></span>

						<?php

						if ( is_array( $level['plan_id'] ) ) {
							foreach ( $level['plan_id'] as $plan_id ) {
								if ( ! $plan_id ) {
									continue;
								}
								?>
								<input type="hidden" class="level-plan_id-<?php echo esc_attr( $row_key ); ?>" name="levels[<?php echo esc_attr( $row_key ); ?>][plan_id][]" value="<?php echo esc_attr( $plan_id ); ?>">
								<?php
							}
						} else {
							?>
							<input type="hidden" id="level-plan_id-<?php echo esc_attr( $row_key ); ?>" name="levels[<?php echo esc_attr( $row_key ); ?>][plan_id]" value="<?php echo esc_attr( $level['plan_id'] ); ?>">
							<?php
						}

						?>

					</td>
				</tr>
				<?php
			}
			?>

			<tr>
				<th>
					<label for="level-hide-subscribe-card-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Hide Subscribe Card', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<input id="level-hide-subscribe-card-<?php echo esc_attr( $row_key ); ?>" class="hide-subscribe- card" type="checkbox" name="levels[<?php echo esc_attr( $row_key ); ?>][hide_subscribe_card]" value="on" <?php echo checked( 'on', $level['hide_subscribe_card'], false ); ?> /> <?php esc_html_e( 'Do not display subscribe card on subscribe page', 'leaky-paywall' ); ?>
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-price-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Subscription Price', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<input id="level-price-<?php echo esc_attr( $row_key ); ?>" type="text" style="width: 100px;" name="levels[<?php echo esc_attr( $row_key ); ?>][price]" value="<?php echo esc_attr( $level['price'] ); ?>" />
					<p class="description"><?php esc_html_e( '0 for Free Subscriptions', 'leaky-paywall' ); ?></p>
				</td>
			</tr>

			<tr>
				<th>
					<label for="level-interval-count-<?php echo esc_attr( $row_key ); ?>"><?php esc_html_e( 'Subscription Length', 'leaky-paywall' ); ?></label>
				</th>
				<td>
					<select class="subscription_length_type" name="levels[<?php echo esc_attr( $row_key ); ?>][subscription_length_type]">
						<option value="unlimited" <?php echo selected( 'unlimited', $level['subscription_length_type'], false ); ?>><?php esc_html_e( 'Forever', 'leaky-paywall' ); ?></option>
						<option value="limited" <?php echo selected( 'limited', $level['subscription_length_type'], false ); ?>> <?php esc_html_e( 'Limited for...', 'leaky-paywall' ); ?></option>
					</select>

					<?php
					if ( 'unlimited' === $level['subscription_length_type'] ) {
						$subscription_length_input_style = 'display: none;';
					} else {
						$subscription_length_input_style = '';
					}
					?>

					<div class="interval_div" style="<?php echo esc_attr( $subscription_length_input_style ); ?>">
						<input id="level-interval-count-<?php echo esc_attr( $row_key ); ?>" type="text" class="interval_count small-text" name="levels[<?php echo esc_attr( $row_key ); ?>][interval_count]" value="<?php echo esc_attr( $level['interval_count'] ); ?>" />
						<select id="interval" name="levels[<?php echo esc_attr( $row_key ); ?>][interval]">
							<option value="day" <?php echo selected( 'day' === $level['interval'], true, false ); ?>><?php esc_html_e( 'Day(s)', 'leaky-paywall' ); ?></option>
							<option value="week" <?php echo selected( 'week' === $level['interval'], true, false ); ?>><?php esc_html_e( 'Week(s)', 'leaky-paywall' ); ?></option>
							<option value="month" <?php echo selected( 'month' === $level['interval'], true, false ); ?>><?php esc_html_e( 'Month(s)', 'leaky-paywall' ); ?></option>
							<option value="year" <?php echo selected( 'year' === $level['interval'], true, false ); ?>><?php esc_html_e( 'Year(s)', 'leaky-paywall' ); ?></option>
						</select>
					</div>
				</td>
			</tr>

			<tr>
				<th><?php esc_html_e( 'Access Options', 'leaky-paywall' ); ?></th>
				<td id="issuem-leaky-paywall-subsciption-row-<?php echo esc_attr( $row_key ); ?>-post-types">

					<table class="leaky-paywall-interal-setting-table">
						<tr>
							<th>Number Allowed</th>
							<th>Post Type</th>
							<th>Taxonomy <span style="font-weight: normal; font-size: 11px; color: #999;"> Category,tag,etc.</span></th>
							<th>&nbsp;</th>
						</tr>

						<?php
						$last_key = -1;
						if ( ! empty( $level['post_types'] ) ) {
							foreach ( $level['post_types'] as $select_post_key => $select_post_type ) {

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
						var leaky_paywall_subscription_row_<?php echo esc_attr( $row_key ); ?>_last_post_type_key = <?php echo absint( $last_key ); ?>;
					</script>
					<p><input data-row-key="<?php echo esc_attr( $row_key ); ?>" class="button-secondary" id="add-subscription-row-post-type" class="add-new-issuem-leaky-paywall-row-post-type" type="submit" name="add_leaky_paywall_subscription_row_post_type" value="<?php esc_attr_e( '+ Add Access Option', 'leaky-paywall' ); ?>" /></p>
					<?php
					if ( $leaky_paywall->is_site_wide_enabled() ) {
						echo '<p class="description">';
						esc_attr_e( 'Post Types that are not native the to the site currently being viewed are marked with an asterisk.', 'leaky-paywall' );
						echo '</p>';
					}
					?>

					<p class="description"><?php esc_html_e( 'Access processed from top to bottom.', 'leaky-paywall' ); ?></p>
				</td>
			</tr>



			<?php
			if ( is_multisite_premium() ) {
				?>
				<tr>
					<th><?php esc_html_e( 'Site', 'leaky-paywall' ); ?></th>
					<td id="issuem-leaky-paywall-subsciption-row-<?php echo esc_attr( $row_key ); ?>-site">
						<select id="site" name="levels[<?php echo esc_attr( $row_key ); ?>][site]">
							<?php
							if ( is_super_admin() ) {
								?>
								<option value="all" <?php echo selected( 'all', $level['site'], false ); ?>><?php esc_html_e( 'All Sites', 'leaky-paywall' ); ?></option>
								<?php
								$sites = get_sites();
								foreach ( $sites as $site ) {
									$site_details = get_blog_details( $site->id );
									?>
									<option value="<?php echo esc_attr( $site->id ); ?>" <?php echo selected( $site->id, $level['site'], false ); ?>><?php echo esc_html( $site_details->blogname ); ?></option>
									<?php
								}
							} else {
								$site_details = get_blog_details( get_current_blog_id() );
								?>
								<option value="<?php echo get_current_blog_id(); ?>" <?php echo selected( get_current_blog_id(), $level['site'], false ); ?>><?php echo esc_html( $site_details->blogname ); ?></option>
								<?php
							}
							?>


						</select>
					</td>
				</tr>

				<?php
			}

			// leaving for backwards compatibility, but it will deprecated.
			echo wp_kses_post( apply_filters( 'build_leaky_paywall_subscription_levels_row_addon_filter', '', $level, $row_key ) );

			do_action( 'leaky_paywall_after_subscription_levels_row', $level, $row_key );

			echo '</table>';

			$content = ob_get_contents();
			ob_end_clean();

			return $content;
	}
}

if ( ! function_exists( 'build_leaky_paywall_subscription_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_subscription_row_ajax() {
		if ( isset( $_REQUEST['row-key'] ) ) {
			// phpcs:ignore
			die( build_leaky_paywall_subscription_levels_row( array(), sanitize_text_field( wp_unslash( $_REQUEST['row-key'] ) ) ) );
		} else {
			die();
		}
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row', 'build_leaky_paywall_subscription_row_ajax' );
}

if ( ! function_exists( 'build_leaky_paywall_subscription_row_post_type' ) ) {

	/**
	 * Build Leaky Paywall subscription row
	 *
	 * @since 1.0.0
	 *
	 * @param array   $select_post_type Data for post type.
	 * @param integer $select_post_key The post key.
	 * @param integer $row_key The row key.
	 * @return mixed Value set for the issuem options.
	 */
	function build_leaky_paywall_subscription_row_post_type( $select_post_type = array(), $select_post_key = '', $row_key = '' ) {

		$default_select_post_type = array(
			'post_type'     => ACTIVE_ISSUEM ? 'article' : 'post',
			'allowed'       => 'unlimited',
			'allowed_value' => -1,
			'site'          => 0,
			'taxonomy'      => '',
		);
		$select_post_type         = wp_parse_args( $select_post_type, $default_select_post_type );

		echo '<tr class="issuem-leaky-paywall-row-post-type">';
		echo '<td><select class="allowed_type" name="levels[' . esc_attr( $row_key ) . '][post_types][' . esc_attr( $select_post_key ) . '][allowed]">';
		echo '<option value="unlimited" ' . selected( 'unlimited', $select_post_type['allowed'], false ) . '>' . esc_html__( 'Unlimited', 'leaky-paywall' ) . '</option>';
		echo '<option value="limited" ' . selected( 'limited', $select_post_type['allowed'], false ) . '>' . esc_html__( 'Limit to...', 'leaky-paywall' ) . '</option>';
		echo '</select>';

		if ( 'unlimited' === $select_post_type['allowed'] ) {
			$allowed_value_input_style = 'display: none;';
		} else {
			$allowed_value_input_style = '';
		}

		echo '<div class="allowed_value_div" style="' . esc_attr( $allowed_value_input_style ) . '">';
		echo '<input type="number" class="allowed_value small-text" name="levels[' . esc_attr( $row_key ) . '][post_types][' . esc_attr( $select_post_key ) . '][allowed_value]" value="' . esc_attr( $select_post_type['allowed_value'] ) . '" placeholder="' . esc_attr__( '#', 'leaky-paywall' ) . '" />';
		echo '</div></td>';

		echo '<td><select class="select_level_post_type" name="levels[' . esc_attr( $row_key ) . '][post_types][' . esc_attr( $select_post_key ) . '][post_type]">';
		$post_types        = get_post_types( array( 'public' => true ), 'objects' );
		$post_types_names  = get_post_types( array(), 'names' );
		$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
		if ( in_array( $select_post_type['post_type'], $post_types_names, true ) ) {
			foreach ( $post_types as $post_type ) {
				if ( in_array( $post_type->name, $hidden_post_types, true ) ) {
					continue;
				}
				echo '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $post_type->name, $select_post_type['post_type'], false ) . '>' . esc_html( $post_type->labels->name ) . '</option>';
			}
		} else {
			echo '<option value="' . esc_attr( $select_post_type['post_type'] ) . '">' . esc_html( $select_post_type['post_type'] ) . ' &#42;</option>';
		}
		echo '</select></td>';

		// get taxonomies for this post type.
		echo '<td><select style="width: 100%;" name="levels[' . esc_attr( $row_key ) . '][post_types][' . esc_attr( $select_post_key ) . '][taxonomy]">';
		$tax_post_type = $select_post_type['post_type'] ? $select_post_type['post_type'] : 'post';
		$taxes         = get_object_taxonomies( $tax_post_type, 'objects' );
		$hidden_taxes  = apply_filters( 'leaky_paywall_settings_hidden_taxonomies', array( 'post_format' ) );

		echo '<option value="all" ' . selected( 'all', $select_post_type['taxonomy'], false ) . '>All</option>';

		foreach ( $taxes as $tax ) {

			if ( in_array( $tax->name, $hidden_taxes, true ) ) {
				continue;
			}

			// create option group for this taxonomy.
			echo '<optgroup label="' . esc_attr( $tax->label ) . '">';

			// create options for this taxonomy.
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
				)
			);

			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $term->term_id, $select_post_type['taxonomy'], false ) . '>' . esc_html( $term->name ) . '</option>';
			}

			echo '</optgroup>';
		}
		echo '</select></td>';

		echo '<td><span class="delete-x delete-post-type-row">&times;</span></td>';

		echo '</tr>';
	}
}

if ( ! function_exists( 'build_leaky_paywall_subscription_row_post_type_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_subscription_row_post_type_ajax() {

		if ( isset( $_REQUEST['select-post-key'] ) && isset( $_REQUEST['row-key'] ) ) {
			$settings = get_leaky_paywall_settings();

			if ( is_multisite_premium() && isset( $_SERVER['HTTP_REFERER'] ) && preg_match( '#^' . network_admin_url() . '#i', sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) ) ) {
				if ( ! defined( 'WP_NETWORK_ADMIN' ) ) {
					define( 'WP_NETWORK_ADMIN', true );
				}
			}

			// phpcs:ignore
			die( build_leaky_paywall_subscription_row_post_type( array(), sanitize_text_field( wp_unslash( $_REQUEST['select-post-key'] ) ), sanitize_text_field( wp_unslash( $_REQUEST['row-key'] ) ) ) );
		}
		die();
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-subscription-row-post-type', 'build_leaky_paywall_subscription_row_post_type_ajax' );
}


	/**
	 * Build a default restriction row
	 *
	 * @since 1.0.0
	 *
	 * @param array   $restriction The restriction.
	 * @param integer $row_key The row key.
	 * @return mixed Value set for the issuem options.
	 */
function build_leaky_paywall_default_restriction_row( $restriction = array(), $row_key = '' ) {

	$settings = get_leaky_paywall_settings();

	if ( empty( $restriction ) ) {
		$restriction = array(
			'post_type'     => '',
			'taxonomy'      => '',
			'allowed_value' => '0',
		);
	}

	if ( ! isset( $restriction['taxonomy'] ) ) {
		$restriction['taxonomy'] = 'all';
	}

	echo '<tr class="issuem-leaky-paywall-restriction-row">';
	$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item', 'lp_transaction', 'custom_css' );
	$post_types        = get_post_types( array( 'public' => true ), 'objects' );

	echo '<td><select class="leaky-paywall-restriction-post-type" id="restriction-post-type-' . esc_attr( $row_key ) . '" name="restrictions[post_types][' . esc_attr( $row_key ) . '][post_type]">';
	foreach ( $post_types as $post_type ) {

		if ( in_array( $post_type->name, $hidden_post_types, true ) ) {
			continue;
		}

		echo '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $post_type->name, $restriction['post_type'], false ) . '>' . esc_html( $post_type->labels->name ) . '</option>';
	}

	echo '</select></td>';

	// get taxonomies for this post type.
	echo '<td><select style="width: 100%;" name="restrictions[post_types][' . esc_attr( $row_key ) . '][taxonomy]">';
	$tax_post_type = $restriction['post_type'] ? $restriction['post_type'] : 'post';
	$taxes         = get_object_taxonomies( $tax_post_type, 'objects' );
	$hidden_taxes  = apply_filters( 'leaky_paywall_settings_hidden_taxonomies', array( 'post_format', 'yst_prominent_words' ) );

	echo '<option value="all" ' . selected( 'all', $restriction['taxonomy'], false ) . '>All</option>';

	foreach ( $taxes as $tax ) {

		if ( in_array( $tax->name, $hidden_taxes, true ) ) {
			continue;
		}

		// create option group for this taxonomy.
		echo '<optgroup label="' . esc_attr( $tax->label ) . '">';

		// create options for this taxonomy.
		$terms = get_terms(
			array(
				'taxonomy'   => $tax->name,
				'hide_empty' => false,
			)
		);

		foreach ( $terms as $term ) {
			echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $term->term_id, $restriction['taxonomy'], false ) . '>' . esc_html( $term->name ) . '</option>';
		}

		echo '</optgroup>';
	}
	echo '</select></td>';

	echo '<td>';

	if ( 'on' === $settings['enable_combined_restrictions'] ) {
		echo '<p class="allowed-number-helper-text" style="color: #555; font-size: 12px;">Using combined restrictions.</p>';
		echo '<input style="display: none;" id="restriction-allowed-' . esc_attr( $row_key ) . '" type="number" class="small-text restriction-allowed-number-setting" name="restrictions[post_types][' . esc_attr( $row_key ) . '][allowed_value]" value="' . esc_attr( $restriction['allowed_value'] ) . '" />';
	} else {
		echo '<p class="allowed-number-helper-text" style="color: #555; font-size: 12px; display: none;">Using combined restrictions.</p>';
		echo '<input id="restriction-allowed-' . esc_attr( $row_key ) . '" type="number" class="small-text restriction-allowed-number-setting" name="restrictions[post_types][' . esc_attr( $row_key ) . '][allowed_value]" value="' . esc_attr( $restriction['allowed_value'] ) . '" />';
	}

	echo '</td>';

	echo '<td><span class="delete-x delete-restriction-row">&times;</span></td>';

	echo '</tr>';
}

/**
 * Get the taxonomies for the selected post type in a restriction setting row
 *
 * @since 4.7.5
 */
function leaky_paywall_get_restriction_row_post_type_taxonomies() {

	$post_type    = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_type'] ) ) : '';
	$taxes        = get_object_taxonomies( $post_type, 'objects' );
	$hidden_taxes = apply_filters( 'leaky_paywall_settings_hidden_taxonomies', array( 'post_format', 'yst_prominent_words' ) );

	ob_start();
	?>

		<select style="width: 100%;">
			<option value="all">All</option>

			<?php
			foreach ( $taxes as $tax ) {

				if ( in_array( $tax->name, $hidden_taxes, true ) ) {
					continue;
				}

				// create option group for this taxonomy.
				echo '<optgroup label="' . esc_attr( $tax->label ) . '">';

				// create options for this taxonomy.
				$terms = get_terms(
					array(
						'taxonomy'   => $tax->name,
						'hide_empty' => false,
					)
				);

				foreach ( $terms as $term ) {
					echo '<option value="' . esc_attr( $term->term_id ) . '">' . esc_html( $term->name ) . '</option>';
				}

				echo '</optgroup>';
			}

			?>

		</select>

	<?php
	$content = ob_get_contents();
	ob_end_clean();

	wp_send_json( $content );
}
add_action( 'wp_ajax_leaky-paywall-get-restriction-row-post-type-taxonomies', 'leaky_paywall_get_restriction_row_post_type_taxonomies' );


if ( ! function_exists( 'build_leaky_paywall_default_restriction_row_ajax' ) ) {

	/**
	 * AJAX Wrapper
	 *
	 * @since 1.0.0
	 */
	function build_leaky_paywall_default_restriction_row_ajax() {

		if ( isset( $_REQUEST['row-key'] ) ) {
			// phpcs:ignore
			die( build_leaky_paywall_default_restriction_row( array(), sanitize_text_field( wp_unslash( $_REQUEST['row-key'] ) ) ) );
		} else {
			die();
		}
	}
	add_action( 'wp_ajax_issuem-leaky-paywall-add-new-restriction-row', 'build_leaky_paywall_default_restriction_row_ajax' );
}

if ( ! function_exists( 'wp_print_r' ) ) {

	/**
	 * Helper function used for printing out debug information
	 *
	 * HT: Glenn Ansley @ iThemes.com
	 *
	 * @since 1.0.0
	 *
	 * @param int  $args Arguments to pass to print_r.
	 * @param bool $die TRUE to die else FALSE (default TRUE).
	 */
	function wp_print_r( $args, $die = true ) {

		$echo = '<pre>' . print_r( $args, true ) . '</pre>';

		if ( $die ) {
			die( esc_attr( $echo ) );
		} else {
			echo esc_attr( $echo );
		}
	}
}

if ( ! function_exists( 'get_leaky_paywall_subscription_level' ) ) {
	/**
	 * Get the Leaky Paywall level
	 *
	 * @param int $level_id The level id.
	 */
	function get_leaky_paywall_subscription_level( $level_id ) {

		$settings = get_leaky_paywall_settings();

		$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
		if ( isset( $settings['levels'][ $level_id ] ) ) {
			$level       = $settings['levels'][ $level_id ];
			$level['id'] = $level_id;
		} else {
			$level = false;
		}

		return apply_filters( 'get_leaky_paywall_subscription_level', $level, $level_id );
	}
}

if ( ! function_exists( 'leaky_paywall_subscription_options' ) ) {

	/**
	 * Display the subscription card options
	 */
	function leaky_paywall_subscription_options() {

		global $blog_id;

		$settings          = get_leaky_paywall_settings();
		$mode              = leaky_paywall_get_current_mode();
		$site              = leaky_paywall_get_current_site();
		$current_level_ids = leaky_paywall_subscriber_current_level_ids();

		$results = apply_filters( 'leaky_paywall_subscription_options', '' );
		// If someone wants to completely override this, they can with the above filter.
		if ( empty( $results ) ) {

			$has_allowed_value = false;
			$results          .= '<h2 class="subscription-options-title">' . __( 'Subscription Options', 'leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_subscription_options_header', '' );

			if ( ! empty( $settings['levels'] ) ) {

				$results .= apply_filters( 'leaky_paywall_before_subscription_options', '' );

				$results .= '<div class="leaky_paywall_subscription_options">';
				foreach ( apply_filters( 'leaky_paywall_subscription_levels', $settings['levels'] ) as $level_id => $level ) {

					if ( ! empty( $level['deleted'] ) ) {
						continue;
					}

					if ( isset( $level['hide_subscribe_card'] ) && 'on' === $level['hide_subscribe_card'] ) {
						continue;
					}

					if ( is_multisite_premium() && ! empty( $level['site'] ) && 'all' !== $level['site'] && $blog_id !== $level['site'] ) {
						continue;
					}

					$level = apply_filters( 'leaky_paywall_subscription_options_level', $level, $level_id );

					if ( isset( $level['recurring'] ) && 'on' === $level['recurring'] ) {
						$is_recurring = true;
					} else {
						$is_recurring = false;
					}

					$payment_options = '';
					$allowed_content = '';

					if ( in_array( $level_id, $current_level_ids ) ) {
						$current_level = 'current-level';
					} else {
						$current_level = '';
					}

					$results .= '<div id="option-' . $level_id . '" class="leaky_paywall_subscription_option ' . $current_level . '">';
					if ( $current_level ) {
						$results .= '<p class="leaky-paywall-subscription-current-level">Your Current Level</p>';
					}
					$results .= '<h3 class="leaky_paywall_subscription_option_title">' . apply_filters( 'leaky_paywall_subscription_option_title', stripslashes( $level['label'] ) ) . '</h3>';

					$results .= '<div class="leaky_paywall_subscription_allowed_content">';

					if ( ! empty( $level['post_types'] && ! $level['description'] ) ) {
						foreach ( $level['post_types'] as $post_type ) {

							if ( isset( $post_type['taxonomy'] ) ) {

								$term = get_term_by( 'term_taxonomy_id', $post_type['taxonomy'] );

								if ( is_object( $term ) ) {
									$name = $term->name;
								} else {
									$name = '';
								}

								$post_type_obj = get_post_type_object( $post_type['post_type'] );
								if ( ! empty( $post_type_obj ) ) {
									if ( 0 <= $post_type['allowed_value'] ) {
										$has_allowed_value = true;

										if ( 1 === $post_type['allowed_value'] ) {
											$plural = '';
										} else {
											$plural = 's';
										}

										/* Translators: %1$s - allowed value, %2$s - name, %3$s - post type name */
										$allowed_content .= '<p>' . sprintf( __( 'Access %1$s %2$s %3$s*', 'leaky-paywall' ), $post_type['allowed_value'], $name, $post_type_obj->labels->singular_name . $plural ) . '</p>';
									} else {
										/* Translators: %1$s - name, %2$s - post type name */
										$allowed_content .= '<p>' . sprintf( __( 'Unlimited %1$s %2$s', 'leaky-paywall' ), $name, $post_type_obj->labels->name ) . '</p>';
									}
								}
							} else {

								/* @todo: We may need to change the site ID during this process, some sites may have different post types enabled */
								$post_type_obj = get_post_type_object( $post_type['post_type'] );
								if ( ! empty( $post_type_obj ) ) {
									if ( 0 <= $post_type['allowed_value'] ) {
										$has_allowed_value = true;

										if ( 1 === $post_type['allowed_value'] ) {
											$plural = '';
										} else {
											$plural = 's';
										}
										/* Translators: %1$s - allowed value, %2$s - post type name */
										$allowed_content .= '<p>' . sprintf( __( 'Access %1$s %2$s*', 'leaky-paywall' ), $post_type['allowed_value'], $post_type_obj->labels->singular_name . $plural ) . '</p>';
									} else {
										/* Translators: %s - type of post object */
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
					if ( ! empty( $level['price'] ) ) {
						if ( ! empty( $level['recurring'] ) && 'on' === $level['recurring'] && apply_filters( 'leaky_paywall_subscription_options_price_recurring_on', true, $current_level ) ) {
							$subscription_price .= '<strong>' . leaky_paywall_get_level_display_price( $level ) . ' ' . leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) . ' ' . __( '(recurring)', 'leaky-paywall' ) . '</strong>';
							$subscription_price .= apply_filters( 'leaky_paywall_before_subscription_options_recurring_price', '' );
						} else {
							/* Translators: %1$s - display price, %2$s - interval */
							$subscription_price .= '<strong>' . sprintf( __( '%1$s %2$s', 'leaky-paywall' ), leaky_paywall_get_level_display_price( $level ), leaky_paywall_human_readable_interval( $level['interval_count'], $level['interval'] ) ) . '</strong>';
							$subscription_price .= apply_filters( 'leaky_paywall_before_subscription_options_non_recurring_price', '' );
						}

						if ( ! empty( $level['trial_period'] ) ) {
							/* Translators: %s - trial period days */
							$subscription_price .= '<span class="leaky-paywall-trial-period">' . sprintf( __( 'Free for the first %s day(s)', 'leaky-paywall' ), $level['trial_period'] ) . '</span>';
						}
					} else {
						$subscription_price .= '<strong>' . __( 'Free', 'leaky-paywall' ) . '</strong>';
					}

					$subscription_price .= '</p>';
					$subscription_price .= '</div>';

					$results .= apply_filters( 'leaky_paywall_subscription_options_subscription_price', $subscription_price, $level_id, $level );

					$subscription_action  = '';
					$subscription_action .= '<div class="leaky_paywall_subscription_payment_options">';

					// Don't show payment options if the users is currently subscribed to this level and it is a recurring level.
					if ( in_array( $level_id, $current_level_ids, true ) ) {

						$subscription_action .= '<div class="leaky_paywall_subscription_current_level"><span>';
						$subscription_action .= __( 'Your Current Subscription', 'leaky-paywall' );
						$subscription_action .= '</span></div>';
					}

					if ( in_array( $level_id, $current_level_ids, true ) && leaky_paywall_user_has_access() && $is_recurring ) {
						$subscription_action .= ''; // they already have an active recurring subscription to this level.
					} else {

						$subscription_action .= apply_filters( 'leaky_paywall_subscription_options_payment_options', $payment_options, $level, $level_id );
					}

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


	/**
	 * Pass a PHP date format string to this function to return its jQuery datepicker equivalent
	 *
	 * @since 1.1.0
	 * @param string $date_format PHP Date Format.
	 * @return string jQuery datePicker Format.
	 */
function leaky_paywall_jquery_datepicker_format( $date_format ) {

	// http://us2.php.net/manual/en/function.date.php .
	// http://api.jqueryui.com/datepicker/#utility-formatDate .
	$php_format = array(
		// day.
		'/d/', // Day of the month, 2 digits with leading zeros.
		'/D/', // A textual representation of a day, three letters.
		'/j/', // Day of the month without leading zeros.
		'/l/', // A full textual representation of the day of the week.
		// '/N/', //ISO-8601 numeric representation of the day of the week (added in PHP 5.1.0).
		// '/S/', //English ordinal suffix for the day of the month, 2 characters.
		// '/w/', //Numeric representation of the day of the week.
		'/z/', // The day of the year (starting from 0).

		// week.
		// '/W/', //ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0).

		// month.
		'/F/', // A full textual representation of a month, such as January or March.
		'/m/', // Numeric representation of a month, with leading zeros.
		'/M/', // A short textual representation of a month, three letters.
		'/n/', // numeric month no leading zeros.
		// 't/', //Number of days in the given month.

		// year.
		// '/L/', //Whether it's a leap year.
		// '/o/', //ISO-8601 year number. This has the same value as Y, except that if the ISO week number (W) belongs to the previous or next year, that year is used instead. (added in PHP 5.1.0).
		'/Y/', // A full numeric representation of a year, 4 digits.
		'/y/', // A two digit representation of a year.
	);

	$datepicker_format = array(
		// day.
		'dd', // day of month (two digit).
		'D',  // day name short.
		'd',  // day of month (no leading zero).
		'DD', // day name long.
		// '',   //N - Equivalent does not exist in datePicker.
		// '',   //S - Equivalent does not exist in datePicker.
		// '',   //w - Equivalent does not exist in datePicker.
		'z' => 'o',  // The day of the year (starting from 0).

		// week.
		// '',   //W - Equivalent does not exist in datePicker.

		// month.
		'MM', // month name long.
		'mm', // month of year (two digit).
		'M',  // month name short.
		'm',  // month of year (no leading zero).
		// '',   //t - Equivalent does not exist in datePicker.

		// year.
		// '',   //L - Equivalent does not exist in datePicker.
		// '',   //o - Equivalent does not exist in datePicker.
		'yy', // year (four digit).
		'y',  // month name long.
	);

	return preg_replace( $php_format, $datepicker_format, preg_quote( $date_format, '/' ) );
}


/**
 * Add lost password link to login form
 */
function leaky_paywall_add_lost_password_link() {
	return '<a id="leaky-paywall-lost-password-link" href="' . wp_lostpassword_url() . '">' . __( 'Lost Password?', 'leaky-paywall' ) . '</a>';
}


/**
 * Get the payment gateways
 *
 * @return array $gateways The gateways.
 */
function leaky_paywall_payment_gateways() {
	$gateways = array(
		'manual'            => __( 'Manual', 'leaky-paywall' ),
		'stripe'            => __( 'Stripe', 'leaky-paywall' ),
		'paypal_standard'   => __( 'PayPal Standard', 'leaky-paywall' ),
		'free_registration' => __( 'Free Registration', 'leaky-paywall' ),
	);
	return apply_filters( 'leaky_paywall_subscriber_payment_gateways', $gateways );
}


/**
 * Create a human readable interval
 *
 * @param string $interval_count The interval count.
 * @param string $interval The interval.
 */
function leaky_paywall_human_readable_interval( $interval_count, $interval ) {

	if ( 0 >= $interval_count ) {
		return __( 'for life', 'leaky-paywall' );
	}

	if ( 1 < $interval_count ) {
		$interval .= 's';
	}

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

	if ( 1 === $interval_count ) {
		return __( 'every', 'leaky-paywall' ) . ' ' . $interval_str;
	} else {
		return __( 'every', 'leaky-paywall' ) . ' ' . $interval_count . ' ' . $interval_str;
	}
}


/**
 * Send email based on subscription status
 *
 * @param integer $user_id The user id.
 * @param string  $status The status of the notification.
 * @param array   $args The details of the subscriber.
 */
function leaky_paywall_email_subscription_status( $user_id, $status = 'new', $args = '' ) {

	// if the args come through as a WP User object, then the user already exists in the system and we don't know their password.
	if ( ! empty( $args ) && is_array( $args ) ) {
		$password = isset( $args['password'] ) ? $args['password'] : '';
	} else {
		$password = '';
	}

	$settings = get_leaky_paywall_settings();

	$mode = leaky_paywall_get_current_mode();
	$site = leaky_paywall_get_current_site();

	$user_info     = get_userdata( $user_id );
	$message       = '';
	$admin_message = '';
	$headers       = array();

	$admin_email_recipients = esc_attr( $settings['admin_new_subscriber_email_recipients'] );
	$admin_email_subject    = esc_attr( $settings['admin_new_subscriber_email_subject'] );

	$site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
	$from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : $site_name;
	$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

	$headers[] = 'From: ' . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>";
	$headers[] = 'Reply-To: ' . $from_email;
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, $status );

	do_action( 'leaky_paywall_before_email_status', $user_id, $status );

	switch ( $status ) {

		case 'new':
		case 'update':
			$message = stripslashes( apply_filters( 'leaky_paywall_new_email_message', $settings['new_email_body'], $user_id ) );
			$subject = stripslashes( apply_filters( 'leaky_paywall_new_email_subject', $settings['new_email_subject'], $user_id ) );

			$filtered_subject = leaky_paywall_filter_email_tags( $subject, $user_id, $user_info->display_name, $password );
			$filtered_message = leaky_paywall_filter_email_tags( $message, $user_id, $user_info->display_name, $password );

			$filtered_message = wpautop( make_clickable( $filtered_message ) );

			if ( 'traditional' === $settings['login_method'] && 'off' === $settings['new_subscriber_email'] ) {
				wp_mail( $user_info->user_email, $filtered_subject, $filtered_message, $headers, $attachments );
			}

			if ( 'off' === $settings['new_subscriber_admin_email'] ) {
				// new user subscribe admin email.

				$level_id   = get_user_meta( $user_info->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$level      = get_leaky_paywall_subscription_level( $level_id );
				$level_name = $level['label'];

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

				if ( $admin_email_recipients ) {
					wp_mail( $admin_email_recipients, $admin_email_subject, $admin_message, $headers, $attachments );
				}
			}

			break;

		case 'renewal_reminder':
			$message = stripslashes( apply_filters( 'leaky_paywall_renewal_reminder_email_message', $settings['renewal_reminder_email_body'], $user_id ) );

			$filtered_subject = leaky_paywall_filter_email_tags( $settings['renewal_reminder_email_subject'], $user_id, $user_info->display_name, $password );
			$filtered_message = leaky_paywall_filter_email_tags( $message, $user_id, $user_info->display_name, $password );

			$filtered_message = wpautop( make_clickable( $filtered_message ) );

			if ( 'traditional' === $settings['login_method'] ) {
				wp_mail( $user_info->user_email, $filtered_subject, $filtered_message, $headers, $attachments );
			}

			break;

		default:
			break;
	}
}


/**
 * Register cron job on plugin activation.
 */
function leaky_paywall_process_renewal_reminder_schedule() {
	$timestamp = wp_next_scheduled( 'leaky_paywall_process_renewal_reminder' );

	if ( false === $timestamp ) {
		wp_schedule_event( time(), 'daily', 'leaky_paywall_process_renewal_reminder' );
	}
}
add_action( 'admin_init', 'leaky_paywall_process_renewal_reminder_schedule' );

/**
 * Remove our renewal reminder scheduled event if Leaky Paywall is deactivated
 */
function leaky_paywall_process_renewal_reminder_deactivation() {
	wp_clear_scheduled_hook( 'leaky_paywall_process_renewal_reminder' );
}
register_deactivation_hook( __FILE__, 'leaky_paywall_process_renewal_reminder_deactivation' );


/**
 * Process renewal reminder email for each Leaky Paywall subscriber
 *
 * @since 4.9.3
 */
function leaky_paywall_maybe_send_renewal_reminder() {

	global $blog_id;

	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();

	if ( 'on' === $settings['renewal_reminder_email'] ) {
		return;
	}

	// do not send an email if the body of the email is empty.
	if ( ! $settings['renewal_reminder_email_body'] ) {
		return;
	}

	leaky_paywall_log( current_time( 'Y-m-d' ), 'process renewal reminder' );

	$days_before     = (int) $settings['renewal_reminder_days_before'];
	$date_to_compare = strtotime( '+' . $days_before . ' day' );

	$args = array(
		'number'     => -1,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_expires' . $site,
				'value'   => gmdate( 'Y-m-d', $date_to_compare ),
				'compare' => '=',
				'type'    => 'DATE',
			),

		),
	);

	$users = get_users( $args );

	if ( empty( $users ) ) {
		return;
	}

	foreach ( $users as $user ) {

		$user_id    = $user->ID;
		$expiration = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
		$plan       = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );

		// do not send renewal reminders to users with recurring plans.
		if ( ! empty( $plan ) ) {
			continue;
		}

		// user does not have an expiration date sent, so we can't do the calculations needed.
		if ( empty( $expiration ) || '0000-00-00 00:00:00' === $expiration ) {
			continue;
		}

		// if expiration is the past, continue.
		if ( strtotime( $expiration ) < current_time( 'timestamp' ) ) {
			continue;
		}

		$date_differ     = leaky_paywall_date_difference( $expiration, gmdate( 'Y-m-d H:i:s' ) );
		$already_emailed = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_renewal_emailed' . $site, true );

		if ( !$already_emailed && ( $date_differ <= $days_before ) ) {
			leaky_paywall_email_subscription_status( $user_id, 'renewal_reminder' );
			update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_renewal_emailed' . $site, current_time( 'timestamp' ) );
		}
	}
}
add_action( 'leaky_paywall_process_renewal_reminder', 'leaky_paywall_maybe_send_renewal_reminder' );


/**
 * Calculate the differce between two date values
 *
 * @since 4.9.3
 * @param string $date_1 The first date.
 * @param string $date_2 The second date.
 * @param string $difference_format The difference format.
 * @return string
 */
function leaky_paywall_date_difference( $date_1, $date_2, $difference_format = '%a' ) {

	$datetime1 = date_create( $date_1 );
	$datetime2 = date_create( $date_2 );

	$interval = date_diff( $datetime1, $datetime2 );

	return $interval->format( $difference_format );
}

/**
 * Set email content type
 *
 * @param string $content_type The content type.
 * @return string
 */
function leaky_paywall_set_email_content_type( $content_type ) {
	return 'text/html';
}


/**
 * Filter email tags
 *
 * @param string $message The email message.
 * @param int    $user_id The user id.
 * @param string $display_name The display name of the user.
 * @param string $password The password of the user.
 * @return string
 */
function leaky_paywall_filter_email_tags( $message, $user_id, $display_name, $password ) {

	$settings = get_leaky_paywall_settings();

	$user = get_userdata( $user_id );

	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );

	$message = str_replace( '%blogname%', $site_name, $message );
	$message = str_replace( '%sitename%', $site_name, $message );
	$message = str_replace( '%username%', $user->user_login, $message );
	$message = str_replace( '%useremail%', $user->user_email, $message );
	$message = str_replace( '%firstname%', $user->user_firstname, $message );
	$message = str_replace( '%lastname%', $user->user_lastname, $message );
	$message = str_replace( '%displayname%', $display_name, $message );
	$message = str_replace( '%password%', $password, $message );

	return $message;
}


/**
 * Get currencies supported by Leaky Paywall
 *
 * @return array
 */
function leaky_paywall_supported_currencies() {
	$currencies = array(
		'AED' => array(
			'symbol'  => '&#1583;.&#1573;',
			'label'   => __( 'UAE dirham', 'leaky-paywall' ),
			'country' => __( 'UAE', 'leaky-paywall' ),
		),
		'AFN' => array(
			'symbol'  => 'Afs',
			'label'   => __( 'Afghan afghani', 'leaky-paywall' ),
			'country' => __( 'Afghanistan', 'leaky-paywall' ),
		),
		'ALL' => array(
			'symbol'  => 'L',
			'label'   => __( 'Albanian lek', 'leaky-paywall' ),
			'country' => __( 'Albania', 'leaky-paywall' ),
		),
		'AMD' => array(
			'symbol'  => 'AMD',
			'label'   => __( 'Armenian dram', 'leaky-paywall' ),
			'country' => __( 'Armenia', 'leaky-paywall' ),
		),
		'ANG' => array(
			'symbol'  => 'NA&#402;',
			'label'   => __( 'Netherlands Antillean gulden', 'leaky-paywall' ),
			'country' => __( 'Netherlands', 'leaky-paywall' ),
		),
		'AOA' => array(
			'symbol'  => 'Kz',
			'label'   => __( 'Angolan kwanza', 'leaky-paywall' ),
			'country' => __( 'Angolia', 'leaky-paywall' ),
		),
		'ARS' => array(
			'symbol'  => '$',
			'label'   => __( 'Argentine peso', 'leaky-paywall' ),
			'country' => __( 'Argentina', 'leaky-paywall' ),
		),
		'AUD' => array(
			'symbol'  => '$',
			'label'   => __( 'Australian dollar', 'leaky-paywall' ),
			'country' => __( 'Australia', 'leaky-paywall' ),
		),
		'AWG' => array(
			'symbol'  => '&#402;',
			'label'   => __( 'Aruban florin', 'leaky-paywall' ),
			'country' => __( 'Aruba', 'leaky-paywall' ),
		),
		'AZN' => array(
			'symbol'  => 'AZN',
			'label'   => __( 'Azerbaijani manat', 'leaky-paywall' ),
			'country' => __( 'Azerbaij', 'leaky-paywall' ),
		),
		'BAM' => array(
			'symbol'  => 'KM',
			'label'   => __( 'Bosnia and Herzegovina konvertibilna marka', 'leaky-paywall' ),
			'country' => __( 'Bosnia', 'leaky-paywall' ),
		),
		'BBD' => array(
			'symbol'  => 'Bds$',
			'label'   => __( 'Barbadian dollar', 'leaky-paywall' ),
			'country' => __( 'Barbadian', 'leaky-paywall' ),
		),
		'BDT' => array(
			'symbol'  => '&#2547;',
			'label'   => __( 'Bangladeshi taka', 'leaky-paywall' ),
			'country' => __( 'Bangladesh', 'leaky-paywall' ),
		),
		'BGN' => array(
			'symbol'  => 'BGN',
			'label'   => __( 'Bulgarian lev', 'leaky-paywall' ),
			'country' => __( 'Bulgaria', 'leaky-paywall' ),
		),
		'BIF' => array(
			'symbol'  => 'FBu',
			'label'   => __( 'Burundi franc', 'leaky-paywall' ),
			'country' => __( 'Burundi', 'leaky-paywall' ),
		),
		'BMD' => array(
			'symbol'  => 'BD$',
			'label'   => __( 'Bermudian dollar', 'leaky-paywall' ),
			'country' => __( 'Bermuda', 'leaky-paywall' ),
		),
		'BND' => array(
			'symbol'  => 'B$',
			'label'   => __( 'Brunei dollar', 'leaky-paywall' ),
			'country' => __( 'Brunei', 'leaky-paywall' ),
		),
		'BOB' => array(
			'symbol'  => 'Bs.',
			'label'   => __( 'Bolivian boliviano', 'leaky-paywall' ),
			'country' => __( 'Bolivia', 'leaky-paywall' ),
		),
		'BRL' => array(
			'symbol'  => 'R$',
			'label'   => __( 'Brazilian real', 'leaky-paywall' ),
			'country' => __( 'Brazil', 'leaky-paywall' ),
		),
		'BSD' => array(
			'symbol'  => 'B$',
			'label'   => __( 'Bahamian dollar', 'leaky-paywall' ),
			'country' => __( 'Bahamas', 'leaky-paywall' ),
		),
		'BWP' => array(
			'symbol'  => 'P',
			'label'   => __( 'Botswana pula', 'leaky-paywall' ),
			'country' => __( 'Botswana', 'leaky-paywall' ),
		),
		'BZD' => array(
			'symbol'  => 'BZ$',
			'label'   => __( 'Belize dollar', 'leaky-paywall' ),
			'country' => __( 'Belize', 'leaky-paywall' ),
		),
		'CAD' => array(
			'symbol'  => '$',
			'label'   => __( 'Canadian dollar', 'leaky-paywall' ),
			'country' => __( 'Canada', 'leaky-paywall' ),
		),
		'CDF' => array(
			'symbol'  => 'F',
			'label'   => __( 'Congolese franc', 'leaky-paywall' ),
			'country' => __( 'Congo', 'leaky-paywall' ),
		),
		'CHF' => array(
			'symbol'  => 'CHF',
			'label'   => __( 'Swiss franc', 'leaky-paywall' ),
			'country' => __( 'Switzerland', 'leaky-paywall' ),
		),
		'CLP' => array(
			'symbol'  => '$',
			'label'   => __( 'Chilean peso', 'leaky-paywall' ),
			'country' => __( 'Chili', 'leaky-paywall' ),
		),
		'CNY' => array(
			'symbol'  => '&#165;',
			'label'   => __( 'Chinese Yuan Renminbi', 'leaky-paywall' ),
			'country' => __( 'Chinese Yuan', 'leaky-paywall' ),
		),
		'COP' => array(
			'symbol'  => 'Col$',
			'label'   => __( 'Colombian peso', 'leaky-paywall' ),
			'country' => __( 'Colombia', 'leaky-paywall' ),
		),
		'CRC' => array(
			'symbol'  => '&#8353;',
			'label'   => __( 'Costa Rican colon', 'leaky-paywall' ),
			'country' => __( 'Costa Rica', 'leaky-paywall' ),
		),
		'CVE' => array(
			'symbol'  => 'Esc',
			'label'   => __( 'Cape Verdean escudo', 'leaky-paywall' ),
			'country' => __( 'Cape Verde', 'leaky-paywall' ),
		),
		'CZK' => array(
			'symbol'  => 'K&#269;',
			'label'   => __( 'Czech koruna', 'leaky-paywall' ),
			'country' => __( 'Czech', 'leaky-paywall' ),
		),
		'DJF' => array(
			'symbol'  => 'Fdj',
			'label'   => __( 'Djiboutian franc', 'leaky-paywall' ),
			'country' => __( 'Djibouti', 'leaky-paywall' ),
		),
		'DKK' => array(
			'symbol'  => 'kr',
			'label'   => __( 'Danish krone', 'leaky-paywall' ),
			'country' => __( 'Danish', 'leaky-paywall' ),
		),
		'DOP' => array(
			'symbol'  => 'RD$',
			'label'   => __( 'Dominican peso', 'leaky-paywall' ),
			'country' => __( 'Dominican Republic', 'leaky-paywall' ),
		),
		'DZD' => array(
			'symbol'  => '&#1583;.&#1580;',
			'label'   => __( 'Algerian dinar', 'leaky-paywall' ),
			'country' => __( 'Algeria', 'leaky-paywall' ),
		),
		'EEK' => array(
			'symbol'  => 'KR',
			'label'   => __( 'Estonian kroon', 'leaky-paywall' ),
			'country' => __( 'Estonia', 'leaky-paywall' ),
		),
		'EGP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'Egyptian pound', 'leaky-paywall' ),
			'country' => __( 'Egypt', 'leaky-paywall' ),
		),
		'ETB' => array(
			'symbol'  => 'Br',
			'label'   => __( 'Ethiopian birr', 'leaky-paywall' ),
			'country' => __( 'Ethiopia', 'leaky-paywall' ),
		),
		'EUR' => array(
			'symbol'  => '&#8364;',
			'label'   => __( 'European Euro', 'leaky-paywall' ),
			'country' => __( 'Euro', 'leaky-paywall' ),
		),
		'FJD' => array(
			'symbol'  => 'FJ$',
			'label'   => __( 'Fijian dollar', 'leaky-paywall' ),
			'country' => __( 'Fiji', 'leaky-paywall' ),
		),
		'FKP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'Falkland Islands pound', 'leaky-paywall' ),
			'country' => __( 'Falkland Islands', 'leaky-paywall' ),
		),
		'GBP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'British pound', 'leaky-paywall' ),
			'country' => __( 'Great Britian', 'leaky-paywall' ),
		),
		'GEL' => array(
			'symbol'  => 'GEL',
			'label'   => __( 'Georgian lari', 'leaky-paywall' ),
			'country' => __( 'Georgia', 'leaky-paywall' ),
		),
		'GIP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'Gibraltar pound', 'leaky-paywall' ),
			'country' => __( 'Gibraltar', 'leaky-paywall' ),
		),
		'GMD' => array(
			'symbol'  => 'D',
			'label'   => __( 'Gambian dalasi', 'leaky-paywall' ),
			'country' => __( 'Gambia', 'leaky-paywall' ),
		),
		'GNF' => array(
			'symbol'  => 'FG',
			'label'   => __( 'Guinean franc', 'leaky-paywall' ),
			'country' => __( 'Guinea', 'leaky-paywall' ),
		),
		'GTQ' => array(
			'symbol'  => 'Q',
			'label'   => __( 'Guatemalan quetzal', 'leaky-paywall' ),
			'country' => __( 'Guatemala', 'leaky-paywall' ),
		),
		'GYD' => array(
			'symbol'  => 'GY$',
			'label'   => __( 'Guyanese dollar', 'leaky-paywall' ),
			'country' => __( 'Guyanese', 'leaky-paywall' ),
		),
		'HKD' => array(
			'symbol'  => 'HK$',
			'label'   => __( 'Hong Kong dollar', 'leaky-paywall' ),
			'country' => __( 'Hong Kong', 'leaky-paywall' ),
		),
		'HNL' => array(
			'symbol'  => 'L',
			'label'   => __( 'Honduran lempira', 'leaky-paywall' ),
			'country' => __( 'Honduras', 'leaky-paywall' ),
		),
		'HRK' => array(
			'symbol'  => 'kn',
			'label'   => __( 'Croatian kuna', 'leaky-paywall' ),
			'country' => __( 'Croatia', 'leaky-paywall' ),
		),
		'HTG' => array(
			'symbol'  => 'G',
			'label'   => __( 'Haitian gourde', 'leaky-paywall' ),
			'country' => __( 'Haiti', 'leaky-paywall' ),
		),
		'HUF' => array(
			'symbol'  => 'Ft',
			'label'   => __( 'Hungarian forint', 'leaky-paywall' ),
			'country' => __( 'Hungary', 'leaky-paywall' ),
		),
		'IDR' => array(
			'symbol'  => 'Rp',
			'label'   => __( 'Indonesian rupiah', 'leaky-paywall' ),
			'country' => __( 'Idonesia', 'leaky-paywall' ),
		),
		'ILS' => array(
			'symbol'  => '&#8362;',
			'label'   => __( 'Israeli new sheqel', 'leaky-paywall' ),
			'country' => __( 'Israel', 'leaky-paywall' ),
		),
		'INR' => array(
			'symbol'  => '&#8377;',
			'label'   => __( 'Indian rupee', 'leaky-paywall' ),
			'country' => __( 'India', 'leaky-paywall' ),
		),
		'ISK' => array(
			'symbol'  => 'kr',
			'label'   => __( 'Icelandic króna', 'leaky-paywall' ),
			'country' => __( 'Iceland', 'leaky-paywall' ),
		),
		'JMD' => array(
			'symbol'  => 'J$',
			'label'   => __( 'Jamaican dollar', 'leaky-paywall' ),
			'country' => __( 'Jamaica', 'leaky-paywall' ),
		),
		'JPY' => array(
			'symbol'  => '&#165;',
			'label'   => __( 'Japanese yen', 'leaky-paywall' ),
			'country' => __( 'Japan', 'leaky-paywall' ),
		),
		'KES' => array(
			'symbol'  => 'KSh',
			'label'   => __( 'Kenyan shilling', 'leaky-paywall' ),
			'country' => __( 'Kenya', 'leaky-paywall' ),
		),
		'KGS' => array(
			'symbol'  => '&#1089;&#1086;&#1084;',
			'label'   => __( 'Kyrgyzstani som', 'leaky-paywall' ),
			'country' => __( 'Kyrgyzstan', 'leaky-paywall' ),
		),
		'KHR' => array(
			'symbol'  => '&#6107;',
			'label'   => __( 'Cambodian riel', 'leaky-paywall' ),
			'country' => __( 'Cambodia', 'leaky-paywall' ),
		),
		'KMF' => array(
			'symbol'  => 'KMF',
			'label'   => __( 'Comorian franc', 'leaky-paywall' ),
			'country' => __( 'Comorian', 'leaky-paywall' ),
		),
		'KRW' => array(
			'symbol'  => 'W',
			'label'   => __( 'South Korean won', 'leaky-paywall' ),
			'country' => __( 'South Korea', 'leaky-paywall' ),
		),
		'KYD' => array(
			'symbol'  => 'KY$',
			'label'   => __( 'Cayman Islands dollar', 'leaky-paywall' ),
			'country' => __( 'Cayman Islands', 'leaky-paywall' ),
		),
		'KZT' => array(
			'symbol'  => 'T',
			'label'   => __( 'Kazakhstani tenge', 'leaky-paywall' ),
			'country' => __( 'Kazakhstan', 'leaky-paywall' ),
		),
		'LAK' => array(
			'symbol'  => 'KN',
			'label'   => __( 'Lao kip', 'leaky-paywall' ),
			'country' => __( 'Loa', 'leaky-paywall' ),
		),
		'LBP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'Lebanese lira', 'leaky-paywall' ),
			'country' => __( 'Lebanese', 'leaky-paywall' ),
		),
		'LKR' => array(
			'symbol'  => 'Rs',
			'label'   => __( 'Sri Lankan rupee', 'leaky-paywall' ),
			'country' => __( 'Sri Lanka', 'leaky-paywall' ),
		),
		'LRD' => array(
			'symbol'  => 'L$',
			'label'   => __( 'Liberian dollar', 'leaky-paywall' ),
			'country' => __( 'Liberia', 'leaky-paywall' ),
		),
		'LSL' => array(
			'symbol'  => 'M',
			'label'   => __( 'Lesotho loti', 'leaky-paywall' ),
			'country' => __( 'Lesotho', 'leaky-paywall' ),
		),
		'LTL' => array(
			'symbol'  => 'Lt',
			'label'   => __( 'Lithuanian litas', 'leaky-paywall' ),
			'country' => __( 'Lithuania', 'leaky-paywall' ),
		),
		'LVL' => array(
			'symbol'  => 'Ls',
			'label'   => __( 'Latvian lats', 'leaky-paywall' ),
			'country' => __( 'Latvia', 'leaky-paywall' ),
		),
		'MAD' => array(
			'symbol'  => 'MAD',
			'label'   => __( 'Moroccan dirham', 'leaky-paywall' ),
			'country' => __( 'Morocco', 'leaky-paywall' ),
		),
		'MDL' => array(
			'symbol'  => 'MDL',
			'label'   => __( 'Moldovan leu', 'leaky-paywall' ),
			'country' => __( 'Moldova', 'leaky-paywall' ),
		),
		'MGA' => array(
			'symbol'  => 'FMG',
			'label'   => __( 'Malagasy ariary', 'leaky-paywall' ),
			'country' => __( 'Malagasy', 'leaky-paywall' ),
		),
		'MKD' => array(
			'symbol'  => 'MKD',
			'label'   => __( 'Macedonian denar', 'leaky-paywall' ),
			'country' => __( 'Macedonia', 'leaky-paywall' ),
		),
		'MNT' => array(
			'symbol'  => '&#8366;',
			'label'   => __( 'Mongolian tugrik', 'leaky-paywall' ),
			'country' => __( 'Mongolia', 'leaky-paywall' ),
		),
		'MOP' => array(
			'symbol'  => 'P',
			'label'   => __( 'Macanese pataca', 'leaky-paywall' ),
			'country' => __( 'Macanese', 'leaky-paywall' ),
		),
		'MRO' => array(
			'symbol'  => 'UM',
			'label'   => __( 'Mauritanian ouguiya', 'leaky-paywall' ),
			'country' => '',
		),
		'MUR' => array(
			'symbol'  => 'Rs',
			'label'   => __( 'Mauritian rupee', 'leaky-paywall' ),
			'country' => '',
		),
		'MVR' => array(
			'symbol'  => 'Rf',
			'label'   => __( 'Maldivian rufiyaa', 'leaky-paywall' ),
			'country' => '',
		),
		'MWK' => array(
			'symbol'  => 'MK',
			'label'   => __( 'Malawian kwacha', 'leaky-paywall' ),
			'country' => '',
		),
		'MXN' => array(
			'symbol'  => '$',
			'label'   => __( 'Mexican peso', 'leaky-paywall' ),
			'country' => '',
		),
		'MYR' => array(
			'symbol'  => 'RM',
			'label'   => __( 'Malaysian ringgit', 'leaky-paywall' ),
			'country' => '',
		),
		'MZN' => array(
			'symbol'  => 'MT',
			'label'   => __( 'Mozambique Metical', 'leaky-paywall' ),
			'country' => '',
		),
		'NAD' => array(
			'symbol'  => 'N$',
			'label'   => __( 'Namibian dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'NGN' => array(
			'symbol'  => '&#8358;',
			'label'   => __( 'Nigerian naira', 'leaky-paywall' ),
			'country' => '',
		),
		'NIO' => array(
			'symbol'  => 'C$',
			'label'   => __( 'Nicaraguan Córdoba', 'leaky-paywall' ),
			'country' => '',
		),
		'NOK' => array(
			'symbol'  => 'kr',
			'label'   => __( 'Norwegian krone', 'leaky-paywall' ),
			'country' => '',
		),
		'NPR' => array(
			'symbol'  => 'NRs',
			'label'   => __( 'Nepalese rupee', 'leaky-paywall' ),
			'country' => '',
		),
		'NZD' => array(
			'symbol'  => 'NZ$',
			'label'   => __( 'New Zealand dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'PAB' => array(
			'symbol'  => 'B./',
			'label'   => __( 'Panamanian balboa', 'leaky-paywall' ),
			'country' => '',
		),
		'PEN' => array(
			'symbol'  => 'S/.',
			'label'   => __( 'Peruvian nuevo sol', 'leaky-paywall' ),
			'country' => '',
		),
		'PGK' => array(
			'symbol'  => 'K',
			'label'   => __( 'Papua New Guinean kina', 'leaky-paywall' ),
			'country' => '',
		),
		'PHP' => array(
			'symbol'  => '&#8369;',
			'label'   => __( 'Philippine peso', 'leaky-paywall' ),
			'country' => '',
		),
		'PKR' => array(
			'symbol'  => 'Rs.',
			'label'   => __( 'Pakistani rupee', 'leaky-paywall' ),
			'country' => '',
		),
		'PLN' => array(
			'symbol'  => 'z&#322;',
			'label'   => __( 'Polish zloty', 'leaky-paywall' ),
			'country' => '',
		),
		'PYG' => array(
			'symbol'  => '&#8370;',
			'label'   => __( 'Paraguayan guarani', 'leaky-paywall' ),
			'country' => '',
		),
		'QAR' => array(
			'symbol'  => 'QR',
			'label'   => __( 'Qatari riyal', 'leaky-paywall' ),
			'country' => '',
		),
		'RON' => array(
			'symbol'  => 'L',
			'label'   => __( 'Romanian leu', 'leaky-paywall' ),
			'country' => '',
		),
		'RSD' => array(
			'symbol'  => 'din.',
			'label'   => __( 'Serbian dinar', 'leaky-paywall' ),
			'country' => '',
		),
		'RUB' => array(
			'symbol'  => 'R',
			'label'   => __( 'Russian ruble', 'leaky-paywall' ),
			'country' => '',
		),
		'RWF' => array(
			'symbol'  => 'R&#8355;',
			'label'   => __( 'Rwandan Franc' ),
			'country' => '',
		),
		'SAR' => array(
			'symbol' => 'SR',
			'label'  => __( 'Saudi riyal', 'leaky-paywall' ),
		),
		'SBD' => array(
			'symbol'  => 'SI$',
			'label'   => __( 'Solomon Islands dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'SCR' => array(
			'symbol'  => 'SR',
			'label'   => __( 'Seychellois rupee', 'leaky-paywall' ),
			'country' => '',
		),
		'SEK' => array(
			'symbol'  => 'kr',
			'label'   => __( 'Swedish krona', 'leaky-paywall' ),
			'country' => '',
		),
		'SGD' => array(
			'symbol'  => 'S$',
			'label'   => __( 'Singapore dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'SHP' => array(
			'symbol'  => '&#163;',
			'label'   => __( 'Saint Helena pound', 'leaky-paywall' ),
			'country' => '',
		),
		'SLL' => array(
			'symbol'  => 'Le',
			'label'   => __( 'Sierra Leonean leone', 'leaky-paywall' ),
			'country' => '',
		),
		'SOS' => array(
			'symbol'  => 'Sh.',
			'label'   => __( 'Somali shilling', 'leaky-paywall' ),
			'country' => '',
		),
		'SRD' => array(
			'symbol'  => '$',
			'label'   => __( 'Surinamese dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'STD' => array(
			'symbol'  => 'STD',
			'label'   => __( 'São Tomé and Príncipe Dobra', 'leaky-paywall' ),
			'country' => '',
		),
		'SVC' => array(
			'symbol'  => '$',
			'label'   => __( 'El Salvador Colon', 'leaky-paywall' ),
			'country' => '',
		),
		'SZL' => array(
			'symbol'  => 'E',
			'label'   => __( 'Swazi lilangeni', 'leaky-paywall' ),
			'country' => '',
		),
		'THB' => array(
			'symbol'  => '&#3647;',
			'label'   => __( 'Thai baht', 'leaky-paywall' ),
			'country' => '',
		),
		'TJS' => array(
			'symbol'  => 'TJS',
			'label'   => __( 'Tajikistani somoni', 'leaky-paywall' ),
			'country' => '',
		),
		'TOP' => array(
			'symbol'  => 'T$',
			'label'   => __( "Tonga Pa'anga", 'leaky-paywall' ),
			'country' => '',
		),
		'TRY' => array(
			'symbol'  => 'TRY',
			'label'   => __( 'Turkish new lira', 'leaky-paywall' ),
			'country' => '',
		),
		'TTD' => array(
			'symbol'  => 'TT$',
			'label'   => __( 'Trinidad and Tobago dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'TWD' => array(
			'symbol'  => 'NT$',
			'label'   => __( 'New Taiwan dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'TZS' => array(
			'symbol'  => 'TZS',
			'label'   => __( 'Tanzanian shilling', 'leaky-paywall' ),
			'country' => '',
		),
		'UAH' => array(
			'symbol'  => 'UAH',
			'label'   => __( 'Ukrainian hryvnia', 'leaky-paywall' ),
			'country' => '',
		),
		'UGX' => array(
			'symbol'  => 'USh',
			'label'   => __( 'Ugandan shilling', 'leaky-paywall' ),
			'country' => '',
		),
		'USD' => array(
			'symbol'  => '$',
			'label'   => __( 'United States dollar', 'leaky-paywall' ),
			'country' => __( 'United States', 'leaky-paywall' ),
		),
		'UYU' => array(
			'symbol'  => '$U',
			'label'   => __( 'Uruguayan peso', 'leaky-paywall' ),
			'country' => '',
		),
		'UZS' => array(
			'symbol'  => 'UZS',
			'label'   => __( 'Uzbekistani som', 'leaky-paywall' ),
			'country' => '',
		),
		'VND' => array(
			'symbol'  => '&#8363;',
			'label'   => __( 'Vietnamese dong', 'leaky-paywall' ),
			'country' => '',
		),
		'VUV' => array(
			'symbol'  => 'VT',
			'label'   => __( 'Vanuatu vatu', 'leaky-paywall' ),
			'country' => '',
		),
		'WST' => array(
			'symbol'  => 'WS$',
			'label'   => __( 'Samoan tala', 'leaky-paywall' ),
			'country' => '',
		),
		'XAF' => array(
			'symbol'  => 'CFA',
			'label'   => __( 'Central African CFA franc', 'leaky-paywall' ),
			'country' => '',
		),
		'XCD' => array(
			'symbol'  => 'EC$',
			'label'   => __( 'East Caribbean dollar', 'leaky-paywall' ),
			'country' => '',
		),
		'XOF' => array(
			'symbol'  => 'CFA',
			'label'   => __( 'West African CFA franc', 'leaky-paywall' ),
			'country' => '',
		),
		'XPF' => array(
			'symbol'  => 'F',
			'label'   => __( 'CFP franc', 'leaky-paywall' ),
			'country' => '',
		),
		'YER' => array(
			'symbol'  => 'YER',
			'label'   => __( 'Yemeni rial', 'leaky-paywall' ),
			'country' => '',
		),
		'ZAR' => array(
			'symbol'  => 'R',
			'label'   => __( 'South African rand', 'leaky-paywall' ),
			'country' => '',
		),
		'ZMW' => array(
			'symbol'  => 'ZK',
			'label'   => __( 'Zambian kwacha', 'leaky-paywall' ),
			'country' => '',
		),
	);

	return apply_filters( 'leaky_paywall_supported_currencies', $currencies );
}

if ( ! function_exists( 'zeen101_dot_com_leaky_rss_feed_check' ) ) {

	/**
	 * Check leakypaywall.com for new RSS items in the leaky blast feed, to update users of latest Leaky Paywall news
	 *
	 * @since 1.1.1
	 */
	function zeen101_dot_com_leaky_rss_feed_check() {

		include_once ABSPATH . WPINC . '/feed.php';

		$output  = '';
		$feedurl = 'https://leakypaywall.com/feed/?post_type=blast&target=leaky-paywall';

		$rss = fetch_feed( $feedurl );

		if ( $rss && ! is_wp_error( $rss ) ) {

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

	if ( ! wp_next_scheduled( 'zeen101_dot_com_leaky_rss_feed_check' ) ) {
		wp_schedule_event( time(), 'daily', 'zeen101_dot_com_leaky_rss_feed_check' );
	}
}


if ( ! function_exists( 'object_to_array' ) ) {
	/**
	 * Helper function to convert object to array
	 *
	 * @since 3.7.0
	 * @param object $object An object.
	 * @return array
	 */
	function object_to_array( $object ) {

		if ( ! is_object( $object ) && ! is_array( $object ) ) {
			return $object;
		}

		return array_map( 'objectToArray', (array) $object );
	}
}

/**
 * Allow csv files to be uploaded via the media uploader
 *
 * @param array $existing_mimes Existing mimes.
 * @since  3.7.1
 */
function leaky_paywall_upload_mimes( $existing_mimes = array() ) {
	$existing_mimes['csv'] = 'text/csv';
	return $existing_mimes;
}
add_filter( 'upload_mimes', 'leaky_paywall_upload_mimes' );


/**
 * Convert csv file to array
 *
 * @param  string $filename  csv file name.
 * @param  string $delimiter separator for data fields.
 * @return array            array of data from csv
 */
function leaky_paywall_csv_to_array( $filename = '', $delimiter = ',' ) {

	if ( ! file_exists( $filename ) || ! is_readable( $filename ) ) {
		return;
	}

	$header = null;
	$data   = array();
	$handle = fopen( $filename, 'r' );

	if ( false !== $handle ) {
		$row = fgetcsv( $handle, 1000, $delimiter );

		while ( false !== $row ) {

			if ( ! $header ) {
				$header = $row;
			} else {
				$data[] = array_combine( $header, $row );
			}
		}
		fclose( $handle );
	}
	return $data;
}

/**
 * Get the old form input value
 *
 * @param string $input name of input.
 * @param bool   $echo Whether to echo value.
 * @return string
 */
function leaky_paywall_old_form_value( $input, $echo = true ) {

	$value = '';

	if ( isset( $_POST[ $input ] ) && sanitize_text_field( wp_unslash( $_POST[ $input ] ) ) ) {
		$value = sanitize_text_field( wp_unslash( $_POST[ $input ] ) );
	}

	if ( $echo ) {
		echo esc_attr( $value );
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
function leaky_paywall_get_current_currency_symbol() {

	$currency   = leaky_paywall_get_currency();
	$currencies = leaky_paywall_supported_currencies();

	return $currencies[ $currency ]['symbol'];
}

/**
 * Check if the current registration has an amount equal to zero (and thus free)
 *
 * @since 4.7.1
 * @param array $meta Registration data.
 * @return bool
 */
function leaky_paywall_is_free_registration( $meta ) {

	if ( $meta['price'] > 0 ) {
		$is_free = false;
	} else {
		$is_free = true;
	}

	return apply_filters( 'leaky_paywall_is_free_registration', $is_free, $meta );
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
 * @param array|object $data the data to store.
 * @param string       $event name of event.
 *
 * @since 4.7.1
 * @return void
 */
function leaky_paywall_log( $data, $event ) {

	leaky_paywall_debug_log( $event . ' | ' . wp_json_encode( $data ) );

}

/**
 * Show Leaky Paywall profile fields on user
 *
 * @param object $user The user object.
 */
function leaky_paywall_show_extra_profile_fields( $user ) {

	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();

	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
	if ( $level_id ) {
		$level = get_leaky_paywall_subscription_level( $level_id );
	}
	$description      = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_description' . $site, true );
	$gateway          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
	$status           = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
	$expires          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
	$plan             = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
	$subscriber_id    = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
	$subscriber_notes = get_user_meta( $user->ID, '_leaky_paywall_subscriber_notes', true );

	if ( ! $level_id ) {
		return;
	}

	if ( false !== strpos( $subscriber_id, 'cus_' )) {
		leaky_paywall_sync_stripe_subscription( $user );
	}

	?>

		<h3>Leaky Paywall</h3>

		<table class="form-table">

			<tr>
				<th><label for="level_id">Level ID</label></th>

				<td>
				<?php echo esc_attr( $level_id ); ?>

				</td>
			</tr>

			<tr>
				<th><label for="level_description">Level Description</label></th>

				<td>
				<?php echo esc_attr( $level['label'] ); ?>

				</td>
			</tr>

			<tr>
				<th><label for="payment_gateway">Payment Gateway</label></th>

				<td>
				<?php echo esc_attr( $gateway ); ?>

				</td>
			</tr>

			<tr>
				<th><label for="payment_status">Payment Status</label></th>

				<td>
				<?php echo esc_attr( $status ); ?>

				</td>
			</tr>

			<tr>
				<th><label for="expires">Expires</label></th>

				<td>
				<?php echo esc_attr( $expires ); ?>

				</td>
			</tr>

		<?php
		if ( $plan ) {
			?>
				<tr>
					<th><label for="plan">Plan</label></th>

					<td>
					<?php echo esc_attr( $plan ); ?>

					</td>
				</tr>
			<?php
		}
		?>


			<?php
			if ( $subscriber_id ) {
				?>
				<tr>
					<th><label for="subscriber_id">Subscriber ID</label></th>

					<td>
						<?php echo esc_attr( $subscriber_id ); ?>

					</td>
				</tr>
				<?php
			}
			?>

			<?php
			if ( $subscriber_notes ) {
				?>
				<tr>
					<th><label for="subscriber_notes">Subscriber Notes</label></th>

					<td>
						<?php echo esc_attr( $subscriber_notes ); ?>

					</td>
				</tr>
				<?php
			}
			?>


		</table>
	<?php
}
add_action( 'show_user_profile', 'leaky_paywall_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'leaky_paywall_show_extra_profile_fields' );

/**
 * Add settings link to plugin table for Leaky Paywall
 *
 * @since 4.10.4
 * @param array $links default plugin links.
 * @return array $links
 */
function leaky_paywall_plugin_add_settings_link( $links ) {
	$settings_link  = '<a target="_blank" href="https://leakypaywall.com/pricing/">' . __( 'Premium Support' ) . '</a>  | ';
	$settings_link .= '<a href="admin.php?page=issuem-leaky-paywall">' . __( 'Settings' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . LEAKY_PAYWALL_BASENAME, 'leaky_paywall_plugin_add_settings_link' );

/**
 * Plugin row meta links for add ons
 *
 * @since 4.10.4
 * @param array  $input already defined meta links.
 * @param string $file plugin file path and name being processed.
 * @return array $input
 */
function leaky_paywall_plugin_row_meta( $input, $file ) {

	if ( 'leaky-paywall/leaky-paywall.php' !== $file ) {
		return $input;
	}

	$lp_link = esc_url(
		add_query_arg(
			array(
				'utm_source'   => 'plugins-page',
				'utm_medium'   => 'plugin-row',
				'utm_campaign' => 'admin',
			),
			'https://leakypaywall.com/downloads/category/leaky-paywall-addons/'
		)
	);

	$links = array(
		'<a href="' . $lp_link . '">' . esc_html__( 'Add-Ons', 'leaky-paywall' ) . '</a>',
	);

	$input = array_merge( $input, $links );

	return $input;
}
add_filter( 'plugin_row_meta', 'leaky_paywall_plugin_row_meta', 10, 2 );

/**
 * Check if Leaky Paywall Recurring plugin is active
 */
function is_leaky_paywall_recurring() {

	$settings  = get_leaky_paywall_settings();
	$recurring = false;

	if ( ! isset( $settings['post_4106'] ) ) {
		$recurring = true;
	}

	if ( is_plugin_active( 'leaky-paywall-recurring-payments/leaky-paywall-recurring-payments.php' ) ) {
		$recurring = true;
	}

	return $recurring;
}



/**
 * Maybe dlete user
 */
function leaky_paywall_maybe_delete_user() {

	if ( ! isset( $_POST['leaky-paywall-delete-account-nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['leaky-paywall-delete-account-nonce'] ) ), 'leaky-paywall-delete-account' ) ) {
		return;
	}

	$settings = get_leaky_paywall_settings();

	require_once ABSPATH . 'wp-admin/includes/user.php';

	$user = wp_get_current_user();

	if ( in_array( 'subscriber', $user->roles, true ) ) {

		wp_delete_user( $user->ID );

		do_action( 'leaky_paywall_after_user_deleted', $user );

		$admin_message = '';
		$headers       = array();

		$admin_emails = array();
		$admin_emails = get_option( 'admin_email' );

		$site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
		$from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : $site_name;
		$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' );

		$headers[] = 'From: ' . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>";
		$headers[] = 'Reply-To: ' . $from_email;
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$admin_message = '<p>The user ' . $user->user_email . ' has deleted their account.</p>';

		/* Translators: %s - site name */
		wp_mail( $admin_emails, sprintf( esc_attr__( 'User Account Deleted on %s', 'leaky-paywall' ), $site_name ), $admin_message, $headers );

		wp_die( wp_kses_post( '<p>Your account has been deleted. Your access and information has been removed.</p><p><a href="' . home_url() . '">Continue</a></p>' ), 'Account Deleted' );
	}

	wp_die( wp_kses_post( '<p>Your user role cannot be deleted from the My Account page. Please contact a site administrator.</p><p><a href="' . home_url() . '">Continue</a></p>' ), 'Account Deleted' );
}
add_action( 'init', 'leaky_paywall_maybe_delete_user' );


/**
 * Get level display price
 *
 * @param array $level Leaky Paywall level.
 */
function leaky_paywall_get_level_display_price( $level ) {

	$settings = get_leaky_paywall_settings();

	$currency_position  = $settings['leaky_paywall_currency_position'];
	$thousand_separator = $settings['leaky_paywall_thousand_separator'];
	$decimal_separator  = $settings['leaky_paywall_decimal_separator'];
	$decimal_number     = empty( $settings['leaky_paywall_decimal_number'] ) ? '0' : $settings['leaky_paywall_decimal_number'];
	$currency_symbol    = leaky_paywall_get_current_currency_symbol();

	$price        = $level['price'];
	$broken_price = explode( '.', $price );

	$before_decimal = $broken_price[0];
	$after_decimal  = substr( isset( $broken_price[1] ) ? $broken_price[1] : '', 0, $decimal_number );

	if ( ! $after_decimal && 2 === $decimal_number ) {
		$after_decimal = '00';
	}

	if ( $price > 0 ) {

		$decimal          = $after_decimal ? $decimal_separator : '';
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
				$display_price = $formatted_number . ' ' . $currency_symbol;
				break;
			default:
				$display_price = $currency_symbol . $formatted_number;
				break;
		}
	} else {
		$display_price = __( 'Free', 'leaky-paywall' );
	}

	return apply_filters( 'leaky_paywall_display_price', $display_price, $level );
}


/**
 * Replace language-specific characters by ASCII-equivalents.
 *
 * @param string $s Initial string.
 * @return string
 */
function leaky_paywall_normalize_chars( $s ) {
	$replace = array(
		'ъ' => '-',
		'Ь' => '-',
		'Ъ' => '-',
		'ь' => '-',
		'Ă' => 'A',
		'Ą' => 'A',
		'À' => 'A',
		'Ã' => 'A',
		'Á' => 'A',
		'Æ' => 'A',
		'Â' => 'A',
		'Å' => 'A',
		'Ä' => 'Ae',
		'Þ' => 'B',
		'Ć' => 'C',
		'ץ' => 'C',
		'Ç' => 'C',
		'È' => 'E',
		'Ę' => 'E',
		'É' => 'E',
		'Ë' => 'E',
		'Ê' => 'E',
		'Ğ' => 'G',
		'İ' => 'I',
		'Ï' => 'I',
		'Î' => 'I',
		'Í' => 'I',
		'Ì' => 'I',
		'Ł' => 'L',
		'Ñ' => 'N',
		'Ń' => 'N',
		'Ø' => 'O',
		'Ó' => 'O',
		'Ò' => 'O',
		'Ô' => 'O',
		'Õ' => 'O',
		'Ö' => 'Oe',
		'Ş' => 'S',
		'Ś' => 'S',
		'Ș' => 'S',
		'Š' => 'S',
		'Ț' => 'T',
		'Ù' => 'U',
		'Û' => 'U',
		'Ú' => 'U',
		'Ü' => 'Ue',
		'Ý' => 'Y',
		'Ź' => 'Z',
		'Ž' => 'Z',
		'Ż' => 'Z',
		'â' => 'a',
		'ǎ' => 'a',
		'ą' => 'a',
		'á' => 'a',
		'ă' => 'a',
		'ã' => 'a',
		'Ǎ' => 'a',
		'а' => 'a',
		'А' => 'a',
		'å' => 'a',
		'à' => 'a',
		'א' => 'a',
		'Ǻ' => 'a',
		'Ā' => 'a',
		'ǻ' => 'a',
		'ā' => 'a',
		'ä' => 'ae',
		'æ' => 'ae',
		'Ǽ' => 'ae',
		'ǽ' => 'ae',
		'б' => 'b',
		'ב' => 'b',
		'Б' => 'b',
		'þ' => 'b',
		'ĉ' => 'c',
		'Ĉ' => 'c',
		'Ċ' => 'c',
		'ć' => 'c',
		'ç' => 'c',
		'ц' => 'c',
		'צ' => 'c',
		'ċ' => 'c',
		'Ц' => 'c',
		'Č' => 'c',
		'č' => 'c',
		'Ч' => 'ch',
		'ч' => 'ch',
		'ד' => 'd',
		'ď' => 'd',
		'Đ' => 'd',
		'Ď' => 'd',
		'đ' => 'd',
		'д' => 'd',
		'Д' => 'D',
		'ð' => 'd',
		'є' => 'e',
		'ע' => 'e',
		'е' => 'e',
		'Е' => 'e',
		'Ə' => 'e',
		'ę' => 'e',
		'ĕ' => 'e',
		'ē' => 'e',
		'Ē' => 'e',
		'Ė' => 'e',
		'ė' => 'e',
		'ě' => 'e',
		'Ě' => 'e',
		'Є' => 'e',
		'Ĕ' => 'e',
		'ê' => 'e',
		'ə' => 'e',
		'è' => 'e',
		'ë' => 'e',
		'é' => 'e',
		'ф' => 'f',
		'ƒ' => 'f',
		'Ф' => 'f',
		'ġ' => 'g',
		'Ģ' => 'g',
		'Ġ' => 'g',
		'Ĝ' => 'g',
		'Г' => 'g',
		'г' => 'g',
		'ĝ' => 'g',
		'ğ' => 'g',
		'ג' => 'g',
		'Ґ' => 'g',
		'ґ' => 'g',
		'ģ' => 'g',
		'ח' => 'h',
		'ħ' => 'h',
		'Х' => 'h',
		'Ħ' => 'h',
		'Ĥ' => 'h',
		'ĥ' => 'h',
		'х' => 'h',
		'ה' => 'h',
		'î' => 'i',
		'ï' => 'i',
		'í' => 'i',
		'ì' => 'i',
		'į' => 'i',
		'ĭ' => 'i',
		'ı' => 'i',
		'Ĭ' => 'i',
		'И' => 'i',
		'ĩ' => 'i',
		'ǐ' => 'i',
		'Ĩ' => 'i',
		'Ǐ' => 'i',
		'и' => 'i',
		'Į' => 'i',
		'י' => 'i',
		'Ї' => 'i',
		'Ī' => 'i',
		'І' => 'i',
		'ї' => 'i',
		'і' => 'i',
		'ī' => 'i',
		'ĳ' => 'ij',
		'Ĳ' => 'ij',
		'й' => 'j',
		'Й' => 'j',
		'Ĵ' => 'j',
		'ĵ' => 'j',
		'я' => 'ja',
		'Я' => 'ja',
		'Э' => 'je',
		'э' => 'je',
		'ё' => 'jo',
		'Ё' => 'jo',
		'ю' => 'ju',
		'Ю' => 'ju',
		'ĸ' => 'k',
		'כ' => 'k',
		'Ķ' => 'k',
		'К' => 'k',
		'к' => 'k',
		'ķ' => 'k',
		'ך' => 'k',
		'Ŀ' => 'l',
		'ŀ' => 'l',
		'Л' => 'l',
		'ł' => 'l',
		'ļ' => 'l',
		'ĺ' => 'l',
		'Ĺ' => 'l',
		'Ļ' => 'l',
		'л' => 'l',
		'Ľ' => 'l',
		'ľ' => 'l',
		'ל' => 'l',
		'מ' => 'm',
		'М' => 'm',
		'ם' => 'm',
		'м' => 'm',
		'ñ' => 'n',
		'н' => 'n',
		'Ņ' => 'n',
		'ן' => 'n',
		'ŋ' => 'n',
		'נ' => 'n',
		'Н' => 'n',
		'ń' => 'n',
		'Ŋ' => 'n',
		'ņ' => 'n',
		'ŉ' => 'n',
		'Ň' => 'n',
		'ň' => 'n',
		'о' => 'o',
		'О' => 'o',
		'ő' => 'o',
		'õ' => 'o',
		'ô' => 'o',
		'Ő' => 'o',
		'ŏ' => 'o',
		'Ŏ' => 'o',
		'Ō' => 'o',
		'ō' => 'o',
		'ø' => 'o',
		'ǿ' => 'o',
		'ǒ' => 'o',
		'ò' => 'o',
		'Ǿ' => 'o',
		'Ǒ' => 'o',
		'ơ' => 'o',
		'ó' => 'o',
		'Ơ' => 'o',
		'œ' => 'oe',
		'Œ' => 'oe',
		'ö' => 'oe',
		'פ' => 'p',
		'ף' => 'p',
		'п' => 'p',
		'П' => 'p',
		'ק' => 'q',
		'ŕ' => 'r',
		'ř' => 'r',
		'Ř' => 'r',
		'ŗ' => 'r',
		'Ŗ' => 'r',
		'ר' => 'r',
		'Ŕ' => 'r',
		'Р' => 'r',
		'р' => 'r',
		'ș' => 's',
		'с' => 's',
		'Ŝ' => 's',
		'š' => 's',
		'ś' => 's',
		'ס' => 's',
		'ş' => 's',
		'С' => 's',
		'ŝ' => 's',
		'Щ' => 'sch',
		'щ' => 'sch',
		'ш' => 'sh',
		'Ш' => 'sh',
		'ß' => 'ss',
		'т' => 't',
		'ט' => 't',
		'ŧ' => 't',
		'ת' => 't',
		'ť' => 't',
		'ţ' => 't',
		'Ţ' => 't',
		'Т' => 't',
		'ț' => 't',
		'Ŧ' => 't',
		'Ť' => 't',
		'™' => 'tm',
		'ū' => 'u',
		'у' => 'u',
		'Ũ' => 'u',
		'ũ' => 'u',
		'Ư' => 'u',
		'ư' => 'u',
		'Ū' => 'u',
		'Ǔ' => 'u',
		'ų' => 'u',
		'Ų' => 'u',
		'ŭ' => 'u',
		'Ŭ' => 'u',
		'Ů' => 'u',
		'ů' => 'u',
		'ű' => 'u',
		'Ű' => 'u',
		'Ǖ' => 'u',
		'ǔ' => 'u',
		'Ǜ' => 'u',
		'ù' => 'u',
		'ú' => 'u',
		'û' => 'u',
		'У' => 'u',
		'ǚ' => 'u',
		'ǜ' => 'u',
		'Ǚ' => 'u',
		'Ǘ' => 'u',
		'ǖ' => 'u',
		'ǘ' => 'u',
		'ü' => 'ue',
		'в' => 'v',
		'ו' => 'v',
		'В' => 'v',
		'ש' => 'w',
		'ŵ' => 'w',
		'Ŵ' => 'w',
		'ы' => 'y',
		'ŷ' => 'y',
		'ý' => 'y',
		'ÿ' => 'y',
		'Ÿ' => 'y',
		'Ŷ' => 'y',
		'Ы' => 'y',
		'ž' => 'z',
		'З' => 'z',
		'з' => 'z',
		'ź' => 'z',
		'ז' => 'z',
		'ż' => 'z',
		'ſ' => 'z',
		'Ж' => 'zh',
		'ж' => 'zh',
	);
	return strtr( $s, $replace );
}

/**
 * Add leaky paywall links to admin toolbar
 *
 * @param object $admin_bar The admin bar object.
 */
function leaky_paywall_add_toolbar_items( $admin_bar ) {

	if ( ! current_user_can( 'edit_user' ) ) {
		return;
	}

	$admin_bar->add_menu(
		array(
			'id'    => 'leaky-paywall-toolbar',
			'title' => 'Leaky Paywall',
			'href'  => admin_url() . 'admin.php?page=issuem-leaky-paywall',
			'meta'  => array(
				'title' => __( 'Leaky Paywall' ),
			),
		)
	);
	$admin_bar->add_menu(
		array(
			'id'     => 'leaky-paywall-toolbar-settings',
			'parent' => 'leaky-paywall-toolbar',
			'title'  => 'Settings',
			'href'   => admin_url() . 'admin.php?page=issuem-leaky-paywall',
			'meta'   => array(
				'title'  => __( 'Settings' ),
				'target' => '',
				'class'  => 'my_menu_item_class',
			),
		)
	);
	$admin_bar->add_menu(
		array(
			'id'     => 'leaky-paywall-toolbar-subscribers',
			'parent' => 'leaky-paywall-toolbar',
			'title'  => 'Subscribers',
			'href'   => admin_url() . 'admin.php?page=leaky-paywall-subscribers',
			'meta'   => array(
				'title'  => __( 'Subscribers' ),
				'target' => '',
				'class'  => 'my_menu_item_class',
			),
		)
	);

	$admin_bar->add_menu(
		array(
			'id'     => 'leaky-paywall-toolbar-transactions',
			'parent' => 'leaky-paywall-toolbar',
			'title'  => 'Transactions',
			'href'   => admin_url() . 'edit.php?post_type=lp_transaction',
			'meta'   => array(
				'title'  => __( 'Transactions' ),
				'target' => '',
				'class'  => 'my_menu_item_class',
			),
		)
	);

}
add_action( 'admin_bar_menu', 'leaky_paywall_add_toolbar_items', 100 );

/**
 * Display Leaky Paywall rate us notice
 */
function leaky_paywall_display_rate_us_notice() {

	$notice_id = 'lp_rate_us_feedback';

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$current_user_has_viewed = get_user_meta( get_current_user_id(), $notice_id, true );

	if ( 'dashboard' !== get_current_screen()->id || $current_user_has_viewed ) {
		return;
	}

	$site = leaky_paywall_get_current_site();

	$args = array(
		'number'     => 11,
		'meta_query' => array(
			array(
				'key'     => '_issuem_leaky_paywall_live_level_id' . $site,
				'compare' => 'EXISTS',
			),
		),
	);

	$wp_user_search         = new WP_User_Query( $args );
	$total_live_subscribers = count( $wp_user_search->get_results() );

	if ( 100 >= $total_live_subscribers ) {
		return;
	}

	$dismiss_url = add_query_arg(
		array(
			'action'    => 'leaky_paywall_set_admin_notice_viewed',
			'notice_id' => esc_attr( $notice_id ),
		),
		admin_url()
	);

	?>
		<div class="notice updated is-dismissible leaky-paywall-message leaky-paywall-message-dismissed" data-notice_id="<?php echo esc_attr( $notice_id ); ?>">
			<div class="leaky-paywall-message-inner">

				<div class="leaky-paywall-message-content">
					<p><strong><?php echo esc_html__( 'Congrats!', 'leaky-paywall' ); ?></strong> 🥳<?php esc_html_e( 'You have more than 100 subscribers with Leaky Paywall. Please help us by leaving a review on WordPress.org. We read every review and use your feedback to make Leaky Paywall better for everyone!', 'leaky-paywall' ); ?></p>
					<p class="leaky-paywall-message-actions">
						<a href="https://wordpress.org/support/plugin/leaky-paywall/reviews/?filter=5/#new-post" target="_blank" class="button button-primary"><?php esc_html_e( 'Leave a Review', 'leaky-paywall' ); ?></a>
						<a href="<?php echo esc_url_raw( $dismiss_url ); ?>" class="button leaky-paywall-button-notice-dismiss"><?php esc_html_e( 'Hide', 'leaky-paywall' ); ?></a>
					</p>
				</div>
				<div class="leaky-paywall-message-logo">
					<img src="<?php echo esc_url( LEAKY_PAYWALL_URL ); ?>/images/zeen101-logo.png" alt="ZEEN101" width="100">
				</div>
			</div>
		</div>

		<style>
			.leaky-paywall-message-inner {
				overflow: hidden;
				width: 100%;
			}

			.leaky-paywall-message-inner .leaky-paywall-message-content {
				width: 60%;
				float: left;
			}

			.leaky-paywall-message-inner .leaky-paywall-message-logo {
				width: 20%;
				float: right;
				text-align: right;
				padding-top: 7px;
				padding-bottom: 7px;
			}
		</style>
	<?php
}
add_action( 'admin_notices', 'leaky_paywall_display_rate_us_notice', 20 );

/**
 * Update admin notice viewed
 */
function leaky_paywall_update_admin_notice_viewed() {

	if ( ! isset( $_GET['action'] ) ) {
		return;
	}

	if ( 'leaky_paywall_set_admin_notice_viewed' !== $_GET['action'] ) {
		return;
	}

	if ( ! isset( $_GET['notice_id'] ) ) {
		return;
	}

	update_user_meta( get_current_user_id(), sanitize_text_field( wp_unslash( $_GET['notice_id'] ) ), true );
}
add_action( 'admin_init', 'leaky_paywall_update_admin_notice_viewed' );

/**
 * Get a Transaction ID from an email address
 *
 * @param string $email The email address.
 */
function leaky_paywall_get_transaction_id_from_email( $email ) {

	$transaction_id = '';

	$args = array(
		'post_type'       => 'lp_transaction',
		'number_of_posts' => 1,
		'meta_query'      => array(
			array(
				'key'     => '_email',
				'value'   => $email,
				'compare' => '=',
			),
		),
	);

	$transactions = get_posts( $args );

	if ( ! empty( $transactions ) ) {
		$transaction    = $transactions[0];
		$transaction_id = $transaction->ID;
	}

	return $transaction_id;
}

/**
 * Sets a Gateway Transaction ID in post meta for the given Transaction ID
 *
 * @since  4.14.5
 * @param int    $transaction_id Transaction ID.
 * @param string $gateway_transaction_id The transaction ID from the gateway.
 */
function leaky_paywall_set_payment_transaction_id( $transaction_id, $gateway_transaction_id ) {

	update_post_meta( $transaction_id, '_gateway_txn_id', $gateway_transaction_id );
}


/**
 * Check login fail
 *
 * @param string $username The username.
 */
function leaky_paywall_login_fail( $username ) {

	$settings       = get_leaky_paywall_settings();
	$referrer       = wp_get_referer();
	$clean_referrer = str_replace( array( 'http://', 'https://' ), '', $referrer );
	$login_link     = str_replace( array( 'http://', 'https://' ), '', get_page_link( $settings['page_for_login'] ) );
	$profile_link   = str_replace( array( 'http://', 'https://' ), '', get_page_link( $settings['page_for_profile'] ) );

	// Only run this check if the user was on the leaky paywall login page or profile page. This keeps it from breaking other login plugins.
	if ( $clean_referrer !== $login_link && $clean_referrer !== $profile_link ) {
		return;
	}

	if ( ! empty( $referrer ) && ! strstr( $referrer, 'wp-login' ) && ! strstr( $referrer, 'wp-admin' ) ) {
		wp_safe_redirect( $referrer . '/?login=failed' );
		exit();
	}
}
add_action( 'wp_login_failed', 'leaky_paywall_login_fail' );
