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

		<script>
			(function($) {

				$(document).ready(function() {

					var stripe = Stripe('<?php echo $this->publishable_key; ?>');

					<?php if ('yes' == $settings['enable_apple_pay']) { ?>
						var paymentRequest = stripe.paymentRequest({
							country: 'US',
							currency: '<?php echo strtolower(leaky_paywall_get_currency()); ?>',
							total: {
								label: '<?php echo $level['label']; ?>',
								amount: <?php echo $level['price'] * 100; ?>,
							},
							requestPayerName: true,
							requestPayerEmail: true,
						});
					<?php } ?>

					var elements = stripe.elements();



					<?php if ('yes' == $settings['enable_apple_pay']) { ?>
						var prButton = elements.create('paymentRequestButton', {
							paymentRequest: paymentRequest,
						});
					<?php } ?>

					var style = {
						base: {
							color: '#32325d',
							lineHeight: '18px',
							fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
							fontSmoothing: 'antialiased',
							fontSize: '16px',
							'::placeholder': {
								color: '#aab7c4'
							}
						},
						invalid: {
							color: '#fa755a',
							iconColor: '#fa755a'
						}
					};

					var card = elements.create('card', {
						style: style
					});

					card.mount('#card-element');

					<?php if ('yes' == $settings['enable_apple_pay']) { ?>
						// Check the availability of the Payment Request API first.
						paymentRequest.canMakePayment().then(function(result) {
							if (result) {
								prButton.mount('#payment-request-button');
							} else {
								document.getElementById('payment-request-button').style.display = 'none';
							}
						});

						paymentRequest.on('token', function(ev) {
							// Send the token to your server to charge it!
							fetch('/', {
									method: 'POST',
									body: JSON.stringify({
										token: ev.token.id
									}),
									headers: {
										'content-type': 'application/json'
									},
								})
								.then(function(response) {
									if (response.ok) {
										// Report to the browser that the payment was successful, prompting
										// it to close the browser payment interface.
										console.log('success');

										ev.complete('success');
										stripeTokenHandler(ev.token);
									} else {
										// Report to the browser that the payment failed, prompting it to
										// re-show the payment interface, or show an error message and close
										// the payment interface.
										console.log('fail');
										ev.complete('fail');
									}
								});
						});
					<?php } ?>


					// Handle real-time validation errors from the card Element.
					card.on('change', function(event) {
						var displayError = document.getElementById('card-errors');
						if (event.error) {
							displayError.textContent = event.error.message;
						} else {
							displayError.textContent = '';
						}
					});

					// Handle form submission.
					var form = document.getElementById('leaky-paywall-payment-form');

					form.addEventListener('submit', function(event) {

						var method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();

						if (method != 'stripe') {
							return;
						}

						event.preventDefault();

						let subButton = document.getElementById('leaky-paywall-submit');
						let firstName = $('input[name="first_name"]').val();
						let lastName = $('input[name="last_name"]').val();
						let clientSecret = $('#payment-intent-client').val();

						subButton.disabled = true;
						subButton.innerHTML = '<?php echo __('Processing... Please Wait', 'leaky-paywall') ?>';


						<?php
						if (isset($level['recurring']) && 'on' == $level['recurring']) {
							// recurring payment
						?>
							const latestInvoicePaymentIntentStatus = localStorage.getItem(
								'latestInvoicePaymentIntentStatus'
							);

							if (latestInvoicePaymentIntentStatus === 'requires_payment_method') {
								const invoiceId = localStorage.getItem('latestInvoiceId');
								const isPaymentRetry = true;
								// create new payment method & retry payment on invoice with new payment method
								createPaymentMethod({
									card,
									isPaymentRetry,
									invoiceId,
								});
							} else {
								// create new payment method & create subscription
								createPaymentMethod({
									card
								});
							}


							function createPaymentMethod({
								card,
								isPaymentRetry,
								invoiceId
							}) {
								// Set up payment method for recurring usage
								let billingName = firstName + ' ' + lastName;
								let customerId = $('#stripe-customer-id').val();
								let planId = $('#plan-id').val();


								stripe.createPaymentMethod({
										type: 'card',
										card: card,
										billing_details: {
											name: billingName,
										},
									})
									.then((result) => {
										if (result.error) {
											showCardError(result);
										} else {
											if (isPaymentRetry) {
												// Update the payment method and retry invoice payment
												retryInvoiceWithNewPaymentMethod({
													customerId: customerId,
													paymentMethodId: result.paymentMethod.id,
													invoiceId: invoiceId,
													planId: planId,
												});
											} else {
												// Create the subscription
												createSubscription({
													customerId: customerId,
													paymentMethodId: result.paymentMethod.id,
													planId: planId,
												});
											}
										}
									});

							}

							function retryInvoiceWithNewPaymentMethod({
								customerId,
								paymentMethodId,
								invoiceId,
								planId
							}) {

								let level_id = $('#level-id').val();
								let data = new FormData();
								const form_data = $("#leaky-paywall-payment-form").serialize();

								data.append('action', 'leaky_paywall_create_stripe_checkout_subscription');
								data.append('level_id', level_id);
								data.append('customerId', customerId);
								data.append('paymentMethodId', paymentMethodId);
								data.append('planId', planId);
								data.append('invoiceId', invoiceId);
								data.append('formData', form_data);

								return (
									fetch(leaky_paywall_script_ajax.ajaxurl, {
										method: 'post',
										credentials: 'same-origin',
										// headers: {
										// 	'Content-type': 'application/json',
										// },
										body: data
									})
									.then((response) => {
										return response.json();
									})
									// If the card is declined, display an error to the user.
									.then((result) => {
										if (result.error) {
											// The card had an error when trying to attach it to a customer.
											throw result;
										}
										console.log('retry invoice result');
										console.log(result);
										return result;
									})
									// Normalize the result to contain the object returned by Stripe.
									// Add the additional details we need.
									.then((result) => {
										return {
											// Use the Stripe 'object' property on the
											// returned result to understand what object is returned.
											invoice: result.invoice,
											paymentMethodId: paymentMethodId,
											planId: planId,
											isRetry: true,
										};
									})
									// Some payment methods require a customer to be on session
									// to complete the payment process. Check the status of the
									// payment intent to handle these actions.
									.then(handlePaymentThatRequiresCustomerAction)
									// No more actions required. Provision your service for the user.
									.then(onSubscriptionComplete)
									.catch((error) => {
										console.log('caught retry invoice error');
										console.log(error);
										// An error has happened. Display the failure to the user here.
										// We utilize the HTML element we created.
										showCardError(error);
									})
								);

							} // end retryInvoiceWithNewPaymentMethod

							function createSubscription({
								customerId,
								paymentMethodId,
								planId
							}) {

								let level_id = $('#level-id').val();
								let data = new FormData();
								const form_data = $("#leaky-paywall-payment-form").serialize();

								data.append('action', 'leaky_paywall_create_stripe_checkout_subscription');
								data.append('level_id', level_id);
								data.append('customerId', customerId);
								data.append('paymentMethodId', paymentMethodId);
								data.append('planId', planId);
								data.append('formData', form_data);

								return (
									fetch(leaky_paywall_script_ajax.ajaxurl, {
										method: 'post',
										credentials: 'same-origin',
										// headers: {
										// 	'Content-type': 'application/json',
										// },
										body: data
									})
									.then((response) => {
										return response.json();
									})
									// If the card is declined, display an error to the user.
									.then((result) => {
										if (result.error) {
											// The card had an error when trying to attach it to a customer.
											throw result;
										}
										console.log('result');
										console.log(result);
										return result;
									})
									// Normalize the result to contain the object returned by Stripe.
									// Add the additional details we need.
									.then((result) => {
										return {
											paymentMethodId: paymentMethodId,
											planId: planId,
											subscription: result.subscription,
										};
									})
									// Some payment methods require a customer to be on session
									// to complete the payment process. Check the status of the
									// payment intent to handle these actions.
									.then(handlePaymentThatRequiresCustomerAction)
									// If attaching this card to a Customer object succeeds,
									// but attempts to charge the customer fail, you
									// get a requires_payment_method error.
									.then(handleRequiresPaymentMethod)
									// No more actions required. Provision your service for the user.
									.then(onSubscriptionComplete)
									.catch((error) => {

										console.log('caught error');
										console.log(error);
										// An error has happened. Display the failure to the user here.
										// We utilize the HTML element we created.
										showCardError(error);
									})
								) // end return
							} // end createSubscription

							function handlePaymentThatRequiresCustomerAction({
								subscription,
								invoice,
								planId,
								paymentMethodId,
								isRetry,
							}) {
								if (subscription && subscription.status === 'active') {
									// Subscription is active, no customer actions required.
									return {
										subscription,
										planId,
										paymentMethodId
									};
								}
								if (subscription && subscription.status === 'trialing') {
									// Subscription is trialing, no customer actions required.
									return {
										subscription,
										planId,
										paymentMethodId
									};
								}

								console.log('handle payment that requires customer action');
								console.log(subscription);

								// If it's a first payment attempt, the payment intent is on the subscription latest invoice.
								// If it's a retry, the payment intent will be on the invoice itself.
								let paymentIntent = invoice ? invoice.payment_intent : subscription.latest_invoice.payment_intent;
								// let paymentIntent = subscription.latest_invoice.payment_intent;

								console.log('payment intent');
								console.log(paymentIntent);

								if (
									paymentIntent.status === 'requires_action' ||
									(isRetry === true && paymentIntent.status === 'requires_payment_method')
								) {
									return stripe
										.confirmCardPayment(paymentIntent.client_secret, {
											payment_method: paymentMethodId,
										})
										.then((result) => {
											if (result.error) {
												// Start code flow to handle updating the payment details.
												// Display error message in your UI.
												// The card was declined (i.e. insufficient funds, card has expired, etc).
												throw result;
											} else {
												if (result.paymentIntent.status === 'succeeded') {
													// Show a success message to your customer.
													// There's a risk of the customer closing the window before the callback.
													// We recommend setting up webhook endpoints later in this guide.
													return {
														planId: planId,
														subscription: subscription,
														invoice: invoice,
														paymentMethodId: paymentMethodId,
													};
												}
											}
										})
										.catch((error) => {
											showCardError(error);
										});
								} else {
									// No customer action needed.
									return {
										subscription,
										planId,
										paymentMethodId
									};
								}
							} // end handlePaymentThatRequiresCustomerAction

							function handleRequiresPaymentMethod({
								subscription,
								paymentMethodId,
								planId,
							}) {

								console.log('handle requires payment method');
								if (subscription.status === 'active' || subscription.status === 'trialing') {
									// subscription is active, no customer actions required.
									return {
										subscription,
										planId,
										paymentMethodId
									};
								} else if (
									subscription.latest_invoice.payment_intent.status ===
									'requires_payment_method'
								) {
									// Using localStorage to manage the state of the retry here,
									// feel free to replace with what you prefer.
									// Store the latest invoice ID and status.
									localStorage.setItem('latestInvoiceId', subscription.latest_invoice.id);
									localStorage.setItem(
										'latestInvoicePaymentIntentStatus',
										subscription.latest_invoice.payment_intent.status
									);
									throw {
										error: {
											message: 'Your card was declined.'
										}
									};
								} else {
									return {
										subscription,
										planId,
										paymentMethodId
									};
								}
							} // end handleRequiresPaymentMethod

							function onSubscriptionComplete(result) {
								console.log('sub complete');
								console.log(result);
								// Payment was successful.
								if (result.subscription.status === 'active' || result.subscription.status === 'trialing') {
									console.log('subscription complete!');
									var form$ = jQuery('#leaky-paywall-payment-form');

									form$.get(0).submit();
									// Change your UI to show a success message to your customer.
									// Call your backend to grant access to your service based on
									// `result.subscription.items.data[0].price.product` the customer subscribed to.
								} else {
									var form$ = jQuery('#leaky-paywall-payment-form');
									form$.get(0).submit();
								}
							}

							function showCardError(event) {
								console.log('show card error - event');
								console.log(event);
								console.log('show card error - event error message');

								let subButton = document.getElementById('leaky-paywall-submit');
								subButton.disabled = false;
								subButton.innerHTML = '<?php echo __('Subscribe', 'leaky-paywall') ?>';

								let displayError = document.getElementById('card-errors');
								if (event.error) {
									if (event.error.message) {
										displayError.textContent = event.error.message;
									} else {
										displayError.textContent = event.error.error.message;
									}

								} else {
									displayError.textContent = 'There was an error with your payment. Please try again.';
								}
							}



						<?php
						} else {
							// one time payment
						?>
							stripe.confirmCardPayment(clientSecret, {
								payment_method: {
									card: card,
									billing_details: {
										name: firstName + ' ' + lastName
									},
								},
								setup_future_usage: 'off_session'
							}).then(function(result) {
								if (result.error) {
									// Show error to your customer (e.g., insufficient funds)
									console.log(result.error.message);
									$('#lp-card-errors').html('<p>' + result.error.message + '</p>');

									let subButton = document.getElementById('leaky-paywall-submit');
									subButton.disabled = false;
									subButton.innerHTML = '<?php echo __('Subscribe', 'leaky-paywall') ?>';

								} else {
									// The payment has been processed!
									if (result.paymentIntent.status === 'succeeded') {
										console.log('success');

										var form$ = jQuery('#leaky-paywall-payment-form');

										form$.get(0).submit();

									}
								}
							});

						<?php
						} ?>


					});

				});


			})(jQuery);
		</script>

		<style>
			.StripeElement {
				background-color: white;
				height: auto;
				padding: 10px 12px;
				border-radius: 4px;
				border: 1px solid transparent;
				box-shadow: 0 1px 3px 0 #e6ebf1;
				-webkit-transition: box-shadow 150ms ease;
				transition: box-shadow 150ms ease;
			}

			.StripeElement--focus {
				box-shadow: 0 1px 3px 0 #cfd7df;
			}

			.StripeElement--invalid {
				border-color: #fa755a;
			}

			.StripeElement--webkit-autofill {
				background-color: #fefde5 !important;
			}
		</style>

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
