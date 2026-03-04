<?php

/**
 * Leaky Paywall Onboarding Class
 *
 * @package Leaky Paywall
 * @since   4.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Onboarding {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'admin_init', array( $this, 'process_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register the hidden onboarding page.
	 */
	public function register_page() {
		add_submenu_page(
			null,
			__( 'Leaky Paywall Setup', 'leaky-paywall' ),
			__( 'Setup', 'leaky-paywall' ),
			'manage_options',
			'leaky-paywall-setup',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Redirect to onboarding on first activation.
	 */
	public function maybe_redirect() {
		if ( ! get_option( 'leaky_paywall_onboarding_redirect', false ) ) {
			return;
		}

		// Only redirect for users who can manage the plugin.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't redirect during AJAX, CLI, or bulk/network activations.
		if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Don't redirect if already on the setup page.
		if ( isset( $_GET['page'] ) && 'leaky-paywall-setup' === $_GET['page'] ) {
			return;
		}

		delete_option( 'leaky_paywall_onboarding_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=leaky-paywall-setup' ) );
		exit;
	}

	/**
	 * Enqueue onboarding styles.
	 */
	public function enqueue_styles( $hook ) {
		if ( false === strpos( $hook, 'leaky-paywall-setup' ) ) {
			return;
		}

		wp_enqueue_style(
			'leaky-paywall-onboarding',
			LEAKY_PAYWALL_URL . 'css/leaky-paywall-onboarding.css',
			array(),
			LEAKY_PAYWALL_VERSION
		);
	}

	/**
	 * Main page router.
	 */
	public function render_page() {
		$step = isset( $_GET['step'] ) ? sanitize_text_field( $_GET['step'] ) : '';

		$steps = array(
			''             => array( 'num' => 1, 'label' => __( 'Welcome', 'leaky-paywall' ) ),
			'tracking'     => array( 'num' => 2, 'label' => __( 'Analytics', 'leaky-paywall' ) ),
			'pages'        => array( 'num' => 3, 'label' => __( 'Pages', 'leaky-paywall' ) ),
			'listbuilder'  => array( 'num' => 4, 'label' => __( 'List Builder', 'leaky-paywall' ) ),
			'restrictions' => array( 'num' => 5, 'label' => __( 'Restrictions', 'leaky-paywall' ) ),
			'stripe'       => array( 'num' => 6, 'label' => __( 'Payments', 'leaky-paywall' ) ),
			'email'        => array( 'num' => 7, 'label' => __( 'Email', 'leaky-paywall' ) ),
		);

		$current_num = isset( $steps[ $step ] ) ? $steps[ $step ]['num'] : 1;
		$total_steps = count( $steps );
		?>
		<div id="leaky-paywall-onboarding" class="wrap">
			<div class="leaky-paywall-onboarding--logo"><img src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . 'images/leaky-paywall-logo.png'; ?>" alt="Leaky Paywall" width="200" /></div>

			<div class="lp-onboarding-progress">
				<div class="lp-onboarding-progress--bar">
					<div class="lp-onboarding-progress--fill" style="width: <?php echo esc_attr( round( ( $current_num / $total_steps ) * 100 ) ); ?>%;"></div>
				</div>
				<div class="lp-onboarding-progress--steps">
					<?php foreach ( $steps as $slug => $info ) : ?>
						<span class="lp-onboarding-progress--step <?php echo $info['num'] === $current_num ? 'is-current' : ''; ?> <?php echo $info['num'] < $current_num ? 'is-complete' : ''; ?>">
							<?php echo esc_html( $info['label'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			</div>

			<?php
			$error = get_transient( 'leaky_paywall_onboarding_error' );
			if ( ! empty( $error ) ) {
				?>
				<div class="lp-onboarding-error">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
				<?php
				delete_transient( 'leaky_paywall_onboarding_error' );
			}

			$template_dir = LEAKY_PAYWALL_PATH . 'include/admin/onboarding/templates/';

			switch ( $step ) {
				case 'tracking':
					include $template_dir . 'tracking.php';
					break;
				case 'pages':
					include $template_dir . 'pages.php';
					break;
				case 'listbuilder':
					include $template_dir . 'listbuilder.php';
					break;
				case 'restrictions':
					include $template_dir . 'restrictions.php';
					break;
				case 'stripe':
					include $template_dir . 'stripe.php';
					break;
				case 'email':
					include $template_dir . 'email.php';
					break;
				default:
					include $template_dir . 'welcome.php';
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Process form submissions for each step.
	 */
	public function process_data() {

		// Welcome step — save publication types.
		if ( isset( $_POST['leaky_paywall_onboarding_welcome'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_welcome'], 'leaky_paywall_onboarding_welcome' ) ) {

			if ( ! empty( $_POST['publication_types'] ) ) {
				$publication_types = array_map( 'sanitize_text_field', $_POST['publication_types'] );
				update_option( 'leaky_paywall_publication_types', $publication_types, false );
			}

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=tracking' ) );
			exit;
		}

		// Tracking opt-in step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_tracking'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_tracking'], 'leaky_paywall_onboarding_tracking' ) ) {

			update_option( 'leaky_paywall_tracking_allow', 1, false );

			// Send tracking data immediately so publication types reach the app right away.
			leaky_paywall_tracking_send();

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=pages' ) );
			exit;
		}

		// Create pages step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_pages'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_pages'], 'leaky_paywall_onboarding_pages' ) ) {

			$settings = get_leaky_paywall_settings();

			$pages = array(
				'page_for_login'        => array(
					'title'     => __( 'Login', 'leaky-paywall' ),
					'shortcode' => '[leaky_paywall_login]',
				),
				'page_for_subscription' => array(
					'title'     => __( 'Subscribe', 'leaky-paywall' ),
					'shortcode' => '[leaky_paywall_subscription]',
				),
				'page_for_register'     => array(
					'title'     => __( 'Register', 'leaky-paywall' ),
					'shortcode' => '[leaky_paywall_register_form]',
				),
				'page_for_profile'      => array(
					'title'     => __( 'My Account', 'leaky-paywall' ),
					'shortcode' => '[leaky_paywall_profile]',
				),
			);

			foreach ( $pages as $setting_key => $page_data ) {
				// Skip if a page is already set.
				if ( ! empty( $settings[ $setting_key ] ) ) {
					continue;
				}

				$page_id = wp_insert_post( array(
					'post_title'   => $page_data['title'],
					'post_content' => $page_data['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				) );

				if ( $page_id && ! is_wp_error( $page_id ) ) {
					$settings[ $setting_key ] = $page_id;
				}
			}

			update_leaky_paywall_settings( $settings );

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=listbuilder' ) );
			exit;
		}

		// List Builder step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_listbuilder'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_listbuilder'], 'leaky_paywall_onboarding_listbuilder' ) ) {

			if ( ! empty( $_POST['enable_listbuilder'] ) ) {
				$lb_settings                         = get_option( 'lp-listbuilder', array() );
				$lb_settings['enabled']              = 'on';
				$lb_settings['level_id']             = 0;
				$lb_settings['heading']              = 'Create a free account, or log in.';
				$lb_settings['subheading']           = 'Gain access to read this content, plus limited free content.';
				$lb_settings['terms_and_conditions'] = 'Yes! I would like to receive new content and updates.';
				$lb_settings['background_color']     = '#000000';
				$lb_settings['text_color']           = '#ffffff';
				$lb_settings['button_color']         = '#E45637';
				$lb_settings['button_text_color']    = '#ffffff';
				update_option( 'lp-listbuilder', $lb_settings );
			}

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=restrictions' ) );
			exit;
		}

		// Restrictions step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_restrictions'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_restrictions'], 'leaky_paywall_onboarding_restrictions' ) ) {

			$settings = get_leaky_paywall_settings();

			$allowed_value = isset( $_POST['free_posts'] ) ? absint( $_POST['free_posts'] ) : 1;

			$settings['free_articles'] = $allowed_value;
			$settings['restrictions']  = array(
				'post_types' => array(
					array(
						'post_type'     => ACTIVE_ISSUEM ? 'article' : 'post',
						'taxonomy'      => 'all',
						'allowed_value' => $allowed_value,
					),
				),
			);

			$settings['enable_js_cookie_restrictions'] = 'on';

			if ( ! empty( $_POST['paid_level_price'] ) && floatval( $_POST['paid_level_price'] ) > 0 ) {
				$settings['levels']['1'] = array(
					'label'                    => ! empty( $_POST['paid_level_label'] ) ? sanitize_text_field( $_POST['paid_level_label'] ) : 'Digital Subscription',
					'price'                    => sanitize_text_field( $_POST['paid_level_price'] ),
					'subscription_length_type' => 'limited',
					'interval_count'           => 1,
					'interval'                 => isset( $_POST['paid_level_interval'] ) && in_array( $_POST['paid_level_interval'], array( 'month', 'year' ), true ) ? $_POST['paid_level_interval'] : 'month',
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
				);
			}

			update_leaky_paywall_settings( $settings );

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=stripe' ) );
			exit;
		}

		// Welcome email step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_email'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_email'], 'leaky_paywall_onboarding_email' ) ) {

			$settings = get_leaky_paywall_settings();

			$settings['new_subscriber_email'] = 'on';

			if ( isset( $_POST['email_subject'] ) ) {
				$settings['new_email_subject'] = sanitize_text_field( wp_unslash( $_POST['email_subject'] ) );
			}

			if ( isset( $_POST['email_body'] ) ) {
				$settings['new_email_body'] = wp_kses_post( wp_unslash( $_POST['email_body'] ) );
			}

			update_leaky_paywall_settings( $settings );

			wp_redirect( admin_url( 'admin.php?page=issuem-leaky-paywall&lp_onboarding_complete=1' ) );
			exit;
		}
	}

	/**
	 * Build the Stripe Connect URL.
	 */
	public function get_connect_url() {
		$api_key = leaky_paywall_ensure_app_api_key();

		$state = wp_generate_password( 32, false, false );
		set_transient( 'lp_connect_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );

		$return_url = add_query_arg(
			array(
				'page'             => 'leaky-paywall-settings',
				'tab'              => 'payments',
				'lp_connect_state' => $state,
			),
			admin_url( 'admin.php' )
		);

		$refresh_url = add_query_arg(
			array(
				'page'             => 'leaky-paywall-settings',
				'tab'              => 'payments',
				'connect_refresh'  => 'true',
				'lp_connect_state' => $state,
			),
			admin_url( 'admin.php' )
		);

		$base_url = apply_filters( 'leaky_paywall_app_url', 'https://app.leakypaywall.com' );

		return add_query_arg(
			array(
				'api_key'              => $api_key,
				'live_mode'            => 1,
				'lp_connect_state'     => $state,
				'customer_site_url'    => urlencode( $return_url ),
				'customer_refresh_url' => urlencode( $refresh_url ),
			),
			$base_url . '/api/v1/connect/init'
		);
	}
}

new Leaky_Paywall_Onboarding();
