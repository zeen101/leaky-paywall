<?php

class Leaky_Paywall_Admin_Subscriber
{

	public function __construct()
	{
		add_action('admin_init', array($this, 'process_admin_subscriber_update'));
		add_action('admin_init', array($this, 'process_admin_subscriber_add'));
	}

	public function process_admin_subscriber_update()
	{

		if (!isset($_POST['leaky_paywall_admin_subscriber_update_nonce'])) {
			return;
		}

		if (! wp_verify_nonce($_POST['leaky_paywall_admin_subscriber_update_nonce'], 'leaky_paywall_admin_subscriber_update')) {
			return;
		}

		$user_id = absint($_GET['id']);

		$first_name = sanitize_text_field($_POST['first_name']);
		$last_name = sanitize_text_field($_POST['last_name']);
		$email = sanitize_text_field($_POST['email']);
		$subscriber_notes = sanitize_textarea_field($_POST['subscriber_notes']);
		$level_id = absint($_POST['level_id']);
		$subscriber_id = sanitize_text_field($_POST['subscriber_id']);
		$payment_status = sanitize_text_field($_POST['payment_status']);
		$payment_gateway = sanitize_text_field($_POST['payment_gateway']);
		$plan = sanitize_text_field($_POST['plan']);

		if (isset($_POST['first_name'])) {
			lp_update_subscriber_meta('first_name', $first_name, $user_id);
		}

		if (isset($_POST['last_name'])) {
			lp_update_subscriber_meta('last_name', $last_name, $user_id);
		}

		if (isset($_POST['email'])) {
			lp_update_subscriber_meta('email', $email, $user_id);
		}

		if (isset($_POST['subscriber_notes'])) {
			lp_update_subscriber_meta('subscriber_notes', $subscriber_notes, $user_id);
		}

		if (isset($_POST['level_id'])) {
			lp_update_subscriber_meta('level_id', $level_id, $user_id);
		}

		if (isset($_POST['subscriber_id'])) {
			lp_update_subscriber_meta('subscriber_id', $subscriber_id, $user_id);
		}

		if (isset($_POST['payment_status'])) {
			lp_update_subscriber_meta('payment_status', $payment_status, $user_id);
		}

		if (isset($_POST['plan'])) {
			lp_update_subscriber_meta('plan', $plan, $user_id);
		}

		if (isset($_POST['payment_gateway'])) {
			lp_update_subscriber_meta('payment_gateway', $payment_gateway, $user_id);
		}

		if (isset($_POST['leaky-paywall-subscriber-expires'])) {

			if ($_POST['leaky-paywall-subscriber-expires'] < 1) {
				$expires = 0;
			} else if (strtolower($_POST['leaky-paywall-subscriber-expires']) == 'never') {
				$expires = 0;
			} else {
				$expires = gmdate('Y-m-d 23:59:59', strtotime(trim(urldecode(sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-expires']))))));
			}

			lp_update_subscriber_meta('expires', $expires, $user_id);
		}

		do_action('update_leaky_paywall_subscriber', $user_id);
	}

	public function process_admin_subscriber_add()
	{
		if (!isset($_POST['leaky_paywall_admin_subscriber_add_nonce'])) {
			return;
		}

		if (! wp_verify_nonce($_POST['leaky_paywall_admin_subscriber_add_nonce'], 'leaky_paywall_admin_subscriber_add')) {
			return;
		}

		$login           = isset($_POST['leaky-paywall-subscriber-login']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-login'])) : '';
		$email           = isset($_POST['leaky-paywall-subscriber-email']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-email'])) : '';
		$payment_gateway = isset($_POST['leaky-paywall-subscriber-payment-gateway']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-payment-gateway'])) : '';
		$subscriber_id   = isset($_POST['leaky-paywall-subscriber-id']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-id'])) : '';
		if (empty($_POST['leaky-paywall-subscriber-expires'])) {
			$expires = 0;
		} else {
			$expires = gmdate('Y-m-d 23:59:59', strtotime(trim(urldecode(sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-expires']))))));
		}

		$meta = array(
			'level_id'        => isset($_POST['leaky-paywall-subscriber-level-id']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-level-id'])) : '',
			'subscriber_id'   => $subscriber_id,
			'price'           => isset($_POST['leaky-paywall-subscriber-price']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-price'])) : '',
			'description'     => __('Manual Addition', 'leaky-paywall'),
			'expires'         => $expires,
			'payment_gateway' => $payment_gateway,
			'payment_status'  => isset($_POST['leaky-paywall-subscriber-status']) ? sanitize_text_field(wp_unslash($_POST['leaky-paywall-subscriber-status'])) : '',
			'interval'        => 0,
			'plan'            => '',
			'site'            => leaky_paywall_get_current_site()
		);

		$user_id = leaky_paywall_new_subscriber(null, $email, $subscriber_id, $meta, $login);

		do_action('add_leaky_paywall_subscriber', $user_id);

		wp_safe_redirect(admin_url() . 'admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user_id);
		exit;
	}

	public function show_subscribers_page()
	{
?>
		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url(LEAKY_PAYWALL_URL) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title">Subscribers</h1>
				</span>
			</div>
		</div>


		<div class="wrap">
			<?php
			if (isset($_GET['action']) && $_GET['action'] == 'show') {
				$this->show_subscriber_page();
			} else if (isset($_GET['action']) && $_GET['action'] == 'add') {
				$this->add_subscriber_page();
			} else {
				$this->show_subscribers_table();
			}
			?>
		</div>


	<?php
	}

	public function show_subscribers_table()
	{
		$subscriber_table = new Leaky_Paywall_Subscriber_List_Table();
		$pagenum          = $subscriber_table->get_pagenum();
		$subscriber_table->prepare_items();
		$total_pages = $subscriber_table->get_pagination_arg('total_pages');
		if ($pagenum > $total_pages && $total_pages > 0) {
			wp_safe_redirect(esc_url_raw(add_query_arg('paged', $total_pages)));
			exit();
		}

	?>

		<div class="add-new-sub-container" style="float: right; margin-bottom: 20px;">
			<?php
			$add_link    = esc_url(add_query_arg([
				'action' => 'add',
			]));

			?>
			<a class="button button-primary" href="<?php echo $add_link; ?>">+ Add Subscriber</a>
		</div>

		<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
		<form id="leaky-paywall-subscribers" method="get">
			<!-- For plugins, we also need to ensure that the form posts back to our current page -->
			<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['page']))) : ''; ?>" />
			<!-- Now we can render the completed list table -->
			<div class="tablenav top">
				<?php $subscriber_table->user_views(); ?>
				<?php $subscriber_table->search_box(__('Search Subscribers'), 'leaky-paywall'); ?>
			</div>
			<?php $subscriber_table->display(); ?>
		</form>

	<?php

	}

	public function add_subscriber_page()
	{

		$settings = get_leaky_paywall_settings();
		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);

	?>
		<div class="wrap">
			<p><a href="<?php echo admin_url(); ?>admin.php?page=leaky-paywall-subscribers">All Subscribers ⤴</a></p>

			<form id="leaky-paywall-susbcriber-add" name="leaky-paywall-subscriber-add" method="post">
				<div style="display: table">
					<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php esc_html_e('Username (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-login" /></p>
					<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php esc_html_e('Email Address (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p>
					<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php esc_html_e('Price Paid', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="" placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
					<p>
						<label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php esc_html_e('Expires', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="" placeholder="<?php echo esc_attr(gmdate($date_format, time())); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
						<input type="hidden" name="date_format" value="<?php echo esc_attr($jquery_date_format); ?>" />
						<br><span style="color: #999;"><?php esc_html_e('Enter 0 for never expires', 'leaky-paywall'); ?></span>
					</p>
					<p>
						<label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php esc_html_e('Subscription Level', 'leaky-paywall'); ?></label>
						<select name="leaky-paywall-subscriber-level-id">
							<?php
							foreach ($settings['levels'] as $key => $level) {
								if (! $level['label']) {
									continue;
								}
								if (isset($level['deleted'])) {
									continue;
								}
								echo '<option value="' . esc_attr($key) . '">ID: ' . $key . ' - ' . esc_html(stripslashes($level['label']));
								echo isset($level['deleted']) && $level['deleted'] == 1 ? '(deleted)' : '';
								echo '</option>';
							}
							?>
						</select>
					</p>
					<p>
						<label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php esc_html_e('Payment Status', 'leaky-paywall'); ?></label>
						<select name="leaky-paywall-subscriber-status">
							<option value="active"><?php esc_html_e('Active', 'leaky-paywall'); ?></option>
							<option value="canceled"><?php esc_html_e('Canceled', 'leaky-paywall'); ?></option>
							<option value="deactivated"><?php esc_html_e('Deactivated', 'leaky-paywall'); ?></option>
						</select>
					</p>
					<p>
						<label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php esc_html_e('Payment Method', 'leaky-paywall'); ?></label>
						<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
						<select name="leaky-paywall-subscriber-payment-gateway">
							<?php
							foreach ($payment_gateways as $key => $gateway) {
								echo '<option value="' . esc_attr($key) . '">' . esc_html($gateway) . '</option>';
							}
							?>
						</select>
					</p>
					<p>
						<label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php esc_html_e('Subscriber ID', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-id" />
					</p>
					<?php do_action('add_leaky_paywall_subscriber_form'); ?>
				</div>
				<?php submit_button('Add New Subscriber'); ?>
				<?php // wp_nonce_field('add_new_subscriber', 'leaky_paywall_add_subscriber');
				?>

				<?php wp_nonce_field('leaky_paywall_admin_subscriber_add', 'leaky_paywall_admin_subscriber_add_nonce'); ?>

			</form>

		</div>
	<?php
	}

	public function show_subscriber_page()
	{

		$id = absint($_GET['id']);
		$user = get_user_by('ID', $id);

		if (!$user) {
			echo '<p>No user found.</p>';
			return;
		}

		$transactions = leaky_paywall_get_all_transactions_by_email($user->user_email);

		// sync with Stripe every time this page loads, if they have an active subscription

		$levels = leaky_paywall_get_levels();
		$subscriber_notes    = lp_get_subscriber_meta('notes', $user);
		$level_id = lp_get_subscriber_meta('level_id', $user);
		$expires = lp_get_subscriber_meta('expires', $user);
		$created = lp_get_subscriber_meta('created', $user);
		$subscriber_id = lp_get_subscriber_meta('subscriber_id', $user);
		$plan = lp_get_subscriber_meta('plan', $user);
		$payment_status = lp_get_subscriber_meta('payment_status', $user);
		$payment_gateway = lp_get_subscriber_meta('payment_gateway', $user);
		$has_access = leaky_paywall_user_has_access($user);

		if ($has_access) {
			$has_access = 'Yes';
		} else {
			$has_access = 'No';
		}

		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);

		if (empty($expires) || '0000-00-00 00:00:00' === $expires || 'Never' === $expires) {
			$expires = __('Never', 'leaky-paywall');
		} else {
			$expires = mysql2date($date_format, $expires, false);
		}

	?>
		<div class="wrap">
			<p><a href="<?php echo admin_url(); ?>admin.php?page=leaky-paywall-subscribers">All Subscribers ⤴</a></p>
			<h1 class="wp-heading-inline"><?php echo $user->user_email; ?></h1>
			<hr class="wp-header-end">

			<form method="POST" action="" id="leaky-paywall-admin-subscriber-form">

				<?php wp_nonce_field('leaky_paywall_admin_subscriber_update', 'leaky_paywall_admin_subscriber_update_nonce'); ?>

				<!-- Subscriber Section -->
				<div id="poststuff">
					<div id="post-body" class="metabox-holder">

						<h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Subscriber Details', 'leaky-paywall'); ?></span></h2>
						<div class="postbox">

							<div class="inside">
								<table class="form-table">
									<tr>
										<th><label for="first_name"><?php esc_html_e('First Name', 'leaky-paywall'); ?></label></th>
										<td><input name="first_name" type="text" id="first_name" value="<?php echo $user->first_name; ?>" class="regular-text"></td>
									</tr>
									<tr>
										<th><label for="last_name"><?php esc_html_e('Last Name', 'leaky-paywall'); ?></label></th>
										<td><input name="last_name" type="text" id="last_name" value="<?php echo $user->last_name; ?>" class="regular-text"></td>
									</tr>
									<tr>
										<th><label for="email"><?php esc_html_e('Email', 'leaky-paywall'); ?></label></th>
										<td><input name="email" type="email" id="email" value="<?php echo $user->user_email; ?>" class="regular-text"></td>
									</tr>
									<tr>
										<th><label for="subscriber_notes"><?php esc_html_e('Notes', 'leaky-paywall'); ?></label></th>
										<td><textarea name="subscriber_notes" id="subscriber_notes" class="large-text" rows="4"><?php echo $subscriber_notes; ?></textarea></td>
									</tr>
									<tr>
										<th><label for="created"><?php esc_html_e('Created', 'leaky-paywall'); ?></label></th>
										<td><?php echo gmdate( $date_format, strtotime( $created ) ); ?></td>
									</tr>
									<tr>
										<th><label for="has_access"><?php esc_html_e('Has Access', 'leaky-paywall'); ?></label></th>
										<td><?php echo $has_access; ?></td>
									</tr>
									<tr>
										<th><label for="password"><?php esc_html_e('Password', 'leaky-paywall'); ?></label></th>
										<td>
											<?php $edit_wp_link = admin_url() . 'user-edit.php?user_id=' . $user->ID; ?>
											<a href="<?php echo esc_url($edit_wp_link); ?>"><?php esc_html_e('Edit on WP User Profile', 'leaky-paywall'); ?></a>
										</td>
									</tr>

									<?php do_action('update_leaky_paywall_subscriber_form', $user->ID); ?>

								</table>

							</div>
						</div>

						<!-- Subscription Details Section -->
						<h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Subscription Details', 'leaky-paywall'); ?></span></h2>
						<div class="postbox">

							<div class="inside">
								<table class="form-table">
									<tr>
										<th><label for="payment_status"><?php esc_html_e('Payment Status', 'leaky-paywall'); ?></label></th>
										<td>
											<select name="payment_status" id="payment_status">
												<option value="active" <?php selected($payment_status, 'active'); ?>><?php esc_html_e('Active', 'leaky-paywall'); ?></option>
												<option value="canceled" <?php selected($payment_status, 'canceled'); ?>><?php esc_html_e('Canceled', 'leaky-paywall'); ?></option>
												<option value="deactivated" <?php selected($payment_status, 'deactivated'); ?>><?php esc_html_e('Deactivated', 'leaky-paywall'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="level_id"><?php esc_html_e('Level', 'leaky-paywall'); ?></label></th>
										<td>
											<select name="level_id" id="level_id">
												<?php
												foreach ($levels as $level) {
													echo '<option ' . selected($level_id, $level['id']) . ' value="' . $level['id'] . '">ID: ' . $level['id'] . ' - ' .  $level['label'] . '</option>';
												}
												?>

											</select>
										</td>
									</tr>
									<tr>
										<th><label for="subscriber_id"><?php esc_html_e('Subscriber ID', 'leaky-paywall'); ?></label></th>
										<td>
											<input name="subscriber_id" type="text" id="subscriber_id" value="<?php echo $subscriber_id; ?>" class="regular-text">
											<?php
											if (strpos($subscriber_id, 'cus_') !== false) {
												echo '<br>';
												$stripe_url = leaky_paywall_get_current_mode() == 'test' ? 'https://dashboard.stripe.com/test/customers/' : 'https://dashboard.stripe.com/customers/';
												echo '<a target="_blank" href="' . $stripe_url . $subscriber_id . '">View in Stripe</a>';
											}
											?>
										</td>
									</tr>
									<tr>
										<th><label for="plan"><?php esc_html_e('Plan', 'leaky-paywall'); ?></label></th>
										<?php
										if (!$plan) {
											$plan = 'Non-Recurring';
										}
										?>
										<td>
											<input name="plan" type="text" id="plan" value="<?php echo $plan; ?>" class="regular-text">
											<br><span style="color: #999;"><?php esc_html_e('Leave empty for Non-Recurring', 'leaky-paywall'); ?></span>
										</td>
									</tr>
									<tr>
										<th><label for="expires"><?php esc_html_e('Expires', 'leaky-paywall'); ?></label></th>
										<td>
											<input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo $expires; ?>" placeholder="<?php echo esc_attr(gmdate($date_format, time())); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
											<input type="hidden" name="date_format" value="<?php echo esc_attr($jquery_date_format); ?>" />
											<br><span style="color: #999;"><?php esc_html_e('Enter 0 for never expires', 'leaky-paywall'); ?></span>
										</td>
									</tr>
									<tr>
										<th>
											<label for="payment-gateway" style="display:table-cell"><?php esc_html_e('Payment Method', 'leaky-paywall'); ?></label>
										</th>
										<td>
											<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
											<select name="payment_gateway">
												<?php
												foreach ($payment_gateways as $key => $gateway) {
													echo '<option value="' . esc_attr($key) . '" ' . selected($key, $payment_gateway, false) . '>' . esc_html($gateway) . '</option>';
												}
												?>
											</select>
										</td>
									</tr>

									<?php do_action('update_leaky_paywall_subscription_form', $user->ID); ?>

								</table>
							</div>
						</div>

						<!-- Save Button -->
						<p class="submit">
							<input type="submit" name="save_subscriber" id="save_subscriber" class="button button-primary" value="Save Changes">
						</p>

						<!-- Transactions Section -->
						<div class="postbox">
							<h2 class="hndle ui-sortable-handle"><span><?php esc_html_e('Past Transactions', 'leaky-paywall'); ?></span></h2>
							<div class="inside">
								<table class="wp-list-table widefat fixed striped">
									<thead>
										<tr>
											<th><?php esc_html_e('Date', 'leaky-paywall'); ?></th>
											<th><?php esc_html_e('Description', 'leaky-paywall'); ?></th>
											<th><?php esc_html_e('Amount', 'leaky-paywall'); ?></th>
											<th><?php esc_html_e('Payment Type', 'leaky-paywall'); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php

										if ($transactions) {
											foreach ($transactions as $transaction) {

												$level_id = get_post_meta($transaction->ID, '_level_id', true);
												$level    = get_leaky_paywall_subscription_level($level_id);


										?>
												<tr>
													<td>
														<a href="<?php echo admin_url(); ?>/post.php?post=<?php echo $transaction->ID; ?>&action=edit"><?php echo date('M d, Y', strtotime($transaction->post_date)); ?></a>
													</td>
													<td>
														<?php echo absint($level_id) . ' - ' . isset($level['label']) ? esc_html($level['label']) : ''; ?>
													</td>
													<td>
														<?php echo leaky_paywall_get_current_currency_symbol() . esc_attr(get_post_meta($transaction->ID, '_price', true)); ?>
													</td>
													<td>
														<?php
															if ( !isset( $level['recurring'] ) ) {
																echo 'One-time payment';
															} else {
																echo get_post_meta($transaction->ID, '_is_recurring', true) ? 'Subscription Renewal' : 'Initial Subscription Payment';
															}
														?>

													</td>
												</tr>
											<?php
											}
										} else {
											?>
											<tr>
												<td colspan="4"><?php esc_html_e('No transactions found', 'leaky-paywall'); ?></td>
											</tr>
										<?php
										}

										?>

									</tbody>
								</table>
							</div>
						</div>

					</div>

				</div>

			</form>

		</div>

		<style>
			.subscription-badge {
				padding: 4px 8px;
				border-radius: 4px;
				font-weight: 600;
				display: inline-block;
				margin-bottom: 6px;
				font-size: 12px;
			}

			.status-active {
				background: #46b450;
				color: #fff;
			}

			.status-trial {
				background: #00a0d2;
				color: #fff;
			}

			.status-warning {
				background: #ffb900;
				color: #000;
			}

			.status-failed {
				background: #dc3232;
				color: #fff;
			}

			.status-paused {
				background: #999;
				color: #fff;
			}

			.status-canceled {
				background: #666;
				color: #fff;
			}

			.status-ended {
				background: #444;
				color: #fff;
			}

			.status-pending {
				background: #0073aa;
				color: #fff;
			}

			.status-unknown {
				background: #ccc;
				color: #000;
			}
		</style>


<?php


	}
}

new Leaky_Paywall_Admin_Subscriber();
