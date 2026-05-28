<?php
/**
 * Leaky Paywall Pro license — single all-access key.
 *
 * One key, stored in the `leaky_paywall_pro_license` option, that unlocks the
 * entire extension catalog. Activation/validation are delegated to the
 * leakypaywall.com store API (which itself wraps EDD All Access).
 *
 * Existing per-extension keys (Leaky_Paywall_License_Key) continue to work
 * independently; this is additive.
 *
 * @package Leaky Paywall
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Pro_License {

	const OPTION_KEY = 'leaky_paywall_pro_license';
	const CRON_HOOK  = 'leaky_paywall_validate_pro_license';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_form' ) );
		add_action( self::CRON_HOOK, array( $this, 'cron_validate' ) );
	}

	/**
	 * @return array { key: string, status: string, expires: string, is_lifetime: bool, last_checked: int }
	 */
	public static function get() {
		$defaults = array(
			'key'          => '',
			'status'       => '',
			'expires'      => '',
			'is_lifetime'  => false,
			'last_checked' => 0,
		);
		return wp_parse_args( (array) get_option( self::OPTION_KEY, array() ), $defaults );
	}

	private static function save( array $data ) {
		update_option( self::OPTION_KEY, $data );
	}

	/**
	 * True when the stored Pro license is currently valid.
	 */
	public static function is_active() {
		$license = self::get();
		return 'valid' === $license['status'];
	}

	public static function get_key() {
		$license = self::get();
		return $license['key'];
	}

	/**
	 * Handle the activate/deactivate form submission on the License page.
	 */
	public function handle_form() {
		if ( ! isset( $_POST['leaky_paywall_pro_license_action'] ) ) {
			return;
		}

		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		if ( ! isset( $_POST['leaky_paywall_pro_license_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['leaky_paywall_pro_license_nonce'] ) ), 'leaky_paywall_pro_license' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['leaky_paywall_pro_license_action'] ) );

		if ( 'activate' === $action ) {
			$key = isset( $_POST['leaky_paywall_pro_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky_paywall_pro_license_key'] ) ) : '';
			$this->activate( $key );
		} elseif ( 'deactivate' === $action ) {
			$this->deactivate();
		}
	}

	/**
	 * Activate a Pro license key against this site.
	 */
	public function activate( $key ) {
		$key = trim( $key );

		if ( empty( $key ) ) {
			$this->add_notice( 'error', __( 'Please enter a license key.', 'leaky-paywall' ) );
			return;
		}

		$result = Leaky_Paywall_Store_Client::activate( $key );

		if ( is_wp_error( $result ) ) {
			$this->add_notice( 'error', $result->get_error_message() );
			return;
		}

		if ( empty( $result['success'] ) ) {
			$error = isset( $result['error'] ) ? (string) $result['error'] : 'unknown';
			$this->add_notice( 'error', $this->error_message( $error ) );
			return;
		}

		$this->save( array(
			'key'          => $key,
			'status'       => 'valid',
			'expires'      => isset( $result['expires'] ) ? (string) $result['expires'] : '',
			'is_lifetime'  => isset( $result['expires'] ) && 'lifetime' === $result['expires'],
			'last_checked' => time(),
		) );

		$this->schedule_cron();
		$this->add_notice( 'success', __( 'Your Leaky Paywall Pro license is now active.', 'leaky-paywall' ) );
	}

	/**
	 * Deactivate this site's Pro license.
	 */
	public function deactivate() {
		$license = self::get();

		if ( ! empty( $license['key'] ) ) {
			// Best effort — even if the remote call fails, clear locally so the
			// admin isn't stuck with a key they can't remove.
			Leaky_Paywall_Store_Client::deactivate( $license['key'] );
		}

		$this->save( array(
			'key'          => '',
			'status'       => '',
			'expires'      => '',
			'is_lifetime'  => false,
			'last_checked' => time(),
		) );

		$this->unschedule_cron();
		$this->add_notice( 'success', __( 'Your Leaky Paywall Pro license has been deactivated.', 'leaky-paywall' ) );
	}

	/**
	 * Daily cron: re-check status without changing activation state.
	 */
	public function cron_validate() {
		$license = self::get();

		if ( empty( $license['key'] ) ) {
			return;
		}

		$result = Leaky_Paywall_Store_Client::validate( $license['key'] );

		if ( is_wp_error( $result ) ) {
			// Network hiccup — leave the stored status untouched and try again
			// tomorrow rather than locking the publisher out on a transient error.
			return;
		}

		$status = isset( $result['status'] ) ? (string) $result['status'] : '';

		$license['status']       = $status;
		$license['last_checked'] = time();
		if ( isset( $result['expires'] ) ) {
			$license['expires'] = (string) $result['expires'];
		}
		if ( isset( $result['is_lifetime'] ) ) {
			$license['is_lifetime'] = (bool) $result['is_lifetime'];
		}

		self::save( $license );
	}

	private function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	private function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Map an EDD SL activation error code to a human message.
	 */
	private function error_message( $code ) {
		switch ( $code ) {
			case 'missing':
				return __( 'That license key was not found. Please double-check it.', 'leaky-paywall' );
			case 'expired':
				return __( 'That license key has expired. Please renew it in your leakypaywall.com account.', 'leaky-paywall' );
			case 'disabled':
				return __( 'That license key has been disabled.', 'leaky-paywall' );
			case 'no_activations_left':
				return __( 'That license key has reached its activation limit. Deactivate it on another site first.', 'leaky-paywall' );
			case 'key_mismatch':
			case 'invalid_item_id':
			case 'item_name_mismatch':
				return __( 'That license key is not valid for Leaky Paywall Pro.', 'leaky-paywall' );
			default:
				return __( 'The license could not be activated. Please try again or contact support.', 'leaky-paywall' );
		}
	}

	/**
	 * Stash an admin notice for the next page load.
	 */
	private function add_notice( $type, $message ) {
		// get_transient returns false when the transient doesn't exist, and
		// (array) false is [ false ] — not [] — which would leak an empty
		// notice on render. Initialize to an empty array explicitly.
		$notices = get_transient( 'leaky_paywall_pro_license_notices' );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$notices[] = array( 'type' => $type, 'message' => $message );
		set_transient( 'leaky_paywall_pro_license_notices', $notices, 60 );
	}
}

new Leaky_Paywall_Pro_License();

/**
 * Public helper: is a valid Pro license active?
 *
 * @since 5.2.0
 * @return bool
 */
function leaky_paywall_pro_is_active() {
	return Leaky_Paywall_Pro_License::is_active();
}

/**
 * Public helper: the stored Pro license key (empty string if none).
 *
 * @since 5.2.0
 * @return string
 */
function leaky_paywall_get_pro_license_key() {
	return Leaky_Paywall_Pro_License::get_key();
}
