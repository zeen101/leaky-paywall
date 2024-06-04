<?php
/**
 * Leaky Paywall Transaction Class
 *
 * @package     Leaky Paywall
 * @since       4.0.0
 */

/**
 * Load the LP_Transaction class
 */
class LP_Transaction {

	private $user_id;

	private $price;

	private $level_id;

	private $currency;

	private $payment_gateway;

	private $payment_gateway_txn_id;

	private $payment_status;

	private $is_recurring;

	/**
	 * The constructor
	 *
	 * @param array $args The data for the transaction.
	 */
	public function __construct( $args ) {

		$this->user_id                = $args['user_id'];
		$this->price                  = $args['price'];
		$this->payment_gateway        = $args['payment_gateway'];
		$this->payment_gateway_txn_id = isset( $args['payment_gateway_txn_id'] ) ? $args['payment_gateway_txn_id'] : '';
		$this->payment_status         = $args['payment_status'];
		$this->level_id               = $args['level_id'];
		$this->currency               = isset( $args['currency'] ) ? $args['currency'] : '';
		$this->is_recurring           = isset( $args['is_recurring'] ) ? true : false;

	}

	/**
	 * Create transaction
	 */
	public function create() {
		$user = get_user_by( 'id', $this->user_id );

		$transaction = array(
			'post_title'   => 'Transaction for ' . $user->user_email,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_type'    => 'lp_transaction',
		);

		$transaction_id = wp_insert_post( $transaction );

		update_post_meta( $transaction_id, '_email', $user->user_email );
		update_post_meta( $transaction_id, '_first_name', $user->first_name );
		update_post_meta( $transaction_id, '_last_name', $user->last_name );
		update_post_meta( $transaction_id, '_login', $user->user_login );
		update_post_meta( $transaction_id, '_level_id', $this->level_id );
		update_post_meta( $transaction_id, '_gateway', $this->payment_gateway );
		update_post_meta( $transaction_id, '_gateway_txn_id', $this->payment_gateway_txn_id );
		update_post_meta( $transaction_id, '_price', $this->price );
		update_post_meta( $transaction_id, '_currency', $this->currency );
		update_post_meta( $transaction_id, '_status', $this->payment_status );
		update_post_meta( $transaction_id, '_is_recurring', $this->is_recurring );
		update_post_meta( $transaction_id, '_transaction_status', 'complete' );

		do_action( 'leaky_paywall_after_create_transaction', $transaction_id, $user );

		return $transaction_id;

	}

	public static function update_meta( $transaction_id, $meta_key, $meta_value ) {

		update_post_meta( $transaction_id, $meta_key, $meta_value );

	}

	public static function get_meta( $transaction_id, $meta_key, $single = true ) {

		return get_post_meta( $transaction_id, $meta_key, $single );

	}

	public static function delete_meta( $transaction_id, $meta_key ) {

		delete_post_meta( $transaction_id, $meta_key );

	}

}
