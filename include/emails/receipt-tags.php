<?php

/**
 * Payment receipt email: transaction template tags, dispatch, admin resend.
 *
 * The receipt is driven off an lp_transaction post. Every payment path (Stripe
 * initial + renewal via Recurring Payments, PayPal, Authorize.net, manual, REST)
 * funnels through LP_Transaction::create(), which fires
 * leaky_paywall_after_create_transaction. Hooking that once covers them all;
 * the gate in leaky_paywall_maybe_send_payment_receipt() keeps out free
 * signups, refunds and incomplete rows.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the receipt template tag values for a transaction.
 *
 * @param int $transaction_id Transaction post ID.
 * @return array token => value (tokens without the surrounding %).
 */
function leaky_paywall_get_payment_receipt_tag_values( $transaction_id ) {

	$transaction = get_post( $transaction_id );

	if ( ! $transaction || 'lp_transaction' !== $transaction->post_type ) {
		return array();
	}

	$price          = get_post_meta( $transaction_id, '_price', true );
	$subtotal       = get_post_meta( $transaction_id, '_subtotal', true );
	$tax            = get_post_meta( $transaction_id, '_tax_amount', true );
	$currency       = get_post_meta( $transaction_id, '_currency', true );
	$gateway        = get_post_meta( $transaction_id, '_gateway', true );
	$gateway_txn_id = get_post_meta( $transaction_id, '_gateway_txn_id', true );
	$level_id       = get_post_meta( $transaction_id, '_level_id', true );
	$is_recurring   = get_post_meta( $transaction_id, '_is_recurring', true );
	$email          = get_post_meta( $transaction_id, '_email', true );
	$receipt_url    = get_post_meta( $transaction_id, '_gateway_receipt_url', true );

	$level = $level_id ? get_leaky_paywall_subscription_level( $level_id ) : array();

	if ( ! empty( $tax ) && floatval( $tax ) > 0 ) {
		$amount_number = floatval( $subtotal ) + floatval( $tax );
	} else {
		$amount_number = floatval( $price );
	}

	$payment_date = date_i18n( get_option( 'date_format' ), strtotime( $transaction->post_date ) );

	$method_names   = array(
		'stripe'            => __( 'Card', 'leaky-paywall' ),
		'stripe_checkout'   => __( 'Card', 'leaky-paywall' ),
		'paypal'            => __( 'PayPal', 'leaky-paywall' ),
		'paypal_standard'   => __( 'PayPal', 'leaky-paywall' ),
		'manual'            => __( 'Manual', 'leaky-paywall' ),
		'free_registration' => __( 'Free', 'leaky-paywall' ),
	);
	$payment_method = isset( $method_names[ $gateway ] ) ? $method_names[ $gateway ] : ucwords( str_replace( '_', ' ', (string) $gateway ) );

	if ( $is_recurring ) {
		$payment_type = __( 'Subscription renewal', 'leaky-paywall' );
	} elseif ( ! empty( $level['recurring'] ) && 'on' === $level['recurring'] ) {
		$payment_type = __( 'New subscription', 'leaky-paywall' );
	} else {
		$payment_type = __( 'Payment', 'leaky-paywall' );
	}

	$next_renewal = '';

	if ( $email ) {
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$mode    = leaky_paywall_get_current_mode();
			$site    = leaky_paywall_get_current_site();
			$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );

			if ( $expires && '0000-00-00 00:00:00' !== $expires ) {
				$next_renewal = date_i18n( get_option( 'date_format' ), strtotime( $expires ) );
			}
		}
	}

	$settings    = get_leaky_paywall_settings();
	$account_url = ! empty( $settings['page_for_profile'] ) ? get_page_link( $settings['page_for_profile'] ) : home_url();

	$values = array(
		'amount'            => leaky_paywall_format_display_price( $amount_number ),
		'currency'          => $currency ? strtoupper( $currency ) : leaky_paywall_get_currency(),
		'subtotal'          => ( '' !== $subtotal && null !== $subtotal ) ? leaky_paywall_format_display_price( floatval( $subtotal ) ) : '',
		'tax'               => ( ! empty( $tax ) && floatval( $tax ) > 0 ) ? leaky_paywall_format_display_price( floatval( $tax ) ) : '',
		'payment_date'      => $payment_date,
		'payment_method'    => $payment_method,
		'payment_type'      => $payment_type,
		'level_name'        => isset( $level['label'] ) ? $level['label'] : '',
		'invoice_number'    => apply_filters( 'leaky_paywall_invoice_number', '#' . $transaction_id, $transaction_id ),
		'transaction_id'    => $gateway_txn_id ? $gateway_txn_id : (string) $transaction_id,
		'next_renewal_date' => $next_renewal,
		'account_url'       => $account_url,
		'receipt_url'       => $receipt_url ? $receipt_url : '',
	);

	/**
	 * Filter the payment receipt template tag values.
	 *
	 * @param array $values         token => value.
	 * @param int   $transaction_id Transaction post ID.
	 */
	return apply_filters( 'leaky_paywall_payment_receipt_tags', $values, $transaction_id );
}

/**
 * Replace %token% and %token|fallback% in a string with transaction values.
 *
 * @param string $content        The string with tags.
 * @param int    $transaction_id Transaction post ID.
 * @return string
 */
function leaky_paywall_replace_payment_receipt_tags( $content, $transaction_id ) {

	$values = leaky_paywall_get_payment_receipt_tag_values( $transaction_id );

	foreach ( $values as $token => $value ) {
		$content = preg_replace_callback(
			'/%' . preg_quote( $token, '/' ) . '(?:\|([^%]*))?%/',
			function ( $matches ) use ( $value ) {
				if ( '' !== (string) $value ) {
					return $value;
				}

				return isset( $matches[1] ) ? trim( $matches[1] ) : '';
			},
			$content
		);
	}

	return $content;
}

/**
 * Send (or resend) the payment receipt for a transaction.
 *
 * @param int  $transaction_id Transaction post ID.
 * @param bool $force          Send even when the email type is disabled.
 * @return bool
 */
function leaky_paywall_send_payment_receipt( $transaction_id, $force = false ) {

	if ( ! class_exists( 'LP_Emails' ) ) {
		return false;
	}

	$email = LP_Emails::instance()->get_email( 'payment_receipt' );

	if ( ! $email ) {
		return false;
	}

	return $email->trigger(
		0,
		array(
			'transaction_id' => $transaction_id,
			'force'          => $force,
		)
	);
}

/**
 * Whether a transaction is eligible for a receipt (paid, not a refund,
 * not incomplete).
 *
 * @param int $transaction_id Transaction post ID.
 * @return bool
 */
function leaky_paywall_transaction_can_have_receipt( $transaction_id ) {

	$price = get_post_meta( $transaction_id, '_price', true );

	if ( ! is_numeric( $price ) || (float) $price <= 0 ) {
		return false;
	}

	if ( 'refund' === get_post_meta( $transaction_id, '_status', true ) ) {
		return false;
	}

	$txn_status = get_post_meta( $transaction_id, '_transaction_status', true );

	if ( $txn_status && ! in_array( $txn_status, array( 'complete', 'completed' ), true ) ) {
		return false;
	}

	return true;
}

/**
 * Send the receipt when a qualifying transaction is created.
 *
 * The recipient comes from the transaction's own email meta, so the $user arg
 * that leaky_paywall_after_create_transaction also passes is not needed here.
 *
 * @param int $transaction_id Transaction post ID.
 */
function leaky_paywall_maybe_send_payment_receipt( $transaction_id ) {

	if ( ! leaky_paywall_transaction_can_have_receipt( $transaction_id ) ) {
		return;
	}

	if ( get_post_meta( $transaction_id, '_lp_receipt_sent', true ) ) {
		return;
	}

	if ( ! class_exists( 'LP_Emails' ) ) {
		return;
	}

	$email = LP_Emails::instance()->get_email( 'payment_receipt' );

	if ( ! $email || ! $email->is_enabled() ) {
		return;
	}

	leaky_paywall_send_payment_receipt( $transaction_id, false );
}
add_action( 'leaky_paywall_after_create_transaction', 'leaky_paywall_maybe_send_payment_receipt', 20, 1 );

/**
 * One-time initialization of the payment receipt email setting.
 *
 * Fresh installs: enabled. Upgrades: disabled, plus a dismissible notice so the
 * publisher opts in deliberately and nobody starts sending duplicate receipts
 * alongside their gateway's.
 */
function leaky_paywall_maybe_init_payment_receipt_email() {

	if ( get_option( 'lp_payment_receipt_email_initialized' ) ) {
		return;
	}

	if ( false !== get_option( 'leaky_paywall_email_payment_receipt_settings', false ) ) {
		update_option( 'lp_payment_receipt_email_initialized', '1' );
		return;
	}

	$lp_settings = get_option( 'issuem-leaky-paywall' );
	$is_fresh    = empty( $lp_settings );

	update_option(
		'leaky_paywall_email_payment_receipt_settings',
		array( 'enabled' => $is_fresh ? 'yes' : 'no' )
	);

	if ( ! $is_fresh ) {
		update_option( 'lp_payment_receipt_email_notice', '1' );
	}

	update_option( 'lp_payment_receipt_email_initialized', '1' );
}
add_action( 'admin_init', 'leaky_paywall_maybe_init_payment_receipt_email', 6 );

/**
 * Notice pointing existing publishers at the new receipt email.
 */
function leaky_paywall_payment_receipt_email_notice() {

	if ( ! get_option( 'lp_payment_receipt_email_notice' ) ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || false === strpos( $screen->id, 'leaky-paywall' ) ) {
		return;
	}

	$key = 'payment_receipt_available';

	if ( function_exists( 'leaky_paywall_config_notice_dismissed' ) && leaky_paywall_config_notice_dismissed( $key ) ) {
		return;
	}

	if ( ! function_exists( 'leaky_paywall_render_config_notice' ) ) {
		return;
	}

	$emails_url = admin_url( 'admin.php?page=leaky-paywall-settings&tab=emails&section=payment_receipt' );

	leaky_paywall_render_config_notice(
		'info',
		$key,
		sprintf(
			/* translators: 1: opening link tag, 2: closing link tag. */
			__( 'Leaky Paywall can now send its own branded payment receipts for every initial payment and renewal. %1$sEnable the Payment Receipt email%2$s.', 'leaky-paywall' ),
			'<a href="' . esc_url( $emails_url ) . '">',
			'</a>'
		),
		__( 'If you enable it, turn off duplicate receipts in your Stripe or PayPal settings so subscribers do not get two.', 'leaky-paywall' )
	);
}
add_action( 'admin_notices', 'leaky_paywall_payment_receipt_email_notice' );

/**
 * Add a "Resend Receipt" row action to the transactions list.
 *
 * @param array   $actions Existing row actions.
 * @param WP_Post $post    The transaction post.
 * @return array
 */
function leaky_paywall_transaction_row_actions( $actions, $post ) {

	if ( 'lp_transaction' !== $post->post_type ) {
		return $actions;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}

	if ( ! leaky_paywall_transaction_can_have_receipt( $post->ID ) ) {
		return $actions;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=lp_resend_receipt&transaction=' . $post->ID ),
		'lp_resend_receipt_' . $post->ID
	);

	$sent  = get_post_meta( $post->ID, '_lp_receipt_sent', true );
	$label = $sent ? __( 'Resend Receipt', 'leaky-paywall' ) : __( 'Send Receipt', 'leaky-paywall' );

	$actions['lp_resend_receipt'] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

	return $actions;
}
add_filter( 'post_row_actions', 'leaky_paywall_transaction_row_actions', 10, 2 );

/**
 * "Send / Resend Receipt" button in the transaction sidebar meta box.
 *
 * @param WP_Post $post The transaction post.
 */
function leaky_paywall_transaction_sidebar_receipt_button( $post ) {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! leaky_paywall_transaction_can_have_receipt( $post->ID ) ) {
		return;
	}

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=lp_resend_receipt&transaction=' . $post->ID ),
		'lp_resend_receipt_' . $post->ID
	);

	$sent  = get_post_meta( $post->ID, '_lp_receipt_sent', true );
	$label = $sent ? __( 'Resend Receipt', 'leaky-paywall' ) : __( 'Send Receipt', 'leaky-paywall' );
	?>
	<div class="lp-sidebar-actions" style="padding-top: 0;">
		<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?> &rarr;</a>
		<?php if ( $sent ) : ?>
			<p class="description" style="margin: 4px 0 0;">
				<?php
				printf(
					/* translators: %s: human-readable time difference. */
					esc_html__( 'Last sent %s ago', 'leaky-paywall' ),
					esc_html( human_time_diff( (int) $sent ) )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'leaky_paywall_transaction_sidebar_after', 'leaky_paywall_transaction_sidebar_receipt_button' );

/**
 * Handle the "Resend Receipt" action.
 */
function leaky_paywall_handle_resend_receipt() {

	$transaction_id = isset( $_GET['transaction'] ) ? absint( $_GET['transaction'] ) : 0;

	if ( ! $transaction_id || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'leaky-paywall' ) );
	}

	check_admin_referer( 'lp_resend_receipt_' . $transaction_id );

	delete_post_meta( $transaction_id, '_lp_receipt_sent' );

	$sent = leaky_paywall_send_payment_receipt( $transaction_id, true );

	$redirect = wp_get_referer();

	if ( ! $redirect ) {
		$redirect = admin_url( 'edit.php?post_type=lp_transaction' );
	}

	$redirect = add_query_arg( 'lp_receipt', $sent ? 'sent' : 'failed', $redirect );

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_lp_resend_receipt', 'leaky_paywall_handle_resend_receipt' );

/**
 * Admin notice after a resend.
 */
function leaky_paywall_resend_receipt_notice() {

	// Read-only display of a post-redirect result. The action that triggers it
	// (leaky_paywall_handle_resend_receipt) verifies its own nonce.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['lp_receipt'] ) ) {
		return;
	}

	$result = sanitize_key( wp_unslash( $_GET['lp_receipt'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'sent' === $result ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Receipt sent.', 'leaky-paywall' ) . '</p></div>';
	} elseif ( 'failed' === $result ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The receipt could not be sent. Check the transaction has a valid email and the Payment Receipt email is configured.', 'leaky-paywall' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'leaky_paywall_resend_receipt_notice' );
