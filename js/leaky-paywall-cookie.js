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
						var response;

						if ( data ) {

							response = JSON.parse(data);

							var children;
							var lead_in = '';
							var content_container_setting = leaky_paywall_cookie_ajax.post_container;
							var content_containers = content_container_setting.split(',');
							var lead_in_elements = leaky_paywall_cookie_ajax.lead_in_elements;

							if ( response.indexOf("leaky_paywall_message_wrap") >= 0 ) {

								content_containers.forEach(function(el) {
									
									console.log(el);

									var content = $(el);

									if ( lead_in_elements > 0 ) {

										children = content.children();

										console.log(children);
	
										children.each(function(i) {
											
											if ( i == lead_in_elements ) {
												return false;
											}
	
											lead_in = lead_in + $(this).wrap('<p/>').parent().html();
											
										});

										
	
									}

									content.html(lead_in + response);
									content.css('display','block');
								});

								console.log(leaky_paywall_cookie_ajax.post_container);

								

								
								
								
							} else {
								content_containers.forEach(function(el) {
									var content = $(el);
									content.css('display','block');
								});
								
							}

						}
						
						
					});

				}

			}

			// for pages
			if ( !value.search('page-id' ) ) {
				
				var classArray = value.split('-');
				var post_id = parseInt( classArray[2] );

				if ( post_id > 0 ) {

					var data = {
						action: 'leaky_paywall_process_cookie',
						post_id: post_id
					};

					$.get(leaky_paywall_cookie_ajax.ajaxurl, data, function(data) {
						var response;
	
						if ( data ) {

							response = JSON.parse(data);

							if ( response.indexOf("leaky_paywall_message_wrap") >= 0 ) {
								
								var content = $( leaky_paywall_cookie_ajax.page_container );
								
								content.html(response);
								
							}

						}
						
						
					});

				}

			}

		});

	});

})( jQuery );