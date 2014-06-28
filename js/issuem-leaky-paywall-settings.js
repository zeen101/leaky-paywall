var $leaky_paywall_settings = jQuery.noConflict();

$leaky_paywall_settings(document).ready(function($) {
	
	$( 'input#add-restriction-row' ).live( 'click', function( event ) {
		event.preventDefault();
        var data = {
            'action': 'issuem-leaky-paywall-add-new-restriction-row',
            'row-key': ++issuem_leaky_paywall_restriction_row_key,
        }
        $.post( ajaxurl, data, function( response ) {
            $( 'table#issuem_leaky_paywall_default_restriction_options' ).append( response );
        });
	});
	
	$( 'input#add-subscription-row' ).live( 'click', function( event ) {
		event.preventDefault();
        var data = {
            'action': 'issuem-leaky-paywall-add-new-subscription-row',
            'row-key': ++issuem_leaky_paywall_subscription_levels_row_key,
        }
        $.post( ajaxurl, data, function( response ) {
            $( 'td#issuem-leaky-paywall-subscription-level-rows' ).append( response );
        });
	});
	
	$( 'input#add-subscription-row-post-type' ).live( 'click', function( event ) {
		event.preventDefault();
		var row_key = $( this ).data( 'row-key' );
		var select_post_key = 'issuem_leaky_paywall_subscription_row_' + row_key + '_last_post_type_key';
        var data = {
            'action': 'issuem-leaky-paywall-add-new-subscription-row-post-type',
            'select-post-key': ++window[select_post_key],
            'row-key': row_key,
        }
        console.log( data );
        $.post( ajaxurl, data, function( response ) {
	        console.log( response );
            $( '#issuem-leaky-paywall-subsciption-row-' + row_key + '-post-types' ).append( response );
        });
	});
	
	$( 'select.subscription_length_type' ).live( 'change', function( event ) {
		var parent = $( this ).parent();
		if ( 'unlimited' == $( this ).val() ) {
			$( '.interval_count', parent ).data( 'prev-value', $( '.interval_count', parent ).val() )
			$( '.interval_div', parent ).hide();
			$( '.interval_count', parent ).val( '0' );
		} else {
			$( '.interval_count', parent ).val( $( '.interval_count', parent ).data( 'prev-value' ) );
			$( '.interval_div', parent ).show();
		}
	});
	
	$( 'select.allowed_type' ).live( 'change', function( event ) {
		var parent = $( this ).parent();
		if ( 'unlimited' == $( this ).val() ) {
			$( '.allowed_value', parent ).data( 'prev-value', $( '.allowed_value', parent ).val() )
			$( '.allowed_value', parent ).hide();
			$( '.allowed_value', parent ).val( '-1' );
		} else {
			$( '.allowed_value', parent ).val( $( '.allowed_value', parent ).data( 'prev-value' ) );
			$( '.allowed_value', parent ).show();
		}
	});
		
	$( '.delete-restriction-row' ).live( 'click', function ( event ) {
		console.log('here');
		event.preventDefault();
		var parent = $( this ).parents( '.issuem-leaky-paywall-restriction-row' );
		parent.slideUp( 'normal', function() { $( this ).remove(); } );
	});
		
	$( '.delete-post-type-row' ).live( 'click', function ( event ) {
		event.preventDefault();
		var parent = $( this ).parent( '.issuem-leaky-paywall-row-post-type' );
		parent.slideUp( 'normal', function() { $( this ).remove(); } );
	});
	
	$( '.delete-subscription-level' ).live( 'click', function ( event ) {
		event.preventDefault();
		var parent = $( this ).parents( '.issuem-leaky-paywall-subscription-level-row-table' );
		parent.slideUp( 'normal', function() { $( this ).remove(); } );
	});
	
});