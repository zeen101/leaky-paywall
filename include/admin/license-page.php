<?php
/**
 * Leaky Paywall > License admin page.
 *
 * The discoverable home for the single Pro license key. Renders the
 * activation form, current status, and (for free installs) a Get Pro CTA.
 *
 * @package Leaky Paywall
 * @since 5.1.0
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

		// Multi-key skip path: the publisher's site has different keys per extension,
		// so migration deliberately left the Pro UI off. Render an explanation and
		// point them at the legacy Licenses tab — the canonical UI for that case.
		if ( 'multi_key' === get_option( Leaky_Paywall_Pro_License_Migration::SKIPPED ) ) {
			$this->render_multi_key_explainer();
			return;
		}

		$license     = Leaky_Paywall_Pro_License::get();
		$active      = 'valid' === $license['status'];
		$prefill_key = $license['key'];
		$failure     = get_transient( Leaky_Paywall_Pro_License_Migration::FAIL_TRANSIENT );

		// Migration ran but the store rejected the key. Prefill what we tried so
		// the publisher can correct it and click Activate without re-typing.
		if ( ! $active && is_array( $failure ) && ! empty( $failure['key'] ) ) {
			$prefill_key = (string) $failure['key'];
		}

		$this->render_header();
		?>
		<div class="wrap">

			<?php $this->render_notices(); ?>
			<?php $this->render_migration_failure( $failure ); ?>

			<div class="lp-license-page">
				<p class="description">
					<?php esc_html_e( 'One license key unlocks every Leaky Paywall Pro extension. Enter the key from your leakypaywall.com account below.', 'leaky-paywall' ); ?>
					<a href="https://leakypaywall.com/my-account/#tabs-2" target="_blank" rel="noopener"><?php esc_html_e( 'Find your license key', 'leaky-paywall' ); ?></a>
				</p>

				<div class="lp-license-card">
					<div class="lp-license-card__header">
						<span class="lp-license-card__name"><?php esc_html_e( 'Leaky Paywall Pro', 'leaky-paywall' ); ?></span>
						<?php echo $this->status_badge( $license ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within status_badge(). ?>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'leaky_paywall_pro_license', 'leaky_paywall_pro_license_nonce' ); ?>

						<div class="lp-license-card__body">
							<label for="leaky_paywall_pro_license_key" class="screen-reader-text"><?php esc_html_e( 'License Key', 'leaky-paywall' ); ?></label>
							<input
								type="<?php echo $active ? 'password' : 'text'; ?>"
								id="leaky_paywall_pro_license_key"
								name="leaky_paywall_pro_license_key"
								class="regular-text"
								value="<?php echo esc_attr( $prefill_key ); ?>"
								<?php echo $active ? 'readonly' : ''; ?>
							/>

							<?php if ( $active ) : ?>
								<input type="hidden" name="leaky_paywall_pro_license_action" value="deactivate" />
								<button type="submit" class="button button-secondary"><?php esc_html_e( 'Deactivate License', 'leaky-paywall' ); ?></button>
							<?php else : ?>
								<input type="hidden" name="leaky_paywall_pro_license_action" value="activate" />
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'leaky-paywall' ); ?></button>
							<?php endif; ?>
						</div>
					</form>

					<?php if ( $active ) : ?>
						<div class="lp-license-card__meta">
							<span class="lp-license-card__meta-label"><?php esc_html_e( 'Expires:', 'leaky-paywall' ); ?></span>
							<span><?php echo esc_html( $license['is_lifetime'] ? __( 'Never (lifetime)', 'leaky-paywall' ) : $this->format_expires( $license['expires'] ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! $active ) : ?>
					<div class="lp-license-callout">
						<h3><?php esc_html_e( "Don't have a Pro license yet?", 'leaky-paywall' ); ?></h3>
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

	/**
	 * Standard LP admin header — orange bar with logo + page title. Matches
	 * Settings, Tools, Dashboard, etc.
	 */
	private function render_header() {
		?>
		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL . 'images/leaky-paywall-logo.png' ); ?>" alt="<?php esc_attr_e( 'Leaky Paywall', 'leaky-paywall' ); ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e( 'License', 'leaky-paywall' ); ?></h1>
				</span>
			</div>
		</div>
		<?php
	}

	private function status_badge( $license ) {
		switch ( $license['status'] ) {
			case 'valid':
				$variant = 'active';
				$label   = __( 'Active', 'leaky-paywall' );
				break;
			case 'expired':
				$variant = 'expired';
				$label   = __( 'Expired', 'leaky-paywall' );
				break;
			case 'disabled':
				$variant = 'expired';
				$label   = __( 'Disabled', 'leaky-paywall' );
				break;
			case '':
				$variant = 'canceled';
				$label   = __( 'Not Activated', 'leaky-paywall' );
				break;
			default:
				$variant = 'suspended';
				$label   = __( 'Inactive', 'leaky-paywall' );
				break;
		}

		return '<span class="lp-status-badge lp-status-badge--' . esc_attr( $variant ) . '">' . esc_html( $label ) . '</span>';
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
			if ( ! is_array( $notice ) || empty( $notice['type'] ) || ! isset( $notice['message'] ) ) {
				continue;
			}
			$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
	}

	/**
	 * Notice surfaced when the auto-migration found a key but the store rejected
	 * it (expired, disabled, no_activations_left, etc.). The key is prefilled
	 * in the form above so the publisher can correct it and click Activate.
	 *
	 * Cleared as soon as it's rendered; if the publisher's next action succeeds,
	 * normal success notices take over.
	 *
	 * @param mixed $failure Transient payload — array { key, error } or false.
	 */
	private function render_migration_failure( $failure ) {
		if ( ! is_array( $failure ) || empty( $failure['error'] ) ) {
			return;
		}
		delete_transient( Leaky_Paywall_Pro_License_Migration::FAIL_TRANSIENT );

		$message = sprintf(
			/* translators: %s is the human-readable error from the store. */
			__( 'We found an existing license key on this site but couldn\'t activate it automatically: %s', 'leaky-paywall' ),
			Leaky_Paywall_Pro_License::error_message( (string) $failure['error'] )
		);

		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Multi-key skip page. Publishers whose per-extension license fields hold
	 * different keys keep the legacy Settings → Licenses tab as canonical UI —
	 * but some of them later upgrade to an all-access Pro pass, so we offer a
	 * Pro activation card below the explainer. Activating a valid Pro key here
	 * clears the skip flag (via apply_activation) and the page renders normal
	 * Pro UI on the next load.
	 */
	private function render_multi_key_explainer() {
		$legacy_url = admin_url( 'admin.php?page=leaky-paywall-settings&tab=licenses' );

		// A failed attempt to activate a Pro key from this page leaves the same
		// transient as a failed auto-migration; surface it identically and
		// prefill the input so the publisher can correct without re-typing.
		$failure     = get_transient( Leaky_Paywall_Pro_License_Migration::FAIL_TRANSIENT );
		$prefill_key = is_array( $failure ) && ! empty( $failure['key'] ) ? (string) $failure['key'] : '';

		$this->render_header();
		?>
		<div class="wrap">

			<?php $this->render_notices(); ?>
			<?php $this->render_migration_failure( $failure ); ?>

			<div class="lp-license-page">
				<div class="lp-license-card">
					<div class="lp-license-card__header">
						<span class="lp-license-card__name"><?php esc_html_e( 'Multiple license keys detected', 'leaky-paywall' ); ?></span>
					</div>
					<div class="lp-license-card__content">
						<p class="lp-license-card__intro">
							<?php esc_html_e( 'Your site has different license keys configured for individual Pro extensions, so Leaky Paywall is keeping them separate instead of switching to a single Pro key.', 'leaky-paywall' ); ?>
						</p>
						<p>
							<a href="<?php echo esc_url( $legacy_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'Manage keys in Settings → Licenses', 'leaky-paywall' ); ?>
							</a>
						</p>
					</div>
				</div>

				<div class="lp-license-card">
					<div class="lp-license-card__header">
						<span class="lp-license-card__name"><?php esc_html_e( 'Have an all-access Pro key?', 'leaky-paywall' ); ?></span>
					</div>
					<div class="lp-license-card__content">
						<p class="lp-license-card__intro">
							<?php esc_html_e( 'Enter your all-access key from your leakypaywall.com account. Activating it here will switch this site to the single Pro flow — the per-extension fields stay visible until those extensions ship Pro-aware updates, but you won\'t need to fill them in.', 'leaky-paywall' ); ?>
						</p>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'leaky_paywall_pro_license', 'leaky_paywall_pro_license_nonce' ); ?>

						<div class="lp-license-card__body">
							<label for="leaky_paywall_pro_license_key" class="screen-reader-text"><?php esc_html_e( 'License Key', 'leaky-paywall' ); ?></label>
							<input
								type="text"
								id="leaky_paywall_pro_license_key"
								name="leaky_paywall_pro_license_key"
								class="regular-text"
								value="<?php echo esc_attr( $prefill_key ); ?>"
							/>
							<input type="hidden" name="leaky_paywall_pro_license_action" value="activate" />
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'leaky-paywall' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
