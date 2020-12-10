<?php

/**
 * Registers zeen101's Leaky Paywall class
 *
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

/**
 * This class registers the main issuem functionality
 *
 * @since 1.0.0
 */
if (!class_exists('Leaky_Paywall')) {

	class Leaky_Paywall
	{

		private $plugin_name	= LEAKY_PAYWALL_NAME;
		private $plugin_slug	= LEAKY_PAYWALL_SLUG;
		private $basename		= LEAKY_PAYWALL_BASENAME;

		/**
		 * Class constructor, puts things in motion
		 *
		 * @since 1.0.0
		 */
		function __construct()
		{

			$settings = $this->get_settings();

			add_action('admin_notices', array($this, 'update_notices'));

			add_action('http_api_curl', array($this, 'force_ssl_version'));

			add_action('admin_init', array($this, 'upgrade'));

			add_action('admin_enqueue_scripts', array($this, 'admin_wp_enqueue_scripts'));
			add_action('admin_print_styles', array($this, 'admin_wp_print_styles'));
			add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

			add_action('admin_menu', array($this, 'admin_menu'));

			add_action('wp_ajax_leaky_paywall_process_notice_link', array($this, 'ajax_process_notice_link'));

			add_action('wp', array($this, 'process_content_restrictions'));
			add_action('wp', array($this, 'process_pdf_restrictions'));
			add_action('wp', array($this, 'process_cancellation_request'));
			add_action('init', array($this, 'process_js_content_restrictions'));

			if ('on' === $settings['restrict_pdf_downloads']) {
				add_filter('issuem_pdf_attachment_url', array($this, 'restrict_pdf_attachment_url'), 10, 2);
			}
		}

		public function process_js_content_restrictions()
		{
			$settings = get_leaky_paywall_settings();

			if ('on' === $settings['enable_js_cookie_restrictions']) {
				$restrictions = new Leaky_Paywall_Restrictions();
				$restrictions->process_js_content_restrictions();
			}
		}

		public function process_content_restrictions()
		{

			if (is_admin()) {
				return;
			}

			$settings = get_leaky_paywall_settings();

			if ('on' === $settings['enable_js_cookie_restrictions']) {
				return;
			}

			$restrictions = new Leaky_Paywall_Restrictions();
			$restrictions->process_content_restrictions();
		}

		public function process_pdf_restrictions()
		{

			if (isset($_GET['issuem-pdf-download'])) {
				$restrictions = new Leaky_Paywall_Restrictions();
				$restrictions->pdf_access();
			}
		}

		public function process_cancellation_request()
		{
			$settings = get_leaky_paywall_settings();

			if (leaky_paywall_has_user_paid()) {

				if ($this->is_cancel_request()) {
					wp_die(leaky_paywall_cancellation_confirmation(), $settings['site_name'] . ' - Cancel Request');
				}

				$this->redirect_from_login_page();
			} else {

				if (!empty($_REQUEST['r'])) {
					$this->process_passwordless_login();
				}
			}
		}

		public function is_cancel_request()
		{
			$settings = get_leaky_paywall_settings();

			if (isset($_REQUEST['cancel'])) {

				if (
					(!empty($settings['page_for_subscription']) && is_page($settings['page_for_subscription']))
					|| (!empty($settings['page_for_profile']) && is_page($settings['page_for_profile']))
				) {
					return true;
				} else if (isset($_GET['lp_cancel'])) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

		/**
		 * Send the user to the my account page if they user has paid and they try to access the login page
		 *
		 * @since 4.10.3
		 */
		public function redirect_from_login_page()
		{

			$settings = get_leaky_paywall_settings();

			if (!empty($settings['page_for_login']) && is_page($settings['page_for_login'])) {

				if (!empty($settings['page_for_profile'])) {
					wp_safe_redirect(get_page_link($settings['page_for_profile']));
				} else if (!empty($settings['page_for_subscription'])) {
					wp_safe_redirect(get_page_link($settings['page_for_subscription']));
				}
			}
		}

		/**
		 * Process the passwordless login functionality
		 *
		 * @since 4.10.3
		 */
		public function process_passwordless_login()
		{

			$settings = get_leaky_paywall_settings();

			if (!empty($settings['page_for_login']) && is_page($settings['page_for_login'])) {

				$login_hash = $_REQUEST['r'];

				if (verify_leaky_paywall_login_hash($login_hash)) {

					leaky_paywall_attempt_login($login_hash);
					if (!empty($settings['page_for_profile'])) {
						wp_safe_redirect(get_page_link($settings['page_for_profile']));
					} else if (!empty($settings['page_for_subscription'])) {
						wp_safe_redirect(get_page_link($settings['page_for_subscription']));
					}
				} else {

					$output  = '<h3>' . __('Invalid or Expired Login Link', 'leaky-paywall') . '</h3>';
					$output .= '<p>' . sprintf(__('Sorry, this login link is invalid or has expired. <a href="%s">Try again?</a>', 'leaky-paywall'), get_page_link($settings['page_for_login'])) . '</p>';
					$output .= '<a href="' . get_home_url() . '">' . sprintf(__('back to %s', 'leaky-paywall'), $settings['site_name']) . '</a>';

					wp_die(apply_filters('leaky_paywall_invalid_login_link', $output));
				}
			}
		}

		public function restrict_pdf_attachment_url($attachment_url, $attachment_id)
		{
			return esc_url(add_query_arg('issuem-pdf-download', $attachment_id));
		}

		/**
		 * Initialize pigeonpack Admin Menu
		 *
		 * @since 1.0.0
		 * @uses add_menu_page() Creates Pigeon Pack menu
		 * @uses add_submenu_page() Creates Settings submenu to Pigeon Pack menu
		 * @uses add_submenu_page() Creates Help submenu to Pigeon Pack menu
		 * @uses do_action() To call 'pigeonpack_admin_menu' for future addons
		 */
		function admin_menu()
		{

			add_menu_page(__('Leaky Paywall', 'leaky-paywall'), __('Leaky Paywall', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'issuem-leaky-paywall', array($this, 'settings_page'), LEAKY_PAYWALL_URL . '/images/anvil-16x16.png');

			add_submenu_page('issuem-leaky-paywall', __('Settings', 'leaky-paywall'), __('Settings', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'issuem-leaky-paywall', array($this, 'settings_page'));

			add_submenu_page('issuem-leaky-paywall', __('Subscribers', 'leaky-paywall'), __('Subscribers', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'leaky-paywall-subscribers', array($this, 'subscribers_page'));

			add_submenu_page('issuem-leaky-paywall', __('Transactions', 'leaky-paywall'), __('Transactions', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'edit.php?post_type=lp_transaction');

			add_submenu_page(false, __('Update', 'leaky-paywall'), __('Update', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'leaky-paywall-update', array($this, 'update_page'));

			add_submenu_page('issuem-leaky-paywall', __('Add-Ons', 'leaky-paywall'), __('Add-Ons', 'leaky-paywall'), apply_filters('manage_leaky_paywall_settings', 'manage_options'), 'leaky-paywall-addons', array($this, 'addons_page'));
		}




		/**
		 * Prints backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_print_styles()
		{

			global $hook_suffix;

			if (
				'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix
				|| 'toplevel_page_issuem-leaky-paywall' === $hook_suffix
				|| 'index.php' === $hook_suffix
				|| 'leaky-paywall_page_leaky-paywall-addons' === $hook_suffix
			)
				wp_enqueue_style('leaky_paywall_admin_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-admin.css', '', LEAKY_PAYWALL_VERSION);

			if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix)
				wp_enqueue_style('leaky_paywall_post_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-post.css', '', LEAKY_PAYWALL_VERSION);
		}

		/**
		 * Enqueues backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_enqueue_scripts($hook_suffix)
		{

			if ('toplevel_page_issuem-leaky-paywall' === $hook_suffix) {
				wp_enqueue_script('leaky_paywall_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-settings.js', array('jquery'), LEAKY_PAYWALL_VERSION);
			}

			if ('leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix) {

				// Removing i18n of UI datepicker for subscriber page only
				remove_action('admin_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000);

				wp_enqueue_script('leaky_paywall_subscribers_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-subscribers.js', array('jquery-ui-datepicker'), LEAKY_PAYWALL_VERSION);
				wp_enqueue_style('jquery-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
			}


			wp_localize_script(
				'leaky_paywall_subscribers_js',
				'leaky_paywall_notice_ajax',
				array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'lpNoticeNonce' => wp_create_nonce('leaky-paywall-notice-nonce')
				)
			);


			if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
				wp_enqueue_script('leaky_paywall_post_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-post.js', array('jquery'), LEAKY_PAYWALL_VERSION);
			}
		}

		/**
		 * Enqueues frontend scripts and styles
		 *
		 * @since 1.0.0
		 */
		function frontend_scripts()
		{

			$settings = $this->get_settings();

			if ($settings['css_style'] == 'default') {
				wp_enqueue_style('issuem-leaky-paywall', LEAKY_PAYWALL_URL . '/css/issuem-leaky-paywall.css', '', LEAKY_PAYWALL_VERSION);
			}

			if ('on' === $settings['enable_js_cookie_restrictions']) {

				if (is_home() || is_front_page() || is_archive()) {
					return;
				}

				wp_enqueue_script('js_cookie_js', LEAKY_PAYWALL_URL . 'js/js-cookie.js', array('jquery'), LEAKY_PAYWALL_VERSION);
				wp_enqueue_script('leaky_paywall_cookie_js', LEAKY_PAYWALL_URL . 'js/leaky-paywall-cookie.js', array('jquery'), LEAKY_PAYWALL_VERSION);

				$post_container = $settings['js_restrictions_post_container'];
				$page_container = $settings['js_restrictions_page_container'];
				$lead_in_elements = $settings['lead_in_elements'];

				wp_localize_script(
					'leaky_paywall_cookie_js',
					'leaky_paywall_cookie_ajax',
					array(
						'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
						'post_container'	=> $post_container,
						'page_container'	=> $page_container,
						'lead_in_elements'	=> $lead_in_elements
					)
				);
			}


			if (!empty($settings['page_for_register']) && is_page($settings['page_for_register'])) {
				wp_enqueue_script('leaky_paywall_validate', LEAKY_PAYWALL_URL . 'js/leaky-paywall-validate.js', array('jquery'), LEAKY_PAYWALL_VERSION);
			}

			wp_enqueue_script('leaky_paywall_script', LEAKY_PAYWALL_URL . 'js/script.js', array('jquery'), LEAKY_PAYWALL_VERSION);

			wp_localize_script(
				'leaky_paywall_validate',
				'leaky_paywall_validate_ajax',
				array(
					'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
				)
			);

			wp_localize_script(
				'leaky_paywall_script',
				'leaky_paywall_script_ajax',
				array(
					'ajaxurl' => admin_url('admin-ajax.php', 'relative'),
					'stripe_pk' => leaky_paywall_get_stripe_public_key()
				)
			);
		}

		/**
		 * Check if zeen101's Leaky Paywall MultiSite options are enabled
		 *
		 * @since CHANGEME
		 */
		function is_site_wide_enabled()
		{
			return (is_multisite()) ? get_site_option('issuem-leaky-paywall-site-wide') : false;
		}

		/**
		 * Get zeen101's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function get_settings()
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
				'page_for_login'				=> 0, /* Site Specific */
				'page_for_subscription'			=> 0, /* Site Specific */
				'page_for_register'				=> 0, /* Site Specific */
				'page_for_after_subscribe'		=> 0,
				'page_for_profile'				=> 0, /* Site Specific */
				'custom_excerpt_length'			=> '',
				'login_method'					=> 'traditional', //default over passwordless
				'post_types'					=> ACTIVE_ISSUEM ? array('article') : array('post'), /* Site Specific */
				'free_articles'					=> 2,
				'cookie_expiration' 			=> 24,
				'cookie_expiration_interval' 	=> 'day',
				'subscribe_login_message'		=> __('<a href="{{SUBSCRIBE_URL}}">Subscribe</a> or <a href="{{LOGIN_URL}}">log in</a> to read the rest of this content.', 'leaky-paywall'),
				'subscribe_upgrade_message'		=> __('You must <a href="{{SUBSCRIBE_URL}}">upgrade your account</a> to read the rest of this content.', 'leaky-paywall'),
				'css_style'						=> 'default',
				'enable_user_delete_account'	=> 'off',
				'remove_username_field'			=> 'off',
				'site_name'						=> get_option('blogname'), /* Site Specific */
				'from_name'						=> get_option('blogname'), /* Site Specific */
				'from_email'					=> get_option('admin_email'), /* Site Specific */
				'new_subscriber_email'			=> 'off',
				'new_email_subject'				=> '',
				'new_email_body'				=> $default_email_body,
				'renewal_reminder_email'		=> 'on',
				'renewal_reminder_email_subject' => '',
				'renewal_reminder_email_body'	=> '',
				'renewal_reminder_days_before'   => '7',
				'new_subscriber_admin_email'	=> 'off',
				'admin_new_subscriber_email_subject'	=> 'New subscription on ' . stripslashes_deep(html_entity_decode(get_bloginfo('name'), ENT_COMPAT, 'UTF-8')),
				'admin_new_subscriber_email_recipients'	=> get_option('admin_email'),
				'payment_gateway'				=> array('stripe_checkout'),
				'test_mode'						=> 'off',
				'live_secret_key'				=> '',
				'live_publishable_key'			=> '',
				'test_secret_key'				=> '',
				'test_publishable_key'			=> '',
				'stripe_webhooks_enabled'		=> 'off',
				'enable_stripe_elements'		=> 'no',
				'enable_apple_pay'				=> 'no',
				'enable_paypal_on_registration' => 'on',
				'paypal_live_email'				=> '',
				'paypal_live_api_username'		=> '',
				'paypal_live_api_password'		=> '',
				'paypal_live_api_secret'		=> '',
				'paypal_image_url'				=> '',
				'paypal_sand_email'				=> '',
				'paypal_sand_api_username'		=> '',
				'paypal_sand_api_password'		=> '',
				'paypal_sand_api_secret'		=> '',
				'leaky_paywall_currency'		=> 'USD',
				'leaky_paywall_currency_position'		=> 'left',
				'leaky_paywall_thousand_separator'	=> ',',
				'leaky_paywall_decimal_separator'	=> '.',
				'leaky_paywall_decimal_number'	=> '2',
				'restrict_pdf_downloads' 		=> 'off',
				'enable_combined_restrictions'  => 'off',
				'combined_restrictions_total_allowed' => '',
				'enable_js_cookie_restrictions' => 'off',
				'js_restrictions_post_container' => 'article .entry-content',
				'js_restrictions_page_container' => 'article .entry-content',
				'lead_in_elements'				=> 2,
				'bypass_paywall_restrictions' => array('administrator'),
				'restrictions' 	=> array(
					'post_types' => array(
						'post_type' 	=> ACTIVE_ISSUEM ? 'article' : 'post',
						'taxonomy'	=> 'all',
						'allowed_value' => 2,
					)
				),
				'levels' => array(
					'0' => array(
						'label' 					=> __('Digital Access', 'leaky-paywall'),
						'price' 					=> '',
						'subscription_length_type' 	=> 'limited',
						'interval_count' 			=> 1,
						'interval' 					=> 'month',
						'recurring' 				=> 'off',
						'plan_id' 					=> array(),
						'post_types' => array(
							array(
								'post_type' 		=> ACTIVE_ISSUEM ? 'article' : 'post',
								'allowed' 			=> 'unlimited',
								'allowed_value' 	=> -1,
							),
						),
						'deleted' 					=> 0,
						'site' 						=> 'all',
					)
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
				$settings = wp_parse_args($site_wide_settings, $settings);
			}

			$settings = apply_filters('leaky_paywall_multisite_premium', $settings);

			return apply_filters('leaky_paywall_get_settings', $settings);
		}

		/**
		 * Update zeen101's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function update_settings($settings)
		{
			update_option('issuem-leaky-paywall', $settings);
			if ($this->is_site_wide_enabled()) {
				update_site_option('issuem-leaky-paywall', $settings);
			}
		}

		/**
		 * Create and Display Leaky Paywall settings page
		 *
		 * @since 1.0.0
		 */
		function settings_page()
		{

			// Get the user options
			$settings = $this->get_settings();
			$settings_saved = false;

			if (isset($_GET['tab'])) {
				$tab = $_GET['tab'];
			} else if ($_GET['page'] == 'issuem-leaky-paywall') {
				$tab = 'general';
			} else {
				$tab = '';
			}

			$settings_tabs = apply_filters('leaky_paywall_settings_tabs', array('general', 'subscriptions', 'payments', 'emails', 'licenses', 'help'));

			$current_tab = apply_filters('leaky_paywall_current_tab', $tab, $settings_tabs);

			if (isset($_REQUEST['update_leaky_paywall_settings'])) {

				if ($current_tab == 'general') {

					if (!empty($_REQUEST['site_wide_enabled'])) {
						update_site_option('issuem-leaky-paywall-site-wide', true);
					} else {
						update_site_option('issuem-leaky-paywall-site-wide', false);
					}

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

					if (!empty($_REQUEST['login_method']))
						$settings['login_method'] = $_REQUEST['login_method'];

					if (!empty($_REQUEST['subscribe_login_message']))
						$settings['subscribe_login_message'] = trim($_REQUEST['subscribe_login_message']);

					if (!empty($_REQUEST['subscribe_upgrade_message']))
						$settings['subscribe_upgrade_message'] = trim($_REQUEST['subscribe_upgrade_message']);

					if (!empty($_REQUEST['css_style']))
						$settings['css_style'] = $_REQUEST['css_style'];

					if (!empty($_REQUEST['enable_user_delete_account'])) {
						$settings['enable_user_delete_account'] = $_REQUEST['enable_user_delete_account'];
					} else {
						$settings['enable_user_delete_account'] = 'off';
					}

					if (!empty($_REQUEST['remove_username_field'])) {
						$settings['remove_username_field'] = $_REQUEST['remove_username_field'];
					} else {
						$settings['remove_username_field'] = 'off';
					}
				}


				if ($current_tab == 'emails') {

					if (!empty($_REQUEST['site_name']))
						$settings['site_name'] = sanitize_text_field($_REQUEST['site_name']);

					if (!empty($_REQUEST['from_name']))
						$settings['from_name'] = sanitize_text_field($_REQUEST['from_name']);

					if (!empty($_REQUEST['from_email']))
						$settings['from_email'] = sanitize_text_field($_REQUEST['from_email']);

					if (!empty($_POST['new_subscriber_email']))
						$settings['new_subscriber_email'] = $_POST['new_subscriber_email'];
					else
						$settings['new_subscriber_email'] = 'off';

					if (!empty($_REQUEST['new_email_subject']))
						$settings['new_email_subject'] = sanitize_text_field($_REQUEST['new_email_subject']);

					if (!empty($_REQUEST['new_email_body']))
						$settings['new_email_body'] = wp_kses_post($_REQUEST['new_email_body']);

					if (!empty($_REQUEST['renewal_reminder_email']))
						$settings['renewal_reminder_email'] = $_REQUEST['renewal_reminder_email'];
					else
						$settings['renewal_reminder_email'] = 'off';

					if (!empty($_POST['renewal_reminder_email_subject'])) {
						$settings['renewal_reminder_email_subject'] = sanitize_text_field($_POST['renewal_reminder_email_subject']);
					}

					if (!empty($_POST['renewal_reminder_email_body'])) {
						$settings['renewal_reminder_email_body'] = wp_kses_post($_POST['renewal_reminder_email_body']);
					}

					if (!empty($_POST['renewal_reminder_days_before'])) {
						$settings['renewal_reminder_days_before'] = sanitize_text_field($_POST['renewal_reminder_days_before']);
					}

					if (!empty($_REQUEST['new_subscriber_admin_email']))
						$settings['new_subscriber_admin_email'] = $_REQUEST['new_subscriber_admin_email'];
					else
						$settings['new_subscriber_admin_email'] = 'off';

					if (isset($_POST['admin_new_subscriber_email_subject'])) {
						$settings['admin_new_subscriber_email_subject'] = sanitize_text_field($_POST['admin_new_subscriber_email_subject']);
					}

					if (isset($_POST['admin_new_subscriber_email_recipients'])) {
						$settings['admin_new_subscriber_email_recipients'] = sanitize_text_field($_POST['admin_new_subscriber_email_recipients']);
					}
				}






				if ($current_tab == 'subscriptions') {

					if (!empty($_REQUEST['post_types']))
						$settings['post_types'] = $_REQUEST['post_types'];

					if (isset($_REQUEST['free_articles']))
						$settings['free_articles'] = trim($_REQUEST['free_articles']);

					if (!empty($_REQUEST['cookie_expiration']))
						$settings['cookie_expiration'] = trim($_REQUEST['cookie_expiration']);

					if (!empty($_REQUEST['cookie_expiration_interval']))
						$settings['cookie_expiration_interval'] = trim($_REQUEST['cookie_expiration_interval']);

					if (!empty($_REQUEST['restrict_pdf_downloads']))
						$settings['restrict_pdf_downloads'] = $_REQUEST['restrict_pdf_downloads'];
					else
						$settings['restrict_pdf_downloads'] = 'off';


					if (!empty($_REQUEST['restrictions'])) {
						$settings['restrictions'] = $_REQUEST['restrictions'];
					} else {
						$settings['restrictions'] = array();
					}

					if (!empty($_POST['enable_combined_restrictions']))
						$settings['enable_combined_restrictions'] = $_POST['enable_combined_restrictions'];
					else
						$settings['enable_combined_restrictions'] = 'off';

					if (isset($_POST['combined_restrictions_total_allowed'])) {
						$settings['combined_restrictions_total_allowed'] = sanitize_text_field($_POST['combined_restrictions_total_allowed']);
					}

					if (!empty($_POST['enable_js_cookie_restrictions']))
						$settings['enable_js_cookie_restrictions'] = $_POST['enable_js_cookie_restrictions'];
					else
						$settings['enable_js_cookie_restrictions'] = 'off';

					if (!empty($_POST['bypass_paywall_restrictions'])) {
						$settings['bypass_paywall_restrictions'] = $_POST['bypass_paywall_restrictions'];
						$settings['bypass_paywall_restrictions'][] = 'administrator';
					} else {
						$settings['bypass_paywall_restrictions'] = array('administrator');
					}

					if (isset($_POST['custom_excerpt_length'])) {

						if (strlen($_POST['custom_excerpt_length']) > 0) {
							$settings['custom_excerpt_length'] = intval($_POST['custom_excerpt_length']);
						} else {
							$settings['custom_excerpt_length'] = '';
						}
					}

					if (isset($_POST['lead_in_elements'])) {

						if (strlen($_POST['lead_in_elements']) > 0) {
							$settings['lead_in_elements'] = intval($_POST['lead_in_elements']);
						} else {
							$settings['lead_in_elements'] = '';
						}
					}

					if (isset($_POST['js_restrictions_post_container'])) {
						$settings['js_restrictions_post_container'] = sanitize_text_field($_POST['js_restrictions_post_container']);
					}

					if (isset($_POST['js_restrictions_page_container'])) {
						$settings['js_restrictions_page_container'] = sanitize_text_field($_POST['js_restrictions_page_container']);
					}

					if (!empty($_REQUEST['levels'])) {
						$settings['levels'] = $_REQUEST['levels'];
					}
				}

				if ($current_tab == 'payments') {

					if (!empty($_REQUEST['test_mode']))
						$settings['test_mode'] = $_REQUEST['test_mode'];
					else
						$settings['test_mode'] = apply_filters('zeen101_demo_test_mode', 'off');

					if (!empty($_REQUEST['payment_gateway']))
						$settings['payment_gateway'] = $_REQUEST['payment_gateway'];
					else
						$settings['payment_gateway'] = array('stripe');

					if (!empty($_REQUEST['live_secret_key']))
						$settings['live_secret_key'] = apply_filters('zeen101_demo_stripe_live_secret_key', trim($_REQUEST['live_secret_key']));

					if (!empty($_REQUEST['live_publishable_key']))
						$settings['live_publishable_key'] = apply_filters('zeen101_demo_stripe_live_publishable_key', trim($_REQUEST['live_publishable_key']));

					if (!empty($_REQUEST['test_secret_key']))
						$settings['test_secret_key'] = apply_filters('zeen101_demo_stripe_test_secret_key', trim($_REQUEST['test_secret_key']));

					if (!empty($_REQUEST['test_publishable_key']))
						$settings['test_publishable_key'] = apply_filters('zeen101_demo_stripe_test_publishable_key', trim($_REQUEST['test_publishable_key']));

					if (isset($_POST['enable_stripe_elements'])) {
						$settings['enable_stripe_elements'] = sanitize_text_field($_POST['enable_stripe_elements']);
					}

					if (!empty($_POST['stripe_webhooks_enabled'])) {
						$settings['stripe_webhooks_enabled'] = 'on';
					} else {
						$settings['stripe_webhooks_enabled'] = 'off';
					}

					if (isset($_POST['enable_apple_pay'])) {
						$settings['enable_apple_pay'] = sanitize_text_field($_POST['enable_apple_pay']);
					}


					if (!empty($_REQUEST['enable_paypal_on_registration']))
						$settings['enable_paypal_on_registration'] = $_REQUEST['enable_paypal_on_registration'];
					else
						$settings['enable_paypal_on_registration'] = apply_filters('zeen101_demo_enable_paypal_on_registration', 'off');

					if (!empty($_REQUEST['paypal_live_email']))
						$settings['paypal_live_email'] = apply_filters('zeen101_demo_paypal_live_email', trim($_REQUEST['paypal_live_email']));

					if (!empty($_REQUEST['paypal_live_api_username']))
						$settings['paypal_live_api_username'] = apply_filters('zeen101_demo_paypal_live_api_username', trim($_REQUEST['paypal_live_api_username']));

					if (!empty($_REQUEST['paypal_live_api_password']))
						$settings['paypal_live_api_password'] = apply_filters('zeen101_demo_paypal_live_api_password', trim($_REQUEST['paypal_live_api_password']));

					if (!empty($_REQUEST['paypal_live_api_secret']))
						$settings['paypal_live_api_secret'] = apply_filters('zeen101_demo_paypal_live_api_secret', trim($_REQUEST['paypal_live_api_secret']));

					if (!empty($_REQUEST['paypal_image_url']))
						$settings['paypal_image_url'] = trim($_REQUEST['paypal_image_url']);

					if (!empty($_REQUEST['paypal_sand_email']))
						$settings['paypal_sand_email'] = apply_filters('zeen101_demo_paypal_sand_email', trim($_REQUEST['paypal_sand_email']));

					if (!empty($_REQUEST['paypal_sand_api_username']))
						$settings['paypal_sand_api_username'] = apply_filters('zeen101_demo_paypal_sand_api_username', trim($_REQUEST['paypal_sand_api_username']));

					if (!empty($_REQUEST['paypal_sand_api_password']))
						$settings['paypal_sand_api_password'] = apply_filters('zeen101_demo_paypal_sand_api_password', trim($_REQUEST['paypal_sand_api_password']));

					if (!empty($_REQUEST['paypal_sand_api_secret']))
						$settings['paypal_sand_api_secret'] = apply_filters('zeen101_demo_paypal_sand_api_secret', trim($_REQUEST['paypal_sand_api_secret']));

					if (!empty($_REQUEST['leaky_paywall_currency']))
						$settings['leaky_paywall_currency'] = trim($_REQUEST['leaky_paywall_currency']);

					if (isset($_POST['leaky_paywall_currency_position'])) {
						$settings['leaky_paywall_currency_position'] = trim($_POST['leaky_paywall_currency_position']);
					}

					if (isset($_POST['leaky_paywall_thousand_separator'])) {
						$settings['leaky_paywall_thousand_separator'] = trim($_POST['leaky_paywall_thousand_separator']);
					}

					if (isset($_POST['leaky_paywall_decimal_separator'])) {
						$settings['leaky_paywall_decimal_separator'] = trim($_POST['leaky_paywall_decimal_separator']);
					}

					if (isset($_POST['leaky_paywall_decimal_number'])) {
						$settings['leaky_paywall_decimal_number'] = trim($_POST['leaky_paywall_decimal_number']);
					}
				}



				$settings = apply_filters('leaky_paywall_update_settings_settings', $settings, $current_tab);

				$this->update_settings($settings);
				$settings_saved = true;

				do_action('leaky_paywall_update_settings', $settings);
			}

			if ($settings_saved) {

				// update settings notification 
?>
				<div class="updated">
					<p><strong><?php _e("Settings Updated", 'leaky-paywall'); ?></strong></p>
				</div>
			<?php

			}

			// Display HTML form for the options below
			?>
			<div class=wrap>
				<div style="width:70%;" class="postbox-container">


					<form id="leaky-paywall" method="post" action="">

						<h1 style='margin-bottom: 2px;'><?php _e("Leaky Paywall", 'leaky-paywall'); ?></h1>

						<?php
						if (in_array($current_tab, $settings_tabs)) {
						?>
							<h2 class="nav-tab-wrapper" style="margin-bottom: 10px;">

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall'); ?>" class="nav-tab<?php if ($current_tab == 'general') { ?> nav-tab-active<?php } ?>"><?php _e('General', 'leaky-paywall'); ?></a>

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall&tab=subscriptions'); ?>" class="nav-tab<?php if ($current_tab == 'subscriptions') { ?> nav-tab-active<?php } ?>"><?php _e('Subscriptions', 'leaky-paywall'); ?></a>

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall&tab=payments'); ?>" class="nav-tab<?php if ($current_tab == 'payments') { ?> nav-tab-active<?php } ?>"><?php _e('Payments', 'leaky-paywall'); ?></a>

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall&tab=emails'); ?>" class="nav-tab<?php if ($current_tab == 'emails') { ?> nav-tab-active<?php } ?>"><?php _e('Emails', 'leaky-paywall'); ?></a>

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall&tab=licenses'); ?>" class="nav-tab<?php if ($current_tab == 'licenses') { ?> nav-tab-active<?php } ?>"><?php _e('Licenses', 'leaky-paywall'); ?></a>

								<?php do_action('leaky_paywall_settings_tabs_links', $current_tab); ?>

								<a href="<?php echo admin_url('admin.php?page=issuem-leaky-paywall&tab=help'); ?>" class="nav-tab<?php if ($current_tab == 'help') { ?> nav-tab-active<?php } ?>"><?php _e('Help', 'leaky-paywall'); ?></a>

							</h2>
						<?php } // endif 
						?>

						<?php do_action('leaky_paywall_before_settings', $current_tab); ?>

						<?php if ($current_tab == 'general') : ?>

							<?php do_action('leaky_paywall_before_general_settings'); ?>

							<?php if (is_multisite_premium() && is_super_admin()) { ?>

								<h2><?php _e('Site Wide Options', 'leaky-paywall'); ?></h2>

								<table id="issuem_multisite_settings" class="leaky-paywall-table">
									<tr>
										<th rowspan="1"> <?php _e('Enable Settings Site Wide?', 'leaky-paywall'); ?></th>
										<td>
										<td><input type="checkbox" id="site_wide_enabled" name="site_wide_enabled" <?php checked($this->is_site_wide_enabled()); ?> /></td>
										</td>
									</tr>
								</table>

								<p class="submit">
									<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e('Save Settings', 'leaky-paywall') ?>" />
								</p>


							<?php } ?>

							<h2><?php _e('General Settings', 'leaky-paywall'); ?></h2>

							<?php if (!isset($settings['page_for_subscription']) || !$settings['page_for_subscription']) {
							?>
								<p>Need help getting started? <a target="_blank" href="https://zeen101.helpscoutdocs.com/article/39-setting-up-leaky-paywall">See our guide</a> or <a target="_blank" href="https://zeen101.com/pubcare/?utm_medium=plugin&utm_source=getting_started&utm_campaign=settings">check out PubCare.</a></p>
							<?php
							} ?>

							<table id="leaky_paywall_administrator_options" class="form-table">

								<tr>
									<th><?php _e('Page for Log In', 'leaky-paywall'); ?></th>
									<td>
										<?php echo wp_dropdown_pages(array('name' => 'page_for_login', 'echo' => 0, 'show_option_none' => __('&mdash; Select &mdash;'), 'option_none_value' => '0', 'selected' => $settings['page_for_login'])); ?>
										<p class="description"><?php printf(__('Add this shortcode to your Log In page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_login]'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Page for Subscribe Cards', 'leaky-paywall'); ?></th>
									<td>
										<?php echo wp_dropdown_pages(array('name' => 'page_for_subscription', 'echo' => 0, 'show_option_none' => __('&mdash; Select &mdash;'), 'option_none_value' => '0', 'selected' => $settings['page_for_subscription'])); ?>
										<p class="description"><?php printf(__('Add this shortcode to your Subscription page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_subscription]'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Page for Register Form', 'leaky-paywall'); ?></th>
									<td>
										<?php echo wp_dropdown_pages(array('name' => 'page_for_register', 'echo' => 0, 'show_option_none' => __('&mdash; Select &mdash;'), 'option_none_value' => '0', 'selected' => $settings['page_for_register'])); ?>
										<p class="description"><?php printf(__('Add this shortcode to your register page: %s. This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_register_form]'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Page for Profile', 'leaky-paywall'); ?></th>
									<td>
										<?php echo wp_dropdown_pages(array('name' => 'page_for_profile', 'echo' => 0, 'show_option_none' => __('&mdash; Select &mdash;'), 'option_none_value' => '0', 'selected' => $settings['page_for_profile'])); ?>
										<p class="description"><?php printf(__('Add this shortcode to your Profile page: %s. This page displays the account information for subscribers.  This page cannot be restricted.', 'leaky-paywall'), '[leaky_paywall_profile]'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Confirmation Page', 'leaky-paywall'); ?></th>
									<td>
										<?php echo wp_dropdown_pages(array('name' => 'page_for_after_subscribe', 'echo' => 0, 'show_option_none' => __('&mdash; Select &mdash;'), 'option_none_value' => '0', 'selected' => $settings['page_for_after_subscribe'])); ?>
										<p class="description"><?php _e('Page a subscriber is redirected to after they subscribe.  This page cannot be restricted.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Subscribe or Login Message', 'leaky-paywall'); ?></th>
									<td>
										<textarea id="subscribe_login_message" class="large-text" name="subscribe_login_message" cols="50" rows="3"><?php echo stripslashes($settings['subscribe_login_message']); ?></textarea>
										<p class="description">
											<?php _e("Available replacement variables: {{SUBSCRIBE_URL}}  {{LOGIN_URL}}", 'leaky-paywall'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Upgrade Message', 'leaky-paywall'); ?></th>
									<td>
										<textarea id="subscribe_upgrade_message" class="large-text" name="subscribe_upgrade_message" cols="50" rows="3"><?php echo stripslashes($settings['subscribe_upgrade_message']); ?></textarea>
										<p class="description">
											<?php _e("Available replacement variables: {{SUBSCRIBE_URL}}", 'leaky-paywall'); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th><?php _e('CSS Style', 'leaky-paywall'); ?></th>
									<td>
										<select id='css_style' name='css_style'>
											<option value='default' <?php selected('default', $settings['css_style']); ?>><?php _e('Default', 'leaky-paywall'); ?></option>
											<option value='none' <?php selected('none', $settings['css_style']); ?>><?php _e('None', 'leaky-paywall'); ?></option>
										</select>
									</td>
								</tr>

								<tr class="general-options">
									<th><?php _e('User Account Creation', 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="remove_username_field" name="remove_username_field" <?php checked('on', $settings['remove_username_field']); ?> /> Remove the username field during registration and use their email address to generate an account username</td>
								</tr>

								<tr class="general-options">
									<th><?php _e('User Account Deletion', 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="enable_user_delete_account" name="enable_user_delete_account" <?php checked('on', $settings['enable_user_delete_account']); ?> /> Allow users to delete their account from the My Profile page</td>
								</tr>



								<?php wp_nonce_field('issuem_leaky_general_options', 'issuem_leaky_general_options_nonce'); ?>

							</table>


							<?php do_action('leaky_paywall_after_general_settings'); ?>
							<?php do_action('leaky_paywall_settings_form', $settings); // here for backwards compatibility 
							?>

							<p class="submit">
								<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e('Save Settings', 'leaky-paywall') ?>" />
							</p>

						<?php endif; ?>

						<?php if ($current_tab == 'emails') : // email tab settings 
						?>

							<?php do_action('leaky_paywall_before_email_settings'); ?>

							<h2><?php _e('Email Settings', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_administrator_options" class="form-table">

								<tr>
									<th><?php _e('Site Name', 'leaky-paywall'); ?></th>
									<td><input type="text" id="site_name" class="regular-text" name="site_name" value="<?php echo htmlspecialchars(stripcslashes($settings['site_name'])); ?>" /></td>
								</tr>

								<tr>
									<th><?php _e('From Name', 'leaky-paywall'); ?></th>
									<td><input type="text" id="from_name" class="regular-text" name="from_name" value="<?php echo htmlspecialchars(stripcslashes($settings['from_name'])); ?>" /></td>
								</tr>

								<tr>
									<th><?php _e('From Email', 'leaky-paywall'); ?></th>
									<td><input type="text" id="from_email" class="regular-text" name="from_email" value="<?php echo htmlspecialchars(stripcslashes($settings['from_email'])); ?>" /></td>
								</tr>

							</table>

							<h2><?php _e('Admin New Subscriber Email', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_administrator_options" class="form-table">

								<tr>
									<th><?php _e("Disable New User Notifications", 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="new_subscriber_admin_email" name="new_subscriber_admin_email" <?php checked('on', $settings['new_subscriber_admin_email']); ?> /> <?php _e('Disable the email sent to an admin when a new subscriber is added to Leaky Paywall', 'leaky-paywall'); ?></td>
								</tr>

								<tr>
									<th><?php _e('Subject', 'leaky-paywall'); ?></th>
									<td><input type="text" id="admin_new_subscriber_email_subject" class="regular-text" name="admin_new_subscriber_email_subject" value="<?php echo htmlspecialchars(stripcslashes($settings['admin_new_subscriber_email_subject'])); ?>" />
										<p class="description"><?php _e('The subject line for this email.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Recipient(s)', 'leaky-paywall'); ?></th>
									<td><input type="text" id="admin_new_subscriber_email_recipients" class="regular-text" name="admin_new_subscriber_email_recipients" value="<?php echo htmlspecialchars(stripcslashes($settings['admin_new_subscriber_email_recipients'])); ?>" />
										<p class="description"><?php _e('Enter recipients (comma separated) for this email.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

							</table>

							<h2><?php _e('New Subscriber Email', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_new_subscriber_email_options" class="form-table">

								<tr>
									<th><?php _e("Disable New Subscriber Email", 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="new_subscriber_email" name="new_subscriber_email" <?php checked('on', $settings['new_subscriber_email']); ?> /> Disable the new subscriber email sent to a subscriber</td>
								</tr>

								<tr>
									<th><?php _e('Subject', 'leaky-paywall'); ?></th>
									<td><input type="text" id="new_email_subject" class="regular-text" name="new_email_subject" value="<?php echo htmlspecialchars(stripcslashes($settings['new_email_subject'])); ?>" />
										<p class="description"><?php _e('The subject line for the email sent to new subscribers.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Body', 'leaky-paywall'); ?></th>
									<td><textarea id="new_email_body" class="large-text" name="new_email_body" rows="10" cols="20"><?php echo htmlspecialchars(stripcslashes($settings['new_email_body'])); ?></textarea>
										<p class="description"><?php _e('The email message that is sent to new subscribers.', 'leaky-paywall'); ?></p>
										<p class="description"><?php _e('Available template tags:', 'leaky-paywall'); ?> <br>
											%blogname%, %sitename%, %username%, %useremail%, %password%, %firstname%, %lastname%, %displayname%</p>
									</td>
								</tr>

							</table>

							<h2><?php _e('Renewal Reminder Email (for non-recurring subscribers)', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_renewal_reminder_email_options" class="form-table">

								<tr>
									<th><?php _e("Disable Renewal Reminder Email", 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="renewal_reminder_email" name="renewal_reminder_email" <?php checked('on', $settings['renewal_reminder_email']); ?> /> Disable the renewal reminder email sent to a non-recurring subscriber</td>
								</tr>

								<tr>
									<th><?php _e('Subject', 'leaky-paywall'); ?></th>
									<td><input type="text" id="renewal_reminder_email_subject" class="regular-text" name="renewal_reminder_email_subject" value="<?php echo htmlspecialchars(stripcslashes($settings['renewal_reminder_email_subject'])); ?>" />

									</td>
								</tr>

								<tr>
									<th><?php _e('Body', 'leaky-paywall'); ?></th>
									<td><textarea id="renewal_reminder_email_body" class="large-text" name="renewal_reminder_email_body" rows="10" cols="20"><?php echo htmlspecialchars(stripcslashes($settings['renewal_reminder_email_body'])); ?></textarea>
										<p class="description"><?php _e('The email message that is sent to remind non-recurring subscribers to renew their subscription.', 'leaky-paywall'); ?></p>
										<p class="description"><?php _e('Available template tags:', 'leaky-paywall'); ?> <br>
											%blogname%, %sitename%, %username%, %password%, %firstname%, %lastname%, %displayname%</p>
									</td>
								</tr>

								<tr>
									<th><?php _e('When to Send Reminder', 'leaky-paywall'); ?></th>
									<td>
										<input type="number" value="<?php echo $settings['renewal_reminder_days_before']; ?>" name="renewal_reminder_days_before" />
										<p class="description"><?php _e('Days in advance of a non-recurring subscriber\'s expiration date to remind them to renew.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

							</table>


							<?php wp_nonce_field('issuem_leaky_email_options', 'issuem_leaky_email_options_nonce'); ?>

							<?php do_action('leaky_paywall_after_email_settings'); ?>

							<p class="submit">
								<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e('Save Settings', 'leaky-paywall') ?>" />
							</p>

						<?php endif; ?>

						<?php if ($current_tab == 'payments') : // payments settings tab 
						?>

							<?php do_action('leaky_paywall_before_payments_settings'); ?>

							<h2><?php _e('Payment Gateway Settings', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_test_option" class="form-table">

								<tr class="gateway-options">
									<th><?php _e("Test Mode", 'leaky-paywall'); ?></th>
									<td>
										<p><input type="checkbox" id="test_mode" name="test_mode" <?php checked('on', $settings['test_mode']); ?> />
											<?php _e('Use the test gateway environment for transactions.', 'leaky-paywall'); ?></p>
									</td>

								</tr>

							</table>

							<?php ob_start(); //Level 1 
							?>

							<table id="leaky_paywall_gateway_options" class="form-table">

								<tr class="gateway-options">
									<th><?php _e('Enabled Gateways', 'leaky-paywall'); ?></th>
									<td>
										<?php
										$gateways = leaky_paywall_get_payment_gateways();

										foreach ($gateways as $key => $value) {

											// no longer supporting the checkout modal
											if ($key == 'stripe' && in_array('stripe_checkout', $settings['payment_gateway']) && !in_array('stripe', $settings['payment_gateway'])) {
												$settings['payment_gateway'][] = 'stripe';
											}


										?>
											<p>
												<input id="enable-<?php echo $key; ?>" type="checkbox" name="payment_gateway[]" value="<?php echo $key; ?>" <?php checked(in_array($key, $settings['payment_gateway'])); ?> /> <label for="enable-<?php echo $key; ?>"><?php _e($value['admin_label'], 'leaky-paywall'); ?></label>
											</p>
										<?php
										}
										?>
										<p class="description">Need a different gateway? Take payments with our <a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-woocommerce/">WooCommerce integration</a> using any Woo supported gateway. <a target="_blank" href="https://zeen101.com/contact/">Get in touch</a> about our integrations with HubSpot, ZOHO, Taxrates, Pipedrive, fulfillment services and other providers.</p>
									</td>
								</tr>

							</table>

							<?php
							if (in_array('stripe', $settings['payment_gateway']) || in_array('stripe_checkout', $settings['payment_gateway'])) {
								ob_start(); //Level 2
							?>

								<table id="leaky_paywall_stripe_options" class="form-table">

									<tr>
										<th colspan="2">
											<h3><?php _e('Stripe Settings', 'leaky-paywall'); ?></h3>

											<?php if (!isset($settings['live_publishable_key']) || !$settings['live_publishable_key']) {
											?>
												<p>Looking for your Stripe keys? <a target="_blank" href="https://dashboard.stripe.com/account/apikeys">Click here.</a></p>
											<?php
											} ?>

										</th>
									</tr>

									<tr>
										<th><?php _e('Live Publishable Key', 'leaky-paywall'); ?></th>
										<td><input type="text" id="live_publishable_key" class="regular-text" name="live_publishable_key" value="<?php echo htmlspecialchars(stripcslashes($settings['live_publishable_key'])); ?>" /></td>
									</tr>

									<tr>
										<th><?php _e('Live Secret Key', 'leaky-paywall'); ?></th>
										<td><input type="text" id="live_secret_key" class="regular-text" name="live_secret_key" value="<?php echo htmlspecialchars(stripcslashes($settings['live_secret_key'])); ?>" /></td>
									</tr>

									<tr>
										<th><?php _e('Test Publishable Key', 'leaky-paywall'); ?></th>
										<td><input type="text" id="test_publishable_key" class="regular-text" name="test_publishable_key" value="<?php echo htmlspecialchars(stripcslashes($settings['test_publishable_key'])); ?>" /></td>
									</tr>

									<tr>
										<th><?php _e('Test Secret Key', 'leaky-paywall'); ?></th>
										<td><input type="text" id="test_secret_key" class="regular-text" name="test_secret_key" value="<?php echo htmlspecialchars(stripcslashes($settings['test_secret_key'])); ?>" /></td>
									</tr>

									<tr>
										<th><?php _e('Stripe Webhook URL', 'leaky-paywall'); ?></th>
										<td>
											<p class="description"><?php echo esc_url(add_query_arg('listener', 'stripe', get_site_url() . '/')); ?></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('Stripe Webhooks Enabled', 'leaky-paywall'); ?></th>
										<td>
											<p><input type="checkbox" id="stripe_webhooks_enabled" name="stripe_webhooks_enabled" <?php checked('on', $settings['stripe_webhooks_enabled']); ?> />
												<?php _e('I have enabled the Leaky Paywall webhook URL in my Stripe account.', 'leaky-paywall'); ?><br><a target="_blank" href="https://zeen101.helpscoutdocs.com/article/120-leaky-paywall-recurring-payments"><?php _e('View Instructions', 'leaky-paywall'); ?></a></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('Enable Apple Pay', 'leaky-paywall'); ?></th>
										<td>
											<select id="enable_apple_pay" name="enable_apple_pay">
												<option <?php selected('yes', $settings['enable_apple_pay']); ?> value="yes">Yes</option>
												<option <?php selected('no', $settings['enable_apple_pay']); ?> value="no">No</option>
											</select>
											<p class="description">You must <a target="_blank" href="https://stripe.com/docs/stripe-js/elements/payment-request-button">verify your domain with Apple Pay</a> to complete the Apple Pay setup.</p>
									</tr>

								</table>
								<?php echo apply_filters('leaky_paywall_settings_page_stripe_payment_gateway_options', ob_get_clean()); ?>
							<?php } ?>

							<?php
							if (in_array('paypal_standard', $settings['payment_gateway']) || in_array('paypal-standard', $settings['payment_gateway'])) {
							?>

								<table id="leaky_paywall_paypal_options" class="gateway-options form-table">

									<tr>
										<th colspan="2">
											<h3><?php _e('PayPal Standard Settings', 'leaky-paywall'); ?></h3>
										</th>
									</tr>

									<tr>
										<th><?php _e('Merchant ID', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_live_email" class="regular-text" name="paypal_live_email" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_live_email'])); ?>" />
											<p class="description"><?php _e('Need help setting up PayPal?', 'leaky-paywall'); ?> <a target="_blank" href="https://zeen101.helpscoutdocs.com/article/213-how-to-set-up-paypal-as-a-payment-gateway"><?php _e('See our guide.', 'leaky-paywall'); ?></a></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('API Username', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_live_api_username" class="regular-text" name="paypal_live_api_username" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_live_api_username'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('API Password', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_live_api_password" class="regular-text" name="paypal_live_api_password" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_live_api_password'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('API Signature', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_live_api_secret" class="regular-text" name="paypal_live_api_secret" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_live_api_secret'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('Image URL', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_image_url" class="regular-text" name="paypal_image_url" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_image_url'])); ?>" />
											<p class="description"><?php _e('Enter the URL to a 150x50px image displayed as your logo in the upper left corner of the Paypal checkout pages.', 'leaky-paywall'); ?></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('Live IPN', 'leaky-paywall'); ?></th>
										<td>
											<p class="description"><?php echo esc_url(add_query_arg('listener', 'IPN', get_site_url() . '/')); ?></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('Sandbox Merchant ID', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_sand_email" class="regular-text" name="paypal_sand_email" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_sand_email'])); ?>" />
											<p class="description"><?php _e('Use PayPal Sandbox Email Address in lieu of Merchant ID', 'leaky-paywall'); ?></p>
										</td>
									</tr>

									<tr>
										<th><?php _e('Sandbox API Username', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_sand_api_username" class="regular-text" name="paypal_sand_api_username" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_sand_api_username'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('Sandbox API Password', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_sand_api_password" class="regular-text" name="paypal_sand_api_password" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_sand_api_password'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('Sandbox API Signature', 'leaky-paywall'); ?></th>
										<td>
											<input type="text" id="paypal_sand_api_secret" class="regular-text" name="paypal_sand_api_secret" value="<?php echo htmlspecialchars(stripcslashes($settings['paypal_sand_api_secret'])); ?>" />
										</td>
									</tr>

									<tr>
										<th><?php _e('Sandbox IPN', 'leaky-paywall'); ?></th>
										<td>
											<p class="description"><?php echo esc_url(add_query_arg('listener', 'IPN', get_site_url() . '/')); ?></p>
										</td>
									</tr>

								</table>

							<?php } ?>

							<?php wp_nonce_field('issuem_leaky_general_options', 'issuem_leaky_general_options_nonce'); ?>

							<?php $leaky_paywall_gateway_options = ob_get_clean(); ?>
							<?php echo apply_filters('leaky_paywall_settings_page_gateway_options', $leaky_paywall_gateway_options); ?>

							<?php do_action('leaky_paywall_after_enabled_gateways', $settings); ?>


							<h2><?php _e('Currency Options', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_currency_options" class="form-table">

								<tr>
									<th><?php _e('Currency', 'leaky-paywall'); ?></th>
									<td>
										<select id="leaky_paywall_currency" name="leaky_paywall_currency">
											<?php
											$currencies = leaky_paywall_supported_currencies();
											foreach ($currencies as $key => $currency) {
												echo '<option value="' . $key . '" ' . selected($key, $settings['leaky_paywall_currency'], true) . '>' . $currency['label'] . ' - ' . $currency['symbol'] . '</option>';
											}
											?>
										</select>
										<p class="description"><?php _e('This controls which currency payment gateways will take payments in.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<tr>
									<th><?php _e('Currency Position', 'leaky-paywall'); ?></th>
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
									<th><?php _e('Thousand Separator', 'leaky-paywall'); ?></th>
									<td>
										<input type="text" class="small-text" id="leaky_paywall_thousand_separator" name="leaky_paywall_thousand_separator" value="<?php echo $settings['leaky_paywall_thousand_separator']; ?>">
									</td>
								</tr>

								<tr>
									<th><?php _e('Decimal Separator', 'leaky-paywall'); ?></th>
									<td>
										<input type="text" class="small-text" id="leaky_paywall_decimal_separator" name="leaky_paywall_decimal_separator" value="<?php echo $settings['leaky_paywall_decimal_separator']; ?>">
									</td>
								</tr>

								<tr>
									<th><?php _e('Number of Decimals', 'leaky-paywall'); ?></th>
									<td>
										<input type="number" class="small-text" id="leaky_paywall_decimal_number" name="leaky_paywall_decimal_number" value="<?php echo $settings['leaky_paywall_decimal_number']; ?>" min="0" step="1">
									</td>
								</tr>

								<?php do_action('leaky_paywall_after_currency_settings', $settings); ?>

							</table>

							<p class="submit">
								<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e('Save Settings', 'leaky-paywall') ?>" />
							</p>

							<?php do_action('leaky_paywall_after_payments_settings', $settings); ?>

						<?php endif; // payment tabs 
						?>


						<?php if ($current_tab == 'subscriptions') : // subscriptions settings tab 
						?>

							<?php do_action('leaky_paywall_before_subscriptions_settings'); ?>

							<h2><?php _e('Content Restriction', 'leaky-paywall'); ?></h2>

							<table id="leaky_paywall_default_restriction_options" class="form-table">

								<tr class="restriction-options">
									<th><?php _e('Limited Article Cookie Expiration', 'leaky-paywall'); ?></th>
									<td>
										<input type="text" id="cookie_expiration" class="small-text" name="cookie_expiration" value="<?php echo stripcslashes($settings['cookie_expiration']); ?>" />
										<select id="cookie_expiration_interval" name="cookie_expiration_interval">
											<option value="hour" <?php selected('hour', $settings['cookie_expiration_interval']); ?>><?php _e('Hour(s)', 'leaky-paywall'); ?></option>
											<option value="day" <?php selected('day', $settings['cookie_expiration_interval']); ?>><?php _e('Day(s)', 'leaky-paywall'); ?></option>
											<option value="week" <?php selected('week', $settings['cookie_expiration_interval']); ?>><?php _e('Week(s)', 'leaky-paywall'); ?></option>
											<option value="month" <?php selected('month', $settings['cookie_expiration_interval']); ?>><?php _e('Month(s)', 'leaky-paywall'); ?></option>
											<option value="year" <?php selected('year', $settings['cookie_expiration_interval']); ?>><?php _e('Year(s)', 'leaky-paywall'); ?></option>
										</select>
										<p class="description"><?php _e('Choose length of time when a visitor can once again read your articles/posts (up to the # of articles allowed).', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<?php
								if (ACTIVE_ISSUEM) {
								?>
									<tr class="restriction-options ">
										<th><?php _e('IssueM PDF Downloads', 'leaky-paywall'); ?></th>
										<td><input type="checkbox" id="restrict_pdf_downloads" name="restrict_pdf_downloads" <?php checked('on', $settings['restrict_pdf_downloads']); ?> /> <?php _e('Restrict PDF issue downloads to active Leaky Paywall subscribers.', 'leaky-paywall'); ?></td>
									</tr>
								<?php
								}
								?>



								<tr class="restriction-options">
									<th>
										<label for="restriction-post-type-' . $row_key . '"><?php _e('Restrictions', 'leaky-paywall'); ?></label>
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

													if (!is_numeric($key))
														continue;

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
											var leaky_paywall_restriction_row_key = <?php echo $last_key; ?>;
										</script>

										<p>
											<input class="button-secondary" id="add-restriction-row" class="add-new-issuem-leaky-paywall-restriction-row" type="submit" name="add_leaky_paywall_restriction_row" value="<?php _e('+ Add Restricted Content', 'leaky-paywall'); ?>" />
										</p>
										<p class="description"><?php _e('By default all content is allowed.', 'leaky-paywall'); ?> <?php _e('Restrictions processed from top to bottom.', 'leaky-paywall'); ?></p>
									</td>
								</tr>

								<tr class="restriction-options">
									<th><?php _e('Combined Restrictions', 'leaky-paywall'); ?></th>
									<td><input type="checkbox" id="enable_combined_restrictions" name="enable_combined_restrictions" <?php checked('on', $settings['enable_combined_restrictions']); ?> /> <?php _e('Use a single value for total number allowed regardless of content type or taxonomy. This uses the Post Type and Taxonomy settings from the Restrictions settings above.', 'leaky-paywall'); ?></td>
								</tr>

								<tr class="restriction-options combined-restrictions-total-allowed <?php echo $settings['enable_combined_restrictions'] != 'on' ? 'hide-setting' : ''; ?>">
									<th><?php _e('Combined Restrictions Total Allowed', 'leaky-paywall'); ?></th>
									<td>
										<input type="number" id="combined_restrictions_total_allowed" class="small-text" name="combined_restrictions_total_allowed" value="<?php echo stripcslashes($settings['combined_restrictions_total_allowed']); ?>" />
										<p class="description"><?php _e('If combined restrictions is enabled, the total amount of content items allowed before content is restricted.'); ?></p>
									</td>
								</tr>

								<tr class="restriction-options">
									<th><?php _e('Alternative Restriction Handling', 'leaky-paywall'); ?></th>
									<td>
										<input type="checkbox" id="enable_js_cookie_restrictions" name="enable_js_cookie_restrictions" <?php checked('on', $settings['enable_js_cookie_restrictions']); ?> /> <?php _e('Enable this if you are using a caching plugin or your host uses heavy caching and the paywall notice is not displaying correctly on your site.'); ?>

										<?php if ($this->check_for_caching() && $settings['enable_js_cookie_restrictions'] != 'on') {
										?>
											<div class="notice-info notice">
												<p><strong>We noticed your site might use caching.</strong></p>
												<p> We highly recommend enabling Alternative Restrction Handling to ensure the paywall displays correctly.<br> <a target="_blank" href="https://zeen101.helpscoutdocs.com/article/72-caching-with-leaky-paywall-i-e-wp-engine">Please see our usage guide here.</a></p>
											</div>
										<?php
										} ?>

									</td>
								</tr>

								<tr class="restriction-options-post-container <?php echo $settings['enable_js_cookie_restrictions'] != 'on' ? 'hide-setting' : ''; ?>">
									<th><?php _e('Alternative Restrictions Post Container', 'leaky-paywall'); ?></th>
									<td>
										<input type="text" id="js_restrictions_post_container" class="large-text" name="js_restrictions_post_container" value="<?php echo stripcslashes($settings['js_restrictions_post_container']); ?>" />
										<p class="description"><?php _e('CSS selector of the container that contains the content on a post and custom post type.'); ?></p>
									</td>
								</tr>

								<tr class="restriction-options-page-container <?php echo $settings['enable_js_cookie_restrictions'] != 'on' ? 'hide-setting' : ''; ?>">
									<th><?php _e('Alternative Restrictions Page Container', 'leaky-paywall'); ?></th>
									<td>
										<input type="text" id="js_restrictions_page_container" class="large-text" name="js_restrictions_page_container" value="<?php echo stripcslashes($settings['js_restrictions_page_container']); ?>" />
										<p class="description"><?php _e('CSS selector of the container that contains the content on a page.'); ?></p>
									</td>
								</tr>

								<tr class="restriction-options-lead-in-elements <?php echo $settings['enable_js_cookie_restrictions'] != 'on' ? 'hide-setting' : ''; ?>">
									<th><?php _e('Lead In Elements', 'leaky-paywall'); ?></th>
									<td>
										<input type="number" id="lead_in_elements" class="small-text" name="lead_in_elements" value="<?php echo esc_attr($settings['lead_in_elements']); ?>">
										<p class="description">
											<?php _e("Number of HTML elements (paragraphs, images, etc.) to show before displaying the subscribe nag.", 'leaky-paywall'); ?>
										</p>
									</td>
								</tr>

								<tr class="custom-excerpt-length <?php echo $settings['enable_js_cookie_restrictions'] == 'on' ? 'hide-setting' : ''; ?>">
									<th><?php _e('Custom Excerpt Length', 'leaky-paywall'); ?></th>
									<td>
										<input type="number" id="custom_excerpt_length" class="small-text" name="custom_excerpt_length" value="<?php echo esc_attr($settings['custom_excerpt_length']); ?>">
										<p class="description">
											<?php _e("Amount of content (in characters) to show before displaying the subscribe nag. If nothing is entered then the full excerpt is displayed.", 'leaky-paywall'); ?>
										</p>
									</td>
								</tr>

								<tr class="restriction-options">
									<th><?php _e('Bypass Restrictions', 'leaky-paywall'); ?></th>
									<td>
										<?php
										$roles = get_editable_roles();

										foreach ($roles as $name => $role) {
										?>
											<input type="checkbox" name="bypass_paywall_restrictions[]" <?php echo in_array($name, $settings['bypass_paywall_restrictions']) ? 'checked' : ''; ?> <?php echo $name == 'administrator' ? 'disabled' : ''; ?> value="<?php echo $name; ?>"> <?php echo ucfirst(str_replace('_', ' ', $name)); ?>&nbsp; &nbsp;
										<?php
										}
										?>

										<p class="description">
											<?php _e('Allow the selected user roles to always bypass the paywall. Administrators can always bypass the paywall.'); ?>
										</p>
									</td>
								</tr>

							</table>

							<h2><?php _e('Subscription Levels', 'leaky-paywall'); ?></span></h2>

							<p><a id="collapse-levels" href="#">Collapse All</a> / <a id="expand-levels" href="#">Expand All</a></p>

							<div id="leaky_paywall_subscription_level_options">

								<table id="leaky_paywall_subscription_level_options_table" class="leaky-paywall-table subscription-options form-table">

									<tr>
										<td id="issuem-leaky-paywall-subscription-level-rows" colspan="2">
											<?php


											$last_key = -1;
											if (!empty($settings['levels'])) {

												$deleted = array();

												foreach ($settings['levels'] as $key => $level) {

													if (!is_numeric($key))
														continue;
													echo build_leaky_paywall_subscription_levels_row($level, $key);
													$last_key = $key;

													if ($level['deleted'] == 1) {
														$deleted[] = true;
													}
												}

												// if we have levels but they have all been deleted, add one level 
												if (count($deleted) == count($settings['levels'])) {

													// set the default key to one more than they last key value
													$default_key = count($settings['levels']);

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
									echo '<h4 class="description">Want more levels? Get our <a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-multiple-levels/?utm_medium=plugin&utm_source=subscriptions_tab&utm_campaign=settings">multiple subscription levels</a> add-on.</h4>';
								}

								if (!is_leaky_paywall_recurring()) {
									echo '<h4 class="description">Want recurring payments? Get our <a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-recurring-payments/?utm_medium=plugin&utm_source=subscriptions_tab&utm_campaign=settings">recurring payments</a> add-on.</h4>';
								}

								?>

							</div>



							<?php do_action('leaky_paywall_after_subscriptions_settings'); ?>

							<p class="submit">
								<input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e('Save Settings', 'issuem-leaky-paywall') ?>" />
							</p>


						<?php endif; ?>

						<?php if ($current_tab == 'licenses') : ?>

							<?php do_action('leaky_paywall_before_licenses_settings'); ?>

							<h2><a target="_blank" href="https://zeen101.com/for-developers/leakypaywall/leaky-paywall-add-ons/?utm_source=plugin&utm_medium=license_tab&utm_content=link&utm_campaign=settings">Find out more about our add-ons</a></h2>

							<?php wp_nonce_field('verify', 'leaky_paywall_license_wpnonce'); ?>

							<?php do_action('leaky_paywall_after_licenses_settings'); ?>

						<?php endif; ?>

						<?php if ($current_tab == 'help') : ?>

							<?php do_action('leaky_paywall_before_help_settings'); ?>

							<h2><?php _e('Getting Started', 'leaky-paywall'); ?></h2>

							<p><a target="_blank" href="https://zeen101.helpscoutdocs.com/article/39-setting-up-leaky-paywall">Setting Up Leaky Paywall</a></p>

							<iframe width="560" height="315" src="https://www.youtube.com/embed/blUGogGw4H8" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

							<h2><?php _e('Setting Up Stripe in Leaky Paywall', 'leaky-paywall'); ?></h2>

							<iframe width="560" height="315" src="https://www.youtube.com/embed/QlrYpL72L4E" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

							<h2><?php _e('Documentation Articles'); ?></h2>

							<p><a target="_blank" href="https://zeen101.helpscoutdocs.com/collection/30-leaky-paywall">View All</a></p>

							<?php wp_nonce_field('verify', 'leaky_paywall_help_wpnonce'); ?>

							<?php do_action('leaky_paywall_after_help_settings'); ?>

						<?php endif; ?>


					</form>


				</div>
				<div class="leaky-paywall-sidebar" style="float: right; width: 28%; margin-top: 110px;">

					<div class="leaky-paywall-sidebar-widget">
						<h3>Need more functionality?</h3>
						<p><a target="_blank" href="https://zeen101.com/downloads/category/leaky-paywall-addons/?utm_medium=plugin&utm_source=sidebar&utm_campaign=settings">Browse our Add-Ons</a></p>
					</div>

					<div class="leaky-paywall-sidebar-widget">
						<h3>Need software and support?</h3>
						<p><a target="_blank" href="https://zeen101.com/pubcare/?utm_medium=plugin&utm_source=sidebar&utm_campaign=settings">Check out PubCare</a></p>
					</div>

					<div class="leaky-paywall-sidebar-widget">
						<h3>Need apps to generate more subscribers?</h3>
						<p><a target="_blank" href="https://zeen101.com/unipress/?utm_medium=plugin&utm_source=sidebar&utm_campaign=settings">Check out UniPress</a></p>
					</div>

					<div class="leaky-paywall-sidebar-widget">
						<h3>Are you a local news publisher?</h3>
						<p><a target="_blank" href="https://livemarket.pub/?utm_medium=plugin&utm_source=sidebar&utm_campaign=settings">Explode your digital ad revenue with LiveMarket</a></p>
					</div>


				</div>
			</div>
		<?php

		}

		/**
		 * Create and Display Leaky Paywall Subscribers page
		 *
		 * @since 1.0.0
		 */
		function subscribers_page()
		{

			global $blog_id;
			$settings = get_leaky_paywall_settings();

			if (is_multisite_premium() && !is_main_site($blog_id)) {
				$site = '_' . $blog_id;
			} else {
				$site = '';
			}

			$date_format = 'F j, Y';
			$jquery_date_format = leaky_paywall_jquery_datepicker_format($date_format);
			$headings = apply_filters('leaky_paywall_bulk_add_headings', array('username', 'email', 'price', 'expires', 'status', 'level-id', 'subscriber-id'));

			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';


			$this->display_zeen101_dot_com_leaky_rss_item();


		?>
			<div class="wrap">

				<div id="icon-users" class="icon32"><br /></div>
				<h2><?php _e('Leaky Paywall Subscribers', 'issuem-leaky-paywall'); ?></h2>

				<?php
				if (!empty($_POST['leaky_paywall_add_subscriber'])) {
					if (!wp_verify_nonce($_POST['leaky_paywall_add_subscriber'], 'add_new_subscriber')) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __('Unable to verify security token. Subscriber not added. Please try again.', 'leaky-paywall') . '</strong></p></div>';
					} else {
						// process form data
						if (
							!empty($_POST['leaky-paywall-subscriber-email']) && is_email(trim(rawurldecode($_POST['leaky-paywall-subscriber-email'])))
							&& !empty($_POST['leaky-paywall-subscriber-login'])
						) {

							$login = trim(rawurldecode($_POST['leaky-paywall-subscriber-login']));
							$email = trim(rawurldecode($_POST['leaky-paywall-subscriber-email']));
							$payment_gateway = trim(rawurldecode($_POST['leaky-paywall-subscriber-payment-gateway']));
							$subscriber_id = trim(rawurldecode($_POST['leaky-paywall-subscriber-id']));
							if (empty($_POST['leaky-paywall-subscriber-expires']))
								$expires = 0;
							else
								$expires = date('Y-m-d 23:59:59', strtotime(trim(urldecode($_POST['leaky-paywall-subscriber-expires']))));

							$meta = array(
								'level_id' 			=> $_POST['leaky-paywall-subscriber-level-id'],
								'subscriber_id'		=> $subscriber_id,
								'price' 			=> trim($_POST['leaky-paywall-subscriber-price']),
								'description' 		=> __('Manual Addition', 'issuem-leaky-paywall'),
								'expires' 			=> $expires,
								'payment_gateway' 	=> $payment_gateway,
								'payment_status' 	=> $_POST['leaky-paywall-subscriber-status'],
								'interval' 			=> 0,
								'plan'				=> '',
								'site'				=> $site,
							);

							$user_id = leaky_paywall_new_subscriber(NULL, $email, $subscriber_id, $meta, $login);

							do_action('add_leaky_paywall_subscriber', $user_id);

							echo '<div class="updated notice is-dismissible" id="message"><p><strong>' . __('Subscriber added.', 'leaky-paywall') . '</strong></p></div>';
						} else {

							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __('You must include a valid email address.', 'leaky-paywall') . '</strong></p></div>';
						}
					}
				} else if (!empty($_POST['leaky_paywall_edit_subscriber'])) {
					if (!wp_verify_nonce($_POST['leaky_paywall_edit_subscriber'], 'edit_subscriber')) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __('Unable to verify security token. Subscriber not added. Please try again.', 'leaky-paywall') . '</strong></p></div>';
					} else {
						// process form data
						if (
							!empty($_POST['leaky-paywall-subscriber-email']) && is_email(trim(rawurldecode($_POST['leaky-paywall-subscriber-email'])))
							&& !empty($_POST['leaky-paywall-subscriber-original-email']) && is_email(trim(rawurldecode($_POST['leaky-paywall-subscriber-original-email'])))
							&& !empty($_POST['leaky-paywall-subscriber-login']) && !empty($_POST['leaky-paywall-subscriber-original-login'])
						) {

							$orig_login = trim(rawurldecode($_POST['leaky-paywall-subscriber-original-login']));
							$orig_email = trim(rawurldecode($_POST['leaky-paywall-subscriber-original-email']));
							$user = get_user_by('email', $orig_email);

							if (!empty($user)) {
								$new_login = trim(rawurldecode($_POST['leaky-paywall-subscriber-login']));
								$new_email = trim(rawurldecode($_POST['leaky-paywall-subscriber-email']));
								$price = trim($_POST['leaky-paywall-subscriber-price']);
								$status = $_POST['leaky-paywall-subscriber-status'];
								$payment_gateway = trim(rawurldecode($_POST['leaky-paywall-subscriber-payment-gateway']));
								$subscriber_id = trim(rawurldecode($_POST['leaky-paywall-subscriber-id']));
								$plan = trim(rawurldecode($_POST['leaky-paywall-plan']));

								if (empty($_POST['leaky-paywall-subscriber-expires'])) {
									$expires = 0;
								} else {
									$expires = date('Y-m-d 23:59:59', strtotime(trim(urldecode($_POST['leaky-paywall-subscriber-expires']))));
								}

								if ($price !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, $price);
								if ($expires !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires);
								if ($status !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, $status);
								if ($payment_gateway !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, $payment_gateway);
								if ($subscriber_id !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, $subscriber_id);
								if ($plan !== get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true))
									update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, $plan);

								if ($orig_email !== $new_email) {
									$args = array('ID' => $user->ID);
									$args['user_email'] = ($orig_email === $new_email) ? $orig_email : $new_email;

									$user_id = wp_update_user($args);
								}

								if ($orig_login !== $new_login) {
									global $wpdb;
									$wpdb->update(
										$wpdb->users,
										array('user_login' => $new_login),
										array('ID' => $user->ID),
										array('%s'),
										array('%d')
									);
									clean_user_cache($user->ID);
								}

								update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, $_POST['leaky-paywall-subscriber-level-id']);

								if (isset($_POST['leaky-paywall-subscriber-notes'])) {
									update_user_meta($user->ID, '_leaky_paywall_subscriber_notes', sanitize_text_field($_POST['leaky-paywall-subscriber-notes']));
								}

								do_action('update_leaky_paywall_subscriber', $user->ID);

								echo '<div class="updated notice is-dismissible" id="message"><p><strong>' . __('Subscriber updated.', 'leaky-paywall') . '</strong></p></div>';
							}
						} else {

							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __('You must include a valid email address.', 'leaky-paywall') . '</strong></p></div>';
						}
					}
				}

				//Create an instance of our package class...
				$subscriber_table = new Leaky_Paywall_Subscriber_List_Table();
				$pagenum = $subscriber_table->get_pagenum();
				//Fetch, prepare, sort, and filter our data...
				$subscriber_table->prepare_items();
				$total_pages = $subscriber_table->get_pagination_arg('total_pages');
				if ($pagenum > $total_pages && $total_pages > 0) {
					wp_redirect(esc_url_raw(add_query_arg('paged', $total_pages)));
					exit;
				}

				?>

				<div id="leaky-paywall-subscriber-add-edit">
					<?php
					$email = !empty($_GET['edit']) ? trim(rawurldecode($_GET['edit'])) : '';
					$user = get_user_by('email', $email);

					if (!empty($email) && !empty($user)) {

						$login = $user->user_login;

						$subscriber_id = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true);
						$plan = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true);
						$subscriber_level_id = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true);
						$payment_status = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true);
						$payment_gateway = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true);
						$price = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true);
						$expires = get_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true);
						$subscriber_notes = get_user_meta($user->ID, '_leaky_paywall_subscriber_notes', true);



						if ('0000-00-00 00:00:00' === $expires)
							$expires = '';
						else
							$expires = mysql2date($date_format, $expires, false);

					?>
						<form id="leaky-paywall-susbcriber-edit" name="leaky-paywall-subscriber-edit" method="post">
							<div style="display: table">
								<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e('Username (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-login" /></p><input id="leaky-paywall-subscriber-original-login" type="hidden" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-original-login" /></p>
								<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e('Email Address (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="<?php echo $email; ?>" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p><input id="leaky-paywall-subscriber-original-email" type="hidden" value="<?php echo $email; ?>" name="leaky-paywall-subscriber-original-email" /></p>
								<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e('Price Paid', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="<?php echo $price; ?>" placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
								<p>
									<label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e('Expires', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo $expires; ?>" placeholder="<?php echo date($date_format, time()); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
									<input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
									<br><span style="color: #999;"><?php _e('Enter 0 for never expires', 'leaky-paywall'); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php _e('Subscription Level', 'leaky-paywall'); ?></label>
									<select name="leaky-paywall-subscriber-level-id">
										<?php
										foreach ($settings['levels'] as $key => $level) {
											if (!$level['label']) {
												continue;
											}
											echo '<option value="' . $key . '" ' . selected($key, $subscriber_level_id, true) . '>' . stripslashes($level['label']);
											echo $level['deleted'] ? '(deleted)' : '';
											echo '</option>';
										}
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php _e('Payment Status', 'leaky-paywall'); ?></label>
									<select name="leaky-paywall-subscriber-status">
										<option value="active" <?php selected('active', $payment_status); ?>><?php _e('Active', 'leaky-paywall'); ?></option>
										<option value="canceled" <?php selected('canceled', $payment_status); ?>><?php _e('Canceled', 'leaky-paywall'); ?></option>
										<option value="deactivated" <?php selected('deactivated', $payment_status); ?>><?php _e('Deactivated', 'leaky-paywall'); ?></option>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php _e('Payment Method', 'leaky-paywall'); ?></label>
									<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
									<select name="leaky-paywall-subscriber-payment-gateway">
										<?php foreach ($payment_gateways as $key => $gateway) {
											echo '<option value="' . $key . '" ' . selected($key, $payment_gateway, false) . '>' . $gateway . '</option>';
										}
										echo apply_filters('leaky_paywall_subscriber_payment_gateway_select_option', '');
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php _e('Subscriber ID', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="<?php echo $subscriber_id; ?>" name="leaky-paywall-subscriber-id" />
								</p>
								<p>
									<label for="leaky-paywall-plan" style="display:table-cell"><?php _e('Plan', 'leaky-paywall'); ?></label><input id="leaky-paywall-plan" class="regular-text" type="text" value="<?php echo $plan; ?>" name="leaky-paywall-plan" />
									<br><span style="color: #999;"><?php _e('Leave empty for Non-Recurring', 'leaky-paywall'); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-notes" style="display:table-cell"><?php _e('Subscriber Notes', 'leaky-paywall'); ?></label>
									<textarea id="leaky-paywall-subscriber-notes" class="regular-text" name="leaky-paywall-subscriber-notes"><?php echo esc_attr($subscriber_notes); ?></textarea>
								</p>
								<?php do_action('update_leaky_paywall_subscriber_form', $user->ID); ?>
							</div>
							<?php submit_button('Update Subscriber'); ?>
							<p>
								<a href="<?php echo esc_url(remove_query_arg('edit')); ?>"><?php _e('Cancel', 'leaky-paywall'); ?></a>
							</p>
							<?php wp_nonce_field('edit_subscriber', 'leaky_paywall_edit_subscriber'); ?>
						</form>
					<?php } else { ?>
						<form id="leaky-paywall-susbcriber-add" name="leaky-paywall-subscriber-add" method="post">
							<div style="display: table">
								<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e('Username (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-login" /></p>
								<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e('Email Address (required)', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p>
								<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e('Price Paid', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="" placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
								<p>
									<label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e('Expires', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="" placeholder="<?php echo date($date_format, time()); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
									<input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
									<br><span style="color: #999;"><?php _e('Enter 0 for never expires', 'leaky-paywall'); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php _e('Subscription Level', 'leaky-paywall'); ?></label>
									<select name="leaky-paywall-subscriber-level-id">
										<?php
										foreach ($settings['levels'] as $key => $level) {
											if (!$level['label']) {
												continue;
											}
											echo '<option value="' . $key . '">' . stripslashes($level['label']);
											echo $level['deleted'] ? '(deleted)' : '';
											echo '</option>';
										}
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php _e('Payment Status', 'leaky-paywall'); ?></label>
									<select name="leaky-paywall-subscriber-status">
										<option value="active"><?php _e('Active', 'leaky-paywall'); ?></option>
										<option value="canceled"><?php _e('Canceled', 'leaky-paywall'); ?></option>
										<option value="deactivated"><?php _e('Deactivated', 'leaky-paywall'); ?></option>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php _e('Payment Method', 'leaky-paywall'); ?></label>
									<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
									<select name="leaky-paywall-subscriber-payment-gateway">
										<?php foreach ($payment_gateways as $key => $gateway) {
											echo '<option value="' . $key . '">' . $gateway . '</option>';
										}
										echo apply_filters('leaky_paywall_subscriber_payment_gateway_select_option', '');
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php _e('Subscriber ID', 'leaky-paywall'); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-id" />
								</p>
								<?php do_action('add_leaky_paywall_subscriber_form'); ?>
							</div>
							<?php submit_button('Add New Subscriber'); ?>
							<?php wp_nonce_field('add_new_subscriber', 'leaky_paywall_add_subscriber'); ?>
						</form>

						<?php do_action('leaky_paywall_after_new_subscriber_form'); ?>

					<?php } ?>
					<br class="clear">
				</div>

				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="leaky-paywall-subscribers" method="get">
					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<!-- Now we can render the completed list table -->
					<div class="tablenav top">
						<?php $subscriber_table->user_views(); ?>
						<?php $subscriber_table->search_box(__('Search Subscribers'), 'leaky-paywall'); ?>
					</div>
					<?php $subscriber_table->display(); ?>
				</form>

			</div>
		<?php

		}

		/**
		 * Outputs the Leaky Paywall Add Ons page
		 *
		 * @since 3.1.3
		 */
		function addons_page()
		{

			// Display HTML
		?>
			<div class="wrap">

				<div style="max-width: 1035px; margin-bottom: 20px; overflow: hidden;">
					<div>
						<h2 style='margin-bottom: 10px;'><?php _e('Leaky Paywall Add-Ons', 'leaky-paywall'); ?></h2>
						<p><?php _e('Just a few of the add-ons available to extend Leaky Paywall functionality. Click the button below to view them all.', 'leaky-paywall'); ?></p>
						<p>
							<a target="_blank" class="button-primary" href="https://zeen101.com/downloads/category/leaky-paywall-addons/?utm_source=Leaky%20dashboard%20addons&utm_medium=Button&utm_content=Button&utm_campaign=Leaky%20Addons%20dashboard%20Browse%20addons%20button">Browse all add-ons for Leaky Paywall</a>
						</p>
					</div>

				</div>



				<table id="leaky-paywall-addons" cellpadding="0" cellspacing="0">
					<tbody>
						<tr>

							<td class="available-addon">
								<div class="available-addon-inner">
									<img src="https://zeen101.com/wp-content/uploads/2019/06/Leaky_Paywall_Add-Ons_Archives_-_ZEEN101-1.jpg" alt="Ad Dropper">
									<h3>Leaky Paywall - Multiple Levels</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/leaky-paywall-multiple-levels/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Give your readers different subscription options by creating an unlimited number of subscriber levels.</p>
								</div>
							</td>

							<td class="available-addon">
								<div class="available-addon-inner">
									<a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-recurring-payments/?ref=leaky_paywall_addons"><img src="https://zeen101.com/wp-content/uploads/2019/06/Leaky_Paywall_Add-Ons_Archives_-_ZEEN101.jpg" alt="Leaky Paywall MailChimp"></a>
									<h3>Leaky Paywall - Recurring Payments</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/leaky-paywall-recurring-payments/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Easily add recurring payments to any subscription level.</p>
								</div>
							</td>

							<td class="available-addon">
								<div class="available-addon-inner">
									<a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-mailchimp/?ref=leaky_paywall_addons"><img src="https://zeen101.com/wp-content/uploads/2019/06/Leaky_Paywall_Add-Ons_Archives_-_ZEEN101-2.jpg" alt="Leaky Paywall MailChimp"></a>
									<h3>Leaky Paywall - MailChimp</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/leaky-paywall-mailchimp/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Automatically add new Leaky Paywall subscribers to your MailChimp lists.</p>
								</div>
							</td>



						</tr>

						<tr>

							<td class="available-addon">
								<div class="available-addon-inner">
									<a target="_blank" href="https://zeen101.com/downloads/leaky-paywall-coupons/?ref=leaky_paywall_addons"><img src="https://zeen101.com/wp-content/uploads/2015/11/leaky-paywall-coupons.jpg" alt="Leaky Paywall Coupons"></a>
									<h3>Leaky Paywall – Coupons</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/leaky-paywall-coupons/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Create unlimited coupon codes. Assign them to individual subscription levels and more.</p>
								</div>
							</td>

							<td class="available-addon">
								<div class="available-addon-inner">
									<a target="_blank" href="https://zeen101.com/downloads/gift-subscriptions/?ref=leaky_paywall_addons"><img src="https://zeen101.com/wp-content/uploads/2015/11/leaky-paywall-gift-subscriptions.jpg" alt="Leaky Paywall Gift Subscriptions"></a>
									<h3>Leaky Paywall - Gift Subscriptions</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/gift-subscriptions/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Generate more subscriptions to your publication by letting friends and family buy gift subscriptions!</p>
								</div>
							</td>

							<td class="available-addon">
								<div class="available-addon-inner">
									<a target="_blank" href="https://zeen101.com/downloads/reporting-tool-free/?ref=leaky_paywall_addons"><img src="https://zeen101.com/wp-content/uploads/2015/11/leaky-paywall-reporting.jpg" alt="Leaky Paywall Reporting Tool"></a>
									<h3>Leaky Paywall - Reporting Tool</h3>
									<a class="button" target="_blank" href="https://zeen101.com/downloads/reporting-tool-free/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>Filter and download (CSV) the subscriber info you need to analyze your subscriptions.</p>
								</div>
							</td>

						</tr>

						<tr>
							<td class="available-addon">
								<div class="available-addon-inner">
									<img src="https://zeen101.com/wp-content/uploads/2015/03/unipress.jpg" alt="UniPress">
									<h3>UniPress</h3>
									<a class="button" target="_blank" href="https://zeen101.com/unipress/?ref=leaky_paywall_addons">Get this Add-on</a>
									<p>UniPress is the first WordPress-to-App publishing framework. It is now simple, quick, and affordable to offer your publication as an app.</p>
								</div>
							</td>
						</tr>


					</tbody>
				</table>

			</div>

		<?php

		}

		function update_page()
		{
			// Display HTML form for the options below
		?>
			<div class=wrap>
				<div style="width:70%;" class="postbox-container">
					<div class="metabox-holder">
						<div class="meta-box-sortables ui-sortable">

							<form id="issuem" method="post" action="">

								<h2 style='margin-bottom: 10px;'><?php _e("Leaky Paywall Updater", 'issuem-leaky-paywall'); ?></h2>

								<?php

								$manual_update_version = get_option('leaky_paywall_manual_update_version');
								$manual_update_version = '1.2.0'; //CHANGEME

								if (version_compare($manual_update_version, '2.0.0', '<'))
									$this->update_2_0_0();

								?>

								<?php wp_nonce_field('leaky_paywall_update', 'leaky_paywall_update_nonce'); ?>

								<?php do_action('leaky_paywall_update_form'); ?>

							</form>

						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Upgrade function, tests for upgrade version changes and performs necessary actions
		 *
		 * @since 1.0.0
		 */
		function upgrade()
		{

			$settings = $this->get_settings();

			if (isset($settings['version']))
				$old_version = $settings['version'];
			else
				$old_version = 0;

			/* Table Version Changes */
			if (isset($settings['db_version']))
				$old_db_version = $settings['db_version'];
			else
				$old_db_version = 0;

			if (0 !== $old_version && version_compare($old_version, '2.0.2', '<'))
				$this->update_2_0_2();

			if (!isset($settings['version'])) {
				$settings['post_4106'] = true;
			}

			$settings['version'] = LEAKY_PAYWALL_VERSION;
			$settings['db_version'] = LEAKY_PAYWALL_DB_VERSION;

			$this->update_settings($settings);
		}

		function update_2_0_2()
		{
			$settings = $this->get_settings();
			$settings['login_method'] = 'passwordless';
			$this->update_settings($settings);
			$settings = $this->get_settings();
		}

		function update_notices()
		{

			global $hook_suffix;

			$settings = $this->get_settings();

			if (isset($settings['version']))
				$old_version = $settings['version'];
			else
				$old_version = 0;

			if (empty($old_version)) { //new installs shouldn't see this notice
				if (current_user_can('manage_options')) {
					if ('admin_page_leaky-paywall-update' !== $hook_suffix && 'leaky-paywall_page_leaky-paywall-update' !== $hook_suffix) {

						$manual_update_version = get_option('leaky_paywall_manual_update_version');

						if (version_compare($manual_update_version, '2.0.0', '<')) {
			?>
							<div id="leaky-paywall-2-0-0-update-nag" class="update-nag">
								<?php
								$update_link = esc_url(add_query_arg(array('page' => 'leaky-paywall-update'), admin_url('admin.php')));
								printf(__('You must update the Leaky Paywall Database to version 2 to continue using this plugin... %s', 'issuem-leaky-paywall'), '<a class="btn" href="' . $update_link . '">' . __('Update Now', 'issuem-leaky-paywall') . '</a>');
								?>
							</div>
				<?php
						}
					}
				}
			}
		}

		function paypal_standard_secure_notice()
		{
			if (current_user_can('manage_options')) {
				?>
				<div id="missing-paypal-settings" class="update-nag notice">
					<?php
					$settings_link = esc_url(add_query_arg(array('page' => 'issuem-leaky-paywall', 'tab' => 'payments'), admin_url('admin.php')));
					printf(__('Please complete your PayPal setup for Leaky Paywall. %s.', 'leaky-paywall'), '<a class="btn" href="' . $settings_link . '">' . __('Complete Your Setup Now', 'leaky-paywall') . '</a>');
					?>
				</div>
<?php
			}
		}

		/**
		 * Check if the current site has a caching plugin or known managed hosting setup
		 *
		 * @since 4.14.0
		 *
		 * @param boolean
		 */
		public function check_for_caching()
		{

			$found = false;

			include_once(ABSPATH . 'wp-admin/includes/plugin.php');

			$checks = array(
				'wp-rocket/wp-rocket.php',
				'litespeed-cache/litespeed-cache.php',
				'wp-fastest-cache/wpFastestCache.php',
				'w3-total-cache/w3-total-cache.php',
				'wp-optimize/wp-optimize.php',
				'autoptimize/autoptimize.php',
				'cache-enabler/cache-enabler.php',
				'wp-super-cache/wp-cache.php',
				'hummingbird-performance/wp-hummingbird.php'
			);

			foreach ($checks as $check) {
				if (is_plugin_active($check)) {
					$found = true;
				}
			}

			return $found;
		}

		/**
		 * Displays latest RSS item from Zeen101.com on Subscriber page
		 *
		 * @since 1.0.0
		 *
		 * @param string $views
		 */
		function display_zeen101_dot_com_leaky_rss_item()
		{

			$current_user = wp_get_current_user();

			$hide = get_user_meta($current_user->ID, 'leaky_paywall_rss_item_notice_link', true);

			if ($hide == 1) {
				return;
			}

			if ($last_rss_item = get_option('last_zeen101_dot_com_leaky_rss_item', true)) {

				echo '<div class="notice notice-success">';
				echo $last_rss_item;
				echo '<p><a href="#" class="lp-notice-link" data-notice="rss_item" data-type="dismiss">' . __('Dismiss', 'leaky-paywall') . '</a></p>';
				echo '</div>';
			}
		}

		/**
		 * Process ajax calls for notice links
		 *
		 * @since 2.0.3
		 */
		function ajax_process_notice_link()
		{

			$nonce = $_POST['nonce'];

			if (!wp_verify_nonce($nonce, 'leaky-paywall-notice-nonce'))
				die('Busted!');

			$current_user = wp_get_current_user();

			update_user_meta($current_user->ID, 'leaky_paywall_rss_item_notice_link', 1);

			echo get_user_meta($current_user->ID, 'leaky_paywall_rss_item_notice_link', true);

			exit;
		}

		/**
		 * Force SSL version to TLS1.2 when using cURL
		 *
		 * Thanks roykho (WooCommerce Commit)
		 * Thanks olivierbellone (Stripe Engineer)
		 * @param resource $curl the handle
		 * @return null
		 */
		public function force_ssl_version($curl)
		{
			if (!$curl) {
				return;
			}

			if (OPENSSL_VERSION_NUMBER >= 0x1000100f) {
				if (!defined('CURL_SSLVERSION_TLSv1_2')) {
					// Note the value 6 comes from its position in the enum that
					// defines it in cURL's source code.
					define('CURL_SSLVERSION_TLSv1_2', 6); // constant not defined in PHP < 5.5
				}

				curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
			} else {
				if (!defined('CURL_SSLVERSION_TLSv1')) {
					define('CURL_SSLVERSION_TLSv1', 1); // constant not defined in PHP < 5.5
				}

				curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
			}
		}
	}
}
