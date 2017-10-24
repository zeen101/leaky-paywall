<?php 

/**
* Load the Restrictions Class
*/
class Leaky_Paywall_Restrictions {


	public function process_php() 
	{
		
		$settings = get_leaky_paywall_settings();

		do_action( 'leaky_paywall_before_process_requests', $settings );

		$has_subscriber_paid = leaky_paywall_has_user_paid();

		if ( isset( $_REQUEST['issuem-pdf-download'] ) ) {
			$this->pdf_access( $has_subscriber_paid );
		}
		
		if ( is_singular() ) {
			$this->content_access();
		}
		
		if ( $has_subscriber_paid ) {
					
			if ( $this->is_cancel_request() ) {
				wp_die( leaky_paywall_cancellation_confirmation() );
			}

			// if they user has paid and they try to access the login page, send them to the my account page instead
			$this->redirect_from_login_page();
		
		} else {
			
			if ( !empty( $_REQUEST['r'] ) ) {
				$this->process_passwordless_login();
			}		
		}

	}

	public function pdf_access( $has_subscriber_paid ) 
	{

		$settings = get_leaky_paywall_settings();

		//Admins or subscribed users can download PDFs
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) || $has_subscriber_paid ) {
			leaky_paywall_server_pdf_download( $_REQUEST['issuem-pdf-download'] );
		} else {
			
			$output = '<h3>' . __( 'Unauthorize PDF Download', 'issuem-leaky-paywall' ) . '</h3>';
			$output .= '<p>' . sprintf( __( 'You must be <a href="%s">logged in</a> with a valid subscription to download Issue PDFs.', 'issuem-leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</p>';
			$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
			
			wp_die( apply_filters( 'leaky_paywall_unauthorized_pdf_download_output', $output ) );
			
		}

	}

	public function content_access() 
	{

		$settings = get_leaky_paywall_settings();
 		
		// allow admins to view all content
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) { 
			return;
		}
		
		// We don't ever want to block the login, subscription, etc.
		if ( $this->is_unblockable_content() ) {
			return;
		}
		
		global $post;
		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;
		
		$restrictions = leaky_paywall_subscriber_restrictions();
		$site = leaky_paywall_get_current_site();
		
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
	
		$level_ids = leaky_paywall_subscriber_current_level_ids();
		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
		
		if ( false !== $visibility && !empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {
									
			switch( $visibility['visibility_type'] ) {
				
				// using trim() == false instead of empty() for older versions of php 
				// see note on http://php.net/manual/en/function.empty.php

				case 'only':
					$only = array_intersect( $level_ids, $visibility['only_visible'] );
					if ( empty( $only ) ) {
						add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
						do_action( 'leaky_paywall_is_restricted_content' );
						return;
					}
					break;
					
				case 'always':
					$always = array_intersect( $level_ids, $visibility['always_visible'] );
					if ( in_array( -1, $visibility['always_visible'] ) || !empty( $always ) ) { //-1 = Everyone
						return; //always visible, don't need process anymore
					}
					break;
				
				case 'onlyalways':
					$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
					if ( empty( $onlyalways ) ) {
						add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
						do_action( 'leaky_paywall_is_restricted_content' );
						return;
					} else if ( !empty( $onlyalways ) ) {
						return; //always visible, don't need process anymore
					}
					break;
				
				
			}
			
		}
		
		$is_restricted = apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $restrictions, $post );
		
		if ( !$is_restricted ) {
			return;
		}
			
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
									
		if ( !empty( $_COOKIE['issuem_lp' . $site] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE['issuem_lp' . $site] ), true );
		}
		
		if ( empty( $available_content[$restricted_post_type] ) )
			$available_content[$restricted_post_type] = array();							
	
		foreach ( $available_content[$restricted_post_type] as $key => $restriction ) {
			
			if ( time() > $restriction || 7200 > $restriction ) { 
				//this post view has expired
				//Or it is very old and based on the post ID rather than the expiration time
				unset( $available_content[$restricted_post_type][$key] );
				
			}
			
		}
									
		if( -1 != $restrictions[$post_type_id]['allowed_value'] ) { //-1 means unlimited
																		
			if ( $restrictions[$post_type_id]['allowed_value'] > count( $available_content[$restricted_post_type] ) ) { 
			
				if ( !array_key_exists( $post->ID, $available_content[$restricted_post_type] ) ) {
					
					$available_content[$restricted_post_type][$post->ID] = $expiration;
				
				}
				
			} else {
			
				if ( !array_key_exists( $post->ID, $available_content[$restricted_post_type] ) ) {
						
					add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
					do_action( 'leaky_paywall_is_restricted_content' );
					
				}
				
			}
		
		}

		$json_available_content = json_encode( $available_content );

		$cookie = setcookie( 'issuem_lp' . $site, $json_available_content, $expiration, '/' );
		$_COOKIE['issuem_lp' . $site] = $json_available_content;	
		
	}

	public function the_content_paywall( $content ) {
	
		$settings = get_leaky_paywall_settings();
				
		add_filter( 'excerpt_more', '__return_false' );
		
		//Remove the_content filter for get_the_excerpt calls
		remove_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
		$content = get_the_excerpt();
		add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
		//Add the_content filter back for futhre the_content calls
		
		$message  = '<div id="leaky_paywall_message">';
		if ( !is_user_logged_in() ) {
			$message .= $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
		} else {
			$message .= $this->replace_variables( stripslashes( $settings['subscribe_upgrade_message'] ) );
		}
		$message .= '</div>';
	
		$new_content = $content . $message;
	
		return apply_filters( 'leaky_paywall_subscribe_or_login_message', $new_content, $message, $content );
		
	}

	public function replace_variables( $message ) {
	
		$settings = get_leaky_paywall_settings();
		
		if ( 0 === $settings['page_for_subscription'] )
			$subscription_url = get_bloginfo( 'wpurl' ) . '/?subscription'; //CHANGEME -- I don't really know what this is suppose to do...
		else
			$subscription_url = get_page_link( $settings['page_for_subscription'] );
		
		if ( 0 === $settings['page_for_profile'] )
			$my_account_url = get_bloginfo( 'wpurl' ) . '/?my-account'; //CHANGEME -- I don't really know what this is suppose to do...
		else
			$my_account_url = get_page_link( $settings['page_for_profile'] );
			
		$message = str_ireplace( '{{SUBSCRIBE_LOGIN_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{SUBSCRIBE_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{MY_ACCOUNT_URL}}', $my_account_url, $message );
		
		if ( 0 === $settings['page_for_login'] )
			$login_url = get_bloginfo( 'wpurl' ) . '/?login'; //CHANGEME -- I don't really know what this is suppose to do...
		else
			$login_url = get_page_link( $settings['page_for_login'] );
			
		$message = str_ireplace( '{{LOGIN_URL}}', $login_url, $message );
		
		//Deprecated
		if ( !empty( $settings['price'] ) ) {
			$message = str_ireplace( '{{PRICE}}', $settings['price'], $message );
		}
		if ( !empty( $settings['interval_count'] ) && !empty( $settings['interval'] ) ) {
			$message = str_ireplace( '{{LENGTH}}', leaky_paywall_human_readable_interval( $settings['interval_count'], $settings['interval'] ), $message );
		}
		
		return $message;
		
	}

	public function is_cancel_request() 
	{
		$settings = get_leaky_paywall_settings();

		if ( isset( $_REQUEST['cancel'] ) ) {

			if ( 
				( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) ) 
				|| ( !empty( $settings['page_for_profile'] ) && is_page( $settings['page_for_profile'] )  )
			) {
				return true;
			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	public function redirect_from_login_page() 
	{

		$settings = get_leaky_paywall_settings();
		
		if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {
			
			if ( !empty( $settings['page_for_profile'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
			} else if ( !empty( $settings['page_for_subscription'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
			}
			
		}

	}

	public function process_passwordless_login() 
	{

		$settings = get_leaky_paywall_settings();
		
		if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {

			$login_hash = $_REQUEST['r'];
			
			if ( verify_leaky_paywall_login_hash( $login_hash ) ) {
			
				leaky_paywall_attempt_login( $login_hash );
				if ( !empty( $settings['page_for_profile'] ) ) {
					wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
				} else if ( !empty( $settings['page_for_subscription'] ) ) {
					wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
				}
				
			} else {
			
				$output  = '<h3>' . __( 'Invalid or Expired Login Link', 'issuem-leaky-paywall' ) . '</h3>';
				$output .= '<p>' . sprintf( __( 'Sorry, this login link is invalid or has expired. <a href="%s">Try again?</a>', 'issuem-leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</p>';
				$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
				
				wp_die( apply_filters( 'leaky_paywall_invalid_login_link', $output ) );

			}
			
		}

	}

	public function is_unblockable_content() 
	{

		$settings = get_leaky_paywall_settings();
		
		$unblockable_content = array(
			$settings['page_for_login'],
			$settings['page_for_subscription'],
			$settings['page_for_profile'],
			$settings['page_for_register']
		);

		if ( is_page( apply_filters( 'leaky_paywall_unblockable_content', $unblockable_content ) ) ) {
			return true;
		}

		return false;
		
	}

	public function process_js() 
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
			$available_content = json_decode( stripslashes( $_COOKIE['issuem_lp'] ), true );
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

		$json_available_content = json_encode( $available_content );

		$cookie = setcookie( 'issuem_lp', $json_available_content, $expiration, '/' );
		$_COOKIE['issuem_lp' . $site] = $json_available_content;	
		
		die();

	}

}
