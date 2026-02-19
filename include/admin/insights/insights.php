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
			case 'paywall':
				$this->paywall_insights();
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
		$counts = $this->get_all_active_subscriber_counts();

	?>
		<h3><?php esc_html_e('Top Active Subscriptions', 'leaky-paywall'); ?></h3>

		<?php

		foreach ($levels as $id => $level) {

			if (isset($level['deleted']) && $level['deleted'] == 1) {
				continue;
			}

			$data[$level['label']] = isset($counts[$id]) ? $counts[$id] : 0;
		}

		arsort($data);

		echo '<table class="wp-list-table widefat fixed striped table-view-list">';

		echo '<thead><tr><th>Subscription Level</th><th>Active Subscribers</th></tr></thead>';

		foreach ($data as $key => $value) {
			echo '<tr><td>' . esc_html(stripslashes($key)) . '</td><td>' . absint($value) . '</td></tr>';
		}

		echo '</table>';
	}

	/**
	 * Get active subscriber counts for all levels in a single query.
	 *
	 * @return array Associative array of level_id => count.
	 */
	public function get_all_active_subscriber_counts()
	{
		$cache_key = 'lp-active-subs-all';

		if (false === ($counts = get_transient($cache_key))) {

			global $wpdb;

			$mode = leaky_paywall_get_current_mode();
			$site = leaky_paywall_get_current_site();

			$level_key  = '_issuem_leaky_paywall_' . $mode . '_level_id' . $site;
			$status_key = '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site;

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT lm.meta_value AS level_id, COUNT(*) AS total
					 FROM {$wpdb->usermeta} lm
					 INNER JOIN {$wpdb->usermeta} sm
						 ON lm.user_id = sm.user_id
						 AND sm.meta_key = %s
						 AND sm.meta_value IN ('active', 'pending_cancel', 'trial')
					 WHERE lm.meta_key = %s
					 GROUP BY lm.meta_value",
					$status_key,
					$level_key
				)
			);

			$counts = array();
			if ($results) {
				foreach ($results as $row) {
					$counts[$row->level_id] = (int) $row->total;
				}
			}

			set_transient($cache_key, $counts, 300);
		}

		return $counts;
	}

	public function content_insights()
	{

		if (isset($_POST['lp_insights_filter_date_field']) && wp_verify_nonce(sanitize_key($_POST['lp_insights_filter_date_field']), 'lp_insights_filter_date')) {
			$period = isset($_POST['filter-date-range']) ? sanitize_text_field($_POST['filter-date-range']) : '30 days';
		} else {
			$period = '30 days';
		}

		$paid_content = leaky_paywall_insights_get_paid_content( $period );
		$free_content = leaky_paywall_insights_get_free_content( $period );

		?>
		<h3><?php esc_html_e('Top Content Leading to Conversion', 'leaky-paywall'); ?></h3>

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
		<h2 class="nav-tab-wrapper">

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

	public function paywall_insights()
	{
		if ( isset( $_POST['lp_insights_filter_date_field'] ) && wp_verify_nonce( sanitize_key( $_POST['lp_insights_filter_date_field'] ), 'lp_insights_filter_date' ) ) {
			$period = isset( $_POST['filter-date-range'] ) ? sanitize_text_field( $_POST['filter-date-range'] ) : '30 days';
		} else {
			$period = '30 days';
		}

		$total_impressions = LP_Nag_Impressions::get_total_impressions( $period );
		$by_nag_type       = LP_Nag_Impressions::get_impressions_by_nag_type( $period );
		$top_posts         = LP_Nag_Impressions::get_top_posts_with_conversions( $period );

		?>

		<h3><?php esc_html_e( 'Paywall Displays', 'leaky-paywall' ); ?></h3>

		<p>
		<form id="leaky_paywall_insights_date_range_filter" method="POST">
			<label for="filter-by-date-range" class="screen-reader-text"><?php esc_html_e( 'Filter by date range', 'leaky-paywall' ); ?></label>
			<select name="filter-date-range" id="filter-by-date-range">
				<option value="today" <?php selected( $period, 'today' ); ?>><?php esc_html_e( 'Last 24 hours', 'leaky-paywall' ); ?></option>
				<option value="7 days" <?php selected( $period, '7 days' ); ?>><?php esc_html_e( 'Last 7 days', 'leaky-paywall' ); ?></option>
				<option value="30 days" <?php selected( $period, '30 days' ); ?>><?php esc_html_e( 'Last 30 days', 'leaky-paywall' ); ?></option>
				<option value="3 months" <?php selected( $period, '3 months' ); ?>><?php esc_html_e( 'Last 3 months', 'leaky-paywall' ); ?></option>
			</select>

			<input name="filter_action" id="lp_insights_date_range_filter_submit" class="button" value="Filter" type="submit">
			<?php wp_nonce_field( 'lp_insights_filter_date', 'lp_insights_filter_date_field' ); ?>
		</form>
		</p>

		<div class="card-stats">
			<div class="card"><span class="dashicons dashicons-visibility"></span>
				<div class="card-content">
					<div class="card-title"><?php esc_html_e( 'Total Paywall Displays', 'leaky-paywall' ); ?></div>
					<div class="card-amount"><?php echo esc_html( number_format( $total_impressions ) ); ?></div>
				</div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Displays by Type', 'leaky-paywall' ); ?></h3>

		<?php if ( empty( $by_nag_type ) ) : ?>
			<p><?php esc_html_e( 'No paywall display data found for selected time period.', 'leaky-paywall' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Paywall Type', 'leaky-paywall' ); ?></th>
						<th><?php esc_html_e( 'Displays', 'leaky-paywall' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $by_nag_type as $row ) : ?>
						<tr>
							<td><?php echo esc_html( LP_Nag_Impressions::get_nag_type_label( $row->nag_type ) ); ?></td>
							<td><?php echo esc_html( number_format( (int) $row->total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Top Content by Paywall Displays', 'leaky-paywall' ); ?></h3>
		<p><?php esc_html_e( 'Posts where the paywall was shown most often, with conversion rates.', 'leaky-paywall' ); ?></p>

		<?php if ( empty( $top_posts ) ) : ?>
			<p><?php esc_html_e( 'No paywall display data found for selected time period.', 'leaky-paywall' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Content', 'leaky-paywall' ); ?></th>
						<th><?php esc_html_e( 'Displays', 'leaky-paywall' ); ?></th>
						<th><?php esc_html_e( 'Conversions', 'leaky-paywall' ); ?></th>
						<th><?php esc_html_e( 'Conversion Rate', 'leaky-paywall' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_posts as $row ) :
						$title = get_the_title( $row->post_id );
						$url   = get_the_permalink( $row->post_id );
					?>
						<tr>
							<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $title ? $title : '#' . $row->post_id ); ?></a></td>
							<td><?php echo esc_html( number_format( $row->impressions ) ); ?></td>
							<td><?php echo esc_html( number_format( $row->conversions ) ); ?></td>
							<td><?php echo esc_html( $row->rate . '%' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php
	}

	public function get_insights_tabs()
	{

		$tabs = array(
			'overview',
			'subscriptions',
			'content',
			'paywall',
		);

		return apply_filters('leaky_paywall_insights_tabs', $tabs);
	}
}
