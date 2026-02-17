<?php

/**
 * Leaky Paywall transaction post type
 *
 * @package Leaky Paywall
 */

/**
 * Load the base class
 */
class LP_Transaction_Post_Type
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

		add_action('restrict_manage_posts', array($this, 'add_filters'), 10, 2);
		add_action('pre_get_posts', array($this, 'apply_filters'));
		add_filter('manage_edit-lp_transaction_sortable_columns', array($this, 'sortable_columns'));
		add_filter('months_dropdown_results', array($this, 'remove_months_dropdown'), 10, 2);
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

		$level_id = get_post_meta($post->ID, '_level_id', true);
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
							<label for="apc_box1_description">Level</label>
						</th>
						<td>
							<?php echo isset($level['label']) ? 'ID: ' . absint($level_id) . ' - ' . esc_html($level['label']) : ''; ?>
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

		<?php
		$refund_for = get_post_meta( $post->ID, '_refund_for', true );
		if ( $refund_for ) {
			$original_email = get_post_meta( $refund_for, '_email', true );
		?>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label>Refund For</label>
						</th>
						<td>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $refund_for ) . '&action=edit' ) ); ?>">
								Transaction #<?php echo absint( $refund_for ); ?>
								<?php if ( $original_email ) { echo '(' . esc_html( $original_email ) . ')'; } ?>
							</a>
						</td>
					</tr>
				</tbody>
			</table>
		<?php
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
				echo isset($level['label']) ? '<span style="color: #aaa;">ID: ' . $level_id . ' - </span>' . esc_html($level['label']) : '';
				break;

			case 'price':
				$price = get_post_meta($post_id, '_price', true);

				if (is_numeric($price)) {
					$formatted = esc_attr(leaky_paywall_get_current_currency_symbol() . number_format($price, 2));
					if ($price < 0) {
						echo '<span style="color: #b32d2e;">' . $formatted . '</span>';
					} else {
						echo $formatted;
					}
				} else {
					echo esc_attr(leaky_paywall_get_current_currency_symbol() . $price);
				}

				break;

			case 'created':
				echo get_the_date('M d, Y h:i:s A', $post_id);
				break;

			case 'status':
				echo get_post_meta($post_id, '_transaction_status', true) ? esc_attr(get_post_meta($post_id, '_transaction_status', true)) : 'Complete';
				break;

			case 'type':
				$status = get_post_meta($post_id, '_status', true);
				if ('refund' === $status) {
					echo '<span style="color: #b32d2e;">Refund</span>';
				} elseif (get_post_meta($post_id, '_is_recurring', true)) {
					echo 'Recurring';
				} else {
					echo 'One Time';
				}
				break;
		}
	}
	/**
	 * Add filter dropdowns above the transaction list table.
	 *
	 * @param string $post_type The current post type.
	 * @param string $which     Top or bottom.
	 */
	public function add_filters($post_type, $which)
	{
		if ('lp_transaction' !== $post_type) {
			return;
		}

		$current_type    = isset($_GET['lp_payment_type']) ? sanitize_text_field($_GET['lp_payment_type']) : '';
		$current_level   = isset($_GET['lp_level']) ? sanitize_text_field($_GET['lp_level']) : '';
		$current_gateway = isset($_GET['lp_gateway']) ? sanitize_text_field($_GET['lp_gateway']) : '';
		$current_date    = isset($_GET['lp_date_range']) ? sanitize_text_field($_GET['lp_date_range']) : '';

		// Date range filter.
		?>
		<select name="lp_date_range">
			<option value=""><?php esc_html_e('All dates', 'leaky-paywall'); ?></option>
			<option value="today" <?php selected($current_date, 'today'); ?>><?php esc_html_e('Today', 'leaky-paywall'); ?></option>
			<option value="7days" <?php selected($current_date, '7days'); ?>><?php esc_html_e('Last 7 days', 'leaky-paywall'); ?></option>
			<option value="30days" <?php selected($current_date, '30days'); ?>><?php esc_html_e('Last 30 days', 'leaky-paywall'); ?></option>
			<option value="this_month" <?php selected($current_date, 'this_month'); ?>><?php esc_html_e('This month', 'leaky-paywall'); ?></option>
			<option value="last_month" <?php selected($current_date, 'last_month'); ?>><?php esc_html_e('Last month', 'leaky-paywall'); ?></option>
			<option value="this_year" <?php selected($current_date, 'this_year'); ?>><?php esc_html_e('This year', 'leaky-paywall'); ?></option>
		</select>
		<?php

		// Payment type filter.
		?>
		<select name="lp_payment_type">
			<option value=""><?php esc_html_e('All payment types', 'leaky-paywall'); ?></option>
			<option value="paid" <?php selected($current_type, 'paid'); ?>><?php esc_html_e('Paid', 'leaky-paywall'); ?></option>
			<option value="free" <?php selected($current_type, 'free'); ?>><?php esc_html_e('Free', 'leaky-paywall'); ?></option>
			<option value="refund" <?php selected($current_type, 'refund'); ?>><?php esc_html_e('Refund', 'leaky-paywall'); ?></option>
			<option value="renewal" <?php selected($current_type, 'renewal'); ?>><?php esc_html_e('Renewal', 'leaky-paywall'); ?></option>
		</select>
		<?php

		// Level filter.
		$settings = get_leaky_paywall_settings();
		$levels   = isset($settings['levels']) ? $settings['levels'] : array();

		if (!empty($levels)) {
			?>
			<select name="lp_level">
				<option value=""><?php esc_html_e('All levels', 'leaky-paywall'); ?></option>
				<?php foreach ($levels as $level_id => $level) : ?>
					<option value="<?php echo esc_attr($level_id); ?>" <?php selected($current_level, (string) $level_id); ?>>
						<?php echo esc_html(isset($level['label']) ? $level['label'] : 'Level ' . $level_id); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		}

		// Gateway filter.
		global $wpdb;
		$gateways = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = '_gateway' AND meta_value != ''
			 ORDER BY meta_value ASC"
		);

		if (!empty($gateways)) {
			?>
			<select name="lp_gateway">
				<option value=""><?php esc_html_e('All gateways', 'leaky-paywall'); ?></option>
				<?php foreach ($gateways as $gateway) : ?>
					<option value="<?php echo esc_attr($gateway); ?>" <?php selected($current_gateway, $gateway); ?>>
						<?php echo esc_html(ucwords(str_replace('_', ' ', $gateway))); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		}
	}

	/**
	 * Modify the query based on the selected filters.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function apply_filters($query)
	{
		global $pagenow;

		if (!is_admin() || 'edit.php' !== $pagenow || !$query->is_main_query()) {
			return;
		}

		if ($query->get('post_type') !== 'lp_transaction') {
			return;
		}

		$meta_query = $query->get('meta_query');
		if (!is_array($meta_query)) {
			$meta_query = array();
		}

		// Payment type filter.
		if (!empty($_GET['lp_payment_type'])) {
			$type = sanitize_text_field($_GET['lp_payment_type']);

			switch ($type) {
				case 'paid':
					$meta_query[] = array(
						'key'     => '_price',
						'value'   => '0',
						'compare' => '>',
						'type'    => 'DECIMAL(10,2)',
					);
					$meta_query[] = array(
						'key'     => '_status',
						'value'   => 'refund',
						'compare' => '!=',
					);
					break;
				case 'free':
					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key'     => '_price',
							'value'   => '0',
							'compare' => '=',
							'type'    => 'DECIMAL(10,2)',
						),
						array(
							'key'     => '_price',
							'compare' => 'NOT EXISTS',
						),
					);
					break;
				case 'refund':
					$meta_query[] = array(
						'key'     => '_status',
						'value'   => 'refund',
						'compare' => '=',
					);
					break;
				case 'renewal':
					$meta_query[] = array(
						'key'     => '_is_recurring',
						'value'   => '1',
						'compare' => '=',
					);
					break;
			}
		}

		// Level filter.
		if (isset($_GET['lp_level']) && '' !== $_GET['lp_level']) {
			$meta_query[] = array(
				'key'     => '_level_id',
				'value'   => sanitize_text_field($_GET['lp_level']),
				'compare' => '=',
			);
		}

		// Gateway filter.
		if (!empty($_GET['lp_gateway'])) {
			$meta_query[] = array(
				'key'     => '_gateway',
				'value'   => sanitize_text_field($_GET['lp_gateway']),
				'compare' => '=',
			);
		}

		if (!empty($meta_query)) {
			$query->set('meta_query', $meta_query);
		}

		// Date range filter.
		if (!empty($_GET['lp_date_range'])) {
			$range = sanitize_text_field($_GET['lp_date_range']);
			$after = '';

			switch ($range) {
				case 'today':
					$after = gmdate('Y-m-d 00:00:00');
					break;
				case '7days':
					$after = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
					break;
				case '30days':
					$after = gmdate('Y-m-d H:i:s', strtotime('-30 days'));
					break;
				case 'this_month':
					$after = gmdate('Y-m-01 00:00:00');
					break;
				case 'last_month':
					$after = gmdate('Y-m-01 00:00:00', strtotime('first day of last month'));
					$before = gmdate('Y-m-01 00:00:00');
					break;
				case 'this_year':
					$after = gmdate('Y-01-01 00:00:00');
					break;
			}

			if ($after) {
				$date_query = array(
					array('after' => $after, 'inclusive' => true),
				);

				if (!empty($before)) {
					$date_query[0]['before'] = $before;
				}

				$query->set('date_query', $date_query);
			}
		}

		// Sortable column ordering.
		$orderby = $query->get('orderby');

		if ('transaction_price' === $orderby) {
			$query->set('meta_key', '_price');
			$query->set('orderby', 'meta_value_num');
		}
	}

	/**
	 * Remove the default WordPress months dropdown for transactions.
	 *
	 * @param array  $months    The months data.
	 * @param string $post_type The current post type.
	 * @return array
	 */
	public function remove_months_dropdown($months, $post_type)
	{
		if ('lp_transaction' === $post_type) {
			return array();
		}

		return $months;
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @param array $columns The sortable columns.
	 * @return array
	 */
	public function sortable_columns($columns)
	{
		$columns['price']   = 'transaction_price';
		$columns['created'] = 'date';

		return $columns;
	}
}

new LP_Transaction_Post_Type();
