<?php

/**
 * Leaky Paywall transaction post type
 *
 * @package Leaky Paywall
 */

/**
 * Load the base class
 */
class LP_Transaction_Table
{

	/**
	 * The constructor
	 */
	public function __construct()
	{
		add_action('init', array($this, 'register_post_type'));
		add_action('add_meta_boxes', array($this, 'meta_box_create'));

		add_filter('manage_edit-lp_transaction_columns', array($this, 'transaction_columns'));
		add_action('manage_lp_transaction_posts_custom_column', array($this, 'transaction_custom_columns'), 10, 2);
	}

	/**
	 * Register transaction post type
	 */
	public function register_post_type()
	{

		$labels = array(
			'name'               => 'Transaction',
			'singular_name'      => 'Transaction',
			'menu_name'          => 'Transactions',
			'name_admin_bar'     => 'Transaction',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Transaction',
			'new_item'           => 'New Transaction',
			'edit_item'          => 'Edit Transaction',
			'view_item'          => 'View Transaction',
			'all_items'          => 'All Transactions',
			'search_items'       => 'Search Transactions',
			'parent_item_colon'  => 'Parent Transactions:',
			'not_found'          => 'No Transactions found',
			'not_found_in_trash' => 'No Transactions found in trash.',
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __('Leaky Paywall Transactions', 'leaky-paywall'),
			'public'             => false,
			'publicly_queryable' => false,
			'exclude_fromsearch' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array('title'),
		);

		register_post_type('lp_transaction', $args);
	}

	/**
	 * Create meta box for transaction post type
	 */
	public function meta_box_create()
	{
		add_meta_box('transaction_details', 'Transaction Details', array($this, 'transaction_details_func'), 'lp_transaction', 'normal', 'high');
	}

	/**
	 * Display the transaction details
	 *
	 * @param object $post The post object.
	 */
	public function transaction_details_func($post)
	{

		$level_id = esc_attr(get_post_meta($post->ID, '_level_id', true));
		$level    = get_leaky_paywall_subscription_level($level_id);

		$gateway        = get_post_meta($post->ID, '_gateway', true);
		$gateway_txn_id = get_post_meta($post->ID, '_gateway_txn_id', true);
		$nag_location = get_post_meta($post->ID, '_nag_location_id', true);

		wp_nonce_field('lp_transaction_meta_box_nonce', 'meta_box_nonce');
?>
		<table class="form-table">
			<tbody>

				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_title">First Name </label>
					</th>
					<td>
						<?php echo esc_attr(get_post_meta($post->ID, '_first_name', true)); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Last Name </label>
					</th>
					<td>
						<?php echo esc_attr(get_post_meta($post->ID, '_last_name', true)); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Email </label>
					</th>
					<td>
						<?php echo esc_attr(get_post_meta($post->ID, '_email', true)); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Gateway</label>
					</th>
					<td>
						<?php echo esc_attr($gateway); ?>
					</td>
				</tr>

				<?php
				if ($gateway_txn_id && 'stripe' === $gateway) {
				?>
					<tr valign="top">
						<th scope="row">
							<label for="apc_box1_description">Gateway Transaction ID</label>
						</th>
						<td>
							<a target="_blank" href="https://dashboard.stripe.com/payments/<?php echo esc_attr($gateway_txn_id); ?>">
								<?php echo esc_attr($gateway_txn_id); ?>
							</a>
						</td>
					</tr>
				<?php
				}
				?>

				<?php if ($level) {
					// will not exist for some purchases (i.e. pay per post)
				?>
					<tr valign="top">
						<th scope="row">
							<label for="apc_box1_description">Level ID </label>
						</th>
						<td>
							<?php echo absint($level_id) . ' - ' . isset($level['label']) ? esc_html($level['label']) : ''; ?>
						</td>
					</tr>
				<?php
				} ?>


				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Price </label>
					</th>
					<td>
						<?php echo esc_attr(get_post_meta($post->ID, '_price', true)); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Currency </label>
					</th>
					<td>
						<?php echo esc_attr(get_post_meta($post->ID, '_currency', true)); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="apc_box1_description">Is Renewal Payment</label>
					</th>
					<td>
						<?php echo get_post_meta($post->ID, '_is_recurring', true) ? 'yes' : 'no'; ?>
					</td>
				</tr>

				<?php if ($nag_location) {
				?>
					<tr valign="top">
						<th scope="row">
							<label for="apc_box1_description">Nag Location</label>
						</th>
						<td>
							<?php echo esc_url(get_the_permalink($nag_location)); ?>
						</td>
					</tr>
				<?php
				} ?>

			</tbody>
		</table>

		<?php
		if (get_post_meta($post->ID, '_paypal_request', true)) {
			echo '<p><strong>Paypal Response</strong></p>';
			echo '<pre>';
			print_r(json_decode(get_post_meta($post->ID, '_paypal_request', true)));
			echo '</pre>';
		}
		?>

		<?php do_action('leaky_paywall_after_transaction_meta_box', $post); ?>

<?php

	}

	/**
	 * Column headers for a transaction
	 *
	 * @param array $columns The column names.
	 */
	public function transaction_columns($columns)
	{

		$columns = array(
			'cb'      => '<input type="checkbox" />',
			'email'   => __('Email'),
			'level'   => __('Level'),
			'price'   => __('Price'),
			'created' => __('Created'),
			'status'  => __('Status'),
			'type'    => __('Payment Type'),
		);

		return $columns;
	}

	/**
	 * Columns for a transaction
	 *
	 * @param string  $column The column name.
	 * @param integer $post_id The post id.
	 */
	public function transaction_custom_columns($column, $post_id)
	{

		$level_id = esc_attr(get_post_meta($post_id, '_level_id', true));
		$level    = get_leaky_paywall_subscription_level($level_id);

		switch ($column) {

			case 'email':
				echo '<a href="' . esc_url(admin_url()) . '/post.php?post=' . absint($post_id) . '&action=edit">' . esc_html(get_post_meta($post_id, '_email', true)) . '</a>';
				break;

			case 'level':
				echo isset($level['label']) ? esc_html($level['label']) : '';
				break;

			case 'price':

				if (is_numeric(get_post_meta($post_id, '_price', true))) {
					echo esc_attr(leaky_paywall_get_current_currency_symbol() . number_format(get_post_meta($post_id, '_price', true), 2));
				} else {
					echo esc_attr(leaky_paywall_get_current_currency_symbol() . get_post_meta($post_id, '_price', true), 2);
				}


				break;

			case 'created':
				echo get_the_date('M d, Y h:i:s A', $post_id);
				break;

			case 'status':
				echo get_post_meta($post_id, '_transaction_status', true) ? esc_attr(get_post_meta($post_id, '_transaction_status', true)) : 'Complete';
				break;

			case 'type':
				echo get_post_meta($post_id, '_is_recurring', true) ? 'Recurring' : 'One Time';
				break;
		}
	}
}

new LP_Transaction_Post_Type();
