<?php

class Leaky_Paywall_Admin_Subscriber
{

	public static function show_subscribers_page()
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
				self::show_subscriber_page();
			} else {
				self::show_subscribers_table();
			}
			?>
		</div>


	<?php
	}

	public static function show_subscribers_table()
	{
		echo '<h3>show table</h3>';


		// Create an instance of our package class...
		$subscriber_table = new Leaky_Paywall_Subscriber_List_Table();
		$pagenum          = $subscriber_table->get_pagenum();
		// Fetch, prepare, sort, and filter our data...
		$subscriber_table->prepare_items();
		$total_pages = $subscriber_table->get_pagination_arg('total_pages');
		if ($pagenum > $total_pages && $total_pages > 0) {
			wp_safe_redirect(esc_url_raw(add_query_arg('paged', $total_pages)));
			exit();
		}

	?>
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

	public static function show_subscriber_page()
	{

		$id = absint($_GET['id']);

		$user = get_user_by('ID', $id);

		if (!$user) {
			echo '<p>No user found.</p>';
			return;
		}

		$levels = leaky_paywall_get_levels();
		$subscriber_notes    = get_user_meta($user->ID, '_leaky_paywall_subscriber_notes', true);
		$level_id = lp_get_subscriber_meta('level_id', $user);
		$expires = lp_get_subscriber_meta('expires', $user);
		$subscriber_id = lp_get_subscriber_meta('subscriber_id', $user);
		$status = lp_get_subscriber_meta('status', $user);

	?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Subscriber Dashboard</h1>
			<hr class="wp-header-end">

			<!-- Subscriber Section -->
			<div id="poststuff">
				<div class="postbox">
					<h2 class="hndle ui-sortable-handle"><span>Subscriber</span></h2>
					<div class="inside">
						<table class="form-table">
							<tr>
								<th><label for="first_name">First Name</label></th>
								<td><input name="first_name" type="text" id="first_name" value="<?php echo $user->first_name; ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="last_name">Last Name</label></th>
								<td><input name="last_name" type="text" id="last_name" value="<?php echo $user->last_name; ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="email">Email</label></th>
								<td><input name="email" type="email" id="email" value="<?php echo $user->user_email; ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="status">Status</label></th>
								<td><?php echo lp_render_subscription_status_badge($status); ?></td>
							</tr>
							<tr>
								<th><label for="subscriber_notes">Notes</label></th>
								<td><textarea name="subscriber_notes" id="subscriber_notes" class="large-text" rows="4"><?php echo $subscriber_notes; ?></textarea></td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Subscription Details Section -->
				<div class="postbox">
					<h2 class="hndle ui-sortable-handle"><span>Subscription Details</span></h2>
					<div class="inside">
						<table class="form-table">
							<tr>
								<th><label for="level_name">Level</label></th>
								<td>
									<select name="level_name" id="level_name">
										<?php
										foreach ($levels as $level) {
											echo '<option ' . selected($level_id, $level['id']) . ' value="' . $level['id'] . '">ID: ' . $level['id'] . ' - ' .  $level['label'] . '</option>';
										}
										?>

									</select>
								</td>
							</tr>
							<tr>
								<th><label for="subscriber_id">Subscriber ID</label></th>
								<td><input name="subscriber_id" type="text" id="subscriber_id" value="<?php echo $subscriber_id; ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="billing_date">Next Billing Date</label></th>
								<td><input name="billing_date" type="date" id="billing_date" value="<?php echo strtotime( $expires ); ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="expires">Expires</label></th>
								<td><input name="expires" type="text" id="expires" value="<?php echo $expires; ?>" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="subscription_status">Status</label></th>
								<td>
									<select name="subscription_status" id="subscription_status">
										<option value="active" selected>Active</option>
										<option value="paused">Paused</option>
										<option value="canceled">Canceled</option>
									</select>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Transactions Section -->
				<div class="postbox">
					<h2 class="hndle ui-sortable-handle"><span>Past Transactions</span></h2>
					<div class="inside">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>Date</th>
									<th>Description</th>
									<th>Amount</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>2025-03-01</td>
									<td>Subscription Renewal</td>
									<td>$29.99</td>
									<td><span class="status green">Paid</span></td>
								</tr>
								<tr>
									<td>2025-02-01</td>
									<td>Subscription Renewal</td>
									<td>$29.99</td>
									<td><span class="status green">Paid</span></td>
								</tr>
								<tr>
									<td>2025-01-01</td>
									<td>Subscription Renewal</td>
									<td>$29.99</td>
									<td><span class="status red">Failed</span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Save Button -->
				<p class="submit">
					<input type="submit" name="save_subscriber" id="save_subscriber" class="button button-primary" value="Save Changes">
				</p>
			</div>
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
