<?php

/**
 * Shared HTML wrapper for Leaky Paywall emails.
 *
 * A light, table-based, inline-styled shell so publishers get a hint of their
 * branding (logo, one accent colour, footer text) without a template editor.
 * Applied to every LP email via LP_Email::wrap(). The publisher's body HTML is
 * dropped into the content cell untouched.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve %sitename% / %siteurl% / %year% in the footer text.
 *
 * @param string $text Raw footer text.
 * @return string
 */
function leaky_paywall_email_footer_tags( $text ) {

	$replacements = array(
		'%sitename%' => stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) ),
		'%siteurl%'  => home_url(),
		'%year%'     => gmdate( 'Y' ),
	);

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
}

/**
 * Wrap an email body in the branded template.
 *
 * @param string $body_html The email body (already HTML, e.g. wpautop'd).
 * @param array  $args       Optional. 'email_id', 'preheader'.
 * @return string A full HTML document.
 */
function leaky_paywall_get_email_template( $body_html, $args = array() ) {

	$settings = get_leaky_paywall_settings();

	$logo   = isset( $settings['email_logo'] ) ? trim( $settings['email_logo'] ) : '';
	$accent = isset( $settings['email_accent_color'] ) && $settings['email_accent_color']
		? $settings['email_accent_color']
		: '#1e293b';
	$footer = isset( $settings['email_footer_text'] ) ? trim( $settings['email_footer_text'] ) : '';

	$accent    = apply_filters( 'leaky_paywall_email_template_accent_color', $accent, $args );
	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );

	if ( '' === $footer ) {
		$footer_html = '<a href="' . esc_url( home_url() ) . '" style="color:#6b7280;text-decoration:none;">' . esc_html( $site_name ) . '</a>';
	} else {
		$footer_html = wpautop( make_clickable( leaky_paywall_email_footer_tags( $footer ) ) );
	}

	if ( $logo ) {
		$header_html = '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site_name ) . '" style="display:block;margin:0 auto;max-height:40px;max-width:220px;height:auto;border:0;">';
	} else {
		$header_html = '<span style="font-size:20px;font-weight:700;color:' . esc_attr( $accent ) . ';">' . esc_html( $site_name ) . '</span>';
	}

	$preheader = ! empty( $args['preheader'] ) ? $args['preheader'] : '';

	$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

	ob_start();
	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title><?php echo esc_html( $site_name ); ?></title>
<style>
	a { color: <?php echo esc_html( $accent ); ?>; }
	h1, h2, h3 { color: <?php echo esc_html( $accent ); ?>; margin: 0 0 12px; }
	body { margin: 0; padding: 0; background: #f4f4f5; }
</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;">
	<?php if ( $preheader ) : ?>
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;"><?php echo esc_html( $preheader ); ?></div>
	<?php endif; ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
	<tr>
		<td align="center" style="padding:24px 12px;">
			<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background:#ffffff;border-top:4px solid <?php echo esc_attr( $accent ); ?>;border-radius:6px;">
				<tr>
					<td align="center" style="padding:28px 32px 8px;">
						<?php echo wp_kses_post( $header_html ); ?>
					</td>
				</tr>
				<tr>
					<td style="padding:16px 32px 28px;font-family:<?php echo esc_attr( $font ); ?>;font-size:15px;line-height:1.6;color:#1f2937;">
						<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller-supplied email body, already built/escaped upstream. ?>
					</td>
				</tr>
				<tr>
					<td style="padding:18px 32px;border-top:1px solid #e5e7eb;font-family:<?php echo esc_attr( $font ); ?>;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">
						<?php echo wp_kses_post( $footer_html ); ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
	<?php
	$html = ob_get_clean();

	/**
	 * Filter the full wrapped email HTML.
	 *
	 * @param string $html      The rendered document.
	 * @param string $body_html The inner body that was wrapped.
	 * @param array  $args      Context ('email_id', 'preheader').
	 */
	return apply_filters( 'leaky_paywall_email_template_html', $html, $body_html, $args );
}

/**
 * Give fresh installs a starter footer (site name + URL) so the footer looks
 * intentional out of the box. Existing installs keep the implicit site-name
 * fallback, nothing changes retroactively.
 */
function leaky_paywall_maybe_seed_email_footer() {

	if ( get_option( 'lp_email_footer_seeded' ) ) {
		return;
	}

	update_option( 'lp_email_footer_seeded', '1' );

	// A stored settings array means this is not a fresh install.
	$settings = get_option( 'issuem-leaky-paywall', false );

	if ( ! empty( $settings ) ) {
		return;
	}

	update_option(
		'issuem-leaky-paywall',
		array( 'email_footer_text' => '%sitename%' . "\n" . '%siteurl%' )
	);
}
add_action( 'admin_init', 'leaky_paywall_maybe_seed_email_footer', 6 );

/**
 * AJAX: send a test copy of an email to the given address.
 */
function leaky_paywall_ajax_send_test_email() {

	check_ajax_referer( 'lp_send_test_email', 'nonce' );

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'leaky-paywall' ) ), 403 );
	}

	$email_id = isset( $_POST['email_id'] ) ? sanitize_key( wp_unslash( $_POST['email_id'] ) ) : '';
	$to       = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';

	$email = LP_Emails::instance()->get_email( $email_id );

	if ( ! $email ) {
		wp_send_json_error( array( 'message' => __( 'Unknown email.', 'leaky-paywall' ) ), 404 );
	}

	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'message' => __( 'Enter a valid email address.', 'leaky-paywall' ) ) );
	}

	if ( $email->send_test( $to ) ) {
		wp_send_json_success(
			array(
				/* translators: %s: recipient email address. */
				'message' => sprintf( __( 'Test sent to %s', 'leaky-paywall' ), $to ),
			)
		);
	}

	wp_send_json_error( array( 'message' => __( 'Could not send. Check the site mail configuration.', 'leaky-paywall' ) ) );
}
add_action( 'wp_ajax_lp_send_test_email', 'leaky_paywall_ajax_send_test_email' );
