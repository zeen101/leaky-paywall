( function( $ )  {

	$(document).ready( function() {

        let stripe = Stripe(leaky_paywall_stripe_registration_ajax.stripe_pk);

        if ( leaky_paywall_stripe_registration_ajax.client_id ) {
            stripe = Stripe(leaky_paywall_stripe_registration_ajax.stripe_pk, { stripeAccount: leaky_paywall_stripe_registration_ajax.client_id});
        }

        let elements;
        let billingAddress = null;

        // Stripe Checkout
        $('#checkout').click(function(e) {
            e.preventDefault();
            $(this).text(leaky_paywall_stripe_registration_ajax.continue_text);
            $("#leaky-paywall-registration-errors").html("");
            validateUserData();
        });

        // one time and recurring
        $("#leaky-paywall-registration-next").click(function () {
            $(this).text(leaky_paywall_stripe_registration_ajax.continue_text).prop('disabled', true);
            $("#leaky-paywall-registration-errors").html("");
            validateUserData();
        });

        // Intercept Enter key during step 1 (account setup) — prevents native form submission
        // which would bypass AJAX validation and fail server-side with no payment intent.
        document.querySelector("#leaky-paywall-payment-form") && document.querySelector("#leaky-paywall-payment-form").addEventListener("submit", function(e) {
            if ($(".leaky-paywall-form-account-setup-step").hasClass("active")) {
                e.preventDefault();
                $("#leaky-paywall-registration-next").text(leaky_paywall_stripe_registration_ajax.continue_text);
                $("#leaky-paywall-registration-errors").html("");
                validateUserData();
            }
        });


        function validateUserData() {

            $("#leaky-paywall-registration-errors").html("");

            const form_data = $("#leaky-paywall-payment-form").serialize();

            const data = {
                action: "leaky_paywall_process_user_registration_validation",
                form_data: form_data,
                nonce: leaky_paywall_stripe_registration_ajax.register_nonce
            };

            $.post(leaky_paywall_stripe_registration_ajax.ajaxurl, data, function (resp) {

                if (resp.errors) {
                    $.each(resp.errors, function (i, value) {
                        $("#leaky-paywall-registration-errors").append(
                        "<p class='leaky-paywall-registration-error'>" +
                            value.message +
                            "</p>"
                        );
                        $("#leaky-paywall-registration-next").text( leaky_paywall_stripe_registration_ajax.next_text ).prop('disabled', false);
                        $("#leaky-paywall-registration-errors").show();

                        $("html, body").animate(
                        {
                            scrollTop: $(".leaky-paywall-registration-error").offset().top,
                        },
                        1000
                        );
                    });

                } else {

                    if ( resp.subscription_updated ) {
                        // Existing subscription was updated to new plan, submit form directly
                        let form$ = jQuery('#leaky-paywall-payment-form');
                        form$.get(0).submit();
                    } else if ( resp.session_id ) {
                        // stripe checkout
                        stripe.redirectToCheckout({
                            sessionId: resp.session_id
                        });
                    } else if (resp.pi_client) {
                        // one time payment
                        $("#payment-intent-client").val(resp.pi_client);
                        $("#payment-intent-id").val(resp.pi_id);

                        buildPaymentForm(resp.pi_client);
                        showPaymentForm();

                          // Handle form submission.
                        document.querySelector("#leaky-paywall-payment-form").addEventListener("submit", handleSubmit);

                    } else if (resp.deferred_tax) {
                        // subscription with automatic tax — defer subscription creation until address is entered
                        $("#stripe-customer-id").val(resp.customer_id);
                        buildAddressOnly();
                        showPaymentForm();

                    } else if (resp.customer_id) {
                        // subscription
                        $("#stripe-customer-id").val(resp.customer_id);

                        buildPaymentForm(resp.client_secret);
                        showPaymentForm();

                          // Handle form submission.
                        document.querySelector("#leaky-paywall-payment-form").addEventListener("submit", handleSubmit);

                    }

                }

            });

        }; // end validate user data

        let addressElementMounted = false;

        function mountAddressElement( elementsInstance ) {
            const addressElement = elementsInstance.create("address", {
                mode: "billing",
            });

            addressElement.mount("#address-element");
            addressElementMounted = true;

            addressElement.on('change', (event) => {
                if (event.complete) {
                    billingAddress = event.value;
                    $('input[name="lp_billing_name"]').val(billingAddress.name || '');
                    $('input[name="lp_billing_line1"]').val(billingAddress.address.line1 || '');
                    $('input[name="lp_billing_line2"]').val(billingAddress.address.line2 || '');
                    $('input[name="lp_billing_city"]').val(billingAddress.address.city || '');
                    $('input[name="lp_billing_state"]').val(billingAddress.address.state || '');
                    $('input[name="lp_billing_postal_code"]').val(billingAddress.address.postal_code || '');
                    $('input[name="lp_billing_country"]').val(billingAddress.address.country || '');

                    if ( 'on' === leaky_paywall_stripe_registration_ajax.automatic_tax_enabled ) {
                        fetchTaxPreview(billingAddress.address);
                    }
                }
            });
        }

        function buildAddressOnly() {
            // For deferred tax flow: mount only the address element first, without a clientSecret.
            var standaloneElements = stripe.elements();
            mountAddressElement( standaloneElements );
        }

        function buildPaymentForm( clientSecret ) {

            elements = stripe.elements({ clientSecret });

            const paymentElementOptions = {
                layout: "tabs",
            };

            const paymentElement = elements.create("payment", paymentElementOptions);

            // Temporarily lock scroll position while Stripe Element mounts to prevent layout shift.
            var scrollPos = $(window).scrollTop();
            paymentElement.mount("#payment-element");
            $(window).scrollTop(scrollPos);

            if ( 'on' == leaky_paywall_stripe_registration_ajax.billing_address && !addressElementMounted ) {
                mountAddressElement( elements );
            }

        }

        function showPaymentForm() {

            setTimeout(() => {
                $("#leaky-paywall-registration-errors").hide();
                $("#leaky-paywall-registration-next").remove();
                $(".leaky-paywall-registration-user-container").hide();
                $(".leaky-paywall-form-payment-setup-step").addClass("active");
                $(".leaky-paywall-form-account-setup-step").removeClass("active");

                $(".leaky-paywall-registration-payment-container").slideDown();

                if ( 'on' === leaky_paywall_stripe_registration_ajax.automatic_tax_enabled ) {
                    showInitialOrderSummary();
                }

                $("html, body").animate(
                    {
                    scrollTop: $(".leaky-paywall-form-steps").offset().top,
                    },
                    1000
                );
            }, 500);

        }

        function showInitialOrderSummary() {
            var price = $('input[name="level_price"]').val();
            var currency = $('input[name="currency"]').val() || 'USD';
            var formatter = formatCurrency(currency);
            var subtotal = Math.round(parseFloat(price) * 100);

            $('#lp-order-subtotal-amount').text(formatter(subtotal));
            $('#lp-order-tax-amount').text('—').addClass('lp-order-pending');
            $('#lp-order-total-amount').text('—');
            $('#lp-order-summary').slideDown();
        }

        var taxPreviewRequest = null;

        function fetchTaxPreview(address) {
            var levelId = $('input[name="level_id"]').val();
            var customerId = $('#stripe-customer-id').val() || '';

            // Abort any in-flight request.
            if ( taxPreviewRequest ) {
                taxPreviewRequest.abort();
            }

            $('#lp-order-tax-amount').text('Calculating...').addClass('lp-order-pending');
            $('#leaky-paywall-submit').prop('disabled', true);

            taxPreviewRequest = $.ajax({
                url: leaky_paywall_stripe_registration_ajax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'leaky_paywall_stripe_tax_preview',
                    nonce: leaky_paywall_stripe_registration_ajax.tax_preview_nonce,
                    level_id: levelId,
                    customer_id: customerId,
                    line1: address.line1 || '',
                    city: address.city || '',
                    state: address.state || '',
                    postal_code: address.postal_code || '',
                    country: address.country || ''
                },
                success: function(response) {
                    jQuery('#leaky-paywall-submit').prop('disabled', false);
                    if (response.success) {
                        var data = response.data;
                        var formatter = formatCurrency(data.currency);
                        jQuery('#lp-order-subtotal-amount').text(formatter(data.subtotal));
                        jQuery('#lp-order-tax-amount').text(formatter(data.tax)).removeClass('lp-order-pending');
                        jQuery('#lp-order-total-amount').text(formatter(data.total));

                        // Build payment form now that subscription is created with tax.
                        if (data.client_secret && !jQuery('#payment-element .StripeElement, #payment-element iframe').length) {
                            buildPaymentForm(data.client_secret);
                            document.querySelector("#leaky-paywall-payment-form").addEventListener("submit", handleSubmit);
                        }
                    } else {
                        jQuery('#lp-order-tax-amount').text('$0.00').removeClass('lp-order-pending');
                    }
                },
                error: function(xhr, status, err) {
                    jQuery('#leaky-paywall-submit').prop('disabled', false);
                    jQuery('#lp-order-tax-amount').text('$0.00').removeClass('lp-order-pending');
                }
            });
        }

        function formatCurrency(currency) {
            return function(amountInCents) {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: currency
                }).format(amountInCents / 100);
            };
        }


        async function handleSubmit(e) {
            e.preventDefault();

            let subButton = document.getElementById('leaky-paywall-submit');
            let emailAddress = $('input[name="email_address"]').val();
            let isTrial = $('input[name="is_trial"]').val();
            let paymentMethod = $('input[name="payment_method"]:checked').val();

            if ( paymentMethod != 'stripe' ) {
                let form$ = jQuery('#leaky-paywall-payment-form');
                form$.get(0).submit();
                return;
            }

            subButton.disabled = true;
            subButton.innerHTML = leaky_paywall_stripe_registration_ajax.continue_text;

            if ( isTrial ) {
                const { error } = await stripe.confirmSetup({
                    elements,
                    confirmParams: {
                        return_url: leaky_paywall_stripe_registration_ajax.redirect_url,
                    },
                    redirect: 'if_required'
                });

                 if ( error ) {
                    if (error.type === "card_error" || error.type === "validation_error") {
                        showMessage(error.message);
                    } else {
                        showMessage("An unexpected error occurred.");
                    }
                    resetSubButton();
                } else {
                    let form$ = jQuery('#leaky-paywall-payment-form');
                    form$.get(0).submit();
                }

            } else {
                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        // Make sure to change this to your payment completion page
                        return_url: leaky_paywall_stripe_registration_ajax.redirect_url,
                        receipt_email: emailAddress,
                    },
                    redirect: 'if_required'
                });

                if (paymentIntent && paymentIntent.id) {

                    if ( paymentIntent.status == 'succeeded') {
                        let form$ = jQuery('#leaky-paywall-payment-form');
                        form$.get(0).submit();
                    } else {
                        resetSubButton();
                    }

                }

                 if ( error ) {
                    if (error.type === "card_error" || error.type === "validation_error") {
                        showMessage(error.message);
                    } else {
                        showMessage("An unexpected error occurred.");
                    }
                    resetSubButton();
                }
            }

            // This point will only be reached if there is an immediate error when
            // confirming the payment. Otherwise, your customer will be redirected to
            // your `return_url`. For some payment methods like iDEAL, your customer will
            // be redirected to an intermediate site first to authorize the payment, then
            // redirected to the `return_url`.




          //  setLoading(false);
        }

        function showMessage(messageText) {
            const messageContainer = document.querySelector("#payment-message");

            messageContainer.classList.remove("hidden");
            messageContainer.textContent = messageText;

            setTimeout(function () {
                messageContainer.classList.add("hidden");
                messageContainer.textContent = "";
            }, 7000);
        }

        function resetSubButton() {
            let subButton = document.getElementById('leaky-paywall-submit');
            subButton.disabled = false;
            subButton.innerHTML = 'Subscribe';
        }


	}); // doc ready

})( jQuery );
