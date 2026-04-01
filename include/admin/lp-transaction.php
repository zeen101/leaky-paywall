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

		add_action('in_admin_header', array($this, 'render_list_header'));

		add_action( 'admin_enqueue_scripts', array( $this, 'dequeue_yoast_ai_generator' ), 999 );
	}

	/**
	 * Dequeue Yoast's AI generator script on the transaction edit screen.
	 *
	 * Yoast's AI_Generator_Integration enqueues ai-generator.js on any post.php
	 * screen without checking post type, then crashes trying to read postType from
	 * localized data that doesn't include non-public post types like lp_transaction.
	 */
	public function dequeue_yoast_ai_generator() {
		$screen = get_current_screen();
		if ( $screen && 'lp_transaction' === $screen->post_type ) {
			wp_dequeue_script( 'yoast-seo-ai-generator' );
		}
	}

	/**
	 * Render the LP branded header on the transactions list page.
	 */
	public function render_list_header()
	{
		$screen = get_current_screen();

		if ( ! $screen || 'edit-lp_transaction' !== $screen->id ) {
			return;
		}

		?>
		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e( 'Transactions', 'leaky-paywall' ); ?></h1>
				</span>
			</div>
		</div>
		<?php
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
	 * Create meta boxes for transaction post type.
	 */
	public function meta_box_create()
	{
		add_meta_box( 'transaction_details', __( 'Transaction Details', 'leaky-paywall' ), array( $this, 'transaction_details_func' ), 'lp_transaction', 'normal', 'high' );
		add_meta_box( 'transaction_sidebar', __( 'Transaction Info', 'leaky-paywall' ), array( $this, 'transaction_sidebar_func' ), 'lp_transaction', 'side', 'high' );
	}

	/**
	 * Display the transaction details (main meta box).
	 *
	 * @param WP_Post $post The post object.
	 */
	public function transaction_details_func( $post )
	{
		$level_id     = get_post_meta( $post->ID, '_level_id', true );
		$level        = get_leaky_paywall_subscription_level( $level_id );
		$first_name   = get_post_meta( $post->ID, '_first_name', true );
		$last_name    = get_post_meta( $post->ID, '_last_name', true );
		$email        = get_post_meta( $post->ID, '_email', true );
		$price        = get_post_meta( $post->ID, '_price', true );
		$is_recurring = get_post_meta( $post->ID, '_is_recurring', true );
		$status       = get_post_meta( $post->ID, '_status', true );
		$txn_status   = get_post_meta( $post->ID, '_transaction_status', true );
		$nag_location = get_post_meta( $post->ID, '_nag_location_id', true );
		$refund_for   = get_post_meta( $post->ID, '_refund_for', true );

		$tax_amount   = get_post_meta( $post->ID, '_tax_amount', true );
		$subtotal     = get_post_meta( $post->ID, '_subtotal', true );

		$is_refund    = 'refund' === $status;
		$display_status = $txn_status ? ucfirst( $txn_status ) : __( 'Complete', 'leaky-paywall' );

		// Format amount.
		if ( is_numeric( $price ) && $price > 0 ) {
			$formatted_price = leaky_paywall_format_display_price( $price );
		} elseif ( is_numeric( $price ) && $price < 0 ) {
			$formatted_price = '-' . leaky_paywall_format_display_price( abs( $price ) );
		} else {
			$formatted_price = leaky_paywall_format_display_price( 0 );
		}

		// Status badge class.
		if ( $is_refund ) {
			$badge_class = 'lp-status-badge--refund';
		} elseif ( in_array( strtolower( $display_status ), array( 'complete', 'completed' ), true ) ) {
			$badge_class = 'lp-status-badge--complete';
		} else {
			$badge_class = 'lp-status-badge--' . sanitize_html_class( strtolower( $display_status ) );
		}

		wp_nonce_field( 'lp_transaction_meta_box_nonce', 'meta_box_nonce' );
		?>

		<!-- Header: status + payment type -->
		<div class="lp-transaction-header">
			<span class="lp-status-badge <?php echo esc_attr( $badge_class ); ?>">
				<?php echo $is_refund ? esc_html__( 'Refund', 'leaky-paywall' ) : esc_html( $display_status ); ?>
			</span>
			<?php if ( ! $is_refund ) :
				if ( $is_recurring ) : ?>
				<span class="lp-status-badge lp-status-badge--recurring"><?php esc_html_e( 'Subscription Renewal', 'leaky-paywall' ); ?></span>
				<?php elseif ( isset( $level['recurring'] ) && $level['recurring'] ) : ?>
				<span class="lp-status-badge lp-status-badge--recurring"><?php esc_html_e( 'Initial Subscription Payment', 'leaky-paywall' ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<!-- Amount -->
		<div class="lp-transaction-price-block">
			<?php if ( ! empty( $tax_amount ) && $tax_amount > 0 ) :
				$total = floatval( $subtotal ) + floatval( $tax_amount );
			?>
			<span class="lp-transaction-amount <?php echo $is_refund ? 'lp-transaction-amount--refund' : ''; ?>">
				<?php echo esc_html( leaky_paywall_format_display_price( $total ) ); ?>
			</span>
			<div class="lp-transaction-tax-breakdown">
				<span class="lp-transaction-tax-line"><?php esc_html_e( 'Subtotal:', 'leaky-paywall' ); ?> <?php echo esc_html( leaky_paywall_format_display_price( $subtotal ) ); ?></span>
				<span class="lp-transaction-tax-line"><?php esc_html_e( 'Tax:', 'leaky-paywall' ); ?> <?php echo esc_html( leaky_paywall_format_display_price( $tax_amount ) ); ?></span>
			</div>
			<?php else : ?>
			<span class="lp-transaction-amount <?php echo $is_refund ? 'lp-transaction-amount--refund' : ''; ?>">
				<?php echo esc_html( $formatted_price ); ?>
			</span>
			<?php endif; ?>
		</div>

		<?php if ( $refund_for ) :
			$original_email = get_post_meta( $refund_for, '_email', true );
		?>
			<div class="lp-transaction-refund-notice">
				<?php esc_html_e( 'Refund for', 'leaky-paywall' ); ?>
				<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $refund_for ) . '&action=edit' ) ); ?>">
					<?php
					printf(
						/* translators: %d: transaction ID */
						esc_html__( 'Transaction #%d', 'leaky-paywall' ),
						absint( $refund_for )
					);
					if ( $original_email ) {
						echo ' (' . esc_html( $original_email ) . ')';
					}
					?>
				</a>
			</div>
		<?php endif; ?>

		<!-- Customer section -->
		<div class="lp-transaction-section">
			<h3><?php esc_html_e( 'Customer', 'leaky-paywall' ); ?></h3>
			<div class="lp-transaction-fields">
				<?php
				$full_name = trim( esc_html( $first_name ) . ' ' . esc_html( $last_name ) );
				if ( $full_name ) :
				?>
					<span class="lp-field-label"><?php esc_html_e( 'Name', 'leaky-paywall' ); ?></span>
					<span class="lp-field-value"><?php echo $full_name; ?></span>
				<?php endif; ?>

				<?php if ( $email ) : ?>
					<span class="lp-field-label"><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></span>
					<span class="lp-field-value">
						<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<!-- Subscription section -->
		<?php if ( $level || $nag_location ) : ?>
			<div class="lp-transaction-section">
				<h3><?php esc_html_e( 'Subscription', 'leaky-paywall' ); ?></h3>
				<div class="lp-transaction-fields">
					<?php if ( $level && isset( $level['label'] ) ) : ?>
						<span class="lp-field-label"><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></span>
						<span class="lp-field-value">
							<?php echo esc_html( $level['label'] ); ?>
							<span style="color: #999;">(ID: <?php echo absint( $level_id ); ?>)</span>
						</span>
					<?php endif; ?>

					<?php if ( $nag_location ) : ?>
						<span class="lp-field-label"><?php esc_html_e( 'Nag Location', 'leaky-paywall' ); ?></span>
						<span class="lp-field-value">
							<a href="<?php echo esc_url( get_the_permalink( $nag_location ) ); ?>" target="_blank">
								<?php echo esc_html( get_the_title( $nag_location ) ); ?>
							</a>
						</span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- PayPal response (collapsible) -->
		<?php
		$paypal_request = get_post_meta( $post->ID, '_paypal_request', true );
		if ( $paypal_request ) :
			$paypal_data = json_decode( $paypal_request, true );
		?>
			<details class="lp-transaction-paypal-details">
				<summary><?php esc_html_e( 'PayPal Response', 'leaky-paywall' ); ?></summary>
				<pre><?php echo esc_html( wp_json_encode( $paypal_data, JSON_PRETTY_PRINT ) ); ?></pre>
			</details>
		<?php endif; ?>

		<!-- Extension hook area -->
		<div class="lp-transaction-extensions">
			<?php do_action( 'leaky_paywall_after_transaction_meta_box', $post ); ?>
		</div>

		<?php
	}

	/**
	 * Display the transaction sidebar meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function transaction_sidebar_func( $post )
	{
		$gateway        = get_post_meta( $post->ID, '_gateway', true );
		$gateway_txn_id = get_post_meta( $post->ID, '_gateway_txn_id', true );
		$email          = get_post_meta( $post->ID, '_email', true );
		$is_recurring   = get_post_meta( $post->ID, '_is_recurring', true );
		$status         = get_post_meta( $post->ID, '_status', true );
		$mode           = leaky_paywall_get_current_mode();
		$is_refund      = 'refund' === $status;

		// Build gateway display name.
		$gateway_names = array(
			'stripe'          => __( 'Stripe', 'leaky-paywall' ),
			'stripe_checkout' => __( 'Stripe Checkout', 'leaky-paywall' ),
			'paypal_standard' => __( 'PayPal Standard', 'leaky-paywall' ),
			'manual'          => __( 'Manual', 'leaky-paywall' ),
			'free_registration' => __( 'Free Registration', 'leaky-paywall' ),
		);
		$gateway_display = isset( $gateway_names[ $gateway ] ) ? $gateway_names[ $gateway ] : ucwords( str_replace( '_', ' ', $gateway ) );

		// Build gateway transaction URL.
		$gateway_url = '';
		if ( $gateway_txn_id ) {
			if ( in_array( $gateway, array( 'stripe', 'stripe_checkout' ), true ) ) {
				$stripe_base = 'test' === $mode ? 'https://dashboard.stripe.com/test/payments/' : 'https://dashboard.stripe.com/payments/';
				$gateway_url = $stripe_base . $gateway_txn_id;
			}

			$gateway_url = apply_filters( 'leaky_paywall_transaction_gateway_url', $gateway_url, $gateway, $gateway_txn_id );
		}

		// Payment type.
		$level_id     = get_post_meta( $post->ID, '_level_id', true );
		$level        = get_leaky_paywall_subscription_level( $level_id );

		if ( $is_refund ) {
			$payment_type = __( 'Refund', 'leaky-paywall' );
		} elseif ( $is_recurring ) {
			$payment_type = __( 'Subscription Renewal', 'leaky-paywall' );
		} elseif ( isset( $level['recurring'] ) && $level['recurring'] ) {
			$payment_type = __( 'Initial Subscription Payment', 'leaky-paywall' );
		} else {
			$payment_type = __( 'One Time', 'leaky-paywall' );
		}

		// Stripe Customer ID — stored on transaction, fallback to user meta for older transactions.
		$subscriber_id = get_post_meta( $post->ID, '_subscriber_id', true );
		$site          = leaky_paywall_get_current_site();

		if ( ! $subscriber_id && $email && in_array( $gateway, array( 'stripe', 'stripe_checkout' ), true ) ) {
			$txn_user = get_user_by( 'email', $email );
			if ( $txn_user ) {
				$subscriber_id = get_user_meta( $txn_user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			}
		}

		$stripe_customer_url = '';
		if ( $subscriber_id && in_array( $gateway, array( 'stripe', 'stripe_checkout' ), true ) ) {
			$stripe_base         = 'test' === $mode ? 'https://dashboard.stripe.com/test/customers/' : 'https://dashboard.stripe.com/customers/';
			$stripe_customer_url = $stripe_base . $subscriber_id;
		}

		// Find subscriber.
		$subscriber_url = '';
		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$subscriber_url = admin_url( 'admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user->ID );
			}
		}
		?>

		<div class="lp-transaction-sidebar">
			<div class="lp-sidebar-field">
				<span class="lp-sidebar-label"><?php esc_html_e( 'Date', 'leaky-paywall' ); ?></span>
				<span class="lp-sidebar-value"><?php echo esc_html( get_the_date( 'M d, Y g:i A', $post ) ); ?></span>
			</div>

			<?php if ( $gateway ) : ?>
				<div class="lp-sidebar-field">
					<span class="lp-sidebar-label"><?php esc_html_e( 'Gateway', 'leaky-paywall' ); ?></span>
					<span class="lp-sidebar-value"><?php echo esc_html( $gateway_display ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $gateway_txn_id ) : ?>
				<div class="lp-sidebar-field">
					<span class="lp-sidebar-label"><?php esc_html_e( 'Transaction ID', 'leaky-paywall' ); ?></span>
					<span class="lp-sidebar-value">
						<?php if ( $gateway_url ) : ?>
							<a href="<?php echo esc_url( $gateway_url ); ?>" target="_blank">
								<?php echo esc_html( substr( $gateway_txn_id, 0, 20 ) ); ?><?php echo strlen( $gateway_txn_id ) > 20 ? '&hellip;' : ''; ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $gateway_txn_id ); ?>
						<?php endif; ?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( $subscriber_id ) : ?>
				<div class="lp-sidebar-field">
					<span class="lp-sidebar-label"><?php esc_html_e( 'Customer ID', 'leaky-paywall' ); ?></span>
					<span class="lp-sidebar-value">
						<?php if ( $stripe_customer_url ) : ?>
							<a href="<?php echo esc_url( $stripe_customer_url ); ?>" target="_blank">
								<?php echo esc_html( $subscriber_id ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $subscriber_id ); ?>
						<?php endif; ?>
					</span>
				</div>
			<?php endif; ?>

			<div class="lp-sidebar-field">
				<span class="lp-sidebar-label"><?php esc_html_e( 'Payment Type', 'leaky-paywall' ); ?></span>
				<span class="lp-sidebar-value"><?php echo esc_html( $payment_type ); ?></span>
			</div>

			<?php if ( $subscriber_url ) : ?>
				<div class="lp-sidebar-actions">
					<a href="<?php echo esc_url( $subscriber_url ); ?>">
						<?php esc_html_e( 'View Subscriber', 'leaky-paywall' ); ?> &rarr;
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $email ) : ?>
				<?php
				$all_transactions_url = admin_url( 'edit.php?post_type=lp_transaction&s=' . urlencode( $email ) );
				?>
				<div class="lp-sidebar-actions" style="padding-top: 0;">
					<a href="<?php echo esc_url( $all_transactions_url ); ?>">
						<?php esc_html_e( 'All Transactions for Customer', 'leaky-paywall' ); ?> &rarr;
					</a>
				</div>
			<?php endif; ?>
		</div>

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
				echo '<a href="' . esc_url(admin_url('post.php?post=' . absint($post_id) . '&action=edit')) . '">' . esc_html(get_post_meta($post_id, '_email', true)) . '</a>';
				break;

			case 'level':
				if (isset($level['label'])) {
					echo '<span class="lp-txn-level-id">ID: ' . esc_html($level_id) . ' - </span>' . esc_html($level['label']);
				}
				break;

			case 'price':
				$price     = get_post_meta($post_id, '_price', true);
				$tax_amt   = get_post_meta($post_id, '_tax_amount', true);
				$sub_total = get_post_meta($post_id, '_subtotal', true);

				if ( ! empty( $tax_amt ) && $tax_amt > 0 ) {
					$display_total = floatval( $sub_total ) + floatval( $tax_amt );
					echo esc_html( leaky_paywall_get_current_currency_symbol() . number_format( $display_total, 2 ) );
				} elseif (is_numeric($price)) {
					$formatted = esc_html(leaky_paywall_get_current_currency_symbol() . number_format($price, 2));
					if ($price < 0) {
						echo '<span class="lp-txn-price--negative">' . $formatted . '</span>';
					} else {
						echo $formatted;
					}
				} else {
					echo esc_html(leaky_paywall_get_current_currency_symbol() . $price);
				}

				break;

			case 'created':
				echo esc_html(get_the_date('M d, Y', $post_id));
				break;

			case 'status':
				$txn_status = get_post_meta($post_id, '_transaction_status', true);
				$display_status = $txn_status ? $txn_status : 'complete';
				$badge_class = 'lp-status-badge--' . sanitize_html_class(strtolower($display_status));
				echo '<span class="lp-status-badge ' . esc_attr($badge_class) . '">' . esc_html(ucfirst($display_status)) . '</span>';
				break;

			case 'type':
				$status = get_post_meta($post_id, '_status', true);
				if ('refund' === $status) {
					echo '<span class="lp-status-badge lp-status-badge--refund">' . esc_html__('Refund', 'leaky-paywall') . '</span>';
				} elseif (get_post_meta($post_id, '_is_recurring', true)) {
					echo '<span class="lp-txn-type--renewal">' . esc_html__('Subscription Renewal', 'leaky-paywall') . '</span>';
				} elseif (isset($level['recurring']) && $level['recurring']) {
					echo esc_html__('Initial Subscription Payment', 'leaky-paywall');
				} else {
					echo esc_html__('One Time', 'leaky-paywall');
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
		$payment_type_options = array(
			'paid'    => esc_html__('Paid', 'leaky-paywall'),
			'free'    => esc_html__('Free', 'leaky-paywall'),
			'refund'  => esc_html__('Refund', 'leaky-paywall'),
			'renewal' => esc_html__('Renewal', 'leaky-paywall'),
		);
		$payment_type_options = apply_filters('leaky_paywall_transaction_payment_type_filters', $payment_type_options);
		?>
		<select name="lp_payment_type">
			<option value=""><?php esc_html_e('All payment types', 'leaky-paywall'); ?></option>
			<?php foreach ($payment_type_options as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($current_type, $value); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
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
