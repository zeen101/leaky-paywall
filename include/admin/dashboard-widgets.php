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
		#dashboard_widget .inside { padding: 0 12px 12px; }
		.lp-dash-stats {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 10px;
			margin-bottom: 20px;
		}
		.lp-dash-stat {
			background: #fff;
			border: 1px solid #f0f0f1;
			border-left: 3px solid transparent;
			border-radius: 6px;
			padding: 12px 10px;
			text-align: center;
			text-decoration: none;
			display: block;
			transition: border-color 0.15s, box-shadow 0.15s;
		}
		.lp-dash-stat:hover {
			box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
		}
		.lp-dash-stat--revenue  { border-left-color: #38A65B; }
		.lp-dash-stat--paid     { border-left-color: #E26B2C; }
		.lp-dash-stat--free     { border-left-color: #3178D1; }
		.lp-dash-stat--paywalls { border-left-color: #8B5CF6; }
		.lp-dash-stat-value {
			font-size: 22px;
			font-weight: 700;
			line-height: 1.2;
			color: #1d2327;
		}
		.lp-dash-stat-label {
			font-size: 10px;
			font-weight: 600;
			color: #888;
			text-transform: uppercase;
			letter-spacing: 0.4px;
			margin-top: 4px;
		}
		.lp-dash-funnel {
			display: grid;
			grid-template-columns: 1fr auto 1fr auto 1fr;
			align-items: center;
			background: #f9f9f9;
			border-radius: 6px;
			padding: 0;
			margin-bottom: 20px;
			overflow: hidden;
		}
		.lp-dash-funnel-step {
			padding: 10px 8px;
			text-align: center;
		}
		.lp-dash-funnel-value {
			font-size: 18px;
			font-weight: 700;
			color: #1d2327;
			line-height: 1.2;
		}
		.lp-dash-funnel-label {
			font-size: 10px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.4px;
			color: #888;
			margin-top: 2px;
		}
		.lp-dash-funnel-arrow {
			font-size: 16px;
			color: #ccc;
			line-height: 1;
		}
		.lp-dash-section-title {
			font-size: 13px;
			font-weight: 600;
			color: #1d2327;
			margin: 0 0 10px 0;
		}
		.lp-dash-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 4px;
		}
		.lp-dash-table thead th {
			font-size: 10px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.4px;
			color: #888;
			padding: 0 0 8px 0;
			border-bottom: 2px solid #f0f0f1;
			text-align: left;
		}
		.lp-dash-table tbody td {
			padding: 8px 0;
			border-bottom: 1px solid #f5f5f5;
			font-size: 13px;
			color: #1d2327;
		}
		.lp-dash-table tbody tr:last-child td {
			border-bottom: none;
		}
		.lp-dash-table a {
			color: #2271b1;
			text-decoration: none;
		}
		.lp-dash-table a:hover {
			color: #135e96;
		}
		.lp-dash-links {
			display: flex;
			gap: 8px;
			padding-top: 12px;
			border-top: 1px solid #f0f0f1;
			margin-top: 16px;
		}
		.lp-dash-links a {
			text-decoration: none;
			font-size: 13px;
		}
		.lp-dash-links span {
			color: #dcdcde;
		}
		.lp-dash-period {
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			color: #888;
			margin: 0 0 12px 0;
		}
	</style>

	<p class="lp-dash-period"><?php esc_html_e( 'Last 30 Days', 'leaky-paywall' ); ?></p>

	<div class="lp-dash-stats">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>" class="lp-dash-stat lp-dash-stat--revenue">
			<div class="lp-dash-stat-value"><?php echo esc_html( $revenue ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Revenue', 'leaky-paywall' ); ?></div>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>" class="lp-dash-stat lp-dash-stat--paid">
			<div class="lp-dash-stat-value"><?php echo esc_html( $new_paid_subs ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Paid', 'leaky-paywall' ); ?></div>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>" class="lp-dash-stat lp-dash-stat--free">
			<div class="lp-dash-stat-value"><?php echo esc_html( $new_free_subs ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Free', 'leaky-paywall' ); ?></div>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>" class="lp-dash-stat lp-dash-stat--paywalls">
			<div class="lp-dash-stat-value"><?php echo esc_html( number_format( $impressions ) ); ?></div>
			<div class="lp-dash-stat-label"><?php esc_html_e( 'Paywalls', 'leaky-paywall' ); ?></div>
		</a>
	</div>

	<?php if ( LP_Nag_Impressions::get_days_of_data() >= 30 ) : ?>
	<div class="lp-dash-funnel">
		<div class="lp-dash-funnel-step">
			<div class="lp-dash-funnel-value"><?php echo esc_html( number_format( $impressions ) ); ?></div>
			<div class="lp-dash-funnel-label"><?php esc_html_e( 'Displayed', 'leaky-paywall' ); ?></div>
		</div>
		<div class="lp-dash-funnel-arrow">&rarr;</div>
		<div class="lp-dash-funnel-step">
			<div class="lp-dash-funnel-value"><?php echo esc_html( number_format( $total_new_subs ) ); ?></div>
			<div class="lp-dash-funnel-label"><?php esc_html_e( 'Conversions', 'leaky-paywall' ); ?></div>
		</div>
		<div class="lp-dash-funnel-arrow">&rarr;</div>
		<div class="lp-dash-funnel-step">
			<div class="lp-dash-funnel-value"><?php echo esc_html( $conversion_rate . '%' ); ?></div>
			<div class="lp-dash-funnel-label"><?php esc_html_e( 'Rate', 'leaky-paywall' ); ?></div>
		</div>
	</div>
	<?php endif; ?>

	<h4 class="lp-dash-section-title"><?php esc_html_e( 'Recent Subscribers', 'leaky-paywall' ); ?></h4>

	<?php

	$recent_txns = get_posts( array(
		'post_type'      => 'lp_transaction',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(
			array(
				'key'     => '_transaction_status',
				'value'   => 'complete',
			),
		),
	) );

	if ( $recent_txns ) {
		$seen_emails = array();
		$shown       = 0;
		?>
		<table class="lp-dash-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'leaky-paywall' ); ?></th>
					<th><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></th>
					<th><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $recent_txns as $txn ) :
				if ( $shown >= 5 ) {
					break;
				}
				// Skip renewals and refunds.
				if ( get_post_meta( $txn->ID, '_is_recurring', true ) ) {
					continue;
				}
				if ( 'refund' === get_post_meta( $txn->ID, '_status', true ) ) {
					continue;
				}
				$email = get_post_meta( $txn->ID, '_email', true );
				if ( ! $email || isset( $seen_emails[ $email ] ) ) {
					continue;
				}
				$seen_emails[ $email ] = true;
				$shown++;
				$level_id = get_post_meta( $txn->ID, '_level_id', true );
				$level_name = isset( $settings['levels'][ $level_id ]['label'] )
					? stripslashes( $settings['levels'][ $level_id ]['label'] )
					: '#' . $level_id;
				$user = get_user_by( 'email', $email );
				$user_link = $user
					? admin_url( 'admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user->ID )
					: '';
			?>
				<tr>
					<td><?php echo esc_html( gmdate( 'M j', strtotime( $txn->post_date ) ) ); ?></td>
					<td><?php if ( $user_link ) : ?><a href="<?php echo esc_url( $user_link ); ?>"><?php echo esc_html( $email ); ?></a><?php else : echo esc_html( $email ); endif; ?></td>
					<td><?php echo esc_html( $level_name ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	} else {
		echo '<p style="font-size: 13px; color: #999; margin: 0;">' . esc_html__( 'No subscribers found.', 'leaky-paywall' ) . '</p>';
	}

	?>
	<div class="lp-dash-links">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>"><?php esc_html_e( 'Dashboard', 'leaky-paywall' ); ?></a>
		<span>|</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-subscribers' ) ); ?>"><?php esc_html_e( 'Subscribers', 'leaky-paywall' ); ?></a>
		<span>|</span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-insights' ) ); ?>"><?php esc_html_e( 'Insights', 'leaky-paywall' ); ?></a>
		<span>|</span>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lp_transaction' ) ); ?>"><?php esc_html_e( 'Transactions', 'leaky-paywall' ); ?></a>
	</div>
	<?php
}
