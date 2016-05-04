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
	
});