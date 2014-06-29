<?php
/**
 * @package IssueM's Leaky Paywall
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
				wp_enqueue_style( 'issuem_leaky_paywall_style', IM_URL . '/css/issuem-leaky-paywall.css', '', ISSUEM_LP_VERSION );
				break;
				
		}
		
	}
	//add_action( 'wp_enqueue_scripts', array( $this, 'issuem_do_leaky_paywall_shortcode_wp_enqueue_scripts' ) );

}

if ( !function_exists( 'do_issuem_leaky_paywall_login' ) ) { 

	/**
	 * Shortcode for IssueM's Leaky Paywall
	 * Prints out the IssueM's Leaky Paywall
	 *
	 * @since 1.0.0
	 */
	function do_issuem_leaky_paywall_login( $atts ) {
		
		global $post;
		
		$settings = get_issuem_leaky_paywall_settings();
		
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
	
		
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_login', 'do_issuem_leaky_paywall_login' );
	
}

if ( !function_exists( 'do_issuem_leaky_paywall_subscription' ) ) { 

	/**
	 * Shortcode for IssueM's Leaky Paywall
	 * Prints out the IssueM's Leaky Paywall
	 *
	 * @since 1.0.0
	 */
	function do_issuem_leaky_paywall_subscription( $atts ) {
		
		global $post;
		
		$settings = get_issuem_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		$show_subscription_options = true;
		
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
				
		if ( !empty( $_SESSION['issuem_lp_email'] ) ) {
						
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
			
			if ( false !== $expires = issuem_leaky_paywall_has_user_paid( $_SESSION['issuem_lp_email'] ) ) {
				
				$user = get_user_by( 'email', $_SESSION['issuem_lp_email'] );
				$hash = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_hash', true );
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway', true );
				$show_subscription_options = false;
				
				if ( !empty( $hash ) )
					$_SESSION['issuem_lp_subscriber'] = $hash;
					
				switch( $expires ) {
				
					case 'subscription':
						$results .= sprintf( __( 'Your subscription will automatically renew until you <a href="%s">cancel</a>.', 'issuem-leaky-paywall' ), '?cancel' );
						break;
						
					case 'unlimited':
						$results .= __( 'You are a lifetime subscriber!', 'issuem-leaky-paywall' );
						break;
				
					case 'canceled':
						$results .= sprintf( __( 'Your subscription has been canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] );
						$show_subscription_options = true;
						break;
						
					default:
						$results .= sprintf( __( 'You are subscribed via %s until %s.', 'issuem-leaky-paywall' ), issuem_translate_payment_gateway_slug_to_name( $payment_gateway ), date_i18n( get_option('date_format'), strtotime( $expires ) ) );
						
				}
				
				$results .= '<p>' . __( 'Thank you very much for subscribing.', 'issuem-leaky-paywall' ) . '</p>';
				
				$results .= '<p><a href="' . wp_logout_url( get_page_link( $settings['page_for_login'] ) ) . '">' . __( 'Log Out', 'issuem-leaky-paywall' ) . '</a></p>';
				$results .= '</div>';
				
			} else {
				
				$show_subscription_options = true;
				
			}
			
		}			
			
		if ( $show_subscription_options ) {
		
			//Once we enable Upgrades, we want to move this outside of the ELSE Block;
			$results .= issuem_leaky_paywall_subscription_options();
		
		}
				
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_subscription', 'do_issuem_leaky_paywall_subscription' );
	
}
