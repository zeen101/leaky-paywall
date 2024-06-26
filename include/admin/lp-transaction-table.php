<?php

/*************************** LOAD THE BASE CLASS *******************************
 * ******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 *
 * @package Leaky Paywall
 */

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


/**
 * Leaky Paywall transaction table display
 *
 * @package Leaky Paywall
 */

/**
 * Load the base class
 */
class LP_Transaction_Table extends WP_List_Table
{

	private $removable_query_args;

	function __construct()
	{

		parent::__construct();

		$this->removable_query_args = wp_removable_query_args();
		$this->removable_query_args = array_merge(
			$this->removable_query_args,
			[
				'order_by',
				'order',
				'limit',
				'offset',
				'search',
			]
		);
	}

	/**
	 * The constructor
	 */
	/**
	 * Ajax user can
	 */
	public function ajax_user_can()
	{
		return current_user_can('manage_network_users');
	}

	/**
	 * Prepare items for table
	 */
	public function prepare_items()
	{

		global $wpdb;

		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array($columns, $hidden, $sortable);

		$transaction_search    = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
		$per_page              = ($this->is_site_users) ? 'site_users_network_per_page' : 'users_per_page';
		$transactions_per_page = $this->get_items_per_page($per_page);

		$paged = $this->get_pagenum();

		$args = array(
			'limit' => $transactions_per_page,
			'offset' => ($paged - 1) * $transactions_per_page,
			'search' => $transaction_search,
		);

		if (!empty($_REQUEST['orderby'])) {
			$args['order_by'] = sanitize_text_field(wp_unslash($_REQUEST['orderby']));
		} else {
			$args['order_by'] = 'ID';
		}

		if (!empty($_REQUEST['order'])) {
			$args['order'] = sanitize_text_field(wp_unslash($_REQUEST['order']));
		} else {
			$args['order'] = 'DESC';
		}

		if ($transaction_search) {

			$args['where'] = '
				   `email`                  LIKE "%' . $transaction_search . '%"
				|| `first_name`             LIKE "%' . $transaction_search . '%"
				|| `last_name`              LIKE "%' . $transaction_search . '%"
				|| `level_id`               LIKE "%' . $transaction_search . '%"
				|| `price`                  LIKE "%' . $transaction_search . '%"
				|| `currency`               LIKE "%' . $transaction_search . '%"
				|| `payment_gateway`        LIKE "%' . $transaction_search . '%"
				|| `payment_gateway_txn_id` LIKE "%' . $transaction_search . '%"
				|| `payment_status`         LIKE "%' . $transaction_search . '%"
				|| `transaction_status`     LIKE "%' . $transaction_search . '%"
			';
		}

		$transactions = LP_Transaction::query($args);
		$this->items    = $transactions;

		$this->set_pagination_args(
			array(
				'total_items' => LP_Transaction::get_total(),
				'per_page'    => $transactions_per_page,
			)
		);
	}

	/**
	 * No items text
	 */
	public function no_items()
	{
		esc_attr_e('No Leaky Paywall transactions found.');
	}

	/**
	 * Get columns
	 */
	public function get_columns()
	{
		$transaction_columns = array(
			'transaction'      => __('Transaction', 'leaky-paywall'),
			'created'              => __('Created', 'leaky-paywall'),
			'email'              => __('E-mail', 'leaky-paywall'),
			'level_id'           => __('Level', 'leaky-paywall'),
			'price'              => __('Total', 'leaky-paywall'),
			'payment_gateway'    => __('Gateway', 'leaky-paywall'),
			'transaction_status' => __('Status', 'leaky-paywall'),
		);
		$transaction_columns = apply_filters('leaky_paywall_transaction_columns', $transaction_columns);

		return $transaction_columns;
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns()
	{
		$sortable_columns = array(
			'email'                  => array('email', false),
			'created'                  => array('created', false),
			'level_id'               => array('level_id', false),
			'price'                  => array('price', false),
			'payment_gateway'        => array('payment_gateway', false),
			'transaction_status'     => array('transaction_status', false),
		);
		$sortable_columns = apply_filters('leaky_paywall_transaction_sortable_columns', $sortable_columns);

		return $sortable_columns;
	}

	/**
	 * Display rows in subscriber table
	 */
	public function display_rows()
	{
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		$alt = '';

		$current_url = set_url_scheme('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		$current_url = remove_query_arg($this->removable_query_args, $current_url);

		foreach ($this->items as $transaction) {

			$user = get_user_by('ID', $transaction->user_id);

			$alt = ('alternate' === $alt) ? '' : 'alternate';

?>
			<tr class="<?php echo esc_attr($alt); ?>">
				<?php

				list($columns, $hidden) = $this->get_column_info();

				foreach ($columns as $column_name => $column_display_name) {

					$class = "$column_name column-$column_name";

					$style = '';
					if (in_array($column_name, $hidden, true)) {
						$style = 'display:none;';
					}

					switch ($column_name) {
						case 'transaction':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
				?>
							<strong><a href="<?php echo esc_url(add_query_arg('edit', $transaction->ID, $current_url)) ?>">#<?php echo esc_html($transaction->ID) . ' ' . esc_html($user->first_name . ' ' . $user->last_name); ?> </a></strong>
							</td>
						<?php
							break;
						case 'email':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
						?>
							<strong><?php echo esc_html($user->user_email); ?></strong>
							</td>
				<?php
							break;

						case 'level_id':

							$level_id = apply_filters('get_leaky_paywall_users_level_id', $transaction->level_id, $user, $mode, $site);
							$level_id = apply_filters('get_leaky_paywall_subscription_level_level_id', $transaction->level_id);
							$level = get_leaky_paywall_subscription_level($transaction->level_id);
							if (false === $level_id || !$level['label'] ) {
								$level_name = __('Undefined', 'leaky-paywall');
							} else {
								$level_name = stripcslashes($level['label']);
							}
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo esc_attr($level_name);
							echo '</td>';
							break;

						case 'price':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo leaky_paywall_get_current_currency_symbol() . number_format((float) $transaction->price, '2');
							echo '</td>';
							break;

						case 'currency':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo $transaction->currency;
							echo '</td>';
							break;

						case 'payment_gateway':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo esc_attr(leaky_paywall_translate_payment_gateway_slug_to_name($transaction->payment_gateway));
							echo '</td>';
							break;

						case 'transaction_status':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo esc_attr(leaky_paywall_translate_payment_gateway_slug_to_name($transaction->transaction_status));
							echo '</td>';
							break;

						case 'created':
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo esc_html(date('M d, Y H:i:s', strtotime($transaction->date_created)));
							echo '</td>';
							break;

						default:
							echo '<td class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">';
							echo esc_attr(apply_filters('manage_leaky_paywall_subscribers_custom_column', '&nbsp;', $column_name, $user->ID));
							echo '</td>';
							break;
					}
				}

				?>
			</tr>
		<?php
		}
	}


	/**
	 * Displays the search box.
	 *
	 * @since 3.1.0
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 */
	public function search_box($text, $input_id)
	{
		if (empty($_REQUEST['s']) && !$this->has_items()) {
			return;
		}

		$input_id = $input_id . '-search-input';

		$search_query = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

		if (!empty($_REQUEST['orderby'])) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['orderby']))) . '" />';
		}
		if (!empty($_REQUEST['order'])) {
			echo '<input type="hidden" name="order" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['order']))) . '" />';
		}
		if (!empty($_REQUEST['post_mime_type'])) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['post_mime_type']))) . '" />';
		}
		if (!empty($_REQUEST['detached'])) {
			echo '<input type="hidden" name="detached" value="' . esc_attr(sanitize_text_field(wp_unslash($_REQUEST['detached']))) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
			<input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php echo esc_attr($search_query); ?>" />

			<?php submit_button($text, '', '', false, array('id' => 'search-submit')); ?>
		</p>
<?php
	}
}
