<?php

/**
 * Manual Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
 */

/**
 * This class extends the gateway class for Stripe
 *
 * @since 1.0.0
 */
class Leaky_Paywall_Payment_Gateway_Manual extends Leaky_Paywall_Payment_Gateway
{



	/**
	 * Get things going
	 *
	 * @since  4.0.0
	 */
	public function init()
	{
		$settings = get_leaky_paywall_settings();
	}

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_signup()
	{

		if (
			!isset($_POST['leaky_paywall_register_nonce'])
			|| !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['leaky_paywall_register_nonce'])), 'leaky-paywall-register-nonce')
		) {
			wp_die(
				esc_html__('An error occurred, please contact the site administrator: ', 'leaky-paywall') . esc_html(get_bloginfo('admin_email')),
				esc_html__('Error', 'leaky-paywall'),
				array('response' => '401')
			);
		}

		return array(
			'level_id'          => $this->level_id,
			'subscriber_id'     => '',
			'subscriber_email'  => $this->email,
			'existing_customer' => false,
			'price'             => $this->amount,
			'description'       => $this->level_name,
			'payment_gateway'   => 'manual',
			'payment_status'    => 'active',
			'interval'          => $this->length_unit,
			'interval_count'    => $this->length,
			'site'              => $this->site,
			'plan'              => $this->plan_id,
			'recurring'         => false,
		);
	}

	/**
	 * Process incoming webhooks
	 *
	 * @since 4.0.0
	 */
	public function process_webhooks()
	{
	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 *
	 * @param integer $level_id The level id.
	 */
	public function fields($level_id)
	{

		$settings = get_leaky_paywall_settings();
		$level    = get_leaky_paywall_subscription_level($level_id);

		if (0 == $level['price']) {
			return;
		}

		if (!empty($level['recurring']) && 'on' === $level['recurring']) {
			return;
		}

		$default_button_text = leaky_paywall_get_registration_checkout_button_text();
		$title = $settings['manual_payment_title'];

		ob_start(); ?>

		<div class="leaky-paywall-payment-method-container">
			<input id="payment_method_manual" class="input-radio" name="payment_method" value="manual" checked="checked" data-order_button_text="<?php echo esc_attr('Pay by invoice'); ?>" type="radio">
			<label for="payment_method_manual"> <?php echo esc_html( $title ); ?></label>
		</div>

		<script>
			jQuery(document).ready(function($) {

				var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();
				var button = $('#leaky-paywall-submit');

				console.log('method 1: ' + method);

				if (method == 'manual') {
					$('.leaky-paywall-card-details').slideUp();
					button.text('<?php echo esc_js($default_button_text); ?>');
				}

				$('#leaky-paywall-payment-form input[name="payment_method"]').change(function() {

					var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();

					console.log('method 2: ' + method);

					if (method == 'manual') {
						$('.leaky-paywall-card-details').slideUp();
						button.text('<?php echo esc_js($default_button_text); ?>');
					}

				});



			});
		</script>

<?php
		return ob_get_clean();
	}


	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 4.0.0
	 */
	public function validate_fields()
	{
	}


	/**
	 * Load Stripe JS. Need to load it on every page for Stripe Radar rules.
	 *
	 * @since  4.0.0
	 */
	public function scripts()
	{
	}
}
