<?php

/**
 * Leaky Paywall Tools page
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Leaky_Paywall_Tools {

	/**
	 * Get the available tools tabs.
	 *
	 * @return array
	 */
	public function get_tabs() {

		$tabs = array(
			'import'    => __( 'Import', 'leaky-paywall' ),
			'export'    => __( 'Export', 'leaky-paywall' ),
			'debug_log' => __( 'Debug Log', 'leaky-paywall' ),
		);

		return apply_filters( 'leaky_paywall_tools_tabs', $tabs );
	}

	/**
	 * Render the Tools page.
	 */
	public function tools_page() {

		$tabs        = $this->get_tabs();
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'import';

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'import';
		}

		?>

		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e( 'Tools', 'leaky-paywall' ); ?></h1>
				</span>
			</div>
		</div>

		<div class="lp-tab-wrap">

			<?php $this->output_tabs( $current_tab ); ?>

		</div>

		<div class="wrap">

			<div class="lp-tools-card">

				<?php $this->output_tab_content( $current_tab ); ?>

			</div>

		</div>

		<?php
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param string $current_tab The active tab slug.
	 */
	public function output_tabs( $current_tab ) {

		$tabs = $this->get_tabs();

		?>
		<h2 class="nav-tab-wrapper" style="margin-bottom: 10px;">

			<?php foreach ( $tabs as $slug => $label ) :

				$class     = $slug === $current_tab ? 'nav-tab-active' : '';
				$admin_url = admin_url( 'admin.php?page=leaky-paywall-tools&tab=' . $slug );

			?>
				<a href="<?php echo esc_url( $admin_url ); ?>" class="nav-tab <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>

		</h2>
		<?php
	}

	/**
	 * Dispatch tab content rendering.
	 *
	 * @param string $current_tab The active tab slug.
	 */
	public function output_tab_content( $current_tab ) {

		$method = 'output_' . $current_tab . '_tab';

		if ( method_exists( $this, $method ) ) {
			$this->$method();
		} else {
			do_action( 'leaky_paywall_tools_tab_' . $current_tab );
		}
	}

	/**
	 * Render the Import tab.
	 */
	public function output_import_tab() {

		$import = new Leaky_Paywall_Import();
		$import->process_requests();

		?>
		<h2><?php esc_html_e( 'Import Subscribers', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Import subscribers from a CSV file. Upload a CSV with subscriber data to bulk-create or update Leaky Paywall subscribers.', 'leaky-paywall' ); ?></p>

		<form id="leaky-paywall-subscriber-bulk-add" name="leaky-paywall-subscriber-bulk-add" method="post">

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'CSV File', 'leaky-paywall' ); ?></th>
					<td>
						<input class="regular-text" type="text" id="leaky_paywall_import_user_csv_file" name="leaky_paywall_bulk_import_user_csv_file" value="" />
						<input type="hidden" id="leaky_paywall_import_user_csv_file_id" name="leaky_paywall_bulk_import_user_csv_file_id" />
						<input id="leaky_paywall_upload_user_csv_button" type="button" class="button" value="<?php esc_attr_e( 'Upload CSV', 'leaky-paywall' ); ?>" />
						<p class="description">
							<a target="_blank" href="https://zeen101.com/wp-content/uploads/2016/04/leaky-user-csv-example.csv"><?php esc_html_e( 'Download example CSV file', 'leaky-paywall' ); ?></a>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input class="button-primary" type="submit" value="<?php esc_attr_e( 'Import Subscribers', 'leaky-paywall' ); ?>" />
			</p>

			<div class="lp-import-instructions">
				<p><?php esc_html_e( 'Use text encoding of UTF-8.', 'leaky-paywall' ); ?></p>
				<p><?php esc_html_e( 'Use a comma as the delimiter.', 'leaky-paywall' ); ?></p>
				<p><?php esc_html_e( 'Minimum required fields: email, level_id', 'leaky-paywall' ); ?></p>
				<p><?php esc_html_e( 'We recommend expires date in a Y-m-d format.', 'leaky-paywall' ); ?></p>
				<p><?php esc_html_e( 'Upload no more than 500 users per file. Split large imports into multiple files.', 'leaky-paywall' ); ?></p>
			</div>

			<?php wp_nonce_field( 'bulk_add_subscribers', 'leaky_paywall_bulk_add_subscribers' ); ?>

		</form>

		<?php
	}

	/**
	 * Render the Export tab.
	 */
	public function output_export_tab() {

		$settings = get_leaky_paywall_settings();

		?>
		<h2><?php esc_html_e( 'Export Subscribers', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Export your Leaky Paywall subscribers to a CSV file. Filter by date range, subscription level, payment status, and more.', 'leaky-paywall' ); ?></p>

		<form id="leaky-paywall-reporting-tool-form" method="post" action="">

			<p><?php esc_html_e( '1. If a subscriber was created while in test mode, Leaky Paywall must be in test mode to export the subscriber. If a subscriber was created while in live mode, Leaky Paywall must be in live mode to export the subscriber.', 'leaky-paywall' ); ?></p>

			<p><?php esc_html_e( '2. To export all subscribers, leave all fields blank.', 'leaky-paywall' ); ?></p>

			<table class="form-table">

				<tr>
					<th><?php esc_html_e( 'Created Date Range', 'leaky-paywall' ); ?></th>
					<td>
						<input type="text" id="created-start" name="created_start" value="" />
						&nbsp; &mdash; &nbsp;
						<input type="text" id="created-end" name="created_end" value="" />
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Expiration Range', 'leaky-paywall' ); ?></th>
					<td>
						<input type="text" id="expire-start" name="expire_start" value="" />
						&nbsp; &mdash; &nbsp;
						<input type="text" id="expire-end" name="expire_end" value="" />
						<?php
						$date_format        = 'F j, Y';
						$jquery_date_format = leaky_paywall_jquery_datepicker_format( $date_format );
						?>
						<input type="hidden" name="date_format" value="<?php echo esc_attr( $jquery_date_format ); ?>" />
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Price', 'leaky-paywall' ); ?></th>
					<td>
						<input type="text" id="price" name="price" value="" />
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Subscription Level', 'leaky-paywall' ); ?></th>
					<td>
						<select name="subscription_level[]" multiple="multiple" size="5">
							<?php
							foreach ( $settings['levels'] as $key => $level ) {
								echo '<option value="' . esc_attr( $key ) . '">' . esc_html( 'ID: ' . $key . ' - ' . stripslashes( $level['label'] ) ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Payment Status', 'leaky-paywall' ); ?></th>
					<td>
						<select name="subscriber_status[]" multiple="multiple" size="4">
							<option value="active"><?php esc_html_e( 'Active', 'leaky-paywall' ); ?></option>
							<option value="canceled"><?php esc_html_e( 'Canceled', 'leaky-paywall' ); ?></option>
							<option value="deactivated"><?php esc_html_e( 'Deactivated', 'leaky-paywall' ); ?></option>
							<option value="trial"><?php esc_html_e( 'Trial', 'leaky-paywall' ); ?></option>
							<option value="expired"><?php esc_html_e( 'Expired', 'leaky-paywall' ); ?></option>
						</select>
						<p class="description">
							<?php
							printf(
								/* translators: %s: documentation URL */
								__( 'For more details on what Leaky Paywall payment statuses mean, <a target="_blank" href="%s">please read our documentation</a>.', 'leaky-paywall' ),
								'https://docs.leakypaywall.com/article/70-i-have-an-expired-subscriber-that-has-an-active-status-can-they-get-access'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Payment Method', 'leaky-paywall' ); ?></th>
					<td>
						<select name="payment_method[]" multiple="multiple" size="4">
							<?php
							$payment_gateways = leaky_paywall_payment_gateways();
							foreach ( $payment_gateways as $key => $gateway ) {
								echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $gateway ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Subscriber ID', 'leaky-paywall' ); ?></th>
					<td>
						<input type="text" id="subscriber-id" name="subscriber_id" value="" />
					</td>
				</tr>

				<?php if ( function_exists( 'leaky_paywall_gift_subscription_generate_unique_code' ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Only Export Gift Subscriptions', 'leaky-paywall' ); ?></th>
						<td>
							<select name="gift_subscriptions" id="gift_subscriptions">
								<option value="0"><?php esc_html_e( 'No', 'leaky-paywall' ); ?></option>
								<option value="1"><?php esc_html_e( 'Yes', 'leaky-paywall' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endif; ?>

				<?php
				if ( is_plugin_active( 'leaky-paywall-custom-subscriber-fields/issuem-leaky-paywall-subscriber-meta.php' ) ) {
					global $dl_pluginissuem_leaky_paywall_subscriber_meta;
					$custom_meta_fields = $dl_pluginissuem_leaky_paywall_subscriber_meta->get_settings();

					if ( ! empty( $custom_meta_fields['meta_keys'] ) ) {
						foreach ( $custom_meta_fields['meta_keys'] as $meta_key ) {
							$label    = $meta_key['name'];
							$meta_key = sanitize_title_with_dashes( $meta_key['name'] );
							?>
							<tr>
								<th><?php echo esc_html( $label ); ?></th>
								<td>
									<input class="subscriber-meta-key subscriber-<?php echo esc_attr( $meta_key ); ?>-meta-key" type="text" value="" name="custom-meta-key[<?php echo esc_attr( $meta_key ); ?>]" />
								</td>
							</tr>
							<?php
						}
					}
				}
				?>

			</table>

			<p class="submit">
				<input class="button-primary" type="submit" id="leaky-paywall-reporting-tool-submit" name="generate_leaky_paywall_report" value="<?php esc_attr_e( 'Export Subscribers', 'leaky-paywall' ); ?>" />
			</p>

			<p id="leaky-paywall-reporting-tool-message"></p>

			<?php wp_nonce_field( 'submit_leaky_paywall_reporting_tool', 'leaky_paywall_reporting_tool_nonce' ); ?>

		</form>

		<?php
	}

	/**
	 * Render the Debug Log tab.
	 */
	public function output_debug_log_tab() {

		global $lp_logs;

		?>
		<h2><?php esc_html_e( 'Debug Log', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Use this tool to help debug Leaky Paywall functionality.', 'leaky-paywall' ); ?></p>

		<form id="lp-debug-log" method="post">
			<input type="hidden" name="lp_action" value="submit_debug_log" />
			<?php wp_nonce_field( 'lp_debug_log_action', 'lp_debug_log_field' ); ?>
			<p class="submit">
				<?php
				submit_button( __( 'Download Debug Log File', 'leaky-paywall' ), 'primary', 'lp-download-debug-log', false );
				echo '&nbsp;';
				submit_button( __( 'Clear Log', 'leaky-paywall' ), 'secondary lp-inline-button', 'lp-clear-debug-log', false );
				?>
			</p>
		</form>

		<p><?php esc_html_e( 'Log file', 'leaky-paywall' ); ?>: <code><?php echo esc_html( $lp_logs->get_log_file_path() ); ?></code></p>
		<?php
	}

}

/**
 * Handle debug log download and clear actions.
 */
function leaky_paywall_tools_handle_debug_log() {

	global $lp_logs;

	if ( ! isset( $_POST['lp_debug_log_field'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lp_debug_log_field'] ) ), 'lp_debug_log_action' )
	) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	if ( isset( $_POST['lp-download-debug-log'] ) ) {
		nocache_headers();

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="lp-debug-log.txt"' );

		print_r( stripslashes_deep( wp_unslash( $lp_logs->get_file_contents() ) ) );
		die( 'end of lp log' );

	} elseif ( isset( $_POST['lp-clear-debug-log'] ) ) {

		$lp_logs->clear_log_file();

		wp_safe_redirect( admin_url( 'admin.php?page=leaky-paywall-tools&tab=debug_log' ) );
		exit;
	}
}
add_action( 'admin_init', 'leaky_paywall_tools_handle_debug_log' );
