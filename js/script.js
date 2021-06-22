(function ($) {
  $(document).ready(function () {
    // registration form handler
    $("#leaky-paywall-registration-next").click(function () {
      $(this).text("Processing... Please Wait");
      $("#leaky-paywall-registration-errors").html("");

      const form_data = $("#leaky-paywall-payment-form").serialize();

      const data = {
        action: "leaky_paywall_process_user_registration_validation",
        form_data: form_data,
      };

      $.post(leaky_paywall_script_ajax.ajaxurl, data, function (resp) {
        if (resp.errors) {
          $.each(resp.errors, function (i, value) {
            console.log(value);
            $("#leaky-paywall-registration-errors").append(
              "<p class='leaky-paywall-registration-error'>" +
                value.message +
                "</p>"
            );
            $("#leaky-paywall-registration-next").text("Next");
            $("#leaky-paywall-registration-errors").show();

            $("html, body").animate(
              {
                scrollTop: $(".leaky-paywall-registration-error").offset().top,
              },
              1000
            );
          });
        } else {
          if (resp.pi_client) {
            $("#payment-intent-client").val(resp.pi_client);
            $("#payment-intent-id").val(resp.pi_id);
          } else {
            $("#stripe-customer-id").val(resp.customer_id);
          }

          setInterval(() => {
            $("#leaky-paywall-registration-errors").hide();
            $("#leaky-paywall-registration-next").remove();
            $(".leaky-paywall-registration-user-container").hide();
            $(".leaky-paywall-form-payment-setup-step").addClass("active");
            $(".leaky-paywall-form-account-setup-step").removeClass("active");

            $(".leaky-paywall-registration-payment-container").slideDown();
          }, 500);

          $("html, body").animate(
            {
              scrollTop: $(".leaky-paywall-form-steps").offset().top,
            },
            1000
          );
        }
      });
    }); // registration next click



    // only load on Stripe enabled payment gateways
    if ( $('#card-element').length > 0 ) {

      // stripe checkout
      // reset on page load
      localStorage.removeItem('latestInvoicePaymentIntentStatus');
      let stripe = Stripe(leaky_paywall_script_ajax.stripe_pk);

      let paymentRequest;
      let prButton;
      let currency = $('input[name="currency"]').val().toLowerCase();

      if ( 'yes' == leaky_paywall_script_ajax.apple_pay) {
        var amount = parseFloat($('input[name="level_price"]').val()) * 100;
        paymentRequest = stripe.paymentRequest({
          country: 'US',
          currency: currency,
          total: {
            label: $('input[name="description"]').val(),
            amount: Math.round(amount),
          },
          requestPayerName: true,
          requestPayerEmail: true,
        });
      }
     
      let elements = stripe.elements();

      if ( 'yes' == leaky_paywall_script_ajax.apple_pay) {
        prButton = elements.create('paymentRequestButton', {
          paymentRequest: paymentRequest,
        });
      }

      

      let style = {
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

      let card = elements.create('card', {
        style: style
      });

      card.mount('#card-element');

      if ( 'yes' == leaky_paywall_script_ajax.apple_pay ) {
        // Check the availability of the Payment Request API first.
        paymentRequest.canMakePayment().then(function(result) {
          if (result) {
            prButton.mount('#payment-request-button');
          } else {
            document.getElementById('payment-request-button').style.display = 'none';
            console.log('apple pay not available');
          }
        });

        paymentRequest.on('paymentmethod', async function(ev) {

          let data = new FormData();
          let level_id = $('#level-id').val();

          data.append('action', 'leaky_paywall_create_stripe_payment_intent');
          data.append('level_id', level_id);
          data.append('paymentMethodType', 'card');
          
          // create paymentIntent
          const {clientSecret} = await fetch(leaky_paywall_script_ajax.ajaxurl, {
            method: 'post',
            credentials: 'same-origin',
            body: data 
          }).then(r => r.json());
          console.log('client secret returned');

          const{error, paymentIntent} = await stripe.confirmCardPayment(
            clientSecret, {
              payment_method: ev.paymentMethod.id,
            },  {
              handleActions: false
            }
          )
          if(error) {
            ev.complete('fail');
            console.log('payment failed');
            return;
          }
          ev.complete('success');
          console.log(`Success: ${paymentIntent.id}`);
          if(paymentIntent.status === 'requires_action') {
            stripe.confirmCardPayment(clientSecret).then(function(result) {
              if (result.error) {
                console.log('payment failed again');
                // The payment failed -- ask your customer for a new payment method.
              } else {
                // The payment has succeeded.
                console.log('lp form submit apple pay success');
                let form$ = jQuery('#leaky-paywall-payment-form');
                form$.get(0).submit();
              }
            });
          } else {
            console.log('lp form submit apple pay success');
            let form$ = jQuery('#leaky-paywall-payment-form');
            form$.get(0).submit();
          }

        });


      }

      // Handle real-time validation errors from the card Element.
      card.on('change', function(event) {
        let displayError = document.getElementById('card-errors');
        if (event.error) {
          displayError.textContent = event.error.message;
        } else {
          displayError.textContent = '';
        }
      });

      // Handle form submission.
			let form = document.getElementById('leaky-paywall-payment-form');

      form.addEventListener('submit', function(event) {

        let method = $('#leaky-paywall-payment-form').find('input[name="payment_method"]:checked').val();

        if (method != 'stripe') {
          return;
        }

        event.preventDefault();

        console.log('lp form submit 1');

        let subButton = document.getElementById('leaky-paywall-submit');
        let firstName = $('input[name="first_name"]').val();
        let lastName = $('input[name="last_name"]').val();
        let clientSecret = $('#payment-intent-client').val();
        let isRecurring = $('input[name="recurring"]').val();
       

				subButton.disabled = true;
				subButton.innerHTML = 'Processing... Please Wait';

        // one time payment
        if ( isRecurring != 'on') {
          console.log('lp form submit one time');

          stripe.confirmCardPayment(clientSecret, {
            payment_method: {
              card: card,
              billing_details: {
                name: firstName + ' ' + lastName
              },
            },
            setup_future_usage: 'off_session'
          }).then(function(result) {
            console.log('lp form submit one time result');
            if (result.error) {
              // Show error to your customer (e.g., insufficient funds)
              console.log(result.error.message);
              $('#lp-card-errors').html('<p>' + result.error.message + '</p>');

              let subButton = document.getElementById('leaky-paywall-submit');
              subButton.disabled = false;
              subButton.innerHTML = 'Subscribe';

            } else {
              
              // The payment has been processed!
              if (result.paymentIntent.status === 'succeeded') {
                console.log('lp form submit one time success');

                let form$ = jQuery('#leaky-paywall-payment-form');

                form$.get(0).submit();

              }
            }
          });

        } // end one time payment

        if ( isRecurring == 'on') {
          console.log('lp form submit recurring');

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

          } // end createPaymentMethod

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
          } // end onSubscriptionComplete

          function showCardError(event) {
            console.log('show card error - event');
            console.log(event);
            console.log('show card error - event error message');

            let subButton = document.getElementById('leaky-paywall-submit');
            subButton.disabled = false;
            subButton.innerHTML = 'Subscribe';

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
          } // end showCardError

        } // end recurring payment processing


      }); // form submit

    }

    


  });
})(jQuery);
