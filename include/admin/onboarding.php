<?php

/**
 * Leaky Paywall Onboarding Class
 *
 * @package     Leaky Paywall
 * @since       4.17.2
 */

/**
 * Load the LP_Onbarding class
 */
class Leaky_Paywall_Onboarding
{

	const STEPS_NUMBER = 3;

	const ONBOARDING_BASE_SLUG = 'lp-onboarding';

	public function __construct()
	{
		add_action('admin_menu', array($this, 'register_pages'));
		// add_action( 'activated_plugin', array( $this, 'maybe_start_onboarding' ) );
		add_action('admin_init', array($this, 'maybe_start_onboarding'));
	}

	public function maybe_start_onboarding()
	{

		$title = false;

		$title = isset($_GET['test_lp_onboarding']);

		if ($title) {
			wp_redirect(admin_url(sprintf('admin.php?page=%s-1', self::ONBOARDING_BASE_SLUG)));
			exit();
		}
	}

	public function register_pages()
	{
		for ($page_number = 1; $page_number <= self::STEPS_NUMBER; $page_number++) {
			$this->register_page('Onboarding wizard', $this->get_page_slug($page_number), array(
				$this,
				sprintf('step_%s', $page_number)
			));
		}
	}

	/**
	 * @param int $page_number
	 *
	 * @return string
	 */
	protected function get_page_slug($page_number)
	{
		return sprintf('%s-%d', self::ONBOARDING_BASE_SLUG, $page_number);
	}

	/**
	 * @param string $title
	 * @param string $slug
	 * @param callable $callable
	 */
	protected function register_page($title, $slug, $callable)
	{
		add_submenu_page('', __($title, 'leaky-paywall'), __($title, 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), $slug, $callable);
	}

	public function step_1()
	{
?>
		<div class="lp-onboarding lp-onboarding-step-1">
			<?php $this->steps_header(); ?>
			<div class="lp-onboarding__settings">
				<div class="lp-onboarding__settings-header">
					<h1>Start accepting payments today</h1>
				</div>
				<div class="lp-onboarding-wrapper">
					<p>Welcome to Leaky Paywall. Click below to Connect your Stripe Account.</p>

					<p><a href="<?php echo $this->get_connect_url(); ?>">Connect to Stripe</a></p>
				</div>
			</div>
			<?php $this->steps_footer(); ?>
		</div>

	<?php
	}

	public function step_2()
	{
	?>
		<div class="ssp-onboarding ssp-onboarding-step-1">
			<?php $this->steps_header(); ?>
			<div class="ssp-onboarding__settings">
				<div class="ssp-onboarding__settings-header">
					<h1>Let's get your publication started</h1>
				</div>
				<form class="ssp-onboarding__settings-body" action="<?php echo esc_attr($step_urls[$step_number + 1]); ?>" method="post">
					<div class="ssp-onboarding__settings-item">
						<h2>What’s the name of your publication?</h2>
						<label for="show_name">This will be the title shown to listeners. You can always change it later.</label>
						<input id="show_name" class="js-onboarding-field" type="text" name="data_title" value="<?php echo esc_attr($data_title); ?>">
					</div>
					<div class="ssp-onboarding__settings-item">
						<h2>What’s your publication about?</h2>
						<label for="show_description">Pique listeners' interest with a a few details about your podcast.</label>
						<textarea id="show_description" class="js-onboarding-field" name="data_description" rows="7"><?php echo esc_attr($data_description); ?></textarea>
					</div>
					<div class="ssp-onboarding__submit">
						<button type="submit" class="js-onboarding-btn" <?php if (empty($data_title) || empty($data_description)) echo 'disabled="disabled"' ?>>Proceed</button>
					</div>
				</form>
			</div>
			<?php $this->steps_footer(); ?>
		</div>
	<?php
	}

	public function steps_header()
	{
	?>
		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url(LEAKY_PAYWALL_URL) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title">Onboarding</h1>
				</span>
			</div>
		</div>
	<?php
	}

	public function steps_footer()
	{
	?>
		<h3 style="color: #9DA3AD; text-align: center;">Skip Setup</h3>
<?php
	}

	public function get_connect_url()
	{

		$return_url = add_query_arg(
			array(
				'page'      => 'issuem-leaky-paywall',
				'tab'       => 'payments',
			),
			admin_url('admin.php')
		);

		$refresh_url = add_query_arg(
			array(
				'page'      => 'issuem-leaky-paywall',
				'tab'       => 'payments',
				'connect_refresh'   => 'true'
			),
			admin_url('admin.php')
		);

		$mode = leaky_paywall_get_current_mode();

		$stripe_connect_url = add_query_arg(
			array(
				'live_mode'         => $mode == 'live' ? true : false,
				'state'             => str_pad(wp_rand(wp_rand(), PHP_INT_MAX), 100, wp_rand(), STR_PAD_BOTH),
				'customer_site_url' => esc_url_raw($return_url),
				'customer_refresh_url' => esc_url_raw($refresh_url)
			),
			'https://leakypaywall.com/?lp_gateway_connect_init=stripe_connect'
		);

		return $stripe_connect_url;
	}

	public function maybe_process_refresh()
	{

		if (!isset($_GET['connect_refresh'])) {
			return;
		}

		if ('true' == $_GET['connect_refresh']) {
			wp_redirect($this->get_connect_url());
			exit;
		}
	}

	public function maybe_process_return()
	{

		if (!isset($_GET['connected_account_id'])) {
			return;
		}

		if (! current_user_can(apply_filters('manage_leaky_paywall_settings', 'manage_options'))) {
			return;
		}

		$settings = get_leaky_paywall_settings();
		// $settings['connected_account_id'] = sanitize_text_field( $_GET['connected_account_id'] );

		$lp_credentials_url = add_query_arg(
			array(
				'mode'         => leaky_paywall_get_current_mode(),
				// 'state'             => sanitize_text_field($_GET['state']),
				'state'             => 'testing',
				'customer_site_url' => urlencode(home_url()),
			),
			'https://leakypaywall.com/?lp_gateway_connect_credentials=stripe_connect'
		);

		$response = wp_remote_get(esc_url_raw($lp_credentials_url));

		$data = json_decode($response['body']);

		$settings['test_publishable_key'] = $data->public_key;
		$settings['test_secret_key'] = $data->secret_key;
		update_leaky_paywall_settings($settings);
	}
}

new Leaky_Paywall_Onboarding();
