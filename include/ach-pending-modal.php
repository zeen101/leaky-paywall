<?php
/**
 * Display an "ACH payment is processing" notice to subscribers who just signed
 * up with a bank account. ACH payments take 3-5 business days to settle, and
 * without this notice subscribers often assume the signup failed and retry,
 * resulting in duplicate subscriptions and double charges.
 *
 * The notice renders as a modal on any page that has ?lp_txn_id=<id> in the
 * URL — the lp_txn_id is appended to the post-signup redirect in
 * leaky_paywall_get_redirect_url().
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'leaky_paywall_render_ach_pending_modal' );

/**
 * Decide whether to render the ACH-pending modal and emit it.
 */
function leaky_paywall_render_ach_pending_modal() {

	if ( empty( $_GET['lp_txn_id'] ) ) {
		return;
	}

	$txn_id = absint( $_GET['lp_txn_id'] );
	if ( ! $txn_id ) {
		return;
	}

	$txn = get_post( $txn_id );
	if ( ! $txn || 'lp_transaction' !== $txn->post_type ) {
		return;
	}

	// Anti-nag: don't show forever if the user bookmarks a URL with lp_txn_id.
	$window_minutes = (int) apply_filters( 'leaky_paywall_ach_notice_window_minutes', 60 );
	$created_ts     = strtotime( $txn->post_date_gmt . ' UTC' );
	if ( $created_ts && ( time() - $created_ts ) > ( $window_minutes * 60 ) ) {
		return;
	}

	if ( 'stripe' !== get_post_meta( $txn_id, '_gateway', true ) ) {
		return;
	}

	$payment_method_type = (string) get_post_meta( $txn_id, '_payment_method_type', true );

	if ( '' === $payment_method_type ) {
		$payment_method_type = leaky_paywall_fetch_and_cache_payment_method_type( $txn_id );
		if ( false === $payment_method_type ) {
			return;
		}
	}

	if ( 'us_bank_account' !== $payment_method_type ) {
		return;
	}

	leaky_paywall_print_ach_pending_modal_markup();
}

/**
 * Retrieve the PaymentIntent's payment_method type from Stripe and cache it on
 * the transaction post. Returns the type string, or false on failure.
 *
 * @param int $txn_id Transaction post ID.
 * @return string|false
 */
function leaky_paywall_fetch_and_cache_payment_method_type( $txn_id ) {

	$pi_id = (string) get_post_meta( $txn_id, '_gateway_txn_id', true );
	if ( '' === $pi_id || 0 !== strpos( $pi_id, 'pi_' ) ) {
		return false;
	}

	if ( ! function_exists( 'leaky_paywall_initialize_stripe_api' ) ) {
		return false;
	}

	try {
		$stripe = leaky_paywall_initialize_stripe_api();
		$pi     = $stripe->paymentIntents->retrieve(
			$pi_id,
			array( 'expand' => array( 'payment_method' ) ),
			leaky_paywall_get_stripe_connect_params()
		);
	} catch ( \Throwable $e ) {
		return false;
	}

	$type = isset( $pi->payment_method->type ) ? (string) $pi->payment_method->type : '';

	// Cache an empty string as "unknown" so we don't retry the Stripe call on
	// every page load when the PI legitimately has no payment method set.
	update_post_meta( $txn_id, '_payment_method_type', $type );

	return $type;
}

/**
 * Emit the modal markup. All strings are filterable so publishers can rewrite
 * copy without forking the plugin.
 */
function leaky_paywall_print_ach_pending_modal_markup() {

	$title  = apply_filters( 'leaky_paywall_ach_notice_title', __( 'Your bank payment is processing', 'leaky-paywall' ) );
	$body   = apply_filters( 'leaky_paywall_ach_notice_body', __( 'Your bank payment is processing and may take 3 to 5 business days to clear. You will receive a welcome email with your login details once it completes. <strong>Please do not submit the form again — your subscription is already on its way.</strong>', 'leaky-paywall' ) );
	$button = apply_filters( 'leaky_paywall_ach_notice_button', __( 'Got it', 'leaky-paywall' ) );
	?>
	<div id="leaky-paywall-ach-notice" role="dialog" aria-labelledby="leaky-paywall-ach-notice-title" aria-modal="true" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
		<div style="background:#fff; max-width:480px; width:90%; padding:28px; border-radius:8px; box-shadow:0 8px 32px rgba(0,0,0,0.2); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
			<h2 id="leaky-paywall-ach-notice-title" style="margin:0 0 12px; font-size:20px; line-height:1.3; color:#1a1a1a;"><?php echo esc_html( $title ); ?></h2>
			<div style="font-size:15px; line-height:1.5; color:#4a4a4a; margin-bottom:20px;"><?php echo wp_kses_post( $body ); ?></div>
			<button type="button" id="leaky-paywall-ach-notice-close" style="background:#2271b1; color:#fff; border:0; padding:10px 18px; font-size:15px; border-radius:4px; cursor:pointer;"><?php echo esc_html( $button ); ?></button>
		</div>
	</div>
	<script>
	(function () {
		var modal = document.getElementById('leaky-paywall-ach-notice');
		var closeBtn = document.getElementById('leaky-paywall-ach-notice-close');
		if (!modal || !closeBtn) { return; }
		modal.style.display = 'flex';
		function dismiss() { modal.style.display = 'none'; }
		closeBtn.addEventListener('click', dismiss);
		modal.addEventListener('click', function (e) { if (e.target === modal) { dismiss(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { dismiss(); } });
	})();
	</script>
	<?php
}
