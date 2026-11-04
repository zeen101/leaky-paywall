<?php
/**
 * Remove readers' passwords stored in plain text before 5.3.3.
 *
 * Until 5.3.3 the password a reader chose at checkout was saved as typed:
 * on the lp_incomplete_user record (in _user_data and _field_data), copied
 * onto the lp_transaction when the account was created from a webhook, and
 * on a pending PayPal transaction (_password) until PayPal's IPN arrived.
 *
 * This works through those records once, in the background. A registration
 * that can still complete keeps a hash so the reader's password still works;
 * everything else simply loses the password.
 *
 * @package Leaky Paywall
 * @since 5.3.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue the cleanup until it has finished once.
 *
 * @since 5.3.3
 */
function leaky_paywall_register_password_scrub() {

	if ( 'done' === get_option( 'leaky_paywall_password_scrub' ) ) {
		return;
	}

	if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( 'leaky_paywall_scrub_stored_passwords' ) ) {
		return;
	}

	as_enqueue_async_action( 'leaky_paywall_scrub_stored_passwords', array(), 'leaky-paywall' );
}
add_action( 'init', 'leaky_paywall_register_password_scrub' );

/**
 * Clean one batch of records, then queue the next.
 *
 * Walks postmeta by meta_id, so each row is read once however many batches
 * it takes.
 *
 * @since 5.3.3
 */
function leaky_paywall_run_password_scrub_batch() {
	global $wpdb;

	// A stray queued batch after the cleanup finished has nothing to do.
	if ( 'done' === get_option( 'leaky_paywall_password_scrub' ) ) {
		return;
	}

	$batch_size = 50; // Hashing is slow by design; keeps a batch well inside Action Scheduler's 30 second run.
	$cursor     = (int) get_option( 'leaky_paywall_password_scrub_cursor', 0 );

	// A registration this recent can still be completed by a webhook, so its
	// password is kept as a hash. Older ones are past the point of completing.
	$keep_after = time() - ( max( 1, leaky_paywall_incomplete_user_retention_days() ) * DAY_IN_SECONDS );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time batched migration over LP's own records.
		$wpdb->prepare(
			"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, p.post_type, p.post_date_gmt
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type IN ( 'lp_incomplete_user', 'lp_transaction' )
			 AND pm.meta_key IN ( '_user_data', '_field_data', '_password' )
			 AND pm.meta_id > %d
			 ORDER BY pm.meta_id ASC
			 LIMIT %d",
			$cursor,
			$batch_size
		)
	);

	$cleaned = 0;

	foreach ( $rows as $row ) {

		$cursor = (int) $row->meta_id;
		$recent = strtotime( $row->post_date_gmt . ' UTC' ) >= $keep_after;

		if ( '_password' === $row->meta_key ) {

			if ( '' !== (string) $row->meta_value && $recent && ! get_post_meta( $row->post_id, '_password_hash', true ) ) {
				update_post_meta( $row->post_id, '_password_hash', wp_hash_password( (string) $row->meta_value ) );
			}

			delete_metadata_by_mid( 'post', $row->meta_id );
			++$cleaned;
			continue;
		}

		$value = maybe_unserialize( $row->meta_value );

		if ( ! is_array( $value ) || ( ! isset( $value['password'] ) && ! isset( $value['confirm_password'] ) ) ) {
			continue;
		}

		if ( '_user_data' === $row->meta_key && 'lp_incomplete_user' === $row->post_type && $recent ) {
			list( $value ) = leaky_paywall_strip_stored_passwords( $value );
		} else {
			unset( $value['password'], $value['confirm_password'] );
		}

		update_metadata_by_mid( 'post', $row->meta_id, $value );
		++$cleaned;
	}

	update_option( 'leaky_paywall_password_scrub_cursor', $cursor, false );

	if ( $cleaned ) {
		leaky_paywall_log( sprintf( 'Removed stored passwords from %d record(s)', $cleaned ), 'password cleanup' );
	}

	if ( count( $rows ) === $batch_size ) {
		as_enqueue_async_action( 'leaky_paywall_scrub_stored_passwords', array(), 'leaky-paywall' );
		return;
	}

	update_option( 'leaky_paywall_password_scrub', 'done', false );
	delete_option( 'leaky_paywall_password_scrub_cursor' );
}
add_action( 'leaky_paywall_scrub_stored_passwords', 'leaky_paywall_run_password_scrub_batch' );
