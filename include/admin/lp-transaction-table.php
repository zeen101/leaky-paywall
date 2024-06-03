<?php

/*************************** LOAD THE BASE CLASS *******************************
 * ******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 *
 * @package Leaky Paywall
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
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
class LP_Transaction_Table extends WP_List_Table {

	/**
	 * The constructor
	 */
	/**
	 * Ajax user can
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_network_users' );
	}

	/**
	 * Prepare items for table
	 */
	public function prepare_items() {

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
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$transaction_search    = isset( $_REQUEST['s'] ) ? '*' . sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) . '*' : '';
		$per_page              = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
		$transactions_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $transactions_per_page,
			'offset' => ( $paged - 1 ) * $transactions_per_page,
			'search' => $transaction_search,
		);

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
		} else {
			$args['orderby'] = 'ID';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			$args['order'] = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) );
		} else {
			$args['order'] = 'DESC';
		}

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		if ( isset( $_GET['custom_field_search'] ) && 'on' === $_GET['custom_field_search'] ) {

			$args['where'] = '
				   `email`                  LIKE "%' . $_GET['s'] . '%"
				|| `first_name`             LIKE "%' . $_GET['s'] . '%"
				|| `last_name`              LIKE "%' . $_GET['s'] . '%"
				|| `level_id`               LIKE "%' . $_GET['s'] . '%"
				|| `price`                  LIKE "%' . $_GET['s'] . '%"
				|| `currency`               LIKE "%' . $_GET['s'] . '%"
				|| `payment_gateway`        LIKE "%' . $_GET['s'] . '%"
				|| `payment_gateway_txn_id` LIKE "%' . $_GET['s'] . '%"
				|| `payment_status`         LIKE "%' . $_GET['s'] . '%"
				|| `transaction_status`     LIKE "%' . $_GET['s'] . '%"
			';

		}

		$transactions = LP_Transaction::query( $args );
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
	public function no_items() {
		esc_attr_e( 'No Leaky Paywall transactions found.' );
	}

	/**
	 * Get columns
	 */
	public function get_columns() {
		$transaction_columns = array(
			'wp_user_login'      => __( 'WordPress Username', 'leaky-paywall' ),
			'email'              => __( 'E-mail', 'leaky-paywall' ),
			'name'               => __( 'Name', 'leaky-paywall' ),
			'level_id'           => __( 'Level ID', 'leaky-paywall' ),
			'price'              => __( 'Price', 'leaky-paywall' ),
			'currency'           => __( 'Currency', 'leaky-paywall' ),
			'gateway'            => __( 'Payment Gateway', 'leaky-paywall' ),
			'gateway_txn_id'     => __( 'Payment Transaction ID', 'leaky-paywall' ),
			'payment_status'     => __( 'Payment Status', 'leaky-paywall' ),
			'transaction_status' => __( 'Transaction Status', 'leaky-paywall' ),
		);
		$transaction_columns = apply_filters( 'leaky_paywall_transaction_columns', $transaction_columns );

		return $transaction_columns;
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'wp_user_login'      => array( 'wp_user_login', false ),
			'email'              => array( 'email', false ),
			'name'               => array( 'name', false ),
			'level_id'           => array( 'level_id', false ),
			'price'              => array( 'price', false ),
			'currency'           => array( 'currency', false ),
			'gateway'            => array( 'gateway', false ),
			'gateway_txn_id'     => array( 'gateway_txn_id', false ),
			'payment_status'     => array( 'payment_status', false ),
			'transaction_status' => array( 'transaction_status', false ),
		);
		$sortable_columns = apply_filters( 'leaky_paywall_transaction_sortable_columns', $sortable_columns );

		return $sortable_columns;
	}

	/**
	 * Display rows in subscriber table
	 */
	public function display_rows() {
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		$alt = '';

		foreach ( $this->items as $transaction ) {

			$user = get_user_by( 'ID', $transaction->user_id );

			$alt = ( 'alternate' === $alt ) ? '' : 'alternate';

			?>
			<tr class="<?php echo esc_attr( $alt ); ?>">
				<?php

				list($columns, $hidden) = $this->get_column_info();

				foreach ( $columns as $column_name => $column_display_name ) {

					$class = "$column_name column-$column_name";

					$style = '';
					if ( in_array( $column_name, $hidden, true ) ) {
						$style = 'display:none;';
					}

					switch ( $column_name ) {
						case 'wp_user_login':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							?>
							<strong><?php echo esc_html( $user->user_login ); ?></strong>

							<?php
							// if the user switching plugin is activated, add switch to link to LP subscriber table for easier testing.
							if ( method_exists( 'user_switching', 'maybe_switch_url' ) ) {
								if ( is_object( $user ) ) {
									$link = user_switching::maybe_switch_url( $user );
									if ( $link ) {
										echo '<br><a href="' . esc_url( $link ) . '">' . esc_html__( 'Switch&nbsp;To', 'leaky-paywall' ) . '</a>';
									}
								}
							}

							?>
							</td>
							<?php
							break;
						case 'email':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							?>
							<strong><?php echo esc_html( $user->user_email ); ?></strong>
							</td>
							<?php
							break;

						case 'name':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							?>
							<?php echo esc_attr( $transaction->first_name ); ?> <?php echo esc_attr( $transaction->last_name ); ?>
							</td>
							<?php
							break;

						case 'level_id':

							$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $transaction->level_id, $user, $mode, $site );
							$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $transaction->level_id );
							if ( false === $level_id || empty( $settings['levels'][ $level_id ]['label'] ) ) {
								$level_name = __( 'Undefined', 'leaky-paywall' );
							} else {
								$level_name = stripcslashes( $settings['levels'][ $level_id ]['label'] );
							}
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $level_name );
							echo '</td>';
							break;

						case 'price':
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo number_format( (float) $transaction->price, '2' );
								echo '</td>';
							break;

						case 'currency':
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo $transaction->currency;
								echo '</td>';
							break;

						case 'gateway':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( leaky_paywall_translate_payment_gateway_slug_to_name( $transaction->payment_gateway ) );
							echo '</td>';
							break;

						case 'gateway_txn_id':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( leaky_paywall_translate_payment_gateway_slug_to_name( $transaction->payment_gateway_txn_id ) );
							echo '</td>';
							break;

						case 'payment_status':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( leaky_paywall_translate_payment_gateway_slug_to_name( $transaction->payment_status ) );
							echo '</td>';
							break;

						case 'transaction_status':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( leaky_paywall_translate_payment_gateway_slug_to_name( $transaction->transaction_status ) );
							echo '</td>';
							break;

						case 'created':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $transaction->date_created );
							echo '</td>';
							break;

						default:
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( apply_filters( 'manage_leaky_paywall_subscribers_custom_column', '&nbsp;', $column_name, $user->ID ) );
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
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['post_mime_type'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['detached'] ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['detached'] ) ) ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="checkbox" name="custom_field_search"> Search custom fields<br>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo esc_attr( $search_query ); ?>" />

			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

}