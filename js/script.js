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
    });
  });
})(jQuery);
