(function ($) {

  function getCurrentPostIdFromBody() {
    var nag_loc = '';
    var bodyClasses = ($('body').attr('class') || '').split(' ');

    $.each(bodyClasses, function(i, value) {

      if ( !value.search('postid' ) ) {

        var classArray = value.split('-');

        var post_id = parseInt( classArray[1] );

        if ( post_id > 0 ) {

          nag_loc = post_id;

        }

      }

      // for pages
      if ( !value.search('page-id' ) ) {

        var classArray = value.split('-');
        var post_id = parseInt( classArray[2] );

        if ( post_id > 0 ) {

          nag_loc = post_id;

        }

      }

    });

    return nag_loc;
  }

  function storeNagLocationThenNavigate(url) {
    var data = {
      action: 'leaky_paywall_store_nag_location',
      post_id: getCurrentPostIdFromBody()
    };

    $.get(leaky_paywall_script_ajax.ajaxurl, data, function(resp) {
      window.location.href = url;
    });
  }

  $(document).ready(function () {


    $('.lpshowlogin').click(function(e) {
      e.preventDefault();
      $('.leaky-paywall-form-login').slideToggle();
    });


    $(document).on('click', '.leaky_paywall_message_wrap a', function(e) {
      e.preventDefault();
      storeNagLocationThenNavigate($(this).attr('href'));
    });

    // List Builder upgrade modal: capture nag location when a logged-in
    // subscriber clicks the upgrade button so the resulting paid transaction
    // is attributed to the post they came from.
    $(document).on('click', '#lplb-upgrade-panel .Slider__ExpandedButton', function(e) {
      e.preventDefault();
      storeNagLocationThenNavigate($(this).attr('href'));
    });

  });
})(jQuery);
