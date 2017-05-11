( function( $ )  {

	$(document).ready( function() {

		var bodyClasses = $('body').attr('class').split(' ');

		$.each(bodyClasses, function(i, value) {

			if ( !value.search('postid' ) ) {
				
				var classArray = value.split('-');

				var post_id = parseInt( classArray[1] );

				if ( post_id > 0 ) {

					var data = {
						action: 'leaky_paywall_process_cookie',
						post_id: post_id
					};

					$.get(leaky_paywall_cookie_ajax.ajaxurl, data, function(data) {
						console.log(data);
					});

				}

			}

		});

	});

})( jQuery );