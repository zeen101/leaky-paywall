<?php
	
if ( !function_exists( 'leaky_paywall_hash' ) ) {
	function leaky_paywall_hash( $str ) {
		return create_leaky_paywall_login_hash( $str );
	}
}

if ( !function_exists( 'is_issuem_leaky_subscriber_logged_in' ) ) {
	function is_issuem_leaky_subscriber_logged_in() {
		return leaky_paywall_has_user_paid();
	}
}
