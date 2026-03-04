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

		$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30 days';
		$allowed_periods = array( 'today', '7 days', '30 days', '3 months' );
		if ( ! in_array( $period, $allowed_periods, true ) ) {
			$period = '30 days';
		}

		$period_labels = array(
			'today'    => __( 'Last 24 Hours', 'leaky-paywall' ),
			'7 days'   => __( 'Last 7 Days', 'leaky-paywall' ),
			'30 days'  => __( 'Last 30 Days', 'leaky-paywall' ),
			'3 months' => __( 'Last 3 Months', 'leaky-paywall' ),
		);

		$revenue       = leaky_paywall_insights_get_total_revenue( $period );
		$new_paid_subs = leaky_paywall_insights_get_new_paid_subs( $period );
		$new_free_subs = leaky_paywall_insights_get_new_free_subs( $period );
		$impressions   = LP_Nag_Impressions::get_total_impressions( $period );

		$total_new_subs  = $new_paid_subs + $new_free_subs;
		$conversion_rate = $impressions > 0 ? round( ( $total_new_subs / $impressions ) * 100, 1 ) : 0;

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();

		$insights = new Leaky_Paywall_Insights();

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

		<?php if ( isset( $_GET['lp_onboarding_complete'] ) ) : ?>
		<div class="lp-onboarding-complete">
			<h2><?php esc_html_e( 'Setup Complete!', 'leaky-paywall' ); ?></h2>
			<p><?php esc_html_e( 'Your paywall is configured and ready to go. Here is your dashboard — it will populate as your site gets traffic and subscribers:', 'leaky-paywall' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Revenue & Subscribers', 'leaky-paywall' ); ?></strong> — <?php esc_html_e( 'track paid and free signups over time', 'leaky-paywall' ); ?></li>
				<li><strong><?php esc_html_e( 'Paywall Displays', 'leaky-paywall' ); ?></strong> — <?php esc_html_e( 'see how often your paywall appears and its conversion rate', 'leaky-paywall' ); ?></li>
				<li><strong><?php esc_html_e( 'Top Converting Content', 'leaky-paywall' ); ?></strong> — <?php esc_html_e( 'discover which articles drive the most subscriptions', 'leaky-paywall' ); ?></li>
			</ul>
			<p><?php printf( esc_html__( 'You can fine-tune your subscription levels, restrictions, and payment settings anytime from %sSettings%s.', 'leaky-paywall' ), '<a href="' . esc_url( admin_url( 'admin.php?page=leaky-paywall-settings' ) ) . '">', '</a>' ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( empty( $settings['insights_api_key'] ) ) : ?>
		<div class="lp-insights-callout">
			<div class="lp-insights-callout--content">
				<h3><?php esc_html_e( 'Connect to Leaky Paywall Insights', 'leaky-paywall' ); ?></h3>
				<p><?php esc_html_e( 'Track subscriber events, content engagement, and payment activity in real time. Enter your API key to start collecting data.', 'leaky-paywall' ); ?></p>
			</div>
			<div class="lp-insights-callout--action">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-settings&tab=general&section=insights' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Connect to Insights', 'leaky-paywall' ); ?></a>
			</div>
		</div>
		<?php else : ?>
		<div class="lp-insights-callout lp-insights-callout--connected">
			<div class="lp-insights-callout--content">
				<h3><?php esc_html_e( 'Subscriber Insights', 'leaky-paywall' ); ?></h3>
				<p><?php esc_html_e( 'Your site is connected. View detailed subscriber analytics, event timelines, and engagement reports on the Subscriber Insights platform.', 'leaky-paywall' ); ?></p>
			</div>
			<div class="lp-insights-callout--action">
				<a href="<?php echo esc_url( apply_filters( 'leaky_paywall_insights_api_url', 'https://insights.leakypaywall.com' ) ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'View Subscriber Insights', 'leaky-paywall' ); ?></a>
			</div>
		</div>
		<?php endif; ?>

		<div class="lp-dashboard">

			<!-- Header row with period selector -->
			<div class="lp-dashboard-header">
				<h2><?php echo esc_html( $period_labels[ $period ] ); ?></h2>
				<div class="lp-dashboard-period">
					<select onchange="window.location.href='<?php echo esc_js( admin_url( 'admin.php?page=issuem-leaky-paywall&period=' ) ); ?>' + this.value;">
						<?php foreach ( $period_labels as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $period, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Stat Cards -->
			<div class="lp-dashboard-stats">
				<div class="lp-stat-card lp-stat-card--revenue">
					<div class="lp-stat-label"><?php esc_html_e( 'Revenue', 'leaky-paywall' ); ?></div>
					<div class="lp-stat-value"><?php echo esc_html( $revenue ); ?></div>
				</div>
				<div class="lp-stat-card lp-stat-card--paid">
					<div class="lp-stat-label"><?php esc_html_e( 'Paid Subscribers', 'leaky-paywall' ); ?></div>
					<div class="lp-stat-value"><?php echo esc_html( $new_paid_subs ); ?></div>
				</div>
				<div class="lp-stat-card lp-stat-card--free">
					<div class="lp-stat-label"><?php esc_html_e( 'Free Subscribers', 'leaky-paywall' ); ?></div>
					<div class="lp-stat-value"><?php echo esc_html( $new_free_subs ); ?></div>
				</div>
				<div class="lp-stat-card lp-stat-card--paywalls">
					<div class="lp-stat-label"><?php esc_html_e( 'Paywall Displays', 'leaky-paywall' ); ?></div>
					<div class="lp-stat-value"><?php echo esc_html( number_format( $impressions ) ); ?></div>
				</div>
			</div>

			<!-- Conversion Funnel -->
			<?php if ( LP_Nag_Impressions::get_days_of_data() >= 30 ) : ?>
			<div class="lp-dashboard-funnel">
				<div class="lp-funnel-step">
					<div class="lp-funnel-value"><?php echo esc_html( number_format( $impressions ) ); ?></div>
					<div class="lp-funnel-label"><?php esc_html_e( 'Paywalls Displayed', 'leaky-paywall' ); ?></div>
				</div>
				<div class="lp-funnel-arrow">&rarr;</div>
				<div class="lp-funnel-step">
					<div class="lp-funnel-value"><?php echo esc_html( number_format( $total_new_subs ) ); ?></div>
					<div class="lp-funnel-label"><?php esc_html_e( 'Conversions', 'leaky-paywall' ); ?></div>
				</div>
				<div class="lp-funnel-arrow">&rarr;</div>
				<div class="lp-funnel-step">
					<div class="lp-funnel-value"><?php echo esc_html( $conversion_rate . '%' ); ?></div>
					<div class="lp-funnel-label"><?php esc_html_e( 'Conversion Rate', 'leaky-paywall' ); ?></div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Charts -->
			<?php $this->display_charts( $insights, $period ); ?>

			<!-- Top Converting Content -->
			<?php
			$top_paid_content = $this->get_top_converting_content( $period, 'paid' );
			$top_free_content = $this->get_top_converting_content( $period, 'free' );
			?>
			<div class="lp-card">
				<h3><?php esc_html_e( 'Top Content — Paid Conversions', 'leaky-paywall' ); ?></h3>
				<?php if ( ! empty( $top_paid_content ) ) : ?>
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Conversions', 'leaky-paywall' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_paid_content as $item ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
							<td style="text-align: right;"><?php echo absint( $item['count'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p class="lp-no-data"><?php esc_html_e( 'No conversion data for this period.', 'leaky-paywall' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lp-card">
				<h3><?php esc_html_e( 'Top Content — Free Conversions', 'leaky-paywall' ); ?></h3>
				<?php if ( ! empty( $top_free_content ) ) : ?>
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Conversions', 'leaky-paywall' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_free_content as $item ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></td>
							<td style="text-align: right;"><?php echo absint( $item['count'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p class="lp-no-data"><?php esc_html_e( 'No conversion data for this period.', 'leaky-paywall' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Paywall Insights -->
			<?php
			$by_nag_type = LP_Nag_Impressions::get_impressions_by_nag_type( $period );
			$top_posts   = LP_Nag_Impressions::get_top_posts_with_conversions( $period, 3 );
			?>
			<div class="lp-card">
				<h3><?php esc_html_e( 'Paywall Displays by Type', 'leaky-paywall' ); ?></h3>
				<?php if ( ! empty( $by_nag_type ) ) : ?>
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Displays', 'leaky-paywall' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $by_nag_type as $row ) : ?>
						<tr>
							<td><?php echo esc_html( LP_Nag_Impressions::get_nag_type_label( $row->nag_type ) ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format( $row->total ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p class="lp-no-data"><?php esc_html_e( 'No paywall display data for this period.', 'leaky-paywall' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="lp-card">
				<h3><?php esc_html_e( 'Top Content by Paywall Displays', 'leaky-paywall' ); ?></h3>
				<?php if ( ! empty( $top_posts ) ) : ?>
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Displays', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Conv.', 'leaky-paywall' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Rate', 'leaky-paywall' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_posts as $row ) :
							$title = get_the_title( $row->post_id );
							if ( ! $title ) {
								$title = __( '(Untitled)', 'leaky-paywall' );
							}
						?>
						<tr>
							<td><a href="<?php echo esc_url( get_the_permalink( $row->post_id ) ); ?>"><?php echo esc_html( $title ); ?></a></td>
							<td style="text-align: right;"><?php echo esc_html( number_format( $row->impressions ) ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( number_format( $row->conversions ) ); ?></td>
							<td style="text-align: right;"><?php echo esc_html( $row->rate . '%' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p class="lp-no-data"><?php esc_html_e( 'No paywall display data for this period.', 'leaky-paywall' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Active Subscriptions by Level -->
			<div class="lp-card">
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
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></th>
							<th><?php esc_html_e( 'Active', 'leaky-paywall' ); ?></th>
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

			<!-- Recent Subscribers -->
			<div class="lp-card">
				<h3><?php esc_html_e( 'Recent Subscribers', 'leaky-paywall' ); ?></h3>
				<?php
				$recent_txns = get_posts( array(
					'post_type'      => 'lp_transaction',
					'post_status'    => 'publish',
					'posts_per_page' => 30,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'meta_query'     => array(
						array(
							'key'     => '_transaction_status',
							'value'   => 'complete',
						),
					),
				) );

				if ( $recent_txns ) :
					$seen_emails = array();
					$shown       = 0;
				?>
				<table class="lp-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'leaky-paywall' ); ?></th>
							<th><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></th>
							<th><?php esc_html_e( 'Level', 'leaky-paywall' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $recent_txns as $txn ) :
						if ( $shown >= 10 ) {
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
							<td><?php echo esc_html( gmdate( 'M d, Y', strtotime( $txn->post_date ) ) ); ?></td>
							<td><?php if ( $user_link ) : ?><a href="<?php echo esc_url( $user_link ); ?>"><?php echo esc_html( $email ); ?></a><?php else : echo esc_html( $email ); endif; ?></td>
							<td><?php echo esc_html( $level_name ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No subscribers found.', 'leaky-paywall' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Quick Links -->
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
		$currency_symbol  = esc_js( html_entity_decode( leaky_paywall_get_current_currency_symbol() ) );
		?>
		<div class="lp-card lp-dashboard-chart">
			<h3><?php esc_html_e( 'Revenue', 'leaky-paywall' ); ?></h3>
			<div class="lp-dashboard-chart-wrap">
				<canvas id="lp-dashboard-revenue-chart"></canvas>
			</div>
		</div>
		<div class="lp-card lp-dashboard-chart">
			<h3><?php esc_html_e( 'New Signups', 'leaky-paywall' ); ?></h3>
			<div class="lp-dashboard-chart-wrap">
				<canvas id="lp-dashboard-signup-chart"></canvas>
			</div>
		</div>

		<script>
		(function() {
			var signupPaid = <?php echo wp_json_encode( $signup_paid_data ); ?>;
			var signupFree = <?php echo wp_json_encode( $signup_free_data ); ?>;
			var revenueData = <?php echo wp_json_encode( $revenue_data ); ?>;
			var currency = '<?php echo $currency_symbol; ?>';

			var sharedScales = {
				x: {
					grid: { display: false },
					ticks: { font: { size: 11 }, color: '#999', maxTicksLimit: 10 }
				}
			};

			var sharedOptions = {
				animation: false,
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { display: true, position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12 } } },
					tooltip: { backgroundColor: '#1d2327', titleFont: { size: 12 }, bodyFont: { size: 12 }, padding: 10, cornerRadius: 6 }
				}
			};

			/* Signup chart */
			new Chart(document.getElementById('lp-dashboard-signup-chart'), {
				type: 'line',
				options: Object.assign({}, sharedOptions, {
					scales: Object.assign({}, {
						x: sharedScales.x,
						y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 }, color: '#999' }, grid: { color: '#f5f5f5' }, border: { display: false } }
					})
				}),
				data: {
					labels: signupPaid.map(function(r) { return r.day; }),
					datasets: [
						{
							label: '<?php echo esc_js( __( 'Paid', 'leaky-paywall' ) ); ?>',
							data: signupPaid.map(function(r) { return r.count; }),
							borderColor: '#E26B2C',
							backgroundColor: 'rgba(226, 107, 44, 0.08)',
							fill: true,
							tension: 0.3,
							borderWidth: 2,
							pointRadius: 0,
							pointHoverRadius: 5,
							pointHoverBackgroundColor: '#E26B2C'
						},
						{
							label: '<?php echo esc_js( __( 'Free', 'leaky-paywall' ) ); ?>',
							data: signupFree.map(function(r) { return r.count; }),
							borderColor: '#3178D1',
							backgroundColor: 'rgba(49, 120, 209, 0.08)',
							fill: true,
							tension: 0.3,
							borderWidth: 2,
							pointRadius: 0,
							pointHoverRadius: 5,
							pointHoverBackgroundColor: '#3178D1'
						}
					]
				}
			});

			/* Revenue chart */
			new Chart(document.getElementById('lp-dashboard-revenue-chart'), {
				type: 'line',
				options: Object.assign({}, sharedOptions, {
					plugins: {
						legend: { display: false },
						tooltip: {
							backgroundColor: '#1d2327',
							titleFont: { size: 12 },
							bodyFont: { size: 12 },
							padding: 10,
							cornerRadius: 6,
							callbacks: {
								label: function(ctx) { return currency + ctx.parsed.y.toFixed(2); }
							}
						}
					},
					scales: Object.assign({}, {
						x: sharedScales.x,
						y: {
							beginAtZero: true,
							ticks: {
								font: { size: 11 },
								color: '#999',
								callback: function(v) { return currency + v.toFixed(0); }
							},
							grid: { color: '#f5f5f5' },
							border: { display: false }
						}
					})
				}),
				data: {
					labels: revenueData.map(function(r) { return r.day; }),
					datasets: [{
						label: '<?php echo esc_js( __( 'Revenue', 'leaky-paywall' ) ); ?>',
						data: revenueData.map(function(r) { return r.amount; }),
						borderColor: '#38A65B',
						backgroundColor: 'rgba(56, 166, 91, 0.08)',
						fill: true,
						tension: 0.3,
						borderWidth: 2,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHoverBackgroundColor: '#38A65B'
					}]
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

	private function get_top_converting_content( $period, $type = 'paid' ) {
		global $wpdb;

		$args_period = leaky_paywall_insights_get_formatted_period( $period );
		$after_date  = gmdate( 'Y-m-d H:i:s', strtotime( $args_period ) );
		$price_cond  = 'paid' === $type ? '> 0' : '= 0';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_nag.meta_value AS nag_loc, COUNT(*) AS total
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_price
					 ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
				 INNER JOIN {$wpdb->postmeta} pm_status
					 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
				 INNER JOIN {$wpdb->postmeta} pm_nag
					 ON p.ID = pm_nag.post_id AND pm_nag.meta_key = '_nag_location_id'
				 WHERE p.post_type = 'lp_transaction'
				 AND p.post_date > %s
				 AND pm_status.meta_value != 'incomplete'
				 AND CAST(pm_price.meta_value AS DECIMAL(10,2)) {$price_cond}
				 AND pm_nag.meta_value != ''
				 GROUP BY pm_nag.meta_value
				 ORDER BY total DESC
				 LIMIT 3",
				$after_date
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		$content = array();
		foreach ( $results as $row ) {
			$post_id = absint( $row->nag_loc );
			$title   = get_the_title( $post_id );

			if ( ! $title ) {
				$title = __( '(Untitled)', 'leaky-paywall' );
			}

			$content[] = array(
				'title' => $title,
				'url'   => get_the_permalink( $post_id ),
				'count' => (int) $row->total,
			);
		}

		return $content;
	}
}
