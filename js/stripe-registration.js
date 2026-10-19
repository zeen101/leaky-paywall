( function( $ )  {

	$(document).ready( function() {

        if ( ! leaky_paywall_stripe_registration_ajax.stripe_pk ) {
            return;
        }

        const messages = leaky_paywall_stripe_registration_ajax.messages || {};

        // Bail cleanly if Stripe.js was blocked. This happens when a consent
        // management platform (Cookiebot, OneTrust, Iubenda, etc.) auto-blocks
        // https://js.stripe.com/v3/ because the visitor didn't consent to the
        // category the CMP classified Stripe under. Without this guard the
        // form throws "Stripe is not defined" and the Subscribe button appears
        // broken — user has no idea their consent choices are the reason.
        //
        // Publisher-side fix: mark Stripe as "Necessary" in the consent tool
        // (Stripe processes the transaction the visitor is trying to make,
        // which fits the Necessary category under GDPR). We surface an
        // in-form message here so the visitor knows what to do.
        if ( typeof Stripe === 'undefined' ) {
            var $errors = $('#leaky-paywall-registration-errors');
            if ( $errors.length ) {
                $errors.html(
                    $('<div class="leaky_paywall_message error"></div>').text( message('stripe_blocked') )
                );
            }
            // Also disable the submit buttons so the user doesn't repeatedly
            // click a form that can't submit.
            $('#checkout, #leaky-paywall-registration-next').prop('disabled', true);
            return;
        }

        let stripe = Stripe(leaky_paywall_stripe_registration_ajax.stripe_pk);

        if ( leaky_paywall_stripe_registration_ajax.client_id ) {
            stripe = Stripe(leaky_paywall_stripe_registration_ajax.stripe_pk, { stripeAccount: leaky_paywall_stripe_registration_ajax.client_id});
        }

        let elements;
        let billingAddress = null;
        let isSubmitting = false;

        // Set while the step 1 request is out. A double click, or Enter
        // pressed while it runs, would otherwise send it again and create a
        // second Stripe customer and subscription.
        let isValidating = false;

        // Stripe Checkout
        $('#checkout').click(function(e) {
            e.preventDefault();
            validateUserData();
        });

        // one time and recurring
        $("#leaky-paywall-registration-next").click(function () {
            validateUserData();
        });

        // Intercept Enter key during step 1 (account setup) — prevents native form submission
        // which would bypass AJAX validation and fail server-side with no payment intent.
        document.querySelector("#leaky-paywall-payment-form") && document.querySelector("#leaky-paywall-payment-form").addEventListener("submit", function(e) {
            if ($(".leaky-paywall-form-account-setup-step").hasClass("active")) {
                e.preventDefault();
                validateUserData();
            }
        });

        // The strings arrive HTML-escaped from esc_html__(), and are shown
        // with .text(), so turn entities back into characters first.
        function message( key ) {
            const decoder = document.createElement('textarea');
            decoder.innerHTML = messages[key] || messages.unexpected || '';
            return decoder.value;
        }

        // Step 1 buttons show "Processing..." while the request runs. Their
        // own label is kept to put back afterwards, since it can be "Next",
        // "Continue to Payment" or "Change Subscription".
        function setAccountButtonsBusy() {
            $('#leaky-paywall-registration-next, #checkout').each(function () {
                if ( undefined === $(this).data('lp-label') ) {
                    $(this).data('lp-label', $(this).text());
                }
                $(this).text(leaky_paywall_stripe_registration_ajax.continue_text).prop('disabled', true);
            });
        }

        function resetAccountButtons() {
            isValidating = false;
            $('#leaky-paywall-registration-next, #checkout').each(function () {
                const label = $(this).data('lp-label');
                $(this).text( undefined === label ? leaky_paywall_stripe_registration_ajax.next_text : label ).prop('disabled', false);
            });
        }

        function showAccountErrors( $messages ) {
            $("#leaky-paywall-registration-errors").append( $messages ).show();
            resetAccountButtons();

            // Once, after all the errors are added. Inside a loop each error
            // queued its own one-second scroll and locked the page.
            $("html, body").animate(
            {
                scrollTop: $(".leaky-paywall-registration-error").offset().top,
            },
            1000
            );
        }

        // Step 1 failed somewhere the server never saw or never answered
        // properly, so tell the reader and write it to the debug log.
        function accountStepFailed( text, reason, details ) {
            showAccountErrors( $("<p class='leaky-paywall-registration-error'></p>").text( text ) );
            reportError( $('#checkout').length ? 'checkout' : 'account', reason, details );
        }

        // Response bodies can be a whole HTML page, such as a firewall
        // challenge. Keep the readable part, short enough for one log line.
        function responseSnippet( text ) {
            return String( text || '' )
                .replace(/<(script|style)[\s\S]*?<\/\1>/gi, ' ')
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .substring(0, 300);
        }

        function reportError( stage, reason, details ) {
            if ( ! leaky_paywall_stripe_registration_ajax.error_nonce ) {
                return;
            }

            $.post(leaky_paywall_stripe_registration_ajax.ajaxurl, $.extend({
                action: 'leaky_paywall_report_checkout_error',
                nonce: leaky_paywall_stripe_registration_ajax.error_nonce,
                stage: stage,
                reason: reason,
                email: $('input[name="email_address"]').val() || '',
                level_id: $('input[name="level_id"]').val() || '',
                customer_id: $('#stripe-customer-id').val() || '',
                intent_id: $('#payment-intent-id').val() || '',
                page: window.location.origin + window.location.pathname
            }, details || {}));
        }

        function validateUserData() {

            if ( isValidating ) {
                return;
            }

            isValidating = true;
            setAccountButtonsBusy();

            $("#leaky-paywall-registration-errors").html("");

            const form_data = $("#leaky-paywall-payment-form").serialize();

            const data = {
                action: "leaky_paywall_process_user_registration_validation",
                form_data: form_data,
                nonce: leaky_paywall_stripe_registration_ajax.register_nonce
            };

            $.ajax({
                url: leaky_paywall_stripe_registration_ajax.ajaxurl,
                type: 'POST',
                data: data,
                dataType: 'json',
                timeout: parseInt(leaky_paywall_stripe_registration_ajax.request_timeout, 10) || 45000
            }).done(function (resp) {

                if ( ! resp || 'object' !== typeof resp ) {
                    accountStepFailed( message('unexpected'), 'unrecognized', { detail: responseSnippet( JSON.stringify( resp ) ) } );
                    return;
                }

                if (resp.errors) {
                    let $messages = $();

                    $.each(resp.errors, function (i, value) {
                        $messages = $messages.add(
                            "<p class='leaky-paywall-registration-error'>" +
                            value.message +
                            "</p>"
                        );
                    });

                    showAccountErrors( $messages );

                } else if ( resp.subscription_updated ) {
                    // Existing subscription was updated to new plan, submit form directly
                    let form$ = jQuery('#leaky-paywall-payment-form');
                    form$.get(0).submit();
                } else if ( resp.session_id ) {
                    // stripe checkout
                    stripe.redirectToCheckout({
                        sessionId: resp.session_id
                    }).then(function (result) {
                        if ( result && result.error ) {
                            accountStepFailed( result.error.message || message('unexpected'), 'stripe', { detail: responseSnippet( result.error.type + ' ' + ( result.error.code || '' ) + ': ' + result.error.message ) } );
                        }
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

                } else {
                    // A reply none of the steps above can use. It used to
                    // leave the button on "Processing..." for good.
                    accountStepFailed( message('unexpected'), 'unrecognized', { detail: responseSnippet( JSON.stringify( resp ) ) } );
                }

            }).fail(function (xhr, status) {

                // Timed out, refused, blocked by a firewall, or answered with
                // something that isn't JSON (a PHP notice ahead of it, or an
                // HTML page). None of these reach the success handler.
                if ( 'timeout' === status ) {
                    accountStepFailed( message('timeout'), 'timeout' );
                } else if ( 'parsererror' === status ) {
                    accountStepFailed( message('unexpected'), 'parse', { http_status: xhr.status, detail: responseSnippet( xhr.responseText ) } );
                } else {
                    accountStepFailed( message('request_failed'), 'request', { http_status: xhr.status, detail: responseSnippet( xhr.responseText ) } );
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

            // Re-entry guard: if a submission is already in flight, ignore further submits
            // (handles rapid double-click / Enter-spam where the disabled attribute isn't
            // applied between events).
            if ( isSubmitting ) {
                return;
            }
            isSubmitting = true;

            let subButton = document.getElementById('leaky-paywall-submit');
            if ( subButton ) {
                if ( undefined === subButton.dataset.lpLabel ) {
                    subButton.dataset.lpLabel = subButton.innerHTML;
                }
                subButton.disabled = true;
                subButton.innerHTML = leaky_paywall_stripe_registration_ajax.continue_text;
            }

            let emailAddress = $('input[name="email_address"]').val();
            let isTrial = $('input[name="is_trial"]').val();
            let paymentMethod = $('input[name="payment_method"]:checked').val();

            if ( paymentMethod != 'stripe' ) {
                let form$ = jQuery('#leaky-paywall-payment-form');
                form$.get(0).submit();
                return;
            }

            // Stripe.js rejects instead of returning an error when it can't
            // reach Stripe at all. Without the catch the button stayed on
            // "Processing..." and every later click was ignored.
            try {

                if ( isTrial ) {
                    const { error } = await stripe.confirmSetup({
                        elements,
                        confirmParams: {
                            return_url: leaky_paywall_stripe_registration_ajax.redirect_url,
                        },
                        redirect: 'if_required'
                    });

                    if ( error ) {
                        paymentFailed( error );
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

                    if ( error ) {
                        paymentFailed( error );
                    } else if ( paymentIntent && ( paymentIntent.status === 'succeeded' || paymentIntent.status === 'processing' ) ) {
                        // 'processing' is the success state for ACH (us_bank_account) — the bank
                        // transfer is in flight but won't settle for 3-5 business days. Treat it
                        // as a success so the subscriber lands on the post-signup page (where the
                        // ACH-pending modal can fire) instead of being silently dropped here.
                        let form$ = jQuery('#leaky-paywall-payment-form');
                        form$.get(0).submit();
                    } else {
                        // No error, but not paid either, such as a bank check
                        // the reader closed. This used to reset the button with
                        // no message.
                        showMessage( message('payment_incomplete') );
                        reportError( 'payment', 'incomplete', { detail: 'payment intent status: ' + ( paymentIntent ? paymentIntent.status : 'none' ) } );
                        resetSubButton();
                    }
                }

            } catch ( err ) {
                showMessage( message('unexpected') );
                reportError( 'payment', 'exception', { detail: responseSnippet( err && err.message ? err.message : String( err ) ) } );
                resetSubButton();
            }

            // This point will only be reached if there is an immediate error when
            // confirming the payment. Otherwise, your customer will be redirected to
            // your `return_url`. For some payment methods like iDEAL, your customer will
            // be redirected to an intermediate site first to authorize the payment, then
            // redirected to the `return_url`.
        }

        function paymentFailed( error ) {
            // Stripe's own message is written for the reader for card problems,
            // form validation and a failed bank check. Anything else is ours
            // to word.
            if ( error.type === "card_error" || error.type === "validation_error" || error.code === "payment_intent_authentication_failure" ) {
                showMessage( error.message );
            } else {
                showMessage( message('unexpected') );
            }

            // An unfinished card number is the reader still typing, not a
            // failure worth a log entry.
            if ( error.type !== "validation_error" ) {
                reportError( 'payment', 'stripe', { detail: responseSnippet( error.type + ' ' + ( error.code || '' ) + ' ' + ( error.decline_code || '' ) + ': ' + error.message ) } );
            }

            resetSubButton();
        }

        function showMessage(messageText) {
            const messageContainer = document.querySelector("#payment-message");

            if ( ! messageContainer ) {
                return;
            }

            messageContainer.classList.remove("hidden");
            messageContainer.textContent = messageText;

            setTimeout(function () {
                messageContainer.classList.add("hidden");
                messageContainer.textContent = "";
            }, 7000);
        }

        function resetSubButton() {
            isSubmitting = false;
            let subButton = document.getElementById('leaky-paywall-submit');
            if ( subButton ) {
                subButton.disabled = false;
                subButton.innerHTML = subButton.dataset.lpLabel || 'Subscribe';
            }
        }


	}); // doc ready

})( jQuery );
