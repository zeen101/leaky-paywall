var $leaky_paywall_post = jQuery.noConflict();

$leaky_paywall_post(document).ready(function($) {
		
	$( 'select#issuem-leaky-paywall-visibility-type' ).live( 'change', function( event ) {
		var parent = $( this ).parent();
		if ( 'default' == $( this ).val() ) {
			$( 'select#issuem-leaky-paywall-only-visible' ).hide();
			$( 'select#issuem-leaky-paywall-always-visible' ).hide();
			$( 'select#issuem-leaky-paywall-only-always-visible' ).hide();
		} else if (  'only' == $( this ).val()  ) {
			$( 'select#issuem-leaky-paywall-only-visible' ).show();
			$( 'select#issuem-leaky-paywall-always-visible' ).hide();
			$( 'select#issuem-leaky-paywall-only-always-visible' ).hide();
		} else if (  'always' == $( this ).val()  ) {
			$( 'select#issuem-leaky-paywall-only-visible' ).hide();
			$( 'select#issuem-leaky-paywall-always-visible' ).show();
			$( 'select#issuem-leaky-paywall-only-always-visible' ).hide();
		} else if (  'onlyalways' == $( this ).val()  ) {
			$( 'select#issuem-leaky-paywall-only-visible' ).hide();
			$( 'select#issuem-leaky-paywall-always-visible' ).hide();
			$( 'select#issuem-leaky-paywall-only-always-visible' ).show();
		}
	});
		
});