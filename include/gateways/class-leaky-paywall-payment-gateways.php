<?php
/**
 * Payment Gateways Class
 *
 * @package     Leaky Paywall
 * @since 4.0.0
 */

/**
 * The payment gateways class
 */
class Leaky_Paywall_Payment_Gateways {


	public $available_gateways;
	public $enabled_gateways;

	/**
	 *  Get things going
	 *
	 * @since 4.0.0
	 */
	public function __construct() {
		$this->available_gateways = $this->get_gateways();
		$this->enabled_gateways   = $this->get_enabled_gateways();
	}

	/**
	 * Retrieve a gateway by ID
	 *
	 * @since 4.0.0
	 *
	 * @param integer $id The id.
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
	 * @since  4.0.0
	 * @return array
	 */
	private function get_gateways() {
		$gateways = array(
			'manual'	=> array(
				'label'     => __( 'Manual Payment', 'leaky-paywall' ),
				'admin_label'   => __( 'Manual Payment (useful for testing payments or accepting checks)', 'leaky-paywall' ),
				'class'         => 'Leaky_Paywall_Payment_Gateway_Manual'
			),
			'stripe'          => array(
				'label'       => __( 'Credit / Debit Card', 'leaky-paywall' ),
				'admin_label' => __( 'Stripe (Credit / Debit Card)', 'leaky-paywall' ),
				'class'       => 'Leaky_Paywall_Payment_Gateway_Stripe',
			),
			'stripe_checkout' => array(
				'label'		=> __('Stripe Checkout', 'leaky-paywall'),
				'admin_label'	=> __('Stripe Checkout (this will send the user offsite for payment)', 'leaky-paywall'),
				'class'			=> 'Leaky_Paywall_Payment_Gateway_Stripe_Checkout'
			)
		);

		// Only show PayPal Standard if the site already has it enabled.
		$settings    = get_leaky_paywall_settings();
		$saved       = isset( $settings['payment_gateway'] ) ? $settings['payment_gateway'] : array();
		$paypal_on   = in_array( 'paypal_standard', $saved, true ) || in_array( 'paypal-standard', $saved, true );

		if ( $paypal_on ) {
			$gateways['paypal_standard'] = array(
				'label'       => __( 'PayPal', 'leaky-paywall' ),
				'admin_label' => __( 'PayPal Standard', 'leaky-paywall' ),
				'class'       => 'Leaky_Paywall_Payment_Gateway_PayPal',
			);
		}

		return apply_filters( 'leaky_paywall_payment_gateways', $gateways );
	}

	/**
	 * Retrieve all enabled gateways
	 *
	 * @since 4.0.0
	 * @return array
	 */
	private function get_enabled_gateways() {
		$settings = get_leaky_paywall_settings();

		$enabled = array();
		$saved   = isset( $settings['payment_gateway'] ) ? array_map( 'trim', $settings['payment_gateway'] ) : array();

		if ( ! empty( $saved ) ) {

			foreach ( $this->available_gateways as $key => $gateway ) {

				if ( in_array( $key, $saved ) ) {

					$enabled[ $key ] = $gateway;
				}
			}
		}

		if ( in_array( 'stripe_checkout', $saved ) && ! in_array( 'stripe', $saved ) ) {

			$enabled['stripe'] = array(
				'label'       => 'Credit / Debit Card',
				'admin_label' => 'Stripe',
				'class'       => 'Leaky_Paywall_Payment_Gateway_Stripe',
			);
		}

		if ( empty( $enabled ) ) {

			$enabled['stripe'] = array(
				'label'       => __( 'Credit / Debit Card', 'leaky-paywall' ),
				'admin_label' => __( 'Stripe (Credit / Debit Card)', 'leaky-paywall' ),
				'class'       => 'Leaky_Paywall_Payment_Gateway_Stripe',
			);
		}

		return apply_filters( 'leaky_paywall_enabled_payment_gateways', $enabled, $this->available_gateways );
	}

	/**
	 * Determine if a gateway is enabled
	 *
	 * @since 4.0.0
	 *
	 * @param integer $id The id of the gateway.
	 * @return bool
	 */
	public function is_gateway_enabled( $id = '' ) {
		return isset( $this->enabled_gateways[ $id ] );
	}

}
