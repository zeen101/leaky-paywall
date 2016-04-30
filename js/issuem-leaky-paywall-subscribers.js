var $leaky_paywall_subscribers = jQuery.noConflict();

$leaky_paywall_subscribers(document).ready(function($) {
	
	$( '#leaky-paywall-subscriber-expires' ).datepicker({
		prevText: '',
		nextText: '',
		minDate: 0,
		dateFormat: $( 'input[name=date_format]' ).val()
	});

	$('.lp-notice-link').click(function (e) {
		e.preventDefault();
		$(this).closest('.notice').hide();
		$.ajax({
			url     : leaky_paywall_notice_ajax.ajaxurl,
			type    : 'POST',
			dataType: 'text',
			cache   : false,
			data    : {
				action  : 'leaky_paywall_process_notice_link',
				nonce   : leaky_paywall_notice_ajax.lpNoticeNonce,
				notice  : $(this).data('notice'),
				type    : $(this).data('type')
			}
		});
	});


	
	
	
	$('#leaky_paywall_upload_user_csv_button').click(function(e) {

		var user_custom_uploader;
	
	    e.preventDefault();
	
	    //If the uploader object has already been created, reopen the dialog
	    if (user_custom_uploader) {
	        user_custom_uploader.open();
	        return;
	    }
	
	    //Extend the wp.media object
	    user_custom_uploader = wp.media.frames.file_frame = wp.media({
	        title: 'Choose CSV File',
	        button: {
	            text: 'Choose CSV File'
	        },
	        multiple: false
	    });
	
	    //When a file is selected, grab the URL and set it as the text field's value
	    user_custom_uploader.on('select', function() {
	        attachment = user_custom_uploader.state().get('selection').first().toJSON();

	        console.log( attachment );

	        $('#leaky_paywall_import_user_csv_file').val(attachment.url);
	    });
	
	    //Open the uploader dialog
	    user_custom_uploader.open();
	
	});
	
});