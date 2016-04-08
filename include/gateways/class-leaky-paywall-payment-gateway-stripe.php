<?php
/**
 * Stripe Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.70.0
*/

class Leaky_Paywall_Payment_Gateway_Stripe extends Leaky_Paywall_Payment_Gateway {

	private $secret_key;
	protected $publishable_key;

	/**
	 * Get things going
	 *
	 * @since  3.5.1
	 */
	public function init() {

		$settings = get_leaky_paywall_settings();

		$this->supports[]	= 'one-time';
		$this->supports[]	= 'recurring';
		// $this->supports[]	= 'fees';

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
	 * @since 3.7.0
	 */
	public function process_signup() {

		if( empty( $_POST['stripeToken'] ) ) {
			wp_die( __( 'Missing Stripe token, please try again or contact support if the issue persists.', 'rcp' ), __( 'Error', 'rcp' ), array( 'response' => 400 ) );
		}

		\Stripe\Stripe::setApiKey( $this->secret_key );

		$paid   = false;
		$customer_exists = false;


		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		$level = get_level_by_level_id( $this->level_id );

		$cu = false;

		try {

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
				if ( $user = get_user_by( 'email', $this->email ) ) {
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
				'email' => $this->email,
				'source'  => $_POST['stripeToken'],
				'description'	=> $this->level_name
			);

			$customer_array = apply_filters( 'leaky_paywall_process_stripe_payment_customer_array', $customer_array );

			// recurring subscription
			if ( !empty( $this->recurring ) && 'on' === $this->recurring && !empty( $this->plan_id ) ) {

				$customer_array['plan'] = $this->plan_id;

				if ( !empty( $cu ) ) {
					$subscriptions = $cu->subscriptions->all( 'limit=1' );
					
					if ( !empty( $subscriptions->data ) ) {
						foreach( $subscriptions->data as $subscription ) {
							$sub = $cu->subscriptions->retrieve( $subscription->id );
							$sub->plan = $this->plan_id;
							$sub->save();
						}
					} else {
						$cu->subscriptions->create( array( 'plan' => $this->plan_id ) );
					}
					
				} else {

					// new customer, and this will charge them?
					$cu = Stripe_Customer::create( $customer_array );
				}

			} else {

				// Create a Customer
				if ( empty( $cu ) ) {
					$cu = Stripe_Customer::create( $customer_array );
				} else {
					$cu->cards->create( array( 'card' => $_POST['stripeToken'] ) );
				}


				$charge_array = array(
					'customer'	=> $cu->id,
					'amount'	=> $this->amount,
					'currency'	=> apply_filters( 'leaky_paywall_stripe_currency', strtolower( $this->currency ) ),
					'description'	=> $this->level_name,
				);

				$charge = Stripe_Charge::create( $charge_array );


			}

		} catch ( Exception $e ) {
				
			return new WP_Error( 'broke', sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) );
			
		}

		$customer_id = $cu->id;


		$meta_args = array(
			'level_id'			=> $this->level_id,
			'subscriber_id' 	=> $customer_id,
			'subscriber_email' 	=> $this->email,
			'price' 			=> $this->level_price,
			'description' 		=> $this->level_name,
			'payment_gateway' 	=> 'stripe',
			'payment_status' 	=> 'active',
			'interval' 			=> $this->length_unit,
			'interval_count' 	=> $this->length,
			'site' 				=> !empty( $level['site'] ) ? $level['site'] : '',
			'plan' 				=> !empty( $customer_array['plan'] ) ? $customer_array['plan'] : '',
		);


		if ( is_user_logged_in() || !empty( $existing_customer ) ) {
			$user_id = leaky_paywall_update_subscriber( NULL,  $this->email, $customer_id, $meta_args ); //if the email already exists, we want to update the subscriber, not create a new one
		} else {
			// create the new customer as a leaky paywall subscriber
			$user_id = leaky_paywall_new_subscriber( NULL,  $this->email, $customer_id, $meta_args );
		}


		if ( $user_id ) {

			do_action( 'leaky_paywall_stripe_signup', $user_id );
			
			// don't want to log them in for now, but maybe in the future
			// wp_set_current_user( $user_id );
			// wp_set_auth_cookie( $user_id );
			
			// redirect user after sign up
			if ( !empty( $settings['page_for_after_subscribe'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_after_subscribe'] ) );
			} else if ( !empty( $settings['page_for_profile'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
			} else if ( !empty( $settings['page_for_subscription'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
			}

			exit;

		} else {

			wp_die( __( 'An error occurred, please contact the site administrator: ', 'leaky-paywall' ) . get_bloginfo( 'admin_email' ), __( 'Error', 'leaky-paywall' ), array( 'response' => '401' ) );

		}

	}

	/**
	 * Add credit card fields
	 *
	 * @since 3.5.1
	 */
	public function fields() {

		ob_start();
		?>
			
			<script type="text/javascript">

			var leaky_paywall_script_options;
			var leaky_paywall_processing;
			var leaky_paywall_stripe_processing = false;

			  // This identifies your website in the createToken call below
			  Stripe.setPublishableKey('<?php echo $this->publishable_key; ?>');

			  function stripeResponseHandler(status, response) {

			  	if (response.error) {
			  		// re-enable th submit button
			  		jQuery('#leaky-paywall-payment-form #leaky-paywall-submit').attr("disabled", false );

			  		// jQuery('#leaky-paywall-registration-form').unblock();
			  		jQuery('#leaky-paywall-submit').before('<div class="leaky-paywall-message error"><p class="leaky-paywall-error"><span>' + response.error.message + '</span></p></div>' );

			  		leaky_paywall_stripe_processing = false;
			  		leaky_paywall_processing = false;

			  	} else {

			  		var form$ = jQuery('#leaky-paywall-payment-form');
			  		var token = response['id'];

			  		form$.append('<input type="hidden" name="stripeToken" value="' + token + '" />');

			  		form$.get(0).submit();

			  	}
			  }

			  jQuery(document).ready(function($) {

			  	$('#leaky-paywall-payment-form').on('submit', function(e) {
 
			  		if ( ! leaky_paywall_stripe_processing ) {

			  			leaky_paywall_stripe_processing = true;

			  			// get the price
			  			$('input[name="stripe_price"]').val();

			  			// disabl the submit button to prevent repeated clicks
			  			$('#leaky-paywall-payment-form #leaky-paywall-submit').attr('disabled', 'disabled' );

			  			// create Stripe token
			  			Stripe.createToken({
			  				number: $('.card-num').val(),
			  				name: $('.card-name').val(),
			  				cvc: $('.cvc').val(),
			  				exp_month: $('.exp-month').val(),
			  				exp_year: $('.exp-year').val(),
			  				address_zip: $('.card-zip').val(),
			  			}, stripeResponseHandler);

			  			return false;
			  		}
			  	});
			  });


			</script>

		<?php 

		return ob_get_clean();

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
	 * @since  3.7.0
	 */
	public function scripts() {
		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', array( 'jquery' ) );
	}

}
