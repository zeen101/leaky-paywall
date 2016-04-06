<?php
/**
 * Stripe Payment Gateway Class
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.1
*/

class Leaky_Paywall_Payment_Gateway_Stripe extends Leaky_Paywall_Payment_Gateway {

	private $secret_key;
	private $publishable_key;

	/**
	 * Get things going
	 *
	 * @since  3.5.1
	 */
	public function init() {

		$settings = get_leaky_paywall_settings();

		$this->supports[]	= 'one-time';
		$this->supports[]	= 'recurring';
		$this->supports[]	= 'fees';

		$this->test_mode	= isset( $settings['test_mode'] );

		if ( $this->test_mode ) {

			$this->secret_key = isset( $settings['test_secret_key'] ) ? trim( $settings['test_secret_key'] ) : '';
			$this->publishable_key = isset( $settings['test_publishable_key'] ) ? trim( $settings['test_publishable_key'] ) : '';

		} else {

			$this->secret_key = isset( $settings['live_secret_key'] ) ? trim( $settings['live_secret_key'] ) : '';
			$this->publishable_key = isset( $settings['live_publishable_key'] ) ? trim( $settings['live_publishable_key'] ) : '';

		}

		if ( ! class_exists( 'Stripe\Stripe' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/lib/Stripe.php';
		}

	}

	/**
	 * Process registration
	 *
	 * @since 3.5.1
	 */
	public function process_signup() {

		\Stripe\Stripe::setApiKey( $this->secret_key );

		// $paid = false;
		// $member = new Leaky_Paywall_Member( $this->user_id );
		// $customer_exists = false;

		if ( empty( $_POST['stripeToken'] ) ) {
			wp_die( __( 'Missing Stripe token, please try again or contact support if the issue persists.', 'issuem-leaky-paywall' ), __('Error', 'issuem-leaky-paywall' ), array( 'response' => 400 ) );
		}

		if ( $this->auto_renew ) {

			// @todo do auto renew functionality
		} else {

			// process a one time payment signup
			
			try {

				// $charge = 
				
			} 
			
		}
		

		
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

					$charge_array['customer'] 	 = $cu->id;
					$charge_array['amount']      = $amount;
					$charge_array['currency']    = apply_filters( 'leaky_paywall_stripe_currency', $currency );
					$charge_array['description'] = $level['label'];
					
					$charge = Stripe_Charge::create( $charge_array );
				}
								
				$customer_id = $cu->id;
				
				$args = array(
					'level_id'			=> $_POST['custom'],
					'subscriber_id' 	=> $customer_id,
					'subscriber_email' 	=> $_POST['stripeEmail'],
					'price' 			=> $level['price'],
					'description' 		=> $level['label'],
					'payment_gateway' 	=> 'stripe',
					'payment_status' 	=> 'active',
					'interval' 			=> $level['interval'],
					'interval_count' 	=> $level['interval_count'],
					'site' 				=> !empty( $level['site'] ) ? $level['site'] : '',
					'plan' 				=> !empty( $customer_array['plan'] ) ? $customer_array['plan'] : '',
				);
					
				if ( is_user_logged_in() || !empty( $existing_customer ) ) {
					$user_id = leaky_paywall_update_subscriber( NULL, $_POST['stripeEmail'], $customer_id, $args ); //if the email already exists, we want to update the subscriber, not create a new one
				} else {
					$user_id = leaky_paywall_new_subscriber( NULL, $_POST['stripeEmail'], $customer_id, $args );
				}
				
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id );
					
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


	/**
	 * Process webhooks
	 *
	 * @since 3.5.1
	 */
	public function process_webhooks() {

		if ( ! isset( $_GET['listener'] ) || strtolower( $_GET['listener'] ) != 'stripe' ) {
			return;
		}

		// Ensure listener URL is not cached by W3TC
		define( 'DONOTCACHEPAGE', true );

		\Stripe\Stripe::setApiKey( $this->secret_key );

		// retrieve the request's body and parse it as JSON
		$body			= @file_get_contents( 'php://input' );
		$event_json_id	= json_decode( $body );

		// for extra security, retrieve from the Stripe API
		if ( isset( $event_json_id->id ) ) {

			$leaky_paywall_payments = new Leaky_Paywall_Payments();

			$event_id = $event_json_id->id;

			try {

				$event 			= \Stripe\Event::retrieve( $event_id );
				$payment_event	= $event->data->object;

				if ( empty( $payment_event->customer ) ) {
					die( 'no customer attached' );
				}

				// retrieve the customer who made this payment. This is their stripe user id (only for subscriptions ) 
				$user = leaky_paywall_get_stripe_id_from_user_id( $payment_event->customer );

				if ( empty( $user ) ) {
					die( 'no user ID found' );
				}

				$subscriber = new Leaky_Paywall_Subscriber( $user );

				// check to confirm this is a Stripe subscriber
				if ( $subscriber ) {

					if ( ! $subscriber->get_subscription_id() ) {
						die( 'No subscription ID for subscriber' );
					}

					// @todo continue here....

				}

			}
		}
	}


	/**
	 * Add credit card fields
	 *
	 * @since 3.5.1
	 */
	public function fields() {
		
	}

	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 3.5.1
	 */
	public function validate_fields() {

		if ( empty( $_POST['card_number'] ) ) {
			leaky_paywall_errors()->add( 'missing_card_number', __( 'The card number you entered is invalid', 'issuem-leaky-paywall' ), 'register' );
		}

	}

	/**
	 * Load Stripe JS
	 *
	 * @since  3.5.1 
	 */
	public function scripts() {
		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', array( 'jquery' ) );
	}

	/**
	 * Create plan in Stripe
	 *
	 * @since  3.5.1 
	 * @return bool 
	 */
	private function create_plan( $plan_name = '' ) {

		$settings = get_leaky_paywall_settings();

		// get all subscription level info for this plan
		$plan 			= leaky_paywall_get_subscription_details( $plan_name );
		$price			= $plan->price * 100;
		$interval 		= $plan->duration_unit;
		$interval_count = $plan->duration;
		$name 			= $plan->name;
		$plan_id		= strtolower( str_replace( ' ', '', $plan_name ) );
		$currency 		= strtolower( $settings['currency'] );

		\Stripe\Stripe::setApiKey( $this->secret_key );

		try {

			\Stripe\Plan::create( array(
				'amount'		=> $price,
				'interval'		=> $interval,
				'interval_count' => $interval_count,
				'name'			=> $name,
				'currency'		=> $currency,
				'id'			=> $plan_id
			) );

			// plan successfully created
			return true;

		} catch ( Exception $e ) {
			// there was a problem
			return false;
		}

	}

	/**
	 * Determine if a plan exists
	 *
	 * @since  3.5.1 
	 */
	private function plan_exists( $plan_id = '' ) {

		$plan_id = strtolower( str_replace( ' ', '', $plan_id ) );

		\Stripe\Stripe::setApiKey( $this->secret_key );

		try {
			$plan = \Stripe\Plan::retrieve( $plan_id );
			return true;
		} catch ( Exception $e ) {
			return false;
		}

	}

}
