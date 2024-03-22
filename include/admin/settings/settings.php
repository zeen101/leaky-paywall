<?php

/**
 * Registers Leaky Paywall Settings class
 *
 * @package Leaky Paywall
 */

/**
 * Load the Restrictions Class
 */
class Leaky_Paywall_Settings
{

	public function settings_page()
	{

		if (isset($_GET['tab'])) {
			$tab = sanitize_text_field(wp_unslash($_GET['tab']));
		} elseif (isset($_GET['page']) && 'issuem-leaky-paywall' === $_GET['page']) {
			$tab = 'general';
		} else {
			$tab = '';
		}

		$settings = $this->get_settings();
		$settings_tabs = $this->get_settings_tabs();
		$current_tab = apply_filters('leaky_paywall_current_tab', $tab, $settings_tabs);
		$sections = $this->get_settings_tab_sections($current_tab);
		$current_section = !empty($_GET['section']) && !empty($sections) ? sanitize_text_field($_GET['section']) : 'general';

		$settings_updated = $this->process_settings_update($current_tab, $current_section);
		$level_deleted = $this->process_level_deleted($current_tab, $current_section);

?>

		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url(LEAKY_PAYWALL_URL) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title">Settings</h1>
				</span>
			</div>
		</div>

		<div class="wrap">

			<div style="width:75%;" class="postbox-container">

				<?php
				if (!isset($settings['page_for_subscription']) || !$settings['page_for_subscription']) {
				?>
					<p>Need help getting started? <a target="_blank" href="https://docs.leakypaywall.com/article/39-setting-up-leaky-paywall">See our guide</a> or get setup and support with <a target="_blank" href="https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_medium=plugin&utm_source=getting_started&utm_campaign=settings">Leaky Paywall Pro.</a></p>
				<?php
				}

				if ($settings_updated) {
				?>
					<div class="updated">
						<p><strong><?php esc_html_e('Settings Updated', 'leaky-paywall'); ?></strong></p>
					</div>
				<?php
				}

				// output tabs
				$this->output_tabs($current_tab);

				// output sections nav
				$this->output_sections_nav($current_tab, $sections, $current_section);

				do_action('leaky_paywall_before_settings', $current_tab);

				?>

				<form id="leaky-paywall-settings-form" method="post" action="">

					<?php $this->output_settings_fields($current_tab, $current_section); ?>

				</form>


			</div> <!-- postbox-container -->

			<div class="leaky-paywall-sidebar" style="float: right; width: 23%; margin-top: 110px;">

				<?php if (!wp_script_is('leaky_paywall_multiple_levels_js', 'enqueued')) {
				?>
					<div class="leaky-paywall-sidebar-widget">
						<h3>Upgrade to Pro</h3>
						<p class="description">
							Gain access to our proven subscription building system and 40+ Leaky Paywall extensions when you upgrade
						</p>
						<ul>
							<li>Personal setup meeting and priority support</li>
							<li>One-on-one strategic support meeting</li>
							<li>Free-to-paid subscription plans, donations, pay per article, timewall, and flipbook access</li>
							<li>Add smart on-site subscriber level targeting for your promotions</li>
							<li>Group and corporate access plans</li>
							<li>Paywall hardening to stop incognito browsing</li>
							<li>Integrations with CRMs, circulation software, and payment gateways</li>
							<li>Sell single purchase access to multiple websites</li>
						</ul>

						<p>
							<a class="button" target="_blank" href="https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_medium=plugin&utm_source=sidebar&utm_campaign=settings">Upgrade Now</a>
						</p>
					</div>
				<?php
				} else {
				?>
					<div class="leaky-paywall-sidebar-widget">
						<h3>Documentation</h3>

						<ul>
							<li><a target="_blank" href="https://docs.leakypaywall.com/category/40-getting-started">Getting Started</a></li>
							<li><a target="_blank" href="https://docs.leakypaywall.com/category/248-revenue">Revenue</a></li>
							<li><a target="_blank" href="https://docs.leakypaywall.com/category/64-how-to-faqs">FAQ</a></li>
							<li><a target="_blank" href="https://docs.leakypaywall.com/category/250-troubleshooting">Troubleshooting</a></li>
							<li><a target="_blank" href="https://docs.leakypaywall.com/category/249-developers">Developers</a></li>

						</ul>

					</div>
				<?php
				} ?>

			</div> <!-- sidebar -->
		</div> <!-- wrap -->

	<?php
	}

	public function output_tabs($current_tab)
	{

		if (!in_array($current_tab, $this->get_settings_tabs(), true)) {
			return;
		}

		$all_tabs = $this->get_settings_tabs();

	?>
		<h2 class="nav-tab-wrapper" style="margin-bottom: 10px;">

			<?php foreach ($all_tabs as $tab) {

				$class = $tab == $current_tab ? 'nav-tab-active' : '';
				$admin_url = 'general' == $tab ? admin_url('admin.php?page=issuem-leaky-paywall') : admin_url('admin.php?page=issuem-leaky-paywall&tab=' . $tab);

			?>
				<a href="<?php echo esc_url($admin_url); ?>" class="nav-tab <?php echo esc_attr($class); ?>"><?php echo esc_html(ucfirst($tab)); ?></a>
			<?php
			} ?>

		</h2>
	<?php

	}

	public function output_sections_nav($current_tab, $sections, $current_section)
	{

		if ($current_tab == 'general' || !$current_tab) {
			$admin_url = admin_url('admin.php?page=issuem-leaky-paywall');
		} else {
			$admin_url = admin_url('admin.php?page=issuem-leaky-paywall&tab=' . $current_tab);
		}

	?>

		<div class="wp-clearfix">
			<ul class="subsubsub leaky-paywall-settings-sub-nav">

				<?php
				if (!empty($sections)) {

					// alphabetize sections, except for first item
					$first_item = array_shift($sections);
					sort($sections);
					array_unshift($sections, $first_item);

					$i = 1;
					foreach ($sections as $section) {

						$class = $section == $current_section ? 'current' : '';

				?>
						<li>
							<a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($admin_url); ?>&section=<?php echo esc_attr($section); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $section))); ?></a>

							<?php if (count($sections) != $i) {
								echo '|';
							}
							?>
						</li>
				<?php
						$i++;
					}
				}

				?>

			</ul>

		</div>



		<?php
	}

	public function output_settings_fields($current_tab, $current_section)
	{

		switch ($current_tab) {
			case 'general':
				$this->output_general_settings($current_section);
				break;
			case 'restrictions':
				$this->output_restrictions_settings($current_section);
				break;
			case 'subscriptions':
				$this->output_subscriptions_settings($current_section);
				break;
			case 'payments':
				$this->output_payments_settings($current_section);
				break;
			case 'emails':
				$this->output_emails_settings($current_section);
				break;
			case 'licenses':
				$this->output_licenses_settings($current_section);
				break;
			case 'help':
				$this->output_help_settings($current_section);
				break;
			default:
				// do nothing
				break;
		}

		// allow other extensions to hook in here
		do_action('leaky_paywall_output_settings_fields', $current_tab, $current_section);

		wp_nonce_field('leaky_paywall_update_settings_nonce', 'leaky_paywall_update_settings_nonce_field');

		$hide_submit_tabs = apply_filters('leaky_paywall_hide_submit_tabs', array('licenses', 'help'));

		if (!in_array($current_tab, $hide_submit_tabs)) {
		?>
			<p class="submit">
				<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php esc_attr_e('Save Settings', 'leaky-paywall'); ?>" />
			</p>
			<?php
		}
	}

	public function output_general_settings($current_section)
	{

		global $leaky_paywall;

		$settings = $this->get_settings();

		if ($current_section == 'general') {

			do_action('leaky_paywall_before_general_settings');

			if (is_multisite_premium() && is_super_admin()) { ?>

				<h2><?php esc_html_e('Site Wide Options', 'leaky-paywall'); ?></h2>

				<table id="leaky_paywalll_multisite_settings" class="leaky-paywall-table">
					<tr>
						<th rowspan="1"> <?php esc_html_e('Enable Settings Site Wide?', 'leaky-paywall'); ?></th>
						<td>
						<td><input type="checkbox" id="site_wide_enabled" name="site_wide_enabled" <?php checked($this->is_site_wide_enabled()); ?> /></td>
						</td>
					</tr>
				</table>

			<?php } ?>

			<div>
				<?php 
				//if ( version_compare( $leaky_paywall->get_db_version(), '1.0.6', '<' ) ) {
					echo 'You are using an old version of the dabatase, please backup your current database and run this <a href="' . esc_url(admin_url()) . 'admin.php?page=issuem-leaky-paywall&section=use-transaction-tables">migration script</a>.';
				//}
				?>
			</div>

			<h2><?php esc_html_e('General Settings', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_administrator_options" class="form-table leaky-paywall-settings-table">

				<tr>
					<th><?php esc_html_e('Subscribe or Login Message', 'leaky-paywall'); ?></th>
					<td>
						<textarea id="subscribe_login_message" class="large-text" name="subscribe_login_message" cols="50" rows="10"><?php echo wp_kses(stripslashes($settings['subscribe_login_message']), $this->allowed_html()); ?></textarea>
						<p class="description">
							<?php esc_html_e('Available replacement variables: {{SUBSCRIBE_URL}}  {{LOGIN_URL}}', 'leaky-paywall'); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Upgrade Message', 'leaky-paywall'); ?></th>
					<td>
						<textarea id="subscribe_upgrade_message" class="large-text" name="subscribe_upgrade_message" cols="50" rows="10"><?php echo wp_kses(stripslashes($settings['subscribe_upgrade_message']), $this->allowed_html()); ?></textarea>
						<p class="description">
							<?php esc_html_e('Available replacement variables: {{SUBSCRIBE_URL}}', 'leaky-paywall'); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('CSS Style', 'leaky-paywall'); ?></th>
					<td>
						<select id='css_style' name='css_style'>
							<option value='default' <?php selected('default', $settings['css_style']); ?>><?php esc_html_e('Default', 'leaky-paywall'); ?></option>
							<option value='none' <?php selected('none', $settings['css_style']); ?>><?php esc_html_e('None', 'leaky-paywall'); ?></option>
						</select>
					</td>
				</tr>

				<tr class="general-options">
					<th><?php esc_html_e('User Account Creation', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="remove_username_field" name="remove_username_field" <?php checked('on', $settings['remove_username_field']); ?> /> <?php esc_html_e('Remove the username field during registration and use their email address to generate an account username', 'leaky-paywall'); ?></td>
				</tr>

				<tr class="general-options">
					<th><?php esc_html_e('User Account Deletion', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="enable_user_delete_account" name="enable_user_delete_account" <?php checked('on', $settings['enable_user_delete_account']); ?> /> <?php esc_html_e('Allow users to delete their account from the My Profile page', 'leaky-paywall'); ?></td>
				</tr>

				<tr class="general-options">
					<th><?php esc_html_e('Expiration Dates', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="add_expiration_dates" name="add_expiration_dates" <?php checked('on', $settings['add_expiration_dates']); ?> /> <?php esc_html_e('If a current subscriber renews/changes their subscription level, add additional time to their current expiration date. If unchecked, their new expiration date will be calculated from the date of subscription level renewal/change.', 'leaky-paywall'); ?></td>
				</tr>

				<tr class="general-options">
					<th><?php esc_html_e('WP REST API', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="enable_rest_api" name="enable_rest_api" <?php checked('on', $settings['enable_rest_api']); ?> /> <?php esc_html_e('Enable the WP REST API for Leaky Paywall and add subscriber data to the User endpoint.', 'leaky-paywall'); ?></td>
				</tr>


			</table>

			<?php do_action('leaky_paywall_after_general_settings'); ?>
			<?php do_action('leaky_paywall_settings_form', $settings); // here for backwards compatibility.
			?>

		<?php } else if ($current_section == 'pages') { ?>

			<table id="leaky_paywall_administrator_options" class="form-table leaky-paywall-settings-table">

				<tr>
					<th><?php esc_html_e('Page for Log In', 'leaky-paywall'); ?></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'     => 'page_for_login',
								'echo'     => 1,
								'show_option_none' => esc_attr__('&mdash; Select &mdash;'),
								'option_none_value' => '0',
								'selected' => esc_attr($settings['page_for_login']),
							)
						);
						?>
						<p class="description">
							<?php
							/* Translators: %s - shortcode for Leaky Paywall login form */
							printf(esc_attr__('Add this shortcode to your Log In page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_login]');
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Page for Subscribe Cards', 'leaky-paywall'); ?></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'     => 'page_for_subscription',
								'echo'     => 1,
								'show_option_none' => esc_attr__('&mdash; Select &mdash;'),
								'option_none_value' => '0',
								'selected' => esc_attr($settings['page_for_subscription']),
							)
						);
						?>
						<p class="description">
							<?php
							/* Translators: %s - shortcode for Leaky Paywall subscription cards */
							printf(esc_attr__('Add this shortcode to your Subscription page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_subscription]');
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Page for Register Form', 'leaky-paywall'); ?></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'     => 'page_for_register',
								'echo'     => 1,
								'show_option_none' => esc_attr__('&mdash; Select &mdash;'),
								'option_none_value' => '0',
								'selected' => esc_attr($settings['page_for_register']),
							)
						);
						?>
						<p class="description">
							<?php
							/* Translators: %s - shortcode for Leaky Paywall registration form */
							printf(esc_attr__('Add this shortcode to your register page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_register_form]');
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Page for Profile', 'leaky-paywall'); ?></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'     => 'page_for_profile',
								'echo'     => 1,
								'show_option_none' => esc_attr__('&mdash; Select &mdash;'),
								'option_none_value' => '0',
								'selected' => esc_attr($settings['page_for_profile']),
							)
						);
						?>
						<p class="description">
							<?php
							/* Translators: %s - shortcode for Leaky Paywall profile */
							printf(esc_attr__('Add this shortcode to your Profile page: %s. This page displays the account information for subscribers.  This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_profile]');
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Confirmation Page', 'leaky-paywall'); ?></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'     => 'page_for_after_subscribe',
								'echo'     => 1,
								'show_option_none' => esc_attr__('&mdash; Select &mdash;'),
								'option_none_value' => '0',
								'selected' => esc_attr($settings['page_for_after_subscribe']),
							)
						);
						?>
						<p class="description"><?php esc_html_e('Page a subscriber is redirected to after they subscribe.  This page cannot be restricted.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

			</table>

		<?php } else if ($current_section == 'use-transaction-tables') { ?>

			MIGRATING

		<?php } ?>


	<?php

	}

	public function output_restrictions_settings($current_section)
	{

		$settings = $this->get_settings();

	?>

		<h2><?php esc_html_e('Content Restrictions', 'leaky-paywall'); ?></h2>

		<p>Use the settings below to set your content restrictions. You can also set individual post restrictions with the <a target="_blank" href="https://docs.leakypaywall.com/article/43-how-to-restrict-individual-articles-visibility-setting">visibility settings</a>.</p>

		<table id="leaky_paywall_default_restriction_options" class="form-table">

			<tr class="restriction-options">
				<th><?php esc_html_e('Limited Article Cookie Expiration', 'leaky-paywall'); ?></th>
				<td>
					<input type="number" id="cookie_expiration" class="small-text" name="cookie_expiration" value="<?php echo esc_attr($settings['cookie_expiration']); ?>" />
					<select id="cookie_expiration_interval" name="cookie_expiration_interval">
						<option value="hour" <?php selected('hour', $settings['cookie_expiration_interval']); ?>><?php esc_html_e('Hour(s)', 'leaky-paywall'); ?></option>
						<option value="day" <?php selected('day', $settings['cookie_expiration_interval']); ?>><?php esc_html_e('Day(s)', 'leaky-paywall'); ?></option>
						<option value="week" <?php selected('week', $settings['cookie_expiration_interval']); ?>><?php esc_html_e('Week(s)', 'leaky-paywall'); ?></option>
						<option value="month" <?php selected('month', $settings['cookie_expiration_interval']); ?>><?php esc_html_e('Month(s)', 'leaky-paywall'); ?></option>
						<option value="year" <?php selected('year', $settings['cookie_expiration_interval']); ?>><?php esc_html_e('Year(s)', 'leaky-paywall'); ?></option>
					</select>
					<p class="description"><?php esc_html_e('Choose length of time when a visitor can once again read your articles/posts (up to the # of articles allowed).', 'leaky-paywall'); ?></p>
				</td>
			</tr>

			<?php
			if (ACTIVE_ISSUEM) {
			?>
				<tr class="restriction-options ">
					<th><?php esc_html_e('IssueM PDF Downloads', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="restrict_pdf_downloads" name="restrict_pdf_downloads" <?php checked('on', $settings['restrict_pdf_downloads']); ?> /> <?php esc_html_e('Restrict PDF issue downloads to active Leaky Paywall subscribers.', 'leaky-paywall'); ?></td>
				</tr>
			<?php
			}
			?>



			<tr class="restriction-options">
				<th>
					<label for="restriction-post-type-' . $row_key . '"><?php esc_html_e('Restrictions', 'leaky-paywall'); ?></label>
				</th>
				<td id="issuem-leaky-paywall-restriction-rows">

					<table>
						<tr>
							<th>Post Type</th>
							<th>Taxonomy <span style="font-weight: normal; font-size: 11px; color: #999;"> Category,tag,etc.</span></th>
							<th>Number Allowed</th>
							<th>&nbsp;</th>
						</tr>

						<?php
						$last_key = -1;
						if (!empty($settings['restrictions']['post_types'])) {

							foreach ($settings['restrictions']['post_types'] as $key => $restriction) {

								if (!is_numeric($key)) {
									continue;
								}

								build_leaky_paywall_default_restriction_row($restriction, $key);

								$last_key = $key;
							}
						}
						?>
					</table>
				</td>
			</tr>



			<tr class="restriction-options">
				<th>&nbsp;</th>
				<td style="padding-top: 0;">
					<script type="text/javascript" charset="utf-8">
						var leaky_paywall_restriction_row_key = <?php echo esc_attr($last_key); ?>;
					</script>

					<p>
						<input class="button-secondary" id="add-restriction-row" class="add-new-issuem-leaky-paywall-restriction-row" type="submit" name="add_leaky_paywall_restriction_row" value="<?php esc_attr_e('+ Add Restricted Content', 'leaky-paywall'); ?>" />
					</p>
					<p class="description"><?php esc_html_e('By default all content is allowed.', 'leaky-paywall'); ?> <?php esc_html_e('Restrictions processed from top to bottom.', 'leaky-paywall'); ?></p>
				</td>
			</tr>

			<tr class="restriction-options">
				<th><?php esc_html_e('Combined Restrictions', 'leaky-paywall'); ?></th>
				<td><input type="checkbox" id="enable_combined_restrictions" name="enable_combined_restrictions" <?php checked('on', $settings['enable_combined_restrictions']); ?> /> <?php esc_html_e('Use a single value for total number allowed regardless of content type or taxonomy. This uses the Post Type and Taxonomy settings from the Restrictions settings above.', 'leaky-paywall'); ?></td>
			</tr>

			<tr class="restriction-options combined-restrictions-total-allowed <?php echo 'on' !== $settings['enable_combined_restrictions'] ? 'hide-setting' : ''; ?>">
				<th><?php esc_html_e('Combined Restrictions Total Allowed', 'leaky-paywall'); ?></th>
				<td>
					<input type="number" id="combined_restrictions_total_allowed" class="small-text" name="combined_restrictions_total_allowed" value="<?php echo esc_attr($settings['combined_restrictions_total_allowed']); ?>" />
					<p class="description"><?php esc_html_e('If combined restrictions is enabled, the total amount of content items allowed before content is restricted.'); ?></p>
				</td>
			</tr>

			<tr class="restriction-options">
				<th><?php esc_html_e('Alternative Restriction Handling', 'leaky-paywall'); ?></th>
				<td>
					<input type="checkbox" id="enable_js_cookie_restrictions" name="enable_js_cookie_restrictions" <?php checked('on', $settings['enable_js_cookie_restrictions']); ?> /> <?php esc_html_e('Enable this if you are using a caching plugin or your host uses heavy caching and the paywall notice is not displaying correctly on your site.'); ?>

					<?php
					if ($this->check_for_caching() && 'on' !== $settings['enable_js_cookie_restrictions']) {
					?>
						<div class="notice-info notice">
							<p><strong><?php esc_html_e('Your subscription message might not be showing.', 'leaky-paywall'); ?></strong></p>
							<p><?php esc_html_e('We noticed your site might use caching.  We recommend enabling the Alternative Restrction Handling setting below to ensure your paywall displays correctly.', 'leaky-paywall'); ?><br> <a target="_blank" href="https://docs.leakypaywall.com/article/72-caching-with-leaky-paywall-i-e-wp-engine">Please see our usage guide here.</a></p>
						</div>
					<?php
					}
					?>

				</td>
			</tr>

			<tr class="restriction-options-post-container <?php echo 'on' !== $settings['enable_js_cookie_restrictions'] ? 'hide-setting' : ''; ?>">
				<th><?php esc_html_e('Alternative Restrictions Post Container', 'leaky-paywall'); ?></th>
				<td>
					<input type="text" id="js_restrictions_post_container" class="large-text" name="js_restrictions_post_container" value="<?php echo esc_attr($settings['js_restrictions_post_container']); ?>" />
					<p class="description"><?php esc_html_e('CSS selector of the container that contains the content on a post and custom post type.'); ?></p>
				</td>
			</tr>

			<tr class="restriction-options-page-container <?php echo 'on' !== $settings['enable_js_cookie_restrictions'] ? 'hide-setting' : ''; ?>">
				<th><?php esc_html_e('Alternative Restrictions Page Container', 'leaky-paywall'); ?></th>
				<td>
					<input type="text" id="js_restrictions_page_container" class="large-text" name="js_restrictions_page_container" value="<?php echo esc_attr($settings['js_restrictions_page_container']); ?>" />
					<p class="description"><?php esc_html_e('CSS selector of the container that contains the content on a page.'); ?></p>
				</td>
			</tr>

			<tr class="restriction-options-lead-in-elements <?php echo 'on' !== $settings['enable_js_cookie_restrictions'] ? 'hide-setting' : ''; ?>">
				<th><?php esc_html_e('Lead In Elements', 'leaky-paywall'); ?></th>
				<td>
					<input type="number" id="lead_in_elements" class="small-text" name="lead_in_elements" value="<?php echo esc_attr($settings['lead_in_elements']); ?>">
					<p class="description">
						<?php esc_html_e('Number of HTML elements (paragraphs, images, etc.) to show before displaying the subscribe nag.', 'leaky-paywall'); ?>
					</p>
				</td>
			</tr>

			<tr class="custom-excerpt-length <?php echo 'on' === $settings['enable_js_cookie_restrictions'] ? 'hide-setting' : ''; ?>">
				<th><?php esc_html_e('Custom Excerpt Length', 'leaky-paywall'); ?></th>
				<td>
					<input type="number" id="custom_excerpt_length" class="small-text" name="custom_excerpt_length" value="<?php echo esc_attr($settings['custom_excerpt_length']); ?>">
					<p class="description">
						<?php esc_html_e('Amount of content (in characters) to show before displaying the subscribe nag. If nothing is entered then the full excerpt is displayed.', 'leaky-paywall'); ?>
					</p>
				</td>
			</tr>

			<tr class="restriction-options">
				<th><?php esc_html_e('Bypass Restrictions', 'leaky-paywall'); ?></th>
				<td>
					<?php
					$roles = get_editable_roles();

					foreach ($roles as $name => $role) {
					?>
						<input type="checkbox" name="bypass_paywall_restrictions[]" <?php echo in_array($name, $settings['bypass_paywall_restrictions'], true) ? 'checked' : ''; ?> <?php echo 'administrator' === $name ? 'disabled' : ''; ?> value="<?php echo esc_attr($name); ?>"> <?php echo esc_html(ucfirst(str_replace('_', ' ', $name))); ?>&nbsp; &nbsp;
					<?php
					}
					?>

					<p class="description">
						<?php esc_html_e('Allow the selected user roles to always bypass the paywall. Administrators can always bypass the paywall.'); ?>
					</p>
				</td>
			</tr>

			<tr class="restriction-exceptions">
				<th><?php esc_html_e('Restriction Exceptions', 'leaky-paywall'); ?></th>
				<td>
					<table>
						<tr>
							<td><label for="post_category_exceptions"><?php esc_html_e('Post Categories', 'leaky-paywall'); ?></label></td>
							<td style="width: 80%;"><input type="text" class="large-text" name="post_category_exceptions" value="<?php echo esc_attr($settings['post_category_exceptions']); ?>"></td>
						</tr>
						<tr>
							<td><label for="post_tag_exceptions"><?php esc_html_e('Post Tags', 'leaky-paywall'); ?></label></td>
							<td style="width: 80%;"><input type="text" class="large-text" name="post_tag_exceptions" value="<?php echo esc_attr($settings['post_tag_exceptions']); ?>"></td>
						</tr>
					</table>

					<p class="description"><?php esc_html_e('Enter a comma separated list of category and/or tag IDs that should not be restricted.', 'leaky-paywall'); ?></p>
				</td>
			</tr>

		</table>

	<?php

	}

	public function output_subscriptions_settings($current_section)
	{

		if ($current_section != 'general') {
			return;
		}

		$settings = $this->get_settings();

		do_action('leaky_paywall_before_subscriptions_settings'); ?>

		<?php

		if (isset($_GET['level_id'])) {

			$current_level = get_leaky_paywall_subscription_level(absint($_GET['level_id']));

		?>
			<h2><?php echo esc_html($current_level['label']); ?> <a href="<?php echo esc_url(admin_url()); ?>admin.php?page=issuem-leaky-paywall&tab=subscriptions">⤴</a><br><span style="color: #aaa; font-size: 14px; font-weight: normal;">ID: <?php echo absint($_GET['level_id']); ?></span></h2>
		<?php
		} else {
		?>
			<h2><?php esc_html_e('Subscription Levels', 'leaky-paywall'); ?></h2>
		<?php
		}
		?>

		<div id="leaky_paywall_subscription_level_options">

			<table id="leaky_paywall_subscription_level_options_table" class="leaky-paywall-table subscription-options form-table">

				<tr>
					<td id="issuem-leaky-paywall-subscription-level-rows" colspan="2">
						<?php

						$last_key = -1;
						if (!empty($settings['levels'])) {

							$deleted = array();

							if (!isset($_GET['level_id'])) {
								echo '<table class="wp-list-table widefat striped" style="margin-bottom: 20px"><thead><tr><td>ID</td><td>Name</td><td>Price</td><td>Duration</td><td>Payment Type</td><td>Direct Link</td></tr></thead>';
							}

							foreach ($settings['levels'] as $key => $level) {

								if (!is_numeric($key)) {
									continue;
								}

								if (isset($level['deleted']) && 1 == $level['deleted']) {
									$deleted[] = true;
									continue;
								}


								if (isset($_GET['level_id'])) {

									if ($key != $_GET['level_id']) {
										continue;
									}


									// phpcs:ignore
									echo build_leaky_paywall_subscription_levels_row($level, $key);
								} else {
									// phpcs:ignore
									echo build_leaky_paywall_subscription_levels_row_summary($level, $key);
								}

								$last_key = $key;
							}

							if (!isset($_GET['level_id'])) {
								echo '</table>';
							}

							// if we have levels but they have all been deleted, add one level.
							if (count($deleted) === count($settings['levels'])) {

								// set the default key to one more than they last key value.
								$default_key = count($settings['levels']);

								// phpcs:ignore
								echo build_leaky_paywall_subscription_levels_row('', $default_key);
							}
						}
						?>
					</td>
				</tr>

			</table>

			<?php do_action('leaky_paywall_after_subscription_levels', $last_key); ?>

			<?php
			if (!is_plugin_active('leaky-paywall-multiple-levels/leaky-paywall-multiple-levels.php')) {
				echo '<h4 class="description">Want more levels? Get our <a target="_blank" href="https://leakypaywall.com/downloads/leaky-paywall-multiple-levels/?utm_medium=plugin&utm_source=subscriptions_tab&utm_campaign=settings">multiple subscription levels</a> extension.</h4>';
			}

			if (!is_plugin_active('leaky-paywall-recurring-payments/leaky-paywall-recurring-payments.php')) {
				echo '<h4 class="description">Want recurring payments? Get our <a target="_blank" href="https://leakypaywall.com/downloads/leaky-paywall-recurring-payments/?utm_medium=plugin&utm_source=subscriptions_tab&utm_campaign=settings">recurring payments</a> extension.</h4>';
			}

			?>

		</div>

		<?php do_action('leaky_paywall_after_subscriptions_settings'); ?>

	<?php
	}

	public function output_payments_settings($current_section)
	{

		if ($current_section != 'general') {
			return;
		}

		$settings = $this->get_settings();

		do_action('leaky_paywall_before_payments_settings'); ?>

		<h2><?php esc_html_e('Payment Gateway Settings', 'leaky-paywall'); ?></h2>

		<table id="leaky_paywall_test_option" class="form-table">

			<tr class="gateway-options">
				<th><?php esc_html_e('Test Mode', 'leaky-paywall'); ?></th>
				<td>
					<p><input type="checkbox" id="test_mode" name="test_mode" <?php checked('on', $settings['test_mode']); ?> />
						<?php esc_html_e('Use the test gateway environment for transactions.', 'leaky-paywall'); ?></p>
				</td>

			</tr>

		</table>

		<?php
		ob_start();
		?>

		<table id="leaky_paywall_gateway_options" class="form-table">

			<tr class="gateway-options">
				<th><?php esc_html_e('Enabled Gateways', 'leaky-paywall'); ?></th>
				<td>
					<?php
					$gateways = leaky_paywall_get_payment_gateways();

					foreach ($gateways as $key => $value) {
					?>
						<p>
							<input id="enable-<?php echo esc_attr($key); ?>" type="checkbox" name="payment_gateway[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $settings['payment_gateway'], true)); ?> /> <label for="enable-<?php echo esc_attr($key); ?>"><?php echo esc_html($value['admin_label']); ?></label>
						</p>
					<?php
					}
					?>
					<p class="description">Need a different gateway? Take payments with our <a target="_blank" href="https://leakypaywall.com/downloads/leaky-paywall-woocommerce/">WooCommerce integration</a> using any Woo supported gateway. <a target="_blank" href="https://leakypaywall.com/contact/">Get in touch</a> about our integrations with HubSpot, ZOHO, Pipedrive, fulfillment services and other providers.</p>
				</td>
			</tr>

		</table>

		<?php

		if (in_array('manual', $settings['payment_gateway'], true) || in_array('manual', $settings['payment_gateway'], true)) {
		?>

			<table id="leaky_paywall_manual_payment_options" class="gateway-options form-table">

				<tr>
					<th colspan="2">
						<h3><?php esc_html_e('Manual Payment Settings', 'leaky-paywall'); ?></h3>
					</th>
				</tr>

				<tr>
					<th><?php esc_html_e('Title', 'leaky-paywall'); ?></th>
					<td>
						<input type="text" id="manual_payment_title" class="regular-text" name="manual_payment_title" value="<?php echo esc_attr($settings['manual_payment_title']); ?>" />
						<p class="description"><?php esc_html_e('The title the user sees during registration.', 'leaky-paywall'); ?></p>
					</td>
				</tr>
			<?php
		} ?>

			<?php
			if (in_array('stripe', $settings['payment_gateway'], true) || in_array('stripe_checkout', $settings['payment_gateway'], true)) {
				ob_start();
			?>

				<table id="leaky_paywall_stripe_options" class="form-table">

					<tr>
						<th colspan="2">
							<h3><?php esc_html_e('Stripe Settings', 'leaky-paywall'); ?></h3>

							<?php
							if (!isset($settings['live_publishable_key']) || !$settings['live_publishable_key']) {
							?>
								<p>Looking for your Stripe keys? <a target="_blank" href="https://dashboard.stripe.com/account/apikeys">Click here.</a></p>
							<?php
							}
							?>

						</th>
					</tr>

					<tr>
						<th><?php esc_html_e('Live Publishable Key', 'leaky-paywall'); ?></th>
						<td><input type="text" id="live_publishable_key" class="regular-text" name="live_publishable_key" value="<?php echo esc_attr($settings['live_publishable_key']); ?>" /></td>
					</tr>

					<tr>
						<th><?php esc_html_e('Live Secret Key', 'leaky-paywall'); ?></th>
						<td><input type="password" id="live_secret_key" class="regular-text" name="live_secret_key" value="<?php echo esc_attr($settings['live_secret_key']); ?>" /></td>
					</tr>

					<tr>
						<th><?php esc_html_e('Test Publishable Key', 'leaky-paywall'); ?></th>
						<td><input type="text" id="test_publishable_key" class="regular-text" name="test_publishable_key" value="<?php echo esc_attr($settings['test_publishable_key']); ?>" /></td>
					</tr>

					<tr>
						<th><?php esc_html_e('Test Secret Key', 'leaky-paywall'); ?></th>
						<td><input type="password" id="test_secret_key" class="regular-text" name="test_secret_key" value="<?php echo esc_attr($settings['test_secret_key']); ?>" /></td>
					</tr>

					<tr>
						<th><?php esc_html_e('Stripe Webhook URL', 'leaky-paywall'); ?></th>
						<td>
							<p class="description"><?php echo esc_url(add_query_arg('listener', 'stripe', get_site_url() . '/')); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Stripe Webhooks Enabled', 'leaky-paywall'); ?></th>
						<td>
							<p><input type="checkbox" id="stripe_webhooks_enabled" name="stripe_webhooks_enabled" <?php checked('on', $settings['stripe_webhooks_enabled']); ?> />
								<?php esc_html_e('I have enabled the Leaky Paywall webhook URL in my Stripe account.', 'leaky-paywall'); ?><br><a target="_blank" href="https://docs.leakypaywall.com/article/120-leaky-paywall-recurring-payments"><?php esc_html_e('View Instructions', 'leaky-paywall'); ?></a></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Test Signing Secret', 'leaky-paywall'); ?></th>
						<td><input type="password" id="test_signing_secret" class="regular-text" name="test_signing_secret" value="<?php echo esc_attr($settings['test_signing_secret']); ?>" /></td>
					</tr>

					<tr>
						<th><?php esc_html_e('Live Signing Secret', 'leaky-paywall'); ?></th>
						<td><input type="password" id="live_signing_secret" class="regular-text" name="live_signing_secret" value="<?php echo esc_attr($settings['live_signing_secret']); ?>" /></td>
					</tr>

					<?php if (in_array('stripe_checkout', $settings['payment_gateway'])) {
					?>
						<tr>
							<th><?php esc_html_e('Automatic Tax', 'leaky-paywall'); ?></th>
							<td>
								<p><input type="checkbox" id="stripe_automatic_tax" name="stripe_automatic_tax" <?php checked('on', $settings['stripe_automatic_tax']); ?> />
									<?php esc_html_e('Automatically calculate tax for Stripe Checkout transactions, only for one time payments.', 'leaky-paywall'); ?><br><a target="_blank" href="https://dashboard.stripe.com/settings/tax/activate">Requires Stripe Tax activation</a></p>
							</td>
						</tr>

						<tr>
							<th><?php esc_html_e('Tax Behavior', 'leaky-paywall'); ?></th>
							<td>
								<select id="stripe_tax_behavior" name="stripe_tax_behavior">
									<option <?php selected('exclusive', $settings['stripe_tax_behavior']); ?> value="exclusive">Exclusive</option>
									<option <?php selected('inclusive', $settings['stripe_tax_behavior']); ?> value="inclusive">Inclusive</option>
								</select>
								<p class="description">When set to exclusive, it adds tax to the subtotal. If set to inclusive, the amount your buyer pays never changes (even if the tax rate varies).</p>
							</td>
						</tr>
					<?php
					} ?>

					<tr>
						<th><?php esc_html_e('Billing Address', 'leaky-paywall'); ?></th>
						<td>
							<p><input type="checkbox" id="stripe_billing_address" name="stripe_billing_address" <?php checked('on', $settings['stripe_billing_address']); ?> />
								<?php esc_html_e('Display Stripe billing fields on the registration form (optional).', 'leaky-paywall'); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Customer Portal', 'leaky-paywall'); ?></th>
						<td>
							<p><input type="checkbox" id="stripe_customer_portal" name="stripe_customer_portal" <?php checked('on', $settings['stripe_customer_portal']); ?> />
								<?php esc_html_e('Enable Stripe Customer Portal access on the My Account page for managing recurring payment information.', 'leaky-paywall'); ?><br><a target="_blank" href="https://dashboard.stripe.com/settings/billing/portal">Requires Stripe Portal configuration</a></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Restrict Stripe Assets', 'leaky-paywall'); ?></th>
						<td>
							<p><input type="checkbox" id="stripe_restrict_assets" name="stripe_restrict_assets" <?php checked('on', $settings['stripe_restrict_assets']); ?> />
								<?php esc_html_e('Only load Stripe.com hosted assets on pages that specifically utilize Stripe functionality.', 'leaky-paywall'); ?><br><span class="description">Enabling this will disable Stripe's advanced fraud detection. <a target="_blank" href="https://stripe.com/docs/disputes/prevention/advanced-fraud-detection">Learn More</a></span></p>
						</td>
					</tr>

				</table>
				<?php do_action('leaky_paywall_settings_page_stripe_payment_gateway_options'); ?>
			<?php } ?>

			<?php
			if (in_array('paypal_standard', $settings['payment_gateway'], true) || in_array('paypal-standard', $settings['payment_gateway'], true)) {
			?>

				<table id="leaky_paywall_paypal_options" class="gateway-options form-table">

					<tr>
						<th colspan="2">
							<h3><?php esc_html_e('PayPal Standard Settings', 'leaky-paywall'); ?></h3>
						</th>
					</tr>

					<tr>
						<th><?php esc_html_e('Merchant ID', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_live_email" class="regular-text" name="paypal_live_email" value="<?php echo esc_attr($settings['paypal_live_email']); ?>" />
							<p class="description"><?php esc_html_e('Need help setting up PayPal?', 'leaky-paywall'); ?> <a target="_blank" href="https://docs.leakypaywall.com/article/213-how-to-set-up-paypal-as-a-payment-gateway"><?php esc_html_e('See our guide.', 'leaky-paywall'); ?></a></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('API Username', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_live_api_username" class="regular-text" name="paypal_live_api_username" value="<?php echo esc_attr($settings['paypal_live_api_username']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('API Password', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_live_api_password" class="regular-text" name="paypal_live_api_password" value="<?php echo esc_attr($settings['paypal_live_api_password']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('API Signature', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_live_api_secret" class="regular-text" name="paypal_live_api_secret" value="<?php echo esc_attr($settings['paypal_live_api_secret']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Image URL', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_image_url" class="regular-text" name="paypal_image_url" value="<?php echo esc_url($settings['paypal_image_url']); ?>" />
							<p class="description"><?php esc_html_e('Enter the URL to a 150x50px image displayed as your logo in the upper left corner of the Paypal checkout pages.', 'leaky-paywall'); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Live IPN', 'leaky-paywall'); ?></th>
						<td>
							<p class="description"><?php echo esc_url(add_query_arg('listener', 'IPN', get_site_url() . '/')); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Sandbox Merchant ID', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_sand_email" class="regular-text" name="paypal_sand_email" value="<?php echo esc_attr($settings['paypal_sand_email']); ?>" />
							<p class="description"><?php esc_html_e('Use PayPal Sandbox Email Address in lieu of Merchant ID', 'leaky-paywall'); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Sandbox API Username', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_sand_api_username" class="regular-text" name="paypal_sand_api_username" value="<?php echo esc_attr($settings['paypal_sand_api_username']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Sandbox API Password', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_sand_api_password" class="regular-text" name="paypal_sand_api_password" value="<?php echo esc_attr($settings['paypal_sand_api_password']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Sandbox API Signature', 'leaky-paywall'); ?></th>
						<td>
							<input type="text" id="paypal_sand_api_secret" class="regular-text" name="paypal_sand_api_secret" value="<?php echo esc_attr($settings['paypal_sand_api_secret']); ?>" />
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e('Sandbox IPN', 'leaky-paywall'); ?></th>
						<td>
							<p class="description"><?php echo esc_url(add_query_arg('listener', 'IPN', get_site_url() . '/')); ?></p>
						</td>
					</tr>

				</table>

			<?php } ?>

			<?php do_action('leaky_paywall_after_enabled_gateways', $settings); ?>

			<h2><?php esc_html_e('Currency Options', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_currency_options" class="form-table">

				<tr>
					<th><?php esc_html_e('Currency', 'leaky-paywall'); ?></th>
					<td>
						<select id="leaky_paywall_currency" name="leaky_paywall_currency">
							<?php
							$currencies = leaky_paywall_supported_currencies();
							foreach ($currencies as $key => $currency) {
								echo '<option value="' . esc_attr($key) . '" ' . selected($key, $settings['leaky_paywall_currency'], true) . '>' . esc_html($currency['label']) . ' - ' . esc_html($currency['symbol']) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php esc_html_e('This controls which currency payment gateways will take payments in.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Currency Position', 'leaky-paywall'); ?></th>
					<td>
						<select id="leaky_paywall_currency_position" name="leaky_paywall_currency_position">

							<option value="left" <?php selected('left', $settings['leaky_paywall_currency_position']); ?>>Left ($99.99)</option>
							<option value="right" <?php selected('right', $settings['leaky_paywall_currency_position']); ?>>Right (99.99$)</option>
							<option value="left_space" <?php selected('left_space', $settings['leaky_paywall_currency_position']); ?>>Left with space ($ 99.99)</option>
							<option value="right_space" <?php selected('right_space', $settings['leaky_paywall_currency_position']); ?>>Right with space (99.99 $)</option>
						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Thousand Separator', 'leaky-paywall'); ?></th>
					<td>
						<input type="text" class="small-text" id="leaky_paywall_thousand_separator" name="leaky_paywall_thousand_separator" value="<?php echo esc_attr($settings['leaky_paywall_thousand_separator']); ?>">
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Decimal Separator', 'leaky-paywall'); ?></th>
					<td>
						<input type="text" class="small-text" id="leaky_paywall_decimal_separator" name="leaky_paywall_decimal_separator" value="<?php echo esc_attr($settings['leaky_paywall_decimal_separator']); ?>">
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Number of Decimals', 'leaky-paywall'); ?></th>
					<td>
						<input type="number" class="small-text" id="leaky_paywall_decimal_number" name="leaky_paywall_decimal_number" value="<?php echo esc_attr($settings['leaky_paywall_decimal_number']); ?>" min="0" step="1">
					</td>
				</tr>

				<?php do_action('leaky_paywall_after_currency_settings', $settings); ?>

			</table>

		<?php do_action('leaky_paywall_after_payments_settings', $settings);
	}

	public function output_emails_settings($current_section)
	{

		$settings = $this->get_settings();

		do_action('leaky_paywall_before_email_settings'); ?>

			<h2><?php esc_html_e('Email Settings', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_administrator_options" class="form-table">

				<tr>
					<th><?php esc_html_e('Site Name', 'leaky-paywall'); ?></th>
					<td><input type="text" id="site_name" class="regular-text" name="site_name" value="<?php echo esc_attr($settings['site_name']); ?>" /></td>
				</tr>

				<tr>
					<th><?php esc_html_e('From Name', 'leaky-paywall'); ?></th>
					<td><input type="text" id="from_name" class="regular-text" name="from_name" value="<?php echo esc_attr($settings['from_name']); ?>" /></td>
				</tr>

				<tr>
					<th><?php esc_html_e('From Email', 'leaky-paywall'); ?></th>
					<td><input type="text" id="from_email" class="regular-text" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>" /></td>
				</tr>

			</table>

			<h2><?php esc_html_e('Admin New Subscriber Email', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_administrator_options" class="form-table">

				<tr>
					<th><?php esc_html_e('Disable New Subscriber Notifications', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="new_subscriber_admin_email" name="new_subscriber_admin_email" <?php checked('on', $settings['new_subscriber_admin_email']); ?> /> <?php esc_html_e('Disable the email sent to an admin when a new subscriber is added to Leaky Paywall', 'leaky-paywall'); ?></td>
				</tr>

				<tr>
					<th><?php esc_html_e('Subject', 'leaky-paywall'); ?></th>
					<td><input type="text" id="admin_new_subscriber_email_subject" class="regular-text" name="admin_new_subscriber_email_subject" value="<?php echo esc_attr($settings['admin_new_subscriber_email_subject']); ?>" />
						<p class="description"><?php esc_html_e('The subject line for this email.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Recipient(s)', 'leaky-paywall'); ?></th>
					<td><input type="text" id="admin_new_subscriber_email_recipients" class="regular-text" name="admin_new_subscriber_email_recipients" value="<?php echo esc_attr($settings['admin_new_subscriber_email_recipients']); ?>" />
						<p class="description"><?php esc_html_e('Enter recipients (comma separated) for this email.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

			</table>

			<h2><?php esc_html_e('New Subscriber Email', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_new_subscriber_email_options" class="form-table">

				<tr>
					<th><?php esc_html_e('Disable New Subscriber Email', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="new_subscriber_email" name="new_subscriber_email" <?php checked('on', $settings['new_subscriber_email']); ?> /> Disable the new subscriber email sent to a subscriber</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Subject', 'leaky-paywall'); ?></th>
					<td><input type="text" id="new_email_subject" class="regular-text" name="new_email_subject" value="<?php echo esc_attr($settings['new_email_subject']); ?>" />
						<p class="description"><?php esc_html_e('The subject line for the email sent to new subscribers.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Body', 'leaky-paywall'); ?></th>
					<td>
						<?php wp_editor(stripslashes($settings['new_email_body']), 'new_email_body'); ?>
						<p class="description"><?php esc_html_e('The email message that is sent to new subscribers. HTML is allowed.', 'leaky-paywall'); ?></p>
						<p class="description"><?php esc_html_e('Available template tags:', 'leaky-paywall'); ?> <br>
							%blogname%, %sitename%, %username%, %useremail%, %password%, %firstname%, %lastname%, %displayname%</p>
					</td>
				</tr>

			</table>

			<h2><?php esc_html_e('Renewal Reminder Email (for non-recurring subscribers)', 'leaky-paywall'); ?></h2>

			<table id="leaky_paywall_renewal_reminder_email_options" class="form-table">

				<tr>
					<th><?php esc_html_e('Disable Renewal Reminder Email', 'leaky-paywall'); ?></th>
					<td><input type="checkbox" id="renewal_reminder_email" name="renewal_reminder_email" <?php checked('on', $settings['renewal_reminder_email']); ?> /> Disable the renewal reminder email sent to a non-recurring subscriber</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Subject', 'leaky-paywall'); ?></th>
					<td><input type="text" id="renewal_reminder_email_subject" class="regular-text" name="renewal_reminder_email_subject" value="<?php echo esc_attr($settings['renewal_reminder_email_subject']); ?>" />

					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('Body', 'leaky-paywall'); ?></th>
					<td>
						<?php wp_editor(stripslashes($settings['renewal_reminder_email_body']), 'renewal_reminder_email_body'); ?>
						<p class="description"><?php esc_html_e('The email message that is sent to remind non-recurring subscribers to renew their subscription.', 'leaky-paywall'); ?></p>
						<p class="description"><?php esc_html_e('Available template tags:', 'leaky-paywall'); ?> <br>
							%blogname%, %sitename%, %username%, %password%, %firstname%, %lastname%, %displayname%</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e('When to Send Reminder', 'leaky-paywall'); ?></th>
					<td>
						<input type="number" value="<?php echo esc_attr($settings['renewal_reminder_days_before']); ?>" name="renewal_reminder_days_before" />
						<p class="description"><?php esc_html_e('Days in advance of a non-recurring subscriber\'s expiration date to remind them to renew.', 'leaky-paywall'); ?></p>
					</td>
				</tr>

			</table>

			<?php do_action('leaky_paywall_after_email_settings'); ?>

		<?php
	}

	public function output_licenses_settings($current_section)
	{

		do_action('leaky_paywall_before_licenses_settings'); ?>

			<p>Enter your extension license keys here to receive updates for purchased extensions. If your license key has expired, <a href="https://leakypaywall.com/my-account/#tabs-2">please login to your account to renew your license</a>.</p>

			<h2><a target="_blank" href="https://leakypaywall.com/downloads/category/leaky-paywall-addons/?utm_source=plugin&utm_medium=license_tab&utm_content=link&utm_campaign=settings">Find out more about our extensions</a></h2>

			<?php wp_nonce_field('verify', 'leaky_paywall_license_wpnonce'); ?>

		<?php do_action('leaky_paywall_after_licenses_settings');
	}

	public function output_help_settings($current_section)
	{
		do_action('leaky_paywall_before_help_settings'); ?>

			<h2><?php esc_html_e('Getting Started', 'leaky-paywall'); ?></h2>

			<p><a target="_blank" href="https://docs.leakypaywall.com/article/39-setting-up-leaky-paywall">Setting Up Leaky Paywall</a></p>

			<iframe width="560" height="315" src="https://www.youtube.com/embed/blUGogGw4H8" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

			<h2><?php esc_html_e('Setting Up Stripe in Leaky Paywall', 'leaky-paywall'); ?></h2>

			<iframe width="560" height="315" src="https://www.youtube.com/embed/QlrYpL72L4E" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

			<h2><?php esc_html_e('Documentation Articles'); ?></h2>

			<p><a target="_blank" href="https://docs.leakypaywall.com/">View All</a></p>

			<?php wp_nonce_field('verify', 'leaky_paywall_help_wpnonce'); ?>

	<?php do_action('leaky_paywall_after_help_settings');
	}

	public function get_settings_tabs()
	{
		$tabs = array(
			'general',
			'restrictions',
			'subscriptions',
			'payments',
			'emails',
			'licenses',
			'help'
		);

		return apply_filters('leaky_paywall_settings_tabs', $tabs);
	}

	public function get_settings_tab_sections($current_tab)
	{

		$sections = apply_filters('leaky_paywall_settings_tab_sections', array(
			'general' => array(
				'general',
				'pages',
			),
			'restrictions' => array(),
			'subscriptions' => array(
				'general'
			),
			'payments' => array(
				'general'
			),
			'emails' => array(),
			'licenses' => array(),
			'help' => array()
		));

		return $sections[$current_tab];
	}

	/**
	 * Get Leaky Paywall settings
	 *
	 * @since 1.0.0
	 */
	public function get_settings()
	{

		$default_email_body = 'PLEASE EDIT THIS CONTENT - You can use simple html, including images.

			Thank you for subscribing to %sitename% and welcome to our community!

			Your account is activated.

			As a member you will gain more insight into the topics you care about, gain access to the latest articles, and you will gain a greater understanding of the events that are shaping our time. With a Digital Subscription, you also get our official Mobile App for FREE. Get the apps here: http://OurPublication.com/apps

			<b>How to login:</b>

			Go to: http://OurPublication.com/my-account/ (this is the “Page for Profile” setting in Leaky Paywall Settings)
			Username: %username%
			Password: %password%

			Use some social media to tell your friends that you are on the journey with us https://twitter.com/OurPublication

			TWEET: I just subscribed to Our Publication. Join up and be awesome! www.ourpublication.com

			Facebook https://www.facebook.com/ourpublication/

			Instagram https://www.instagram.com/ourpublication/

			LinkedIn https://www.linkedin.com/groups/12345678

			We love feedback… please help us make your publication better by emailing info@ourpublication.pub … and thanks again!';

		$defaults = array(
			'page_for_login'                        => 0, /* Site Specific */
			'page_for_subscription'                 => 0, /* Site Specific */
			'page_for_register'                     => 0, /* Site Specific */
			'page_for_after_subscribe'              => 0,
			'page_for_profile'                      => 0, /* Site Specific */
			'custom_excerpt_length'                 => '',
			'login_method'                          => 'traditional', // default over passwordless.
			'post_types'                            => ACTIVE_ISSUEM ? array('article') : array('post'), /* Site Specific */
			'free_articles'                         => 2,
			'cookie_expiration'                     => 30,
			'cookie_expiration_interval'            => 'day',
			'subscribe_login_message'               => __('<a href="{{SUBSCRIBE_URL}}">Subscribe</a> or <a href="{{LOGIN_URL}}">log in</a> to read the rest of this content.', 'leaky-paywall'),
			'subscribe_upgrade_message'             => __('You must <a href="{{SUBSCRIBE_URL}}">upgrade your account</a> to read the rest of this content.', 'leaky-paywall'),
			'css_style'                             => 'default',
			'enable_user_delete_account'            => 'off',
			'remove_username_field'                 => 'off',
			'add_expiration_dates'   	            => 'on',
			'enable_rest_api'                  		=> 'on',
			'site_name'                             => get_option('blogname'), /* Site Specific */
			'from_name'                             => get_option('blogname'), /* Site Specific */
			'from_email'                            => get_option('admin_email'), /* Site Specific */
			'new_subscriber_email'                  => 'off',
			'new_email_subject'                     => '',
			'new_email_body'                        => $default_email_body,
			'renewal_reminder_email'                => 'on',
			'renewal_reminder_email_subject'        => '',
			'renewal_reminder_email_body'           => '',
			'renewal_reminder_days_before'          => '7',
			'new_subscriber_admin_email'            => 'off',
			'admin_new_subscriber_email_subject'    => 'New subscription on ' . stripslashes_deep(html_entity_decode(get_bloginfo('name'), ENT_COMPAT, 'UTF-8')),
			'admin_new_subscriber_email_recipients' => get_option('admin_email'),
			'payment_gateway'                       => array('stripe_checkout'),
			'test_mode'                             => 'off',
			'live_secret_key'                       => '',
			'live_publishable_key'                  => '',
			'test_secret_key'                       => '',
			'test_publishable_key'                  => '',
			'test_signing_secret'	                => '',
			'live_signing_secret'	                => '',
			'stripe_webhooks_enabled'               => 'off',
			'enable_stripe_elements'                => 'no',
			'enable_apple_pay'                      => 'no',
			'stripe_automatic_tax'                  => 'no',
			'stripe_customer_portal'				=> 'no',
			'stripe_billing_address'					=> 'no',
			'stripe_restrict_assets'				=> 'no',
			'stripe_tax_behavior'                   => 'exclusive',
			'enable_paypal_on_registration'         => 'on',
			'paypal_live_email'                     => '',
			'paypal_live_api_username'              => '',
			'paypal_live_api_password'              => '',
			'paypal_live_api_secret'                => '',
			'paypal_image_url'                      => '',
			'paypal_sand_email'                     => '',
			'paypal_sand_api_username'              => '',
			'paypal_sand_api_password'              => '',
			'paypal_sand_api_secret'                => '',
			'manual_payment_title'					=> 'Manual Payment',
			'leaky_paywall_currency'                => 'USD',
			'leaky_paywall_currency_position'       => 'left',
			'leaky_paywall_thousand_separator'      => ',',
			'leaky_paywall_decimal_separator'       => '.',
			'leaky_paywall_decimal_number'          => '2',
			'restrict_pdf_downloads'                => 'off',
			'enable_combined_restrictions'          => 'off',
			'combined_restrictions_total_allowed'   => '',
			'enable_js_cookie_restrictions'         => 'off',
			'js_restrictions_post_container'        => 'article .entry-content',
			'js_restrictions_page_container'        => 'article .entry-content',
			'lead_in_elements'                      => 2,
			'bypass_paywall_restrictions'           => array('administrator'),
			'post_tag_exceptions'                   => '',
			'post_category_exceptions'              => '',
			'restrictions'                          => array(
				'post_types' => array(
					'post_type'     => ACTIVE_ISSUEM ? 'article' : 'post',
					'taxonomy'      => 'all',
					'allowed_value' => 2,
				),
			),
			'levels'                                => array(
				'0' => array(
					'label'                    => __('Digital Access', 'leaky-paywall'),
					'price'                    => '0',
					'subscription_length_type' => 'limited',
					'interval_count'           => 1,
					'interval'                 => 'month',
					'recurring'                => 'off',
					'plan_id'                  => array(),
					'post_types'               => array(
						array(
							'post_type'     => ACTIVE_ISSUEM ? 'article' : 'post',
							'allowed'       => 'unlimited',
							'allowed_value' => -1,
						),
					),
					'deleted'                  => 0,
					'site'                     => 'all',
				),
			),
		);

		$defaults = apply_filters('leaky_paywall_default_settings', $defaults);
		$settings = get_option('issuem-leaky-paywall'); /* Site specific settings */
		$settings = wp_parse_args($settings, $defaults);

		if ($this->is_site_wide_enabled()) {
			$site_wide_settings = get_site_option('issuem-leaky-paywall');
			/* These are all site-specific settings */
			unset($site_wide_settings['page_for_login']);
			unset($site_wide_settings['page_for_subscription']);
			unset($site_wide_settings['page_for_register']);
			unset($site_wide_settings['page_for_after_subscribe']);
			unset($site_wide_settings['page_for_profile']);
			unset($site_wide_settings['post_types']);
			unset($site_wide_settings['free_articles']);
			unset($site_wide_settings['cookie_expiration']);
			unset($site_wide_settings['cookie_expiration_interval']);
			unset($site_wide_settings['subscribe_login_message']);
			unset($site_wide_settings['subscribe_upgrade_message']);
			unset($site_wide_settings['css_style']);
			unset($site_wide_settings['site_name']);
			unset($site_wide_settings['from_name']);
			unset($site_wide_settings['from_email']);
			unset($site_wide_settings['restrictions']);
			$site_wide_settings = apply_filters('leak_paywall_get_settings_site_wide_settings', $site_wide_settings);
			$settings           = wp_parse_args($site_wide_settings, $settings);
		}

		$settings = apply_filters('leaky_paywall_multisite_premium', $settings);

		return apply_filters('leaky_paywall_get_settings', $settings);
	}

	public function process_level_deleted($current_tab, $current_section)
	{
		if ('subscriptions' != $current_tab) {
			return false;
		}

		if (!isset($_GET['delete_level_id'])) {
			return false;
		}

		$settings = $this->get_settings();
		$level_id = absint($_GET['delete_level_id']);
		$settings['levels'][$level_id]['deleted'] = 1;
		$this->update_settings($settings);

		return true;
	}

	public function process_settings_update($current_tab, $current_section)
	{

		if (!isset($_POST['update_leaky_paywall_settings'])) {
			return false;
		}

		if (!isset($_POST['leaky_paywall_update_settings_nonce_field'])) {
			return false;
		}

		if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['leaky_paywall_update_settings_nonce_field'])), 'leaky_paywall_update_settings_nonce')) {
			return false;
		}

		$settings = $this->get_settings();

		if ('general' === $current_tab && 'general' == $current_section) {

			if (!empty($_REQUEST['site_wide_enabled'])) {
				update_site_option('issuem-leaky-paywall-site-wide', true);
			} else {
				update_site_option('issuem-leaky-paywall-site-wide', false);
			}

			if (!empty($_POST['login_method'])) {
				$settings['login_method'] = sanitize_text_field(wp_unslash($_POST['login_method']));
			}

			if (!empty($_POST['subscribe_login_message'])) {
				$settings['subscribe_login_message'] = wp_kses(wp_unslash($_POST['subscribe_login_message']), $this->allowed_html());
			}

			if (!empty($_POST['subscribe_upgrade_message'])) {
				$settings['subscribe_upgrade_message'] = wp_kses(wp_unslash($_POST['subscribe_upgrade_message']), $this->allowed_html());
			}

			if (!empty($_POST['css_style'])) {
				$settings['css_style'] = sanitize_text_field(wp_unslash($_POST['css_style']));
			}

			if (!empty($_POST['enable_user_delete_account'])) {
				$settings['enable_user_delete_account'] = sanitize_text_field(wp_unslash($_POST['enable_user_delete_account']));
			} else {
				$settings['enable_user_delete_account'] = 'off';
			}

			if (!empty($_POST['remove_username_field'])) {
				$settings['remove_username_field'] = sanitize_text_field(wp_unslash($_POST['remove_username_field']));
			} else {
				$settings['remove_username_field'] = 'off';
			}

			if (!empty($_POST['add_expiration_dates'])) {
				$settings['add_expiration_dates'] = sanitize_text_field(wp_unslash($_POST['add_expiration_dates']));
			} else {
				$settings['add_expiration_dates'] = 'off';
			}

			if (!empty($_POST['enable_rest_api'])) {
				$settings['enable_rest_api'] = sanitize_text_field(wp_unslash($_POST['enable_rest_api']));
			} else {
				$settings['enable_rest_api'] = 'off';
			}
		}

		if ('general' == $current_tab && 'pages' == $current_section) {

			if (isset($_POST['page_for_login'])) {
				$settings['page_for_login'] = absint($_POST['page_for_login']);
			}

			if (isset($_POST['page_for_subscription'])) {
				$settings['page_for_subscription'] = absint($_POST['page_for_subscription']);
			}

			if (isset($_POST['page_for_register'])) {
				$settings['page_for_register'] = absint($_POST['page_for_register']);
			}

			if (isset($_POST['page_for_after_subscribe'])) {
				$settings['page_for_after_subscribe'] = absint($_POST['page_for_after_subscribe']);
			}

			if (isset($_POST['page_for_profile'])) {
				$settings['page_for_profile'] = absint($_POST['page_for_profile']);
			}
		}

		if ('emails' === $current_tab) {

			if (!empty($_POST['site_name'])) {
				$settings['site_name'] = sanitize_text_field(wp_unslash($_POST['site_name']));
			}

			if (!empty($_POST['from_name'])) {
				$settings['from_name'] = sanitize_text_field(wp_unslash($_POST['from_name']));
			}

			if (!empty($_POST['from_email'])) {
				$settings['from_email'] = sanitize_text_field(wp_unslash($_POST['from_email']));
			}

			if (!empty($_POST['new_subscriber_email'])) {
				$settings['new_subscriber_email'] = sanitize_text_field(wp_unslash($_POST['new_subscriber_email']));
			} else {
				$settings['new_subscriber_email'] = 'off';
			}

			if (!empty($_POST['new_email_subject'])) {
				$settings['new_email_subject'] = sanitize_text_field(wp_unslash($_POST['new_email_subject']));
			}

			if (!empty($_POST['new_email_body'])) {
				$settings['new_email_body'] = wp_kses_post(wp_unslash($_POST['new_email_body']));
			}

			if (!empty($_POST['renewal_reminder_email'])) {
				$settings['renewal_reminder_email'] = sanitize_text_field(wp_unslash($_POST['renewal_reminder_email']));
			} else {
				$settings['renewal_reminder_email'] = 'off';
			}

			if (!empty($_POST['renewal_reminder_email_subject'])) {
				$settings['renewal_reminder_email_subject'] = sanitize_text_field(wp_unslash($_POST['renewal_reminder_email_subject']));
			}

			if (!empty($_POST['renewal_reminder_email_body'])) {
				$settings['renewal_reminder_email_body'] = wp_kses_post(wp_unslash($_POST['renewal_reminder_email_body']));
			}

			if (!empty($_POST['renewal_reminder_days_before'])) {
				$settings['renewal_reminder_days_before'] = sanitize_text_field(wp_unslash($_POST['renewal_reminder_days_before']));
			}

			if (!empty($_POST['new_subscriber_admin_email'])) {
				$settings['new_subscriber_admin_email'] = sanitize_text_field(wp_unslash($_POST['new_subscriber_admin_email']));
			} else {
				$settings['new_subscriber_admin_email'] = 'off';
			}

			if (isset($_POST['admin_new_subscriber_email_subject'])) {
				$settings['admin_new_subscriber_email_subject'] = sanitize_text_field(wp_unslash($_POST['admin_new_subscriber_email_subject']));
			}

			if (isset($_POST['admin_new_subscriber_email_recipients'])) {
				$settings['admin_new_subscriber_email_recipients'] = sanitize_text_field(wp_unslash($_POST['admin_new_subscriber_email_recipients']));
			}
		}

		if ('restrictions' === $current_tab) {

			if (!empty($_POST['post_types'])) {
				$settings['post_types'] = sanitize_text_field(wp_unslash($_POST['post_types']));
			}

			if (isset($_POST['free_articles'])) {
				$settings['free_articles'] = absint($_POST['free_articles']);
			}

			if (!empty($_POST['cookie_expiration'])) {
				$settings['cookie_expiration'] = absint($_POST['cookie_expiration']);
			}

			if (!empty($_POST['cookie_expiration_interval'])) {
				$settings['cookie_expiration_interval'] = sanitize_text_field(wp_unslash($_POST['cookie_expiration_interval']));
			}

			if (!empty($_POST['restrict_pdf_downloads'])) {
				$settings['restrict_pdf_downloads'] = sanitize_text_field(wp_unslash($_POST['restrict_pdf_downloads']));
			} else {
				$settings['restrict_pdf_downloads'] = 'off';
			}

			if (!empty($_POST['restrictions'])) {
				// phpcs:ignore
				$settings['restrictions'] = $this->sanitize_restrictions($_POST['restrictions']);
			} else {
				$settings['restrictions'] = array();
			}

			if (!empty($_POST['enable_combined_restrictions'])) {
				$settings['enable_combined_restrictions'] = sanitize_text_field(wp_unslash($_POST['enable_combined_restrictions']));
			} else {
				$settings['enable_combined_restrictions'] = 'off';
			}

			if (isset($_POST['combined_restrictions_total_allowed'])) {
				$settings['combined_restrictions_total_allowed'] = sanitize_text_field(wp_unslash($_POST['combined_restrictions_total_allowed']));
			}

			if (!empty($_POST['enable_js_cookie_restrictions'])) {
				$settings['enable_js_cookie_restrictions'] = sanitize_text_field(wp_unslash($_POST['enable_js_cookie_restrictions']));
			} else {
				$settings['enable_js_cookie_restrictions'] = 'off';
			}

			if (!empty($_POST['bypass_paywall_restrictions'])) {
				$settings['bypass_paywall_restrictions']   = array_map('sanitize_text_field', wp_unslash($_POST['bypass_paywall_restrictions']));
				$settings['bypass_paywall_restrictions'][] = 'administrator';
			} else {
				$settings['bypass_paywall_restrictions'] = array('administrator');
			}

			if (isset($_POST['custom_excerpt_length'])) {

				if (strlen(sanitize_text_field(wp_unslash($_POST['custom_excerpt_length']))) > 0) {
					$settings['custom_excerpt_length'] = intval($_POST['custom_excerpt_length']);
				} else {
					$settings['custom_excerpt_length'] = '';
				}
			}

			if (isset($_POST['lead_in_elements'])) {

				if (strlen(sanitize_text_field(wp_unslash($_POST['lead_in_elements']))) > 0) {
					$settings['lead_in_elements'] = intval($_POST['lead_in_elements']);
				} else {
					$settings['lead_in_elements'] = '';
				}
			}

			if (isset($_POST['post_category_exceptions'])) {
				$settings['post_category_exceptions'] = sanitize_text_field(wp_unslash($_POST['post_category_exceptions']));
			}

			if (isset($_POST['post_tag_exceptions'])) {
				$settings['post_tag_exceptions'] = sanitize_text_field(wp_unslash($_POST['post_tag_exceptions']));
			}

			if (isset($_POST['js_restrictions_post_container'])) {
				$settings['js_restrictions_post_container'] = sanitize_text_field(wp_unslash($_POST['js_restrictions_post_container']));
			}

			if (isset($_POST['js_restrictions_page_container'])) {
				$settings['js_restrictions_page_container'] = sanitize_text_field(wp_unslash($_POST['js_restrictions_page_container']));
			}
		}

		if ('subscriptions' == $current_tab && 'general' == $current_section) {
			if (!empty($_POST['levels'])) {
				// phpcs:ignore
				foreach ($_POST['levels'] as $key => $level) {

					if (isset($_GET['level_id'])) {

						if ($key ==  $_GET['level_id']) {

							$settings['levels'][$key] = $this->sanitize_level($key, $level);
						}
					} else {

						$settings['levels'][$key] = $this->sanitize_level($key, $level);
					}
				}
			}
		}

		if ('payments' === $current_tab && 'general' == $current_section) {

			if (!empty($_POST['test_mode'])) {
				$settings['test_mode'] = sanitize_text_field(wp_unslash($_POST['test_mode']));
			} else {
				$settings['test_mode'] = apply_filters('zeen101_demo_test_mode', 'off');
			}

			if (!empty($_POST['payment_gateway'])) {

				$settings['payment_gateway'] = array_map('sanitize_text_field', wp_unslash($_POST['payment_gateway']));

				if (in_array('stripe', $settings['payment_gateway']) && in_array('stripe_checkout', $settings['payment_gateway'])) {
					$settings['payment_gateway'] = array('stripe');
				}
			} else {
				$settings['payment_gateway'] = array('manual');
			}

			if (isset($_POST['manual_payment_title'])) {
				$settings['manual_payment_title'] = sanitize_text_field(wp_unslash($_POST['manual_payment_title']));
			}

			if (isset($_POST['live_secret_key'])) {
				$settings['live_secret_key'] = apply_filters('zeen101_demo_stripe_live_secret_key', sanitize_text_field(wp_unslash($_POST['live_secret_key'])));
			}

			if (isset($_POST['live_publishable_key'])) {
				$settings['live_publishable_key'] = apply_filters('zeen101_demo_stripe_live_publishable_key', sanitize_text_field(wp_unslash($_POST['live_publishable_key'])));
			}

			if (isset($_POST['test_secret_key'])) {
				$settings['test_secret_key'] = apply_filters('zeen101_demo_stripe_test_secret_key', sanitize_text_field(wp_unslash($_POST['test_secret_key'])));
			}

			if (isset($_POST['test_publishable_key'])) {
				$settings['test_publishable_key'] = apply_filters('zeen101_demo_stripe_test_publishable_key', sanitize_text_field(wp_unslash($_POST['test_publishable_key'])));
			}

			if (isset($_POST['enable_stripe_elements'])) {
				$settings['enable_stripe_elements'] = sanitize_text_field(wp_unslash($_POST['enable_stripe_elements']));
			}

			if (!empty($_POST['stripe_webhooks_enabled'])) {
				$settings['stripe_webhooks_enabled'] = 'on';
			} else {
				$settings['stripe_webhooks_enabled'] = 'off';
			}

			if (isset($_POST['test_signing_secret'])) {
				$settings['test_signing_secret'] = sanitize_text_field(wp_unslash($_POST['test_signing_secret']));
			}

			if (isset($_POST['live_signing_secret'])) {
				$settings['live_signing_secret'] = sanitize_text_field(wp_unslash($_POST['live_signing_secret']));
			}

			if (isset($_POST['enable_apple_pay'])) {
				$settings['enable_apple_pay'] = sanitize_text_field(wp_unslash($_POST['enable_apple_pay']));
			}

			if (!empty($_POST['stripe_automatic_tax'])) {
				$settings['stripe_automatic_tax'] = 'on';
			} else {
				$settings['stripe_automatic_tax'] = 'off';
			}

			if (!empty($_POST['stripe_billing_address'])) {
				$settings['stripe_billing_address'] = 'on';
			} else {
				$settings['stripe_billing_address'] = 'off';
			}

			if (!empty($_POST['stripe_customer_portal'])) {
				$settings['stripe_customer_portal'] = 'on';
			} else {
				$settings['stripe_customer_portal'] = 'off';
			}

			if (!empty($_POST['stripe_restrict_assets'])) {
				$settings['stripe_restrict_assets'] = 'on';
			} else {
				$settings['stripe_restrict_assets'] = 'off';
			}

			if (isset($_POST['stripe_tax_behavior'])) {
				$settings['stripe_tax_behavior'] = sanitize_text_field(wp_unslash($_POST['stripe_tax_behavior']));
			}

			if (!empty($_POST['enable_paypal_on_registration'])) {
				$settings['enable_paypal_on_registration'] = sanitize_text_field(wp_unslash($_POST['enable_paypal_on_registration']));
			} else {
				$settings['enable_paypal_on_registration'] = apply_filters('zeen101_demo_enable_paypal_on_registration', 'off');
			}

			if (!empty($_POST['paypal_live_email'])) {
				$settings['paypal_live_email'] = apply_filters('zeen101_demo_paypal_live_email', sanitize_text_field(wp_unslash($_POST['paypal_live_email'])));
			}

			if (!empty($_POST['paypal_live_api_username'])) {
				$settings['paypal_live_api_username'] = apply_filters('zeen101_demo_paypal_live_api_username', sanitize_text_field(wp_unslash($_POST['paypal_live_api_username'])));
			}

			if (!empty($_POST['paypal_live_api_password'])) {
				$settings['paypal_live_api_password'] = apply_filters('zeen101_demo_paypal_live_api_password', sanitize_text_field(wp_unslash($_POST['paypal_live_api_password'])));
			}

			if (!empty($_POST['paypal_live_api_secret'])) {
				$settings['paypal_live_api_secret'] = apply_filters('zeen101_demo_paypal_live_api_secret', sanitize_text_field(wp_unslash($_POST['paypal_live_api_secret'])));
			}

			if (!empty($_POST['paypal_image_url'])) {
				$settings['paypal_image_url'] = sanitize_text_field(wp_unslash($_POST['paypal_image_url']));
			}

			if (!empty($_POST['paypal_sand_email'])) {
				$settings['paypal_sand_email'] = apply_filters('zeen101_demo_paypal_sand_email', sanitize_text_field(wp_unslash($_POST['paypal_sand_email'])));
			}

			if (!empty($_POST['paypal_sand_api_username'])) {
				$settings['paypal_sand_api_username'] = apply_filters('zeen101_demo_paypal_sand_api_username', sanitize_text_field(wp_unslash($_POST['paypal_sand_api_username'])));
			}

			if (!empty($_POST['paypal_sand_api_password'])) {
				$settings['paypal_sand_api_password'] = apply_filters('zeen101_demo_paypal_sand_api_password', sanitize_text_field(wp_unslash($_POST['paypal_sand_api_password'])));
			}

			if (!empty($_POST['paypal_sand_api_secret'])) {
				$settings['paypal_sand_api_secret'] = apply_filters('zeen101_demo_paypal_sand_api_secret', sanitize_text_field(wp_unslash($_POST['paypal_sand_api_secret'])));
			}

			if (!empty($_POST['leaky_paywall_currency'])) {
				$settings['leaky_paywall_currency'] = sanitize_text_field(wp_unslash($_POST['leaky_paywall_currency']));
			}

			if (!empty($_POST['leaky_paywall_currency_position'])) {
				$settings['leaky_paywall_currency_position'] = sanitize_text_field(wp_unslash($_POST['leaky_paywall_currency_position']));
			}

			if (!empty($_POST['leaky_paywall_thousand_separator'])) {
				$settings['leaky_paywall_thousand_separator'] = sanitize_text_field(wp_unslash($_POST['leaky_paywall_thousand_separator']));
			}

			if (!empty($_POST['leaky_paywall_decimal_separator'])) {
				$settings['leaky_paywall_decimal_separator'] = sanitize_text_field(wp_unslash($_POST['leaky_paywall_decimal_separator']));
			}

			if (isset($_POST['leaky_paywall_decimal_number'])) {
				$settings['leaky_paywall_decimal_number'] = sanitize_text_field(wp_unslash($_POST['leaky_paywall_decimal_number']));
			}
		}

		$settings = apply_filters('leaky_paywall_update_settings_settings', $settings, $current_tab);

		$this->update_settings($settings);

		do_action('leaky_paywall_update_settings', $settings, $current_tab, $current_section);

		return true;
	}

	/**
	 * Sanitize level settings
	 *
	 * @param array $levels The levels to sanitize.
	 * @return array
	 */
	public function sanitize_levels($levels)
	{
		$text_fields     = array('label', 'deleted', 'price', 'subscription_length_type', 'interval_count', 'interval', 'hide_subscribe_card');
		$textarea_fields = array('description', 'registration_form_description');

		foreach ($levels as $i => $level) {

			foreach ($level as $key => $value) {

				if (in_array($key, $text_fields)) {
					$levels[$i][$key] = sanitize_text_field(wp_unslash($value));
				}

				if (in_array($key, $textarea_fields)) {
					$levels[$i][$key] = wp_kses_post(wp_unslash($value));
				}

				if ('post_types' == $key) {
					$levels[$i][$key] = $this->sanitize_level_post_types($value);
				}
			}
		}

		return $levels;
	}

	/**
	 * Sanitize level settings
	 *
	 * @param array $levels The levels to sanitize.
	 * @return array
	 */
	public function sanitize_level($level_id, $level)
	{
		$text_fields     = array('label', 'deleted', 'price', 'subscription_length_type', 'interval_count', 'interval', 'hide_subscribe_card');
		$textarea_fields = array('description', 'registration_form_description');

		foreach ($level as $key => $value) {

			if (in_array($key, $text_fields)) {
				$levels[$level_id][$key] = sanitize_text_field(wp_unslash($value));
			}

			if (in_array($key, $textarea_fields)) {
				$levels[$level_id][$key] = wp_kses_post(wp_unslash($value));
			}

			if ('post_types' == $key) {
				$levels[$level_id][$key] = $this->sanitize_level_post_types($value);
			}
		}

		return $level;
	}

	/**
	 * Sanitize level post types
	 *
	 * @param array $post_types The post types to sanitize.
	 */
	public function sanitize_level_post_types($post_types)
	{
		foreach ($post_types as $i => $rules) {
			foreach ($rules as $key => $rule) {
				$post_types[$i][$key] = sanitize_text_field($rule);
			}
		}

		return $post_types;
	}

	/**
	 * Sanitize restriction settings
	 *
	 * @param array $restrictions restrictions settings.
	 * @return array
	 */
	public function sanitize_restrictions($restrictions)
	{
		foreach ($restrictions as $i => $restriction) {

			foreach ($restriction as $key => $value) {

				$restrictions[$i][$key] = array_map('sanitize_text_field', $value);
			}
		}

		return $restrictions;
	}

	/**
	 * Update Leaky Paywall options
	 *
	 * @param array $settings Leaky Paywall options data.
	 * @since 1.0.0
	 */
	public function update_settings($settings)
	{
		update_option('issuem-leaky-paywall', $settings);
		if ($this->is_site_wide_enabled()) {
			update_site_option('issuem-leaky-paywall', $settings);
		}
	}


	/**
	 * Check if Leaky Paywall MultiSite options are enabled
	 *
	 * @since 4.19
	 */
	public function is_site_wide_enabled()
	{
		return (is_multisite()) ? get_site_option('issuem-leaky-paywall-site-wide') : false;
	}

	/**
	 * Check if the current site has a caching plugin or known managed hosting setup
	 *
	 * @since 4.14.0
	 */
	public function check_for_caching()
	{
		$found = false;

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$checks = array(
			'wp-rocket/wp-rocket.php',
			'litespeed-cache/litespeed-cache.php',
			'wp-fastest-cache/wpFastestCache.php',
			'w3-total-cache/w3-total-cache.php',
			'wp-optimize/wp-optimize.php',
			'autoptimize/autoptimize.php',
			'cache-enabler/cache-enabler.php',
			'wp-super-cache/wp-cache.php',
			'hummingbird-performance/wp-hummingbird.php',
		);

		foreach ($checks as $check) {
			if (is_plugin_active($check)) {
				$found = true;
			}
		}

		return $found;
	}

	/**
	 * Allow for script tags in the subscribe and upgrade nags
	 *
	 * @since 4.19.1
	 */
	public function allowed_html()
	{
		$html_allowed = wp_kses_allowed_html('post');
		$html_allowed['script'] = array();
		$html_allowed['a']['onclick'] = 1;

		return $html_allowed;
	}
}
