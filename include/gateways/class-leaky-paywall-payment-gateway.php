<?php
/**
 * Payment Gateway Base Class
 *
 * @package     Leaky Paywall
 * @since 4.0.0
 */

/**
 * The payment gateway class
 */
class Leaky_Paywall_Payment_Gateway {

	public $supports = array();
	public $email;
	public $first_name;
	public $last_name;
	public $user_id;
	public $user_name;
	public $currency;
	public $amount;
	public $discount;
	public $length;
	public $length_unit;
	public $level_id;
	public $level_price;
	public $level_name;
	public $plan_id;
	public $site;
	public $recurring;
	public $return_url;

	public function __construct( $subscription_data = array() ) {

		$this->init();

		if ( ! empty( $subscription_data ) ) {

			$this->email       = $subscription_data['user_email'];
			$this->user_id     = $subscription_data['user_id'];
			$this->user_name   = $subscription_data['user_name'];
			$this->first_name  = $subscription_data['first_name'];
			$this->last_name   = $subscription_data['last_name'];
			$this->currency    = $subscription_data['currency'];
			$this->amount      = $subscription_data['amount'];
			$this->length      = $subscription_data['interval_count'];
			$this->length_unit = $subscription_data['interval'];
			$this->level_id    = $subscription_data['level_id'];
			$this->level_price = $subscription_data['price'];
			$this->level_name  = $subscription_data['description'];
			$this->plan_id     = $subscription_data['plan'];
			$this->site        = $subscription_data['site'];
			$this->recurring   = $subscription_data['recurring'];

		}

	}

	public function init() {}

	public function process_signup() {}

	public function process_webhooks() {}

	public function scripts() {}

	public function fields( $level_id ) {}

	public function validate_fields() {}

	public function supports( $item = '' ) {
		return in_array( $item, $this->supports );
	}

	public function generate_transaction_id() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		return strtolower( md5( $this->subscription_key . gmdate( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'leaky-paywall', true ) ) );
	}

	public function renew_member( $recurring = false, $status = 'active' ) {
		$member = new Leaky_Paywall_Member( $this->user_id );
		$member->renew( $recurring, $status );
	}

	public function add_error( $code = '', $message = '' ) {
		leaky_paywall_errors()->add( $code, $message, 'register' );
	}

}
