<?php
/**
 * Activation prompts for Leaky Paywall Pro.
 *
 * Banner appears only on the Leaky Paywall Dashboard so publishers setting LP
 * up don't get nagged on Settings, Tools, Subscribers, etc. The WordPress
 * dashboard widget stays as a secondary discoverable surface. Banner is
 * dismissible per-user for 90 days via user meta + a small AJAX endpoint.
 *
 * After 90 days the banner re-appears once on the LP Dashboard so publishers
 * who weren't ready earlier still get a reminder.
 *
 * Both surfaces disappear automatically once a valid Pro license is active.
 *
 * @package Leaky Paywall
 * @since 5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice on the LP Dashboard prompting Pro activation.
 */
function leaky_paywall_pro_activation_notice() {

	if ( leaky_paywall_pro_is_active() ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_issuem-leaky-paywall' !== $screen->id ) {
		return;
	}

	// Respect per-user dismissal for 90 days. After that we re-show once so
	// publishers who weren't ready earlier still get a nudge.
	$dismissed_at = (int) get_user_meta( get_current_user_id(), 'lp_pro_prompt_dismissed_at', true );
	if ( $dismissed_at && ( time() - $dismissed_at ) < 90 * DAY_IN_SECONDS ) {
		return;
	}

	$license_url = admin_url( 'admin.php?page=leaky-paywall-license' );
	$nonce       = wp_create_nonce( 'lp_dismiss_pro_prompt' );
	$ajax_url    = admin_url( 'admin-ajax.php' );
	?>
	<div class="notice notice-info is-dismissible" data-lp-prompt="pro" data-lp-nonce="<?php echo esc_attr( $nonce ); ?>">
		<p>
			<strong><?php esc_html_e( 'Leaky Paywall Pro', 'leaky-paywall' ); ?>:</strong>
			<?php esc_html_e( 'Activate your license to unlock all premium extensions and updates.', 'leaky-paywall' ); ?>
			<a href="<?php echo esc_url( $license_url ); ?>"><?php esc_html_e( 'Add your license key →', 'leaky-paywall' ); ?></a>
		</p>
	</div>
	<script>
	( function() {
		var notice = document.querySelector( '.notice[data-lp-prompt="pro"]' );
		if ( ! notice ) { return; }

		var nonce   = notice.getAttribute( 'data-lp-nonce' );
		var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

		// WP core wires .notice-dismiss onto every .is-dismissible notice on
		// admin-init. Listen for its click so we can persist the dismissal —
		// the visual fade-out is already handled by core.
		notice.addEventListener( 'click', function( e ) {
			if ( ! e.target.closest( '.notice-dismiss' ) ) { return; }

			var data = new FormData();
			data.append( 'action', 'lp_dismiss_pro_prompt' );
			data.append( 'nonce', nonce );

			// Fire and forget — the banner is already gone visually before
			// the response lands, and the worst case (network failure) is
			// the banner re-appears next page load, which is the same as
			// today's behavior.
			fetch( ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} );
		} );
	} )();
	</script>
	<?php
}
add_action( 'admin_notices', 'leaky_paywall_pro_activation_notice' );

/**
 * AJAX handler for dismissing the Pro prompt banner. Records the timestamp in
 * user meta so the banner stays hidden for ~90 days.
 */
function leaky_paywall_ajax_dismiss_pro_prompt() {
	if ( ! check_ajax_referer( 'lp_dismiss_pro_prompt', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	update_user_meta( get_current_user_id(), 'lp_pro_prompt_dismissed_at', time() );
	wp_send_json_success();
}
add_action( 'wp_ajax_lp_dismiss_pro_prompt', 'leaky_paywall_ajax_dismiss_pro_prompt' );

/**
 * Register a WordPress-dashboard widget prompting Pro activation.
 */
function leaky_paywall_register_pro_dashboard_widget() {

	if ( leaky_paywall_pro_is_active() ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	wp_add_dashboard_widget(
		'leaky_paywall_pro_activation',
		__( 'Activate Leaky Paywall Pro', 'leaky-paywall' ),
		'leaky_paywall_render_pro_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'leaky_paywall_register_pro_dashboard_widget' );

/**
 * Dashboard widget body.
 */
function leaky_paywall_render_pro_dashboard_widget() {
	$license_url = admin_url( 'admin.php?page=leaky-paywall-license' );
	$upgrade_url = 'https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_source=plugin&utm_medium=dashboard_widget&utm_content=cta&utm_campaign=upgrade';
	?>
	<p><?php esc_html_e( 'Enter your Leaky Paywall Pro license key to unlock all premium extensions, install them with one click, and receive automatic updates.', 'leaky-paywall' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $license_url ); ?>" class="button button-primary"><?php esc_html_e( 'Add License Key', 'leaky-paywall' ); ?></a>
		<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'Get Pro', 'leaky-paywall' ); ?></a>
	</p>
	<?php
}
