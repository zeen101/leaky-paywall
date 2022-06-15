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

			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );

			add_action( 'leaky_paywall_after_licenses_settings', array( $this, 'license_key_settings_div' ) );

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

			?>
			<div id="modules" class="postbox">

				<h3 style="margin-left: 10px;" class="hndle"><span><?php echo esc_html( $this->plugin_name, $this->plugin_slug ); ?></span></h3>

				<div class="inside">
					
					<table id="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_key" class="form-table">
						<tr>
							<th rowspan="1"> 
								<?php esc_attr_e( 'License Key', 'leaky-paywall' ); ?>
							</th>
	
							<td>
								<input type="text" id="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_key" class="regular-text" name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_key" value="<?php echo esc_attr( $settings['license_key'] ); ?>" />
							
								<?php
								if ( false !== $settings['license_status']
										&& 'valid' == $settings['license_status'] ) {
														   // license is active.
									?>
											<span style="color:green;"><?php esc_html_e( 'active' ); ?></span>
											<input type="submit" class="button-secondary" 
												name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_deactivate" 
												value="<?php esc_attr_e( 'Deactivate License', 'leaky-paywall' ); ?>"/>

									<?php
								} elseif ( 'invalid' == $settings['license_status'] ) {
										// license is invalid.
									?>
											<span style="color:red;"><?php esc_html_e( 'invalid' ); ?></span>
											<input type="submit" class="button-secondary" 
												name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_activate" 
												value="<?php esc_attr_e( 'Activate License', 'leaky-paywall' ); ?>"/>

									<?php
								} else {
										// license hasn't been entered yet.
										$plug_slug = $this->plugin_prefix;
									?>
											<input type="submit" class="button-secondary" 
												name="<?php echo esc_attr( $this->plugin_prefix ); ?>_license_activate" 
												value="<?php esc_attr_e( 'Activate License', $plug_slug ); ?>"/>

								<?php } ?>
								
							</td>
						</tr>
					</table>
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
