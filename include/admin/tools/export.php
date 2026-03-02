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
		$rand = absint( $_POST['rand'] );

		if ( 'done' === $step ) {
			wp_send_json( array( 'step' => 'done' ) );
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
				$this->export_file( $user_meta, $step, $rand );
			}
		} else {

			if ( 1 == $step ) {
				$response = array(
					'step' => 'done',
					'url'  => 'none',
				);
			} else {
				$uploads_dir = trailingslashit( wp_upload_dir()['baseurl'] ) . 'leaky-paywall';
				$filename    = str_replace( 'http://', 'https://', $uploads_dir . '/leaky-paywall-report-' . $rand . '-' . wp_hash( home_url( '/' ) ) ) . '.csv';

				$response = array(
					'step' => 'done',
					'url'  => $filename,
				);
			}

			wp_send_json( $response );
		}
	}

	/**
	 * Write user data to CSV file in batches.
	 *
	 * @param array $content_array User data to write.
	 * @param int   $step          Current batch step.
	 * @param int   $rand          Random number for filename uniqueness.
	 */
	public function export_file( $content_array, $step, $rand ) {

		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'leaky-paywall';

		if ( 1 == $step ) {
			if ( ! is_dir( $uploads_dir ) ) {
				wp_mkdir_p( $uploads_dir );
			}

			$filename = $uploads_dir . '/leaky-paywall-report-' . $rand . '-' . wp_hash( home_url( '/' ) ) . '.csv';
			$f        = fopen( $filename, 'w' );
		} else {
			$filename = $uploads_dir . '/leaky-paywall-report-' . $rand . '-' . wp_hash( home_url( '/' ) ) . '.csv';
			$f        = fopen( $filename, 'a' );
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
				'step' => $step + 1,
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
