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
		static $shortcode_count = 0; //Tracking the number of times this shortcode appears on the page
									 //So we don't display multiple instances for certain output
		
		$settings = get_issuem_leaky_paywall_settings();
		
		$defaults = array(
			'login_heading' 	=> __( 'Enter your email address to start your subscription:', 'issuem-leaky-paywall' ),
			'login_desc' 		=> __( 'Check your email for a link to start your subscription.', 'issuem-leaky-paywall' ),
			'plan_id'			=> $settings['plan_id'],
			'price'				=> $settings['price'],
			'recurring'			=> $settings['recurring'],
			'interval_count' 	=> $settings['interval_count'],
			'interval'			=> $settings['interval'],
			'description'		=> $settings['charge_description'],
			'payment_gateway' 	=> $settings['payment_gateway'],
		);
	
		// Merge defaults with passed atts
		// Extract (make each array element its own PHP var
		$args = shortcode_atts( $defaults, $atts );
		extract( $args );
		
		$results = '';

		if ( !empty( $_SESSION['issuem_lp_hash'] ) ) {
		
			$hash = $_SESSION['issuem_lp_hash'];
			kill_issuem_leaky_paywall_login_hash( $hash );
			
			if ( empty( $_SESSION['issuem_lp_subscriber'] ) ) {
			
				$results .= '<h1 class="error">' . sprintf( __( 'Sorry, this login link is invalid or has expired. <a href="%s">Try again?</a>', 'issuem-leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</h1>';	
				
			}
			
		}
		
		if ( !empty( $_SESSION['issuem_lp_email'] ) ) {
						
			if ( false !== $expires = issuem_leaky_paywall_has_user_paid( $_SESSION['issuem_lp_email'] ) ) {
			
				if ( 0 === $shortcode_count ) {
				
					$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
					
					$customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] );

					switch( $expires ) {
					
						case 'subscription':
							$_SESSION['issuem_lp_subscriber'] = $customer->hash;
							$results .= sprintf( __( 'Your subscription will automatically renew until you <a href="%s">cancel</a>.', 'issuem-leaky-paywall' ), '?cancel' );
							break;
							
						case 'unlimited':
							$_SESSION['issuem_lp_subscriber'] = $customer->hash;
							$results .= __( 'You are a lifetime subscriber!', 'issuem-leaky-paywall' );
							break;
					
						case 'canceled':
							$results .= sprintf( __( 'Your subscription has been canceled. You will continue to have access to %s until the end of your billing cycle. Thank you for the time you have spent subscribed to our site and we hope you will return soon!', 'issuem-leaky-paywall' ), $settings['site_name'] );
							break;
							
						default:
							$_SESSION['issuem_lp_subscriber'] = $customer->hash;
							$results .= sprintf( __( 'You are subscribed via %s until %s.', 'issuem-leaky-paywall' ), issuem_translate_payment_gateway_slug_to_name( $customer->payment_gateway ), date_i18n( get_option('date_format'), strtotime( $expires ) ) );
							
					}
					
					$results .= '<h3>' . __( 'Thank you very much for subscribing.', 'issuem-leaky-paywall' ) . '</h3>';
					$results .= '<h1><a href="?logout">' . __( 'Log Out', 'issuem-leaky-paywall' ) . '</a></h1>';
					$results .= '</div>';
				
				}
				
			} else {
				
				if ( 'stripe' === $payment_gateway ) {
					
					$stripe_price = number_format( $price, '2', '', '' ); //no decimals
					$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
				
					if ( !empty( $_POST['stripeToken'] ) ) {
						
						try {
		
							$token = $_POST['stripeToken'];
							
							if ( $existing_customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) )
								$cu = Stripe_Customer::retrieve( $existing_customer->subscriber_id );
								
							if ( !empty( $cu ) )
								if ( true === $cu->deleted )
									$cu = array();
												
							$customer_array = array(
									'email' => $_SESSION['issuem_lp_email'],
									'card'  => $token,
							);
						
							if ( 'on' === $recurring && !empty( $plan_id ) ) {
								
								$customer_array['plan'] = $plan_id;	
								
								if ( !empty( $cu ) )
									$cu->updateSubscription( array( 'plan' => $plan_id ) );
								else
									$cu = Stripe_Customer::create( $customer_array );
								
							} else {
							
								if ( !empty( $cu ) ) {
									
									$cu->card = $token;
									$cu->save();
									
								} else {
										
									$cu = Stripe_Customer::create( $customer_array );
									
								}
								
								$charge = Stripe_Charge::create(array(
									'customer' 		=> $cu->id,
									'amount'   		=> $stripe_price,
									'currency' 		=> apply_filters( 'issuem_leaky_paywall_stripe_currency', 'usd' ), //currently Stripe only supports USD and CAD
									'description'	=> $description,
								));
							
							}
							
							$unique_hash = issuem_leaky_paywall_hash( $_SESSION['issuem_lp_email'] );
							
							$args['payment_status'] = 'active';
								
							if ( !empty( $existing_customer ) )
								issuem_leaky_paywall_update_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args ); //if the email already exists, we want to update the subscriber, not create a new one
							else
								issuem_leaky_paywall_new_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args );
								
							$_SESSION['issuem_lp_subscriber'] = $unique_hash;
							
							$results .= '<h1>' . __( 'Successfully subscribed!' , 'issuem-leaky-paywall' ) . '</h1>';
							
						} catch ( Exception $e ) {
							
							$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
							
						}
						
					} else {
						
						if ( 'on' === $recurring && !empty( $plan_id ) ) {
							
							try {
									
								$stripe_plan = Stripe_Plan::retrieve( $plan_id );
													
								$results .= '<h2>' . sprintf( __( 'Subscribe for just $%s %s', 'issuem-leaky-paywall' ), number_format( (float)$stripe_plan->amount/100, 2 ), issuem_leaky_paywall::human_readable_interval( $stripe_plan->interval_count, $stripe_plan->interval ) ) . '</h2>';
								
								if ( $stripe_plan->trial_period_days ) {
									$results .= '<h3>' . sprintf( __( 'Free for the first %s day(s)', 'issuem-leaky-paywall' ), $stripe_plan->trial_period_days ) . '</h3>';
								}
								
								$results .= '<form action="" method="post">
											  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
													  data-key="' . $publishable_key . '"
													  data-plan="' . $plan_id . '" 
													  data-description="' . $description . '">
											  </script>
											</form>';
								
								$results .= '<h3>' . __( '(You can cancel anytime with just two clicks.)', 'issuem-leaky-paywall' ) . '</h3>';
								
							} catch ( Exception $e ) {
		
								$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
	
							}
							
						} else {
						
							$results .= '<h2>' . sprintf( __( 'Subscribe for just $%s %s', 'issuem-leaky-paywall' ), $price, issuem_leaky_paywall::human_readable_interval( $interval_count, $interval ) ) . '</h2>';
								
							$results .= '<form action="" method="post">
										  <script src="https://checkout.stripe.com/v2/checkout.js" class="stripe-button"
												  data-key="' . $publishable_key . '"
												  data-amount="' . $stripe_price . '" 
												  data-description="' . $description . '">
										  </script>
										</form>';
						
						}
					
					}
				
				} else if ( 'paypal_standard' === $payment_gateway ) {
					
					if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-return'] ) ) {
						
						if ( !empty( $_REQUEST['tx'] ) ) //if PDT is enabled
							$transaction_id = $_REQUEST['tx'];
						else if ( !empty( $_REQUEST['txn_id'] ) ) //if PDT is not enabled
							$transaction_id = $_REQUEST['txn_id'];
						else
							$transaction_id = NULL;
							
						if ( !empty( $_REQUEST['cm'] ) )
							$transient_transaction_id = $_REQUEST['cm'];
						else
							$transient_transaction_id = NULL;
				
						if ( !empty( $_REQUEST['amt'] ) ) //if PDT is enabled
							$transaction_amount = $_REQUEST['amt'];
						else if ( !empty( $_REQUEST['mc_gross'] ) ) //if PDT is not enabled
							$transaction_amount = $_REQUEST['mc_gross'];
						else
							$transaction_amount = NULL;
				
						if ( !empty( $_REQUEST['st'] ) ) //if PDT is enabled
							$transaction_status = $_REQUEST['st'];
						else if ( !empty( $_REQUEST['payment_status'] ) ) //if PDT is not enabled
							$transaction_status = $_REQUEST['payment_status'];
						else
							$transaction_status = NULL;
									
						if ( !empty( $transaction_id ) && !empty( $transaction_amount ) && !empty( $transaction_status ) ) {
				
							try {
				
								$cu = new stdClass;
								$cu->id = $transaction_id; //temporary, will be replaced with subscriber ID during IPN
							
								if ( number_format( $transaction_amount, '2', '', '' ) != number_format( $price, '2', '', '' ) )
									throw new Exception( sprintf( __( 'Error: Amount charged is not the same as the cart total! %s | %s', 'issuem-leaky-paywall' ), $AMT, $transaction_object->total ) );
									
								switch( strtolower( $transaction_status ) ) {
									
									case 'denied' :
										throw new Exception( __( 'Error: PayPal denied this payment.', 'issuem-leaky-paywall' ) );
										break;
									case 'failed' :
										throw new Exception( __( 'Error: Payment failed.', 'issuem-leaky-paywall' ) );
										break;
									case 'completed':
									case 'success':
									case 'canceled_reversal':
									case 'processed' :
									default:
										$args['payment_status'] = 'active';
										break;
									
								}
									
								$error = false;
				
							}
							catch ( Exception $e ) {
								
								$error = true;
								$results = '<h1>' . sprintf( __( 'Error processing request: %s', 'issuem-leaky-paywall' ), $e->getMessage() ) . '</h1>';
				
							}
							
							if ( !$error ) {
				
								$unique_hash = issuem_leaky_paywall_hash( $_SESSION['issuem_lp_email'] );
								
								if ( $existing_customer = get_issuem_leaky_paywall_subscriber_by_email( $_SESSION['issuem_lp_email'] ) )
									issuem_leaky_paywall_update_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args ); //if the email already exists, we want to update the subscriber, not create a new one
								else
									issuem_leaky_paywall_new_subscriber( $unique_hash, $_SESSION['issuem_lp_email'], $cu, $args );
									
								$_SESSION['issuem_lp_subscriber'] = $unique_hash;
								
								$results .= '<h1>' . __( 'Successfully subscribed!' , 'issuem-leaky-paywall' ) . '</h1>';
								
							}
							
						}				
						
					} else {
						
						$mode = 'on' === $settings['test_mode'] ? 'sandbox' : '';
						$paypal_account = 'on' === $settings['test_mode'] ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
						
						if ( 'on' === $recurring ) {
																					
							$results .= '<h2>' . sprintf( __( 'Subscribe for just $%s %s', 'issuem-leaky-paywall' ), $price, issuem_leaky_paywall::human_readable_interval( $interval_count, $interval ) ) . '</h2>';
								
							$results .= '<script 
											data-env="' . $mode . '" 
											data-callback="' . add_query_arg( 'issuem-leaky-paywall-paypal-standard-ipn', '1', get_site_url() . '/' ) . '"
											data-return="' . add_query_arg( 'issuem-leaky-paywall-paypal-standard-return', '1', get_page_link( $settings['page_for_subscription'] ) ) . '"
											data-cancel_return="' . add_query_arg( 'issuem-leaky-paywall-paypal-standard-cancel-return', '1', get_page_link( $settings['page_for_subscription'] ) ) . '" 
											data-src="1" 
											data-period="' . strtoupper( substr( $interval, 0, 1 ) ) . '" 
											data-recurrence="' . $interval_count . '" 
											data-currency="' . apply_filters( 'issuem_leaky_paywall_paypal_currency', 'USD' ) . '" 
											data-amount="' . $price. '" 
											data-name="' . $description . '" 
											data-button="subscribe" src="' . ISSUEM_LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . $paypal_account . '"
											data-custom="' . $_SESSION['issuem_lp_email'] . '" 
											data-no_note="1",
											data-no_shipping="1",
											data-shipping="0",
										></script>';
							
							$results .= '<h3>' . __( '(You can cancel anytime with just two clicks.)', 'issuem-leaky-paywall' ) . '</h3>';
							
						} else {
						
							$results .= '<h2>' . sprintf( __( 'Subscribe for just $%s %s', 'issuem-leaky-paywall' ), $price, issuem_leaky_paywall::human_readable_interval( $interval_count, $interval ) ) . '</h2>';
								
							$results .= '<script 
											data-env="' . $mode . '" 
											data-callback="' . add_query_arg( 'issuem-leaky-paywall-paypal-standard-ipn', '1', get_site_url() . '/' ) . '" 
											data-return="' . get_page_link( $settings['page_for_subscription'] ) . '"
											data-cancel_return="' . get_page_link( $settings['page_for_subscription'] ) . '" 
											data-tax="0" 
											data-shipping="0" 
											data-currency="' . apply_filters( 'issuem_leaky_paywall_paypal_currency', 'USD' ) . '" 
											data-amount="' . $price. '" 
											data-quantity="1" 
											data-name="' . $description . '" 
											data-button="buynow" src="' . ISSUEM_LEAKY_PAYWALL_URL . '/js/paypal-button.min.js?merchant=' . $paypal_account . '"
											data-custom="' . $_SESSION['issuem_lp_email'] . '" 
											data-no_note="1",
											data-no_shipping="1",
											data-shipping="0",
										></script>';
						
						}
					
					}

				}
				
			} 
			
		} else {
		
			if ( 0 === $shortcode_count ) {
				
				$results .= '<div class="issuem-leaky-paywall-subscriber-info">';
			
				$args = array(
					'heading' 		=> $login_heading,
					'description' 	=> $login_desc,
				);
	
				$results .= do_issuem_leaky_paywall_login( $args );
				$results .= '</div>';
			
			}

		}
		
		$shortcode_count++;
				
		return $results;
		
	}
	add_shortcode( 'leaky_paywall_subscription', 'do_issuem_leaky_paywall_subscription' );
	
}
