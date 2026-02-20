<?php
/**
 * Leaky Paywall Dashboard page
 *
 * @package zeen101's Leaky Paywall
 * @since 4.21.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Leaky_Paywall_Dashboard {

	public function dashboard_page() {

		$period = '30 days';

		$revenue       = leaky_paywall_insights_get_total_revenue( $period );
		$new_paid_subs = leaky_paywall_insights_get_new_paid_subs( $period );
		$new_free_subs = leaky_paywall_insights_get_new_free_subs( $period );
		$impressions   = LP_Nag_Impressions::get_total_impressions( $period );

		$total_new_subs  = $new_paid_subs + $new_free_subs;
		$conversion_rate = $impressions > 0 ? round( ( $total_new_subs / $impressions ) * 100, 1 ) : 0;

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		?>

		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e( 'Dashboard', 'leaky-paywall' ); ?></h1>
				</span>
			</div>
		</div>

		<div id="lp-wit-app">

			<h3><?php esc_html_e( 'Last 30 Days', 'leaky-paywall' ); ?></h3>

			<div class="card-stats">
				<div class="card"><span class="dashicons dashicons-chart-bar"></span>
					<div class="card-content">
						<div class="card-title"><?php esc_html_e( 'Total Revenue', 'leaky-paywall' ); ?></div>
						<div class="card-amount"><?php echo esc_html( $revenue ); ?></div>
					</div>
				</div>
				<div class="card"><span class="dashicons dashicons-money-alt"></span>
					<div class="card-content">
						<div class="card-title"><?php esc_html_e( 'New Paid Subscribers', 'leaky-paywall' ); ?></div>
						<div class="card-amount"><?php echo esc_html( $new_paid_subs ); ?></div>
					</div>
				</div>
				<div class="card"><span class="dashicons dashicons-admin-users"></span>
					<div class="card-content">
						<div class="card-title"><?php esc_html_e( 'New Free Subscribers', 'leaky-paywall' ); ?></div>
						<div class="card-amount"><?php echo esc_html( $new_free_subs ); ?></div>
					</div>
				</div>
				<div class="card"><span class="dashicons dashicons-visibility"></span>
					<div class="card-content">
						<div class="card-title"><?php esc_html_e( 'Paywall Displays', 'leaky-paywall' ); ?></div>
						<div class="card-amount"><?php echo esc_html( number_format( $impressions ) ); ?></div>
					</div>
				</div>
			</div>

			<?php if ( LP_Nag_Impressions::get_days_of_data() >= 30 ) : ?>
			<div class="lp-dashboard-funnel">
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

			<?php
			$insights = new Leaky_Paywall_Insights();
			$this->display_charts( $insights, $period );
			?>

			<div class="lp-dashboard-columns">

				<div class="lp-dashboard-column">
					<h3><?php esc_html_e( 'Active Subscriptions by Level', 'leaky-paywall' ); ?></h3>
					<?php
					$levels = leaky_paywall_get_levels();
					$counts = $insights->get_all_active_subscriber_counts();
					$data   = array();

					foreach ( $levels as $id => $level ) {
						if ( isset( $level['deleted'] ) && 1 == $level['deleted'] ) {
							continue;
						}
						$data[ $level['label'] ] = isset( $counts[ $id ] ) ? $counts[ $id ] : 0;
					}

					arsort( $data );
					?>

					<?php if ( ! empty( $data ) ) : ?>
					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Subscription Level', 'leaky-paywall' ); ?></th>
								<th><?php esc_html_e( 'Active Subscribers', 'leaky-paywall' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data as $label => $count ) : ?>
							<tr>
								<td><?php echo esc_html( stripslashes( $label ) ); ?></td>
								<td><?php echo absint( $count ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else : ?>
						<p><?php esc_html_e( 'No subscription levels found.', 'leaky-paywall' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="lp-dashboard-column">
					<h3><?php esc_html_e( 'Recent Subscribers', 'leaky-paywall' ); ?></h3>
					<?php
					$args = array(
						'order'      => 'DESC',
						'orderby'    => 'ID',
						'number'     => 10,
						'meta_query' => array(
							array(
								'key'     => '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site,
								'compare' => 'EXISTS',
							),
						),
					);

					$users = get_users( $args );

					if ( $users ) :
					?>
					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'leaky-paywall' ); ?></th>
								<th><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></th>
								<th><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( $users as $user ) :
							$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

							if ( ! is_numeric( $level_id ) ) {
								continue;
							}

							$level_name = isset( $settings['levels'][ $level_id ]['label'] )
								? stripslashes( $settings['levels'][ $level_id ]['label'] )
								: '#' . $level_id;
						?>
							<tr>
								<td><?php echo esc_html( gmdate( 'M d, Y', strtotime( $user->user_registered ) ) ); ?></td>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user->ID ) ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td>
								<td><?php echo esc_html( $level_name ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php else : ?>
						<p><?php esc_html_e( 'No subscribers found.', 'leaky-paywall' ); ?></p>
					<?php endif; ?>
				</div>

			</div>

			<div class="lp-dashboard-quick-links">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'leaky-paywall' ); ?></a>
				<span>|</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-subscribers' ) ); ?>"><?php esc_html_e( 'Subscribers', 'leaky-paywall' ); ?></a>
				<span>|</span>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lp_transaction' ) ); ?>"><?php esc_html_e( 'Transactions', 'leaky-paywall' ); ?></a>
				<span>|</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-insights' ) ); ?>"><?php esc_html_e( 'Insights', 'leaky-paywall' ); ?></a>
			</div>

		</div>

		<?php
	}

	private function display_charts( $insights, $period ) {
		$signup_paid_data = $insights->get_signup_paid_data( $period );
		$signup_free_data = $insights->get_signup_free_data( $period );
		$revenue_data     = $this->get_daily_revenue_data( $period );
		?>
		<div class="lp-dashboard-charts">
			<div class="lp-dashboard-chart">
				<h3><?php esc_html_e( 'New Signups', 'leaky-paywall' ); ?></h3>
				<div class="lp-dashboard-chart-wrap">
					<canvas id="lp-dashboard-signup-chart"></canvas>
				</div>
			</div>
			<div class="lp-dashboard-chart">
				<h3><?php esc_html_e( 'Revenue', 'leaky-paywall' ); ?></h3>
				<div class="lp-dashboard-chart-wrap">
					<canvas id="lp-dashboard-revenue-chart"></canvas>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var signupPaid = <?php echo wp_json_encode( $signup_paid_data ); ?>;
			var signupFree = <?php echo wp_json_encode( $signup_free_data ); ?>;
			var revenueData = <?php echo wp_json_encode( $revenue_data ); ?>;

			var sharedOptions = {
				animation: false,
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: true },
					tooltip: { enabled: true }
				},
				scales: {
					y: {
						beginAtZero: true
					}
				}
			};

			new Chart(document.getElementById('lp-dashboard-signup-chart'), {
				type: 'line',
				options: Object.assign({}, sharedOptions, {
					scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
				}),
				data: {
					labels: signupPaid.map(function(row) { return row.day; }),
					datasets: [
						{
							label: '<?php echo esc_js( __( 'Paid', 'leaky-paywall' ) ); ?>',
							data: signupPaid.map(function(row) { return row.count; }),
							borderColor: '#38A65B',
							backgroundColor: '#38A65B'
						},
						{
							label: '<?php echo esc_js( __( 'Free', 'leaky-paywall' ) ); ?>',
							data: signupFree.map(function(row) { return row.count; }),
							borderColor: '#3178D1',
							backgroundColor: '#3178D1'
						}
					]
				}
			});

			new Chart(document.getElementById('lp-dashboard-revenue-chart'), {
				type: 'line',
				options: Object.assign({}, sharedOptions, {
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function(value) {
									return '<?php echo esc_js( html_entity_decode( leaky_paywall_get_current_currency_symbol() ) ); ?>' + value.toFixed(2);
								}
							}
						}
					},
					plugins: {
						legend: { display: false },
						tooltip: {
							enabled: true,
							callbacks: {
								label: function(context) {
									return '<?php echo esc_js( html_entity_decode( leaky_paywall_get_current_currency_symbol() ) ); ?>' + context.parsed.y.toFixed(2);
								}
							}
						}
					}
				}),
				data: {
					labels: revenueData.map(function(row) { return row.day; }),
					datasets: [
						{
							label: '<?php echo esc_js( __( 'Revenue', 'leaky-paywall' ) ); ?>',
							data: revenueData.map(function(row) { return row.amount; }),
							borderColor: '#E26B2C',
							backgroundColor: 'rgba(226, 107, 44, 0.1)',
							fill: true
						}
					]
				}
			});
		})();
		</script>
		<?php
	}

	private function get_daily_revenue_data( $period ) {
		global $wpdb;

		$args_period = leaky_paywall_insights_get_formatted_period( $period );
		$after_date  = gmdate( 'Y-m-d', strtotime( $args_period ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(p.post_date) AS revenue_date,
						SUM(CAST(pm_price.meta_value AS DECIMAL(10,2))) AS daily_total
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_price
					 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
				 INNER JOIN {$wpdb->postmeta} pm_status
					 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
				 WHERE p.post_type = 'lp_transaction'
				 AND p.post_date >= %s
				 AND pm_status.meta_value != 'incomplete'
				 AND CAST(pm_price.meta_value AS DECIMAL(10,2)) > 0
				 GROUP BY DATE(p.post_date)
				 ORDER BY revenue_date ASC",
				$after_date
			)
		);

		$by_date = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$day_label = gmdate( 'M j', strtotime( $row->revenue_date ) );
				$by_date[ $day_label ] = (float) $row->daily_total;
			}
		}

		// Build array with all days in period, filling gaps with 0.
		$days = $this->get_period_days( $period );
		$data = array();

		foreach ( $days as $day_label ) {
			$data[] = array(
				'day'    => $day_label,
				'amount' => isset( $by_date[ $day_label ] ) ? $by_date[ $day_label ] : 0,
			);
		}

		return $data;
	}

	private function get_period_days( $period ) {
		switch ( $period ) {
			case '7 days':
				$num_days = 8;
				break;
			case '30 days':
				$num_days = 31;
				break;
			case '3 months':
				$num_days = 93;
				break;
			case 'today':
				$num_days = 2;
				break;
			default:
				$num_days = 31;
				break;
		}

		$days = array();
		for ( $i = $num_days - 1; $i >= 0; $i-- ) {
			$days[] = gmdate( 'M j', strtotime( '-' . $i . ' days' ) );
		}

		return $days;
	}
}
