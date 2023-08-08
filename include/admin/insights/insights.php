<?php

/**
 * Registers Leaky Paywall Insights class
 *
 * @package Leaky Paywall
 */

/**
 * Load the Insights Class
 */
class Leaky_Paywall_Insights
{

	public function insights_page()
	{
?>

		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo LEAKY_PAYWALL_URL . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title">Insights</h1>
				</span>
			</div>
		</div>

		<?php

		if (isset($_GET['tab'])) {
			$tab = sanitize_text_field(wp_unslash($_GET['tab']));
		} elseif (isset($_GET['page']) && 'leaky-paywall-insights' === $_GET['page']) {
			$tab = 'overview';
		} else {
			$tab = '';
		}

		$insights_tabs = $this->get_insights_tabs();
		$current_tab = apply_filters('leaky_paywall_current_tab', $tab, $insights_tabs);

		$this->output_tabs($current_tab);

		echo '<div id="lp-wit-app">';

		$this->output_data($current_tab);

		echo '</div>';
	}

	public function output_data($current_tab)
	{

		switch ($current_tab) {
			case 'overview':
				$this->general_insights();
				break;
			case 'subscriptions':
				$this->subscriptions_insights();
				break;
			case 'content':
				$this->content_insights();
				break;
			case 'attribution':
				$this->attribution_insights();
				break;

			default:
				// do nothing
				break;
		}
	}

	public function general_insights()
	{

		$revenue = leaky_paywall_reports_get_total_revenue('30 days');
		$new_paid_subs = leaky_paywall_reports_get_new_paid_subs('30 days');
		$new_free_subs = leaky_paywall_reports_get_new_free_subs('30 days');

		?>
		<h3>Last 30 Days</h3>

		<div class="card-stats">
			<div class="card"><span class="dashicons dashicons-chart-bar"></span>
				<div class="card-content">
					<div class="card-title">Total Revenue</div>
					<div class="card-amount"><?php echo $revenue; ?></div>
				</div>
			</div>
			<div class="card"><span class="dashicons dashicons-money-alt"></span>
				<div class="card-content">
					<div class="card-title">New Paid Subscribers</div>
					<div class="card-amount"><?php echo $new_paid_subs; ?></div>
				</div>
			</div>
			<div class="card"><span class="dashicons dashicons-admin-users"></span>
				<div class="card-content">
					<div class="card-title">New Free Subscribers</div>
					<div class="card-amount"><?php echo $new_free_subs; ?></div>
				</div>
			</div>
			<!-- <div class="card"><span class="dashicons dashicons-heart"></span>
				<div class="card-content">
					<div class="card-title">New Gift Subscribers</div>
					<div class="card-amount">3</div>
				</div>
			</div> -->
		</div>
	<?php
	}

	public function subscriptions_insights()
	{

		$levels = leaky_paywall_get_levels();
		$data = array();

	?>
		<h3>Top Active Subscriptions</h3>

		<?php

		foreach ($levels as $id => $level) {

			if (isset($level['deleted']) && $level['deleted'] == 1) {
				continue;
			}

			$total_subscribers = 0;
			$total_subscribers = $this->get_total_active_subscribers($id);

			$data[$level['label']] = $total_subscribers;
		}

		arsort($data);

		echo '<table class="wp-list-table widefat fixed striped table-view-list">';

		echo '<thead><tr><th>Subscription Level</th><th>Active Subscribers</th></tr></thead>';

		foreach ($data as $key => $value) {
			echo '<tr><td>' . esc_html( stripslashes( $key ) ) . '</td><td>' . absint( $value ) . '</td></tr>';
		}

		echo '</table>';
	}

	public function get_total_active_subscribers($level_id)
	{

		$cache_key = 'lp-active-subs-' . $level_id;

		if (false === ($total = get_transient($cache_key))) {

			$mode = leaky_paywall_get_current_mode();
			$site = leaky_paywall_get_current_site();

			$args = array(
				'fields' => 'ID',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
						'value'   => $level_id,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
						'value'   => 'active',
						'compare' => 'LIKE',
					)
				)
			);

			$subscribers = new WP_User_Query($args);
			$total = $subscribers->get_total();

			set_transient($cache_key, $total, 300);
		}

		return $total;
	}

	public function content_insights()
	{

		$paid_content = leaky_paywall_reports_get_paid_content('30 days');
		$free_content = leaky_paywall_reports_get_free_content('30 days');

		?>
		<h3>Top Content Leading to Conversion</h3>

		<h4>Last 30 Days</h4>

		<p>Content the user was viewing when the nag was displayed and they clicked a "subscribe" link</p>

		<div class="content-coversions">
			<div class="content-conversion-list">
				<h3>Paid</h3>
				<ol class="">
					<?php foreach ($paid_content as $item) {
						echo '<li>' . $item . '</li>';
					} ?>
				</ol>
			</div>
			<div class="content-conversion-list">
				<h3>Free</h3>
				<ol class="">
					<?php
					foreach ($free_content as $item) {
						echo '<li>' . $item . '</li>';
					} ?>
				</ol>
			</div>
		</div>

	<?php
	}

	public function attribution_insights()
	{

		$args = array(
			'meta_query' => array(
				array(
					'key' => '_leaky_paywall_attribution_survey',
					'compare' => 'EXISTS',
				),
			)
		);

		$subscribers = new WP_User_Query($args);

		$responses = array();

		foreach ($subscribers->get_results() as $user) {

			$response = trim(get_user_meta($user->ID, '_leaky_paywall_attribution_survey', true));

			if ($response && $response != 'other') {
				$responses[] = $response;
			}
		}

		$counted = array_count_values($responses);

		arsort($counted);

	?>
		<h3>Attribution Survey Results</h3>

		<table class="wp-list-table widefat fixed striped table-view-list">

			<thead>
				<tr>
					<th>Attribution</th>
					<th>Amount</th>
				</tr>
			</thead>

			<?php
			foreach ($counted as $key => $value) {
				echo '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
			} ?>

		</table>

	<?php

	}


	public function output_tabs($current_tab)
	{
		if (!in_array($current_tab, $this->get_insights_tabs(), true)) {
			return;
		}

		$all_tabs = $this->get_insights_tabs();

	?>
		<h2 class="nav-tab-wrapper" style="margin-bottom: 10px; margin-top: 20px;">

			<?php foreach ($all_tabs as $tab) {

				$class = $tab == $current_tab ? 'nav-tab-active' : '';
				$admin_url = 'insights' == $tab ? admin_url('admin.php?page=leaky-paywall-insights') : admin_url('admin.php?page=leaky-paywall-insights&tab=' . $tab);

			?>
				<a href="<?php echo esc_url($admin_url); ?>" class="nav-tab <?php echo esc_attr($class); ?>"><?php echo esc_html(ucfirst($tab)); ?></a>
			<?php
			} ?>

		</h2>
<?php
	}

	public function get_insights_tabs()
	{

		$tabs = array(
			'overview',
			'subscriptions',
			'content',
			'attribution'
		);

		return apply_filters('leaky_paywall_insights_tabs', $tabs);
	}
}
