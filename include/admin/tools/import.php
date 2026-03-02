<?php

/**
 * Leaky Paywall Import handler.
 *
 * Ported from the Leaky Paywall Bulk Import Subscribers add-on plugin.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Leaky_Paywall_Import {

	/**
	 * Process a CSV import submission.
	 *
	 * Called at the top of the Import tab render so result messages
	 * appear before the form.
	 */
	public function process_requests() {

		if ( empty( $_POST['leaky_paywall_bulk_import_user_csv_file'] ) ) {
			return;
		}

		if ( ! isset( $_POST['leaky_paywall_bulk_add_subscribers'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['leaky_paywall_bulk_add_subscribers'] ) ), 'bulk_add_subscribers' )
		) {
			return;
		}

		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		$file_id = absint( $_POST['leaky_paywall_bulk_import_user_csv_file_id'] );

		if ( ! $file_id ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'No file uploaded for bulk upload.', 'leaky-paywall' ) . '</strong></p></div>';
			return;
		}

		$file_path = get_attached_file( $file_id );

		if ( ! $file_path ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'The file path could not be found.', 'leaky-paywall' ) . '</strong></p></div>';
			return;
		}

		// Temporarily allow CSV MIME type through WordPress validation.
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_csv_mime_type' ), 10, 4 );

		$headers = array();
		$manager = new SplFileObject( $file_path );
		$i       = 0;
		$new     = 0;
		$updated = 0;

		while ( ! $manager->eof() ) {

			$row = $manager->fgetcsv();

			if ( 0 === $i ) {
				foreach ( $row as $element ) {
					$clean_element = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $element );
					$headers[]     = strtolower( str_replace( ' ', '_', $clean_element ) );
				}
				$i++;
				continue;
			}

			if ( empty( $headers ) ) {
				break;
			}

			if ( empty( $row[0] ) ) {
				continue;
			}

			if ( count( $headers ) !== count( $row ) ) {
				continue;
			}

			$type = $this->process_row( array_combine( $headers, $row ) );

			if ( 'new' === $type ) {
				$new++;
			} elseif ( 'updated' === $type ) {
				$updated++;
			}
		}

		remove_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_csv_mime_type' ), 10 );

		if ( $new > 0 || $updated > 0 ) {
			echo '<div class="notice notice-success">';
			echo '<p><strong>' . intval( $new ) . esc_html__( ' new subscribers were imported.', 'leaky-paywall' ) . '</strong></p>';
			echo '<p><strong>' . intval( $updated ) . esc_html__( ' existing subscribers were updated.', 'leaky-paywall' ) . '</strong></p>';
			echo '</div>';
		}
	}

	/**
	 * Process a single CSV row.
	 *
	 * @param array $item Associative array of CSV row data.
	 * @return string|void 'new', 'updated', or void on skip.
	 */
	public function process_row( $item ) {

		global $blog_id;

		$email    = isset( $item['email'] ) ? sanitize_text_field( strtolower( $item['email'] ) ) : '';
		$level_id = isset( $item['level_id'] ) ? sanitize_text_field( $item['level_id'] ) : '';

		if ( ! is_email( $email ) ) {
			return;
		}

		// Allow a level_id of 0.
		if ( null === $level_id || '' === $level_id ) {
			return;
		}

		$level = get_leaky_paywall_subscription_level( $level_id );

		if ( ! $level ) {
			return;
		}

		$subscriber_id = isset( $item['subscriber_id'] ) ? sanitize_text_field( $item['subscriber_id'] ) : '';

		$expires = '';

		if ( isset( $item['expires'] ) ) {
			$expires = sanitize_text_field( $item['expires'] );

			if ( 'never' === strtolower( $expires ) ) {
				$expires = '0000-00-00 00:00:00';
			}
		}

		$payment_status = 'active';

		if ( ! empty( $item['payment_status'] ) ) {
			$payment_status = sanitize_text_field( $item['payment_status'] );
		}

		$meta = apply_filters(
			'leaky_paywall_bulk_import_meta',
			array(
				'level_id'        => $level_id,
				'subscriber_id'   => $subscriber_id,
				'price'           => isset( $item['price'] ) ? number_format( (float) str_replace( '$', '', sanitize_text_field( $item['price'] ) ), 2 ) : $level['price'],
				'description'     => isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : $level['label'],
				'created'         => isset( $item['created'] ) ? sanitize_text_field( $item['created'] ) : gmdate( 'Y-m-d H:i:s' ),
				'expires'         => $expires,
				'payment_gateway' => isset( $item['payment_gateway'] ) ? sanitize_text_field( strtolower( $item['payment_gateway'] ) ) : 'manual',
				'payment_status'  => $payment_status,
				'interval'        => isset( $item['interval'] ) ? sanitize_text_field( $item['interval'] ) : '',
				'plan'            => isset( $item['plan'] ) ? sanitize_text_field( $item['plan'] ) : '',
				'site'            => isset( $item['site'] ) ? sanitize_text_field( $item['site'] ) : $blog_id,
				'password'        => isset( $item['password'] ) ? sanitize_text_field( $item['password'] ) : '',
				'login'           => isset( $item['username'] ) ? sanitize_text_field( strtolower( $item['username'] ) ) : $email,
				'first_name'      => isset( $item['first_name'] ) ? sanitize_text_field( $item['first_name'] ) : '',
				'last_name'       => isset( $item['last_name'] ) ? sanitize_text_field( $item['last_name'] ) : '',
				'display_name'    => isset( $item['display_name'] ) ? sanitize_text_field( $item['display_name'] ) : '',
				'recurring'       => isset( $item['recurring'] ) ? sanitize_text_field( $item['recurring'] ) : '',
				'currency'        => isset( $item['currency'] ) ? sanitize_text_field( $item['currency'] ) : leaky_paywall_get_currency(),
			),
			$item
		);

		if ( email_exists( $email ) ) {
			$user_id = $this->update_subscriber( $email, $meta );
			$type    = 'updated';
		} else {
			$user_id = leaky_paywall_new_subscriber( null, $email, $subscriber_id, $meta );
			$type    = 'new';
		}

		if ( ! empty( $user_id ) ) {
			do_action( 'bulk_add_leaky_paywall_subscriber', $user_id, $meta );
		} else {
			do_action( 'bulk_add_leaky_paywall_subscriber_failed', $meta, $email, $meta['login'] );
		}

		return $type;
	}

	/**
	 * Update an existing subscriber with imported meta data.
	 *
	 * @param string $email Subscriber email.
	 * @param array  $meta  Meta data from CSV row.
	 * @return int|void User ID on success.
	 */
	public function update_subscriber( $email, $meta ) {

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		if ( is_multisite_premium() && ! is_main_site( $meta['site'] ) ) {
			$site = '_' . $meta['site'];
		} else {
			$site = '';
		}
		unset( $meta['site'] );

		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		foreach ( $meta as $key => $value ) {

			if ( 'login' === $key ) {
				update_user_meta( $user->ID, 'user_login', $value );
			} elseif ( 'password' === $key ) {
				if ( ! empty( $value ) ) {
					wp_set_password( $value, $user->ID );
				}
			} elseif ( in_array( $key, array( 'first_name', 'last_name', 'display_name' ), true ) ) {
				update_user_meta( $user->ID, $key, $value );
			} elseif ( null !== $value && '' !== $value ) {
				update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_' . $key . $site, $value );
			}
		}

		return $user->ID;
	}

	/**
	 * Allow CSV MIME types through WordPress validation.
	 *
	 * @param array  $data     File data array.
	 * @param string $file     Full path to the file.
	 * @param string $filename The name of the file.
	 * @param array  $mimes    Allowed MIME types.
	 * @return array
	 */
	public function allow_csv_mime_type( $data, $file, $filename, $mimes ) {
		$wp_filetype    = wp_check_filetype( $filename, $mimes );
		$ext            = $wp_filetype['ext'];
		$type           = $wp_filetype['type'];
		$proper_filename = $data['proper_filename'];

		return compact( 'ext', 'type', 'proper_filename' );
	}
}
