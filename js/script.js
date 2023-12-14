(function ($) {
  $(document).ready(function () {


    $('.lpshowlogin').click(function(e) {
      e.preventDefault();
      $('.leaky-paywall-form-login').slideToggle();
    });


    $(document).on('click', '.leaky_paywall_message_wrap a', function(e) {
			e.preventDefault();

			var url = $(this).attr('href');
			var post_id = '';
			var nag_loc = '';
			var bodyClasses = $('body').attr('class').split(' ');

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

			var data = {
				action: 'leaky_paywall_store_nag_location',
				post_id: nag_loc
			};

			$.get(leaky_paywall_script_ajax.ajaxurl, data, function(resp) {
				window.location.href = url;
			});

		});

  });
})(jQuery);
