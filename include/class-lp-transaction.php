<?php 

/**
* Load the base class
*/
class LP_Transaction {
		
	private $user_id;

	private $price;

	private $created;

	private $payment_gateway;

	private $payment_status;

	private $coupon_code;

	private $is_recurring;

	function __construct( $args )	{

		$this->user_id = $args['user_id'];
		$this->price = $args['price'];
		$this->created = $args['created'];
		$this->payment_gateway = $args['payment_gateway'];
		$this->payment_status = $args['payment_status'];
		$this->coupon_code = $args['coupon_code'];
		$this->is_recurring = $args['is_recurring'];

	}

	public function createTransaction() 
	{
		
	}

	public function getTransaction() 
	{
		
	}

}