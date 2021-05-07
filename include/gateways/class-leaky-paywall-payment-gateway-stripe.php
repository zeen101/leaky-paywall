<?php

/**
 * Stripe Payment Gateway Class
 *
 * @package     Leaky Paywall
 * @subpackage  Classes/Roles
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0.0
 */

class Leaky_Paywall_Payment_Gateway_Stripe extends Leaky_Paywall_Payment_Gateway
{

	private $secret_key;
	protected $publishable_key;

	/**
	 * Get things going
	 *
	 * @since  4.0.0
	 */
	public function init()
	{

		$settings = get_leaky_paywall_settings();

		$this->supports[]	= 'one-time';
		$this->supports[]	= 'recurring';
		// $this->supports[]	= 'fees';

		$this->test_mode	= 'off' === $settings['test_mode'] ? false : true;

		if ($this->test_mode) {

			$this->secret_key = isset($settings['test_secret_key']) ? trim($settings['test_secret_key']) : '';
			$this->publishable_key = isset($settings['test_publishable_key']) ? trim($settings['test_publishable_key']) : '';
		} else {

			$this->secret_key = isset($settings['live_secret_key']) ? trim($settings['live_secret_key']) : '';
			$this->publishable_key = isset($settings['live_publishable_key']) ? trim($settings['live_publishable_key']) : '';
		}

		if (!class_exists('Stripe') && !class_exists('Stripe\Stripe')) {
			require_once LEAKY_PAYWALL_PATH . 'include/stripe/init.php';
		}
	}

	/**
	 * Process registration
	 *
	 * @since 4.0.0
	 */
	public function process_signup()
	{

		$level = get_leaky_paywall_subscription_level($this->level_id);

		$incomplete_user = get_posts(array(
			'post_type' => 'lp_incomplete_user',
			'posts_per_page' => 1,
			'meta_key'	=> '_email',
			'meta_value' => $this->email
		));

		if (empty($incomplete_user)) {
			wp_die(__('An error occurred, please contact the site administrator: ', 'leaky-paywall') . get_bloginfo('admin_email'), __('Error', 'leaky-paywall'), array('response' => '401'));
		}

		$customer_data = get_post_meta($incomplete_user[0]->ID, '_customer_data', true);
		$customer_id = $customer_data->id;

		$user = get_user_by('email', $this->email);

		if ($user) {
			$existing_customer = true;
		} else {
			$existing_customer = false;
		}

		if (!$customer_id) {
			wp_die(__('An error occurred, please contact the site administrator: ', 'leaky-paywall') . get_bloginfo('admin_email'), __('Error', 'leaky-paywall'), array('response' => '401'));
		}

		$payment_intent_id = $_POST['payment-intent-id'];

		$gateway_data = array(
			'level_id'			=> $this->level_id,
			'subscriber_id' 	=> $customer_id,
			'subscriber_email' 	=> $this->email,
			'existing_customer' => $existing_customer,
			'price' 			=> $this->level_price,
			'description' 		=> $this->level_name,
			'payment_gateway' 	=> 'stripe',
			'payment_status' 	=> 'active', // will deaactivate with payment_intent.failed webhook if needed
			'interval' 			=> $this->length_unit,
			'interval_count' 	=> $this->length,
			'site' 				=> !empty($level['site']) ? $level['site'] : '',
			'plan' 				=> $this->plan_id,
			'recurring'			=> $this->recurring,
			'currency'			=> $this->currency,
			'payment_gateway_txn_id' => $payment_intent_id
		);

		do_action('leaky_paywall_stripe_signup', $gateway_data);

		return apply_filters('leaky_paywall_stripe_gateway_data', $gateway_data, $this, $customer_data);
	}

	/**
	 * Process incoming webhooks
	 *
	 * @since 4.0.0
	 */
	public function process_webhooks()
	{

		if (!isset($_GET['listener']) || strtolower($_GET['listener']) != 'stripe') {
			return;
		}

		$body = @file_get_contents('php://input');
		$stripe_event = json_decode($body);
		$settings = get_leaky_paywall_settings();
		$user = '';

		if ($stripe_event->livemode == false) {
			$mode = 'test';
		} else {
			$mode = 'live';
		}

		if (!isset($stripe_event->type)) {
			return;
		}

		$stripe_object = $stripe_event->data->object;

		leaky_paywall_log($stripe_object, 'stripe webhook - ' . $stripe_event->type);

		if (!empty($stripe_object->customer)) {
			$user = get_leaky_paywall_subscriber_by_subscriber_id($stripe_object->customer, $mode);
		}

		if (empty($user)) {
			return;
		}

		if (is_multisite_premium()) {
			if ($site_id = get_leaky_paywall_subscribers_site_id_by_subscriber_id($stripe_object->customer)) {
				$site = '_' . $site_id;
			}
		} else {
			$site = '';
		}

		//https://stripe.com/docs/api#event_types
		switch ($stripe_event->type) {

			case 'charge.succeeded':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
				$transaction_id = leaky_paywall_get_transaction_id_from_email($user->user_email);
				leaky_paywall_set_payment_transaction_id($transaction_id, $stripe_object->id);
				break;
			case 'charge.failed':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated');
				do_action('leaky_paywall_failed_payment', $user);
				break;
			case 'charge.refunded':
				if ($stripe_object->refunded)
					update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated');
				else
					update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated');
				break;
			case 'charge.dispute.created':
			case 'charge.dispute.updated':
			case 'charge.dispute.closed':
			case 'customer.created':
			case 'customer.updated':
			case 'customer.source.created':
			case 'invoice.created':
			case 'invoice.updated':
				break;
			case 'customer.deleted':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled');
				break;

			case 'invoice.payment_succeeded':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
				break;
			case 'invoice.paid':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
				break;

			case 'invoice.payment_failed':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'deactivated');
				do_action('leaky_paywall_failed_payment', $user);
				break;

			case 'customer.subscription.updated':
				$expires = date_i18n('Y-m-d 23:59:59', $stripe_object->current_period_end);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires);
				break;

			case 'customer.subscription.created':
				$expires = date_i18n('Y-m-d 23:59:59', $stripe_object->current_period_end);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires);
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
				break;

			case 'customer.subscription.deleted':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled');
				break;

			case 'payment_intent.canceled':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'canceled');
				break;

			case 'payment_intent.succeeded':
				update_user_meta($user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, 'active');
				break;

			default:
				break;
		};

		// create an action for each event fired by stripe
		$action = str_replace('.', '_', $stripe_event->type);
		do_action('leaky_paywall_stripe_' . $action, $user, $stripe_object);
	}

	/**
	 * Add credit card fields
	 *
	 * @since 4.0.0
	 */
	public function fields($level_id)
	{

		$level_id = is_numeric($level_id) ? $level_id : esc_html($_GET['level_id']);
		$level = get_leaky_paywall_subscription_level($level_id);

		if ($level['price'] == 0) {
			return;
		}

		$content = $this->stripe_elements($level_id);
		return $content;
	}

	public function stripe_elements($level_id)
	{

		$stripe_plan = '';
		$level = get_leaky_paywall_subscription_level($level_id);

		if ($level['price'] == 0) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		$stripe_price = number_format($level['price'], 2, '', '');

		$plan_args = array(
			'stripe_price'	=> $stripe_price,
			'currency'		=> leaky_paywall_get_currency(),
			'secret_key'	=> $this->secret_key
		);

		if (isset($level['recurring']) && 'on' == $level['recurring']) {
			$stripe_plan = leaky_paywall_get_stripe_plan($level, $level_id, $plan_args);
		}

		ob_start();
?>

		<div class="leaky-paywall-payment-method-container">

			<input id="payment_method_stripe" class="input-radio" name="payment_method" value="stripe" checked="checked" data-order_button_text="" type="radio">

			<label for="payment_method_stripe"> <?php _e('Credit Card', 'leaky-paywall'); ?> <img width="150" src="<?php echo LEAKY_PAYWALL_URL; ?>images/credit_card_logos_5.gif"></label>

		</div>

		<div class="leaky-paywall-card-details">

			<input type="hidden" id="plan-id" name="plan_id" value="<?php echo $stripe_plan ? $stripe_plan->id : ''; ?>" />
			<input type="hidden" id="stripe-customer-id" value="">
			<input type="hidden" id="payment-intent-client" value="">
			<input type="hidden" id="payment-intent-id" name="payment-intent-id" value="">

			<?php if ('yes' == $settings['enable_apple_pay']) { ?>
				<div id="payment-request-button">
					<!-- A Stripe Element will be inserted here. -->
				</div>
			<?php } ?>

			<?php
			if (!is_ssl()) {
				echo '<div class="leaky_paywall_message error"><p>' . __('This page is unsecured. Do not enter a real credit card number. Use this field only for testing purposes.', 'leaky-paywall') . '</p></div>';
			}
			?>


			<div class="form-row">
				<label for="card-element">
					<?php _e('Credit or debit card', 'leaky-paywall'); ?>
				</label>
				<div id="card-element">
					<!-- A Stripe Element will be inserted here. -->
				</div>

				<!-- Used to display form errors. -->
				<div id="card-errors" role="alert"></div>
				<div id="lp-card-errors"></div>
			</div>

		</div>

		<!-- stripe javascript -->

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

		if (empty($_POST['card_number'])) {
			leaky_paywall_errors()->add('missing_card_number', __('The card number you entered is invalid', 'issuem-leaky-paywall'), 'register');
		}
	}


	/**
	 * Load Stripe JS. Need to load it on every page for Stripe Radar rules.
	 *
	 * @since  4.0.0
	 */
	public function scripts()
	{

		wp_enqueue_script('stripe', 'https://js.stripe.com/v3/', array('jquery'));
	}
}
