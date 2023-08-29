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
					<img class="lp-header-logo" width="200" src="<?php echo esc_url(LEAKY_PAYWALL_URL) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e('Insights', 'leaky-paywall'); ?></h1>
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
			default:
				// do nothing
				break;
		}

		// allow other extensions to hook in here
		do_action('leaky_paywall_output_insights_data', $current_tab);
	}

	public function general_insights()
	{

		if (isset($_POST['lp_insights_filter_date_field']) && wp_verify_nonce(sanitize_key($_POST['lp_insights_filter_date_field']), 'lp_insights_filter_date')) {
			$period = isset($_POST['filter-date-range']) ? sanitize_text_field($_POST['filter-date-range']) : '30 days';
		} else {
			$period = '30 days';
		}

		$revenue = leaky_paywall_insights_get_total_revenue($period);
		$new_paid_subs = leaky_paywall_insights_get_new_paid_subs($period);
		$new_free_subs = leaky_paywall_insights_get_new_free_subs($period);

		?>

		<p>
		<form id="leaky_paywall_insights_date_range_filter" method="POST">
			<label for="filter-by-status" class="screen-reader-text"><?php esc_html_e('Filter by date range', 'leaky-paywall'); ?></label>
			<select name="filter-date-range" id="filter-by-date-range">
				<option value="today" <?php selected($period, 'today'); ?>><?php esc_html_e('Last 24 hours', 'leaky-paywall'); ?></option>
				<option value="7 days" <?php selected($period, '7 days'); ?>><?php esc_html_e('Last 7 days', 'leaky-paywall'); ?></option>
				<option value="30 days" <?php selected($period, '30 days'); ?>><?php esc_html_e('Last 30 days', 'leaky-paywall'); ?></option>
				<option value="3 months" <?php selected($period, '3 months'); ?>><?php esc_html_e('Last 3 months', 'leaky-paywall'); ?></option>
			</select>

			<input name="filter_action" id="lp_insights_date_range_filter_submit" class="button" value="Filter" type="submit">
			<?php wp_nonce_field('lp_insights_filter_date', 'lp_insights_filter_date_field'); ?>
		</form>
		</p>

		<div class="card-stats">
			<div class="card"><span class="dashicons dashicons-chart-bar"></span>
				<div class="card-content">
					<div class="card-title"><?php esc_html_e('Total Revenue', 'leaky-paywall'); ?></div>
					<div class="card-amount"><?php echo esc_html($revenue); ?></div>
				</div>
			</div>
			<div class="card"><span class="dashicons dashicons-money-alt"></span>
				<div class="card-content">
					<div class="card-title"><?php esc_html_e('New Paid Subscribers', 'leaky-paywall'); ?></div>
					<div class="card-amount"><?php echo esc_html($new_paid_subs); ?></div>
				</div>
			</div>
			<div class="card"><span class="dashicons dashicons-admin-users"></span>
				<div class="card-content">
					<div class="card-title"><?php esc_html_e('New Free Subscribers', 'leaky-paywall'); ?></div>
					<div class="card-amount"><?php echo esc_html($new_free_subs); ?></div>
				</div>
			</div>

			<?php do_action('leaky_paywall_card_stats'); ?>
		</div>

		<?php do_action('leaky_paywall_after_card_stats'); ?>

	<?php
	}

	public function subscriptions_insights()
	{

		$levels = leaky_paywall_get_levels();
		$data = array();

	?>
		<h3><?php esc_html_e('Top Active Subscriptions', 'leaky-paywall'); ?></h3>

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
			echo '<tr><td>' . esc_html(stripslashes($key)) . '</td><td>' . absint($value) . '</td></tr>';
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

		$paid_content = leaky_paywall_insights_get_paid_content('30 days');
		$free_content = leaky_paywall_insights_get_free_content('30 days');

		?>
		<h3><?php esc_html_e('Top Content Leading to Conversion', 'leaky-paywall'); ?></h3>

		<h4><?php esc_html_e('Last 30 Days', 'leaky-paywall'); ?></h4>

		<p><?php esc_html_e('Content the user was viewing when the nag was displayed and they clicked a "subscribe" link', 'leaky-paywall'); ?></p>

		<div class="content-coversions">
			<div class="content-conversion-list">
				<h3>Paid</h3>
				<ol class="">
					<?php foreach ($paid_content as $item) {
						echo '<li>' . esc_html($item) . '</li>';
					} ?>
				</ol>
			</div>
			<div class="content-conversion-list">
				<h3>Free</h3>
				<ol class="">
					<?php
					foreach ($free_content as $item) {
						echo '<li>' . esc_html($item) . '</li>';
					} ?>
				</ol>
			</div>
		</div>

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
		);

		return apply_filters('leaky_paywall_insights_tabs', $tabs);
	}
}
