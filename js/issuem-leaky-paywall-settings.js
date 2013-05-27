var $leaky_paywall_settings = jQuery.noConflict();

$leaky_paywall_settings(document).ready(function($) {
	
	$( 'input[name=recurring]' ).live( 'change', function() {
		
		if ( $( this ).is(':checked') ) {
			
			$( 'tr.stripe_manual' ).hide();
			$( 'tr.stripe_plan' ).fadeIn( 'slow' );
			
		} else {
			
			$( 'tr.stripe_plan' ).hide();
			$( 'tr.stripe_manual' ).fadeIn( 'slow' );
			
		}
		
	});
	
});