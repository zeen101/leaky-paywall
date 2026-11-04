<?php
/**
 * HTTP client for the leakypaywall.com store API (the leaky-paywall-store plugin).
 *
 * Centralizes the base URL and request plumbing for the catalog and license
 * endpoints so the rest of LP core never builds these requests inline. Downloading
 * extensions lives in the Leaky Paywall Pro plugin, not here.
 *
 * @package Leaky Paywall
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Store_Client {

	/**
	 * Base URL of the store site. Filterable so it can be pointed at a
	 * staging or local environment during testing.
	 */
	public static function base_url() {
		return apply_filters( 'leaky_paywall_store_url', 'https://leakypaywall.com' );
	}

	private static function endpoint( $path ) {
		return trailingslashit( self::base_url() ) . 'wp-json/leaky-paywall-store/v1/' . ltrim( $path, '/' );
	}

	private static function site_url() {
		return home_url();
	}

	/**
	 * GET /catalog — list of extensions. Returns the decoded `extensions`
	 * array on success, or WP_Error on failure.
	 *
	 * @return array|WP_Error
	 */
	public static function get_catalog() {
		$response = wp_remote_get( self::endpoint( 'catalog' ), array(
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || ! is_array( $body ) || ! isset( $body['extensions'] ) ) {
			return new WP_Error( 'lp_store_catalog_failed', __( 'Could not load the extension catalog.', 'leaky-paywall' ) );
		}

		return $body['extensions'];
	}

	/**
	 * POST /activate.
	 *
	 * @return array|WP_Error Decoded EDD SL activation result.
	 */
	public static function activate( $license_key ) {
		return self::post( 'activate', array(
			'license'  => $license_key,
			'site_url' => self::site_url(),
		) );
	}

	/**
	 * POST /deactivate.
	 *
	 * @return array|WP_Error
	 */
	public static function deactivate( $license_key ) {
		return self::post( 'deactivate', array(
			'license'  => $license_key,
			'site_url' => self::site_url(),
		) );
	}

	/**
	 * POST /validate.
	 *
	 * @return array|WP_Error Decoded { status, expires?, is_lifetime? }.
	 */
	public static function validate( $license_key ) {
		return self::post( 'validate', array(
			'license'  => $license_key,
			'site_url' => self::site_url(),
		) );
	}

	/**
	 * Shared POST helper. Returns the decoded JSON array, or WP_Error.
	 * Non-2xx responses are still decoded and returned so callers can read
	 * the status string EDD SL provides (e.g. "expired", "site_inactive").
	 *
	 * @return array|WP_Error
	 */
	private static function post( $path, array $body ) {
		$response = wp_remote_post( self::endpoint( $path ), array(
			'timeout' => 20,
			'body'    => $body,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'lp_store_bad_response', __( 'Unexpected response from the store.', 'leaky-paywall' ) );
		}

		return $decoded;
	}
}
