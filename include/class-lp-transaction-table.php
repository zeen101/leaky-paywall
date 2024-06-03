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

	private $login;

	private $user_id;

	private $email;

	private $first_name;

	private $last_name;

	private $price;

	private $level_id;

	private $currency;

	private $payment_gateway;

	private $payment_gateway_txn_id;

	private $payment_status;

	private $transaction_status;

	private $is_recurring;

	/**
	 * The constructor
	 *
	 * @param array $args The data for the transaction.
	 */
	public function __construct( $args ) {

		$this->login                  = $args['login'];
		$this->user_id                = $args['user_id'];
		$this->email                  = $args['email'];
		$this->first_name             = $args['first_name'];
		$this->last_name              = $args['last_name'];
		$this->price                  = $args['price'];
		$this->level_id               = $args['level_id'];
		$this->currency               = isset( $args['currency'] ) ? $args['currency'] : '';
		$this->payment_gateway        = $args['payment_gateway'];
		$this->payment_gateway_txn_id = isset( $args['payment_gateway_txn_id'] ) ? $args['payment_gateway_txn_id'] : '';
		$this->payment_status         = $args['payment_status'];
		$this->transaction_status     = $args['payment_status'];
		$this->is_recurring           = isset( $args['is_recurring'] ) ? 1 : 0;

	}

	/**
	 * Insert transaction into the database
	 */
	public function create() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'lp_transactions';

		if ( empty( $this->user_id ) && !empty( $this->login ) ) {
			$user = get_user_by( 'login', $this->login );
			$this->user_id = $user->ID;
		}

		$transaction = [
			'user_id'                => $this->user_id,
			'email'                  => $this->email,
			'first_name'             => $this->first_name,
			'last_name'              => $this->last_name,
			'price'                  => $this->price,
			'level_id'               => $this->level_id,
			'currency'               => $this->currency,
			'payment_gateway'        => $this->payment_gateway,
			'payment_gateway_txn_id' => $this->payment_gateway_txn_id,
			'payment_status'         => $this->payment_status,
			'transaction_status'     => $this->transaction_status,
			'is_recurring'           => $this->is_recurring,
			'date_updated'           => current_time( 'mysql', 1 ),
			'date_created'           => current_time( 'mysql', 1 ),
		];

		$return = $wpdb->insert(
			$table_name,
			$transaction
		);

		do_action( 'leaky_paywall_after_create_transaction', $wpdb->insert_id );

		return $wpdb->insert_id;

	}

	public static function get_transaction_by( $key, $value ) {

		global $wpdb;

		$table_name = $wpdb->prefix . 'lp_transactions';

		$exists = $wpdb->get_row( 
			$wpdb->prepare( 
				"SELECT * FROM $table_name WHERE %s = %s", 
				$key,
				$value 
			) 
		);

	}

	public static function update_meta( $transaction_id, $meta_key, $meta_value ) {

		global $wpdb;
		
		$table_name = $wpdb->prefix . 'lp_transaction_meta';
		
		// Check if the meta key already exists for the transaction ID
		$exists = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT COUNT(*) FROM $table_name WHERE transaction_id = %d AND meta_key = %s", 
				$transaction_id, 
				$meta_key 
			) 
		);
		
		if ( $exists > 0 ) {

			// Update the existing meta data
			$wpdb->update( 
				$table_name, 
				array( 'meta_value' => $meta_value ), 
				array( 'transaction_id' => $transaction_id, 'meta_key' => $meta_key ) 
			);

		} else {

			// Insert new meta data
			$wpdb->insert( 
				$table_name, 
				array( 'transaction_id' => $transaction_id, 'meta_key' => $meta_key, 'meta_value' => $meta_value )
			);

		}

	}

	public static function get_meta( $transaction_id, $meta_key ) {

		global $wpdb;
		
		$table_name = $wpdb->prefix . 'lp_transaction_meta';
		
		$meta_value = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT meta_value FROM $table_name WHERE transaction_id = %d AND meta_key = %s", 
				$transaction_id, 
				$meta_key 
			) 
		);
		
		return $meta_value;

	}

	public static function delete_meta( $transaction_id, $meta_key ) {

		global $wpdb; // Get the WordPress database object
		
		$table_name = $wpdb->prefix . 'lp_transaction_meta';
		
		$wpdb->delete( 
			$table_name, 
			array( 'transaction_id' => $transaction_id, 'meta_key' => $meta_key ) 
		);

	}

}