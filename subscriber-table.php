<?php

/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class IssueM_Leaky_Paywall_Subscriber_List_Table extends WP_List_Table {
	
	function ajax_user_can() {
		return current_user_can( 'manage_network_users' );
	}
	
	function prepare_items() {
		global $usersearch, $wpdb;
	
		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();
	   
		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array($columns, $hidden, $sortable);

		$usersearch = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

		$users_per_page = $this->get_items_per_page( 'users_network_per_page' );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'search' => $usersearch,
		);

		// If a search is not being performed, show only the latest users with no paging in order
		// to avoid expensive count queries.
		if ( !$usersearch ) {
			if ( !isset($_REQUEST['orderby']) )
				$_GET['orderby'] = $_REQUEST['orderby'] = 'created';
			if ( !isset($_REQUEST['order']) )
				$_GET['order'] = $_REQUEST['order'] = 'DESC';
			$args['count_total'] = false;
		}

		if ( !empty( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( !empty( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		// Query the user IDs for this page
		$results = issuem_leaky_paywall_subscriber_query( $args );
		
		$this->items = $results;
		
		$args['number'] = 0;
		$this->set_pagination_args( array(
			'total_items' => count( issuem_leaky_paywall_subscriber_query( $args ) ),
			'per_page' => $users_per_page,
		) );
	}

	function no_items() {
		_e( 'No Leaky Paywall subscribers found.' );
	}

	function get_columns() {
		$users_columns = array(
			'wp_user_login' => __( 'WordPress Username', 'issuem-leaky-paywall' ),
			'email'         => __( 'E-mail', 'issuem-leaky-paywall' ),
			'susbcriber_id' => __( 'Subscriber ID', 'issuem-leaky-paywall' ),
			'price'         => __( 'Price', 'issuem-leaky-paywall' ),
			'plan'          => __( 'Plan', 'issuem-leaky-paywall' ),
			'expires'       => __( 'Expires', 'issuem-leaky-paywall' ),
			'gateway'       => __( 'Gateway', 'issuem-leaky-paywall' ),
			'status'        => __( 'Status', 'issuem-leaky-paywall' ),
		);
		$users_columns = apply_filters( 'leaky_paywall_subscribers_columns', $users_columns );

		return $users_columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'wp_user_login' => array( 'wp_user_login', false ),
			'email'         => array( 'email', false ),
			'price'         => array( 'price', false ),
			'plan'          => array( 'plan', false ),
			'expires'       => array( 'expires', false ),
			'gateway'       => array( 'payment_gateway', false ),
			'status'        => array( 'payment_status', false ),
		);
		$sortable_columns = apply_filters( 'leaky_paywall_subscribers_sortable_columns', $sortable_columns );

		return $sortable_columns;
	}

	function display_rows() {
		global $current_site;

		$alt = '';
		foreach ( $this->items as $user ) {
			$alt = ( 'alternate' == $alt ) ? '' : 'alternate';
			$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $user->user_email );
			?>
			<tr class="<?php echo $alt; ?>">
			<?php

			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $columns as $column_name => $column_display_name ) :
				$class = "class='$column_name column-$column_name'";

				$style = '';
				if ( in_array( $column_name, $hidden ) )
					$style = ' style="display:none;"';

				$attributes = "$class$style";

				switch ( $column_name ) {
					case 'wp_user_login':
						echo "<td $attributes>"; ?>
							<strong><?php echo $user->user_login; ?></strong>
						</td>
					<?php
					break;
					case 'email':
						$avatar	= get_avatar( $user->user_email, 32 );
						$edit_link = esc_url( add_query_arg( 'edit', urlencode( $user->user_email ) ) );

						echo "<td $attributes>"; ?>
							<?php echo $avatar; ?><strong><a href="<?php echo $edit_link; ?>" class="edit"><?php echo $user->user_email; ?></a></strong>
						</td>
					<?php
					break;
					
					case 'susbcriber_id':
						echo '<td $attributes>' . $subscriber['subscriber_id'] . '</td>';
					break;

					case 'plan':
						if ( empty( $user->plan ) ) {
							$plan = "not recurring";	
						} else if ( 'paypal_standard' === $subscriber['payment_gateway'] ) {
							$plan = sprintf( __( 'Recurring every %s', 'issuem-leaky-paywall' ), str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $subscriber['plan'] ) );
						} else {
							$plan = $subscriber['plan'];	
						}
						
						echo "<td $attributes>$plan</td>";
					break;

					case 'price':
						echo '<td $attributes>' . $subscriber['price'] . '</td>';
					break;

					case 'expires':
						if ( empty( $subscriber['expires'] ) || '0000-00-00 00:00:00' === $subscriber['expires'] ) {
							if ( 'manual' === $subscriber['payment_gateway'] )
								$expires = __( 'Never', 'issuem-leaky-paywall' );
							else if ( 'stripe' === $subscriber['payment_gateway'] )
								$expires = __( 'Determined by Stripe', 'issuem-leaky-paywall' );
						} else {
							$date_format = get_option( 'date_format' );
							$expires = mysql2date( $date_format, $subscriber['expires'] );
						}
						
						echo "<td $attributes>" . $expires . "</td>";
					break;

					case 'gateway':
						echo "<td $attributes>" . issuem_translate_payment_gateway_slug_to_name( $subscriber['payment_gateway'] ) . "</td>";
					break;

					case 'status':
						echo '<td $attributes>' . $subscriber['payment_status'] . '</td>';
					break;

					default:
						echo "<td $attributes>";
						echo apply_filters( 'manage_leaky_paywall_susbcribers_custom_column', '', $column_name, $subscriber['hash'] );
						echo "</td>";
					break;
				}
			endforeach
			?>
			</tr>
			<?php
		}
	}
	
}