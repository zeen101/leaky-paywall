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
class Leaky_Paywall {

	/**
	 * Leaky Paywall Name
	 *
	 * @var string
	 */
	private $plugin_name = LEAKY_PAYWALL_NAME;

	/**
	 * Leaky Paywall Slug
	 *
	 * @var string
	 */
	private $plugin_slug = LEAKY_PAYWALL_SLUG;

	/**
	 * Leaky Paywall Basename
	 *
	 * @var string
	 */
	private $basename = LEAKY_PAYWALL_BASENAME;

	/**
	 * Class constructor, puts things in motion
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'http_api_curl', array( $this, 'force_ssl_version' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_wp_enqueue_scripts' ) );
		add_filter('script_loader_tag', array( $this, 'add_type_attribute' ) , 10, 3);
		add_action( 'admin_print_styles', array( $this, 'admin_wp_print_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'wp_ajax_leaky_paywall_process_notice_link', array( $this, 'ajax_process_notice_link' ) );

		add_action( 'wp', array( $this, 'process_content_restrictions' ) );
		add_action( 'wp', array( $this, 'process_pdf_restrictions' ) );
		add_action( 'init', array( $this, 'process_js_content_restrictions' ) );
		add_action( 'rest_api_init', array( $this, 'process_rest_content_restrictions' ) );

		add_filter( 'issuem_pdf_attachment_url', array( $this, 'restrict_pdf_attachment_url' ), 10, 2 );
	}

	/**
	 * Process restrictions with WP REST API
	 */
	public function process_rest_content_restrictions() {

		$restrictions = new Leaky_Paywall_Restrictions();
		add_filter( 'the_content', array( $restrictions, 'process_rest_content_restrictions' ) );

	}

	/**
	 * Process restrictions with javascript
	 */
	public function process_js_content_restrictions() {
		$settings = get_leaky_paywall_settings();

		if ( 'on' === $settings['enable_js_cookie_restrictions'] ) {
			$restrictions = new Leaky_Paywall_Restrictions();
			$restrictions->process_js_content_restrictions();
		}
	}

	/**
	 * Process restrictions with php
	 */
	public function process_content_restrictions() {
		if ( is_admin() ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		if ( 'on' === $settings['enable_js_cookie_restrictions'] ) {
			return;
		}

		$restrictions = new Leaky_Paywall_Restrictions();
		$restrictions->process_content_restrictions();
	}

	/**
	 * Process restrictions for IssueM PDFs
	 */
	public function process_pdf_restrictions() {
		if ( isset( $_GET['issuem-pdf-download'] ) ) {
			$restrictions = new Leaky_Paywall_Restrictions();
			$restrictions->pdf_access();
		}
	}

	/**
	 * Send the user to the my account page if they user has paid and they try to access the login page
	 *
	 * @since 4.10.3
	 */
	public function redirect_from_login_page() {
		$settings = get_leaky_paywall_settings();

		if ( ! empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {

			if ( ! empty( $settings['page_for_profile'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
				exit;
			} elseif ( ! empty( $settings['page_for_subscription'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
				exit;
			}
		}
	}

	/**
	 * Process the passwordless login functionality
	 *
	 * @since 4.10.3
	 */
	public function process_passwordless_login() {
		$settings = get_leaky_paywall_settings();

		if ( ! empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {

			if ( empty( $_REQUEST['r'] ) ) {
				return;
			}

			$login_hash = sanitize_text_field( wp_unslash( $_REQUEST['r'] ) );

			if ( verify_leaky_paywall_login_hash( $login_hash ) ) {

				leaky_paywall_attempt_login( $login_hash );
				if ( ! empty( $settings['page_for_profile'] ) ) {
					wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
					exit;
				} elseif ( ! empty( $settings['page_for_subscription'] ) ) {
					wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
					exit;
				}
			} else {

				$output = '<h3>' . __( 'Invalid or Expired Login Link', 'leaky-paywall' ) . '</h3>';
				/* Translators: %s - page for login url */
				$output .= '<p>' . sprintf( __( 'Sorry, this login link is invalid or has expired. <a href="%s">Try again?</a>', 'leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</p>';
				/* Translators: %s - site name */
				$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'leaky-paywall' ), $settings['site_name'] ) . '</a>';

				$message = apply_filters( 'leaky_paywall_invalid_login_link' );

				wp_die( esc_attr( $message ), esc_attr( $output ) );
			}
		}
	}

	/**
	 * Restrict a PDF attachment url
	 *
	 * @param string $attachment_url The attachment url.
	 * @param int    $attachment_id The id of the attachment.
	 * @return string The new attachment url
	 */
	public function restrict_pdf_attachment_url( $attachment_url, $attachment_id ) {

		$settings = get_leaky_paywall_settings();

		if ( 'on' === $settings['restrict_pdf_downloads'] ) {
			return esc_url( add_query_arg( 'issuem-pdf-download', $attachment_id ) );
		}

		return $attachment_url;

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
	public function admin_menu() {

		$settings = new Leaky_Paywall_Settings();
		$insights = new Leaky_Paywall_Insights();
		$admin_icon = $this->get_svg();

		add_menu_page( __( 'Leaky Paywall', 'leaky-paywall' ), __( 'Leaky Paywall', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $settings, 'settings_page' ), $admin_icon );

		add_submenu_page( 'issuem-leaky-paywall', __( 'Settings', 'leaky-paywall' ), __( 'Settings', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $settings, 'settings_page' ) );

		add_submenu_page( 'issuem-leaky-paywall', __( 'Subscribers', 'leaky-paywall' ), __( 'Subscribers', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-subscribers', array( $this, 'subscribers_page' ) );

		add_submenu_page( 'issuem-leaky-paywall', __( 'Transactions', 'leaky-paywall' ), __( 'Transactions', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'edit.php?post_type=lp_transaction' );

		add_submenu_page( 'issuem-leaky-paywall', __( 'Insights', 'leaky-paywall' ), __( 'Insights', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-insights', array( $insights, 'insights_page' ) );

		if ( !is_plugin_active( 'leaky-paywall-multiple-levels/leaky-paywall-multiple-levels.php' ) ) {
			add_submenu_page( 'issuem-leaky-paywall', __( 'Upgrade', 'leaky-paywall' ), __( 'Upgrade', 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-upgrade', array( $this, 'upgrade_page' ) );
		}

	}


	/**
	 * Prints backend Leaky Paywall styles
	 *
	 * @global $hook_suffix
	 * @since 1.0.0
	 */
	public function admin_wp_print_styles() {
		global $hook_suffix;

		if (
			'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix
			|| 'toplevel_page_issuem-leaky-paywall' === $hook_suffix
			|| 'index.php' === $hook_suffix
			|| 'leaky-paywall_page_leaky-paywall-upgrade' ===
			$hook_suffix
			|| 'leaky-paywall_page_leaky-paywall-insights' === $hook_suffix
		) {
			wp_enqueue_style( 'leaky_paywall_admin_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-admin.css', '', LEAKY_PAYWALL_VERSION );
		}

		if ( 'leaky-paywall_page_leaky-paywall-insights' === $hook_suffix ) {
			wp_enqueue_style( 'leaky_paywall_admin_insights_style', LEAKY_PAYWALL_URL . 'css/leaky-paywall-admin-insights.css', '', LEAKY_PAYWALL_VERSION );
		}

		if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
			wp_enqueue_style( 'leaky_paywall_post_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-post.css', '', LEAKY_PAYWALL_VERSION );
		}

	}

	public function get_svg() {

		$svg_b64 = 'PHN2ZyB3aWR0aD0iNDIzIiBoZWlnaHQ9IjQyMyIgdmlld0JveD0iMCAwIDQyMyA0MjMiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0zNjEuNDAzIDAuNzY0MTZWNjMuMDA5M0g2MS40MDgyQzkxLjQ1NTggMjMuNzc3MiAxMzguMDY3IDAuNzY0MTYgMTg3LjQ4MSAwLjc2NDE2SDM2MS40MDNaIiBmaWxsPSIjRTQ1NjM3Ii8+CjxwYXRoIGQ9Ik02MS40MDgyIDQyMi41MTVWMzYwLjI3SDM2MS40MDNDMzMxLjM1NSAzOTkuNTAyIDI4NC43NjIgNDIyLjUxNSAyMzUuMzMgNDIyLjUxNUg2MS40MDgyWiIgZmlsbD0iI0U0NTYzNyIvPgo8cGF0aCBkPSJNMjQuMDg5NCA2Mi40MDg3SDYxLjQyOTZWMzYwLjg3MkgyNC4wODk0QzExLjE4OTcgMzYwLjg3MiAwLjczMjQyMiAzNTAuNDE1IDAuNzMyNDIyIDMzNy41MTVWODUuNzY1N0MwLjczMjQyMiA3Mi44ODMyIDExLjE4OTcgNjIuNDA4NyAyNC4wODk0IDYyLjQwODdaIiBmaWxsPSIjRTQ1NjM3Ii8+CjxwYXRoIGQ9Ik0zOTguNzQ3IDM2MC44NzJIMzYxLjQwNkwzNjEuNDA2IDYyLjQwODNIMzk4Ljc0N0M0MTEuNjI5IDYyLjQwODMgNDIyLjEwNCA3Mi44NjU3IDQyMi4xMDQgODUuNzY1M0w0MjIuMTA0IDMzNy41MTVDNDIyLjEwNCAzNTAuMzk4IDQxMS42NDYgMzYwLjg3MiAzOTguNzQ3IDM2MC44NzJaIiBmaWxsPSIjRTQ1NjM3Ii8+CjxwYXRoIGQ9Ik0yOTkuOTE4IDEyMy4xNDFIMTIyLjkxOFYzMDAuMTQxSDI5OS45MThWMTIzLjE0MVoiIGZpbGw9IiNFNDU2MzciLz4KPC9zdmc+Cg==';

		$icon = 'data:image/svg+xml;base64,' . $svg_b64;

		return $icon;
	}

	/**
	 * Enqueues backend IssueM styles
	 *
	 * @param string $hook_suffix The hook suffix.
	 * @since 1.0.0
	 */
	public function admin_wp_enqueue_scripts( $hook_suffix ) {

		if ( 'toplevel_page_issuem-leaky-paywall' === $hook_suffix ) {
			wp_enqueue_script( 'leaky_paywall_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-settings.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );

			wp_localize_script(
				'leaky_paywall_js',
				'leaky_paywall_js',
				array(
					'lpJsNonce' => wp_create_nonce('leaky-paywall-js-nonce'),
				)
			);
		}

		if ( 'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix ) {

			// Removing i18n of UI datepicker for subscriber page only.
			remove_action( 'admin_enqueue_scripts', 'wp_localize_jquery_ui_datepicker', 1000 );

			wp_enqueue_script( 'leaky_paywall_subscribers_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-subscribers.js', array( 'jquery-ui-datepicker' ), LEAKY_PAYWALL_VERSION, true );
			wp_enqueue_style( 'leaky_paywall_admin_subscribers_style', LEAKY_PAYWALL_URL . 'css/leaky-paywall-subscribers.css', '', LEAKY_PAYWALL_VERSION );
		}


		if ( 'leaky-paywall_page_leaky-paywall-insights' === $hook_suffix ) {
			wp_enqueue_script('leaky_paywall_chart_js', LEAKY_PAYWALL_URL . 'js/chart.min.js', array(), LEAKY_PAYWALL_VERSION, false);
		}

		wp_localize_script(
			'leaky_paywall_subscribers_js',
			'leaky_paywall_notice_ajax',
			array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'lpNoticeNonce' => wp_create_nonce( 'leaky-paywall-notice-nonce' ),
			)
		);

		if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix ) {
			wp_enqueue_script( 'leaky_paywall_post_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-post.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );
		}
	}

	public function add_type_attribute( $tag, $handle, $src )
	{

		// if not your script, do nothing and return original $tag
		if ( 'leaky_paywall_insights' !== $handle ) {
			return $tag;
		}
		// change the script tag by adding type="module" and return it.
		$tag = '<script type="module" src="' . esc_url( $src ) . '"></script>';
		return $tag;
	}


	/**
	 * Enqueues frontend scripts and styles
	 *
	 * @since 1.0.0
	 */
	public function frontend_scripts() {
		$settings = get_leaky_paywall_settings();

		if ( 'default' === $settings['css_style'] ) {
			wp_enqueue_style( 'issuem-leaky-paywall', LEAKY_PAYWALL_URL . '/css/issuem-leaky-paywall.css', '', LEAKY_PAYWALL_VERSION );
		}

		if ( 'on' === $settings['enable_js_cookie_restrictions'] ) {

			if ( is_home() || is_front_page() || is_archive() ) {
				return;
			}

			wp_enqueue_script( 'js_cookie_js', LEAKY_PAYWALL_URL . 'js/js-cookie.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );
			wp_enqueue_script( 'leaky_paywall_cookie_js', LEAKY_PAYWALL_URL . 'js/leaky-paywall-cookie.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );

			$post_container   = apply_filters( 'leaky_paywall_js_restriction_post_container', $settings['js_restrictions_post_container'] );
			$page_container   = apply_filters( 'leaky_paywall_js_restriction_page_container', $settings['js_restrictions_page_container'] );
			$lead_in_elements = apply_filters( 'leaky_paywall_js_restriction_lead_in_elements', $settings['lead_in_elements'] );

			wp_localize_script(
				'leaky_paywall_cookie_js',
				'leaky_paywall_cookie_ajax',
				array(
					'ajaxurl'          => admin_url( 'admin-ajax.php', 'relative' ),
					'post_container'   => $post_container,
					'page_container'   => $page_container,
					'lead_in_elements' => $lead_in_elements,
				)
			);
		}

		wp_enqueue_script( 'zeen101_micromodal', LEAKY_PAYWALL_URL . 'js/micromodal.min.js', array('jquery'), LEAKY_PAYWALL_VERSION, true);
		wp_enqueue_script( 'leaky_paywall_validate', LEAKY_PAYWALL_URL . 'js/leaky-paywall-validate.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );
		wp_enqueue_script( 'leaky_paywall_script', LEAKY_PAYWALL_URL . 'js/script.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION, true );

		wp_localize_script(
			'leaky_paywall_validate',
			'leaky_paywall_validate_ajax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php', 'relative' ),
				'register_nonce' => wp_create_nonce( 'lp_register_nonce' ),
				'password_text' =>  esc_html__('Passwords do not match.', 'leaky-paywall')
			)
		);

		wp_localize_script(
			'leaky_paywall_script',
			'leaky_paywall_script_ajax',
			array(
				'ajaxurl'   => admin_url( 'admin-ajax.php', 'relative' ),
				'stripe_pk' => leaky_paywall_get_stripe_public_key(),
			)
		);

		$gateways = new Leaky_Paywall_Payment_Gateways();

		if ( $gateways->is_gateway_enabled( 'stripe' ) || $gateways->is_gateway_enabled('stripe_checkout') ) {

			if ( get_the_ID() == $settings['page_for_register'] ) {
				wp_enqueue_script('leaky_paywall_stripe_registration', LEAKY_PAYWALL_URL . 'js/stripe-registration.js', array('jquery'), LEAKY_PAYWALL_VERSION, true);

				wp_localize_script(
					'leaky_paywall_stripe_registration',
					'leaky_paywall_stripe_registration_ajax',
					array(
						'ajaxurl'   => admin_url('admin-ajax.php', 'relative'),
						'stripe_pk' => leaky_paywall_get_stripe_public_key(),
						'continue_text' => esc_html__('Processing... Please Wait', 'leaky-paywall'),
						'next_text' => esc_html__('Next', 'leaky-paywall'),
						'billing_address' => $settings['stripe_billing_address'],
						'redirect_url' => get_page_link($settings['page_for_profile'])
					)
				);

			}

		}
	}

	/**
	 * Check if zeen101's Leaky Paywall MultiSite options are enabled
	 *
	 * @since CHANGEME
	 */
	public function is_site_wide_enabled() {
		return ( is_multisite() ) ? get_site_option( 'issuem-leaky-paywall-site-wide' ) : false;
	}

	/**
	 * Create and Display Leaky Paywall Subscribers page
	 *
	 * @since 1.0.0
	 */
	public function subscribers_page() {
		global $blog_id;
		$settings = get_leaky_paywall_settings();

		if ( is_multisite_premium() && ! is_main_site( $blog_id ) ) {
			$site = '_' . $blog_id;
		} else {
			$site = '';
		}

		$date_format        = 'F j, Y';
		$jquery_date_format = leaky_paywall_jquery_datepicker_format( $date_format );
		$headings           = apply_filters( 'leaky_paywall_bulk_add_headings', array( 'username', 'email', 'price', 'expires', 'status', 'level-id', 'subscriber-id' ) );

		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		$this->display_zeen101_dot_com_leaky_rss_item();

		?>

		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo.png'; ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title">Subscribers</h1>
				</span>
			</div>
		</div>


			<div class="wrap">

			<?php
			if ( ! empty( $_POST['leaky_paywall_add_subscriber'] ) ) {
				if ( ! wp_verify_nonce( sanitize_key( $_POST['leaky_paywall_add_subscriber'] ), 'add_new_subscriber' ) ) {
					echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . esc_html__( 'Unable to verify security token. Subscriber not added. Please try again.', 'leaky-paywall' ) . '</strong></p></div>';
				} else {
					// process form data.
					if (
						! empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( rawurldecode( sanitize_email( wp_unslash( $_POST['leaky-paywall-subscriber-email'] ) ) ) ) )
						&& ! empty( $_POST['leaky-paywall-subscriber-login'] )
					) {

						$login           = isset( $_POST['leaky-paywall-subscriber-login'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-login'] ) ) : '';
						$email           = isset( $_POST['leaky-paywall-subscriber-email'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-email'] ) ) : '';
						$payment_gateway = isset( $_POST['leaky-paywall-subscriber-payment-gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-payment-gateway'] ) ) : '';
						$subscriber_id   = isset( $_POST['leaky-paywall-subscriber-id'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-id'] ) ) : '';
						if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) ) {
							$expires = 0;
						} else {
							$expires = gmdate( 'Y-m-d 23:59:59', strtotime( trim( urldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-expires'] ) ) ) ) ) );
						}

						$meta = array(
							'level_id'        => isset( $_POST['leaky-paywall-subscriber-level-id'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-level-id'] ) ) : '',
							'subscriber_id'   => $subscriber_id,
							'price'           => isset( $_POST['leaky-paywall-subscriber-price'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-price'] ) ) : '',
							'description'     => __( 'Manual Addition', 'issuem-leaky-paywall' ),
							'expires'         => $expires,
							'payment_gateway' => $payment_gateway,
							'payment_status'  => isset( $_POST['leaky-paywall-subscriber-status'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-status'] ) ) : '',
							'interval'        => 0,
							'plan'            => '',
							'site'            => $site,
						);

						$user_id = leaky_paywall_new_subscriber( null, $email, $subscriber_id, $meta, $login );

						do_action( 'add_leaky_paywall_subscriber', $user_id );

						echo '<div class="updated notice is-dismissible" id="message"><p><strong>' . esc_html__( 'Subscriber added.', 'leaky-paywall' ) . '</strong></p></div>';
					} else {

						echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . esc_html__( 'You must include a valid email address.', 'leaky-paywall' ) . '</strong></p></div>';
					}
				}
			} elseif ( ! empty( $_POST['leaky_paywall_edit_subscriber'] ) ) {
				if ( ! wp_verify_nonce( sanitize_key( $_POST['leaky_paywall_edit_subscriber'] ), 'edit_subscriber' ) ) {
					echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . esc_html__( 'Unable to verify security token. Subscriber not added. Please try again.', 'leaky-paywall' ) . '</strong></p></div>';
				} else {
					// process form data.
					if (
						! empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( rawurldecode( sanitize_email( wp_unslash( $_POST['leaky-paywall-subscriber-email'] ) ) ) ) )
						&& ! empty( $_POST['leaky-paywall-subscriber-original-email'] ) && is_email( trim( rawurldecode( sanitize_email( wp_unslash( $_POST['leaky-paywall-subscriber-original-email'] ) ) ) ) )
						&& ! empty( $_POST['leaky-paywall-subscriber-login'] ) && ! empty( $_POST['leaky-paywall-subscriber-original-login'] )
					) {

						$orig_login = isset( $_POST['leaky-paywall-subscriber-original-login'] ) ? trim( rawurldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-original-login'] ) ) ) ) : '';
						$orig_email = isset( $_POST['leaky-paywall-subscriber-original-email'] ) ? trim( rawurldecode( sanitize_email( wp_unslash( $_POST['leaky-paywall-subscriber-original-email'] ) ) ) ) : '';
						$user       = get_user_by( 'email', $orig_email );

						if ( ! empty( $user ) ) {
							$new_login       = isset( $_POST['leaky-paywall-subscriber-login'] ) ? trim( rawurldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-login'] ) ) ) ) : '';
							$new_email       = isset( $_POST['leaky-paywall-subscriber-email'] ) ? trim( rawurldecode( sanitize_email( wp_unslash( $_POST['leaky-paywall-subscriber-email'] ) ) ) ) : '';
							$price           = isset( $_POST['leaky-paywall-subscriber-price'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-price'] ) ) : '';
							$status          = isset( $_POST['leaky-paywall-subscriber-status'] ) ? sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-status'] ) ) : '';
							$payment_gateway = isset( $_POST['leaky-paywall-subscriber-payment-gateway'] ) ? trim( rawurldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-payment-gateway'] ) ) ) ) : '';
							$subscriber_id   = isset( $_POST['leaky-paywall-subscriber-id'] ) ? trim( rawurldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-id'] ) ) ) ) : '';
							$plan            = isset( $_POST['leaky-paywall-plan'] ) ? trim( rawurldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-plan'] ) ) ) ) : '';

							if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) ) {
								$expires = 0;
							} else {
								$expires = gmdate( 'Y-m-d 23:59:59', strtotime( urldecode( sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-expires'] ) ) ) ) );
							}

							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true ) !== $price ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, $price );
							}
							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true ) !== $expires ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
							}
							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true ) !== $status ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, $status );
							}
							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true ) !== $payment_gateway ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, $payment_gateway );
							}
							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true ) !== $subscriber_id ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, $subscriber_id );
							}
							if ( get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true ) !== $plan ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, $plan );
							}

							if ( $orig_email !== $new_email ) {
								$args               = array( 'ID' => $user->ID );
								$args['user_email'] = ( $orig_email === $new_email ) ? $orig_email : $new_email;

								$user_id = wp_update_user( $args );
							}

							if ( $orig_login !== $new_login ) {
								global $wpdb;
								$wpdb->update(
									$wpdb->users,
									array( 'user_login' => $new_login ),
									array( 'ID' => $user->ID ),
									array( '%s' ),
									array( '%d' )
								);
								clean_user_cache( $user->ID );
							}

							if ( isset( $_POST['leaky-paywall-subscriber-level-id'] ) ) {
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-level-id'] ) ) );
							}

							if ( isset( $_POST['leaky-paywall-subscriber-notes'] ) ) {
								update_user_meta( $user->ID, '_leaky_paywall_subscriber_notes', sanitize_text_field( wp_unslash( $_POST['leaky-paywall-subscriber-notes'] ) ) );
							}

							do_action( 'update_leaky_paywall_subscriber', $user->ID );

							echo '<div class="updated notice is-dismissible" id="message"><p><strong>' . esc_html__( 'Subscriber updated.', 'leaky-paywall' ) . '</strong></p></div>';
						}
					} else {

						echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . esc_html__( 'You must include a valid email address.', 'leaky-paywall' ) . '</strong></p></div>';
					}
				}
			}

			// Create an instance of our package class...
			$subscriber_table = new Leaky_Paywall_Subscriber_List_Table();
			$pagenum          = $subscriber_table->get_pagenum();
			// Fetch, prepare, sort, and filter our data...
			$subscriber_table->prepare_items();
			$total_pages = $subscriber_table->get_pagination_arg( 'total_pages' );
			if ( $pagenum > $total_pages && $total_pages > 0 ) {
				wp_safe_redirect( esc_url_raw( add_query_arg( 'paged', $total_pages ) ) );
				exit();
			}

			?>

				<div id="leaky-paywall-subscriber-add-edit">
				<?php

				$email = '';

				if ( isset( $_GET['edit'] ) ) {
					$email = rawurldecode( sanitize_email( wp_unslash( $_GET['edit'] ) ) );
				}

				$user = get_user_by( 'email', $email );

				if ( ! empty( $email ) && ! empty( $user ) ) {

					$login = $user->user_login;

					$subscriber_id       = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					$plan                = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
					$subscriber_level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
					$payment_status      = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
					$payment_gateway     = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$price               = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true );
					$expires             = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
					$subscriber_notes    = get_user_meta( $user->ID, '_leaky_paywall_subscriber_notes', true );

					if ( '0000-00-00 00:00:00' === $expires ) {
						$expires = '';
					} else {
						$expires = mysql2date( $date_format, $expires, false );
					}

					?>
						<form id="leaky-paywall-susbcriber-edit" name="leaky-paywall-subscriber-edit" method="post">
							<div style="display: table">
								<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php esc_html_e( 'Username (required)', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="<?php echo esc_attr( $login ); ?>" name="leaky-paywall-subscriber-login" /></p><input id="leaky-paywall-subscriber-original-login" type="hidden" value="<?php echo esc_attr( $login ); ?>" name="leaky-paywall-subscriber-original-login" /></p>
								<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php esc_html_e( 'Email Address (required)', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="<?php echo esc_attr( $email ); ?>" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p><input id="leaky-paywall-subscriber-original-email" type="hidden" value="<?php echo esc_attr( $email ); ?>" name="leaky-paywall-subscriber-original-email" /></p>
								<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php esc_html_e( 'Price Paid', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="<?php echo esc_attr( $price ); ?>" placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
								<p>
									<label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php esc_html_e( 'Expires', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo esc_attr( $expires ); ?>" placeholder="<?php echo esc_attr( gmdate( $date_format, time() ) ); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
									<input type="hidden" name="date_format" value="<?php echo esc_attr( $jquery_date_format ); ?>" />
									<br><span style="color: #999;"><?php esc_html_e( 'Enter 0 for never expires', 'leaky-paywall' ); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php esc_html_e( 'Subscription Level', 'leaky-paywall' ); ?></label>
									<select name="leaky-paywall-subscriber-level-id">
									<?php
									foreach ( $settings['levels'] as $key => $level ) {
										if ( ! $level['label'] ) {
											continue;
										}
										echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $subscriber_level_id, true ) . '>' . esc_html( stripslashes( $level['label'] ) );
										echo isset($level['deleted']) && $level['deleted'] == 1 ? '(deleted)' : '';
										echo '</option>';
									}
									?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php esc_html_e( 'Payment Status', 'leaky-paywall' ); ?></label>
									<select name="leaky-paywall-subscriber-status">
										<option value="active" <?php selected( 'active', $payment_status ); ?>><?php esc_html_e( 'Active', 'leaky-paywall' ); ?></option>
										<option value="canceled" <?php selected( 'canceled', $payment_status ); ?>><?php esc_html_e( 'Canceled', 'leaky-paywall' ); ?></option>
										<option value="deactivated" <?php selected( 'deactivated', $payment_status ); ?>><?php esc_html_e( 'Deactivated', 'leaky-paywall' ); ?></option>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php esc_html_e( 'Payment Method', 'leaky-paywall' ); ?></label>
									<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
									<select name="leaky-paywall-subscriber-payment-gateway">
										<?php
										foreach ( $payment_gateways as $key => $gateway ) {
											echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $payment_gateway, false ) . '>' . esc_html( $gateway ) . '</option>';
										}
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php esc_html_e( 'Subscriber ID', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="<?php echo esc_attr( $subscriber_id ); ?>" name="leaky-paywall-subscriber-id" />
								</p>
								<p>
									<label for="leaky-paywall-plan" style="display:table-cell"><?php esc_html_e( 'Plan', 'leaky-paywall' ); ?></label><input id="leaky-paywall-plan" class="regular-text" type="text" value="<?php echo esc_attr( $plan ); ?>" name="leaky-paywall-plan" />
									<br><span style="color: #999;"><?php esc_html_e( 'Leave empty for Non-Recurring', 'leaky-paywall' ); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-notes" style="display:table-cell"><?php esc_html_e( 'Subscriber Notes', 'leaky-paywall' ); ?></label>
									<textarea id="leaky-paywall-subscriber-notes" class="regular-text" name="leaky-paywall-subscriber-notes"><?php echo esc_html( $subscriber_notes ); ?></textarea>
								</p>
								<?php do_action( 'update_leaky_paywall_subscriber_form', $user->ID ); ?>
							</div>
							<?php submit_button( 'Update Subscriber' ); ?>
							<p>
								<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>"><?php esc_html_e( 'Cancel', 'leaky-paywall' ); ?></a>
							</p>
							<?php wp_nonce_field( 'edit_subscriber', 'leaky_paywall_edit_subscriber' ); ?>
						</form>
					<?php } else { ?>
						<form id="leaky-paywall-susbcriber-add" name="leaky-paywall-subscriber-add" method="post">
							<div style="display: table">
								<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php esc_html_e( 'Username (required)', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-login" /></p>
								<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php esc_html_e( 'Email Address (required)', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p>
								<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php esc_html_e( 'Price Paid', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="" placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
								<p>
									<label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php esc_html_e( 'Expires', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="" placeholder="<?php echo esc_attr( gmdate( $date_format, time() ) ); ?>" name="leaky-paywall-subscriber-expires" autocomplete="off" />
									<input type="hidden" name="date_format" value="<?php echo esc_attr( $jquery_date_format ); ?>" />
									<br><span style="color: #999;"><?php esc_html_e( 'Enter 0 for never expires', 'leaky-paywall' ); ?></span>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php esc_html_e( 'Subscription Level', 'leaky-paywall' ); ?></label>
									<select name="leaky-paywall-subscriber-level-id">
										<?php
										foreach ( $settings['levels'] as $key => $level ) {
											if ( ! $level['label'] ) {
												continue;
											}
											if (isset($level['deleted']) ) {
												continue;
											}
											echo '<option value="' . esc_attr( $key ) . '">' . esc_html( stripslashes( $level['label'] ) );
											echo isset( $level['deleted'] ) && $level['deleted'] == 1 ? '(deleted)' : '';
											echo '</option>';
										}
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php esc_html_e( 'Payment Status', 'leaky-paywall' ); ?></label>
									<select name="leaky-paywall-subscriber-status">
										<option value="active"><?php esc_html_e( 'Active', 'leaky-paywall' ); ?></option>
										<option value="canceled"><?php esc_html_e( 'Canceled', 'leaky-paywall' ); ?></option>
										<option value="deactivated"><?php esc_html_e( 'Deactivated', 'leaky-paywall' ); ?></option>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php esc_html_e( 'Payment Method', 'leaky-paywall' ); ?></label>
									<?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
									<select name="leaky-paywall-subscriber-payment-gateway">
										<?php
										foreach ( $payment_gateways as $key => $gateway ) {
											echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $gateway ) . '</option>';
										}
										?>
									</select>
								</p>
								<p>
									<label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php esc_html_e( 'Subscriber ID', 'leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-id" />
								</p>
								<?php do_action( 'add_leaky_paywall_subscriber_form' ); ?>
							</div>
							<?php submit_button( 'Add New Subscriber' ); ?>
							<?php wp_nonce_field( 'add_new_subscriber', 'leaky_paywall_add_subscriber' ); ?>
						</form>

						<?php do_action( 'leaky_paywall_after_new_subscriber_form' ); ?>

					<?php } ?>
					<br class="clear">
				</div>

				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="leaky-paywall-subscribers" method="get">
					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo isset( $_GET['page'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) : ''; ?>" />
					<!-- Now we can render the completed list table -->
					<div class="tablenav top">
						<?php $subscriber_table->user_views(); ?>
						<?php $subscriber_table->search_box( __( 'Search Subscribers' ), 'leaky-paywall' ); ?>
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
	public function insights_page() {
		?>
			<div id="lp-wit-app">

				<div class="wrap">

					<app-stats></app-stats>

				</div>

			</div>
		<?php

	}

	/**
	 * Outputs the Leaky Paywall Add Ons page
	 *
	 * @since 3.1.3
	 */
	public function upgrade_page() {
		?>
			<div id="leaky-paywall-upgrade-page-wrapper">
				<div class="header">
					<a href="https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_medium=plugin&utm_source=upgrade&utm_campaign=settings"><img src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo-wh.png'; ?>"></a>
				</div>
				<div class="content">

					<h2>Upgrade to Leaky Paywall Pro</h2>
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
						<a class="button" target="_blank" href="https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_medium=plugin&utm_source=upgrade&utm_campaign=settings">Upgrade Now</a>
					</p>
				</div>
				<div class="logo">
					<a href="https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_medium=plugin&utm_source=upgrade&utm_campaign=settings"><img src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . '/images/leaky-paywall-logo-wh.png'; ?>"></a>
				</div>
			</div>

			<?php

	}

	/**
	 * Display paypal setup notice in WP admin
	 */
	public function paypal_standard_secure_notice() {
		if ( current_user_can( 'manage_options' ) ) {
			?>
				<div id="missing-paypal-settings" class="update-nag notice">
				<?php
				$settings_link = esc_url(
					add_query_arg(
						array(
							'page' => 'issuem-leaky-paywall',
							'tab'  => 'payments',
						),
						admin_url( 'admin.php' )
					)
				);
				/* Translators: %s - url for settings */
				printf( esc_attr__( 'Please complete your PayPal setup for Leaky Paywall. %s.', 'leaky-paywall' ), '<a class="btn" href="' . esc_url( $settings_link ) . '">' . esc_html__( 'Complete Your Setup Now', 'leaky-paywall' ) . '</a>' );
				?>
				</div>
				<?php
		}
	}


	/**
	 * Displays latest RSS item from Zeen101.com on Subscriber page
	 *
	 * @since 1.0.0
	 */
	public function display_zeen101_dot_com_leaky_rss_item() {
		$current_user = wp_get_current_user();

		$hide = get_user_meta( $current_user->ID, 'leaky_paywall_rss_item_notice_link', true );

		if ( 1 === $hide ) {
			return;
		}

		$last_rss_item = get_option( 'last_zeen101_dot_com_leaky_rss_item', true );

		if ( $last_rss_item ) {
			echo '<div class="notice notice-success">';
			echo wp_kses_post( $last_rss_item );
			echo '<p><a href="#" class="lp-notice-link" data-notice="rss_item" data-type="dismiss">' . esc_html__( 'Dismiss', 'leaky-paywall' ) . '</a></p>';
			echo '</div>';
		}
	}

	/**
	 * Process ajax calls for notice links
	 *
	 * @since 2.0.3
	 */
	public function ajax_process_notice_link() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'leaky-paywall-notice-nonce' ) ) {
			die( 'Busted!' );
		}

		$current_user = wp_get_current_user();

		update_user_meta( $current_user->ID, 'leaky_paywall_rss_item_notice_link', 1 );

		exit;
	}

	/**
	 * Force SSL version to TLS1.2 when using cURL
	 *
	 * Thanks roykho (WooCommerce Commit)
	 * Thanks olivierbellone (Stripe Engineer)
	 *
	 * @param resource $curl the handle.
	 * @return null
	 */
	public function force_ssl_version( $curl ) {
		if ( ! $curl ) {
			return;
		}

		if ( OPENSSL_VERSION_NUMBER >= 0x1000100f ) {
			if ( ! defined( 'CURL_SSLVERSION_TLSV1_2' ) ) {
				// Note the value 6 comes from its position in the enum that
				// defines it in cURL's source code.
				define( 'CURL_SSLVERSION_TLSV1_2', 6 ); // constant not defined in PHP < 5.5.
			}

			curl_setopt( $curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSV1_2 );
		} else {
			if ( ! defined( 'CURL_SSLVERSION_TLSV1' ) ) {
				define( 'CURL_SSLVERSION_TLSV1', 1 ); // constant not defined in PHP < 5.5.
			}

			curl_setopt( $curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSV1 );
		}
	}

	/**
	 * Sanitize level settings
	 *
	 * @param array $levels The levels to sanitize.
	 * @return array
	 */
	public function sanitize_levels( $levels ) {
		$text_fields     = array( 'label', 'deleted', 'price', 'subscription_length_type', 'interval_count', 'interval', 'hide_subscribe_card' );
		$textarea_fields = array( 'description', 'registration_form_description' );

		foreach ( $levels as $i => $level ) {

			foreach ( $level as $key => $value ) {

				if ( in_array( $key, $text_fields ) ) {
					$levels[ $i ][ $key ] = sanitize_text_field( wp_unslash( $value ) );
				}

				if ( in_array( $key, $textarea_fields ) ) {
					$levels[ $i ][ $key ] = wp_kses_post( wp_unslash( $value ) );
				}

				if ( 'post_types' == $key ) {
					$levels[ $i ][ $key ] = $this->sanitize_level_post_types( $value );
				}
			}
		}

		return $levels;
	}

	/**
	 * Sanitize level post types
	 *
	 * @param array $post_types The post types to sanitize.
	 */
	public function sanitize_level_post_types( $post_types ) {
		foreach ( $post_types as $i => $rules ) {
			foreach ( $rules as $key => $rule ) {
				$post_types[ $i ][ $key ] = sanitize_text_field( $rule );
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
	public function sanitize_restrictions( $restrictions ) {
		foreach ( $restrictions as $i => $restriction ) {

			foreach ( $restriction as $key => $value ) {

				$restrictions[ $i ] [ $key ] = array_map( 'sanitize_text_field', $value );

			}
		}

		return $restrictions;
	}
}
