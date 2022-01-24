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
 * This class builds the subscriber table
 *
 * @since 1.0.0
 */
class Leaky_Paywall_Subscriber_List_Table extends WP_List_Table {

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

		$usersearch     = isset( $_REQUEST['s'] ) ? '*' . sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) . '*' : '';
		$per_page       = ( $this->is_site_users ) ? 'site_users_network_per_page' : 'users_per_page';
		$users_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged - 1 ) * $users_per_page,
			'search' => $usersearch,
		);

		// If a search is not being performed, show only the latest users with no paging in order.
		// to avoid expensive count queries.
		if ( ! $usersearch ) {
			if ( ! isset( $_REQUEST['orderby'] ) ) {
				$_GET['orderby'] = 'ID';
			}
			if ( ! isset( $_REQUEST['order'] ) ) {
				$_GET['order'] = 'DESC';
			}
		}

		if ( $this->is_site_users ) {
			$args['blog_id'] = $this->site_id;
		}

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

		$args['meta_query'] = array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'compare' => 'EXISTS',
			),
		);

		if ( isset( $_GET['custom_field_search'] ) && 'on' === $_GET['custom_field_search'] ) {

			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][]           = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'value'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'compare' => 'LIKE',
			);

			unset( $args['search'] );
		}

		if ( isset( $_GET['filter-level'] ) && isset( $_GET['user-type'] ) && 'lpsubs' === $_GET['user-type'] ) {

			$level = sanitize_text_field( wp_unslash( $_GET['filter-level'] ) );

			if ( 'all' !== $level ) {

				$args['meta_query'][] = array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
					'value'   => $level,
					'compare' => 'LIKE',
				);
			}
		}

		if ( isset( $_GET['filter-status'] ) && isset( $_GET['user-type'] ) && 'lpsubs' === $_GET['user-type'] ) {

			$status = sanitize_text_field( wp_unslash( $_GET['filter-status'] ) );

			if ( 'all' !== $status ) {

				$args['meta_query'][] = array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
					'value'   => $status,
					'compare' => 'LIKE',
				);
			}
		}

		if ( ! empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
			unset( $args['meta_query'] );
		}

		$wp_user_search = new WP_User_Query( $args );
		$this->items    = $wp_user_search->get_results();

		$this->set_pagination_args(
			array(
				'total_items' => $wp_user_search->get_total(),
				'per_page'    => $users_per_page,
			)
		);
	}

	/**
	 * No items text
	 */
	public function no_items() {
		esc_attr_e( 'No Leaky Paywall subscribers found.' );
	}

	/**
	 * Get columns
	 */
	public function get_columns() {
		$users_columns = array(
			'wp_user_login' => __( 'WordPress Username', 'leaky-paywall' ),
			'email'         => __( 'E-mail', 'leaky-paywall' ),
			'name'          => __( 'Name', 'leaky-paywall' ),
			'level_id'      => __( 'Level ID', 'leaky-paywall' ),
			'susbcriber_id' => __( 'Subscriber ID', 'leaky-paywall' ),
			'price'         => __( 'Price', 'leaky-paywall' ),
			'plan'          => __( 'Plan', 'leaky-paywall' ),
			'created'       => __( 'Created', 'leaky-paywall' ),
			'expires'       => __( 'Expires', 'leaky-paywall' ),
			'has_access'    => __( 'Has Access', 'leaky-paywall' ),
			'gateway'       => __( 'Gateway', 'leaky-paywall' ),
			'status'        => __( 'Payment Status', 'leaky-paywall' ),
			'notes'         => __( 'Notes', 'leaky-paywall' ),
		);
		$users_columns = apply_filters( 'leaky_paywall_subscribers_columns', $users_columns );

		return $users_columns;
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'wp_user_login' => array( 'wp_user_login', false ),
			'email'         => array( 'email', false ),
			'level_id'      => array( 'level_id', false ),
			'susbcriber_id' => array( 'susbcriber_id', false ),
			'price'         => array( 'price', false ),
			'plan'          => array( 'plan', false ),
			'gateway'       => array( 'payment_gateway', false ),
			'status'        => array( 'payment_status', false ),
		);
		$sortable_columns = apply_filters( 'leaky_paywall_subscribers_sortable_columns', $sortable_columns );

		return $sortable_columns;
	}

	/**
	 * Select for user type
	 */
	public function user_views() {
		$user_type = ! empty( $_GET['user-type'] ) ? sanitize_text_field( wp_unslash( $_GET['user-type'] ) ) : 'lpsubs';

		echo '<div class="alignleft actions">';
		echo '<label for="user-type-selector" class="screen-reader-text">' . esc_attr__( 'Select User Type' ) . '</label>';
		echo '<select name="user-type" id="user-type-selector">';
		echo '<option value="lpsubs" ' . selected( 'lpsubs', $user_type, false ) . '>' . esc_attr__( 'Leaky Paywall Subscribers' ) . '</option>';
		echo '<option value="wpusers" ' . selected( 'wpusers', $user_type, false ) . '>' . esc_attr__( 'All WordPress Users' ) . '</option>';
		echo '</select>';

		submit_button( __( 'Apply' ), 'primary', false, false );
		echo '</div>';
	}

	/**
	 * Display subscriber table navigation
	 *
	 * @param string $which The location of the nav.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		if ( isset( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
			return;
		}

		$levels = leaky_paywall_get_levels();
		$lev    = isset( $_GET['filter-level'] ) ? sanitize_text_field( wp_unslash( $_GET['filter-level'] ) ) : 'all';
		$stat   = isset( $_GET['filter-status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter-status'] ) ) : 'all';
		?>

		<div class="alignleft actions">
			<label for="filter-by-level" class="screen-reader-text">Filter by level</label>
			<select name="filter-level" id="filter-by-level">
				<option value="all" <?php selected( $lev, 'all' ); ?>>All Levels</option>
				<?php
				foreach ( $levels as $key => $level ) {
					echo '<option ' . selected( $key, $lev, false ) . ' value="' . esc_attr( $key ) . '">' . esc_attr( $level['label'] ) . '</option>';
				}
				?>
			</select>

			<?php
			$status_filter_args = apply_filters(
				'leaky_paywall_status_filter_args',
				array(
					'all'         => 'All Statuses',
					'active'      => 'Active',
					'deactivated' => 'Deactivated',
					'canceled'    => 'Canceled',
				)
			);
			?>

			<label for="filter-by-status" class="screen-reader-text">Filter by status</label>
			<select name="filter-status" id="filter-by-status">

				<?php
				foreach ( $status_filter_args as $key => $value ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stat, $key ); ?>><?php echo esc_attr( $value ); ?></option>
					<?php
				}
				?>

			</select>

			<input name="filter_action" id="subscriber-query-submit" class="button" value="Filter" type="submit">
		</div>

		<?php
	}

	/**
	 * Display rows in subscriber table
	 */
	public function display_rows() {
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		$alt = '';

		foreach ( $this->items as $user ) {

			if ( ! $user->user_email ) {
				continue;
			}

			$user = get_user_by( 'email', $user->user_email );

			$alt = ( 'alternate' === $alt ) ? '' : 'alternate';

			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );

			if ( ! empty( $_GET['user-type'] ) && 'wpusers' !== $_GET['user-type'] ) {
				if ( empty( $payment_gateway ) ) {
					$payment_gateway = 'manual';
				}
			}

			if ( is_multisite_premium() ) {
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
			} else {
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', true );
			}

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
							<strong><?php echo esc_attr( $user->user_login ); ?></strong>

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
							$edit_link    = esc_url( add_query_arg( 'edit', rawurlencode( $user->user_email ) ) );
							$edit_wp_link = admin_url() . 'user-edit.php?user_id=' . $user->ID;
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							?>
							<strong><?php echo esc_attr( $user->user_email ); ?></strong>
							<br><a href="<?php echo esc_url( $edit_link ); ?>" class="edit">Edit LP Sub</a> | <a href="<?php echo esc_url( $edit_wp_link ); ?>">Edit WP user</a>
							</td>
							<?php
							break;

						case 'name':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							?>
							<?php echo esc_attr( $user->first_name ); ?> <?php echo esc_attr( $user->last_name ); ?>
							</td>
							<?php
							break;

						case 'level_id':

							$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
							$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
							if ( false === $level_id || empty( $settings['levels'][ $level_id ]['label'] ) ) {
								$level_name = __( 'Undefined', 'leaky-paywall' );
							} else {
								$level_name = stripcslashes( $settings['levels'][ $level_id ]['label'] );
							}
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $level_name );
							echo '</td>';
							break;

						case 'susbcriber_id':
							if ( is_multisite_premium() ) {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true ) );
								echo '</td>';
							} else {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id', true ) );
								echo '</td>';
							}

							break;

						case 'price':
							if ( is_multisite_premium() ) {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo number_format( (float) get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true ), '2' );
								echo '</td>';
							} else {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo number_format( (float) get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price', true ), '2' );
								echo '</td>';
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
							} elseif ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
								/* Translators: %s: type of time */
								$plan = sprintf( __( 'Recurring every %s', 'leaky-paywall' ), str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $plan ) );
							}

							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $plan );
							echo '</td>';
							break;

						case 'created':
							if ( is_multisite_premium() ) {
								$created = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_created' . $site, true );
							} else {
								$created = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_created', true );
							}

							$created = apply_filters( 'do_leaky_paywall_profile_shortcode_created_column', $created, $user, $mode, $site, $level_id );

							$date_format = 'F j, Y';

							if ( is_numeric( $created ) && (int)$created == $created ) {
								// its a timestamp
								$formatted_created = date( $date_format, $created );
							} else {
								// its a date format
								$formatted_created     = mysql2date( $date_format, $created );
							}
							
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $formatted_created );
							echo '</td>';
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

								$date_format = 'F j, Y';
								$expires     = mysql2date( $date_format, $expires );
							}

							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( $expires );
							echo '</td>';
							break;

						case 'has_access':
							$has_access = leaky_paywall_user_has_access( $user );

							if ( $has_access ) {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr__( 'Yes', 'leaky-paywall' );
								echo '</td>';
							} else {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr__( 'No', 'leaky-paywall' );
								echo '</td>';
							}
							break;

						case 'gateway':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) );
							echo '</td>';
							break;

						case 'status':
							if ( is_multisite_premium() ) {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true ) );
								echo '</td>';
							} else {
								echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
								echo esc_attr( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status', true ) );
								echo '</td>';
							}
							break;
						case 'notes':
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo esc_attr( get_user_meta( $user->ID, '_leaky_paywall_subscriber_notes', true ) );
							echo '</td>';
							break;

						default:
							echo '<td class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '">';
							echo apply_filters( 'manage_leaky_paywall_subscribers_custom_column', '&nbsp;', $column_name, $user->ID );
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
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_attr( $text ); ?>:</label>
			<input type="checkbox" name="custom_field_search"> Search custom fields<br>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo esc_attr( $search_query ); ?>" />

			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}
}
