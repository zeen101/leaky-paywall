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
			
            $results .= apply_filters( 'leaky_paywall_before_login_form', '' );
                        		
			add_action( 'login_form_bottom', 'leaky_paywall_add_lost_password_link' );
			$args = array(
				'echo' => false,
				'redirect' => $page_link,
			);
			$results .= wp_login_form( apply_filters( 'leaky_paywall_login_form_args', $args ) );
		
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
		
		if ( isset( $_REQUEST['level_id'] ) ) {
			return do_leaky_paywall_register_form();
		}
		
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
			
			$results .= '<p>' . sprintf( __( 'Welcome %s, you are currently logged in. <a href="%s">Click here to log out.</a>', 'leaky-paywall' ) . '</p>', $user->user_login, wp_logout_url( get_page_link( $settings['page_for_login'] ) ) );
			
			//Your Subscription
			$results .= '<h2 class="leaky-paywall-profile-subscription-title">' . __( 'Your Subscription', 'leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_start', '' );
			
			$profile_table = '<table class="leaky-paywall-profile-subscription-details">';
			$profile_table .= '<thead>';
			$profile_table .= '<tr>';
			$profile_table .= '	<th>' . __( 'Status', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Type', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Payment Method', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Expiration', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Cancel?', 'leaky-paywall' ) . '</th>';
			$profile_table .= '</tr>';
			$profile_table .= '</thead>';
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
					$expires = __( 'Never', 'leaky-paywall' );
				} else {
					$date_format = get_option( 'date_format' );
					$expires = mysql2date( $date_format, $expires );
				}
				
				$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
				if ( !empty( $plan ) && 'Canceled' !== $plan && 'Never' !== $expires ) {
					$expires = sprintf( __( 'Recurs on %s', 'leaky-paywall' ), $expires );	
				}
							
				$paid = leaky_paywall_has_user_paid( $user->user_email, $site );
				
				if ( 'subscription' === $paid ) {
					$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$cancel = sprintf( __( '<a href="%s">cancel</a>', 'leaky-paywall' ), '?cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id );
				} else if ( !empty( $plan ) && 'Canceled' == $plan ) {
					$cancel = 'You have cancelled your subscription, but your account will remain active until your expiration date.';
				} else {
					$cancel = '&nbsp;';
				}
				
				if ( !empty( $status ) && !empty( $level_name ) && !empty( $payment_gateway ) && !empty( $expires ) ) {
					$profile_table .= '<tbody>';
					$profile_table .= '	<td>' . ucfirst( $status ) . '</td>';
					$profile_table .= '	<td>' . $level_name . '</td>';
					$profile_table .= '	<td>' . leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway ) . '</td>';
					$profile_table .= '	<td>' . $expires . '</td>';
					$profile_table .= '	<td>' . $cancel . '</td>';
					$profile_table .= '</tbody>';
				}
			}
			$profile_table .= '</table>';
			$results .= apply_filters( 'leaky_paywall_profile_table', $profile_table, $user, $sites, $mode, $settings );
			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_end', '' );
			
			//Your Mobile Devices
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( is_plugin_active( 'unipress-api/unipress-api.php' ) ) {
				
				global $unipress_api;
					
				$results .= '<h2>' . __( 'Your Mobile Devices', 'leaky-paywall' ) . '</h2>';
				$results .= '<p>' . __( 'To generate a token for the mobile app, click the "Add New Mobile Device" button below.', 'leaky-paywall' ) . '</p>';
				$results .= apply_filters( 'leaky_paywall_profile_your_mobile_devices_start', '' );
				$results .= $unipress_api->leaky_paywall_subscriber_info_paid_subscriber_end( '' );
				$results .= apply_filters( 'leaky_paywall_profile_your_mobile_devices_end', '' );
				
			}
			
			//Your Profile
			$results .= '<h2>' . __( 'Your Profile', 'leaky-paywall' ) . '</h2>';
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
							$args['user_login'] = sanitize_text_field( $_POST['username'] );
						}
						
						if ( !empty( $_POST['displayname'] ) ) {
							$args['display_name'] = sanitize_text_field( $_POST['displayname'] );
						}
						
						if ( !empty( $_POST['email'] ) ) {
							if ( is_email( $_POST['email'] ) ) {
								$args['user_email'] = sanitize_text_field( $_POST['email'] );
							} else {
								throw new Exception( __( 'Invalid email address.', 'leaky-paywall' ) );
							}
						}
						
						if ( !empty( $_POST['password1'] ) && !empty( $_POST['password2'] ) ) {
							if ( $_POST['password1'] === $_POST['password2'] ) {
								wp_set_password( sanitize_text_field( $_POST['password1'] ), $user->ID );
							} else {
								throw new Exception( __( 'Passwords do not match.', 'leaky-paywall' ) );
							}
						}
						
						$user_id = wp_update_user( $args );
												
						if ( is_wp_error( $user_id ) ) {
							throw new Exception( $user_id->get_error_message() );
						} else {
							$user = get_userdata( $user_id ); //Refresh the user object				
							$results .= '<p class="save">' . __( 'Profile Changes Saved.', 'leaky-paywall' ) . '</p>';

							do_action( 'leaky_paywall_after_profile_changes_saved', $user_id, $args );
							
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
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-username">' . __( 'Username', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-username" name="username" value="' . $user->user_login . '" disabled="disabled" readonly="readonly" />';
			$results .= '</p>';
			
			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-display-name">' . __( 'Display Name', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="' . $user->display_name . '" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-email">' . __( 'Email', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="' . $user->user_email . '" />';
			$results .= '</p>';


			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-password1">' . __( 'New Password', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-gift-subscription-password2">' . __( 'New Password (again)', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-gift-subscription-password2" name="password2" value="" />';
			$results .= '</p>';
			
			$results .= wp_nonce_field( 'leaky-paywall-profile', 'leaky-paywall-profile-nonce', true, false );
			
			$results .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . __( 'Save Profile Changes', 'leaky-paywall' ) . '"  /></p>'; 
			$results .= '</form>';
			$results .= apply_filters( 'leaky_paywall_profile_your_profile_end', '' );
			
			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
						
			if ( false !== $expires = leaky_paywall_has_user_paid() ) {
				//Your Payment Information
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

							$subscriptions = $cu->subscriptions->all( array('limit' => '1') );

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
						$payment_form = '';
						
						switch( $payment_gateway ) {
							
							case 'stripe':
								$payment_form .= '<h3>' . __( 'Update Credit Card', 'issuem-leaky-paywall' ) . '</h3>';
								$payment_form .= '<form id="leaky-paywall-update-credit-card" action="" method="post">';
								
								$payment_form .= '<p>';
								$payment_form .= '<label class="lp-field-label" for="leaky-paywall-cc-number">' . __( 'Card Number', 'issuem-leaky-paywall' ) . '</label>';
								$payment_form .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-number" name="stripe-cc-number" value="" placeholder="4242 4242 4242 4242" />';
								$payment_form .= '</p>';
		
								$payment_form .= '<p>';
								$payment_form .= '<label class="lp-field-label" for="leaky-paywall-cc-expiration">' . __( 'Expiration Date', 'issuem-leaky-paywall' ) . '</label>';
								$payment_form .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-exp-month" name="stripe-cc-exp-month" value="" placeholder="' . date_i18n( 'm', strtotime( '+1 Month' ) ) . '" />';
								$payment_form .= '&nbsp;/&nbsp;';
								$payment_form .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-exp-year" name="stripe-cc-exp-year" value="" placeholder="' . date_i18n( 'Y', strtotime( '+1 Year' ) ) . '" />';
								$payment_form .= '</p>';
		
								$payment_form .= '<p>';
								$payment_form .= '<label class="lp-field-label" for="leaky-paywall-cc-cvc">' . __( 'Security Code (CVC)', 'issuem-leaky-paywall' ) . '</label>';
								$payment_form .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-cvc" name="stripe-cc-cvc" value="" placeholder="777" />';
								$payment_form .= '</p>';
		
								$payment_form .= '<p>';
								$payment_form .= '<label class="lp-field-label" for="leaky-paywall-cc-name">' . __( "Cardholder's Name", 'issuem-leaky-paywall' ) . '</label>';
								$payment_form .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-cc-name" name="stripe-cc-name" value="" placeholder="John Doe" />';
								$payment_form .= '</p>';
								
								$payment_form .= wp_nonce_field( 'leaky-paywall-profile-stripe-cc-update', 'leaky-paywall-profile-stripe-cc-update-nonce', true, false );
								
								$payment_form .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . __( 'Update Credit Card Information', 'issuem-leaky-paywall' ) . '"  /></p>'; 
								$payment_form .= '</form>';
								break;
								
							case 'paypal-standard':
							case 'paypal_standard':
								$paypal_url   = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
								$paypal_email = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
								$payment_form .= '<p>' . __( "You can update your payment details through PayPal's website.", 'leaky-paywall' ) . '</p>';
								$payment_form .= '<p><a href="' . $paypal_url . '"><img src="https://www.paypalobjects.com/webstatic/en_US/btn/btn_pponly_142x27.png" border="0"></a></p>';
								break;
						}
						
						
						$results .= '<h2>' . __( 'Your Payment Information', 'leaky-paywall' ) . '</h2>';
						$results .= $payment_form;
						
						break; //We only want the first match
					}
				}
			} else if ( !empty( $plan ) && 'Canceled' == $plan ) {
				$results .= '<h2>' . __( 'Your Subscription Has Been Cancelled', 'leaky-paywall' ) . '</h2>';
				$results .= '<p>' . sprintf( __( 'You have cancelled your subscription, but your account will remain active until your expiration date. To reactivate your subscription, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
			} else {
				
				$results .= '<h2>' . __( 'Your Account is Not Currently Active', 'leaky-paywall' ) . '</h2>';
				$results .= '<p>' . sprintf( __( 'To reactivate your account, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
				
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

/**
 * Outputs the default Leaky Paywall register form
 *
 * @since 3.7.0
 */
function do_leaky_paywall_register_form() {

	$settings = get_leaky_paywall_settings();

	$level_id = isset($_GET['level_id']) ? $_GET['level_id'] : null;

	if ( is_null( $level_id ) ) {
		$content = '<p>Please <a href="' . get_page_link( $settings['page_for_subscription'] ) . '">go to the subscribe page</a> to choose a subscription level.</p>';
		return $content;
	}

	$level = get_leaky_paywall_subscription_level( $level_id );

	global $blog_id;
	if ( is_multisite_premium() ){
		$site = '_' . $blog_id;
	} else {
		$site = '';
	}

	$currency = leaky_paywall_get_currency();
	$currencies = leaky_paywall_supported_currencies();
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];

	$userdata = get_userdata( get_current_user_id() );
	if ( !empty( $userdata ) ) {
		$email = $userdata->user_email;
		$username = $userdata->user_login;
		$first = $userdata->first_name;
		$last = $userdata->last_name;
	} else {
		$email = leaky_paywall_old_form_value( 'email_address', false );
		$username = leaky_paywall_old_form_value( 'username', false );
		$first = leaky_paywall_old_form_value( 'first_name', false );
		$last = leaky_paywall_old_form_value( 'last_name', false );
	}
	ob_start();

	// show any error messages after form submission
	leaky_paywall_show_error_messages( 'register' );
		?>

		<div class="leaky-paywall-subscription-details-wrapper">

			<h3 class="leaky-paywall-subscription-details-title"><?php printf( __( 'Your Subscription', 'leaky-paywall' ) ); ?></h3>

			<ul class="leaky-paywall-subscription-details">
				<li><strong><?php printf( __( 'Subscription Name:', 'leaky-paywall' ) ); ?></strong> <?php echo $level['label']; ?></li>
				<li><strong><?php printf( __( 'Subscription Length:', 'leaky-paywall' ) ); ?></strong> <?php echo $level['subscription_length_type'] == 'unlimited' ? 'Forever' : $level['interval_count'] . ' ' . $level['interval'] . ( $level['interval_count'] > 1  ? 's' : '' ); ?></li>
				<li><strong><?php printf( __( 'Recurring:', 'leaky-paywall' ) ); ?> </strong> <?php echo !empty( $level['recurring'] ) && $level['recurring'] == 'on' ? 'Yes' : 'No'; ?></li>
				<li><strong><?php printf( __( 'Content Access:', 'leaky-paywall' ) ); ?></strong>
					
				<?php 
					$content_access_description = '';
					$i = 0;
					foreach( $level['post_types'] as $type ) {

						if ( $i > 0 ) {
							$content_access_description .= ', ';
						}

						if ( $type['allowed'] == 'unlimited' ) {
							$content_access_description .= ucfirst( $type['allowed'] ) . ' ' . $type['post_type'] . 's';
						} else {
							$content_access_description .= $type['allowed_value'] . ' ' . $type['post_type'] . 's';
						}
						
						$i++;
					}	
					
					echo apply_filters( 'leaky_paywall_content_access_description', $content_access_description, $level, $level_id );
				?>	
				
				</li>
				
			</ul>

			<p class="leaky-paywall-subscription-total">
				<?php if ( $level['price'] > 0 ) {
					$total = leaky_paywall_get_current_currency_symbol() . number_format( $level['price'], 2 );
				} else {
					$total = 'Free';
				} ?>

			    <strong><?php printf( __( 'Total:', 'leaky-paywall' ) ); ?></strong> <?php echo apply_filters( 'leaky_paywall_your_subscription_total', $total, $level ); ?>
			</p>

		</div>

		<?php do_action( 'leaky_paywall_before_registration_form', $level ); ?>

		<form action="" method="POST" name="payment-form" id="leaky-paywall-payment-form" class="leaky-paywall-payment-form">
		  <span class="payment-errors"></span>

		  <div class="leaky-paywall-user-fields">

			  <h3><?php printf( __( 'Your Details', 'leaky-paywall' ) ); ?></h3>

			  <p class="form-row first-name">
			    <label for="first_name"><?php printf( __( 'First Name', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="first_name" value="<?php echo $first; ?>" />
			  </p>

			  <p class="form-row last-name">
			    <label for="last_name"><?php printf( __( 'Last Name', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="last_name" value="<?php echo $last; ?>"/>
			  </p>
			 
			  <p class="form-row email-address">
			    <label for="email_address"><?php printf( __( 'Email Address', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="email_address" value="<?php echo $email; ?>" <?php echo !empty( $email ) && !empty( $userdata ) ? 'disabled="disabled"' : ''; ?>/>
			  </p>

		  </div>

		  <div class="leaky-paywall-account-fields">

			  <h3><?php printf( __( 'Account Details', 'leaky-paywall' ) ); ?></h3>

			  <p class="form-row username">
			    <label for="username"><?php printf( __( 'Username', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="text" size="20" name="username" value="<?php echo $username; ?>" <?php echo !empty( $username ) && !empty( $userdata ) ? 'disabled="disabled"' : ''; ?>/>
			  </p>
			  
			  <?php if ( !is_user_logged_in() ) { ?>

			  <p class="form-row password">
			    <label for="password"><?php printf( __( 'Password', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="password" size="20" name="password"/>
			  </p>

			  <p class="form-row confirm-password">
			    <label for="confirm_password"><?php printf( __( 'Confirm Password', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
			    <input type="password" size="20" name="confirm_password"/>
			  </p>
			  
			  <?php } ?>

		  </div>

		  <?php do_action( 'leaky_paywall_after_password_registration_field', $level_id, $level ); ?>

		  <?php 

		  	$gateways = leaky_paywall_get_enabled_payment_gateways(); 

		  	if ( $gateways && $level['price'] != 0 ) {

		  		foreach( $gateways as $key => $gateway ) {

		  			echo '<input type="hidden" name="gateway" value="' . esc_attr( $key ) . '" />';

		  		}
		  	} else {
		  		echo '<input type="hidden" name="gateway" value="free_registration" />';
		  	}

		  ?>

		  <?php 
		  	if ( $level['price'] > 0 ) {
		  		$level_price = number_format( $level['price'], 2 );
		  	} else {
		  		$level_price = 0;
		  	}

		  ?>

		  <input type="hidden" name="level_price" value="<?php echo $level_price; ?>"/>
		  <input type="hidden" name="currency" value="<?php echo $currency; ?>"/>
		  <input type="hidden" name="description" value="<?php echo $level['label']; ?>"/>
		  <input type="hidden" name="level_id" value="<?php echo $level_id; ?>"/>
		  <input type="hidden" name="interval" value="<?php echo $level['interval']; ?>"/>
		  <input type="hidden" name="interval_count" value="<?php echo $level['interval_count']; ?>"/>
		  <input type="hidden" name="recurring" value="<?php echo empty( $level['recurring'] ) ? '' : $level['recurring']; ?>"/>
		  <input type="hidden" name="site" value="<?php echo $site; ?>"/>

		  <input type="hidden" name="leaky_paywall_register_nonce" value="<?php echo wp_create_nonce('leaky-paywall-register-nonce' ); ?>"/>

		  <?php if ( $level_price > 0 ) {
		  	?>
		  	<h3><?php printf( __( 'Payment Information', 'leaky-paywall' ) ); ?></h3>
		  	<?php 
		  } ?>
		  
		  <?php do_action( 'leaky_paywall_before_registration_submit_field', $gateways ); ?>

		  <div class="leaky-paywall-checkout-button">
		  	<button id="leaky-paywall-submit" type="submit"><?php printf( __( 'Subscribe', 'leaky-paywall' ) ); ?></button>
		  </div>
		</form>

		<?php 

	$content = ob_get_contents();
	ob_end_clean();

	return $content; 

}
add_shortcode( 'leaky_paywall_register_form', 'do_leaky_paywall_register_form' );
