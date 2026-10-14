(function ($) {
  $(document).ready(function () {
    var emailValid = false;
    var userValid = false;
    var passwordsMatch = true;

    var email = $("#email_address");
    var username = $("#username");
    var passwordField = $("#password");
    var passwordConfirmField = $("#confirm_password");

    passwordField.focus(function () {
      passwordsMatch = false;
      $(".password-error").remove();

      setSubmitBtnActive();
    });

    passwordConfirmField.focus(function () {
      passwordsMatch = false;
      $(".password-error").remove();

      setSubmitBtnActive();
    });

    passwordField.blur(function () {
      passwordsMatch = validatePasswords();
      setSubmitBtnActive();
    });

    passwordConfirmField.blur(function () {
      passwordsMatch = validatePasswords();
      setSubmitBtnActive();
    });

    email.focus(function () {
      emailValid = false;
      $(".email-error").remove();

      setSubmitBtnActive();
    });

    email.blur(function () {
      var data = {
        action: "leaky_paywall_validate_registration",
        email: email.val(),
        nonce: leaky_paywall_validate_ajax.register_nonce
      };

      $.post(leaky_paywall_validate_ajax.ajaxurl, data, function (resp) {
        if (resp.status == "error") {
          $(
            '<p class="leaky-paywall-input-error email-error">' +
              resp.message +
              "</p>"
          ).insertAfter("#email_address");
          emailValid = false;
        } else {
          emailValid = true;
        }

        setSubmitBtnActive();
      });
    });

    if (username.length > 0) {
      // username
      username.focus(function () {
        userValid = false;
        $(".username-error").remove();

        setSubmitBtnActive();
      });

      username.blur(function () {
        var data = {
          action: "leaky_paywall_validate_registration",
          username: username.val(),
          nonce: leaky_paywall_validate_ajax.register_nonce
        };

        $.post(leaky_paywall_validate_ajax.ajaxurl, data, function (resp) {
          if (resp.status == "error") {
            $(
              '<p class="leaky-paywall-input-error username-error">' +
                resp.message +
                "</p>"
            ).insertAfter("#username");
            userValid = false;
          } else {
            userValid = true;
          }
          setSubmitBtnActive();
        });
      });
    } else {
      // email is the username
      userValid = true;
    }

    function validatePasswords() {
      var pwMatch =
        !passwordField.val() ||
        !passwordConfirmField.val() ||
        passwordField.val() === passwordConfirmField.val();
      passwordsMatch = pwMatch;
      if (pwMatch) {
        $(".password-error").remove();
      } else {
        if (passwordField.val() && passwordConfirmField.val()) {
          $(
            '<p class="leaky-paywall-input-error password-error">' +
              leaky_paywall_validate_ajax.password_text +
              "</p>"
          ).insertAfter(passwordConfirmField);
        }
      }
      return pwMatch;
    }

    function setSubmitBtnActive() {
      if (userValid && emailValid && passwordsMatch) {
        $("#leaky-paywall-submit").prop("disabled", false);
        $("#leaky-paywall-registration-next").prop("disabled", false);
      } else {
        // $("#leaky-paywall-submit").prop("disabled", true);
        // $("#leaky-paywall-registration-next").prop("disabled", true);
      }
    }
  });

  // A page served from a page cache keeps the nonces it was rendered with,
  // and WordPress rejects them after 12 to 24 hours. When this copy is old
  // enough that they might have expired, fetch fresh ones before anyone
  // submits. A nonce lasts at least 12 hours, so a copy under 6 hours old
  // still has 6 left and makes no request.
  $(document).ready(function () {
    var config = window.leaky_paywall_validate_ajax || {};
    var created = parseInt(config.nonces_created, 10) || 0;
    var stripe = window.leaky_paywall_stripe_registration_ajax;
    var fields = $('input[name="leaky_paywall_register_nonce"]');

    if (!created || Date.now() / 1000 - created < 6 * 3600) {
      return;
    }

    // Only pages with the registration form: all three are sent from it.
    // The Stripe script is on every page, so it doesn't count.
    if (!fields.length) {
      return;
    }

    $.post(config.ajaxurl, { action: "leaky_paywall_refresh_nonces" }, function (resp) {
      if (!resp || !resp.success || !resp.data) {
        return;
      }

      if (resp.data.register) {
        fields.val(resp.data.register);
      }

      if (resp.data.register_ajax) {
        config.register_nonce = resp.data.register_ajax;
      }

      if (stripe && resp.data.tax_preview) {
        stripe.tax_preview_nonce = resp.data.tax_preview;
      }

      document.dispatchEvent(new CustomEvent("leaky_paywall_nonces_refreshed", { detail: resp.data }));
    });
  });
})(jQuery);
