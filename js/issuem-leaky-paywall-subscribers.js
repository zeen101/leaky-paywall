var $leaky_paywall_subscribers = jQuery.noConflict();

$leaky_paywall_subscribers(document).ready(function($) {
	
	$( '#leaky-paywall-subscriber-expires' ).datepicker({
		prevText: '',
		nextText: '',
		minDate: 0,
		dateFormat: $( 'input[name=date_format]' ).val()
	});
	
});