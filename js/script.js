(function ($) {
  $(document).ready(function () {
    var stripe = Stripe(leaky_paywall_script_ajax.stripe_pk);

    $(".lp-stripe-checkout-button").click(function (e) {
      e.preventDefault();

      var level_id = $(this).data("level-id");

      var data = {
        action: "leaky_paywall_create_stripe_checkout_session",
        level_id: level_id,
      };

      $.post(leaky_paywall_script_ajax.ajaxurl, data, function (resp) {
        console.log(resp);

        if (resp.session_id) {
          return stripe.redirectToCheckout({ sessionId: resp.session_id });
        }
      });
    });

    // registration form stuff
    $("#leaky-paywall-registration-next").click(function () {
      console.log("validate data and create user");
      $(this).text("Processing... Please Wait");

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

            $("html, body")
              .stop(true, true)
              .animate(
                {
                  scrollTop: $("#leaky-paywall-payment-form").offset().top,
                },
                1000
              );

            $(".leaky-paywall-registration-payment-container").slideDown();
          }, 500);
        }
      });
    });
  });
})(jQuery);
