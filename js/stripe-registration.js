( function( $ )  {

	$(document).ready( function() {

        const stripe = Stripe(leaky_paywall_stripe_registration_ajax.stripe_pk);

        let elements;

        // Stripe Checkout
        $('#checkout').click(function(e) {
            e.preventDefault();
            $(this).text(leaky_paywall_stripe_registration_ajax.continue_text);
            $("#leaky-paywall-registration-errors").html("");
            validateUserData();
        });

        // one time and recurring
        $("#leaky-paywall-registration-next").click(function () {
            $(this).text(leaky_paywall_stripe_registration_ajax.continue_text);
            $("#leaky-paywall-registration-errors").html("");
            validateUserData();
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

                console.log('response:');
                console.log(resp);

                if (resp.errors) {
                    $.each(resp.errors, function (i, value) {
                        console.log(value);
                        $("#leaky-paywall-registration-errors").append(
                        "<p class='leaky-paywall-registration-error'>" +
                            value.message +
                            "</p>"
                        );
                        $("#leaky-paywall-registration-next").text( leaky_paywall_stripe_registration_ajax.next_text );
                        $("#leaky-paywall-registration-errors").show();

                        $("html, body").animate(
                        {
                            scrollTop: $(".leaky-paywall-registration-error").offset().top,
                        },
                        1000
                        );
                    });

                } else {

                    if ( resp.session_id ) {
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

        function buildPaymentForm( clientSecret ) {

            elements = stripe.elements({ clientSecret });

            const paymentElementOptions = {
                layout: "tabs",
            };

            const paymentElement = elements.create("payment", paymentElementOptions);
            paymentElement.mount("#payment-element");

            if ( 'on' == leaky_paywall_stripe_registration_ajax.billing_address ) {
                // Create and mount the Address Element in billing mode
                const addressElement = elements.create("address", {
                    mode: "billing",
                });

                addressElement.mount("#address-element");
            }

        }

        function showPaymentForm() {

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


        async function handleSubmit(e) {
            e.preventDefault();

            console.log('lp form submit 1');

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
                    console.log('submit form 1');
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
                        console.log('submit form 2');
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

            console.log('lp submit form after confirm payment');


            // This point will only be reached if there is an immediate error when
            // confirming the payment. Otherwise, your customer will be redirected to
            // your `return_url`. For some payment methods like iDEAL, your customer will
            // be redirected to an intermediate site first to authorize the payment, then
            // redirected to the `return_url`.




          //  setLoading(false);
        }

        function showMessage(messageText) {
            const messageContainer = document.querySelector("#payment-message");

            console.log('error: ' + messageText);
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
