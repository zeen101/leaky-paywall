<?php 
/**
 * Payment Gateways Class
 *
 * @since 3.5.1
 */

class Leaky_Paywall_Payment_Gateways {

	public $available_gateways;
	public $enabled_gateways;

	/**
	 *  Get things going
	 *
	 * @since 3.5.1 
	 */
	public function __construct() {

		$this->available_gateways = $this->get_gateways();
		$this->enabled_gateways = $this->get_enabled_gateways();

	}

	/**
	 * Retrieve a gateway by ID
	 *
	 * @since 3.5.1
	 * @return object|false
	 */
	public function get_gateway( $id = '' ) {

		if ( isset( $this->available_gateways[ $id ] ) ) {

			return $this->available_gateways[ $id ];

		}

		return false;

	}

	/** 
	 * Retrieve all registered gateways
	 *
	 * @since  3.5.1 
	 * @return array
	 */
	private function get_gateways() {

		$gateways = array(
			// 'manual'	=> array(
			// 	'label'		=> __( 'Manual Payment', 'issuem-leaky-paywall' ),
			// 	'admin_label'	=> __( 'Manual Payment', 'issuem-leaky-paywall' ),
			// 	'class'			=> 'Leaky_Paywall_Payment_Gateway_Manual'
			// ),
			'paypal_standard'	=> array(
				'label'		=> __( 'PayPal', 'issuem-leaky-paywall' ),
				'admin_label'	=> __( 'PayPal Standard', 'issuem-leaky-paywall' ),
				'class'			=> __( 'Leaky_Paywall_Payment_Gateway_PayPal' )
			),
			'stripe'	=> array(
				'label'		=> __( 'Credit / Debit Card', 'issuem-leaky-paywall' ),
				'admin_label'	=> __( 'Stripe Credit / Debit Card', 'issuem-leaky-paywall' ),
				'class'			=> 'Leaky_Paywall_Payment_Gateway_Stripe'
			),
			'stripe_checkout'	=> array(
				'label'		=> __( 'Stripe Checkout', 'issuem-leaky-paywall' ),
				'admin_label'	=> __( 'Stripe Checkout', 'issuem-leaky-paywall' ),
				'class'			=> 'Leaky_Paywall_Payment_Gateway_Stripe_Checkout'
			)
		);

		return apply_filters( 'leaky_paywall_payment_gateways', $gateways );

	}

	/**
	 * Retrieve all enabled gateways
	 *
	 * @since 3.5.1 
	 * @return array 
	 */
	private function get_enabled_gateways() {

		$settings = get_leaky_paywall_settings();

		$enabled = array();
		$saved = isset( $settings['payment_gateway'] ) ? array_map( 'trim', $settings['payment_gateway'] ) : array();

		if ( ! empty( $saved ) ) {

			foreach ( $this->available_gateways as $key => $gateway ) {

				if ( in_array( $key, $saved ) ) {

					$enabled[$key] = $gateway;

				}
			}
		}

		if ( empty( $enabled ) ) {

			$enabled['paypal_standard'] = __( 'PayPal Standard', 'issuem-leaky-paywall' );

		}

		return apply_filters( 'leaky_paywall_enabled_payment_gateways', $enabled, $this->available_gateways );

	}

	/**
	 * Determine if a gateway is enabled
	 *
	 * @since 3.5.1 
	 * @return bool
	 */
	public function is_gateway_enabled( $id = '' ) {
		return isset( $this->enabled_gateways[$id] );
	}

	/**
	 * Loead the fieds for the gateway
	 *
	 * @since 3.5.1 
	 * @return void
	 */
	public function load_fields() {

		if ( ! empty( $_POST['leaky_paywall_gateway'] ) ) {

			$gateway = $this->get_gateway( sanitize_text_field( $_POST['leaky_paywall_gateway'] ) );

			if ( isset( $gateway['class'] ) ) {
				$gateway = new $gateway['class'];
			}

			if ( is_object( $gateway ) ) {
				wp_send_json_success( array( 'success'	=> true, 'fields' => $gateway->fields() ) );
			} else {
				wp_send_json_error( array( 'success' => false ) );
			}

		}

	}
	
}