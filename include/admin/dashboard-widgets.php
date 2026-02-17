<?php
/**
 * Leaky Paywall dashboard widgets
 *
 * @package zeen101's Leaky Paywall
 * @since 3.8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the dashboard widget
 *
 * @since  3.8.0
 */
function leaky_paywall_register_recent_subscribers_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'dashboard_widget', 'Leaky Paywall', 'leaky_paywall_load_recent_subscribers_dashboard_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'leaky_paywall_register_recent_subscribers_dashboard_widget' );

/**
 * Output the contents of the dashboard widget
 *
 * @since  3.8.0
 *
 * @param object $post The post object.
 * @param array  $callback_args The callback args.
 */
function leaky_paywall_load_recent_subscribers_dashboard_widget( $post, $callback_args ) {

	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();
	$period   = '30 days';

	// Stats using existing Insights query functions.
	$revenue        = leaky_paywall_insights_get_total_revenue( $period );
	$new_paid_subs  = leaky_paywall_insights_get_new_paid_subs( $period );
	$new_free_subs  = leaky_paywall_insights_get_new_free_subs( $period );
	$impressions    = LP_Nag_Impressions::get_total_impressions( $period );
	$total_new_subs = $new_paid_subs + $new_free_subs;
	$conversion_rate = $impressions > 0 ? round( ( $total_new_subs / $impressions ) * 100, 1 ) : 0;

	?>
	<style>
		.lp-dash-stats {
			display: flex;
			gap: 12px;
			margin-bottom: 16px;
		}
		.lp-dash-stat {
			flex: 1;
			background: #f6f7f7;
			border-radius: 4px;
			padding: 12px;
			text-align: center;
		}
		.lp-dash-stat-value {
			font-size: 20px;
			font-weight: 600;
			line-height: 1.3;
			color: #1d2327;
		}
		.lp-dash-stat-label {
			font-size: 11px;
			color: #646970;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			margin-top: 2px;
		}
		.lp-dash-funnel {
			background: #f0f6fc;
			border-left: 4px solid #2271b1;
			padding: 10px 14px;
			margin-bottom: 16px;
			font-size: 13px;
			color: #1d2327;
		}
		.lp-dash-funnel strong {
			font-size: 14px;
		}
		.lp-dash-links {
			display: flex;
			gap: 8px;
			padding-top: 12px;
			border-top: 1px solid #f0f0f1;
			margin-top: 12px;
		}
		.lp-dash-links a {
			text-decoration: none;
		}
		.lp-dash-links span {
			color: #dcdcde;
		}
	</style>

	<h3 style="margin-top: 0;"><?php esc_html_e( 'Last 30 Days', 'leaky-paywall' ); ?></h3>

	<div class="lp-dash-stats">
		<div class="lp-dash-stat">
			<div class="lp-dash-stat-value"><?php echo esc_html( $revenue ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Revenue', 'leaky-paywall' ); ?></div>
		</div>
		<div class="lp-dash-stat">
			<div class="lp-dash-stat-value"><?php echo esc_html( $new_paid_subs ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Paid Subs', 'leaky-paywall' ); ?></div>
		</div>
		<div class="lp-dash-stat">
			<div class="lp-dash-stat-value"><?php echo esc_html( $new_free_subs ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Free Subs', 'leaky-paywall' ); ?></div>
		</div>
		<div class="lp-dash-stat">
			<div class="lp-dash-stat-value"><?php echo esc_html( number_format( $impressions ) ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Paywalls Displayed', 'leaky-paywall' ); ?></div>
		</div>
	</div>

	<?php if ( LP_Nag_Impressions::get_days_of_data() >= 30 ) : ?>
	<div class="lp-dash-funnel">
		<?php
		printf(
			/* translators: 1: paywalls displayed count, 2: conversions count, 3: conversion rate */
			esc_html__( '%1$s paywalls displayed &rarr; %2$s conversions &rarr; %3$s conversion rate', 'leaky-paywall' ),
			'<strong>' . esc_html( number_format( $impressions ) ) . '</strong>',
			'<strong>' . esc_html( number_format( $total_new_subs ) ) . '</strong>',
			'<strong>' . esc_html( $conversion_rate . '%' ) . '</strong>'
		);
		?>
	</div>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Recent Subscribers', 'leaky-paywall' ); ?></h3>

	<?php

	$args = array(
		'order'      => 'DESC',
		'orderby'    => 'ID',
		'number'     => 5,
		'meta_query' => array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
				'compare' => 'EXISTS',
			),
		),
	);

	$users = get_users( $args );

	if ( $users ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'leaky-paywall' ); ?></th>
					<th><?php esc_html_e( 'Name', 'leaky-paywall' ); ?></th>
					<th><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $users as $user ) :
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

				if ( ! is_numeric( $level_id ) ) {
					continue;
				}

				$name = trim( $user->first_name . ' ' . $user->last_name );
				if ( ! $name ) {
					$name = $user->user_email;
				}

				$level_name = isset( $settings['levels'][ $level_id ]['label'] )
					? stripslashes( $settings['levels'][ $level_id ]['label'] )
					: '#' . $level_id;
			?>
				<tr>
					<td><?php echo esc_html( gmdate( 'M d, Y', strtotime( $user->user_registered ) ) ); ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>"><?php echo esc_html( $name ); ?></a></td>
					<td><?php echo esc_html( $level_name ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	} else {
		echo '<p>' . esc_html__( 'No subscribers found.', 'leaky-paywall' ) . '</p>';
	}

	?>
	<div class="lp-dash-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-insights' ) ); ?>"><?php esc_html_e( 'Insights', 'leaky-paywall' ); ?></a>
		<span>|</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-subscribers' ) ); ?>"><?php esc_html_e( 'Subscribers', 'leaky-paywall' ); ?></a>
		<span>|</span>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lp_transaction' ) ); ?>"><?php esc_html_e( 'Transactions', 'leaky-paywall' ); ?></a>
	</div>
	<?php
}
