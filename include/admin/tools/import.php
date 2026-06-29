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
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['leaky_paywall_bulk_add_subscribers'] ) ), 'bulk_add_subscribers' )
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

		// Restrict to plain-text CSV. The media uploader accepts other types
		// (e.g., .numbers binary archives) by default; without this check the
		// parser walks garbage bytes and shows a misleading "missing email
		// column" error.
		$filetype = wp_check_filetype( $file_path );
		$ext      = strtolower( (string) $filetype['ext'] );
		if ( 'csv' !== $ext ) {
			echo '<div class="notice notice-error">';
			echo '<p><strong>' . esc_html__( 'Only .csv files are supported by this importer.', 'leaky-paywall' ) . '</strong></p>';
			echo '<p>' . sprintf(
				/* translators: %s: the uploaded file's extension. */
				esc_html__( 'Uploaded file extension: %s. If you exported from Numbers or Excel, use "File → Export To → CSV" first, then upload the .csv.', 'leaky-paywall' ),
				'<code>' . esc_html( $ext !== '' ? '.' . $ext : '(none)' ) . '</code>'
			) . '</p>';
			echo '</div>';
			return;
		}

		$headers     = array();
		$manager     = new SplFileObject( $file_path );
		// Without READ_CSV, fgetcsv() works but trailing blank lines come back
		// as `[null]` (a 1-element array of null) and confuse the row loop.
		// SKIP_EMPTY + READ_AHEAD also help with files that have stray
		// Windows-style line endings or BOM-induced empty first reads.
		$manager->setFlags( SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );
		$i           = 0;
		$new         = 0;
		$updated     = 0;
		$skipped     = 0;
		$header_ok   = false;

		while ( ! $manager->eof() ) {

			$row = $manager->fgetcsv();

			if ( 0 === $i ) {
				foreach ( $row as $element ) {
					$el = (string) $element;
					// Strip UTF-8 BOM if present on the very first cell.
					if ( 0 === strpos( $el, "\xEF\xBB\xBF" ) ) {
						$el = substr( $el, 3 );
					}
					$clean_element = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $el );
					$headers[]     = strtolower( str_replace( ' ', '_', trim( $clean_element ) ) );
				}
				$i++;

				// Validate that the file has an email column under either of
				// the two accepted names (import wants 'email'; LP exports
				// write 'user_email' — accept both so an exported CSV can be
				// re-imported without renaming columns).
				$header_ok = in_array( 'email', $headers, true ) || in_array( 'user_email', $headers, true );
				if ( ! $header_ok ) {
					$detected = empty( $headers ) ? '(none detected — file may be empty or not a CSV)' : implode( ', ', array_map( 'sanitize_text_field', $headers ) );
					echo '<div class="notice notice-error">';
					echo '<p><strong>' . esc_html__( 'CSV header is missing an email column. Add a header row containing either "email" or "user_email".', 'leaky-paywall' ) . '</strong></p>';
					echo '<p>' . esc_html__( 'Detected columns:', 'leaky-paywall' ) . ' <code>' . esc_html( $detected ) . '</code></p>';
					echo '</div>';
					return;
				}
				continue;
			}

			if ( empty( $headers ) ) {
				break;
			}

			if ( empty( $row[0] ) ) {
				continue;
			}

			if ( count( $headers ) !== count( $row ) ) {
				$skipped++;
				continue;
			}

			$type = $this->process_row( array_combine( $headers, $row ) );

			if ( 'new' === $type ) {
				$new++;
			} elseif ( 'updated' === $type ) {
				$updated++;
			} else {
				$skipped++;
			}
		}

		// Always show a result message — including the all-zero case — so a
		// silent page refresh after upload never happens again.
		$class = ( $new + $updated > 0 ) ? 'notice notice-success' : 'notice notice-warning';
		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<p><strong>' . intval( $new ) . ' ' . esc_html__( 'new subscribers imported.', 'leaky-paywall' ) . '</strong></p>';
		echo '<p><strong>' . intval( $updated ) . ' ' . esc_html__( 'existing subscribers updated.', 'leaky-paywall' ) . '</strong></p>';
		if ( $skipped > 0 ) {
			echo '<p><strong>' . intval( $skipped ) . ' ' . esc_html__( 'rows skipped (missing email, unknown level_id, or column-count mismatch).', 'leaky-paywall' ) . '</strong></p>';
		}
		echo '</div>';
	}

	/**
	 * Process a single CSV row.
	 *
	 * @param array $item Associative array of CSV row data.
	 * @return string|void 'new', 'updated', or void on skip.
	 */
	public function process_row( $item ) {

		global $blog_id;

		// Accept both 'email' and 'user_email' (the column name LP's own export
		// writes). Same for 'username' / 'user_login'.
		$email_raw = '';
		if ( isset( $item['email'] ) && '' !== trim( (string) $item['email'] ) ) {
			$email_raw = $item['email'];
		} elseif ( isset( $item['user_email'] ) ) {
			$email_raw = $item['user_email'];
		}
		$email    = $email_raw !== '' ? sanitize_text_field( strtolower( $email_raw ) ) : '';
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
				'login'           => isset( $item['username'] ) && '' !== trim( (string) $item['username'] )
					? sanitize_text_field( strtolower( $item['username'] ) )
					: ( isset( $item['user_login'] ) && '' !== trim( (string) $item['user_login'] )
						? sanitize_text_field( strtolower( $item['user_login'] ) )
						: $email ),
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
}
