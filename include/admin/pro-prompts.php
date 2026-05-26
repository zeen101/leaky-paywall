<?php
/**
 * Activation prompts for Leaky Paywall Pro.
 *
 * Surfaces the License page to publishers who have not yet activated a Pro
 * key, via an admin notice on LP screens and a WordPress dashboard widget.
 * Both disappear automatically once a valid Pro license is active.
 *
 * @package Leaky Paywall
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice on Leaky Paywall admin screens prompting Pro activation.
 */
function leaky_paywall_pro_activation_notice() {

	if ( leaky_paywall_pro_is_active() ) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	// Only on Leaky Paywall admin pages.
	$is_lp_screen = ( false !== strpos( $screen->id, 'leaky-paywall' ) )
		|| ( false !== strpos( $screen->id, 'issuem-leaky-paywall' ) )
		|| ( isset( $screen->post_type ) && 'lp_transaction' === $screen->post_type );

	if ( ! $is_lp_screen ) {
		return;
	}

	// Don't nag on the License page itself.
	if ( isset( $_GET['page'] ) && 'leaky-paywall-license' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
		return;
	}

	$license_url = admin_url( 'admin.php?page=leaky-paywall-license' );
	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Leaky Paywall Pro', 'leaky-paywall' ); ?>:</strong>
			<?php esc_html_e( 'Activate your license to unlock all premium extensions and updates.', 'leaky-paywall' ); ?>
			<a href="<?php echo esc_url( $license_url ); ?>"><?php esc_html_e( 'Add your license key →', 'leaky-paywall' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'leaky_paywall_pro_activation_notice' );

/**
 * Register a dashboard widget prompting Pro activation.
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
