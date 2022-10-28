<?php
/**
 * Paypal Standard Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
 */

 /**
  * This class extends the gateway class for PayPal
  *
  * @since 1.0.0
  */
class Leaky_Paywall_Payment_Gateway_PayPal extends Leaky_Paywall_Payment_Gateway {

	/**
	 * The username
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * The password
	 *
	 * @var string
	 */
	protected $password;

	/**
	 * The signature
	 *
	 * @var string
	 */
	protected $signature;


	/**
	 * Get things going
	 *
	 * @since  4.0.0
	 */
	public function init() {
		$settings = get_leaky_paywall_settings();

		$this->supports[] = 'one-time';
		$this->supports[] = 'recurring';

		$this->test_mode = 'off' === $settings['test_mode'] ? false : true;

		if ( $this->test_mode ) {

			$this->api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
			$this->checkout_url = 'https://www.paypal.com/cgi-bin/webscr';
		} else {

			$this->api_endpoint = 'https://api-3t.paypal.com/nvp';
			$this->checkout_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}
	}

	/**
	 * Fields for the registration form
	 *
	 * @param integer $level_id The level id.
	 */
	public function fields( $level_id ) {

		$settings = get_leaky_paywall_settings();
		$level    = get_leaky_paywall_subscription_level( $level_id );

		if ( 0 == $level['price'] ) {
			return;
		}

		$paypal_button_text  = leaky_paywall_get_registration_checkout_button_text( 'paypal' );
		$default_button_text = leaky_paywall_get_registration_checkout_button_text();

		ob_start(); ?>

		<div class="leaky-paywall-payment-method-container">
			<input id="payment_method_paypal" class="input-radio" name="payment_method" value="paypal_standard" checked="checked" data-order_button_text="<?php echo esc_attr( $paypal_button_text ); ?>" type="radio">
			<label for="payment_method_paypal"> Paypal <img src="<?php echo esc_url( LEAKY_PAYWALL_URL ); ?>images/PP_logo_h_150x38.png" alt="PayPal Logo"></label>
		</div>

		<script>
			jQuery(document).ready(function($) {

				var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();
				var button = $('#leaky-paywall-submit');

				if (method == 'paypal_standard') {
					$('.leaky-paywall-card-details').slideUp();
					button.text('<?php echo esc_js( $paypal_button_text ); ?>');
				}

				$('#leaky-paywall-payment-form input[name="payment_method"]').change(function() {

					var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();

					if (method == 'paypal_standard') {
						$('.leaky-paywall-card-details').slideUp();
						button.text('<?php echo esc_js( $paypal_button_text ); ?>');
					} else {
						$('.leaky-paywall-card-details').slideDown();
						button.text('<?php echo esc_js( $default_button_text ); ?>');
					}

				});

				$('#leaky-paywall-payment-form').on('submit', function(e) {

					var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();

					if (method == 'paypal_standard') {
						// alert('save data and send to paypal');
						// e.preventDefault();
					}

				});


			});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Load any scripts
	 */
	public function scripts() {

	}

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_signup() {

		if ( ! isset( $_POST['leaky_paywall_register_nonce'] ) ) {
			return;
		}
	
		if ( ! wp_verify_nonce( sanitize_key( $_POST['leaky_paywall_register_nonce'] ), 'leaky-paywall-register-nonce' ) ) {
			return;
		}

		$this->save_data_to_transaction();

		// https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/ .
		$settings       = get_leaky_paywall_settings();
		$paypal_sandbox = 'off' === $settings['test_mode'] ? '' : 'sandbox';

		$paypal_args = http_build_query( $this->get_paypal_args(), '', '&' );

		if ( $paypal_sandbox ) {
			$url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?test_ipn=1&' . $paypal_args;
		} else {
			$url = 'https://www.paypal.com/cgi-bin/webscr?' . $paypal_args;
		}

		wp_redirect( $url );
		exit;

		// send data to paypal.
	}

	/**
	 * Get PayPal args
	 */
	protected function get_paypal_args() {
		// documentation: https://developer.paypal.com/docs/classic/paypal-payments-standard/integration-guide/Appx_websitestandard_htmlvariables/ .
		$settings       = get_leaky_paywall_settings();
		$mode           = 'off' === $settings['test_mode'] ? 'live' : 'test';
		$paypal_sandbox = 'off' === $settings['test_mode'] ? '' : 'sandbox';
		$paypal_account = 'on' === $settings['test_mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
		$currency       = leaky_paywall_get_currency();
		$current_user   = wp_get_current_user();

		$level = get_leaky_paywall_subscription_level( $this->level_id );

		$args = array(
			'cmd'           => '_xclick',
			'business'      => $paypal_account,
			'no_note'       => 1,
			'currency_code' => $currency,
			'charset'       => 'utf-8',
			'rm'            => 2,
			'upload'        => 1,
			'return'        => esc_url_raw( add_query_arg( 'leaky-paywall-confirm', 'paypal_standard', get_page_link( $settings['page_for_after_subscribe'] ) ) ),
			'cancel_return' => esc_url_raw( add_query_arg( 'leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_profile'] ) ) ),
			'page_style'    => 'paypal',
			'image_url'     => esc_url_raw( $settings['paypal_image_url'] ),
			'paymentaction' => 'sale',
			'bn'            => 'LeakyPaywall_AddToCart_WPS_US',
			'invoice'       => 'LP-' . $this->level_id . '-' . wp_rand( 99, 999 ),
			'custom'        => $this->email,
			'notify_url'    => esc_url_raw( add_query_arg( 'listener', 'IPN', get_site_url() . '/' ) ),
			'first_name'    => $this->first_name,
			'last_name'     => $this->last_name,
			'email'         => $this->email,
			'amount'        => $this->amount,
			'quantity'      => 1,
			'item_name'     => preg_replace( '/[^A-Za-z0-9 ]/', '', $this->level_name ),
			'item_number'   => $this->level_id,
			'custom'        => $this->email,
			'no_shipping'   => 1,
		);

		if ( ! empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
			unset( $args['amount'] );
			$args['src']       = 1;
			$args['a3']        = $this->amount;
			$args['p3']        = $level['interval_count'];
			$args['t3']        = strtoupper( substr( $level['interval'], 0, 1 ) );
			$args['item_name'] = $this->level_name;
			$args['cmd']       = '_xclick-subscriptions';
			$args['bn']        = 'LeakyPaywall_Subscribe_WPS_US';
		}

		leaky_paywall_log( $args, 'paypal standard args' );

		return apply_filters( 'leaky_paywall_paypal_args', $args );
	}

	/**
	 * Process registration and payment confirmation after returning from PayPal
	 *
	 * @since 4.0.0
	 */
	public function process_confirmation() {
		if ( empty( $_GET['leaky-paywall-confirm'] ) && 'paypal_standard' !== $_GET['leaky-paywall-confirm'] ) {
			return false;
		}

		leaky_paywall_log( $_REQUEST, 'paypal standard confirm data' );

		return; // everything will be handled with webhooks instead of here.

	}


	/**
	 * Process PayPal IPN. This is also where Paypal Subscribe buttons are processed.
	 *
	 * @since 4.0.0
	 */
	public function process_webhooks() {
		
		if ( ! isset( $_POST['txn_type'] ) ) {
			return;
		}

		leaky_paywall_log( $_POST, 'paypal standard ipn' );

		$site           = '';
		$settings       = get_leaky_paywall_settings();
		$mode           = 'off' === $settings['test_mode'] ? 'live' : 'test';
		$payload['cmd'] = '_notify-validate';

		foreach ( $_POST as $key => $value ) {
			// $payload[ $key ] = sanitize_text_field( wp_unslash( $value ) ); // this is breaking too many integrations
			// $payload[ $key ] = $value;
			$payload[$key] = stripslashes( $value );
		}

		if ( 'test' == $mode ) {
			$paypal_api_url = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
		} else {
			$paypal_api_url = 'https://ipnpb.paypal.com/cgi-bin/webscr';
		}

		leaky_paywall_log( $payload, 'paypal standard ipn payload' );

		// https://developer.paypal.com/api/nvp-soap/ipn/IPNTesting/#receive-an-invalid-message
		$response = wp_remote_post(
			$paypal_api_url,
			array(
				'body'        => $payload,
				'httpversion' => '1.1',
			)
		);
		$body = wp_remote_retrieve_body( $response );

		leaky_paywall_log( $body, 'paypal standard ipn body' );

		if ( 'VERIFIED' === $body ) {

			if ( ! empty( $_REQUEST['txn_type'] ) ) {

				if ( isset( $_REQUEST['item_number'] ) ) {
					$level_id = sanitize_text_field( wp_unslash( $_REQUEST['item_number'] ) );
				} else if ( isset( $_REQUEST['item_number1'] ) ) {
					$level_id = sanitize_text_field( wp_unslash( $_REQUEST['item_number1'] ) );
				} else {
					$level_id = '';
				}

				if ( isset( $_REQUEST['item_name'] ) ) {
					$desc = sanitize_text_field( wp_unslash( $_REQUEST['item_name'] ) );
				} else if ( isset( $_REQUEST['item_name1'] ) ) {
					$desc = sanitize_text_field( wp_unslash( $_REQUEST['item_name1'] ) );
				} else {
					$desc = '';
				}

				$args = apply_filters(
					'leaky_paywall_paypal_verified_ipn_args',
					array(
						'level_id'        => $level_id, // should be universal for all PayPal IPNs we're capturing.
						'description'     => $desc, // should be universal for all PayPal IPNs we're capturing.
						'payment_gateway' => 'paypal_standard',
					)
				);

				$level = get_leaky_paywall_subscription_level( $args['level_id'] );

				leaky_paywall_log( $level, 'paypal standard ipn body verified level' );

				// if the level isn't found in Leaky Paywall, we don't want to do edit anything on the user.
				if ( ! $level ) {
					return;
				}

				$args['interval']       = $level['interval'];
				$args['interval_count'] = $level['interval_count'];
				$args['site']           = isset( $level['site'] ) ? $level['site'] : '';

				if ( is_multisite_premium() && ! empty( $level['site'] ) && ! is_main_site( $level['site'] ) ) {
					$site = '_' . $level['site'];
				} else {
					$site = '';
				}

				do_action( 'leaky_paywall_before_process_paypal_webhooks', $args );

				leaky_paywall_log( $args, 'paypal standard ipn before process webhooks' );

				switch ( sanitize_text_field( wp_unslash( $_REQUEST['txn_type'] ) ) ) {

					case 'web_accept':
						if ( isset( $_REQUEST['mc_gross'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['mc_gross'] ) );
						} elseif ( isset( $_REQUEST['payment_gross'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['payment_gross'] ) );
						}

						if ( isset( $_REQUEST['txn_id'] ) ) { // subscr_payment.
							$args['subscr_id'] = sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) );
						}

						$args['plan'] = '';

						if ( isset( $_REQUEST['payment_status'] ) && 'completed' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) ) ) {
							$args['payment_status'] = 'active';
						} else {
							$args['payment_status'] = 'deactivated';
						}
						break;

					case 'cart':
						if ( isset( $_REQUEST['mc_gross1'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['mc_gross1'] ) );
						} elseif ( isset( $_REQUEST['payment_gross'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['payment_gross'] ) );
						}

						if ( isset( $_REQUEST['txn_id'] ) ) { // subscr_payment.
							$args['subscr_id'] = sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) );
						}

						$args['plan'] = '';

						if ( isset( $_REQUEST['payment_status'] ) && 'completed' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) ) ) {
							$args['payment_status'] = 'active';
						} else {
							$args['payment_status'] = 'deactivated';
						}
						break;

					case 'subscr_signup':
						if ( isset( $_REQUEST['mc_amount3'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['mc_amount3'] ) );
						} elseif ( isset( $_REQUEST['amount3'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['amount3'] ) );
						}

						if ( isset( $_REQUEST['subscr_id'] ) ) { // subscr_payment.
							$args['subscr_id'] = sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) );
						}

						if ( isset( $_REQUEST['period3'] ) ) {
							$args['plan'] = sanitize_text_field( wp_unslash( $_REQUEST['period3'] ) );
							if ( isset( $_REQUEST['subscr_date'] ) ) {
								$new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $args['plan'] ), strtotime( sanitize_text_field( wp_unslash( $_REQUEST['subscr_date'] ) ) ) ) );
							} else {
								$new_expiration = '';
							}

							$args['expires'] = $new_expiration;
						}

						$args['payment_status'] = 'active'; // It's a signup, of course it's active!

						// create new subscriber here.
						break;

					case 'subscr_payment':
						if ( isset( $_REQUEST['mc_gross'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['mc_gross'] ) );
						} elseif ( isset( $_REQUEST['payment_gross'] ) ) { // subscr_payment.
							$args['price'] = sanitize_text_field( wp_unslash( $_REQUEST['payment_gross'] ) );
						}

						if ( ! empty( $_REQUEST['subscr_id'] ) ) { // subscr_payment.
							$args['subscr_id'] = sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) );
						}

						if ( isset( $_REQUEST['payment_status'] ) && 'completed' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) ) ) {
							$args['payment_status'] = 'active';
						} else {
							$args['payment_status'] = 'deactivated';
						}

						$user = get_leaky_paywall_subscriber_by_subscriber_id( $args['subscr_id'], $mode );

						if ( is_multisite_premium() ) {
							$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ) );
							if ( $site_id ) {
								$site = '_' . $site_id;
							}
						}

						if (
							! empty( $user ) && 0 !== $user->ID
							&& ( $plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true ) )
							&& isset( $_REQUEST['payment_status'] ) && 'completed' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) )
						) {
							$args['plan'] = $plan;
							if ( isset( $_REQUEST['payment_date'] ) ) {
								$new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $plan ), strtotime( sanitize_text_field( wp_unslash( $_REQUEST['payment_date'] ) ) ) ) );
							} else {
								$new_expiration = '';
							}
							$args['expires'] = $new_expiration;
						} else {
							$args['plan'] = $level['interval_count'] . ' ' . strtoupper( substr( $level['interval'], 0, 1 ) );
							if ( isset( $_REQUEST['payment_date'] ) ) {
								$new_expiration = date_i18n( 'Y-m-d 23:59:59', strtotime( '+' . str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $args['plan'] ), strtotime( sanitize_text_field( wp_unslash( $_REQUEST['payment_date'] ) ) ) ) );
							} else {
								$new_expiration = '';
							}

							$args['expires'] = $new_expiration;
						}
						break;

					case 'subscr_cancel':
						if ( isset( $_REQUEST['subscr_id'] ) ) { // subscr_payment.
							$user = get_leaky_paywall_subscriber_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ), $mode );
							if ( is_multisite_premium() ) {
								$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ) );
								if ( $site_id ) {
									$site = '_' . $site_id;
								}
							}
							if ( ! empty( $user ) && 0 !== $user->ID ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );

								do_action( 'leaky_paywall_cancelled_subscriber', $user, 'paypal' );
							}
						}
						return true; // We don't need to process anymore.

					case 'subscr_eot':
						if ( isset( $_REQUEST['subscr_id'] ) ) { // subscr_payment.
							$user = get_leaky_paywall_subscriber_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ), $mode );
							if ( is_multisite_premium() ) {
								$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ) );
								if ( $site_id ) {
									$site = '_' . $site_id;
								}
							}
							if ( ! empty( $user ) && 0 !== $user->ID ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'expired' );
							}
						}
						return true; // We don't need to process anymore.

					case 'recurring_payment_suspended_due_to_max_failed_payment':
						if ( isset( $_REQUEST['recurring_payment_id'] ) ) { // subscr_payment.
							$user = get_leaky_paywall_subscriber_by_subscriber_id( $args['recurring_payment_id'], $mode );
							if ( is_multisite_premium() ) {
								$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['recurring_payment_id'] ) ) );
								if ( $site_id ) {
									$site = '_' . $site_id;
								}
							}
							if ( ! empty( $user ) && 0 !== $user->ID ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
								do_action( 'leaky_paywall_failed_payment', $user );
							}
						}
						return true; // We don't need to process anymore.

					case 'recurring_payment_suspended':
						if ( isset( $_REQUEST['subscr_id'] ) ) { // subscr_payment.
							$user = get_leaky_paywall_subscriber_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ), $mode );
							if ( is_multisite_premium() ) {
								$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ) );
								if ( $site_id ) {
									$site = '_' . $site_id;
								}
							}
							if ( ! empty( $user ) && 0 !== $user->ID ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'suspended' );
							}
						} elseif ( isset( $_REQUEST['recurring_payment_id'] ) ) { // subscr_payment.
							$user = get_leaky_paywall_subscriber_by_subscriber_id( $args['recurring_payment_id'], $mode );
							if ( is_multisite_premium() ) {
								$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['recurring_payment_id'] ) ) );
								if ( $site_id ) {
									$site = '_' . $site_id;
								}
							}
							if ( ! empty( $user ) && 0 !== $user->ID ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'suspended' );
							}
						}
						return true; // We don't need to process anymore.
				}

				if ( ! empty( $_REQUEST['custom'] ) && is_email( wp_unslash( $_REQUEST['custom'] ) ) ) {
					$user  = get_user_by( 'email', sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) );
					$email = sanitize_email( wp_unslash( $_REQUEST['custom'] ) );
					if ( empty( $user ) ) {
						$user = get_leaky_paywall_subscriber_by_subscriber_email( sanitize_email( wp_unslash( $_REQUEST['custom'] ) ), $mode );
						if ( is_multisite_premium() ) {
							$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) );
							if ( $site_id ) {
								$args['site'] = $site_id;
							}
						}
					}
				} elseif ( ! empty( $_REQUEST['payer_email'] ) && is_email( wp_unslash( $_REQUEST['payer_email'] ) ) ) {
					$user  = get_user_by( 'email', sanitize_email( wp_unslash( $_REQUEST['payer_email'] ) ) );
					$email = sanitize_email( wp_unslash( $_REQUEST['payer_email'] ) );
					if ( empty( $user ) ) {
						$user = get_leaky_paywall_subscriber_by_subscriber_email( sanitize_email( wp_unslash( $_REQUEST['payer_email'] ) ), $mode );
						if ( is_multisite_premium() ) {
							$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_email( sanitize_email( wp_unslash( $_REQUEST['payer_email'] ) ) );
							if ( $site_id ) {
								$args['site'] = $site_id;
							}
						}
					}
				}

				if ( empty( $user ) && ! empty( $_REQUEST['txn_id'] ) ) {
					$user = get_leaky_paywall_subscriber_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) ), $mode );
					if ( is_multisite_premium() ) {
						$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) ) );
						if ( $site ) {
							$args['site'] = $site_id;
						}
					}
				}

				if ( empty( $user ) && ! empty( $_REQUEST['subscr_id'] ) ) {
					$user = get_leaky_paywall_subscriber_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ), $mode );
					if ( is_multisite_premium() ) {
						$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( sanitize_text_field( wp_unslash( $_REQUEST['subscr_id'] ) ) );
						if ( $site_id ) {
							$args['site'] = $site_id;
						}
					}
				}

				// get data submitted in registration form.
				$transaction_id = leaky_paywall_get_transaction_id_from_email( sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) );

				if ( $transaction_id ) {
					$args['password'] = get_post_meta( $transaction_id, '_password', true );
					delete_post_meta( $transaction_id, '_password' ); // dont want this in the database.
					$args['first_name']     = get_post_meta( $transaction_id, '_first_name', true );
					$args['last_name']      = get_post_meta( $transaction_id, '_last_name', true );
					$args['login']          = get_post_meta( $transaction_id, '_login', true );
					$args['transaction_id'] = $transaction_id;

					if ( 'cart' == $_REQUEST['txn_type'] || 'web_accept' == $_REQUEST['txn_type'] || 'subscr_signup' == $_REQUEST['txn_type'] ) {
						update_post_meta( $transaction_id, '_paypal_request', json_encode( $_REQUEST ) );
						update_post_meta( $transaction_id, '_transaction_status', 'complete' );
						leaky_paywall_set_payment_transaction_id( $transaction_id, sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) ) );
					}
				} else {
					// create a transaction after clicking the paypal button on the subscribe card.
					// one time payment uses txn_type web_accept.
					// recurring subscription uses txn_type subscr_signup.
					if ( 'cart' == $_REQUEST['txn_type'] || 'web_accept' == $_REQUEST['txn_type'] || 'subscr_signup' == $_REQUEST['txn_type'] ) {
						$transaction_id = $this->save_data_to_transaction( $email );
						leaky_paywall_set_payment_transaction_id( $transaction_id, sanitize_text_field( wp_unslash( $_REQUEST['txn_id'] ) ) );
					}
				}

				$args['transaction_id'] = $transaction_id;
				$user_exists            = get_user_by( 'email', sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) );

				if ( ! empty( $user_exists ) ) {
					// WordPress user exists.
					$args['subscriber_email'] = is_email( sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) ) ? sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) : $user->user_email;

					leaky_paywall_log( $args, 'before paypal standard update existing user' );

					$user_id = leaky_paywall_update_subscriber( null, $args['subscriber_email'], $args['subscr_id'], $args );
				} else {
					// Need to create a new user.
					$args['subscriber_email'] = is_email( sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) ) ? sanitize_email( wp_unslash( $_REQUEST['custom'] ) ) : sanitize_text_field( wp_unslash( $_REQUEST['payer_email'] ) );

					leaky_paywall_log( $args, 'before paypal standard create new user: args' );

					$user_id = leaky_paywall_new_subscriber( null, $args['subscriber_email'], $args['subscr_id'], $args );

					// send new user the welcome email.
					leaky_paywall_email_subscription_status( $user_id, 'new', $args );
				}

				leaky_paywall_cleanup_incomplete_user( $args['subscriber_email'] );

				leaky_paywall_log( $args, 'after paypal standard ipn' );

				do_action( 'leaky_paywall_after_process_paypal_webhooks', $_REQUEST, $args, $user_id );
			}
		} else {
			leaky_paywall_log( $payload, 'Invalid IPN sent from PayPal' );
		}

		return true;
	}

	/**
	 * Save paypal data to a transaction
	 *
	 * @param string $email The email address
	 */
	public function save_data_to_transaction( $email = '' ) {

		if ( $email ) {
			$transaction_email = $email;
		} else {
			$transaction_email = $this->email;
		}

		$transaction = array(
			'post_title'   => 'Transaction for ' . $transaction_email,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'lp_transaction',
		);

		// Insert the post into the database.
		$transaction_id = wp_insert_post( $transaction );

		if ( isset( $_POST['password'] ) ) {
			$transaction_password = sanitize_text_field( wp_unslash( $_POST['password'] ) );
		} else {
			$transaction_password = '';
		}

		if ( isset( $_POST['username'] ) ) {
			$username = sanitize_text_field( wp_unslash( $_POST['username'] ) );
		} else {
			$username = '';
		}

		if ( isset( $this->first_name ) ) {
			$first_name = $this->first_name;
		} elseif ( isset( $_REQUEST['first_name'] ) ) {
			$first_name = sanitize_text_field( wp_unslash( $_REQUEST['first_name'] ) );
		} else {
			$first_name = '';
		}

		if ( isset( $this->last_name ) ) {
			$last_name = $this->last_name;
		} elseif ( isset( $_REQUEST['last_name'] ) ) {
			$last_name = sanitize_text_field( wp_unslash( $_REQUEST['last_name'] ) );
		} else {
			$last_name = '';
		}

		if ( isset( $_REQUEST['item_number'] ) ) {
			$level_id = sanitize_text_field( wp_unslash( $_REQUEST['item_number'] ) );
		} else {
			$level_id = $this->level_id;
		}

		if ( isset( $this->currency ) ) {
			$currency = $this->currency;
		} else {
			if ( isset( $_REQUEST['mc_currency'] ) ) {
				$currency = sanitize_text_field( wp_unslash( $_REQUEST['mc_currency'] ) );
			}
		}

		if ( isset( $_REQUEST['mc_amount3'] ) ) { // subscr_payment.
			$price = sanitize_text_field( wp_unslash( $_REQUEST['mc_amount3'] ) );
		} elseif ( isset( $_REQUEST['amount3'] ) ) { // subscr_payment.
			$price = sanitize_text_field( wp_unslash( $_REQUEST['amount3'] ) );
		} elseif ( isset( $_REQUEST['mc_gross'] ) ) { // subscr_payment.
			$price = sanitize_text_field( wp_unslash( $_REQUEST['mc_gross'] ) );
		} elseif ( isset( $this->amount ) ) {
			$price = $this->amount;
		} else {
			$price = '';
		}

		update_post_meta( $transaction_id, '_email', $transaction_email );
		update_post_meta( $transaction_id, '_password', $transaction_password );
		update_post_meta( $transaction_id, '_first_name', $first_name );
		update_post_meta( $transaction_id, '_last_name', $last_name );
		update_post_meta( $transaction_id, '_login', $username );
		update_post_meta( $transaction_id, '_level_id', $level_id );
		update_post_meta( $transaction_id, '_gateway', 'paypal' );
		update_post_meta( $transaction_id, '_price', $price );
		update_post_meta( $transaction_id, '_currency', $currency );
		update_post_meta( $transaction_id, '_transaction_status', 'incomplete' );

		if ( isset( $_REQUEST['txn_type'] ) ) {
			update_post_meta( $transaction_id, '_paypal_request', array_map( 'sanitize_text_field', wp_unslash( $_REQUEST ) ) );
			update_post_meta( $transaction_id, '_transaction_status', 'complete' );
		}

		do_action( 'leaky_paywall_save_data_to_paypal_transaction', $transaction_id );

		return $transaction_id;
	}
}
