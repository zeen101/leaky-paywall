<?php
/**
 * Leaky Paywall > License admin page.
 *
 * The discoverable home for the single Pro license key. Renders the
 * activation form, current status, and (for free installs) a Get Pro CTA.
 *
 * @package Leaky Paywall
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_License_Page {

	const UPGRADE_URL = 'https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_source=plugin&utm_medium=license_page&utm_content=cta&utm_campaign=upgrade';

	public function render_page() {
		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		$license = Leaky_Paywall_Pro_License::get();
		$active  = 'valid' === $license['status'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Leaky Paywall License', 'leaky-paywall' ); ?></h1>

			<?php $this->render_notices(); ?>

			<div class="lp-license-page" style="max-width: 720px; margin-top: 16px;">
				<div class="lp-license-card" style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:20px;">
					<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
						<h2 style="margin:0;"><?php esc_html_e( 'Leaky Paywall Pro', 'leaky-paywall' ); ?></h2>
						<?php echo $this->status_badge( $license ); ?>
					</div>

					<p class="description" style="margin-bottom:16px;">
						<?php esc_html_e( 'One license key unlocks every Leaky Paywall Pro extension. Enter the key from your leakypaywall.com account below.', 'leaky-paywall' ); ?>
						<a href="https://leakypaywall.com/my-account/#tabs-2" target="_blank" rel="noopener"><?php esc_html_e( 'Find your license key', 'leaky-paywall' ); ?></a>
					</p>

					<form method="post" action="">
						<?php wp_nonce_field( 'leaky_paywall_pro_license', 'leaky_paywall_pro_license_nonce' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="leaky_paywall_pro_license_key"><?php esc_html_e( 'License Key', 'leaky-paywall' ); ?></label></th>
								<td>
									<input
										type="<?php echo $active ? 'password' : 'text'; ?>"
										id="leaky_paywall_pro_license_key"
										name="leaky_paywall_pro_license_key"
										class="regular-text"
										value="<?php echo esc_attr( $license['key'] ); ?>"
										<?php echo $active ? 'readonly' : ''; ?>
									/>

									<?php if ( $active ) : ?>
										<input type="hidden" name="leaky_paywall_pro_license_action" value="deactivate" />
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Deactivate License', 'leaky-paywall' ); ?></button>
									<?php else : ?>
										<input type="hidden" name="leaky_paywall_pro_license_action" value="activate" />
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'leaky-paywall' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>

							<?php if ( $active && ! empty( $license['expires'] ) && ! $license['is_lifetime'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Expires', 'leaky-paywall' ); ?></th>
								<td><?php echo esc_html( $this->format_expires( $license['expires'] ) ); ?></td>
							</tr>
							<?php elseif ( $active && $license['is_lifetime'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Expires', 'leaky-paywall' ); ?></th>
								<td><?php esc_html_e( 'Never (lifetime)', 'leaky-paywall' ); ?></td>
							</tr>
							<?php endif; ?>
						</table>
					</form>
				</div>

				<?php if ( ! $active ) : ?>
					<div style="margin-top:20px;padding:20px;background:#f0f6fc;border:1px solid #c5d9ed;border-radius:6px;">
						<h3 style="margin-top:0;"><?php esc_html_e( "Don't have a Pro license yet?", 'leaky-paywall' ); ?></h3>
						<p><?php esc_html_e( 'Leaky Paywall Pro unlocks recurring subscriptions, all premium extensions, and priority support.', 'leaky-paywall' ); ?></p>
						<a href="<?php echo esc_url( self::UPGRADE_URL ); ?>" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'Get Leaky Paywall Pro', 'leaky-paywall' ); ?></a>
					</div>
				<?php else : ?>
					<p style="margin-top:16px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-extensions' ) ); ?>"><?php esc_html_e( 'Browse and install Pro extensions →', 'leaky-paywall' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function status_badge( $license ) {
		switch ( $license['status'] ) {
			case 'valid':
				$color = '#fff'; $bg = '#46b450'; $label = __( 'Active', 'leaky-paywall' );
				break;
			case 'expired':
				$color = '#fff'; $bg = '#dc3232'; $label = __( 'Expired', 'leaky-paywall' );
				break;
			case 'disabled':
				$color = '#fff'; $bg = '#dc3232'; $label = __( 'Disabled', 'leaky-paywall' );
				break;
			case '':
				$color = '#50575e'; $bg = '#f0f0f1'; $label = __( 'Not Activated', 'leaky-paywall' );
				break;
			default:
				$color = '#fff'; $bg = '#dba617'; $label = __( 'Inactive', 'leaky-paywall' );
				break;
		}

		return '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;color:' . esc_attr( $color ) . ';background:' . esc_attr( $bg ) . ';">' . esc_html( $label ) . '</span>';
	}

	private function format_expires( $expires ) {
		// EDD returns either a Y-m-d H:i:s string or a unix timestamp.
		$ts = is_numeric( $expires ) ? (int) $expires : strtotime( $expires );
		if ( ! $ts ) {
			return $expires;
		}
		return date_i18n( get_option( 'date_format', 'F j, Y' ), $ts );
	}

	private function render_notices() {
		$notices = get_transient( 'leaky_paywall_pro_license_notices' );
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}
		delete_transient( 'leaky_paywall_pro_license_notices' );

		foreach ( $notices as $notice ) {
			$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}
}
