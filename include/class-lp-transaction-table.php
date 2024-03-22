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
		$this->level_id               = $args['level_id'];
		$this->currency               = isset( $args['currency'] ) ? $args['currency'] : '';
		$this->payment_gateway        = $args['payment_gateway'];
		$this->payment_gateway_txn_id = isset( $args['payment_gateway_txn_id'] ) ? $args['payment_gateway_txn_id'] : '';
		$this->payment_status         = $args['payment_status'];
		$this->is_recurring           = isset( $args['is_recurring'] ) ? true : false;

	}

	public function maybe_create_lp_transactions_table() {
		global $wpdb;

		$tablename = $wpdb->prefix . 'lp_transactions';

		if ($wpdb->get_var("SHOW TABLES LIKE '$tablename'") != $tablename) {

			$sql = "CREATE TABLE `$tablename` (
				`id`             INT(11)        NOT NULL AUTO_INCREMENT,
				`user_id`        INT(11)        NULL,
				`email`          VARCHAR(100)   NULL,
				`first_name`     VARCHAR(255)   NULL,
				`last_name`      VARCHAR(255)   NULL,
				`login`          VARCHAR(60)    NULL,
				`level_id`       VARCHAR(100)   NULL,
				`gateway`        VARCHAR(100)   NULL,
				`gateway_txn_id` VARCHAR(100)   NULL,
				`price`          TEXT           NULL,
				`currency`       VARCHAR( 100 ) NULL,
				`is_recurring`   INT(1)         NULL,
				`status`         VARCHAR(20)    NULL,
				`date_updated`   DATETIME       NULL,
				`date_created`   DATETIME       NULL,
				PRIMARY KEY (`id`),
				KEY status (status),
				KEY date_created (date_created),
				KEY date_updated (date_updated)

			);";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			dbDelta($sql);
		}
	}

	/**
	 * Create transaction
	 */
	public function create() {
		global $wpdb;

		$user = get_user_by( 'id', $this->user_id );

		$transaction = [
			'user_id'        => $this->user_id,
			'email'          => $user->user_email,
			'first_name'     => $user->first_name,
			'last_name'      => $user->last_name,
			'login'          => $user->user_login,
			'level_id'       => $this->level_id,
			'gateway'        => $this->payment_gateway,
			'gateway_txn_id' => $this->payment_gateway_txn_id,
			'price'          => $this->price,
			'currency'       => $this->currency,
			'is_recurring'   => $this->is_recurring,
			'status'         => $this->payment_status,
			'date_updated'   => current_time( 'mysql', 1 ),
			'date_created'   => current_time( 'mysql', 1 ),
		];

		$wpdb->insert(
			$wpdb->prefix . 'lp_transactions',
			$transaction
		);

		do_action( 'leaky_paywall_after_create_transaction', $wpdb->insert_id, $user );

		return $wpdb->insert_id;

	}
}