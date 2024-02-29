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
})(jQuery);
