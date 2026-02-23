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

		if (! wp_verify_nonce(sanitize_key($_POST['leaky_paywall_admin_subscriber_update_nonce']), 'leaky_paywall_admin_subscriber_update')) {
			return;
		}

		if (!isset($_GET['id'])) {
			return;
		}

		$user_id = absint($_GET['id']);

		$first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
		$last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
		$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
		$subscriber_notes = isset($_POST['subscriber_notes']) ? sanitize_textarea_field($_POST['subscriber_notes']) : '';
		$level_id = isset($_POST['level_id']) ? absint($_POST['level_id']) : '';
		$subscriber_id = isset($_POST['subscriber_id']) ? sanitize_text_field($_POST['subscriber_id']) : '';
		$payment_status = isset($_POST['payment_status']) ? sanitize_text_field($_POST['payment_status']) : '';
		$payment_gateway = isset($_POST['payment_gateway']) ? sanitize_text_field($_POST['payment_gateway']) : '';
		$plan = isset($_POST['plan']) ? sanitize_text_field($_POST['plan']) : '';

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

			if (is_numeric($_POST['leaky-paywall-subscriber-expires']) && $_POST['leaky-paywall-subscriber-expires'] < 1) {
				$expires = 0;
			} else if (strtolower(sanitize_text_field($_POST['leaky-paywall-subscriber-expires'])) === 'never') {
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

		if (! wp_verify_nonce(sanitize_key($_POST['leaky_paywall_admin_subscriber_add_nonce']), 'leaky_paywall_admin_subscriber_add')) {
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
			if (isset($_GET['action']) && $_GET['action'] === 'show') {
				$this->show_subscriber_page();
			} else if (isset($_GET['action']) && $_GET['action'] === 'add') {
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
			<a class="button button-primary" href="#" id="lp-open-add-subscriber-modal">+ <?php esc_html_e('Add Subscriber', 'leaky-paywall'); ?></a>
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

		<?php $this->add_subscriber_modal(); ?>

	<?php

	}

	public function add_subscriber_modal()
	{
		$settings           = get_leaky_paywall_settings();
		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);
		$payment_gateways   = leaky_paywall_payment_gateways();
	?>
		<div id="lp-add-subscriber-modal" class="lp-modal-overlay" style="display:none;">
			<div class="lp-modal">
				<div class="lp-modal-header">
					<h2><?php esc_html_e('Add Subscriber', 'leaky-paywall'); ?></h2>
					<button type="button" class="lp-modal-close" aria-label="<?php esc_attr_e('Close', 'leaky-paywall'); ?>">&times;</button>
				</div>

				<form id="lp-add-subscriber-form">
					<div class="lp-modal-body">
						<div class="lp-modal-field">
							<label for="lp-modal-login"><?php esc_html_e('Username', 'leaky-paywall'); ?> <span class="required">*</span></label>
							<input id="lp-modal-login" type="text" name="leaky-paywall-subscriber-login" required />
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-email"><?php esc_html_e('Email Address', 'leaky-paywall'); ?> <span class="required">*</span></label>
							<input id="lp-modal-email" type="email" name="leaky-paywall-subscriber-email" placeholder="user@example.com" required />
						</div>

						<div class="lp-modal-field-row">
							<div class="lp-modal-field">
								<label for="lp-modal-first-name"><?php esc_html_e('First Name', 'leaky-paywall'); ?></label>
								<input id="lp-modal-first-name" type="text" name="leaky-paywall-subscriber-first-name" />
							</div>
							<div class="lp-modal-field">
								<label for="lp-modal-last-name"><?php esc_html_e('Last Name', 'leaky-paywall'); ?></label>
								<input id="lp-modal-last-name" type="text" name="leaky-paywall-subscriber-last-name" />
							</div>
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-price"><?php esc_html_e('Price Paid', 'leaky-paywall'); ?></label>
							<input id="lp-modal-price" type="text" name="leaky-paywall-subscriber-price" placeholder="0.00" />
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-expires"><?php esc_html_e('Expires', 'leaky-paywall'); ?></label>
							<input id="lp-modal-expires" type="text" name="leaky-paywall-subscriber-expires" class="datepicker" placeholder="<?php echo esc_attr(gmdate($date_format, time())); ?>" autocomplete="off" />
							<input type="hidden" name="date_format" value="<?php echo esc_attr($jquery_date_format); ?>" />
							<p class="lp-modal-field-hint"><?php esc_html_e('Enter 0 for never expires', 'leaky-paywall'); ?></p>
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-level"><?php esc_html_e('Subscription Level', 'leaky-paywall'); ?></label>
							<select id="lp-modal-level" name="leaky-paywall-subscriber-level-id">
								<?php foreach ($settings['levels'] as $key => $level) :
									if (empty($level['label']) || !empty($level['deleted'])) {
										continue;
									}
								?>
									<option value="<?php echo esc_attr($key); ?>">ID: <?php echo esc_html($key); ?> - <?php echo esc_html(stripslashes($level['label'])); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-status"><?php esc_html_e('Payment Status', 'leaky-paywall'); ?></label>
							<select id="lp-modal-status" name="leaky-paywall-subscriber-status">
								<option value="active"><?php esc_html_e('Active', 'leaky-paywall'); ?></option>
								<option value="pending_cancel"><?php esc_html_e('Pending Cancel', 'leaky-paywall'); ?></option>
								<option value="trial"><?php esc_html_e('Trial', 'leaky-paywall'); ?></option>
								<option value="past_due"><?php esc_html_e('Past Due', 'leaky-paywall'); ?></option>
								<option value="canceled"><?php esc_html_e('Canceled', 'leaky-paywall'); ?></option>
								<option value="expired"><?php esc_html_e('Expired', 'leaky-paywall'); ?></option>
								<option value="deactivated"><?php esc_html_e('Deactivated', 'leaky-paywall'); ?></option>
							</select>
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-gateway"><?php esc_html_e('Payment Method', 'leaky-paywall'); ?></label>
							<select id="lp-modal-gateway" name="leaky-paywall-subscriber-payment-gateway">
								<?php foreach ($payment_gateways as $key => $gateway) : ?>
									<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($gateway); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="lp-modal-field">
							<label for="lp-modal-subscriber-id"><?php esc_html_e('Subscriber ID', 'leaky-paywall'); ?></label>
							<input id="lp-modal-subscriber-id" type="text" name="leaky-paywall-subscriber-id" />
						</div>

						<?php do_action('add_leaky_paywall_subscriber_form'); ?>
					</div>

					<div class="lp-modal-footer">
						<div class="lp-modal-error" style="display:none;"></div>
						<div class="lp-modal-footer-buttons">
							<button type="button" class="button lp-modal-cancel"><?php esc_html_e('Cancel', 'leaky-paywall'); ?></button>
							<button type="submit" class="button button-primary" id="lp-modal-submit"><?php esc_html_e('Add Subscriber', 'leaky-paywall'); ?></button>
						</div>
					</div>
				</form>

				<div class="lp-modal-success" style="display:none;">
					<div class="lp-modal-success-icon">&#10003;</div>
					<p class="lp-modal-success-message"></p>
					<p class="lp-modal-success-link"></p>
				</div>
			</div>
		</div>
	<?php
	}

	public function ajax_add_subscriber()
	{
		if (!check_ajax_referer('leaky_paywall_admin_subscriber_add', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Security check failed.', 'leaky-paywall')));
		}

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'leaky-paywall')));
		}

		$login      = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
		$email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';

		if (empty($login) || empty($email)) {
			wp_send_json_error(array('message' => __('Username and email are required.', 'leaky-paywall')));
		}

		if (get_user_by('email', $email)) {
			wp_send_json_error(array('message' => __('A user with that email address already exists.', 'leaky-paywall')));
		}

		$payment_gateway = isset($_POST['payment_gateway']) ? sanitize_text_field(wp_unslash($_POST['payment_gateway'])) : '';
		$subscriber_id   = isset($_POST['subscriber_id']) ? sanitize_text_field(wp_unslash($_POST['subscriber_id'])) : '';

		if (empty($_POST['expires'])) {
			$expires = 0;
		} else {
			$expires = gmdate('Y-m-d 23:59:59', strtotime(trim(urldecode(sanitize_text_field(wp_unslash($_POST['expires']))))));
		}

		$meta = array(
			'level_id'        => isset($_POST['level_id']) ? sanitize_text_field(wp_unslash($_POST['level_id'])) : '',
			'subscriber_id'   => $subscriber_id,
			'price'           => isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '',
			'description'     => __('Manual Addition', 'leaky-paywall'),
			'expires'         => $expires,
			'payment_gateway' => $payment_gateway,
			'payment_status'  => isset($_POST['payment_status']) ? sanitize_text_field(wp_unslash($_POST['payment_status'])) : '',
			'interval'        => 0,
			'plan'            => '',
			'site'            => leaky_paywall_get_current_site(),
			'first_name'      => $first_name,
			'last_name'       => $last_name,
		);

		$user_id = leaky_paywall_new_subscriber(null, $email, $subscriber_id, $meta, $login);

		if (!is_wp_error($user_id) && $user_id) {
			wp_update_user(array(
				'ID'         => $user_id,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			));
		}

		if (is_wp_error($user_id)) {
			wp_send_json_error(array('message' => $user_id->get_error_message()));
		}

		do_action('add_leaky_paywall_subscriber', $user_id);

		$view_url = admin_url('admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user_id);

		wp_send_json_success(array(
			'user_id' => $user_id,
			'message' => __('Subscriber added successfully.', 'leaky-paywall'),
			'url'     => $view_url,
		));
	}

	public function add_subscriber_page()
	{

		$settings = get_leaky_paywall_settings();
		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);

	?>
		<div class="wrap">
			<p><a href="<?php echo esc_url(admin_url()); ?>admin.php?page=leaky-paywall-subscribers"><?php esc_html_e('All Subscribers', 'leaky-paywall'); ?> ⤴</a></p>

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
								echo '<option value="' . esc_attr($key) . '">ID: ' . esc_html($key) . ' - ' . esc_html(stripslashes($level['label']));
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
							<option value="pending_cancel"><?php esc_html_e('Pending Cancel', 'leaky-paywall'); ?></option>
							<option value="trial"><?php esc_html_e('Trial', 'leaky-paywall'); ?></option>
							<option value="past_due"><?php esc_html_e('Past Due', 'leaky-paywall'); ?></option>
							<option value="canceled"><?php esc_html_e('Canceled', 'leaky-paywall'); ?></option>
							<option value="expired"><?php esc_html_e('Expired', 'leaky-paywall'); ?></option>
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

				<?php wp_nonce_field('leaky_paywall_admin_subscriber_add', 'leaky_paywall_admin_subscriber_add_nonce'); ?>

			</form>

		</div>
	<?php
	}

	public function show_subscriber_page()
	{

		$id = isset($_GET['id']) ? absint($_GET['id']) : '';
		$user = get_user_by('ID', $id);

		if (!$user) {
			echo '<p>No user found.</p>';
			return;
		}

		$transactions = leaky_paywall_get_all_transactions_by_email($user->user_email);

		// sync with Stripe every time this page loads, if they have an active subscription
		leaky_paywall_sync_stripe_subscription($user);

		$levels = leaky_paywall_get_levels();
		$subscriber_notes    = lp_get_subscriber_meta('notes', $user);
		$level_id = lp_get_subscriber_meta('level_id', $user);
		$expires = lp_get_subscriber_meta('expires', $user);
		$created = lp_get_subscriber_meta('created', $user) ? lp_get_subscriber_meta('created', $user) : $user->user_registered;
		$subscriber_id = lp_get_subscriber_meta('subscriber_id', $user);
		$plan = lp_get_subscriber_meta('plan', $user);
		$payment_status = lp_get_subscriber_meta('payment_status', $user);
		$payment_gateway = lp_get_subscriber_meta('payment_gateway', $user);

		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);

		if (empty($expires) || '0000-00-00 00:00:00' === $expires || 'Never' === $expires) {
			$expires = __('Never', 'leaky-paywall');
		} else {
			$expires = mysql2date($date_format, $expires, false);
		}

	?>
		<style>
			.lp-sub-page { max-width: 1100px; margin-top: 10px; }
			.lp-sub-back { display: inline-block; font-size: 13px; color: #2271b1; text-decoration: none; margin-bottom: 12px; }
			.lp-sub-back:hover { color: #135e96; }
			.lp-sub-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
			.lp-sub-header h1 { margin: 0; font-size: 20px; font-weight: 600; color: #1d2327; }
			.lp-sub-header .lp-status-badge { font-size: 12px; }
			.lp-sub-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
			.lp-sub-card {
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
				padding: 24px;
			}
			.lp-sub-card h3 {
				margin: 0 0 16px 0;
				font-size: 14px;
				font-weight: 600;
				color: #1d2327;
			}
			.lp-sub-card--full { grid-column: 1 / -1; }
			.lp-sub-field { margin-bottom: 16px; }
			.lp-sub-field:last-child { margin-bottom: 0; }
			.lp-sub-field label,
			.lp-sub-field-label {
				display: block;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.4px;
				color: #888;
				margin-bottom: 5px;
			}
			.lp-sub-field input[type="text"],
			.lp-sub-field input[type="email"],
			.lp-sub-field textarea,
			.lp-sub-field select {
				width: 100%;
				max-width: 100%;
				box-sizing: border-box;
			}
			.lp-sub-field textarea { resize: vertical; }
			.lp-sub-field .lp-sub-field-hint {
				font-size: 12px;
				color: #999;
				margin-top: 4px;
			}
			.lp-sub-field-static {
				font-size: 14px;
				color: #1d2327;
			}
			.lp-sub-table {
				width: 100%;
				border-collapse: collapse;
			}
			.lp-sub-table thead th {
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.4px;
				color: #888;
				padding: 0 12px 10px 0;
				border-bottom: 2px solid #f0f0f1;
				text-align: left;
			}
			.lp-sub-table tbody td {
				padding: 10px 12px 10px 0;
				border-bottom: 1px solid #f5f5f5;
				font-size: 13px;
				color: #1d2327;
			}
			.lp-sub-table tbody tr:last-child td { border-bottom: none; }
			.lp-sub-table a { color: #2271b1; text-decoration: none; }
			.lp-sub-table a:hover { color: #135e96; }
			.lp-sub-save { margin-top: 0; }
			.lp-sub-empty { font-size: 13px; color: #999; margin: 0; }
		</style>

		<div class="lp-sub-page">

			<a href="<?php echo esc_url(admin_url('admin.php?page=leaky-paywall-subscribers')); ?>" class="lp-sub-back">&larr; <?php esc_html_e('All Subscribers', 'leaky-paywall'); ?></a>

			<div class="lp-sub-header">
				<h1><?php echo esc_html($user->user_email); ?></h1>
				<span class="lp-status-badge lp-status-badge--<?php echo esc_attr($payment_status); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $payment_status))); ?></span>
			</div>

			<form method="POST" action="" id="leaky-paywall-admin-subscriber-form">

				<?php wp_nonce_field('leaky_paywall_admin_subscriber_update', 'leaky_paywall_admin_subscriber_update_nonce'); ?>

				<div class="lp-sub-grid">

					<!-- Subscriber Details -->
					<div class="lp-sub-card">
						<h3><?php esc_html_e('Subscriber Details', 'leaky-paywall'); ?></h3>

						<div class="lp-sub-field">
							<label for="first_name"><?php esc_html_e('First Name', 'leaky-paywall'); ?></label>
							<input name="first_name" type="text" id="first_name" value="<?php echo esc_attr($user->first_name); ?>" class="regular-text">
						</div>
						<div class="lp-sub-field">
							<label for="last_name"><?php esc_html_e('Last Name', 'leaky-paywall'); ?></label>
							<input name="last_name" type="text" id="last_name" value="<?php echo esc_attr($user->last_name); ?>" class="regular-text">
						</div>
						<div class="lp-sub-field">
							<label for="email"><?php esc_html_e('Email', 'leaky-paywall'); ?></label>
							<input name="email" type="email" id="email" value="<?php echo esc_attr($user->user_email); ?>" class="regular-text">
						</div>
						<div class="lp-sub-field">
							<label for="subscriber_notes"><?php esc_html_e('Notes', 'leaky-paywall'); ?></label>
							<textarea name="subscriber_notes" id="subscriber_notes" rows="4"><?php echo esc_html($subscriber_notes); ?></textarea>
						</div>
						<div class="lp-sub-field">
							<span class="lp-sub-field-label"><?php esc_html_e('Created', 'leaky-paywall'); ?></span>
							<span class="lp-sub-field-static"><?php echo esc_html(gmdate($date_format, strtotime($created))); ?></span>
						</div>
						<div class="lp-sub-field">
							<span class="lp-sub-field-label"><?php esc_html_e('Password', 'leaky-paywall'); ?></span>
							<?php $edit_wp_link = admin_url('user-edit.php?user_id=' . $user->ID); ?>
							<a href="<?php echo esc_url($edit_wp_link); ?>"><?php esc_html_e('Edit on WP User Profile', 'leaky-paywall'); ?></a>
						</div>

						<?php if (is_multisite_premium()) : ?>
						<div class="lp-sub-field">
							<label for="site"><?php esc_html_e('Site', 'leaky-paywall'); ?></label>
							<input name="site" type="text" id="site" value="<?php echo esc_attr(get_leaky_paywall_subscribers_site_id_by_subscriber_email($user->user_email)); ?>" class="regular-text">
						</div>
						<?php endif; ?>

						<?php do_action('update_leaky_paywall_subscriber_form', $user->ID); ?>
					</div>

					<!-- Subscription Details -->
					<div class="lp-sub-card">
						<h3><?php esc_html_e('Subscription Details', 'leaky-paywall'); ?></h3>

						<div class="lp-sub-field">
							<label for="payment_status"><?php esc_html_e('Payment Status', 'leaky-paywall'); ?></label>
							<select name="payment_status" id="payment_status">
								<option value="active" <?php selected($payment_status, 'active'); ?>><?php esc_html_e('Active', 'leaky-paywall'); ?></option>
								<option value="pending_cancel" <?php selected($payment_status, 'pending_cancel'); ?>><?php esc_html_e('Pending Cancel', 'leaky-paywall'); ?></option>
								<option value="trial" <?php selected($payment_status, 'trial'); ?>><?php esc_html_e('Trial', 'leaky-paywall'); ?></option>
								<option value="past_due" <?php selected($payment_status, 'past_due'); ?>><?php esc_html_e('Past Due', 'leaky-paywall'); ?></option>
								<option value="canceled" <?php selected($payment_status, 'canceled'); ?>><?php esc_html_e('Canceled', 'leaky-paywall'); ?></option>
								<option value="expired" <?php selected($payment_status, 'expired'); ?>><?php esc_html_e('Expired', 'leaky-paywall'); ?></option>
								<option value="deactivated" <?php selected($payment_status, 'deactivated'); ?>><?php esc_html_e('Deactivated', 'leaky-paywall'); ?></option>
							</select>
						</div>
						<div class="lp-sub-field">
							<label for="level_id"><?php esc_html_e('Level', 'leaky-paywall'); ?></label>
							<select name="level_id" id="level_id">
								<?php
								foreach ($levels as $level) {
									echo '<option ' . selected($level_id, $level['id']) . ' value="' . esc_attr($level['id']) . '">ID: ' . esc_html($level['id']) . ' - ' .  esc_html($level['label']) . '</option>';
								}
								?>
							</select>
						</div>
						<div class="lp-sub-field">
							<label for="subscriber_id"><?php esc_html_e('Subscriber ID', 'leaky-paywall'); ?></label>
							<input name="subscriber_id" type="text" id="subscriber_id" value="<?php echo esc_attr($subscriber_id); ?>" class="regular-text">
							<?php
							if (strpos($subscriber_id, 'cus_') !== false) {
								$stripe_url = leaky_paywall_get_current_mode() == 'test' ? 'https://dashboard.stripe.com/test/customers/' : 'https://dashboard.stripe.com/customers/';
								echo '<div class="lp-sub-field-hint"><a target="_blank" href="' . esc_url($stripe_url . $subscriber_id) . '">View in Stripe &rarr;</a></div>';
							}
							?>
						</div>
						<div class="lp-sub-field">
							<label for="plan"><?php esc_html_e('Plan', 'leaky-paywall'); ?></label>
							<?php
							if (!$plan) {
								$plan = 'Non-Recurring';
							}
							?>
							<input name="plan" type="text" id="plan" value="<?php echo esc_attr($plan); ?>" class="regular-text">
							<div class="lp-sub-field-hint"><?php esc_html_e('Leave empty for Non-Recurring', 'leaky-paywall'); ?></div>
						</div>
						<div class="lp-sub-field">
							<label for="leaky-paywall-subscriber-expires"><?php esc_html_e('Expires', 'leaky-paywall'); ?></label>
							<input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo esc_attr($expires); ?>" placeholder="<?php echo esc_attr(gmdate($date_format, time())); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
							<input type="hidden" name="date_format" value="<?php echo esc_attr($jquery_date_format); ?>" />
							<div class="lp-sub-field-hint"><?php esc_html_e('Enter 0 for never expires', 'leaky-paywall'); ?></div>
						</div>
						<div class="lp-sub-field">
							<label for="payment_gateway"><?php esc_html_e('Payment Method', 'leaky-paywall'); ?></label>
							<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
							<select name="payment_gateway">
								<?php
								foreach ($payment_gateways as $key => $gateway) {
									echo '<option value="' . esc_attr($key) . '" ' . selected($key, $payment_gateway, false) . '>' . esc_html($gateway) . '</option>';
								}
								?>
							</select>
						</div>

						<?php do_action('update_leaky_paywall_subscription_form', $user->ID); ?>
					</div>

				</div>

				<!-- Save Button -->
				<p class="submit lp-sub-save">
					<input type="submit" name="save_subscriber" id="save_subscriber" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'leaky-paywall'); ?>">
				</p>

				<!-- Transactions -->
				<div class="lp-sub-grid">
					<div class="lp-sub-card lp-sub-card--full">
						<h3><?php esc_html_e('Past Transactions', 'leaky-paywall'); ?></h3>
						<?php if ($transactions) : ?>
						<table class="lp-sub-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Date', 'leaky-paywall'); ?></th>
									<th><?php esc_html_e('Description', 'leaky-paywall'); ?></th>
									<th><?php esc_html_e('Amount', 'leaky-paywall'); ?></th>
									<th><?php esc_html_e('Payment Type', 'leaky-paywall'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($transactions as $transaction) :
									$txn_level_id = get_post_meta($transaction->ID, '_level_id', true);
									$txn_level    = get_leaky_paywall_subscription_level($txn_level_id);
								?>
								<tr>
									<td><a href="<?php echo esc_url(admin_url('post.php?post=' . $transaction->ID . '&action=edit')); ?>"><?php echo esc_html(gmdate('M j, Y', strtotime($transaction->post_date))); ?></a></td>
									<td><?php echo isset($txn_level['label']) ? esc_html($txn_level['label']) : esc_html('#' . $txn_level_id); ?></td>
									<td><?php echo esc_html(leaky_paywall_get_current_currency_symbol()) . esc_html(get_post_meta($transaction->ID, '_price', true)); ?></td>
									<td>
										<?php
										if (!isset($txn_level['recurring'])) {
											esc_html_e('One-time payment', 'leaky-paywall');
										} else {
											echo get_post_meta($transaction->ID, '_is_recurring', true) ? esc_html__('Subscription Renewal', 'leaky-paywall') : esc_html__('Initial Subscription Payment', 'leaky-paywall');
										}
										?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php else : ?>
							<p class="lp-sub-empty"><?php esc_html_e('No transactions found.', 'leaky-paywall'); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Status History -->
				<div class="lp-sub-grid">
					<div class="lp-sub-card lp-sub-card--full">
						<h3><?php esc_html_e('Status History', 'leaky-paywall'); ?></h3>
						<?php
						$status_log = leaky_paywall_get_status_log($user->ID);

						if (!empty($status_log)) :
							$status_log = array_reverse($status_log);
						?>
						<table class="lp-sub-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Date', 'leaky-paywall'); ?></th>
									<th><?php esc_html_e('Change', 'leaky-paywall'); ?></th>
									<th><?php esc_html_e('Source', 'leaky-paywall'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($status_log as $entry) : ?>
								<tr>
									<td><?php echo esc_html(date_i18n('M j, Y g:i a', $entry['date'])); ?></td>
									<td>
										<span class="lp-status-badge lp-status-badge--<?php echo esc_attr($entry['from']); ?>"><?php echo esc_html(lp_get_status_label($entry['from'])); ?></span>
										&rarr;
										<span class="lp-status-badge lp-status-badge--<?php echo esc_attr($entry['to']); ?>"><?php echo esc_html(lp_get_status_label($entry['to'])); ?></span>
									</td>
									<td><?php echo esc_html(lp_get_source_label($entry['source'])); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php else : ?>
							<p class="lp-sub-empty"><?php esc_html_e('No status changes recorded yet.', 'leaky-paywall'); ?></p>
						<?php endif; ?>
					</div>
				</div>

			</form>

		</div>


<?php


	}
}

new Leaky_Paywall_Admin_Subscriber();
