<?php
/**
 * Subscriber list table with Screen Options, sortable columns, and expanded search.
 *
 * @package Leaky Paywall
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LP_Subscriber_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'subscriber',
			'plural'   => 'subscribers',
			'ajax'     => false,
			'screen'   => 'leaky-paywall_page_leaky-paywall-subscribers',
		) );
	}

	/**
	 * Define all columns. Extensions add via the existing filter.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'email'         => __( 'E-mail', 'leaky-paywall' ),
			'name'          => __( 'Name', 'leaky-paywall' ),
			'level_id'      => __( 'Level', 'leaky-paywall' ),
			'status'        => __( 'Status', 'leaky-paywall' ),
			'created'       => __( 'Created', 'leaky-paywall' ),
			'expires'       => __( 'Expires', 'leaky-paywall' ),
			'susbcriber_id' => __( 'Subscriber ID', 'leaky-paywall' ),
			'plan'          => __( 'Plan', 'leaky-paywall' ),
			'gateway'       => __( 'Gateway', 'leaky-paywall' ),
			'has_access'    => __( 'Has Access', 'leaky-paywall' ),
			'notes'         => __( 'Notes', 'leaky-paywall' ),
			'price'         => __( 'Price', 'leaky-paywall' ),
		);

		return apply_filters( 'leaky_paywall_subscribers_columns', $columns );
	}

	/**
	 * Columns hidden by default. Users toggle via Screen Options.
	 *
	 * @return array
	 */
	public function get_default_hidden_columns() {
		return array( 'susbcriber_id', 'plan', 'gateway', 'has_access', 'notes', 'price' );
	}

	/**
	 * Read hidden columns from user meta (Screen Options).
	 *
	 * @return array
	 */
	protected function get_hidden_columns() {
		$screen = get_current_screen();
		if ( $screen ) {
			$hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );
			if ( is_array( $hidden ) ) {
				return $hidden;
			}
		}
		return $this->get_default_hidden_columns();
	}

	/**
	 * Sortable columns — only native wp_users fields to avoid meta joins.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable = array(
			'email'   => array( 'user_email', false ),
			'name'    => array( 'display_name', false ),
			'created' => array( 'user_registered', false ),
		);

		return apply_filters( 'leaky_paywall_subscribers_sortable_columns', $sortable );
	}

	/**
	 * Prepare items for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$usersearch     = isset( $_REQUEST['s'] ) ? '*' . sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) . '*' : '';
		$users_per_page = $this->get_items_per_page( 'lp_subscribers_per_page', 20 );
		$paged          = $this->get_pagenum();

		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged - 1 ) * $users_per_page,
			'search' => $usersearch,
		);

		// Sorting.
		$valid_orderby = array( 'user_email', 'display_name', 'user_registered', 'ID' );
		$orderby       = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'ID';
		$args['orderby'] = in_array( $orderby, $valid_orderby, true ) ? $orderby : 'ID';
		$args['order']   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';

		// Base meta query: only show LP subscribers.
		$args['meta_query'] = array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'compare' => 'EXISTS',
			),
		);

		// Custom field search — search across multiple meta keys.
		if ( isset( $_GET['custom_field_search'] ) && 'on' === $_GET['custom_field_search'] && ! empty( $_GET['s'] ) ) {
			$search_term = sanitize_text_field( wp_unslash( $_GET['s'] ) );

			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_leaky_paywall_subscriber_notes',
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site,
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_issuem_leaky_paywall_' . $mode . '_description' . $site,
					'value'   => $search_term,
					'compare' => 'LIKE',
				),
			);

			unset( $args['search'] );
		}

		// Level filter.
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

		// Status filter.
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

		// Gateway filter.
		if ( ! empty( $_GET['filter-gateway'] ) && 'all' !== $_GET['filter-gateway'] ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site,
				'value'   => sanitize_text_field( wp_unslash( $_GET['filter-gateway'] ) ),
				'compare' => '=',
			);
		}

		// Created date range.
		if ( ! empty( $_GET['created-from'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_created' . $site,
				'value'   => sanitize_text_field( wp_unslash( $_GET['created-from'] ) ) . ' 00:00:00',
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( ! empty( $_GET['created-to'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_created' . $site,
				'value'   => sanitize_text_field( wp_unslash( $_GET['created-to'] ) ) . ' 23:59:59',
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		// Expires date range.
		if ( ! empty( $_GET['expires-from'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_expires' . $site,
				'value'   => sanitize_text_field( wp_unslash( $_GET['expires-from'] ) ) . ' 00:00:00',
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}
		if ( ! empty( $_GET['expires-to'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_expires' . $site,
				'value'   => sanitize_text_field( wp_unslash( $_GET['expires-to'] ) ) . ' 23:59:59',
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		// Allow extensions to add their own meta_query clauses.
		$args['meta_query'] = apply_filters( 'leaky_paywall_subscriber_table_query', $args['meta_query'], $mode, $site );

		// "All WordPress Users" view — remove LP meta filter.
		if ( ! empty( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
			unset( $args['meta_query'] );
		}

		$wp_user_search = new WP_User_Query( $args );
		$results        = $wp_user_search->get_results();

		// Deduplicate — meta joins can return the same user multiple times.
		$seen        = array();
		$this->items = array();
		foreach ( $results as $user ) {
			$id = is_object( $user ) ? $user->ID : $user;
			if ( ! isset( $seen[ $id ] ) ) {
				$seen[ $id ]   = true;
				$this->items[] = $user;
			}
		}

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page'    => $users_per_page,
		) );
	}

	/**
	 * Render a column value.
	 *
	 * @param WP_User $user        User object.
	 * @param string  $column_name Column slug.
	 * @return string
	 */
	public function column_default( $user, $column_name ) {
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();
		$settings = get_leaky_paywall_settings();

		switch ( $column_name ) {

			case 'name':
				return esc_html( trim( $user->first_name . ' ' . $user->last_name ) );

			case 'level_id':
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
				$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );

				if ( false === $level_id || empty( $settings['levels'][ $level_id ]['label'] ) ) {
					$level_name = __( 'Undefined', 'leaky-paywall' );
				} else {
					$level_name = stripslashes( $settings['levels'][ $level_id ]['label'] );
				}

				return esc_html( $level_name ) . '<br><span style="color: #999;">ID: ' . esc_html( $level_id ) . '</span>';

			case 'status':
				$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
				return '<span class="lp-status-badge lp-status-badge--' . esc_attr( $status ) . '">' . esc_html( lp_get_status_label( $status ) ) . '</span>';

			case 'created':
				$created = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_created' . $site, true );
				if ( empty( $created ) ) {
					$created = $user->user_registered;
				}
				$created     = apply_filters( 'do_leaky_paywall_profile_shortcode_created_column', $created, $user, $mode, $site, '' );
				$date_format = 'F j, Y';
				if ( is_numeric( $created ) && (int) $created == $created ) {
					return esc_html( gmdate( $date_format, $created ) );
				}
				return esc_html( mysql2date( $date_format, $created ) );

			case 'expires':
				$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
				$expires = apply_filters( 'do_leaky_paywall_profile_shortcode_expiration_column', $expires, $user, $mode, $site, '' );
				if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires || 'Never' === $expires ) {
					return esc_html__( 'Never', 'leaky-paywall' );
				}
				return esc_html( date_i18n( 'F j, Y', strtotime( $expires ) ) );

			case 'susbcriber_id':
				$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
				if ( ! empty( $subscriber_id ) && strpos( $subscriber_id, 'cus_' ) !== false ) {
					$stripe_url = 'test' === $mode ? 'https://dashboard.stripe.com/test/customers/' : 'https://dashboard.stripe.com/customers/';
					return '<a target="_blank" href="' . esc_url( $stripe_url . $subscriber_id ) . '">' . esc_html( $subscriber_id ) . '</a>';
				}
				return esc_html( $subscriber_id );

			case 'plan':
				$plan            = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
				if ( empty( $plan ) ) {
					return esc_html__( 'Non-Recurring', 'leaky-paywall' );
				}
				if ( 'paypal_standard' === $payment_gateway || 'paypal-standard' === $payment_gateway ) {
					/* Translators: %s: interval type */
					return esc_html( sprintf( __( 'Recurring every %s', 'leaky-paywall' ), str_replace( array( 'D', 'W', 'M', 'Y' ), array( 'Days', 'Weeks', 'Months', 'Years' ), $plan ) ) );
				}
				return esc_html( $plan );

			case 'gateway':
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
				return esc_html( leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) );

			case 'has_access':
				return leaky_paywall_user_has_access( $user ) ? esc_html__( 'Yes', 'leaky-paywall' ) : esc_html__( 'No', 'leaky-paywall' );

			case 'notes':
				return esc_html( get_user_meta( $user->ID, '_leaky_paywall_subscriber_notes', true ) );

			case 'price':
				$price = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true );
				return esc_html( $price );

			default:
				return esc_html( apply_filters( 'manage_leaky_paywall_subscribers_custom_column', '', $column_name, $user->ID ) );
		}
	}

	/**
	 * Email column with row actions.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	public function column_email( $user ) {
		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

		$edit_link = esc_url( add_query_arg( array(
			'action' => 'show',
			'id'     => $user->ID,
		) ) );

		$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );

		$output = '<strong><a href="' . $edit_link . '">' . esc_html( $user->user_email ) . '</a></strong>';

		// Row actions.
		$actions         = array();
		$actions['edit'] = '<a href="' . $edit_link . '">' . __( 'Edit', 'leaky-paywall' ) . '</a>';

		if ( ! empty( $subscriber_id ) && strpos( $subscriber_id, 'cus_' ) !== false ) {
			$stripe_url        = 'test' === $mode ? 'https://dashboard.stripe.com/test/customers/' : 'https://dashboard.stripe.com/customers/';
			$actions['stripe'] = '<a href="' . esc_url( $stripe_url . $subscriber_id ) . '" target="_blank">' . __( 'View in Stripe', 'leaky-paywall' ) . '</a>';
		}

		if ( method_exists( 'user_switching', 'maybe_switch_url' ) && is_object( $user ) ) {
			$switch_url = user_switching::maybe_switch_url( $user );
			if ( $switch_url ) {
				$actions['switch_to'] = '<a href="' . esc_url( $switch_url ) . '">' . __( 'Switch To', 'leaky-paywall' ) . '</a>';
			}
		}

		$output .= $this->row_actions( $actions );

		return $output;
	}

	/**
	 * User type selector.
	 */
	public function user_views() {
		$user_type = ! empty( $_GET['user-type'] ) ? sanitize_text_field( wp_unslash( $_GET['user-type'] ) ) : 'lpsubs';

		echo '<div class="alignleft actions">';
		echo '<label for="user-type-selector" class="screen-reader-text">' . esc_html__( 'Select User Type' ) . '</label>';
		echo '<select name="user-type" id="user-type-selector">';
		echo '<option value="lpsubs" ' . selected( 'lpsubs', $user_type, false ) . '>' . esc_html__( 'Leaky Paywall Subscribers' ) . '</option>';
		echo '<option value="wpusers" ' . selected( 'wpusers', $user_type, false ) . '>' . esc_html__( 'All WordPress Users' ) . '</option>';
		echo '</select>';
		submit_button( __( 'Apply' ), 'secondary', false, false );
		echo '</div>';
	}

	/**
	 * Filter dropdowns above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		if ( isset( $_GET['user-type'] ) && 'lpsubs' !== $_GET['user-type'] ) {
			return;
		}

		$mode   = leaky_paywall_get_current_mode();
		$site   = leaky_paywall_get_current_site();
		$levels = leaky_paywall_get_levels();
		$lev    = isset( $_GET['filter-level'] ) ? sanitize_text_field( wp_unslash( $_GET['filter-level'] ) ) : 'all';
		$stat   = isset( $_GET['filter-status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter-status'] ) ) : 'all';
		$gw     = isset( $_GET['filter-gateway'] ) ? sanitize_text_field( wp_unslash( $_GET['filter-gateway'] ) ) : 'all';
		?>
		<div class="alignleft actions">
			<label for="filter-by-level" class="screen-reader-text"><?php esc_html_e( 'Filter by level', 'leaky-paywall' ); ?></label>
			<select name="filter-level" id="filter-by-level">
				<option value="all" <?php selected( $lev, 'all' ); ?>><?php esc_html_e( 'All Levels', 'leaky-paywall' ); ?></option>
				<?php
				foreach ( $levels as $key => $level ) {
					echo '<option ' . selected( $key, $lev, false ) . ' value="' . esc_attr( $key ) . '">' . esc_html( stripslashes( $level['label'] ) ) . '</option>';
				}
				?>
			</select>

			<?php
			$status_filter_args = apply_filters(
				'leaky_paywall_status_filter_args',
				array(
					'all'            => __( 'All Statuses', 'leaky-paywall' ),
					'active'         => __( 'Active', 'leaky-paywall' ),
					'pending_cancel' => __( 'Pending Cancel', 'leaky-paywall' ),
					'trial'          => __( 'Trial', 'leaky-paywall' ),
					'past_due'       => __( 'Past Due', 'leaky-paywall' ),
					'canceled'       => __( 'Canceled', 'leaky-paywall' ),
					'expired'        => __( 'Expired', 'leaky-paywall' ),
					'deactivated'    => __( 'Deactivated', 'leaky-paywall' ),
				)
			);
			?>

			<label for="filter-by-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'leaky-paywall' ); ?></label>
			<select name="filter-status" id="filter-by-status">
				<?php foreach ( $status_filter_args as $key => $value ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $stat, $key ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php
			$gateway_slugs = array(
				'all'               => __( 'All Gateways', 'leaky-paywall' ),
				'stripe'            => __( 'Stripe', 'leaky-paywall' ),
				'stripe_checkout'   => __( 'Stripe Checkout', 'leaky-paywall' ),
				'paypal_standard'   => __( 'PayPal', 'leaky-paywall' ),
				'manual'            => __( 'Manual', 'leaky-paywall' ),
				'free_registration' => __( 'Free Registration', 'leaky-paywall' ),
			);
			$gateway_slugs = apply_filters( 'leaky_paywall_gateway_filter_args', $gateway_slugs );
			?>

			<label for="filter-by-gateway" class="screen-reader-text"><?php esc_html_e( 'Filter by gateway', 'leaky-paywall' ); ?></label>
			<select name="filter-gateway" id="filter-by-gateway">
				<?php foreach ( $gateway_slugs as $key => $value ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $gw, $key ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php
			/**
			 * Allow extensions to render additional filter dropdowns.
			 *
			 * @since 5.1.0
			 *
			 * @param string $mode Current LP mode (test/live).
			 * @param string $site Current LP site suffix.
			 */
			do_action( 'leaky_paywall_subscriber_table_filters', $mode, $site );
			?>

			<?php
			$has_date_filter = ! empty( $_GET['created-from'] ) || ! empty( $_GET['created-to'] ) || ! empty( $_GET['expires-from'] ) || ! empty( $_GET['expires-to'] );
			?>
			<a href="#" id="lp-toggle-date-filters" style="text-decoration: none; line-height: 28px; padding: 0 4px;"><?php echo $has_date_filter ? esc_html__( 'Date Filters', 'leaky-paywall' ) . ' &#9650;' : esc_html__( 'Date Filters', 'leaky-paywall' ) . ' &#9660;'; ?></a>

			<input name="filter_action" id="subscriber-query-submit" class="button" value="<?php esc_attr_e( 'Filter', 'leaky-paywall' ); ?>" type="submit">
		</div>

		<?php
		$created_from = isset( $_GET['created-from'] ) ? sanitize_text_field( wp_unslash( $_GET['created-from'] ) ) : '';
		$created_to   = isset( $_GET['created-to'] ) ? sanitize_text_field( wp_unslash( $_GET['created-to'] ) ) : '';
		$expires_from = isset( $_GET['expires-from'] ) ? sanitize_text_field( wp_unslash( $_GET['expires-from'] ) ) : '';
		$expires_to   = isset( $_GET['expires-to'] ) ? sanitize_text_field( wp_unslash( $_GET['expires-to'] ) ) : '';
		?>

		<div id="lp-date-filters" class="alignleft actions" style="clear: left; gap: 16px; padding-top: 8px; <?php echo $has_date_filter ? 'display: flex;' : 'display: none;'; ?>">
			<fieldset style="display: flex; align-items: center; gap: 4px; margin: 0; padding: 0; border: none;">
				<legend style="font-size: 12px; font-weight: 600; color: #50575e; padding: 0; margin-bottom: 2px; float: left; width: 100%;"><?php esc_html_e( 'Created', 'leaky-paywall' ); ?></legend>
				<input type="date" name="created-from" value="<?php echo esc_attr( $created_from ); ?>" style="max-width: 140px;">
				<span style="color: #999;">&ndash;</span>
				<input type="date" name="created-to" value="<?php echo esc_attr( $created_to ); ?>" style="max-width: 140px;">
			</fieldset>

			<fieldset style="display: flex; align-items: center; gap: 4px; margin: 0; padding: 0; border: none;">
				<legend style="font-size: 12px; font-weight: 600; color: #50575e; padding: 0; margin-bottom: 2px; float: left; width: 100%;"><?php esc_html_e( 'Expires', 'leaky-paywall' ); ?></legend>
				<input type="date" name="expires-from" value="<?php echo esc_attr( $expires_from ); ?>" style="max-width: 140px;">
				<span style="color: #999;">&ndash;</span>
				<input type="date" name="expires-to" value="<?php echo esc_attr( $expires_to ); ?>" style="max-width: 140px;">
			</fieldset>
		</div>

		<script>
		document.getElementById('lp-toggle-date-filters').addEventListener('click', function(e) {
			e.preventDefault();
			var panel = document.getElementById('lp-date-filters');
			var visible = panel.style.display !== 'none';
			panel.style.display = visible ? 'none' : 'flex';
			this.innerHTML = visible
				? '<?php echo esc_js( __( 'Date Filters', 'leaky-paywall' ) ); ?> \u25BC'
				: '<?php echo esc_js( __( 'Date Filters', 'leaky-paywall' ) ); ?> \u25B2';
		});
		</script>
		<?php
	}

	/**
	 * Search box with "Search all fields" option.
	 *
	 * @param string $text     Submit button label.
	 * @param string $input_id Input ID prefix.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id     = $input_id . '-search-input';
		$search_query = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$checked      = isset( $_GET['custom_field_search'] ) && 'on' === $_GET['custom_field_search'] ? 'checked' : '';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<label><input type="checkbox" name="custom_field_search" value="on" <?php echo $checked; ?>> <?php esc_html_e( 'Search all fields', 'leaky-paywall' ); ?></label><br>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No Leaky Paywall subscribers found.', 'leaky-paywall' );
	}

	/**
	 * Bulk actions placeholder for future use.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array();
	}
}
