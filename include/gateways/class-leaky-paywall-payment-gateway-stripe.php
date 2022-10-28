<?php
/**
 * Stripe Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
 */

 /**
  * This class extends the gateway class for Stripe
  *
  * @since 1.0.0
  */
class Leaky_Paywall_Payment_Gateway_Stripe extends Leaky_Paywall_Payment_Gateway {

	/**
	 * The secret key
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * The publishable key
	 *
	 * @var string
	 */
	protected $publishable_key;

	/**
	 * Get things going
	 *
	 * @since  4.0.0
	 */
	public function init() {
		$settings = get_leaky_paywall_settings();

		$this->supports[] = 'one-time';
		$this->supports[] = 'recurring';
		// $this->supports[]	= 'fees';

		$this->test_mode = 'off' === $settings['test_mode'] ? false : true;

		if ( $this->test_mode ) {

			$this->secret_key      = isset( $settings['test_secret_key'] ) ? trim( $settings['test_secret_key'] ) : '';
			$this->publishable_key = isset( $settings['test_publishable_key'] ) ? trim( $settings['test_publishable_key'] ) : '';
		} else {

			$this->secret_key      = isset( $settings['live_secret_key'] ) ? trim( $settings['live_secret_key'] ) : '';
			$this->publishable_key = isset( $settings['live_publishable_key'] ) ? trim( $settings['live_publishable_key'] ) : '';
		}

		if ( ! class_exists( 'Stripe' ) && ! class_exists( 'Stripe\Stripe' ) ) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/init.php';
		}
	}

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_signup() {

		if (
			! isset( $_POST['leaky_paywall_register_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['leaky_paywall_register_nonce'] ) ), 'leaky-paywall-register-nonce' )
		) {
			wp_die( 
				esc_html__( 'An error occurred, please contact the site administrator: ', 'leaky-paywall' ) . esc_html( get_bloginfo( 'admin_email' ) ), 
				esc_html__( 'Error', 'leaky-paywall' ), 
				array( 'response' => '401' ) 
			);
		}

		$level = get_leaky_paywall_subscription_level( $this->level_id );

		$incomplete_user = get_posts(
			array(
				'post_type'      => 'lp_incomplete_user',
				'posts_per_page' => 1,
				'meta_key'       => '_email',
				'meta_value'     => $this->email,
			)
		);

		if ( empty( $incomplete_user ) ) {
			wp_die( 
				esc_html__( 'An error occurred, please contact the site administrator: ', 'leaky-paywall' ) . esc_html( get_bloginfo( 'admin_email' ) ), 
				esc_html__( 'Error', 'leaky-paywall' ), 
				array( 'response' => '401' ) 
			);
		}

		$customer_data = get_post_meta( $incomplete_user[0]->ID, '_customer_data', true );
		$customer_id   = $customer_data->id;

		$user = get_user_by( 'email', $this->email );

		if ( $user ) {
			$existing_customer = true;
		} else {
			$existing_customer = false;
		}

		if ( ! $customer_id ) {
			wp_die( 
				esc_html__( 'An error occurred, please contact the site administrator: ', 'leaky-paywall' ) . esc_html( get_bloginfo( 'admin_email' ) ), 
				esc_html__( 'Error', 'leaky-paywall' ), 
				array( 'response' => '401' ) 
			);
		}

		$payment_intent_id = isset( $_POST['payment-intent-id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment-intent-id'] ) ) : '';

		$gateway_data = array(
			'level_id'               => $this->level_id,
			'subscriber_id'          => $customer_id,
			'subscriber_email'       => $this->email,
			'existing_customer'      => $existing_customer,
			'price'                  => $this->level_price,
			'description'            => $this->level_name,
			'payment_gateway'        => 'stripe',
			'payment_status'         => 'active', // will deaactivate with payment_intent.failed webhook if needed.
			'interval'               => $this->length_unit,
			'interval_count'         => $this->length,
			'site'                   => ! empty( $level['site'] ) ? $level['site'] : '',
			'plan'                   => $this->plan_id,
			'recurring'              => $this->recurring,
			'currency'               => $this->currency,
			'payment_gateway_txn_id' => $payment_intent_id,
		);

		do_action( 'leaky_paywall_stripe_signup', $gateway_data );

		return apply_filters( 'leaky_paywall_stripe_gateway_data', $gateway_data, $this, $customer_data );
	}

	/**
	 * Process incoming webhooks
	 *
	 * @since 4.0.0
	 */
	public function process_webhooks() {
		if ( ! isset( $_GET['listener'] ) || strtolower( sanitize_text_field( wp_unslash( $_GET['listener'] ) ) ) != 'stripe' ) {
			return;
		}

		// phpcs:ignore
		$body         = @file_get_contents( 'php://input' );
		$stripe_event = json_decode( $body );
		$settings     = get_leaky_paywall_settings();
		$user         = '';

		if ( false == $stripe_event->livemode ) {
			$mode = 'test';
			$endpoint_secret = $settings['test_signing_secret'];
		} else {
			$mode = 'live';
			$endpoint_secret = $settings['live_signing_secret'];
		}

		if ( ! isset( $stripe_event->type ) ) {
			return;
		}

		if ( $endpoint_secret ) {

			$stripe = leaky_paywall_initialize_stripe_api();
			$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) : '';

			try {
				$event = \Stripe\Webhook::constructEvent(
					$body, $sig_header, $endpoint_secret
				);
			} catch(\UnexpectedValueException $e) {
				// Invalid payload
				leaky_paywall_log( $e->getMessage(), 'stripe webhook - invalid payload' );
				wp_send_json( ['leaky paywall webhook received - invalid payload'], 400 );
			} catch(\Stripe\Exception\SignatureVerificationException $e) {
				// Invalid signature
				leaky_paywall_log( $e->getMessage(), 'stripe webhook - invalid signature' );
				wp_send_json( ['leaky paywall webhook received - invalid signature'], 400 );
			}
		}

		$stripe_object = $stripe_event->data->object;

		do_action( 'leaky_paywall_before_process_stripe_webhook', $stripe_event );

		leaky_paywall_log( $stripe_object, 'stripe webhook - ' . $stripe_event->type );

		if ( ! empty( $stripe_object->customer ) ) {
			$user = get_leaky_paywall_subscriber_by_subscriber_id( $stripe_object->customer, $mode );
		}

		if ( empty( $user ) ) {
			return;
		}

		if ( is_multisite_premium() ) {
			$site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id( $stripe_object->customer );
			if ( $site_id ) {
				$site = '_' . $site_id;
			}
		} else {
			$site = '';
		}

		// https://stripe.com/docs/api#event_types .
		switch ( $stripe_event->type ) {

			case 'charge.succeeded':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );

				$transaction_id = leaky_paywall_get_transaction_id_from_email( $user->user_email );
		
				// only recurring payments will include the word "Invoice"
				if ( strpos( $stripe_object->description, 'Invoice' ) === false ) {
					leaky_paywall_set_payment_transaction_id( $transaction_id, $stripe_object->id );
				}
				
				break;
			case 'charge.failed':
			case 'payment_intent.payment_failed':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
				do_action( 'leaky_paywall_failed_payment', $user );
				break;
			case 'charge.refunded':
				if ( $stripe_object->refunded ) {
					update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
				} else {
					update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
				}
				break;
			case 'charge.dispute.created':
			case 'charge.dispute.updated':
			case 'charge.dispute.closed':
			case 'customer.created':
			case 'customer.updated':
			case 'customer.source.created':
			case 'invoice.created':
			case 'invoice.updated':
				break;
			case 'customer.deleted':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
				break;

			case 'invoice.payment_succeeded':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
				// get the subscription and sync expiration date
				$stripe = leaky_paywall_initialize_stripe_api();
				$sub = $stripe->subscriptions->retrieve( $stripe_object->subscription );
				$expires = date_i18n( 'Y-m-d 23:59:59', $sub->current_period_end );
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
				break;
			case 'invoice.paid':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
				break;

			case 'invoice.payment_failed':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
				do_action( 'leaky_paywall_failed_payment', $user );
				break;

			case 'customer.subscription.updated':
				
				if ( 'past_due' == $stripe_object->status ) {
					update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated' );
				}
				break;

			case 'customer.subscription.created':
				$expires = date_i18n( 'Y-m-d 23:59:59', $stripe_object->current_period_end );
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
				break;

			case 'customer.subscription.deleted':
				
				if ( 'canceled' == $stripe_object->status ) {
					update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
				} else {
					$expires = date_i18n( 'Y-m-d 23:59:59', $stripe_object->current_period_end );
					update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
				}

				break;

			case 'payment_intent.canceled':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled' );
				break;

			case 'payment_intent.succeeded':
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active' );
				break;
			
			default:
				break;
		};

		// create an action for each event fired by stripe.
		$action = str_replace( '.', '_', $stripe_event->type );
		do_action( 'leaky_paywall_stripe_' . $action, $user, $stripe_object );

		wp_send_json( ['leaky paywall webhook received - success'], 200 );

	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 *
	 * @param integer $level_id The level id.
	 */
	public function fields( $level_id ) {

		if ( is_numeric( $level_id ) ) {
			$level_id = $level_id;
		} else if ( isset( $_GET['level_id'] ) ) {
			$level_id = sanitize_text_field( wp_unslash( $_GET['level_id'] ) );
		}

		$level    = get_leaky_paywall_subscription_level( $level_id );

		if ( 0 == $level['price'] ) {
			return;
		}

		$content = $this->stripe_elements( $level_id );
		return $content;
	}

	/**
	 * Add Stripe elements
	 *
	 * @param integer $level_id The level id.
	 */
	public function stripe_elements( $level_id ) {

		$stripe_plan = '';
		$level       = get_leaky_paywall_subscription_level( $level_id );

		if ( 0 == $level['price'] ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		$stripe_price = number_format( $level['price'], 2, '', '' );

		$plan_args = array(
			'stripe_price' => $stripe_price,
			'currency'     => leaky_paywall_get_currency(),
			'secret_key'   => $this->secret_key,
		);

		if ( isset( $level['recurring'] ) && 'on' == $level['recurring'] ) {
			$stripe_plan = leaky_paywall_get_stripe_plan( $level, $level_id, $plan_args );
		}

		ob_start();
		?>

		<div class="leaky-paywall-payment-method-container">

			<input id="payment_method_stripe" class="input-radio" name="payment_method" value="stripe" checked="checked" data-order_button_text="" type="radio">

			<label for="payment_method_stripe"> <?php esc_html_e( 'Credit Card', 'leaky-paywall' ); ?> <img width="150" src="<?php echo esc_url( LEAKY_PAYWALL_URL ); ?>images/credit_card_logos_5.gif"></label>

		</div>

		<div class="leaky-paywall-card-details">

			<input type="hidden" id="plan-id" name="plan_id" value="<?php echo $stripe_plan ? esc_attr( $stripe_plan->id ) : ''; ?>" />
			<input type="hidden" id="stripe-customer-id" value="">
			<input type="hidden" id="payment-intent-client" value="">
			<input type="hidden" id="payment-intent-id" name="payment-intent-id" value="">

			<?php if ( 'yes' == $settings['enable_apple_pay'] ) { ?>
				<div id="payment-request-button">
					<!-- A Stripe Element will be inserted here. -->
				</div>
			<?php } ?>

			<?php
			if ( ! is_ssl() ) {
				echo '<div class="leaky_paywall_message error"><p>' . esc_html__( 'This page is unsecured. Do not enter a real credit card number. Use this field only for testing purposes.', 'leaky-paywall' ) . '</p></div>';
			}
			?>


			<div class="form-row">
				<label for="card-element">
					<?php esc_attr_e( 'Credit or debit card', 'leaky-paywall' ); ?>
				</label>
				<div id="card-element">
					<!-- A Stripe Element will be inserted here. -->
				</div>

				<!-- Used to display form errors. -->
				<div id="card-errors" role="alert"></div>
				<div id="lp-card-errors"></div>
			</div>

		</div>

		<!-- stripe javascript -->

		<?php

		return ob_get_clean();
	}

	
	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 4.0.0
	 */
	public function validate_fields() {
		
	}


	/**
	 * Load Stripe JS. Need to load it on every page for Stripe Radar rules.
	 *
	 * @since  4.0.0
	 */
	public function scripts() {
		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v3/', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );
	}
}
