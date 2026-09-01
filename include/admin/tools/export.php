<?php

/**
 * Leaky Paywall Export handler.
 *
 * Ported from the Leaky Paywall Reporting Tool add-on plugin.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Leaky_Paywall_Export {

	public function __construct() {
		add_action( 'wp_ajax_leaky_paywall_reporting_tool_process', array( $this, 'process_requests' ) );
		add_action( 'admin_post_leaky_paywall_download_export', array( $this, 'download_export' ) );
	}

	/**
	 * AJAX handler for batch export requests.
	 */
	public function process_requests() {

		$form_data = isset( $_POST['formData'] ) ? htmlspecialchars_decode( wp_kses_post( wp_unslash( $_POST['formData'] ) ) ) : '';
		parse_str( $form_data, $fields );

		if ( ! isset( $fields['leaky_paywall_reporting_tool_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $fields['leaky_paywall_reporting_tool_nonce'], 'submit_leaky_paywall_reporting_tool' ) ) {
			return;
		}

		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		$step = sanitize_text_field( $_POST['step'] );

		if ( 'done' === $step ) {
			wp_send_json( array( 'step' => 'done' ) );
		}

		if ( 1 == $step ) {
			$token = self::create_token();
		} else {
			$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

			if ( ! self::token_is_valid( $token ) ) {
				wp_send_json( array( 'error' => __( 'Your export session has expired. Please start the export again.', 'leaky-paywall' ) ) );
			}
		}

		$users = $this->reporting_tool_query( $fields, $step );

		$meta = array(
			'level_id',
			'hash',
			'subscriber_id',
			'price',
			'description',
			'plan',
			'created',
			'expires',
			'payment_gateway',
			'payment_status',
		);

		$meta = apply_filters( 'leaky_paywall_reporting_tool_meta', $meta );

		$custom_meta_fields = array();
		if ( is_plugin_active( 'leaky-paywall-custom-subscriber-fields/issuem-leaky-paywall-subscriber-meta.php' ) ) {
			global $dl_pluginissuem_leaky_paywall_subscriber_meta;
			$custom_meta_fields = $dl_pluginissuem_leaky_paywall_subscriber_meta->get_settings();
		}

		if ( ! empty( $users ) ) {

			$user_meta = array();

			foreach ( $users as $user ) {
				$user_meta[ $user->ID ]['user_id']    = $user->ID;
				$user_meta[ $user->ID ]['user_login']  = $user->data->user_login;
				$user_meta[ $user->ID ]['user_email']  = $user->data->user_email;
				$user_meta[ $user->ID ]['first_name']  = $user->first_name;
				$user_meta[ $user->ID ]['last_name']   = $user->last_name;

				foreach ( $meta as $key ) {
					$user_meta[ $user->ID ][ $key ] = lp_get_subscriber_meta( $key, $user );
				}

				if ( leaky_paywall_user_has_access( $user ) ) {
					$user_meta[ $user->ID ]['has_access'] = 'yes';
				} else {
					$user_meta[ $user->ID ]['has_access'] = 'no';
				}

				if ( ! empty( $custom_meta_fields['meta_keys'] ) ) {
					$mode = leaky_paywall_get_current_mode();
					$site = leaky_paywall_get_current_site();

					foreach ( $custom_meta_fields['meta_keys'] as $meta_key ) {
						$user_meta[ $user->ID ][ $meta_key['name'] ] = get_user_meta(
							$user->ID,
							'_issuem_leaky_paywall_' . $mode . '_subscriber_meta_' . sanitize_title_with_dashes( $meta_key['name'] ) . $site,
							true
						);
					}
				}

				$user_meta = apply_filters( 'leaky_paywall_reporting_tool_user_meta', $user_meta, $user->ID );
			}

			if ( ! empty( $user_meta ) ) {
				$this->export_file( $user_meta, $step, $token );
			}
		} else {

			if ( 1 == $step ) {
				self::delete_token();

				$response = array(
					'step' => 'done',
					'url'  => 'none',
				);
			} else {
				// The file is never given a public URL. This points at an
				// admin-post handler that checks capability and nonce, then
				// streams it.
				$response = array(
					'step' => 'done',
					'url'  => add_query_arg(
						array(
							'action'   => 'leaky_paywall_download_export',
							'token'    => $token,
							'_wpnonce' => wp_create_nonce( 'leaky_paywall_download_export' ),
						),
						admin_url( 'admin-post.php' )
					),
				);
			}

			wp_send_json( $response );
		}
	}

	/**
	 * Directory the export files are written to.
	 *
	 * Closed to direct requests, and separate from uploads/leaky-paywall, which
	 * older versions wrote publicly readable exports into.
	 *
	 * @return string
	 */
	public static function get_export_dir() {
		return leaky_paywall_get_protected_dir( 'leaky-paywall-exports' );
	}

	/**
	 * Start a new export and remember its token for the current user.
	 *
	 * @return string
	 */
	private static function create_token() {

		$token = bin2hex( random_bytes( 16 ) );

		set_transient( 'leaky_paywall_export_' . get_current_user_id(), $token, HOUR_IN_SECONDS );

		return $token;
	}

	/**
	 * Whether a token belongs to the current user's in-flight export.
	 *
	 * @param string $token The token to check.
	 * @return bool
	 */
	private static function token_is_valid( $token ) {

		if ( ! $token || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return false;
		}

		$stored = get_transient( 'leaky_paywall_export_' . get_current_user_id() );

		return is_string( $stored ) && hash_equals( $stored, $token );
	}

	/**
	 * Forget the current user's export token.
	 *
	 * @return void
	 */
	private static function delete_token() {
		delete_transient( 'leaky_paywall_export_' . get_current_user_id() );
	}

	/**
	 * Full path of the export file for a token.
	 *
	 * @param string $token The export token.
	 * @return string
	 */
	public static function get_export_path( $token ) {
		return self::get_export_dir() . 'leaky-paywall-report-' . $token . '.csv';
	}

	/**
	 * Stream a finished export to the admin who requested it.
	 *
	 * @return void
	 */
	public function download_export() {

		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to download subscriber exports.', 'leaky-paywall' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'leaky_paywall_download_export' );

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( ! self::token_is_valid( $token ) ) {
			wp_die( esc_html__( 'This export link is no longer valid. Please run the export again.', 'leaky-paywall' ), '', array( 'response' => 403 ) );
		}

		$file = self::get_export_path( $token );

		if ( ! file_exists( $file ) ) {
			wp_die( esc_html__( 'The export file could not be found. Please run the export again.', 'leaky-paywall' ), '', array( 'response' => 404 ) );
		}

		$size = filesize( $file );

		// Content-Length has to match what actually reaches the browser. With
		// output compression on, or another plugin's buffer in the way, it does
		// not, and the browser truncates the file. The export would then be
		// deleted below as though it had been delivered in full.
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore
		}

		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="leaky-paywall-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'Content-Length: ' . $size );

		$sent = readfile( $file );

		// Only discard it once the whole file went out. A truncated stream
		// leaves the export in place so the admin can retry without re-running
		// the query.
		if ( $sent === $size ) {
			@unlink( $file );
			self::delete_token();
		}

		exit;
	}

	/**
	 * Write user data to CSV file in batches.
	 *
	 * @param array $content_array User data to write.
	 * @param int   $step          Current batch step.
	 * @param int   $rand          Random number for filename uniqueness.
	 */
	public function export_file( $content_array, $step, $token ) {

		self::get_export_dir();

		$filename = self::get_export_path( $token );
		$f        = fopen( $filename, 1 == $step ? 'w' : 'a' );

		if ( ! $f ) {
			wp_send_json( array( 'error' => __( 'The export file could not be written. Please check that your uploads directory is writable.', 'leaky-paywall' ) ) );
		}

		if ( 1 == $step ) {
			fputcsv( $f, array_keys( reset( $content_array ) ) );
		}

		foreach ( $content_array as $row ) {
			$row = array_map(
				function ( $value ) {
					if ( is_string( $value ) && preg_match( '/^0\d+$/', $value ) ) {
						return '="' . $value . '"';
					}
					return $value;
				},
				$row
			);
			fputcsv( $f, $row );
		}

		fclose( $f );

		wp_send_json(
			array(
				'step'  => $step + 1,
				'token' => $token,
			)
		);
	}

	/**
	 * Query users matching the export filter criteria.
	 *
	 * @param array $fields Form filter fields.
	 * @param int   $step   Current batch step.
	 * @return array|false Array of WP_User objects or false.
	 */
	public function reporting_tool_query( $fields, $step ) {

		if ( empty( $fields ) ) {
			return false;
		}

		$args = array(
			'role__not_in' => 'administrator',
			'number'       => 1000,
			'offset'       => ( (int) $step - 1 ) * 1000,
		);

		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

		if ( ! empty( $fields['expire_start'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_expires' . $site,
				'value'   => gmdate( 'Y-m-d 23:59:59', strtotime( $fields['expire_start'] ) ),
				'type'    => 'DATE',
				'compare' => '>=',
			);
		}

		if ( ! empty( $fields['expire_end'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_expires' . $site,
				'value'   => gmdate( 'Y-m-d 23:59:59', strtotime( $fields['expire_end'] ) ),
				'type'    => 'DATE',
				'compare' => '<=',
			);
		}

		if ( ! empty( $fields['created_start'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_created' . $site,
				'value'   => gmdate( 'Y-m-d 23:59:59', strtotime( $fields['created_start'] ) ),
				'type'    => 'DATE',
				'compare' => '>=',
			);
		}

		if ( ! empty( $fields['created_end'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_created' . $site,
				'value'   => gmdate( 'Y-m-d 23:59:59', strtotime( $fields['created_end'] ) ),
				'type'    => 'DATE',
				'compare' => '<=',
			);
		}

		if ( ! empty( $fields['subscription_level'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'value'   => $fields['subscription_level'],
				'type'    => 'NUMERIC',
				'compare' => 'IN',
			);
		} else {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'compare' => 'EXISTS',
			);
		}

		if ( ! empty( $fields['subscriber_status'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
				'value'   => $fields['subscriber_status'],
				'type'    => 'CHAR',
				'compare' => 'IN',
			);
		}

		if ( ! empty( $fields['price'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_issuem_leaky_paywall_' . $mode . '_price' . $site,
				'value' => $fields['price'],
			);
		}

		if ( ! empty( $fields['payment_method'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site,
				'value'   => $fields['payment_method'],
				'type'    => 'CHAR',
				'compare' => 'IN',
			);
		}

		if ( ! empty( $fields['subscriber_id'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'value'   => $fields['subscriber_id'],
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $fields['gift_subscriptions'] ) && $fields['gift_subscriptions'] > 0 ) {
			$args['meta_query'][] = array(
				'key'     => '_leaky_paywall_gift_subscription_code',
				'compare' => 'EXISTS',
			);
		}

		if ( ! empty( $fields['custom-meta-key'] ) ) {
			foreach ( $fields['custom-meta-key'] as $meta_key => $value ) {
				if ( ! empty( $meta_key ) && ! empty( $value ) ) {
					$args['meta_query'][] = array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_meta_' . $meta_key,
						'value'   => $value,
						'compare' => 'LIKE',
					);
				}
			}
		}

		$args['meta_query']['relation'] = 'AND';
		$args  = apply_filters( 'leaky_paywall_reporting_tool_pre_users', $args, $mode, '_issuem' );
		$users = get_users( $args );

		return $users;
	}
}

// Only instantiate if the old Reporting Tool plugin is not active.
if ( ! is_plugin_active( 'leaky-paywall-reporting-tool/leaky-paywall-reporting-tool.php' ) ) {
	new Leaky_Paywall_Export();
}

/**
 * How long a finished export is kept before the daily sweep removes it.
 *
 * @return int Seconds.
 */
function leaky_paywall_get_export_retention_period() {

	$hours = (int) apply_filters( 'leaky_paywall_export_retention_hours', 24 );

	return ( $hours > 0 ? $hours : 24 ) * HOUR_IN_SECONDS;
}

/**
 * Delete every subscriber export file.
 *
 * @return int Number of files deleted.
 */
function leaky_paywall_delete_export_files() {

	$files   = glob( Leaky_Paywall_Export::get_export_dir() . 'leaky-paywall-report-*.csv' );
	$deleted = 0;

	if ( ! $files ) {
		return 0;
	}

	foreach ( $files as $file ) {
		if ( @unlink( $file ) ) {
			++$deleted;
		}
	}

	return $deleted;
}

/**
 * Delete subscriber exports that were never downloaded.
 *
 * A completed download removes its own file. This catches the export whose
 * download was abandoned, so a full subscriber list is not left sitting on disk
 * indefinitely.
 *
 * @return int Number of files deleted.
 */
function leaky_paywall_cleanup_subscriber_exports() {

	$files = glob( Leaky_Paywall_Export::get_export_dir() . 'leaky-paywall-report-*.csv' );

	if ( ! $files ) {
		return 0;
	}

	$cutoff  = time() - leaky_paywall_get_export_retention_period();
	$deleted = 0;

	foreach ( $files as $file ) {
		if ( filemtime( $file ) > $cutoff ) {
			continue;
		}

		if ( @unlink( $file ) ) {
			++$deleted;
		}
	}

	return $deleted;
}
add_action( 'leaky_paywall_cleanup_exports', 'leaky_paywall_cleanup_subscriber_exports' );

/**
 * Schedule the daily export sweep.
 *
 * @return void
 */
function leaky_paywall_register_export_cleanup() {

	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return;
	}

	if ( as_has_scheduled_action( 'leaky_paywall_cleanup_exports' ) ) {
		return;
	}

	as_schedule_recurring_action(
		time() + HOUR_IN_SECONDS,
		DAY_IN_SECONDS,
		'leaky_paywall_cleanup_exports',
		array(),
		'leaky-paywall'
	);
}
add_action( 'init', 'leaky_paywall_register_export_cleanup' );
