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


if ( !function_exists( 'do_leaky_paywall_profile' ) ) { 

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since CHANGEME
	 */
	function do_leaky_paywall_profile( $atts ) {
		
		global $lew;
		$lew = true;
		
		$settings = get_leaky_paywall_settings();
		$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';

		$defaults = array(
		);
	
		// Merge defaults with passed atts
		// Extract (make each array element its own PHP var
		$args = shortcode_atts( $defaults, $atts );
		extract( $args );
		
		$results = '';
		
		if ( is_user_logged_in() ) {
			
			$user = wp_get_current_user();
			
			$results .= sprintf( __( 'Welcome %s, you are currently logged in. <a href="%s">Click here to log out.</a>', 'issuem-leaky-paywall' ), $user->user_login, wp_logout_url( get_page_link( $settings['page_for_login'] ) ) );
			
			//Your Subscription
			$results .= '<h2>' . __( 'Your Subscription', 'issuem-leaky-paywall' ) . '</h2>';
			
			$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );
			
			$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status', true );
			
			$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', true );
			$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
			if ( false === $level_id || empty( $settings['levels'][$level_id]['label'] ) ) {
				$level_name = __( 'Undefined', 'issuem-leaky-paywall' );
			} else {
				$level_name = stripcslashes( $settings['levels'][$level_id]['label'] );
			}
			
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway', true );
			
			$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires', true );
			if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
				$expires = __( 'Never', 'issuem-leaky-paywall' );
			} else {
				$date_format = get_option( 'date_format' );
				$expires = mysql2date( $date_format, $expires );
			}
			
			$results .= '<table>';
			$results .= '<thead>';
			$results .= '<tr>';
			$results .= '	<th>' . __( 'Status', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Type', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Payment Method', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Expiration', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '</tr>';
			$results .= '</thead>';
			$results .= '<tbody>';
			$results .= '	<td>' . ucfirst( $status ) . '</td>';
			$results .= '	<td>' . $level_name . '</td>';
			$results .= '	<td>' . leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) . '</td>';
			$results .= '	<td>' . $expires . '</td>';
			$results .= '</tbody>';
			$results .= '</table>';
			
			//Your Mobile Devices
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( is_plugin_active( 'unipress-api/unipress-api.php' ) ) {
				
				global $unipress_api;
					
				$results .= '<h2>' . __( 'Your Mobile Devices', 'issuem-leaky-paywall' ) . '</h2>';
				$results .= '<p>' . __( 'To generate a token for the mobile app, click the "Add New Mobile Device" button below.', 'issuem-leaky-paywall' ) . '</p>';
				
				$results .= $unipress_api->leaky_paywall_subscriber_info_paid_subscriber_end( '' );
				
			}
			
			//Your Profile
			$results .= '<h2>' . __( 'Your Profile', 'issuem-leaky-paywall' ) . '</h2>';
			if ( !empty( $_POST['leaky-paywall-profile-nonce'] ) ) {
				
				if ( wp_verify_nonce( $_POST['leaky-paywall-profile-nonce'], 'leaky-paywall-profile' ) ) {
					
					try {
						$userdata = get_userdata( $user->ID );
						$args = array(
							'ID' 			=> $user->ID,
							'user_login' 	=> $userdata->user_login,
							'display_name' 	=> $userdata->display_name,	
							'user_email' 	=> $userdata->user_email,	
						);
						
						if ( !empty( $_POST['username'] ) ) {
							$args['user_login'] = $_POST['username'];
						}
						
						if ( !empty( $_POST['displayname'] ) ) {
							$args['display_name'] = $_POST['displayname'];
						}
						
						if ( !empty( $_POST['email'] ) ) {
							if ( is_email( $_POST['email'] ) ) {
								$args['user_email'] = $_POST['email'];
							} else {
								throw new Exception( __( 'Invalid email address.', 'issuem-leaky-paywall' ) );
							}
						}
						
						if ( !empty( $_POST['password1'] ) && !empty( $_POST['password2'] ) ) {
							if ( $_POST['password1'] === $_POST['password2'] ) {
								wp_set_password( $_POST['password1'], $user_id );
							} else {
								throw new Exception( __( 'Passwords do not match.', 'issuem-leaky-paywall' ) );
							}
						}
						
						$user_id = wp_update_user( $args );
												
						if ( is_wp_error( $user_id ) ) {
							throw new Exception( $user_id->get_error_message() );
						} else {
							$user = get_userdata( $user_id ); //Refresh the user object				
							$results .= '<p class="save">' . __( 'Profile Changes Saved.', 'issuem-leaky-paywall' ) . '</p>';
						}		
						
					}
					catch ( Exception $e ) {
						$results .= '<p class="error">' . $e->getMessage() . '</p>';
					}
					
				}
				
			}
			$results .= '<form id="leaky-paywall-profile" action="" method="post">';
			
			$results .= '<p>';
			$results .= '<label class="lp-field-label" for="leaky-paywall-username">' . __( 'Username', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-username" name="username" value="' . $user->user_login . '" />';
			$results .= '</p>';
			
			$results .= '<p>';
			$results .= '<label class="lp-field-label" for="leaky-paywall-display-name">' . __( 'Display Name', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="' . $user->display_name . '" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="issuem-leaky-paywall-field-label" for="leaky-paywall-email">' . __( 'Email', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="' . $user->user_email . '" />';
			$results .= '</p>';


			$results .= '<p>';
			$results .= '<label class="issuem-leaky-paywall-field-label" for="leaky-paywall-password1">' . __( 'Password', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="issuem-leaky-paywall-field-label" for="leaky-paywall-gift-subscription-password2">' . __( 'Password (again)', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-gift-subscription-password2" name="password2" value="" />';
			$results .= '</p>';
			
			$results .= wp_nonce_field( 'leaky-paywall-profile', 'leaky-paywall-profile-nonce', true, false );
			
			$results .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="Update Profile Information"  /></p>'; 
			$results .= '</form>';
			
		} else {
			
			$results .= do_leaky_paywall_login( array() );
			
		}
		
		return $results;
		
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
	add_shortcode( 'leaky_paywall_profile', 'do_leaky_paywall_profile' );
	
}