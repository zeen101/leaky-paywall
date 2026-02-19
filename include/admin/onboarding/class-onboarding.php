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
		if ( get_option( 'leaky_paywall_onboarding_redirect', false ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			delete_option( 'leaky_paywall_onboarding_redirect' );
			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup' ) );
			exit;
		}
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
		?>
		<div id="leaky-paywall-onboarding" class="wrap">
			<p class="leaky-paywall-onboarding--logo"><img src="<?php echo esc_url( LEAKY_PAYWALL_URL ) . 'images/leaky-paywall-logo.png'; ?>" alt="Leaky Paywall" width="300" /></p>

			<?php
			add_thickbox();
			wp_enqueue_media();

			$error = get_transient( 'leaky_paywall_onboarding_error' );
			if ( ! empty( $error ) ) {
				?>
				<div class="leaky-paywall-onboarding--error" style="background:red; padding: 5px 20px; text-align: center; color: #FFF; border-radius: 5px; max-width: 750px; margin: 40px auto -50px auto;">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
				<?php
				delete_transient( 'leaky_paywall_onboarding_error' );
			}

			$template_dir = LEAKY_PAYWALL_PATH . 'include/admin/onboarding/templates/';

			switch ( $step ) {
				case 'business':
					include $template_dir . 'business.php';
					break;
				case 'stripe':
					include $template_dir . 'stripe.php';
					break;
				case 'license':
					include $template_dir . 'license.php';
					break;
				case 'data':
					include $template_dir . 'data.php';
					break;
				case 'updates':
					include $template_dir . 'updates.php';
					break;
				case 'guide':
					include $template_dir . 'guide.php';
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

		// Business information step.
		if ( isset( $_POST['leaky_paywall_onboarding_business'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_business'], 'leaky_paywall_onboarding_business' ) ) {

			update_option( 'leaky_paywall_address1', sanitize_text_field( $_POST['address1'] ), false );
			update_option( 'leaky_paywall_address2', sanitize_text_field( $_POST['address2'] ), false );
			update_option( 'leaky_paywall_city', sanitize_text_field( $_POST['city'] ), false );
			update_option( 'leaky_paywall_state', sanitize_text_field( $_POST['state'] ), false );
			update_option( 'leaky_paywall_postcode', sanitize_text_field( $_POST['postcode'] ), false );
			update_option( 'leaky_paywall_country', sanitize_text_field( $_POST['country'] ), false );
			update_option( 'leaky_paywall_logo', intval( $_POST['logo'] ), false );

			if ( ! empty( $_POST['publication_types'] ) ) {
				$publication_types = array_map( 'sanitize_text_field', $_POST['publication_types'] );
				update_option( 'leaky_paywall_publication_types', $publication_types, false );
			}

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=stripe' ) );
			exit;
		}

		// License activation step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_license'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_license'], 'leaky_paywall_onboarding_license' ) ) {

			$license = sanitize_text_field( $_POST['license'] );
			update_option( 'leaky_paywall_license_key', $license, false );

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=data&license=true' ) );
			exit;
		}

		// Analytics/data tracking step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_data'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_data'], 'leaky_paywall_onboarding_data' ) ) {

			update_option( 'leaky_paywall_tracking_allow', 1, false );

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=updates' ) );
			exit;
		}

		// Email updates step.
		elseif ( isset( $_POST['leaky_paywall_onboarding_updates'] ) && wp_verify_nonce( $_POST['leaky_paywall_onboarding_updates'], 'leaky_paywall_onboarding_updates' ) ) {

			$email      = sanitize_email( $_POST['email'] );
			$first_name = sanitize_text_field( $_POST['first_name'] );

			update_option( 'leaky_paywall_newsletter_email', $email, false );
			update_option( 'leaky_paywall_newsletter_name', $first_name, false );

			wp_redirect( admin_url( 'admin.php?page=leaky-paywall-setup&step=guide' ) );
			exit;
		}
	}

	/**
	 * Build the Stripe Connect URL.
	 */
	public function get_connect_url() {

		$return_url = add_query_arg(
			array(
				'page' => 'issuem-leaky-paywall',
				'tab'  => 'payments',
			),
			admin_url( 'admin.php' )
		);

		$refresh_url = add_query_arg(
			array(
				'page'            => 'issuem-leaky-paywall',
				'tab'             => 'payments',
				'connect_refresh' => 'true',
			),
			admin_url( 'admin.php' )
		);

		$mode = leaky_paywall_get_current_mode();

		$stripe_connect_url = add_query_arg(
			array(
				'live_mode'            => 'live' === $mode ? true : false,
				'state'                => str_pad( wp_rand( wp_rand(), PHP_INT_MAX ), 100, wp_rand(), STR_PAD_BOTH ),
				'customer_site_url'    => esc_url_raw( $return_url ),
				'customer_refresh_url' => esc_url_raw( $refresh_url ),
			),
			'https://leakypaywall.com/?lp_gateway_connect_init=stripe_connect'
		);

		return $stripe_connect_url;
	}

	/**
	 * Get list of countries.
	 */
	public static function get_countries() {
		return array(
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'AU' => 'Australia',
			'NZ' => 'New Zealand',
			'IE' => 'Ireland',
			'DE' => 'Germany',
			'FR' => 'France',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'CH' => 'Switzerland',
			'AT' => 'Austria',
			'DK' => 'Denmark',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'FI' => 'Finland',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'GR' => 'Greece',
			'CZ' => 'Czech Republic',
			'RO' => 'Romania',
			'HU' => 'Hungary',
			'BR' => 'Brazil',
			'MX' => 'Mexico',
			'AR' => 'Argentina',
			'CL' => 'Chile',
			'CO' => 'Colombia',
			'IN' => 'India',
			'JP' => 'Japan',
			'CN' => 'China',
			'KR' => 'South Korea',
			'SG' => 'Singapore',
			'MY' => 'Malaysia',
			'TH' => 'Thailand',
			'ID' => 'Indonesia',
			'PH' => 'Philippines',
			'ZA' => 'South Africa',
		);
	}
}

new Leaky_Paywall_Onboarding();
