<?php
/**
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

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
		
			if ( !empty( $settings['page_for_profile'] ) ) {
				$page_link = get_page_link( $settings['page_for_profile'] );
			} else if ( !empty( $settings['page_for_subscription'] ) ) {
				$page_link = get_page_link( $settings['page_for_subscription'] );
			}
		
			add_action( 'login_form_bottom', 'leaky_paywall_add_lost_password_link' );
			$args = array(
				'echo' => false,
				'redirect' => $page_link,
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
		
		if ( isset( $_REQUEST['issuem-leaky-paywall-free-form'] ) )
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
			
			$sites = array( '' );
			if ( is_multisite_premium() ) {
				global $blog_id;			
				if ( !is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}
			
			$user = wp_get_current_user();
				
			$results .= apply_filters( 'leaky_paywall_subscriber_info_start', '' );
			
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';

			foreach ( $sites as $site ) {
				
				if ( false !== $expires = leaky_paywall_has_user_paid( $user->user_email, $site ) ) {
						
					$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );
					
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					
					if ( empty( $payment_gateway ) && empty( $subscriber_id ) ) {
						continue;
					}
					
					switch( $expires ) {
					
						case 'subscription':
							$results .= sprintf( __( 'Your subscription will automatically renew until you <a href="%s">cancel</a>', 'issuem-leaky-paywall' ), '?cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id );
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
					
					break; //We only want one
									
				}
			
			}
			
			$results .= '</div>';
			
			$results .= apply_filters( 'leaky_paywall_subscriber_info_end', '' );
			
		}			
			
		$results .= leaky_paywall_subscription_options();
				
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_subscription', 'do_leaky_paywall_subscription' );
	
}

if ( !function_exists( 'do_leaky_paywall_profile' ) ) { 

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since CHANGEME
	 */
	function do_leaky_paywall_profile( $atts ) {
		
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
			
			$sites = array( '' );
			global $blog_id;
			if ( is_multisite_premium() ) {
				if ( !is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}			
			$user = wp_get_current_user();
			
			$results .= sprintf( __( '<p>Welcome %s, you are currently logged in. <a href="%s">Click here to log out.</a></p>', 'issuem-leaky-paywall' ), $user->user_login, wp_logout_url( get_page_link( $settings['page_for_login'] ) ) );
			
			//Your Subscription
			$results .= '<h2>' . __( 'Your Subscription', 'issuem-leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_start', '' );
			
			$results .= '<table>';
			$results .= '<thead>';
			$results .= '<tr>';
			$results .= '	<th>' . __( 'Status', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Type', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Payment Method', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Expiration', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '	<th>' . __( 'Cancel?', 'issuem-leaky-paywall' ) . '</th>';
			$results .= '</tr>';
			$results .= '</thead>';
			foreach( $sites as $site ) {
				$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
				
				$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
				$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
				if ( false === $level_id || empty( $settings['levels'][$level_id]['label'] ) ) {
					$level_name = __( 'Undefined', 'issuem-leaky-paywall' );
				} else {
					$level_name = stripcslashes( $settings['levels'][$level_id]['label'] );
				}
				
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
				
				$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
				$expires = apply_filters( 'do_leaky_paywall_profile_shortcode_expiration_column', $expires, $user, $mode, $site, $level_id );
				if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
					$expires = __( 'Never', 'issuem-leaky-paywall' );
				} else {
					$date_format = get_option( 'date_format' );
					$expires = mysql2date( $date_format, $expires );
				}
				
				$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
				if ( !empty( $plan ) && 'Canceled' !== $plan && 'Never' !== $expires ) {
					$expires = sprintf( __( 'Recurs on %s', 'issuem-leaky-paywall' ), $expires );	
				}
							
				$paid = leaky_paywall_has_user_paid( $user->user_email, $site );
				
				if ( 'subscription' === $paid ) {
					$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$cancel = sprintf( __( '<a href="%s">cancel</a>', 'issuem-leaky-paywall' ), '?cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id );
				} else {
					$cancel = '&nbsp;';
				}
				
				if ( !empty( $status ) && !empty( $level_name ) && !empty( $payment_gateway ) && !empty( $expires ) ) {
					$results .= '<tbody>';
					$results .= '	<td>' . ucfirst( $status ) . '</td>';
					$results .= '	<td>' . $level_name . '</td>';
					$results .= '	<td>' . leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) . '</td>';
					$results .= '	<td>' . $expires . '</td>';
					$results .= '	<td>' . $cancel . '</td>';
					$results .= '</tbody>';
				}
			}
			$results .= '</table>';
			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_end', '' );
			
			//Your Mobile Devices
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( is_plugin_active( 'unipress-api/unipress-api.php' ) ) {
				
				global $unipress_api;
					
				$results .= '<h2>' . __( 'Your Mobile Devices', 'issuem-leaky-paywall' ) . '</h2>';
				$results .= '<p>' . __( 'To generate a token for the mobile app, click the "Add New Mobile Device" button below.', 'issuem-leaky-paywall' ) . '</p>';
				$results .= apply_filters( 'leaky_paywall_profile_your_mobile_devices_start', '' );
				$results .= $unipress_api->leaky_paywall_subscriber_info_paid_subscriber_end( '' );
				$results .= apply_filters( 'leaky_paywall_profile_your_mobile_devices_end', '' );
				
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
								wp_set_password( $_POST['password1'], $user->ID );
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
			$results .= apply_filters( 'leaky_paywall_profile_your_profile_start', '' );
			$results .= '<form id="leaky-paywall-profile" action="" method="post">';
			
			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-username">' . __( 'Username', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-username" name="username" value="' . $user->user_login . '" disabled="disabled" readonly="readonly" />';
			$results .= '</p>';
			
			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-display-name">' . __( 'Display Name', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="' . $user->display_name . '" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-email">' . __( 'Email', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="' . $user->user_email . '" />';
			$results .= '</p>';


			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-password1">' . __( 'Password', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-gift-subscription-password2">' . __( 'Password (again)', 'issuem-leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-gift-subscription-password2" name="password2" value="" />';
			$results .= '</p>';
			
			$results .= wp_nonce_field( 'leaky-paywall-profile', 'leaky-paywall-profile-nonce', true, false );
			
			$results .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . __( 'Update Profile Information', 'issuem-leaky-paywall' ) . '"  /></p>'; 
			$results .= '</form>';
			$results .= apply_filters( 'leaky_paywall_profile_your_profile_end', '' );
			
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
						
			if ( false !== $expires = leaky_paywall_has_user_paid() ) {
				//Your Payment Information
				$results .= '<h2>' . __( 'Your Payment Information', 'issuem-leaky-paywall' ) . '</h2>';
				if ( !empty( $_POST['leaky-paywall-profile-stripe-cc-update-nonce'] ) ) {
					
					if ( wp_verify_nonce( $_POST['leaky-paywall-profile-stripe-cc-update-nonce'], 'leaky-paywall-profile-stripe-cc-update' ) ) {
						
						try {
							
							$secret_key = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
							foreach ( $sites as $site ) {
								$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
								if ( !empty( $subscriber_id ) ) {
									break;
								}
							}
														
							$cu = Stripe_Customer::retrieve( $subscriber_id );
							if ( !empty( $cu ) )
								if ( true === $cu->deleted )
									throw new Exception( __( 'Unable to find valid Stripe customer ID to unsubscribe. Please contact support', 'issuem-leaky-paywall' ) );
									
							if ( empty( $_POST['stripe-cc-number'] ) ) {
								throw new Exception( __( 'Credit Card Number Required', 'issuem-leaky-paywall' ) );
							}	
							if ( empty( $_POST['stripe-cc-exp-month'] ) ) {
								throw new Exception( __( 'Credit Card Expiration Month Required', 'issuem-leaky-paywall' ) );
							}	
							if ( empty( $_POST['stripe-cc-exp-year'] ) ) {
								throw new Exception( __( 'Credit Card Expiration Year Required', 'issuem-leaky-paywall' ) );
							}	
							if ( empty( $_POST['stripe-cc-cvc'] ) ) {
								throw new Exception( __( 'Credit Card Security Code (CVC) Required', 'issuem-leaky-paywall' ) );
							}	
							if ( empty( $_POST['stripe-cc-name'] ) ) {
								throw new Exception( __( "Credit Card Cardholder's Name Required", 'issuem-leaky-paywall' ) );
							}
															
							$subscriptions = $cu->subscriptions->all( 'limit=1' );
	
							foreach( $subscriptions->data as $susbcription ) {
								$sub = $cu->subscriptions->retrieve( $susbcription->id );
								$sub->card = array(
									'number' 	=> $_POST['stripe-cc-number'],
									'exp_month' => $_POST['stripe-cc-exp-month'],
									'exp_year' 	=> $_POST['stripe-cc-exp-year'],
									'cvc' 		=> $_POST['stripe-cc-cvc'],
									'name' 		=> $_POST['stripe-cc-name'],
								);
								$sub->save();
							}
							
							$results .= '<p>' . __( 'Your credit card has been successfully updated.', 'issuem-leaky-paywall' ) . '</p>';
							
						} catch ( Exception $e ) {
						
							$results = '<h1>' . sprintf( __( 'Error updating Credit Card information: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
							
						}
						
					}
					
				}
				$results .= apply_filters( 'leaky_paywall_profile_your_payment_info_start', '' );
				
				$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );
				
				foreach( $sites as $site ) {	
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					$expires = leaky_paywall_has_user_paid( $user->user_email, $site );

					if ( 'subscription' === $expires ) {
						switch( $payment_gateway ) {
							
							case 'stripe':
								$results .= '<h3>' . __( 'Update Credit Card', 'issuem-leaky-paywall' ) . '</h3>';
								$results .= '<form id="leaky-paywall-update-credit-card" action="" method="post">';
								
								$results .= '<p>';
								$results .= '<label class="lp-field-label" for="leaky-paywall-cc-number">' . __( 'Card Number', 'issuem-leaky-paywall' ) . '</label>';
								$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-number" name="stripe-cc-number" value="" placeholder="4242 4242 4242 4242" />';
								$results .= '</p>';
		
								$results .= '<p>';
								$results .= '<label class="lp-field-label" for="leaky-paywall-cc-expiration">' . __( 'Expiration Date', 'issuem-leaky-paywall' ) . '</label>';
								$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-exp-month" name="stripe-cc-exp-month" value="" placeholder="' . date_i18n( 'm', strtotime( '+1 Month' ) ) . '" />';
								$results .= '&nbsp;/&nbsp;';
								$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-exp-year" name="stripe-cc-exp-year" value="" placeholder="' . date_i18n( 'Y', strtotime( '+1 Year' ) ) . '" />';
								$results .= '</p>';
		
								$results .= '<p>';
								$results .= '<label class="lp-field-label" for="leaky-paywall-cc-cvc">' . __( 'Security Code (CVC)', 'issuem-leaky-paywall' ) . '</label>';
								$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-cvc" name="stripe-cc-cvc" value="" placeholder="777" />';
								$results .= '</p>';
		
								$results .= '<p>';
								$results .= '<label class="lp-field-label" for="leaky-paywall-cc-name">' . __( "Cardholder's Name", 'issuem-leaky-paywall' ) . '</label>';
								$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-name" name="stripe-cc-name" value="" placeholder="John Doe" />';
								$results .= '</p>';
								
								$results .= wp_nonce_field( 'leaky-paywall-profile-stripe-cc-update', 'leaky-paywall-profile-stripe-cc-update-nonce', true, false );
								
								$results .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . __( 'Update Credit Card Information', 'issuem-leaky-paywall' ) . '"  /></p>'; 
								$results .= '</form>';
								break;
								
							case 'paypal-standard':
							case 'paypal_standard':
								$paypal_url   = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
								$paypal_email = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
								$results .= '<p>' . __( "You can update your payment details through PayPal's website.", 'issuem-leaky-paywall' ) . '</p>';
								$results .= '<p><a href="' . $paypal_url . '"><img src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_pponly_142x27.png" border="0"></a></p>';
								break;
						}
						
						break; //We only want the first match
					}
				}
			}
			$results .= '</div>';
			$results .= apply_filters( 'leaky_paywall_profile_your_payment_info_end', '' );
			
		} else {
			
			$results .= do_leaky_paywall_login( array() );
			
		}
		
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_profile', 'do_leaky_paywall_profile' );
	
}
