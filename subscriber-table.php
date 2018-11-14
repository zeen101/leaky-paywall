<?php

/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary.
 */
if( !class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class Leaky_Paywall_Subscriber_List_Table extends WP_List_Table {
	
	function ajax_user_can() {
		return current_user_can( 'manage_network_users' );
	}
	
	public function prepare_items() {
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

		$usersearch = isset( $_REQUEST['s'] ) ? '*' . sanitize_text_field( $_REQUEST['s'] ) . '*' : '';

		// $users_per_page = $this->get_items_per_page( 'users_network_per_page' );
		$per_page = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'search' => $usersearch,
		);

		// If a search is not being performed, show only the latest users with no paging in order
		// to avoid expensive count queries.
		if ( !$usersearch ) {
			if ( !isset( $_REQUEST['orderby'] ) ) {
				$_GET['orderby'] = $_REQUEST['orderby'] = 'ID';
			}
			if ( !isset( $_REQUEST['order'] ) ) {
				$_GET['order'] = $_REQUEST['order'] = 'DESC';
			}
			// $args['count_total'] = false;
		}


		if ( $this->is_site_users ) {
			$args['blog_id'] = $this->site_id;
		}

		if ( !empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
		}

		if ( !empty( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		// Query the user IDs for this page
		// global $blog_id;
		// $results = leaky_paywall_subscriber_query( $args, $blog_id );

		$settings = get_leaky_paywall_settings();
		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

		// if ( is_multisite_premium() && is_main_site( $blog_id ) ) {
		// 	$results = array_merge( $results, leaky_paywall_subscriber_query( $args, false ) );
		// }
		
		// $this->items = $results;
		// 
		
		$args['meta_query'] = array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'compare' => 'EXISTS',
			),
		);

		// search by subscriber id
		if ( isset( $_GET['custom_field_search'] ) && 'on' == $_GET['custom_field_search'] ) {

			// if is custom field, then do the following
			
			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'value'   => $_GET['s'],
				'compare'	=> 'LIKE'
			);

			unset( $args['search'] );
		}

		

		if ( isset( $_GET['filter-level'] ) && 'lpsubs' == $_GET['user-type'] ) {

			$level = esc_attr( $_GET['filter-level'] );

			if ( 'all' != $level ) {

				$args['meta_query'][] = array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
					'value'   => $level,
					'compare' => 'LIKE',
				);

			}
			
		}

		if ( isset( $_GET['filter-status'] ) && 'lpsubs' == $_GET['user-type'] ) {

			$status = esc_attr( $_GET['filter-status'] );

			if ( 'all' != $status ) {

				$args['meta_query'][] = array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
					'value'   => $status,
					'compare' => 'LIKE',
				);

			}
			
		}

		if ( !empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
			unset( $args['meta_query'] );
		}


		$wp_user_search = new WP_User_Query( $args );
		$this->items = $wp_user_search->get_results();
		
		// $args['number'] = 0;
		// $this->set_pagination_args( array(
		// 	'total_items' => count( leaky_paywall_subscriber_query( $args ) ),
		// 	'per_page' => $users_per_page,
		// ) );

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $users_per_page,
		) );
	}

	public function new_prepare_items() 
	{
		
		global $role, $usersearch;

		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$per_page = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		if ( 'none' === $role ) {
			$args = array(
				'number' => $users_per_page,
				'offset' => ( $paged-1 ) * $users_per_page,
				'include' => wp_get_users_with_no_role( $this->site_id ),
				'search' => $usersearch,
				'fields' => 'all_with_meta'
			);
		} else {
			$args = array(
				'number' => $users_per_page,
				'offset' => ( $paged-1 ) * $users_per_page,
				'role' => $role,
				'search' => $usersearch,
				'fields' => 'all_with_meta'
			);
		}

		if ( '' !== $args['search'] )
			$args['search'] = '*' . $args['search'] . '*';

		if ( $this->is_site_users )
			$args['blog_id'] = $this->site_id;

		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		/**
		 * Filters the query arguments used to retrieve users for the current users list table.
		 *
		 * @since 4.4.0
		 *
		 * @param array $args Arguments passed to WP_User_Query to retrieve items for the current
		 *                    users list table.
		 */
		$args = apply_filters( 'users_list_table_query_args', $args );

		// Query the user IDs for this page
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $users_per_page,
		) );

	}

	function no_items() {
		_e( 'No Leaky Paywall subscribers found.' );
	}

	function get_columns() {
		$users_columns = array(
			'wp_user_login' => __( 'WordPress Username', 'leaky-paywall' ),
			'email'         => __( 'E-mail', 'leaky-paywall' ),
			'name'         => __( 'Name', 'leaky-paywall' ),
			'level_id' 		=> __( 'Level ID', 'leaky-paywall' ),
			'susbcriber_id' => __( 'Subscriber ID', 'leaky-paywall' ),
			'price'         => __( 'Price', 'leaky-paywall' ),
			'plan'          => __( 'Plan', 'leaky-paywall' ),
			'created'       => __( 'Created', 'leaky-paywall' ),
			'expires'       => __( 'Expires', 'leaky-paywall' ),
			'has_access'    => __( 'Has Access', 'leaky-paywall' ),
			'gateway'       => __( 'Gateway', 'leaky-paywall' ),
			'status'        => __( 'Payment Status', 'leaky-paywall' ),
		);
		$users_columns = apply_filters( 'leaky_paywall_subscribers_columns', $users_columns );

		return $users_columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'wp_user_login' => array( 'wp_user_login', false ),
			'email'         => array( 'email', false ),
			'level_id' 		=> array( 'level_id', false ),
			'susbcriber_id' => array( 'susbcriber_id', false ),
			'price'         => array( 'price', false ),
			'plan'          => array( 'plan', false ),
			'gateway'       => array( 'payment_gateway', false ),
			'status'        => array( 'payment_status', false ),
		);
		$sortable_columns = apply_filters( 'leaky_paywall_subscribers_sortable_columns', $sortable_columns );

		return $sortable_columns;
	}
	
	function user_views() {
		$user_type = !empty( $_GET['user-type'] ) ? $_GET['user-type'] : 'lpsubs';
	
		echo '<div class="alignleft actions">';
		echo '<label for="user-type-selector" class="screen-reader-text">' . __( 'Select User Type' ) . '</label>';
		echo '<select name="user-type" id="user-type-selector">';
		echo '<option value="lpsubs" ' . selected( 'lpsubs', $user_type, false ) . '>' . __( 'Leaky Paywall Subscribers' ) . '</option>';
		echo '<option value="wpusers" ' . selected( 'wpusers', $user_type, false ) . '>' . __( 'All WordPress Users' ) . '</option>';
		echo '</select>';

		submit_button( __( 'Apply' ), 'primary', false, false );
		echo '</div>';

	}

	public function extra_tablenav( $which ) {

		if ( $which != 'top' ) {
			return;
		}

		if ( isset( $_GET['user-type'] ) && $_GET['user-type'] != 'lpsubs' ) {
			return;
		}

		$levels = leaky_paywall_get_levels();
		$lev = isset( $_GET['filter-level'] ) ? esc_attr( $_GET['filter-level'] ) : 'all';	
		$stat = isset( $_GET['filter-status'] ) ? esc_attr( $_GET['filter-status'] ) : 'all';	
		?>
		
		<div class="alignleft actions">
			<label for="filter-by-level" class="screen-reader-text">Filter by level</label>
			<select name="filter-level" id="filter-by-level">
				<option value="all" <?php selected( $lev, 'all' ); ?>>All Levels</option>
				<?php 
					foreach( $levels as $key => $level ) {
						echo '<option ' . selected( $key, $lev, false ) . ' value="' . $key . '">' . $level['label'] . '</option>';
					}
				?>
			</select>

			<?php 
				$status_filter_args = apply_filters( 'leaky_paywall_status_filter_args', array(
					'all'	=> 'All Statuses',
					'active'	=> 'Active',
					'deactivated'	=> 'Deactivated',
					'canceled'	=> 'Canceled',
				) );
			?>

			<label for="filter-by-status" class="screen-reader-text">Filter by status</label>
			<select name="filter-status" id="filter-by-status">

				<?php foreach( $status_filter_args as $key => $value ) {
					?>	
					<option value="<?php echo $key; ?>" <?php selected( $stat, $key ); ?>><?php echo $value; ?></option>
					<?php 
				} ?>
				
			</select>

			<input name="filter_action" id="subscriber-query-submit" class="button" value="Filter" type="submit">
		</div>
		
		<?php 
	}

	function display_rows() {
		global $current_site, $blog_id;
		$settings = get_leaky_paywall_settings();
	
		// create a generic array so that single site installs will iterate through the sites loop below
		$sites = array( '_all' );
	
		if ( is_multisite_premium() ) {
			global $blog_id;			

			if ( !is_main_site( $blog_id ) ) {
				$sites = array( '_' . $blog_id );
			} else {
				$sites = array( '_' . $blog_id, '' );
			}
		}
		
		$alt = '';


		foreach ( $this->items as $user ) {

			$user = get_user_by( 'email', $user->user_email );
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
			foreach( $sites as $site ) {

				$alt = ( 'alternate' == $alt ) ? '' : 'alternate';

				if ( is_multisite_premium() ) {
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
				} else {
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway', true );
				}
				
				if ( !empty( $_GET['user-type'] ) && 'wpusers' !== $_GET['user-type'] ) {
					if ( empty( $payment_gateway ) ) {
						continue;
					}
				}
				
				?>
				<tr class="<?php echo $alt; ?>">
				<?php
	
				list( $columns, $hidden ) = $this->get_column_info();
	
				foreach ( $columns as $column_name => $column_display_name ) {

					$class = "class='$column_name column-$column_name'";
	
					$style = '';
					if ( in_array( $column_name, $hidden ) )
						$style = ' style="display:none;"';
	
					$attributes = "$class$style";
	
					switch ( $column_name ) {
						case 'wp_user_login':
							echo "<td $attributes>"; ?>
								<strong><?php echo $user->user_login; ?></strong>

								<?php 
								// if the user switching plugin is activated, add switch to link to LP subscriber table for easier testing
								if ( class_exists( 'user_switching' ) ) {
									if ( $link = user_switching::maybe_switch_url( $user ) ) {
										echo '<br><a href="' . esc_url( $link ) . '">' . esc_html__( 'Switch&nbsp;To', 'leaky-paywall' ) . '</a>';
									}
								}
								
								?>
							</td>
						<?php
						break;
						case 'email':
							$edit_link = esc_url( add_query_arg( 'edit', urlencode( $user->user_email ) ) );
							echo "<td $attributes>"; ?>
								<strong><a href="<?php echo $edit_link; ?>" class="edit"><?php echo $user->user_email; ?></a></strong>
							</td>
						<?php
						break;
						
						case 'name':
						
							echo "<td $attributes>"; ?>
								<?php echo $user->first_name; ?> <?php echo $user->last_name; ?>
							</td>
						<?php
						break;
						
						case 'level_id':
							if ( is_multisite_premium() ) {
								$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
							} else {
								$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', true );
							}
							
							$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
							$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
							if ( false === $level_id || empty( $settings['levels'][$level_id]['label'] ) ) {
								$level_name = __( 'Undefined', 'leaky-paywall' );
							} else {
								$level_name = stripcslashes( $settings['levels'][$level_id]['label'] );
							}
								
							echo "<td $attributes>" . $level_name . '</td>';
						break;
						
						case 'susbcriber_id':
							if ( is_multisite_premium() ) {
								echo "<td $attributes>" . get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true ) . '</td>';
							} else {
								echo "<td $attributes>" . get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id', true ) . '</td>';
							}
							
						break;
	
						case 'price':
							if ( is_multisite_premium() ) {
								echo "<td $attributes>" . number_format( (float)get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true ), '2' ) . '</td>';
							} else {
								echo "<td $attributes>" . number_format( (float)get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price', true ), '2' ) . '</td>';
							}
							
						break;
	
						case 'plan':
							if ( is_multisite_premium() ) {
								$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
							} else {
								$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan', true );
							}
							
							if ( empty( $plan ) ) {
								$plan = __( 'Non-Recurring', 'leaky-paywall' );	
							} else if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
								$plan = sprintf( __( 'Recurring every %s', 'leaky-paywall' ), str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $plan ) );
							}
							
							echo "<td $attributes>" . $plan . '</td>';
						break;
	
						case 'created':
							if ( is_multisite_premium() ) {
								$created = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_created' . $site, true );
							} else {
								$created = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_created', true );
							}
							
							$created = apply_filters( 'do_leaky_paywall_profile_shortcode_created_column', $created, $user, $mode, $site, $level_id );
							
							$date_format = get_option( 'date_format' );
							$created = mysql2date( $date_format, $created );
							
							echo "<td $attributes>" . $created . '</td>';
						break;
	
						case 'expires':
							if ( is_multisite_premium() ) {
								$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
							} else {
								$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires', true );
							}
							
							$expires = apply_filters( 'do_leaky_paywall_profile_shortcode_expiration_column', $expires, $user, $mode, $site, $level_id );
							
							if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires || 'Never' === $expires ) {
								$expires = __( 'Never', 'leaky-paywall' );
							} else {
								$date_format = get_option( 'date_format' );
								$expires = mysql2date( $date_format, $expires );
							}
							
							echo "<td $attributes>" . $expires . '</td>';
						break;

						case 'has_access':
							$has_access = leaky_paywall_user_has_access( $user );

							if ( $has_access ) {
								echo '<td ' . $attributes . '>Yes</td>';
							} else {
								echo '<td ' . $attributes . '>No</td>';
							}
						break;

						case 'gateway':
							echo "<td $attributes>" . leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) . '</td>';
						break;
	
						case 'status':
							if ( is_multisite_premium() ) {
								echo "<td $attributes>" . get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true ) . '</td>';
							} else {
								echo "<td $attributes>" . get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status', true ) . '</td>';
							}
							
						break;
	
						default:
							echo "<td $attributes>" . apply_filters( 'manage_leaky_paywall_subscribers_custom_column', '&nbsp;', $column_name, $user->ID ) . '</td>';
						break;
					}
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
		if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
			return;

		$input_id = $input_id . '-search-input';

		$search_query = isset($_REQUEST['s']) ? trim( esc_attr( wp_unslash( $_REQUEST['s'] ) ) ) : '';

		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		if ( ! empty( $_REQUEST['post_mime_type'] ) )
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
		if ( ! empty( $_REQUEST['detached'] ) )
			echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo $text; ?>:</label>
			<input type="checkbox" name="custom_field_search"> Search custom fields<br>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo $search_query; ?>" />

			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}
	
}
