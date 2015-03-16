<?php
	
if ( !function_exists( 'leaky_paywall_hash' ) ) {
	function leaky_paywall_hash( $str ) {
        _deprecated_function( __FUNCTION__, 'CHANGEME', 'create_leaky_paywall_login_hash( $str )' );
		return create_leaky_paywall_login_hash( $str );
	}
}

if ( !function_exists( 'is_issuem_leaky_subscriber_logged_in' ) ) {
	function is_issuem_leaky_subscriber_logged_in() {
        _deprecated_function( __FUNCTION__, 'CHANGEME', 'leaky_paywall_has_user_paid()' );
		return leaky_paywall_has_user_paid();
	}
}

if ( !function_exists( 'get_leaky_paywall_subscriber_by_hash' ) ) {
	function get_leaky_paywall_subscriber_by_hash( $hash, $mode ) {
        _deprecated_function( __FUNCTION__, 'CHANGEME' );
        
        if ( preg_match( '#^[0-9a-f]{32}$#i', $hash ) ) { //verify we get a valid 32 character md5 hash
	
			if ( empty( $mode ) ) {
                $settings = get_leaky_paywall_settings();
                $mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
            }

            $args = array(
                'meta_key'   => '_issuem_leaky_paywall_' . $mode . '_hash',
                'meta_value' => $hash,
            );
            $users = get_users( $args );

            if ( !empty( $users ) ) {
                foreach ( $users as $user ) {
                    return $user;
                }
            }

        }
        return false;
	}
}
