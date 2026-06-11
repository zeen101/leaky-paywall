<?php
/**
 * HTTP client for the leakypaywall.com store API (the leaky-paywall-store plugin).
 *
 * Centralizes the base URL and request plumbing for the catalog, license, and
 * download endpoints so the rest of LP core never builds these requests inline.
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
	 * POST /download — saves the streamed ZIP to a temp file.
	 *
	 * @param string $license_key
	 * @param string $slug Extension plugin slug.
	 * @return string|WP_Error Absolute path to the downloaded temp file.
	 */
	public static function download_to_temp( $license_key, $slug ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = wp_tempnam( 'lp-ext-' . $slug . '.zip' );
		if ( ! $tmp ) {
			return new WP_Error( 'lp_store_tmp_failed', __( 'Could not create a temporary file for the download.', 'leaky-paywall' ) );
		}

		$response = wp_remote_post( self::endpoint( 'download' ), array(
			'timeout'  => 60,
			'stream'   => true,
			'filename' => $tmp,
			'body'     => array(
				'license'  => $license_key,
				'site_url' => self::site_url(),
				'slug'     => $slug,
			),
		) );

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			// On error the body is JSON, not a ZIP — read it back for the message.
			$message  = __( 'The download could not be authorized.', 'leaky-paywall' );
			$contents = @file_get_contents( $tmp );
			$decoded  = $contents ? json_decode( $contents, true ) : null;

			if ( is_array( $decoded ) ) {
				// When the store returns access_denied, prefer a friendly mapping
				// of the EDD All Access failure_id over the raw 'access_denied'
				// string the publisher would otherwise see.
				$store_message = ! empty( $decoded['message'] ) ? (string) $decoded['message'] : '';
				$failure_id    = ! empty( $decoded['failure_id'] ) ? (string) $decoded['failure_id'] : '';

				if ( 'access_denied' === $store_message && '' !== $failure_id ) {
					$message = self::friendly_message_for_failure_id( $failure_id );
				} elseif ( '' !== $store_message ) {
					$message = $store_message;
				}
			}

			@unlink( $tmp );
			return new WP_Error( 'lp_store_download_failed_' . $code, $message );
		}

		return $tmp;
	}

	/**
	 * Map a known EDD All Access failure_id to a publisher-friendly message.
	 * Unknown failure_ids fall back to "Download authorization failed (<id>)"
	 * so a support report still includes the raw code.
	 *
	 * @param string $failure_id
	 * @return string
	 */
	private static function friendly_message_for_failure_id( $failure_id ) {

		$map = array(
			'no_pass_for_customer' => __( 'Your Leaky Paywall license does not include access to this extension. Please contact support to upgrade your license.', 'leaky-paywall' ),
			'customer_does_not_have_active_passes' => __( 'Your Leaky Paywall subscription is not currently active. Please renew it to continue receiving updates.', 'leaky-paywall' ),
			'download_not_in_passes_active_categories' => __( 'Your Leaky Paywall license does not grant access to this extension. Please contact support.', 'leaky-paywall' ),
			'download_limit_reached' => __( 'You have reached the download limit for today. Please try again tomorrow.', 'leaky-paywall' ),
			'all_access_pass_expired' => __( 'Your Leaky Paywall subscription has expired. Please renew it to continue receiving extension updates.', 'leaky-paywall' ),
			'all_access_pass_canceled' => __( 'Your Leaky Paywall subscription has been canceled. Please contact support to restore extension updates.', 'leaky-paywall' ),
			'all_access_pass_inactive' => __( 'Your Leaky Paywall subscription is currently inactive. Please contact support.', 'leaky-paywall' ),
			'all_access_not_available' => __( 'The Leaky Paywall store is misconfigured. Please contact support.', 'leaky-paywall' ),
			'download_post_status_invalid' => __( 'This extension is not currently available for download. Please contact support.', 'leaky-paywall' ),
		);

		if ( isset( $map[ $failure_id ] ) ) {
			return $map[ $failure_id ];
		}

		/* translators: %s: short code identifying the underlying reason. */
		return sprintf( __( 'Download authorization failed (%s). Please contact support.', 'leaky-paywall' ), $failure_id );
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
