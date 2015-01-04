<?php
/**
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

if ( !function_exists( 'issuem_do_leaky_paywall_shortcode_wp_enqueue_scripts' ) ) { 

	/**
	 * Helper function used for printing out debug information
	 *
	 * HT: Glenn Ansley @ iThemes.com
	 *
	 * @since 1.1.6
	 *
	 * @param int $args Arguments to pass to print_r
	 * @param bool $die TRUE to die else FALSE (default TRUE)
	 */
	function issuem_do_leaky_paywall_shortcode_wp_enqueue_scripts() {
	
		$settings = get_issuem_leaky_settings();
	
		switch( $settings['css_style'] ) {
			
			case 'none' :
				break;
			
			case 'default' :
			default : 
				wp_enqueue_style( 'leaky_paywall_style', IM_URL . '/css/issuem-leaky-paywall.css', '', LP_VERSION );
				break;
				
		}
		
	}
	//add_action( 'wp_enqueue_scripts', array( $this, 'issuem_do_leaky_paywall_shortcode_wp_enqueue_scripts' ) );

}

if ( !function_exists( 'do_leaky_paywall_login' ) ) { 

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since 1.0.0
	 */
	function do_leaky_paywall_login( $atts ) {
		
		global $post;
		
		$settings = get_leaky_paywall_settings();
		
		$defaults = array(
			'heading'			=> __( 'Email address:', 'issuem-leaky-paywall' ),
			'description' 		=> __( 'Check your email for a link to log in.', 'issuem-leaky-paywall' ),
			'email_sent' 		=> __( 'Email sent. Please check your email for the login link.', 'issuem-leaky-paywall' ),
			'error_msg' 		=> __( 'Error sending login email, please try again later.', 'issuem-leaky-paywall' ),
			'missing_email_msg' => __( 'Please supply a valid email address.', 'issuem-leaky-paywall' ),
		);
	
		// Merge defaults with passed atts
		// Extract (make each array element its own PHP var
		$args = shortcode_atts( $defaults, $atts );
		extract( $args );
		
		$results = '';
		
		if ( 'passwordless' === $settings['login_method'] ) {
	
			if ( isset( $_REQUEST['submit-leaky-login'] ) ) {
				
				if ( isset( $_REQUEST['email'] ) && is_email( $_REQUEST['email'] ) ) {
				
					if ( send_leaky_paywall_email( $_REQUEST['email'] ) )
						return '<h3>' . $email_sent . '</h3>';
					else
						$results .= '<h1 class="error">' . $error_msg . '</h1>';
					
				} else {
				
					$results .= '<h1 class="error">' . $missing_email_msg . '</h1>';
					
				}
				
			}
			
			$results .= '<h2>' . $heading . '</h2>';
			$results .= '<form action="" method="post">';
			$results .= '<input type="text" id="leaky-paywall-login-email" name="email" placeholder="valid@email.com" value="" />';
			$results .= '<input type="submit" id="leaky-paywall-submit-buttom" name="submit-leaky-login" value="' . __( 'Send Login Email', 'issuem-leaky-paywall' ) . '" />';
			$results .= '</form>';
			$results .= '<h3>' . $description . '</h3>';
	
		} else { //traditional
		
			add_action( 'login_form_bottom', 'leaky_paywall_add_lost_password_link' );
			$args = array(
				'echo' => false,
				'redirect' => get_page_link( $settings['page_for_subscription'] ),
			);
			$results .= wp_login_form( $args );
		
		}
		
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_login', 'do_leaky_paywall_login' );
	
}

if ( !function_exists( 'do_leaky_paywall_subscription' ) ) { 

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since 1.0.0
	 */
	function do_leaky_paywall_subscription( $atts ) {
		
		global $post;
		
		if ( isset( $_REQUEST['issuem-leaky-paywall-free-return'] ) )
			return leaky_paywall_free_registration_form();
		
		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		$defaults = array(
			'login_heading' 	=> __( 'Enter your email address to start your subscription:', 'issuem-leaky-paywall' ),
			'login_desc' 		=> __( 'Check your email for a link to start your subscription.', 'issuem-leaky-paywall' ),
			//Deprecated!
			//'plan_id'			=> $settings['plan_id'],
			//'price'				=> $settings['price'],
			//'recurring'			=> $settings['recurring'],
			//'interval_count' 	=> $settings['interval_count'],
			//'interval'			=> $settings['interval'],
			//'description'		=> $settings['charge_description'],
			//'payment_gateway' 	=> $settings['payment_gateway'],
		);
	
		// Merge defaults with passed atts
		// Extract (make each array element its own PHP var
		$args = shortcode_atts( $defaults, $atts );
		extract( $args );
		
		$results = '';
				
		if ( is_user_logged_in() ) {
			
			$user = wp_get_current_user();
						
			$results .= apply_filters( 'leaky_paywall_subscriber_info_start', '' );
			
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
						
			if ( false !== $expires = leaky_paywall_has_user_paid() ) {
						
				$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );
				
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway', true );
				
				switch( $expires ) {
				
					case 'subscription':
						$results .= sprintf( __( 'Your subscription will automatically renew until you <a href="%s">cancel</a>.', 'issuem-leaky-paywall' ), '?cancel' );
						break;
						
					case 'unlimited':
						$results .= __( 'You are a lifetime subscriber!', 'issuem-leaky-paywall' );
						break;
				
					case 'canceled':
						$results .= sprintf( __( 'Your subscription has been canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] );
						break;
						
					default:
						$results .= sprintf( __( 'You are subscribed via %s until %s.', 'issuem-leaky-paywall' ), leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ), date_i18n( get_option('date_format'), strtotime( $expires ) ) );
						
				}
				
				$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_end', '' );
				
				$results .= '<p><a href="' . wp_logout_url( get_page_link( $settings['page_for_login'] ) ) . '">' . __( 'Log Out', 'issuem-leaky-paywall' ) . '</a></p>';
								
			}
			
			$results .= '</div>';
			
			$results .= apply_filters( 'leaky_paywall_subscriber_info_end', '' );
			
		}			
			
		$results .= leaky_paywall_subscription_options();
				
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_subscription', 'do_leaky_paywall_subscription' );
	
}
if ( !function_exists( 'do_leaky_paywall_multisite_subscription' ) ) { 

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since CHANGEME
	 */
	function do_leaky_paywall_multisite_subscription( $atts ) {
		
		global $post;
		
		if ( isset( $_REQUEST['issuem-leaky-paywall-free-return'] ) )
			return leaky_paywall_free_registration_form();
		
		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		
		$defaults = array(
			'login_heading' 	=> __( 'Enter your email address to start your subscription:', 'issuem-leaky-paywall' ),
			'login_desc' 		=> __( 'Check your email for a link to start your subscription.', 'issuem-leaky-paywall' ),
		);
	
		// Merge defaults with passed atts
		// Extract (make each array element its own PHP var
		$args = shortcode_atts( $defaults, $atts );
		extract( $args );
		
		$results = '';
				
		if ( is_user_logged_in() ) {
			
			$user = wp_get_current_user();
						
			$results .= apply_filters( 'leaky_paywall_subscriber_info_start', '' );
			
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
						
			if ( false !== $expires = leaky_paywall_has_user_paid() ) {
						
				$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );
				
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway', true );
				
				switch( $expires ) {
				
					case 'subscription':
						$results .= sprintf( __( 'Your subscription will automatically renew until you <a href="%s">cancel</a>.', 'issuem-leaky-paywall' ), '?cancel' );
						break;
						
					case 'unlimited':
						$results .= __( 'You are a lifetime subscriber!', 'issuem-leaky-paywall' );
						break;
				
					case 'canceled':
						$results .= sprintf( __( 'Your subscription has been canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] );
						break;
						
					default:
						$results .= sprintf( __( 'You are subscribed via %s until %s.', 'issuem-leaky-paywall' ), leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ), date_i18n( get_option('date_format'), strtotime( $expires ) ) );
						
				}
				
				$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_end', '' );
				
				$results .= '<p><a href="' . wp_logout_url( get_page_link( $settings['page_for_login'] ) ) . '">' . __( 'Log Out', 'issuem-leaky-paywall' ) . '</a></p>';
								
			}
			
			$results .= '</div>';
			
			$results .= apply_filters( 'leaky_paywall_subscriber_info_end', '' );
			
		}			
			
		$results .= leaky_paywall_subscription_options();
				
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_multisite_subscription', 'do_leaky_paywall_multisite_subscription' );
	
}
