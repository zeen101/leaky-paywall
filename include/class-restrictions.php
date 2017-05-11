<?php 

/**
* Load the Restrictions Class
*/
class Leaky_Paywall_Restrictions {

	public function run() 
	{

		add_action( 'wp_ajax_nopriv_leaky_paywall_process_cookie', array( $this, 'process_cookie_requests' ) );
		add_action( 'wp_ajax_leaky_paywall_process_cookie', array( $this, 'process_cookie_requests' ) );

	}

	/**
	 * Process ajax requests for restricting content with javascript cookies
	 *
	 * @since 4.7.1
	 *
	 */
	public function process_cookie_requests() 
	{
		
		$post_id = $_REQUEST['post_id'];

		$post_obj = get_post( $post_id );

		$post_type = 'post';

		if ( $post_obj->post_type != $post_type ) {
			echo 'is not a single ' . $post_type;
			die();
		}
			
		// set cookie

		$settings = get_leaky_paywall_settings();

		switch ( $settings['cookie_expiration_interval'] ) {
			case 'hour':
				$multiplier = 60 * 60; //seconds in an hour
				break;
			case 'day':
				$multiplier = 60 * 60 * 24; //seconds in a day
				break;
			case 'week':
				$multiplier = 60 * 60 * 24 * 7; //seconds in a week
				break;
			case 'month':
				$multiplier = 60 * 60 * 24 * 7 * 4; //seconds in a month (4 weeks)
				break;
			case 'year':
				$multiplier = 60 * 60 * 24 * 7 * 52; //seconds in a year (52 weeks)
				break;
		}
		$expiration = time() + ( $settings['cookie_expiration'] * $multiplier );

		if ( !empty( $_COOKIE['issuem_lp'] ) ) {
			$available_content = maybe_unserialize( stripslashes( $_COOKIE['issuem_lp'] ) );
		}

		if ( empty( $available_content[$post_type] ) ) {
			$available_content[$post_type] = array();			
		}

		foreach ( $available_content[$post_type] as $key => $restriction ) {
			
			if ( time() > $restriction || 7200 > $restriction ) { 
				//this post view has expired
				//Or it is very old and based on the post ID rather than the expiration time
				unset( $available_content[$post_type][$key] );
				
			}
			
		}


		// cookie stuff

		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;
		
		$restrictions = leaky_paywall_subscriber_restrictions();

		if ( !empty( $restrictions ) ) {
			
			foreach( $restrictions as $key => $restriction ) {

				if ( is_singular( $restriction['post_type'] ) ) {
				
					if ( 0 <= $restriction['allowed_value'] ) {
					
						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						$is_restricted = true;
						break;
						
					}
					
				}
				
			}

		}	

		if( -1 != $restrictions[$post_type_id]['allowed_value'] ) { //-1 means unlimited
																		
			if ( $restrictions[$post_type_id]['allowed_value'] > count( $available_content[$restricted_post_type] ) ) { 
			
				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					
					$available_content[$restricted_post_type][$post_id] = $expiration;
					
					echo 'is a single ' . $post_type . ', but do not show paywall';
				}
				
			} else {
			
				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
						
					echo 'show paywall';
					
				}
				
			}
		
		}

		$serialized_available_content = maybe_serialize( $available_content );

		$cookie = setcookie( 'issuem_lp', $serialized_available_content, $expiration, '/' );
		$_COOKIE['issuem_lp' . $site] = $serialized_available_content;	
		
		die();

	}

}