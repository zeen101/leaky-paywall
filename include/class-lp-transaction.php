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

	private $coupon_code;

	private $is_recurring;

	private $subscriber_id;

	private $tax_amount;

	private $subtotal;

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
		$this->subscriber_id          = isset( $args['subscriber_id'] ) ? $args['subscriber_id'] : '';
		$this->tax_amount             = isset( $args['tax_amount'] ) ? $args['tax_amount'] : '';
		$this->subtotal               = isset( $args['subtotal'] ) ? $args['subtotal'] : '';

	}

	/**
	 * Create transaction
	 */
	public function create() {
		$user = get_user_by( 'id', $this->user_id );

		// Deduplication: prevent duplicate transactions from concurrent webhook + redirect handlers.
		$existing = get_posts( array(
			'post_type'      => 'lp_transaction',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'date_query'     => array(
				array( 'after' => '60 seconds ago' ),
			),
			'meta_query'     => array(
				array( 'key' => '_email', 'value' => $user->user_email ),
				array( 'key' => '_level_id', 'value' => $this->level_id ),
			),
		) );

		if ( ! empty( $existing ) ) {
			return $existing[0]->ID;
		}

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

		if ( $this->subscriber_id ) {
			update_post_meta( $transaction_id, '_subscriber_id', $this->subscriber_id );
		}

		if ( '' !== $this->tax_amount ) {
			update_post_meta( $transaction_id, '_tax_amount', $this->tax_amount );
		}

		if ( '' !== $this->subtotal ) {
			update_post_meta( $transaction_id, '_subtotal', $this->subtotal );
		}

		do_action( 'leaky_paywall_after_create_transaction', $transaction_id, $user );

		return $transaction_id;

	}

}
