<?php 

/**
* Load the base class
*/
class LP_Transaction {
		
	private $user_id;

	private $price;

	private $level_id;

	private $currency;

	private $payment_gateway;

	private $payment_status;

	private $coupon_code;

	private $is_recurring;

	function __construct( $args )	{

		$this->user_id = $args['user_id'];
		$this->price = $args['price'];
		$this->payment_gateway = $args['payment_gateway'];
		$this->payment_status = $args['payment_status'];
		$this->level_id = $args['level_id'];
		$this->currency = $args['currency'];

	}

	public function create() 
	{

		$user = get_user_by( 'id', $this->user_id );

		$transaction = array(
			'post_title'    => 'Transaction for ' . $user->user_email,
			'post_content'  => '',
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_type'		=> 'lp_transaction'
		);
		
		// Insert the post into the database
		$transaction_id = wp_insert_post( $transaction );

		update_post_meta( $transaction_id, '_email', $user->user_email );
		update_post_meta( $transaction_id, '_first_name', $user->first_name );
		update_post_meta( $transaction_id, '_last_name', $user->last_name );
		update_post_meta( $transaction_id, '_login', $user->user_login );
		update_post_meta( $transaction_id, '_level_id', $this->level_id );
		update_post_meta( $transaction_id, '_gateway', $this->payment_gateway );
		update_post_meta( $transaction_id, '_price', $this->price );
		update_post_meta( $transaction_id, '_currency', $this->currency );
		update_post_meta( $transaction_id, '_status', $this->payment_status );

		return $transaction_id;
		
	}

}