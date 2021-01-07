var $leaky_paywall_settings = jQuery.noConflict();

$leaky_paywall_settings(document).ready(function ($) {
  $("#leaky_paywall_default_restriction_options").on(
    "click",
    "input#add-restriction-row",
    function (event) {
      event.preventDefault();
      var data = {
        action: "issuem-leaky-paywall-add-new-restriction-row",
        "row-key": ++leaky_paywall_restriction_row_key,
      };
      $.post(ajaxurl, data, function (response) {
        $("td#issuem-leaky-paywall-restriction-rows table").append(response);
      });
    }
  );

  $(".leaky-paywall-subscription-level-row-header").click(function () {
    $(this)
      .next(".issuem-leaky-paywall-subscription-level-row-table")
      .slideToggle("fast", function () {});
    // .toggleClass("toggled");
    $(this).toggleClass("toggled");
  });

  $("#collapse-levels").click(function (e) {
    e.preventDefault();
    $(".issuem-leaky-paywall-subscription-level-row-table").each(function () {
      if (!$(this).hasClass("hidden")) {
        $(this).slideUp("fast");
      }
    });

    $(".leaky-paywall-subscription-level-row-header").each(function () {
      if (!$(this).hasClass("hidden")) {
        $(this).addClass("toggled");
      }
    });
  });

  $("#expand-levels").click(function (e) {
    e.preventDefault();
    $(".issuem-leaky-paywall-subscription-level-row-table").each(function () {
      if (!$(this).hasClass("hidden")) {
        $(this).slideDown("fast");
      }
    });

    $(".leaky-paywall-subscription-level-row-header").each(function () {
      if (!$(this).hasClass("hidden")) {
        $(this).removeClass("toggled");
      }
    });
  });

  $("#leaky_paywall_subscription_level_options").on(
    "click",
    "input#add-subscription-row-post-type",
    function (event) {
      event.preventDefault();
      var row_key = $(this).data("row-key");
      var select_post_key =
        "leaky_paywall_subscription_row_" + row_key + "_last_post_type_key";
      var data = {
        action: "issuem-leaky-paywall-add-new-subscription-row-post-type",
        "select-post-key": ++window[select_post_key],
        "row-key": row_key,
      };
      $.post(ajaxurl, data, function (response) {
        console.log(response);
        $(
          "#issuem-leaky-paywall-subsciption-row-" +
            row_key +
            "-post-types table"
        ).append(response);
      });
    }
  );

  $("#issuem-leaky-paywall-subscription-level-rows").on(
    "change",
    "select.subscription_length_type",
    function (event) {
      var parent = $(this).parent();
      if ("unlimited" == $(this).val()) {
        $(".interval_count", parent).data(
          "prev-value",
          $(".interval_count", parent).val()
        );
        $(".interval_div", parent).hide();
        $(".interval_count", parent).val("-1");
      } else {
        $(".interval_count", parent).val(
          $(".interval_count", parent).data("prev-value")
        );
        $(".interval_div", parent).show();
      }
    }
  );

  $("#issuem-leaky-paywall-subscription-level-rows").on(
    "change",
    ".issuem-leaky-paywall-row-post-type select.allowed_type",
    function (event) {
      var parent = $(this).parent();
      if ("unlimited" == $(this).val()) {
        $(".allowed_value", parent).data(
          "prev-value",
          $(".allowed_value", parent).val()
        );
        $(".allowed_value_div", parent).hide();
        $(".allowed_value", parent).val("-1");
      } else {
        $(".allowed_value", parent).val(
          $(".allowed_value", parent).data("prev-value")
        );
        $(".allowed_value_div", parent).show();
      }
    }
  );

  $("#leaky_paywall_default_restriction_options").on(
    "click",
    ".delete-restriction-row",
    function (event) {
      event.preventDefault();
      var parent = $(this).parents(".issuem-leaky-paywall-restriction-row");
      parent.slideUp("normal", function () {
        $(this).remove();
      });
    }
  );

  $("#issuem-leaky-paywall-subscription-level-rows").on(
    "click",
    ".delete-subscription-level",
    function (event) {
      event.preventDefault();

      if (window.confirm("Are you sure you want to delete this level?")) {
        var parent = $(this).parents(
          ".issuem-leaky-paywall-subscription-level-row-table"
        );
        parent.slideUp("normal", function () {
          $(this).hide();
          $(".deleted-subscription", this).val(1);
        });
        parent.prev(".leaky-paywall-subscription-level-row-header").hide();
      }
      
    }
  );

  $("#issuem-leaky-paywall-subscription-level-rows").on(
    "click",
    ".delete-post-type-row",
    function (event) {
      event.preventDefault();
      var parent = $(this)
        .parent()
        .parent(".issuem-leaky-paywall-row-post-type");
      parent.slideUp("normal", function () {
        $(this).remove();
      });
    }
  );

  $("#issuem-leaky-paywall-restriction-rows").on(
    "change",
    ".leaky-paywall-restriction-post-type",
    function () {
      // replace taxonomy select with loader
      console.log("here");
      var post_type = $(this).children("option:selected").val();
      var taxCell = $(this).parent().next();
      taxCell.append(
        '<div class="spinner" style="visibility: visible; float: left;"></div>'
      );
      taxCell.find("select").remove();

      // ajax call to find taxonomies and terms
      var data = {
        action: "leaky-paywall-get-restriction-row-post-type-taxonomies",
        post_type: post_type,
      };

      $.post(ajaxurl, data, function (response) {
        // build out the new select box
        taxCell.append(response);
        taxCell.find(".spinner").remove();
      });
    }
  );

  $(".issuem-leaky-paywall-subscription-level-row-table").on(
    "change",
    ".select_level_post_type",
    function () {
      // replace taxonomy select with loader
      var post_type = $(this).children("option:selected").val();
      var taxCell = $(this).parent().next();
      taxCell.append(
        '<div class="spinner" style="visibility: visible; float: left;"></div>'
      );
      taxCell.find("select").remove();

      // ajax call to find taxonomies and terms
      var data = {
        action: "leaky-paywall-get-restriction-row-post-type-taxonomies",
        post_type: post_type,
      };

      $.post(ajaxurl, data, function (response) {
        // build out the new select box
        taxCell.find("select").remove();
        taxCell.append(response);
        taxCell.find(".spinner").remove();
      });
    }
  );

  $("#enable_combined_restrictions").click(function () {
    if ($(this).is(":checked")) {
      $(".restriction-allowed-number-setting").css("display", "none");
      $(".allowed-number-helper-text").css("display", "block");
      $(".combined-restrictions-total-allowed").removeClass("hide-setting");
    } else {
      $(".restriction-allowed-number-setting").css("display", "block");
      $(".allowed-number-helper-text").css("display", "none");
      $(".combined-restrictions-total-allowed").addClass("hide-setting");
    }
  });

  $("#enable_js_cookie_restrictions").click(function () {
    if ($(this).is(":checked")) {
      $(".restriction-options-post-container").removeClass("hide-setting");
      $(".restriction-options-page-container").removeClass("hide-setting");
      $(".restriction-options-lead-in-elements").removeClass("hide-setting");
      $(".custom-excerpt-length").addClass("hide-setting");
    } else {
      $(".restriction-options-post-container").addClass("hide-setting");
      $(".restriction-options-page-container").addClass("hide-setting");
      $(".restriction-options-lead-in-elements").addClass("hide-setting");
      $(".custom-excerpt-length").removeClass("hide-setting");
    }
  });

  $(".stripe-recurring").click(function (e) {
    if ($(this).is(":checked")) {
      $(this).siblings("span").removeClass("hidden");
    } else {
      $(this).siblings("span").addClass("hidden");
    }
  });
});
