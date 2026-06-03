<?php
/**
 * Automatic cleanup of stale lp_incomplete_user posts.
 *
 * Incomplete-user posts are created on form submit and normally deleted by
 * leaky_paywall_cleanup_incomplete_user() after a successful finalize. Anything
 * left behind is orphan residue — the customer abandoned mid-flow, or the
 * finalize never ran. Stale records are PII (name, email, submitted form
 * fields, Stripe customer object) and were a foot-gun for webhook handlers
 * matching on email.
 *
 * The load-bearing protections against the orphan-incomplete-user bug live in
 * leaky_paywall_get_incomplete_user_from_email() (read-time age check) and
 * leaky_paywall_finalize_subscription_from_payment_intent() (renewal gate).
 * This module is the disk-space / PII-retention layer on top.
 *
 * Retention defaults to 30 days, controllable via the
 * `leaky_paywall_incomplete_user_retention_days` filter. A value of 0 disables
 * automatic deletion while leaving the recurring action scheduled, so a
 * publisher who restores the filter later doesn't need to re-register.
 *
 * @package Leaky Paywall
 * @since 5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Days of retention for stale lp_incomplete_user posts.
 *
 * @since 5.1.0
 * @return int Days; 0 disables automatic cleanup.
 */
function leaky_paywall_incomplete_user_retention_days() {
	return max( 0, (int) apply_filters( 'leaky_paywall_incomplete_user_retention_days', 30 ) );
}

/**
 * Self-healing schedule: ensure the daily cleanup action is registered with
 * Action Scheduler on every page load. If the action ever goes missing (DB
 * issue, brief deactivation, migration) it's re-registered on the next init.
 *
 * @since 5.1.0
 */
function leaky_paywall_register_incomplete_user_cleanup() {
	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return;
	}

	if ( as_has_scheduled_action( 'leaky_paywall_cleanup_incomplete_users' ) ) {
		return;
	}

	as_schedule_recurring_action(
		time() + DAY_IN_SECONDS,
		DAY_IN_SECONDS,
		'leaky_paywall_cleanup_incomplete_users',
		array(),
		'leaky-paywall'
	);
}
add_action( 'init', 'leaky_paywall_register_incomplete_user_cleanup' );

/**
 * Delete a batch of stale incomplete-user posts. Continues itself if the batch
 * ceiling was hit, so a large backlog drains in one chained pass instead of
 * one batch per day.
 *
 * Catches both 'publish' (created state) and 'trash' (the soft-trashed state
 * left by leaky_paywall_cleanup_incomplete_user(), which uses wp_trash_post).
 *
 * @since 5.1.0
 */
function leaky_paywall_run_incomplete_user_cleanup_batch() {
	$days = leaky_paywall_incomplete_user_retention_days();
	if ( 0 === $days ) {
		return;
	}

	$batch_size = 200;

	$stale = get_posts( array(
		'post_type'      => 'lp_incomplete_user',
		'post_status'    => array( 'publish', 'trash' ),
		'posts_per_page' => $batch_size,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'date_query'     => array(
			array(
				'before' => $days . ' days ago',
				'column' => 'post_date',
			),
		),
	) );

	foreach ( $stale as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	$deleted = count( $stale );

	leaky_paywall_log(
		sprintf( 'Deleted %d stale incomplete-user post(s) older than %d day(s)', $deleted, $days ),
		'incomplete user cleanup'
	);

	// Hit the cap — there's likely more. Chain another batch immediately so a
	// multi-thousand backlog drains in one pass rather than one per day.
	if ( $deleted === $batch_size && function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'leaky_paywall_cleanup_incomplete_users', array(), 'leaky-paywall' );
	}
}
add_action( 'leaky_paywall_cleanup_incomplete_users', 'leaky_paywall_run_incomplete_user_cleanup_batch' );

/**
 * Unschedule the recurring action on plugin deactivation. The uninstall handler
 * performs a final sweep of any remaining posts.
 *
 * @since 5.1.0
 */
function leaky_paywall_unschedule_incomplete_user_cleanup() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'leaky_paywall_cleanup_incomplete_users' );
	}
}
register_deactivation_hook(
	LEAKY_PAYWALL_PATH . 'leaky-paywall.php',
	'leaky_paywall_unschedule_incomplete_user_cleanup'
);
