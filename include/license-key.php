<?php
/**
 * Registers Leaky Paywall License Key class
 *
 * @package Leaky Paywall
 * @since 4.4.0
 */

if ( ! class_exists( 'Leaky_Paywall_License_Key' ) ) {
	/**
	 * This class registers the Leaky Paywall license key activate/deactive functionality
	 *
	 * @since 4.4.0
	 */
	class Leaky_Paywall_License_Key {

		/**
		 * Registry of all add-on slugs.
		 *
		 * @var array<string>
		 */
		private static $registered_slugs = array();

		/**
		 * The plugin slug
		 *
		 * @var string
		 */
		private $plugin_slug;

		/**
		 * The underscored version of the slug
		 *
		 * @var string
		 */
		private $plugin_prefix;

		/**
		 * The plugin name
		 *
		 * @var string
		 */
		private $plugin_name;

		/**
		 * Class constructor, puts things in motion
		 *
		 * @since 4.4.0
		 *
		 * @param string $plugin_slug The plugin slug.
		 * @param string $plugin_name The plugin name.
		 */
		public function __construct( $plugin_slug, $plugin_name ) {

			$this->plugin_slug   = $plugin_slug;
			$this->plugin_name   = $plugin_name;
			$this->plugin_prefix = str_replace( '-', '_', $plugin_slug );

			self::$registered_slugs[] = $plugin_slug;

			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );

			add_action( 'leaky_paywall_after_licenses_settings', array( $this, 'license_key_settings_div' ) );

		}

		/**
		 * Get all registered add-on slugs.
		 *
		 * @since 5.0
		 *
		 * @return array<string>
		 */
		public static function get_registered_slugs() {
			return self::$registered_slugs;
		}

		/**
		 * Get Leaky Paywall License Settings for this plugin
		 *
		 * @since 4.4.0
		 */
		public function get_settings() {

			$defaults = array(
				'license_key'    => '',
				'license_status' => '',
			);

			$defaults = apply_filters( $this->plugin_slug . '_default_license_settings', $defaults );
			$settings = get_option( $this->plugin_slug );
			$settings = wp_parse_args( $settings, $defaults );

			return apply_filters( $this->plugin_prefix . '_get_license_settings', $settings );

		}

		/**
		 * Update Leaky Paywall License Settings for this plugin
		 *
		 * @since 4.4.0
		 *
		 * @param array $settings The settings.
		 */
		public function update_settings( $settings ) {
			update_option( $this->plugin_slug, $settings );
		}

		/**
		 * Create and display license key on the Leaky Paywall settings page
		 *
		 * @since 1.3.0
		 */
		public function license_key_settings_div() {

			$settings = $this->get_settings();

			if ( false !== $settings['license_status'] && 'valid' == $settings['license_status'] ) {
				$badge_class = 'lp-status-badge--active';
				$badge_label = __( 'Active', 'leaky-paywall' );
			} elseif ( 'invalid' == $settings['license_status'] ) {
				$badge_class = 'lp-status-badge--expired';
				$badge_label = __( 'Invalid', 'leaky-paywall' );
			} else {
				$badge_class = 'lp-status-badge--canceled';
				$badge_label = __( 'Not Activated', 'leaky-paywall' );
			}

			?>
			<div class="lp-license-card">
				<div class="lp-license-card__header">
					<span class="lp-license-card__name"><?php echo esc_html( $this->plugin_name ); ?></span>
					<span class="lp-status-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
				</div>
				<div class="lp-license-card__body">
					<input type="text" id="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_key" class="regular-text" name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_key" value="<?php echo esc_attr( $settings['license_key'] ); ?>" />

					<?php if ( false !== $settings['license_status'] && 'valid' == $settings['license_status'] ) : ?>
						<input type="submit" class="button-secondary"
							name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_deactivate"
							value="<?php esc_attr_e( 'Deactivate License', 'leaky-paywall' ); ?>"/>
					<?php else : ?>
						<input type="submit" class="button-secondary"
							name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_activate"
							value="<?php esc_attr_e( 'Activate License', 'leaky-paywall' ); ?>"/>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/**
		 * Activate the Leaky Paywall license if its valid for this plugin
		 *
		 * @since 4.4.0
		 */
		public function activate_license() {

			if ( ! isset( $_POST[ $this->plugin_prefix . '_license_activate' ] ) ) {
				return;
			}

			if ( ! check_admin_referer( 'verify', 'leaky_paywall_license_wpnonce' ) ) {
				return;
			}

			$settings = $this->get_settings();

			if ( ! empty( $_POST[ $this->plugin_prefix . '_license_key' ] ) ) {
				$settings['license_key'] = sanitize_text_field( wp_unslash( $_POST[ $this->plugin_prefix . '_license_key' ] ) );
			}

			$license = trim( $settings['license_key'] );

			// data to send in our API request.
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => rawurlencode( $this->plugin_name ), // the name of our product in EDD.
			);

			// Call the custom API.
			$response = wp_remote_get(
				esc_url_raw( add_query_arg( $api_params, ZEEN101_STORE_URL ) ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				)
			);

			// make sure the response came back okay.
			if ( is_wp_error( $response ) ) {
				return false;
			}

			// decode the license data.
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "active" or "inactive".
			$settings['license_status'] = $license_data->license;
			$this->update_settings( $settings );

		}

		/**
		 * De-activate the Leaky Paywall license for this plugin
		 *
		 * @since 4.4.0
		 */
		public function deactivate_license() {

			if ( ! isset( $_POST[ $this->plugin_prefix . '_license_deactivate' ] ) ) {
				return;
			}

			if ( ! check_admin_referer( 'verify', 'leaky_paywall_license_wpnonce' ) ) {
				return;
			}

			$settings = $this->get_settings();

			// retrieve the license from the database.
			$license = trim( $settings['license_key'] );

			// data to send in our API request.
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => rawurlencode( $this->plugin_name ), // the name of our product in EDD.
			);

			// Call the custom API.
			$response = wp_remote_get(
				esc_url_raw( add_query_arg( $api_params, ZEEN101_STORE_URL ) ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				)
			);

			// make sure the response came back okay.
			if ( is_wp_error( $response ) ) {
				return false;
			}

			// decode the license data.
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed".
			if ( 'deactivated' == $license_data->license || 'failed' == $license_data->license ) {

				unset( $settings['license_key'] );
				unset( $settings['license_status'] );
				$this->update_settings( $settings );

			}

		}

	}

}
