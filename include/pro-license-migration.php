<?php
/**
 * One-time migration from per-extension license keys to the single Pro license.
 *
 * Historical context: each Pro extension stored its own license key in a WP
 * option named after its slug ({ license_key, license_status }), driven by
 * Leaky_Paywall_License_Key. Publishers with an all-access pass pasted the
 * same key into every field. The Pro flow ([[pro-license.php]]) replaces all
 * of that with one option, leaky_paywall_pro_license.
 *
 * This runner detects the common case (one distinct key across all per-extension
 * options), validates it against the store, and writes the new option so the
 * publisher's License page comes up Active on first load after the upgrade.
 *
 * Edge cases handled:
 *   - Manual activation already done before migration runs → mark sentinel, exit.
 *   - Zero legacy keys → mark sentinel, exit silently.
 *   - Multiple distinct keys → record skipped='multi_key' and do not write a Pro
 *     license; the License page renders an explanation pointing at Settings →
 *     Licenses (the legacy tab remains the canonical UI for that publisher).
 *   - Store activation fails with a definitive error (expired, disabled, etc.)
 *     → mark sentinel + stash the failure for the License page to surface.
 *   - Store activation fails on network error → leave sentinel unset; retry on
 *     the next page load.
 *
 * Legacy options are NOT deleted by this migration. Existing extension plugins
 * still ship with EDD updaters wired to per-extension keys; clobbering those
 * options would break their update channel until each extension ships a Pro-
 * aware release. Cleanup is a separate, later task.
 *
 * @package Leaky Paywall
 * @since 5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Pro_License_Migration {

	const SENTINEL        = 'leaky_paywall_pro_license_migrated';
	const SKIPPED         = 'leaky_paywall_pro_license_migration_skipped';
	const FAIL_TRANSIENT  = 'leaky_paywall_pro_license_migration_failed';
	const SUCCESS_NOTICE  = 'leaky_paywall_pro_license_migration_success';

	public function __construct() {
		// admin_init runs after plugins_loaded, by which point every extension's
		// Leaky_Paywall_License_Key constructor has registered its slug. The DB
		// scan in discover_legacy_keys() catches anything that didn't.
		add_action( 'admin_init', array( $this, 'maybe_run' ) );

		// When a Pro license is active, point publishers at the new License page
		// from the legacy Settings → Licenses tab. The per-extension cards still
		// render below (extensions hook in independently) until extensions ship
		// Pro-aware versions; this notice steers ongoing management to one place.
		add_action( 'leaky_paywall_before_licenses_settings', array( $this, 'render_legacy_tab_notice' ) );

		// One-time global admin notice after a successful migration — surfaces
		// the new License page from wherever the publisher happens to be when
		// they next load wp-admin after the LP upgrade.
		add_action( 'admin_notices', array( $this, 'render_post_migration_notice' ) );
	}

	public function render_post_migration_notice() {
		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		if ( ! get_transient( self::SUCCESS_NOTICE ) ) {
			return;
		}
		delete_transient( self::SUCCESS_NOTICE );

		$license_page_url = admin_url( 'admin.php?page=leaky-paywall-license' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Leaky Paywall Pro is activated.', 'leaky-paywall' ); ?></strong>
				<?php esc_html_e( 'Your existing license key has been migrated to the new single-key flow — no action needed.', 'leaky-paywall' ); ?>
				<a href="<?php echo esc_url( $license_page_url ); ?>"><?php esc_html_e( 'Manage your license →', 'leaky-paywall' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function render_legacy_tab_notice() {
		if ( ! Leaky_Paywall_Pro_License::is_active() ) {
			return;
		}

		$license_page_url = admin_url( 'admin.php?page=leaky-paywall-license' );
		?>
		<div class="notice notice-info inline" style="margin: 0 0 16px;">
			<p>
				<?php esc_html_e( 'Leaky Paywall Pro is active. Your license is managed from one place now.', 'leaky-paywall' ); ?>
				<a href="<?php echo esc_url( $license_page_url ); ?>"><?php esc_html_e( 'Go to the License page →', 'leaky-paywall' ); ?></a>
			</p>
			<p class="description" style="margin-bottom:0;">
				<?php esc_html_e( 'The per-extension fields below are still shown for compatibility with extensions that haven\'t adopted the new license flow yet. You don\'t need to enter your Pro key in each one.', 'leaky-paywall' ); ?>
			</p>
		</div>
		<?php
	}

	public function maybe_run() {
		// Already done — most page loads end here.
		if ( get_option( self::SENTINEL ) ) {
			return;
		}

		// Manual activation beat us to it.
		if ( Leaky_Paywall_Pro_License::is_active() ) {
			update_option( self::SENTINEL, true );
			return;
		}

		$legacy   = $this->discover_legacy_keys();
		$distinct = $this->distinct_valid_keys( $legacy );

		if ( empty( $distinct ) ) {
			// Either zero legacy options or all of them empty. Nothing to do, but
			// mark complete so we don't re-scan on every page load.
			update_option( self::SENTINEL, true );
			return;
		}

		if ( count( $distinct ) > 1 ) {
			// Per the locked design: don't migrate, don't show the Pro UI. The
			// License page reads this option and renders an explanatory message
			// pointing at the legacy tab.
			update_option( self::SKIPPED, 'multi_key' );
			update_option( self::SENTINEL, true );
			return;
		}

		$this->migrate_single_key( reset( $distinct ) );
	}

	/**
	 * Run the activation + persist via the Pro license helper and stash a notice
	 * for the publisher on next page load.
	 *
	 * @param string $key
	 */
	private function migrate_single_key( $key ) {
		$result = Leaky_Paywall_Pro_License::apply_activation( $key );

		if ( $result['ok'] ) {
			update_option( self::SENTINEL, true );
			set_transient( self::SUCCESS_NOTICE, true, DAY_IN_SECONDS );
			return;
		}

		if ( 'network' === $result['error'] ) {
			// Transient failure — try again next page load. No sentinel write.
			leaky_paywall_log(
				isset( $result['message'] ) ? $result['message'] : 'unknown network error',
				'pro license migration - network error'
			);
			return;
		}

		// Definitive failure (expired, disabled, missing, no_activations_left, ...).
		// Mark sentinel so we don't loop, but record the failure so the License
		// page can surface it with the key prefilled for the publisher to retry.
		update_option( self::SENTINEL, true );
		set_transient( self::FAIL_TRANSIENT, array(
			'key'   => $key,
			'error' => $result['error'],
		), DAY_IN_SECONDS );
		leaky_paywall_log(
			sprintf( 'Auto-activation failed: %s', $result['error'] ),
			'pro license migration'
		);
	}

	/**
	 * Find every option that looks like a per-extension license record.
	 *
	 * Two strategies, merged: registered slugs (primary, depends on extensions
	 * being currently active) plus a DB scan (catches deactivated extensions
	 * whose options still hold a valid key).
	 *
	 * @return array<string, array{key: string, status: string}> Keyed by slug.
	 */
	private function discover_legacy_keys() {
		$keys = array();

		if ( class_exists( 'Leaky_Paywall_License_Key' ) ) {
			foreach ( Leaky_Paywall_License_Key::get_registered_slugs() as $slug ) {
				$option = get_option( $slug );
				if ( ! is_array( $option ) || empty( $option['license_key'] ) ) {
					continue;
				}
				$keys[ $slug ] = array(
					'key'    => (string) $option['license_key'],
					'status' => isset( $option['license_status'] ) ? (string) $option['license_status'] : '',
				);
			}
		}

		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT option_name, option_value
			 FROM {$wpdb->options}
			 WHERE option_name LIKE 'leaky-paywall-%'
			 AND option_value LIKE '%license_key%'"
		);

		foreach ( (array) $rows as $row ) {
			if ( isset( $keys[ $row->option_name ] ) ) {
				continue;
			}
			$option = maybe_unserialize( $row->option_value );
			if ( ! is_array( $option ) || empty( $option['license_key'] ) ) {
				continue;
			}
			$keys[ $row->option_name ] = array(
				'key'    => (string) $option['license_key'],
				'status' => isset( $option['license_status'] ) ? (string) $option['license_status'] : '',
			);
		}

		return $keys;
	}

	/**
	 * Reduce the discovered records to the set of distinct non-empty keys,
	 * preferring entries currently flagged 'valid' if any exist. If none are
	 * 'valid' (e.g. the publisher's local cache is stale but the keys are good
	 * upstream), fall back to all non-empty keys and let the store have the
	 * final word.
	 *
	 * @param array<string, array{key: string, status: string}> $legacy
	 * @return string[] Distinct keys.
	 */
	private function distinct_valid_keys( array $legacy ) {
		if ( empty( $legacy ) ) {
			return array();
		}

		$collect = function ( array $entries ) {
			$distinct = array();
			foreach ( $entries as $entry ) {
				$k = trim( $entry['key'] );
				if ( '' === $k ) {
					continue;
				}
				$distinct[ $k ] = $k;
			}
			return array_values( $distinct );
		};

		$valid_only = array_filter( $legacy, function ( $entry ) {
			return 'valid' === $entry['status'];
		} );

		$distinct = $collect( $valid_only );
		if ( ! empty( $distinct ) ) {
			return $distinct;
		}

		return $collect( $legacy );
	}
}

new Leaky_Paywall_Pro_License_Migration();
