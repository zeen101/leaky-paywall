<?php
/**
 * Update Pro extensions from the Plugins page with the Leaky Paywall Pro license.
 *
 * Each extension ships its own updater, which asks the store for the update
 * with that extension's own license key. A site on the single Pro license
 * often has no such key, or an old one, so the Plugins page showed
 * "Automatic update is unavailable for this plugin" (no key) or "Update
 * failed: Download failed. Unauthorized" (a key the store no longer accepts),
 * while the same update worked from Leaky Paywall > Extensions, which
 * downloads with the Pro license.
 *
 * With Pro active, an extension in the store catalog is downloaded the way
 * the Extensions page does it. The extension's updater still decides whether
 * there is an update; only where the zip comes from changes.
 *
 * @package Leaky Paywall
 * @since 5.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Pro_Updates {

	/**
	 * Stands in for the download URL. The license key never goes into the
	 * update data WordPress stores.
	 */
	const PACKAGE_PREFIX = 'leaky-paywall-pro://';

	/**
	 * The package each extension's own updater offered, by slug, so a Pro
	 * download that fails can still fall back to it.
	 *
	 * @var array
	 */
	private $original_packages = array();

	public function __construct() {
		// Late, after anything else that adjusts the update data on read.
		add_filter( 'site_transient_update_plugins', array( $this, 'use_pro_packages' ), 9999 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_pro_package' ), 10, 4 );
	}

	/**
	 * Point each Pro extension's update at the Pro download.
	 *
	 * @param mixed $transient The update_plugins site transient.
	 * @return mixed
	 */
	public function use_pro_packages( $transient ) {

		if ( ! is_object( $transient ) || empty( $transient->response ) || ! is_array( $transient->response ) ) {
			return $transient;
		}

		if ( ! function_exists( 'leaky_paywall_pro_is_active' ) || ! leaky_paywall_pro_is_active() ) {
			return $transient;
		}

		$slugs = $this->catalog_slugs();

		if ( empty( $slugs ) ) {
			return $transient;
		}

		foreach ( $transient->response as $plugin_file => $update ) {

			$slug = dirname( $plugin_file );

			if ( ! is_object( $update ) || ! isset( $slugs[ $slug ] ) ) {
				continue;
			}

			$package = isset( $update->package ) ? (string) $update->package : '';

			if ( 0 === strpos( $package, self::PACKAGE_PREFIX ) ) {
				continue;
			}

			$this->original_packages[ $slug ] = $package;

			// The response object is shared with WordPress' cached copy, so
			// work on a clone rather than change it in place.
			$update          = clone $update;
			$update->package = self::PACKAGE_PREFIX . $slug;

			$transient->response[ $plugin_file ] = $update;
		}

		return $transient;
	}

	/**
	 * Download a Pro extension's update with the Pro license.
	 *
	 * @param bool|WP_Error|string $reply      False to let WordPress download it.
	 * @param string               $package    The package being downloaded.
	 * @param WP_Upgrader          $upgrader   The upgrader.
	 * @param array                $hook_extra Extra arguments.
	 * @return bool|WP_Error|string Path to the downloaded zip, an error, or the reply unchanged.
	 */
	public function download_pro_package( $reply, $package, $upgrader, $hook_extra = array() ) {

		if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, self::PACKAGE_PREFIX ) ) {
			return $reply;
		}

		$slug = sanitize_title( substr( $package, strlen( self::PACKAGE_PREFIX ) ) );

		if ( is_object( $upgrader ) && isset( $upgrader->skin ) && is_object( $upgrader->skin ) ) {
			$upgrader->skin->feedback( __( 'Downloading the update with your Leaky Paywall Pro license&#8230;', 'leaky-paywall' ) );
		}

		$file = leaky_paywall_pro_is_active()
			? Leaky_Paywall_Store_Client::download_to_temp( leaky_paywall_get_pro_license_key(), $slug )
			: new WP_Error( 'lp_pro_inactive', __( 'Leaky Paywall Pro is not active on this site.', 'leaky-paywall' ) );

		if ( ! is_wp_error( $file ) ) {
			return $file;
		}

		leaky_paywall_log_error( $slug . ': ' . $file->get_error_message(), 'pro updates - download with the Pro license failed' );

		// The extension's own key may still be good for it.
		$original = isset( $this->original_packages[ $slug ] ) ? $this->original_packages[ $slug ] : '';

		if ( $original && is_object( $upgrader ) ) {
			return $upgrader->download_package( $original, false, $hook_extra );
		}

		return $file;
	}

	/**
	 * Slugs of the extensions the store catalog lists, as keys.
	 *
	 * Uses the Extensions page's cached copy, so this costs a store request at
	 * most every twelve hours.
	 *
	 * @return array
	 */
	private function catalog_slugs() {

		$page = isset( $GLOBALS['leaky_paywall_extensions_page'] ) ? $GLOBALS['leaky_paywall_extensions_page'] : null;

		if ( ! $page || ! method_exists( $page, 'get_catalog' ) ) {
			return array();
		}

		$slugs = array();

		foreach ( (array) $page->get_catalog() as $extension ) {
			if ( ! empty( $extension['slug'] ) ) {
				$slugs[ sanitize_title( $extension['slug'] ) ] = true;
			}
		}

		return $slugs;
	}
}

$GLOBALS['leaky_paywall_pro_updates'] = new Leaky_Paywall_Pro_Updates();
