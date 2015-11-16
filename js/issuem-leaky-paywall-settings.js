var $leaky_paywall_settings = jQuery.noConflict();

$leaky_paywall_settings(document).ready(function($) {
	
	$( '#leaky_paywall_default_restriction_options' ).on( 'click', 'input#add-restriction-row', function( event ) {
		event.preventDefault();
        var data = {
            'action': 'issuem-leaky-paywall-add-new-restriction-row',
            'row-key': ++leaky_paywall_restriction_row_key,
        }
        $.post( ajaxurl, data, function( response ) {
            $( 'td#issuem-leaky-paywall-restriction-rows' ).append( response );
        });
	});
	
	$( '#leaky_paywall_subscription_level_options' ).on( 'click', 'input#add-subscription-row', function( event ) {
		event.preventDefault();
        var data = {
            'action': 'issuem-leaky-paywall-add-new-subscription-row',
            'row-key': ++leaky_paywall_subscription_levels_row_key,
        }
        $.post( ajaxurl, data, function( response ) {
            $( 'td#issuem-leaky-paywall-subscription-level-rows' ).append( response );
        });
	});
	
	$( '#leaky_paywall_subscription_level_options' ).on( 'click', 'input#add-subscription-row-post-type', function( event ) {
		event.preventDefault();
		var row_key = $( this ).data( 'row-key' );
		var select_post_key = 'leaky_paywall_subscription_row_' + row_key + '_last_post_type_key';
        var data = {
            'action': 'issuem-leaky-paywall-add-new-subscription-row-post-type',
            'select-post-key': ++window[select_post_key],
            'row-key': row_key,
        }
        $.post( ajaxurl, data, function( response ) {
	        console.log( response );
            $( '#issuem-leaky-paywall-subsciption-row-' + row_key + '-post-types' ).append( response );
        });
	});
	
	$( '#issuem-leaky-paywall-subscription-level-rows' ).on( 'change', 'select.subscription_length_type', function( event ) {
		var parent = $( this ).parent();
		if ( 'unlimited' == $( this ).val() ) {
			$( '.interval_count', parent ).data( 'prev-value', $( '.interval_count', parent ).val() )
			$( '.interval_div', parent ).hide();
			$( '.interval_count', parent ).val( '-1' );
		} else {
			$( '.interval_count', parent ).val( $( '.interval_count', parent ).data( 'prev-value' ) );
			$( '.interval_div', parent ).show();
		}
	});
	
	$( '#issuem-leaky-paywall-subscription-level-rows' ).on( 'change', '.issuem-leaky-paywall-row-post-type select.allowed_type', function( event ) {
		var parent = $( this ).parent();
		if ( 'unlimited' == $( this ).val() ) {
			$( '.allowed_value', parent ).data( 'prev-value', $( '.allowed_value', parent ).val() )
			$( '.allowed_value_div', parent ).hide();
			$( '.allowed_value', parent ).val( '-1' );
		} else {
			$( '.allowed_value', parent ).val( $( '.allowed_value', parent ).data( 'prev-value' ) );
			$( '.allowed_value_div', parent ).show();
		}
	});
		
	$( '#leaky_paywall_default_restriction_options' ).on( 'click', '.delete-restriction-row', function ( event ) {
		event.preventDefault();
		var parent = $( this ).parents( '.issuem-leaky-paywall-restriction-row' );
		parent.slideUp( 'normal', function() { $( this ).remove(); } );
	});
	
	$( '#issuem-leaky-paywall-subscription-level-rows' ).on( 'click', '.delete-subscription-level', function ( event ) {
		event.preventDefault();
		var parent = $( this ).parents( '.issuem-leaky-paywall-subscription-level-row-table' );
		parent.slideUp( 'normal', function() { $( this ).hide(); $( '.deleted-subscription', this ).val( 1 ); } );
	});
		
	$( '#issuem-leaky-paywall-subscription-level-rows' ).on( 'click', '.delete-post-type-row', function ( event ) {
		event.preventDefault();
		var parent = $( this ).parent( '.issuem-leaky-paywall-row-post-type' );
		parent.slideUp( 'normal', function() { $( this ).remove(); } );
	});
			
});