<?php
/**
 * Registers zeen101's Leaky Paywall class
 *
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

/**
 * This class registers the main issuem functionality
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'Leaky_Paywall' ) ) {
	
	class Leaky_Paywall {
		
		private $plugin_name	= LEAKY_PAYWALL_NAME;
		private $plugin_slug	= LEAKY_PAYWALL_SLUG;
		private $basename		= LEAKY_PAYWALL_BASENAME;
		
		/**
		 * Class constructor, puts things in motion
		 *
		 * @since 1.0.0
		 */
		function __construct() {
		
			if ( function_exists( 'sessions_status' ) ) {
				if ( PHP_SESSION_NONE === session_status() )
					session_start();
			} else if ( function_exists( 'session_id' ) ){
				if ( '' === session_id() )
					session_start();
			} else {
				session_start();
			}
		
			$settings = $this->get_settings();
			
			add_action( 'admin_notices', array( $this, 'update_notices' ) );
		
			add_action( 'admin_init', array( $this, 'upgrade' ) );
			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_wp_enqueue_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'admin_wp_print_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
					
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			//Premium Plugin Filters
			add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins' ) );
			
			add_action( 'wp', array( $this, 'process_requests' ) );
			
			if ( 'on' === $settings['restrict_pdf_downloads'] )
				add_filter( 'issuem_pdf_attachment_url', array( $this, 'issuem_pdf_attachment_url' ), 10, 2 );
			
			if ( in_array( 'stripe', $settings['payment_gateway'] ) ) {
				
				if ( !empty( $settings['test_secret_key'] ) || !empty( $settings['live_secret_key'] ) ) {
										
					// Initialized Stripe...
					if ( !class_exists( 'Stripe' ) )
						require_once('include/stripe/lib/Stripe.php');
					
					$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
					Stripe::setApiKey( $secret_key );
					Stripe::setApiVersion( '2014-06-17' ); //Last version before Stripe changed subscription model
									
				}
			
			}
			
			if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) ) {
				
				if ( empty( $settings['paypal_live_api_username'] ) || empty( $settings['paypal_live_api_password'] ) || empty( $settings['paypal_live_api_secret'] ) ) {
					
					add_action( 'admin_notices', array( $this, 'paypal_standard_secure_notice' ) );
					
				}
				
			}
			
		}
		
		function issuem_pdf_attachment_url( $attachment_url, $attachment_id ) {
			return esc_url( add_query_arg( 'issuem-pdf-download', $attachment_id ) );
		}
		
		/**
		 * Initialize pigeonpack Admin Menu
		 *
		 * @since 1.0.0
		 * @uses add_menu_page() Creates Pigeon Pack menu
		 * @uses add_submenu_page() Creates Settings submenu to Pigeon Pack menu
		 * @uses add_submenu_page() Creates Help submenu to Pigeon Pack menu
		 * @uses do_action() To call 'pigeonpack_admin_menu' for future addons
		 */
		function admin_menu() {
					
			add_menu_page( __( 'Leaky Paywall', 'issuem-leaky-paywall' ), __( 'Leaky Paywall', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $this, 'settings_page' ), LEAKY_PAYWALL_URL . '/images/issuem-16x16.png' );
			
			add_submenu_page( 'issuem-leaky-paywall', __( 'Settings', 'issuem-leaky-paywall' ), __( 'Settings', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $this, 'settings_page' ) );
						
			add_submenu_page( 'issuem-leaky-paywall', __( 'Subscribers', 'issuem-leaky-paywall' ), __( 'Subscribers', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-subscribers', array( $this, 'subscribers_page' ) );
									
			add_submenu_page( false, __( 'Update', 'issuem-leaky-paywall' ), __( 'Update', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-update', array( $this, 'update_page' ) );
			
		}
		
		function process_requests() {
				
			$settings = $this->get_settings();
			
			$response = leaky_paywall_maybe_process_payment();
			if ( is_wp_error( $response ) ) {
				$args = array(
					'response' => 401,
					'back_link' => true,
				);		
				wp_die( $response, '', $args );
			}
			
			if ( leaky_paywall_maybe_process_webhooks() )
				die(); //no point in loading the whole page for webhooks

			$has_subscriber_paid = leaky_paywall_has_user_paid();
											
			if ( isset( $_REQUEST['issuem-pdf-download'] ) ) {
				
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
			
			if ( is_singular() ) {
				
				global $blog_id;
				if ( is_multisite() ){
					$site = '_' . $blog_id;
				} else {
					$site = '';
				}
			
				if ( !current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) { //Admins can see it all
				
					// We don't ever want to block the login, subscription
					if ( !is_page( array( $settings['page_for_login'], $settings['page_for_subscription'], $settings['page_for_profile'] ) ) ) {
					
						global $post;
						$post_type_id = '';
						$restricted_post_type = '';
						$is_restricted = false;
						
						$restrictions = leaky_paywall_subscriber_restrictions();
						
						if ( empty( $restrictions ) )
							$restrictions = $settings['restrictions']['post_types']; //default restrictions
																					
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
						
						if ( $is_restricted ) {
							
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
														
							if ( !empty( $_COOKIE['issuem_lp' . $site] ) )
								$available_content = maybe_unserialize( stripslashes( $_COOKIE['issuem_lp' . $site] ) );
							
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
							
							$serialized_available_content = maybe_serialize( $available_content );
							setcookie( 'issuem_lp' . $site, $serialized_available_content, $expiration, '/' );
							$_COOKIE['issuem_lp' . $site] = $serialized_available_content;
							
						}
						
						return; //We don't need to process anything else after this
						
					}
					
				}
	
			}
			
			if ( $has_subscriber_paid ) {
						
				if ( 
					(
						( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) ) 
						|| ( !empty( $settings['page_for_profile'] ) && is_page( $settings['page_for_profile'] )  )
					)
					&& isset( $_REQUEST['cancel'] ) ) {
					
					wp_die( leaky_paywall_cancellation_confirmation() );
					
				}
				
			
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {
					
					if ( !empty( $settings['page_for_profile'] ) ) {
						wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
					} else if ( !empty( $settings['page_for_subscription'] ) ) {
						wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
					}
					
				}
			
			} else {
			
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] )  && !empty( $_REQUEST['r'] ) ) {

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
					
					return; //We don't need to process anything else after this
					
				}
						
			}
			
		}
		
		function the_content_paywall( $content ) {
		
			$settings = $this->get_settings();	
					
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
	
			$settings = $this->get_settings();
			
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
		
		/**
		 * Prints backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_print_styles() {
		
			global $hook_suffix;
			
			if ( 'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix
				|| 'toplevel_page_issuem-leaky-paywall' === $hook_suffix )
				wp_enqueue_style( 'leaky_paywall_admin_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-admin.css', '', LEAKY_PAYWALL_VERSION );
				
			if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix )
				wp_enqueue_style( 'leaky_paywall_post_style', LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-post.css', '', LEAKY_PAYWALL_VERSION );
			
		}
	
		/**
		 * Enqueues backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_enqueue_scripts( $hook_suffix ) {
			
			if ( 'toplevel_page_issuem-leaky-paywall' === $hook_suffix ) {
				wp_enqueue_script( 'leaky_paywall_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-settings.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION );
			}
				
			if ( 'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix ) {
				wp_enqueue_script( 'leaky_paywall_subscribers_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-subscribers.js', array( 'jquery-ui-datepicker' ), LEAKY_PAYWALL_VERSION );
				wp_enqueue_style( 'jquery-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css' );
			}
				
			if ( 'post.php' === $hook_suffix|| 'post-new.php' === $hook_suffix )
				wp_enqueue_script( 'leaky_paywall_post_js', LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-post.js', array( 'jquery' ), LEAKY_PAYWALL_VERSION );
				
			
		}
		
		/**
		 * Enqueues frontend scripts and styles
		 *
		 * @since 1.0.0
		 */
		function frontend_scripts() {
			
			wp_enqueue_style( 'issuem-leaky-paywall', LEAKY_PAYWALL_URL . '/css/issuem-leaky-paywall.css', '', LEAKY_PAYWALL_VERSION );

			
		}
		
		function activate_license() {
		
			// listen for our activate button to be clicked
			if( isset( $_POST['leaky_paywall_license_activate'] ) ) {
		
				// run a quick security check 
				if( ! check_admin_referer( 'verify', 'license_wpnonce' ) )
					return; // get out if we didn't click the Activate button
				
				$settings = $this->get_settings();
				if ( !empty( $_POST['license_key'] ) )
					$settings['license_key'] = $_POST['license_key'];
		
				// retrieve the license from the database
				$license = trim( $settings['license_key'] );
		
				// data to send in our API request
				$api_params = array( 
					'edd_action'=> 'activate_license', 
					'license' 	=> $license, 
					'item_name' => urlencode( $this->plugin_name ) // the name of our product in EDD
				);
				
				// Call the custom API.
				$response = wp_remote_get( esc_url_raw( add_query_arg( $api_params, ZEEN101_STORE_URL ) ), array( 'timeout' => 15, 'sslverify' => false ) );

				// make sure the response came back okay
				if ( is_wp_error( $response ) )
					return false;
		
				// decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
				
				// $license_data->license will be either "active" or "inactive"
				$settings['license_status'] = $license_data->license;
				$this->update_settings( $settings );
		
			}
			
		}
		
		function deactivate_license() {
		
			// listen for our activate button to be clicked
			if( isset( $_POST['leaky_paywall_license_deactivate'] ) ) {
		
				// run a quick security check 
				if( ! check_admin_referer( 'verify', 'license_wpnonce' ) ) 	
					return; // get out if we didn't click the Activate button
				
				$settings = $this->get_settings();
		
				// retrieve the license from the database
				$license = trim( $settings['license_key'] );
		
				// data to send in our API request
				$api_params = array( 
					'edd_action'=> 'deactivate_license', 
					'license' 	=> $license, 
					'item_name' => urlencode( $this->plugin_name ) // the name of our product in EDD
				);
				
				// Call the custom API.
				$response = wp_remote_get( esc_url_raw( add_query_arg( $api_params, ZEEN101_STORE_URL ) ), array( 'timeout' => 15, 'sslverify' => false ) );
		
				// make sure the response came back okay
				if ( is_wp_error( $response ) )
					return false;
		
				// decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
				
				// $license_data->license will be either "deactivated" or "failed"
				if( $license_data->license == 'deactivated' ) {
					
					unset( $settings['license_key'] );
					unset( $settings['license_status'] );
					$this->update_settings( $settings );
					
				}
		
			}
			
		}
		
		/**
		 * Check if zeen101's Leaky Paywall MultiSite options are enabled
		 *
		 * @since CHANGEME
		 */
		function is_site_wide_enabled() {
			return ( is_multisite() ) ? get_site_option( 'issuem-leaky-paywall-site-wide' ) : false;
		}
		
		/**
		 * Get zeen101's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function get_settings() {
			
			$defaults = array( 
				'license_key'					=> '',
				'license_status'				=> '',
				'page_for_login'				=> 0, /* Site Specific */
				'page_for_subscription'			=> 0, /* Site Specific */
				'page_for_after_subscribe'		=> 0,
				'page_for_profile'				=> 0, /* Site Specific */
				'login_method'					=> 'traditional', //default over passwordless
				'post_types'					=> ACTIVE_ISSUEM ? array( 'article' ) : array( 'post' ), /* Site Specific */
				'free_articles'					=> 2,
				'cookie_expiration' 			=> 24,
				'cookie_expiration_interval' 	=> 'day',
				'subscribe_login_message'		=> __( '<a href="{{SUBSCRIBE_URL}}">Subscribe</a> or <a href="{{LOGIN_URL}}">log in</a> to read the rest of this content.', 'issuem-leaky-paywall' ),
				'subscribe_upgrade_message'		=> __( 'You must <a href="{{SUBSCRIBE_URL}}">upgrade your account</a> to read the rest of this content.', 'issuem-leaky-paywall' ),
				'css_style'						=> 'default',
				'site_name'						=> get_option( 'blogname' ), /* Site Specific */
				'from_name'						=> get_option( 'blogname' ), /* Site Specific */
				'from_email'					=> get_option( 'admin_email' ), /* Site Specific */
				'new_email_subject'				=> '',
				'new_email_body'				=> '',
				'payment_gateway'				=> array( 'stripe' ),
				'test_mode'						=> 'off',
				'live_secret_key'				=> '',
				'live_publishable_key'			=> '',
				'test_secret_key'				=> '',
				'test_publishable_key'			=> '',
				'paypal_live_email'				=> '',
				'paypal_live_api_username'		=> '',
				'paypal_live_api_password'		=> '',
				'paypal_live_api_secret'		=> '',
				'paypal_sand_email'				=> '',
				'paypal_sand_api_username'		=> '',
				'paypal_sand_api_password'		=> '',
				'paypal_sand_api_secret'		=> '',
				'leaky_paywall_currency'		=> 'USD',
				'restrict_pdf_downloads' 		=> 'off',
				'restrictions' 	=> array(
					'post_types' => array(
						'post_type' 	=> ACTIVE_ISSUEM ? 'article' : 'post',
						'allowed_value' => 2,
					)
				),
				'levels' => array(
					'0' => array(
						'label' 					=> __( 'Magazine Subscription', 'issuem-leaky-paywall' ),
						'price' 					=> '',
						'subscription_length_type' 	=> 'limited',
						'interval_count' 			=> 1,
						'interval' 					=> 'month',
						'recurring' 				=> 'off',
						'plan_id' 					=> '',
						'post_types' => array(
							array(
								'post_type' 		=> ACTIVE_ISSUEM ? 'article' : 'post',
								'allowed' 			=> 'unlimited',
								'allowed_value' 	=> -1,
							),
						),
						'deleted' 					=> 0,
						'site' 						=> 'all',
					)
				),
			);
		
			$defaults = apply_filters( 'leaky_paywall_default_settings', $defaults );
			$settings = get_option( 'issuem-leaky-paywall' ); /* Site specific settings */
			$settings = wp_parse_args( $settings, $defaults );
			
			if ( $this->is_site_wide_enabled() ) {
				$site_wide_settings = get_site_option( 'issuem-leaky-paywall' );
				/* These are all site-specific settings */
				unset( $site_wide_settings['page_for_login'] );
				unset( $site_wide_settings['page_for_subscription'] );
				unset( $site_wide_settings['page_for_after_subscribe'] );
				unset( $site_wide_settings['page_for_profile'] );
				unset( $site_wide_settings['post_types'] );
				unset( $site_wide_settings['free_articles'] );
				unset( $site_wide_settings['cookie_expiration'] );
				unset( $site_wide_settings['cookie_expiration_interval'] );
				unset( $site_wide_settings['subscribe_login_message'] );
				unset( $site_wide_settings['subscribe_upgrade_message'] );
				unset( $site_wide_settings['css_style'] );
				unset( $site_wide_settings['site_name'] );
				unset( $site_wide_settings['from_name'] );
				unset( $site_wide_settings['from_email'] );
				unset( $site_wide_settings['restrictions'] );
				$site_wide_settings = apply_filters( 'leak_paywall_get_settings_site_wide_settings', $site_wide_settings );
				$settings = wp_parse_args( $site_wide_settings, $settings );
			}

			return apply_filters( 'leaky_paywall_get_settings', $settings );
			
		}
		
		/**
		 * Update zeen101's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function update_settings( $settings ) {
			update_option( 'issuem-leaky-paywall', $settings );
			if ( $this->is_site_wide_enabled() ) {
				update_site_option( 'issuem-leaky-paywall', $settings );
			}
		}
		
		/**
		 * Create and Display IssueM settings page
		 *
		 * @since 1.0.0
		 */
		function settings_page() {
			
			// Get the user options
			$settings = $this->get_settings();
			$settings_saved = false;

			if ( isset( $_REQUEST['update_leaky_paywall_settings'] ) ) {
										
				if ( !empty( $_REQUEST['license_key'] ) )
					$settings['license_key'] = $_REQUEST['license_key'];
					
				if ( !empty( $_REQUEST['page_for_login'] ) )
					$settings['page_for_login'] = $_REQUEST['page_for_login'];
					
				if ( !empty( $_REQUEST['page_for_subscription'] ) )
					$settings['page_for_subscription'] = $_REQUEST['page_for_subscription'];

				if ( !empty( $_REQUEST['page_for_after_subscribe'] ) )
					$settings['page_for_after_subscribe'] = $_REQUEST['page_for_after_subscribe'];
					
				if ( !empty( $_REQUEST['page_for_profile'] ) )
					$settings['page_for_profile'] = $_REQUEST['page_for_profile'];
					
				if ( !empty( $_REQUEST['login_method'] ) )
					$settings['login_method'] = $_REQUEST['login_method'];
				
				if ( !empty( $_REQUEST['post_types'] ) )
					$settings['post_types'] = $_REQUEST['post_types'];
					
				if ( isset( $_REQUEST['free_articles'] ) )
					$settings['free_articles'] = trim( $_REQUEST['free_articles'] );
					
				if ( !empty( $_REQUEST['site_name'] ) )
					$settings['site_name'] = trim( $_REQUEST['site_name'] );
					
				if ( !empty( $_REQUEST['from_name'] ) )
					$settings['from_name'] = trim( $_REQUEST['from_name'] );
					
				if ( !empty( $_REQUEST['from_email'] ) )
					$settings['from_email'] = trim( $_REQUEST['from_email'] );

				if ( !empty( $_REQUEST['new_email_subject'] ) )
					$settings['new_email_subject'] = trim( $_REQUEST['new_email_subject'] );

				if ( !empty( $_REQUEST['new_email_body'] ) )
					$settings['new_email_body'] = trim( $_REQUEST['new_email_body'] );
					
				if ( !empty( $_REQUEST['cookie_expiration'] ) )
					$settings['cookie_expiration'] = trim( $_REQUEST['cookie_expiration'] );
					
				if ( !empty( $_REQUEST['cookie_expiration_interval'] ) )
					$settings['cookie_expiration_interval'] = trim( $_REQUEST['cookie_expiration_interval'] );

				if ( !empty( $_REQUEST['leaky_paywall_currency'] ) )
					$settings['leaky_paywall_currency'] = trim( $_REQUEST['leaky_paywall_currency'] );
					
				if ( !empty( $_REQUEST['restrict_pdf_downloads'] ) )
					$settings['restrict_pdf_downloads'] = $_REQUEST['restrict_pdf_downloads'];
				else
					$settings['restrict_pdf_downloads'] = 'off';
					
				if ( !empty( $_REQUEST['subscribe_login_message'] ) )
					$settings['subscribe_login_message'] = trim( $_REQUEST['subscribe_login_message'] );
					
				if ( !empty( $_REQUEST['subscribe_upgrade_message'] ) )
					$settings['subscribe_upgrade_message'] = trim( $_REQUEST['subscribe_upgrade_message'] );
					
				if ( !empty( $_REQUEST['css_style'] ) )
					$settings['css_style'] = $_REQUEST['css_style'];
					
				if ( !empty( $_REQUEST['test_mode'] ) )
					$settings['test_mode'] = $_REQUEST['test_mode'];
				else
					$settings['test_mode'] = apply_filters( 'zeen101_demo_test_mode', 'off' );
					
				if ( !empty( $_REQUEST['payment_gateway'] ) )
					$settings['payment_gateway'] = $_REQUEST['payment_gateway'];
				else
					$settings['payment_gateway'] = array( 'stripe' );
					
				if ( !empty( $_REQUEST['live_secret_key'] ) )
					$settings['live_secret_key'] = apply_filters( 'zeen101_demo_stripe_live_secret_key', trim( $_REQUEST['live_secret_key'] ) );
					
				if ( !empty( $_REQUEST['live_publishable_key'] ) )
					$settings['live_publishable_key'] = apply_filters( 'zeen101_demo_stripe_live_publishable_key', trim( $_REQUEST['live_publishable_key'] ) );
					
				if ( !empty( $_REQUEST['test_secret_key'] ) )
					$settings['test_secret_key'] = apply_filters( 'zeen101_demo_stripe_test_secret_key', trim( $_REQUEST['test_secret_key'] ) );
					
				if ( !empty( $_REQUEST['test_publishable_key'] ) )
					$settings['test_publishable_key'] = apply_filters( 'zeen101_demo_stripe_test_publishable_key', trim( $_REQUEST['test_publishable_key'] ) );
					
				if ( !empty( $_REQUEST['paypal_live_email'] ) )
					$settings['paypal_live_email'] = apply_filters( 'zeen101_demo_paypal_live_email', trim( $_REQUEST['paypal_live_email'] ) );
					
				if ( !empty( $_REQUEST['paypal_live_api_username'] ) )
					$settings['paypal_live_api_username'] = apply_filters( 'zeen101_demo_paypal_live_api_username', trim( $_REQUEST['paypal_live_api_username'] ) );
					
				if ( !empty( $_REQUEST['paypal_live_api_password'] ) )
					$settings['paypal_live_api_password'] = apply_filters( 'zeen101_demo_paypal_live_api_password', trim( $_REQUEST['paypal_live_api_password'] ) );
					
				if ( !empty( $_REQUEST['paypal_live_api_secret'] ) )
					$settings['paypal_live_api_secret'] = apply_filters( 'zeen101_demo_paypal_live_api_secret', trim( $_REQUEST['paypal_live_api_secret'] ) );
					
				if ( !empty( $_REQUEST['paypal_sand_email'] ) )
					$settings['paypal_sand_email'] = apply_filters( 'zeen101_demo_paypal_sand_email', trim( $_REQUEST['paypal_sand_email'] ) );
					
				if ( !empty( $_REQUEST['paypal_sand_api_username'] ) )
					$settings['paypal_sand_api_username'] = apply_filters( 'zeen101_demo_paypal_sand_api_username', trim( $_REQUEST['paypal_sand_api_username'] ) );
					
				if ( !empty( $_REQUEST['paypal_sand_api_password'] ) )
					$settings['paypal_sand_api_password'] = apply_filters( 'zeen101_demo_paypal_sand_api_password', trim( $_REQUEST['paypal_sand_api_password'] ) );
					
				if ( !empty( $_REQUEST['paypal_sand_api_secret'] ) )
					$settings['paypal_sand_api_secret'] = apply_filters( 'zeen101_demo_paypal_sand_api_secret', trim( $_REQUEST['paypal_sand_api_secret'] ) );
					
				if ( !empty( $_REQUEST['restrictions'] ) )
					$settings['restrictions'] = $_REQUEST['restrictions'];
				else
					$settings['restrictions'] = array();
					
				if ( !empty( $_REQUEST['levels'] ) ) {
					$settings['levels'] = $_REQUEST['levels'];
				} else {
					$settings['levels'] = array();
				}
				
				$this->update_settings( $settings );
				$settings_saved = true;
				
				do_action( 'leaky_paywall_update_settings', $settings );
				
			}
			
			if ( $settings_saved ) {
				
				// update settings notification ?>
				<div class="updated"><p><strong><?php _e( "zeen101's Leaky Paywall Settings Updated.", 'issuem-leaky-paywall' );?></strong></p></div>
				<?php
				
			}
			
			// Display HTML form for the options below
			?>
			<div class=wrap>
            <div style="width:70%;" class="postbox-container">
            <div class="metabox-holder">	
            <div class="meta-box-sortables ui-sortable">
            
                <form id="issuem" method="post" action="">
            
                    <h2 style='margin-bottom: 10px;' ><?php _e( "zeen101's Leaky Paywall Settings", 'issuem-leaky-paywall' ); ?></h2>
  		
						<?php if ( is_multisite() && is_super_admin() ) { ?>
  		
						<div id="site-wide-option" class="postbox">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'Site Wide Options', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div class="inside">
	                        
	                        <table id="issuem_license_key" class="leaky-paywall-table">
	                        	<tr>
	                                <th rowspan="1"> <?php _e( 'Enable Settings Site Wide?', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
	                                <td><input type="checkbox" id="site_wide_enabled" name="site_wide_enabled" <?php checked( $this->is_site_wide_enabled() ); ?> /></td>
	                                </td>
	                            </tr>
	                        </table>
	                                                                                                         
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	                        
	                        </div>
	                        
	                    </div>
  		
						<?php } ?>
  		
						<div id="license-key" class="postbox">
	                    
	                       
	                        
	                        <div class="inside">
	                        
	                        <table id="issuem_license_key" class="form-table">
	                        	<tr>
	                                <th rowspan="1"> <?php _e( 'License Key', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
	                                <input type="text" id="license_key" class="regular-text" name="license_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['license_key'] ) ); ?>" />
	                                
	                                <?php if( $settings['license_status'] !== false 
											&& $settings['license_status'] == 'valid' ) { ?>
										<span style="color:green;"><?php _e('active'); ?></span>
										<input type="submit" class="button-secondary" name="leaky_paywall_license_deactivate" value="<?php _e( 'Deactivate License', 'issuem-leaky-paywall' ); ?>"/>
									<?php } else if ( $settings['license_status'] == 'invalid' ) {	?>
									<span style="color:red;"><?php _e('invalid'); ?></span>
									<input type="submit" class="button-secondary" name="leaky_paywall_license_activate" value="<?php _e( 'Activate License', 'issuem-leaky-paywall' ); ?>"/>
									<?php } else  { ?>
										<input type="submit" class="button-secondary" name="leaky_paywall_license_activate" value="<?php _e( 'Activate License', 'issuem-leaky-paywall' ); ?>"/>
									<?php } ?>
	                                <?php wp_nonce_field( 'verify', 'license_wpnonce' ); ?>
	                                </td>
	                            </tr>
	                        </table>
	                                                                                                         
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	                        
	                        </div>
	                        
	                    </div>
	                                        
						<?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
	                    
	                    <div id="modules" class="postbox">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'General Settings', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div class="inside">
	                        
	                        <table id="leaky_paywall_administrator_options" class="form-table">
	                        
	                        	<tr>
	                                <th><?php _e( 'Page for Log In', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<?php echo wp_dropdown_pages( array( 'name' => 'page_for_login', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_login'] ) ); ?>
	                                <p class="description"><?php printf( __( 'Add this shortcode to your Log In page: %s', 'issuem-leaky-paywall' ), '[leaky_paywall_login]' ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Page for Subscription', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<?php echo wp_dropdown_pages( array( 'name' => 'page_for_subscription', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_subscription'] ) ); ?>
	                                <p class="description"><?php printf( __( 'Add this shortcode to your Subscription page: %s', 'issuem-leaky-paywall' ), '[leaky_paywall_subscription]' ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Page for Profile', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<?php echo wp_dropdown_pages( array( 'name' => 'page_for_profile', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_profile'] ) ); ?>
	                                <p class="description"><?php printf( __( 'Add this shortcode to your Profile page: %s', 'issuem-leaky-paywall' ), '[leaky_paywall_profile]' ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Login Method', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<select id='login_method' name='login_method'>
										<option value='traditional' <?php selected( 'traditional', $settings['login_method'] ); ?> ><?php _e( 'Traditional', 'issuem-leaky-paywall' ); ?></option>
										<option value='passwordless' <?php selected( 'passwordless', $settings['login_method'] ); ?> ><?php _e( 'Passwordless', 'issuem-leaky-paywall' ); ?></option>
									</select>
	                                <p class="description"><?php printf( __( 'Traditional allows users to log in with a username and password. Passwordless authenticates the user via a secure link sent to their email.', 'issuem-leaky-paywall' ) ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Subscribe or Login Message', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
	                    				<textarea id="subscribe_login_message" class="large-text" name="subscribe_login_message" cols="50" rows="3"><?php echo stripslashes( $settings['subscribe_login_message'] ); ?></textarea>
	                                    <p class="description">
	                                    <?php _e( "Available replacement variables: {{SUBSCRIBE_LOGIN_URL}} {{SUBSCRIBE_URL}}  {{LOGIN_URL}}", 'issuem-leaky-paywall' ); ?>
	                                    </p>
	                                </td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Upgrade Message', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
	                    				<textarea id="subscribe_upgrade_message" class="large-text" name="subscribe_upgrade_message" cols="50" rows="3"><?php echo stripslashes( $settings['subscribe_upgrade_message'] ); ?></textarea>
	                                    <p class="description">
	                                    <?php _e( "Available replacement variables: {{SUBSCRIBE_LOGIN_URL}} {{SUBSCRIBE_URL}}", 'issuem-leaky-paywall' ); ?>
	                                    </p>
	                                </td>
	                            </tr>
	                        
	                        	<tr>
	                                <th><?php _e( 'CSS Style', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<select id='css_style' name='css_style'>
										<option value='default' <?php selected( 'default', $settings['css_style'] ); ?> ><?php _e( 'Default', 'issuem-leaky-paywall' ); ?></option>
										<option value='none' <?php selected( 'none', $settings['css_style'] ); ?> ><?php _e( 'None', 'issuem-leaky-paywall' ); ?></option>
									</select>
	                                </td>
	                            </tr>

	                            <tr>
	                                <th><?php _e( 'After Subscribe Page', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
									<?php echo wp_dropdown_pages( array( 'name' => 'page_for_after_subscribe', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_after_subscribe'] ) ); ?>
	                                <p class="description"><?php _e( 'Page to redirect to after a user subscribes', 'issuem-leaky-paywall' ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        </table>
	                                                                          
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	
	                        </div>
	                        
	                    </div>
	                    
	                    <div id="modules" class="postbox">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'Email Settings', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div class="inside">
	                        
	                        <table id="leaky_paywall_administrator_options" class="form-table">
	                        
	                        	<tr>
	                                <th><?php _e( 'Site Name', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="site_name" class="regular-text" name="site_name" value="<?php echo htmlspecialchars( stripcslashes( $settings['site_name'] ) ); ?>" /></td>
	                            </tr>
	                        
	                        	<tr>
	                                <th><?php _e( 'From Name', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="from_name" class="regular-text" name="from_name" value="<?php echo htmlspecialchars( stripcslashes( $settings['from_name'] ) ); ?>" /></td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'From Email', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="from_email" class="regular-text" name="from_email" value="<?php echo htmlspecialchars( stripcslashes( $settings['from_email'] ) ); ?>" /></td>
	                            </tr>

	                            <tr><td colspan="2"><h3>New Subscriber Email</h3></td></tr>

	                            <tr>
	                                <th><?php _e( 'Subject', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="new_email_subject" class="regular-text" name="new_email_subject" value="<?php echo htmlspecialchars( stripcslashes( $settings['new_email_subject'] ) ); ?>" />
	                                	<p class="description">The subject line for the email sent to new subscribers.</p>
	                                </td>
	                            </tr>

	                            <tr>
	                                <th><?php _e( 'Body', 'issuem-leaky-paywall' ); ?></th>
	                                <td><textarea id="new_email_body" class="large-text" name="new_email_body"><?php echo htmlspecialchars( stripcslashes( $settings['new_email_body'] ) ); ?></textarea>
	                                <p class="description">The email message that is sent to new subscribers.</p>
	                                <p class="description">Available template tags: <br>
	                                %blogname%, %username%, %password%, %firstname%, %lastname%, and %displayname%</p>
	                                </td>
	                            </tr>
	                            
	                        </table>
	                        
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	
	                        </div>
	                        
	                    </div>
	                                        
	                    <div id="modules" class="postbox leaky-paywall-gateway-settings">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'Payment Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div class="inside">
	                        
	                        <table id="leaky_paywall_gateway_options" class="form-table">
				                        								
	                        	<tr class="gateway-options">
	                                <th><?php _e( 'Enabled Gateways', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
										<p>
											<input id="enable-stripe" type="checkbox" name="payment_gateway[]" value="stripe" <?php checked( in_array( 'stripe', $settings['payment_gateway'] ) ); ?> /> <label for="enable-stripe"><?php _e( 'Stripe', 'issuem-leaky-paywall' ); ?></label>
										</p>
										<p>
										<input id="enable-paypal-standard" type="checkbox" name="payment_gateway[]"  value='paypal_standard' <?php checked( in_array( 'paypal_standard', $settings['payment_gateway'] ) ); ?> /> <label for="enable-paypal-standard"><?php _e( 'PayPal Standard', 'issuem-leaky-paywall' ); ?></label>
										</p>
	                                </td>
	                            </tr>
	                                                        
	                            <tr class="gateway-options">
	                            	<th><?php _e( "Test Mode?", 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="checkbox" id="test_mode" name="test_mode" <?php checked( 'on', $settings['test_mode'] ); ?> /></td>
	                            </tr>
	                            
	                        </table>
	                        
	                        <?php
	                        if ( in_array( 'stripe', $settings['payment_gateway'] ) ) {
	                        ?>
	                        
	                        <table id="leaky_paywall_stripe_options" class="form-table">
	                        
		                        <tr><th><?php _e( 'Stripe Settings', 'issuem-leaky-paywall' ); ?></th><td></td></tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Live Secret Key', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="live_secret_key" class="regular-text" name="live_secret_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['live_secret_key'] ) ); ?>" /></td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Live Publishable Key', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="live_publishable_key" class="regular-text" name="live_publishable_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['live_publishable_key'] ) ); ?>" /></td>
	                            </tr>
	                            
	                            <tr>
	                            	<th><?php _e( 'Live Webhooks', 'issuem-leaky-paywall' ); ?></th>
	                            	<td><p class="description"><?php echo esc_url( add_query_arg( 'issuem-leaky-paywall-stripe-live-webhook', '1', get_site_url() . '/' ) ); ?></p></td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Test Secret Key', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="test_secret_key" class="regular-text" name="test_secret_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['test_secret_key'] ) ); ?>" /></td>
	                            </tr>
	                            
	                        	<tr>
	                                <th><?php _e( 'Test Publishable Key', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="text" id="test_publishable_key" class="regular-text" name="test_publishable_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['test_publishable_key'] ) ); ?>" /></td>
	                            </tr>
	                            
	                            <tr>
	                            	<th><?php _e( 'Test Webhooks', 'issuem-leaky-paywall' ); ?></th>
	                            	<td><p class="description"><?php echo esc_url( add_query_arg( 'issuem-leaky-paywall-stripe-test-webhook', '1', get_site_url() . '/' ) ); ?></p></td>
	                            </tr>
	                            
	                        </table>
	
		                    <?php } ?>
		                    
		                    <?php
		                    if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) ) { 
		                    ?>
		                                            
		                        <table id="leaky_paywall_paypal_options" class="gateway-options form-table">
		                        
			                        <tr><th><?php _e( 'PayPal Standard Settings', 'issuem-leaky-paywall' ); ?></th><td></td></tr>
		                        
		                        	<tr>
		                                <th><?php _e( 'Merchant ID', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_live_email" class="regular-text" name="paypal_live_email" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_live_email'] ) ); ?>" />
		                                	<p class="description"><?php _e( 'Use PayPal Email Address in lieu of Merchant ID', 'issuem-leaky-paywall' ); ?></p>
		                                </td>
		                            </tr>
		                        
		                        	<tr>
		                                <th><?php _e( 'API Username', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_live_api_username" class="regular-text" name="paypal_live_api_username" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_live_api_username'] ) ); ?>" />
		                                	<p class="description"><?php _e( 'At PayPal, see: Profile &rarr; My Selling Tools &rarr; API Access &rarr; Update &rarr; View API Signature (or Request API Credentials).', 'issuem-leaky-paywall' ); ?></p>
		                                </td>
		                            </tr>
		                        
		                        	<tr>
		                                <th><?php _e( 'API Password', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_live_api_password" class="regular-text" name="paypal_live_api_password" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_live_api_password'] ) ); ?>" />
		                                </td>
		                            </tr>
		                        
		                        	<tr>
		                                <th><?php _e( 'API Signature', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_live_api_secret" class="regular-text" name="paypal_live_api_secret" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_live_api_secret'] ) ); ?>" />
		                                </td>
		                            </tr>
		                            
		                            <tr>
		                            	<th><?php _e( 'Live IPN', 'issuem-leaky-paywall' ); ?></th>
		                            	<td><p class="description"><?php echo esc_url( add_query_arg( 'issuem-leaky-paywall-paypal-standard-live-ipn', '1', get_site_url() . '/' ) ); ?></p></td>
		                            </tr>
		                            
		                        	<tr>
		                                <th><?php _e( 'Sandbox Merchant ID', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_sand_email" class="regular-text" name="paypal_sand_email" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_sand_email'] ) ); ?>" />
		                                	<p class="description"><?php _e( 'Use PayPal Sandbox Email Address in lieu of Merchant ID', 'issuem-leaky-paywall' ); ?></p>
		                                </td>
		                            </tr>
		                            
		                        	<tr>
		                                <th><?php _e( 'Sandbox API Username', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_sand_api_username" class="regular-text" name="paypal_sand_api_username" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_sand_api_username'] ) ); ?>" />
		                                	<p class="description"><?php _e( 'At PayPal, see: Profile &rarr; My Selling Tools &rarr; API Access &rarr; Update &rarr; View API Signature (or Request API Credentials).', 'issuem-leaky-paywall' ); ?></p>
		                                </td>
		                            </tr>
		                            
		                        	<tr>
		                                <th><?php _e( 'Sandbox API Password', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_sand_api_password" class="regular-text" name="paypal_sand_api_password" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_sand_api_password'] ) ); ?>" />
		                                </td>
		                            </tr>
		                            
		                        	<tr>
		                                <th><?php _e( 'Sandbox API Signature', 'issuem-leaky-paywall' ); ?></th>
		                                <td>
		                                	<input type="text" id="paypal_sand_api_secret" class="regular-text" name="paypal_sand_api_secret" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_sand_api_secret'] ) ); ?>" />
		                                </td>
		                            </tr>
		                            
		                            <tr>
		                            	<th><?php _e( 'Sandbox IPN', 'issuem-leaky-paywall' ); ?></th>
		                            	<td><p class="description"><?php echo esc_url( add_query_arg( 'issuem-leaky-paywall-paypal-standard-test-ipn', '1', get_site_url() . '/' ) ); ?></p></td>
		                            </tr>
		                            
		                        </table>
		
		                    <?php } ?>
	                        
	                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
	                                                  
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	
	                        </div>
	                        
	                    </div>

	                    <?php // currency options ?>

	                    <div id="modules" class="postbox">
            
			                <div class="handlediv" title="Click to toggle"><br /></div>
			                
			                <h3 class="hndle"><span><?php _e( 'Currency Options', 'issuem-leaky-paywall' ); ?></span></h3>
			                
			                <div class="inside">
			                
			                <table id="leaky_paywall_currency_options" class="form-table">
			                
			                    <tr>
			                        <th><?php _e( 'Currency', 'issuem-leaky-paywall' ); ?></th>
			                        <td>
			                        	<select id="leaky_paywall_currency" name="leaky_paywall_currency">
				                        	<?php
											$currencies = leaky_paywall_supported_currencies();
											foreach ( $currencies as $key => $currency ) {
				                        		echo '<option value="' . $key . '" ' . selected( $key, $settings['leaky_paywall_currency'], true ) . '>' . $currency['label'] . ' - ' . $currency['symbol'] . '</option>';
											}
				                        	?>
			                        	</select>
			                        	<p class="description"><?php _e( 'This controls which currency payment gateways will take payments in.', 'issuem-leaky-paywall' ); ?></p>
			                        </td>
			                    </tr>
			                    
			                    
			                    
			                </table>
			
			                                                                  
			                <p class="submit">
			                    <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
			                </p>
			
			                </div>
			                
			            </div>

	                    <?php // end currency options ?>
	                    
	                    <div id="modules" class="postbox leaky-paywall-restriction-settings">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'Content Restriction', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div class="inside">
	                        
	                        <table id="leaky_paywall_default_restriction_options" class="form-table">
	                        	     
	                        	<tr class="restriction-options">
	                                <th><?php _e( 'Limited Article Cookie Expiration', 'issuem-leaky-paywall' ); ?></th>
	                                <td>
	                                	<input type="text" id="cookie_expiration" class="small-text" name="cookie_expiration" value="<?php echo stripcslashes( $settings['cookie_expiration'] ); ?>" /> 
	                                	<select id="cookie_expiration_interval" name="cookie_expiration_interval">
	                                		<option value="hour" <?php selected( 'hour', $settings['cookie_expiration_interval'] ); ?>><?php _e( 'Hour(s)', 'issuem-leaky-paywall' ); ?></option>
	                                		<option value="day" <?php selected( 'day', $settings['cookie_expiration_interval'] ); ?>><?php _e( 'Day(s)', 'issuem-leaky-paywall' ); ?></option>
	                                		<option value="week" <?php selected( 'week', $settings['cookie_expiration_interval'] ); ?>><?php _e( 'Week(s)', 'issuem-leaky-paywall' ); ?></option>
	                                		<option value="month" <?php selected( 'month', $settings['cookie_expiration_interval'] ); ?>><?php _e( 'Month(s)', 'issuem-leaky-paywall' ); ?></option>
	                                		<option value="year" <?php selected( 'year', $settings['cookie_expiration_interval'] ); ?>><?php _e( 'Year(s)', 'issuem-leaky-paywall' ); ?></option>
	                                	</select>
	                                	<p class="description"><?php _e( 'Choose length of time when a visitor can once again read your articles/posts (up to the # of articles allowed).', 'issuem-leaky-paywall' ); ?></p>
	                                </td>
	                            </tr>
	                            
	                        	<tr class="restriction-options ">
	                                <th><?php _e( 'Restrict PDF Downloads?', 'issuem-leaky-paywall' ); ?></th>
	                                <td><input type="checkbox" id="restrict_pdf_downloads" name="restrict_pdf_downloads" <?php checked( 'on', $settings['restrict_pdf_downloads'] ); ?> /></td>
	                            </tr>
	                            
	                        	<tr class="restriction-options">
									<th>
										<label for="restriction-post-type-' . $row_key . '"><?php _e( 'Restrictions', 'issuem-leaky-paywall' ); ?></label>
									</th>
									<td id="issuem-leaky-paywall-restriction-rows">
		                        	<?php 
		                        	$last_key = -1;
		                        	if ( !empty( $settings['restrictions']['post_types'] ) ) {
		                        	
			                        	foreach( $settings['restrictions']['post_types'] as $key => $restriction ) {
			                        	
			                        		if ( !is_numeric( $key ) )
				                        		continue;
				                        		
			                        		echo build_leaky_paywall_default_restriction_row( $restriction, $key );
			                        		$last_key = $key;
			                        		
			                        	}
			                        	
		                        	}
		                        	?>
			                        </td>
		                        </tr>
	                    
	                        	<tr class="restriction-options">
									<th>&nbsp;</th>
									<td>
								        <script type="text/javascript" charset="utf-8">
								            var leaky_paywall_restriction_row_key = <?php echo $last_key; ?>;
								        </script>
										<p class="description"><?php _e( 'By default all content is allowed.', 'issuem-leaky-paywall' ); ?></p>
				                    	<p>
				                       		<input class="button-secondary" id="add-restriction-row" class="add-new-issuem-leaky-paywall-restriction-row" type="submit" name="add_leaky_paywall_restriction_row" value="<?php _e( 'Add New Restricted Content', 'issuem-leaky-paywall-multilevel' ); ?>" />
				                    	</p>
			                        </td>
		                        </tr>
	                            
	                        </table>
	
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	                        
	                        </div>
	                        
	                    </div>
	                                        
	                    <div id="modules" class="postbox leaky-paywall-subscription-settings">
	                    
	                        <div class="handlediv" title="Click to toggle"><br /></div>
	                        
	                        <h3 class="hndle"><span><?php _e( 'Subscription Levels', 'issuem-leaky-paywall' ); ?></span></h3>
	                        
	                        <div id="leaky_paywall_subscription_level_options" class="inside">
	                        
	                        <table id="leaky_paywall_subscription_level_options_table" class="leaky-paywall-table subscription-options form-table">
	
								<tr><td id="issuem-leaky-paywall-subscription-level-rows" colspan="2">
	                        	<?php 
	                        	$last_key = -1;
	                        	if ( !empty( $settings['levels'] ) ) {
	                        	
		                        	foreach( $settings['levels'] as $key => $level ) {
		                        	
		                        		if ( !is_numeric( $key ) )
			                        		continue;
		                        		echo build_leaky_paywall_subscription_levels_row( $level, $key );
		                        		$last_key = $key;
		                        		
		                        	} 
		                        	
	                        	}
	                        	?>
								</td></tr>
	
	                        </table>
	
					        <script type="text/javascript" charset="utf-8">
					            var leaky_paywall_subscription_levels_row_key = <?php echo $last_key; ?>;
					        </script>
	                        
	                        <p class="subscription-options">
		                        <input class="button-secondary" id="add-subscription-row" class="add-new-issuem-leaky-paywall-subscription-row" type="submit" name="add_leaky_paywall_row" value="<?php _e( 'Add New Level', 'issuem-leaky-paywall' ); ?>" />
	                        </p>
	
	                        <p class="submit">
	                            <input class="button-primary" type="submit" name="update_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
	                        </p>
	
	                        </div>
	                        
	                    </div>
	                    
	                    <?php  do_action( 'leaky_paywall_settings_form', $settings ); ?>
                    
                </form>
                
            </div>
            </div>
            </div>
			</div>
			<?php
			
		}
				
		/**
		 * Create and Display Leaky Paywall Subscribers page
		 *
		 * @since 1.0.0
		 */
		function subscribers_page() {
			
			global $blog_id;
			if ( is_multisite() && !is_main_site( $blog_id ) ) {
				$site = '_' . $blog_id;
			} else {
				$site = '';
			}
			
			$date_format = get_option( 'date_format' );
			$jquery_date_format = leaky_paywall_jquery_datepicker_format( $date_format );
			$headings = apply_filters( 'leaky_paywall_bulk_add_headings', array( 'username', 'email', 'price', 'expires', 'status', 'level-id', 'subscriber-id' ) );
			
			$settings = get_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		   
			?>
			<div class="wrap">
			   
				<div id="icon-users" class="icon32"><br/></div>
				<h2><?php _e( 'Leaky Paywall Subscribers', 'issuem-leaky-paywall' ); ?></h2>
                    
                <?php
                if ( !empty( $_POST['leaky_paywall_add_subscriber'] ) )  {
                    if ( !wp_verify_nonce( $_POST['leaky_paywall_add_subscriber'], 'add_new_subscriber' ) ) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __( 'Unable to verify security token. Subscriber not added. Please try again.', 'issuem-leaky-paywall' ) . '</strong></p></div>';

					}  else {
						// process form data
						if ( !empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) ) ) 
							&& !empty( $_POST['leaky-paywall-subscriber-login'] ) ) {
							
							$login = trim( rawurldecode( $_POST['leaky-paywall-subscriber-login'] ) );
							$email = trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) );
							$payment_gateway = trim( rawurldecode( $_POST['leaky-paywall-subscriber-payment-gateway'] ) );
							$subscriber_id = trim( rawurldecode( $_POST['leaky-paywall-subscriber-id'] ) );
							if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
								$expires = 0;
							else 
								$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
							
							$meta = array(
								'level_id' 			=> $_POST['leaky-paywall-subscriber-level-id'],
								'subscriber_id'		=> $subscriber_id,
								'price' 			=> trim( $_POST['leaky-paywall-subscriber-price'] ),
								'description' 		=> __( 'Manual Addition', 'issuem-leaky-paywall' ),
								'expires' 			=> $expires,
								'payment_gateway' 	=> $payment_gateway,
								'payment_status' 	=> $_POST['leaky-paywall-subscriber-status'],
								'interval' 			=> 0,
								'plan'				=> '',
							);
							
							$user_id = leaky_paywall_new_subscriber( NULL, $email, $subscriber_id, $meta, $login );
							
							do_action( 'add_leaky_paywall_subscriber', $user_id );
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                } else if ( !empty( $_POST['leaky_paywall_edit_subscriber'] ) )  {
                    if ( !wp_verify_nonce( $_POST['leaky_paywall_edit_subscriber'], 'edit_subscriber' ) ) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __( 'Unable to verify security token. Subscriber not added. Please try again.', 'issuem-leaky-paywall' ) . '</strong></p></div>';

					}  else {
						// process form data
						if ( !empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) ) )
							&& !empty( $_POST['leaky-paywall-subscriber-original-email'] ) && is_email( trim( rawurldecode( $_POST['leaky-paywall-subscriber-original-email'] ) ) ) 
							&& !empty( $_POST['leaky-paywall-subscriber-login'] ) && !empty( $_POST['leaky-paywall-subscriber-original-login'] ) ) {
							
							$orig_login = trim( rawurldecode( $_POST['leaky-paywall-subscriber-original-login'] ) );
							$orig_email = trim( rawurldecode( $_POST['leaky-paywall-subscriber-original-email'] ) );
							$user = get_user_by( 'email', $orig_email );
							
							if ( !empty( $user ) ) {
								$new_login = trim( rawurldecode( $_POST['leaky-paywall-subscriber-login'] ) );
								$new_email = trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) );
								$price = trim( $_POST['leaky-paywall-subscriber-price'] );
								$status = $_POST['leaky-paywall-subscriber-status'];
								$payment_gateway = trim( rawurldecode( $_POST['leaky-paywall-subscriber-payment-gateway'] ) );
								$subscriber_id = trim( rawurldecode( $_POST['leaky-paywall-subscriber-id'] ) );
								
								if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
									$expires = 0;
								else 
									$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
									
								if ( $price !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, $price );
								if ( $expires !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, $expires );
								if ( $status !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, $status );
								if ( $payment_gateway !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, $payment_gateway );
								if ( $subscriber_id !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, $subscriber_id );
								
								if ( $orig_email !== $new_email ) {
									$args = array( 'ID' => $user->ID );
									$args['user_email'] = ( $orig_email === $new_email ) ? $orig_email : $new_email;
									
									$user_id = wp_update_user( $args );
								}
								
								if ( $orig_login !== $new_login ) {
									global $wpdb;
									$wpdb->update( $wpdb->users,
										array( 'user_login' => $new_login ), 
										array( 'ID' => $user->ID ),
										array( '%s' ), 
										array( '%d' )
									);
									clean_user_cache( $user->ID );
								}
								
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, $_POST['leaky-paywall-subscriber-level-id'] );
								
								do_action( 'update_leaky_paywall_subscriber', $user->ID );
							}
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                } else if ( !empty( $_POST['leaky_paywall_bulk_add_subscribers'] ) )  {
                    if ( !wp_verify_nonce( $_POST['leaky_paywall_bulk_add_subscribers'], 'bulk_add_subscribers' ) ) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __( 'Unable to verify security token. Subscribers not added. Please try again.', 'issuem-leaky-paywall' ) . '</strong></p></div>';

					}  else {
						// process form data
						if ( !empty( $_POST['leaky-paywall-subscriber-bulk-add-content'] ) ) {
							
							$errors = array();
							$keys = array();
							
							$textarea = explode( "\n", $_POST['leaky-paywall-subscriber-bulk-add-content'] );
							foreach( $textarea as $line ) {
								if ( !empty( $line ) )
									$imports[] = str_getcsv( trim( rawurldecode( stripslashes( $line ) ) ), ',', '"' );
							}
							
							$heading_line = array_shift( $imports );
							
							foreach( $headings as $heading ) {
								if ( false !== $key = array_search( $heading, $heading_line ) )
									$keys[$heading] = $key;
							}
							
							if ( !array_key_exists( 'email', $keys ) ) { //header line was not included (or modified)
								$imports = array_unshift( $imports, $heading_line ); //so add the line back for processing
								//assume these keys
								$keys['username'] = 0;
								$keys['email'] = 1;
								$keys['price'] = 2;
								$keys['expires'] = 3;
								$keys['status'] = 4;
								$keys['level-id'] = 5;
								$keys['subscriber-id'] = 6;
							}
							
							foreach( $imports as $import ) {
								
								$login = trim( $import[$keys['username']] );
								if ( empty( $login ) ) {
									$errors[] = sprintf( __( 'Invalid Username, line: %s', 'issuem-leaky-paywall' ), join( ',', $import ) );
									continue;
								}
								
								$email = trim( $import[$keys['email']] );
								if ( empty( $email ) || !is_email( $email ) ) {
									$errors[] = sprintf( __( 'Invalid Email, line: %s', 'issuem-leaky-paywall' ), join( ',', $import ) );
									continue;
								}
								if ( empty( $import[$keys['price']] ) )
									$price = 0;
								else 
									$price = trim( $import[$keys['price']] );
								
								if ( empty( $import[$keys['expires']] ) )
									$expires = 0;
								else 
									$expires = date( 'Y-m-d 23:59:59', strtotime( trim( $import[$keys['expires']] ) ) );
									
								if ( empty( $import[$keys['status']] ) )
									$status = 'active';
								else 
									$status = strtolower( trim( $import[$keys['status']] ) );
									
								if ( isset( $import[$keys['level-id']] ) )
									$level_id = trim( $import[$keys['level-id']] );
								else 
									$level_id = '';
															
								$meta = array(
									'level_id'			=> $level_id,
									'subscriber_id' 	=> $subscriber_id,
									'price' 			=> $price,
									'description' 		=> __( 'Bulk Addition', 'issuem-leaky-paywall' ),
									'expires' 			=> $expires,
									'payment_gateway' 	=> 'manual',
									'payment_status' 	=> $status,
									'interval' 			=> 0,
									'plan'				=> '',
								);
								
								$user_id = leaky_paywall_new_subscriber( NULL, $email, '', $meta, $login );
								
								if ( !empty( $user_id ) )
									do_action( 'bulk_add_leaky_paywall_subscriber', $user_id, $keys, $import );
								else
									do_action( 'bulk_add_leaky_paywall_subscriber_failed', $keys, $import, $email, $meta, $login );
								
							}
							
							if ( !empty( $errors ) ) {
							
								echo '<div class="error settings-error" id="setting-error-bulk-import">';
								foreach( $errors as $error ) {
									echo '<p><strong>' . $error . '</strong></p>';
								}
								echo '</div>';								
							}
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-bulk-content"><p><strong>' . __( 'No valid content supplied for bulk upload.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                }
					
				//Create an instance of our package class...
				$subscriber_table = new Leaky_Paywall_Subscriber_List_Table();
				$pagenum = $subscriber_table->get_pagenum();
				//Fetch, prepare, sort, and filter our data...
				$subscriber_table->prepare_items();
                $total_pages = $subscriber_table->get_pagination_arg( 'total_pages' );
		        if ( $pagenum > $total_pages && $total_pages > 0 ) {
	                wp_redirect( esc_url_raw( add_query_arg( 'paged', $total_pages ) ) );
	                exit;
		        }
		        
                ?>
			   
				<div id="leaky-paywall-subscriber-add-edit">
                	<?php 
                	$email = !empty( $_GET['edit'] ) ? trim( rawurldecode( $_GET['edit'] ) ) : '';
            		$user = get_user_by( 'email', $email );

                	if ( !empty( $email ) && !empty( $user ) ) {
                	
                		$login = $user->user_login;
                		
                		$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
                		$subscriber_level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
                		$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
                		$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
                		$price = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' . $site, true );
                		$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
						if ( '0000-00-00 00:00:00' === $expires )
							$expires = '';
						else
							$expires = mysql2date( $date_format, $expires );
						
						?>
	                    <form id="leaky-paywall-susbcriber-edit" name="leaky-paywall-subscriber-edit" method="post">
	                    	<div style="display: table">
	                    	<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e( 'Username (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-login" /></p><input id="leaky-paywall-subscriber-original-login" type="hidden" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-original-login" /></p>
	                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="<?php echo $email; ?>" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p><input id="leaky-paywall-subscriber-original-email" type="hidden" value="<?php echo $email; ?>" name="leaky-paywall-subscriber-original-email" /></p>
	                    	<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e( 'Price Paid', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="<?php echo $price; ?>"  placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e( 'Expires', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo $expires; ?>" placeholder="<?php echo date_i18n( $date_format, time() ); ?>"name="leaky-paywall-subscriber-expires"  />
	                        <input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
	                        </p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php _e( 'Subscription Level', 'issuem-leaky-paywall' ); ?></label>
	                        <select name="leaky-paywall-subscriber-level-id">
	                        <?php
	                        foreach( $settings['levels'] as $key => $level ) {
		                        echo '<option value="' . $key .'" ' . selected( $key, $subscriber_level_id, true ) . '>' . stripslashes( $level['label'] ) . '</option>';
	                        }
	                        ?>
	                        </select>
	                        </p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php _e( 'Status', 'issuem-leaky-paywall' ); ?></label>
	                        <select name="leaky-paywall-subscriber-status">
	                            <option value="active" <?php selected( 'active', $payment_status ); ?>><?php _e( 'Active', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="canceled" <?php selected( 'canceled', $payment_status ); ?>><?php _e( 'Canceled', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="deactivated" <?php selected( 'deactivated', $payment_status ); ?>><?php _e( 'Deactivated', 'issuem-leaky-paywall' ); ?></option>
	                        </select>
	                        </p>
	                        <p>
	                        <label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php _e( 'Payment Method', 'issuem-leaky-paywall' ); ?></label>
	                        <?php $payment_gateways = leaky_paywall_payment_gateways(); ?>
	                        <select name="leaky-paywall-subscriber-payment-gateway">
		                        <?php foreach( $payment_gateways as $key => $gateway ) {
	                            	echo '<option value="' . $key . '" ' . selected( $key, $payment_gateway, false ) . '>' . $gateway . '</option>';
		                        }
								echo apply_filters( 'leaky_paywall_subscriber_payment_gateway_select_option', '' );
								?>
	                        </select>
	                        </p>
	                    	<p>
		                        <label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php _e( 'Subscriber ID', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="<?php echo $subscriber_id; ?>" name="leaky-paywall-subscriber-id"  />
	                        </p>
	                        <?php do_action( 'update_leaky_paywall_subscriber_form', $user->ID ); ?>
	                        </div>
	                        <?php submit_button( 'Update Subscriber' ); ?>
	                        <p>
	                        <a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>"><?php _e( 'Cancel', 'issuem-leaky-paywall' ); ?></a>
	                        </p>
	                        <?php wp_nonce_field( 'edit_subscriber', 'leaky_paywall_edit_subscriber' ); ?>
						</form>
                    <?php } else { ?>
	                    <form id="leaky-paywall-susbcriber-add" name="leaky-paywall-subscriber-add" method="post">
	                    	<div style="display: table">
	                    	<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e( 'Username (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-login" /></p>
	                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@zeen101.com" name="leaky-paywall-subscriber-email" /></p>
	                    	<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e( 'Price Paid', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value=""  placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e( 'Expires', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="" placeholder="<?php echo date_i18n( $date_format, time() ); ?>"name="leaky-paywall-subscriber-expires"  />
	                        <input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
	                        </p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-level-id" style="display:table-cell"><?php _e( 'Subscription Level', 'issuem-leaky-paywall' ); ?></label>
	                        <select name="leaky-paywall-subscriber-level-id">
	                        <?php
	                        foreach( $settings['levels'] as $key => $level ) {
		                        echo '<option value="' . $key .'">' . stripslashes( $level['label'] ) . '</option>';
	                        }
	                        ?>
	                        </select>
	                        </p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php _e( 'Status', 'issuem-leaky-paywall' ); ?></label>
	                        <select name="leaky-paywall-subscriber-status">
	                            <option value="active"><?php _e( 'Active', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="canceled"><?php _e( 'Canceled', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="deactivated"><?php _e( 'Deactivated', 'issuem-leaky-paywall' ); ?></option>
	                        </select>
	                        </p>
	                    	<p>
	                        <label for="leaky-paywall-subscriber-payment-gateway" style="display:table-cell"><?php _e( 'Payment Method', 'issuem-leaky-paywall' ); ?></label>
	                        <select name="leaky-paywall-subscriber-payment-gateway">
	                            <option value="manual"><?php _e( 'Manual', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="stripe"><?php _e( 'Stripe', 'issuem-leaky-paywall' ); ?></option>
	                            <option value="paypal_standard"><?php _e( 'PayPal', 'issuem-leaky-paywall' ); ?></option>
	                        </select>
	                        </p>
	                    	<p>
		                        <label for="leaky-paywall-subscriber-id" style="display:table-cell"><?php _e( 'Subscriber ID', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-id" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-id"  />
	                        </p>
	                        <?php do_action( 'add_leaky_paywall_subscriber_form' ); ?>
	                        </div>
	                        <?php submit_button( 'Add New Subscriber' ); ?>
	                        <?php wp_nonce_field( 'add_new_subscriber', 'leaky_paywall_add_subscriber' ); ?>
	                    </form>
	                    <form id="leaky-paywall-subscriber-bulk-add" name="leaky-paywall-subscriber-bulk-add" method="post">
	                    	<p><label for="leaky-paywall-subscriber-bulk-content" style="display:table-cell"><?php _e( 'Bulk Import', 'issuem-leaky-paywall' ); ?></label><textarea id="leaky-paywall-subscriber-bulk-add-content" name="leaky-paywall-subscriber-bulk-add-content"><?php echo join( ',', $headings ) . "\n"; ?></textarea></p>
	                    	<p class="description"><?php _e( 'Use double quotes " to enclose strings with commas', 'issuem-leaky-paywall' ); ?></p>
	                        <?php submit_button( 'Bulk Add Subscribers' ); ?>
	                        <?php wp_nonce_field( 'bulk_add_subscribers', 'leaky_paywall_bulk_add_subscribers' ); ?>
	                    </form>
                    <?php } ?>
					<br class="clear">
				</div>
			   
				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="leaky-paywall-subscribers" method="get">
					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<!-- Now we can render the completed list table -->
					<div class="tablenav top">
						<?php $subscriber_table->user_views(); ?>
						<?php $subscriber_table->search_box( __( 'Search Subscribers' ), 'issuem-leaky-paywall' ); ?>
					</div>
					<?php $subscriber_table->display(); ?>
				</form>
			   
			</div>
			<?php
			
		}
		
		function update_page() {
			// Display HTML form for the options below
			?>
			<div class=wrap>
            <div style="width:70%;" class="postbox-container">
            <div class="metabox-holder">	
            <div class="meta-box-sortables ui-sortable">
            
                <form id="issuem" method="post" action="">
            
                    <h2 style='margin-bottom: 10px;' ><?php _e( "Leaky Paywall Updater", 'issuem-leaky-paywall' ); ?></h2>
                    
					<?php
					
					$manual_update_version = get_option( 'leaky_paywall_manual_update_version' );
					$manual_update_version = '1.2.0'; //CHANGEME
										
					if ( version_compare( $manual_update_version, '2.0.0', '<' ) )
						$this->update_2_0_0();
									
					?>
                                        
					<?php wp_nonce_field( 'leaky_paywall_update', 'leaky_paywall_update_nonce' ); ?>
					
                    <?php do_action( 'leaky_paywall_update_form' ); ?>
                    
                </form>
                
            </div>
            </div>
            </div>
			</div>
			<?php
		}
		
		/**
		 * Upgrade function, tests for upgrade version changes and performs necessary actions
		 *
		 * @since 1.0.0
		 */
		function upgrade() {
			
			$settings = $this->get_settings();
			
			if ( isset( $settings['version'] ) )
				$old_version = $settings['version'];
			else
				$old_version = 0;
				
			/* Table Version Changes */
			if ( isset( $settings['db_version'] ) )
				$old_db_version = $settings['db_version'];
			else
				$old_db_version = 0;
				
			if ( 0 !== $old_version && version_compare( $old_version, '2.0.2', '<' ) )
				$this->update_2_0_2();
						
			$settings['version'] = LEAKY_PAYWALL_VERSION;
			$settings['db_version'] = LEAKY_PAYWALL_DB_VERSION;
			
			$this->update_settings( $settings );
			
		}
				
		function update_2_0_0() {
			global $wpdb;
						
			echo '<h3>' . __( 'Version 2.0.0 Update Process', 'issuem-leaky-paywall' ) . '</h1>';
			echo '<p>' . __( 'We have decided to use the WordPress Users table to instead of maintaining our own subscribers table. This process will copy all existing leaky paywall subscriber data to individual WordPress users.', 'issuem-leaky-paywall' ) . '</p>';
			
            $n = ( isset($_GET['n']) ) ? intval($_GET['n']) : 0;

			$sql = "SELECT lps.* FROM " . $wpdb->prefix . "leaky_paywall_subscribers as lps LIMIT " . $n . ", 5";

            $subscribers = $wpdb->get_results( $sql );
            
            echo "<ul>";
            foreach ( (array) $subscribers as $subscriber ) {
                echo '<li>' . sprintf( __( 'Copying user data for %s (%s mode user)...', 'issuem-leaky-paywall' ), $subscriber->email, $subscriber->mode );
                
                if ( $user = get_user_by( 'email', $subscriber->email ) ) { 
                	//the user already exists
                	//grab the ID for later
                    $user_id = $user->ID;
                } else {
                    //the user doesn't already exist
                    //create a new user with their email address as their username
                    //grab the ID for later
                    $parts = explode( '@', $subscriber->email );
                    $userdata = array(
					    'user_login' => $parts[0],
					    'user_email' => $subscriber->email,
					    'user_pass'  => wp_generate_password(),
					);
					$user_id = wp_insert_user( $userdata ) ;
                }
                
                if ( !empty( $user_id ) ) {
                
	                //now we want to set the Leaky Paywall subscriber meta
	                $meta = array(
	                	'level_id'			=> 0,
	                	'subscriber_id' 	=> $subscriber->subscriber_id,
	                	'price' 			=> $subscriber->price,
	                	'description' 		=> $subscriber->description,
	                	'plan' 				=> $subscriber->plan,
	                	'created' 			=> $subscriber->created,
	                	'expires' 			=> $subscriber->expires,
	                	'payment_gateway' 	=> $subscriber->payment_gateway,
	                	'payment_status' 	=> $subscriber->payment_status,
	                );
	                
	                foreach( $meta as $key => $value ) {
		
						update_user_meta( $user_id, '_issuem_leaky_paywall_' . $subscriber->mode . '_' . $key, $value );
						
					}
	                
	                echo __( 'completed.', 'issuem-leaky-paywall' );
                } else {
	                echo __( 'skipping.', 'issuem-leaky-paywall' );
                }
                
                echo '</li>';
                
            }
            echo "</ul>";
            
            if ( empty( $subscribers ) || 5 > count( $subscribers ) ) {
            
                echo '<p>' . __( 'Finished Migrating Subscribers!', 'issuem-leaky-paywall' ) . '</p>';
                echo '<p>' . __( 'Updating Settings...', 'issuem-leaky-paywall' ) . '</p>';
                
                $settings = $this->get_settings();
                
                if ( !is_array( $settings['payment_gateway'] ) )
                	$settings['payment_gateway'] = array( $settings['payment_gateway'] );
				
				if ( !empty( $settings['post_types'] ) ) {
					foreach ( $settings['post_types'] as $post_type ) {
						
						$restriction[] = array(
							'post_type' 	=> $post_type,
							'allowed_value' => $settings['free_articles'],
						);
						
					}
				}
                $settings['restrictions']['post_types'] = $restriction;

				if ( !empty( $settings['post_types'] ) ) {
					foreach ( $settings['post_types'] as $post_type ) {
						
						$allow_post_types[] = array(
							'post_type' 	=> $post_type,
							'allowed'		=> 'unlimited',
							'allowed_value' => -1,
						);
						
					}
				} else {
					$allow_post_types = array();
				}
				
				$settings['levels'] = array(
					'0' => array(
						'label' 					=> $settings['charge_description'],
						'price' 					=> $settings['price'],
						'recurring' 				=> $settings['recurring'],
						'subscription_length_type' 	=> empty( $settings['interval_count'] ) ? 'unlimited' : 'limited',
						'interval_count' 			=> $settings['interval_count'],
						'interval' 					=> $settings['interval'],
						'plan_id' 					=> $settings['plan_id'],
						'post_types' 				=> $allow_post_types,
					)
				);
                
				$this->update_settings( $settings );

                echo '<p>' . __( 'All Done!', 'issuem-leaky-paywall' ) . '</p>';
				update_option( 'leaky_paywall_manual_update_version', '2.0.0' );
                return;
                
            } else {
	            
	            ?><p><?php _e( 'If your browser doesn&#8217;t start loading the next page automatically, click this link:' ); ?> <a class="button" href="admin.php?page=leaky-paywall-update&amp;n=<?php echo ($n + 5) ?>"><?php _e( 'Next Subscribers', 'issuem-leaky-paywall' ); ?></a></p>
	            <script type='text/javascript'>
	            <!--
	            function nextpage() {
	                location.href = "admin.php?page=leaky-paywall-update&n=<?php echo ($n + 5) ?>";
	            }
	            setTimeout( "nextpage()", 250 );
	            //-->
	            </script><?php
	            
            }

		}
		
		function update_2_0_2() {
			$settings = $this->get_settings();
			$settings['login_method'] = 'passwordless';
			$this->update_settings( $settings );	
			$settings = $this->get_settings();
		}
		
		function update_notices() {
		
			global $hook_suffix;
			
			$settings = $this->get_settings();
			
			if ( isset( $settings['version'] ) )
				$old_version = $settings['version'];
			else
				$old_version = 0;
				
			if ( !empty( $old_version ) ) { //new installs shouldn't see this notice
				if ( current_user_can( 'manage_options' ) ) {
					if ( 'admin_page_leaky-paywall-update' !== $hook_suffix && 'leaky-paywall_page_leaky-paywall-update' !== $hook_suffix ) {
										
						$manual_update_version = get_option( 'leaky_paywall_manual_update_version' );
											
						if ( version_compare( $manual_update_version, '2.0.0', '<' ) ) {
							?>
							<div id="leaky-paywall-2-0-0-update-nag" class="update-nag">
								<?php
								$update_link = esc_url( add_query_arg( array( 'page' => 'leaky-paywall-update' ), admin_url( 'admin.php' ) ) );
								printf( __( 'You must update the Leaky Paywall Database to version 2 to continue using this plugin... %s', 'issuem-leaky-paywall' ), '<a class="btn" href="' . $update_link . '">' . __( 'Update Now', 'issuem-leaky-paywall' ) . '</a>' );
								?>
							</div>
							<?php
						}
					}
				}
			}
		}
		
		function paypal_standard_secure_notice() {
			if ( current_user_can( 'manage_options' ) ) {
				?>
				<div id="missing-paypal-settings" class="update-nag">
					<?php
					$settings_link = esc_url( add_query_arg( array( 'page' => 'issuem-leaky-paywall' ), admin_url( 'admin.php' ) ) );
					printf( __( 'You must complete your PayPal setup to continue using the Leaky Paywall Plugin. %s.', 'issuem-leaky-paywall' ), '<a class="btn" href="' . $settings_link . '">' . __( 'Complete Your Setup Now', 'issuem-leaky-paywall' ) . '</a>' );
					?>
				</div>
				<?php
			}
		}
		
		/**
         * API Request sent and processed by the IssueM API
         *
         * @since 1.0.0
         *
         * @param array $args Arguments to send to the IssueM API
         */
        function issuem_api_request( $_action, $_data ) {

                $api_params = array(
                        'edd_action' 	=> 'get_version',
                        'name' 			=> $_data['name'],
                        'slug' 			=> $_data['slug'],
                        'license' 		=> $_data['license'],
                        'author' 		=> 'zeen101 Development Team',
                );

                $request = wp_remote_post(
                        ZEEN101_STORE_URL,
                        array(
                                'timeout' => 15,
                                'sslverify' => false,
                                'body' => $api_params
                        )
                );

                if ( !is_wp_error( $request ) ) {

                        $request = json_decode( wp_remote_retrieve_body( $request ) );

                        if ( $request )
                                $request->sections = maybe_unserialize( $request->sections );

                        return $request;

                } else {

                        return false;

                }

        }
		
		function plugins_api( $_data, $_action = '', $_args = NULL ) {
			
			if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->plugin_slug ) ) 
				return $_data;
				
			$settings = $this->get_settings();
	
			$to_send = array( 
				'slug' 		=> $this->plugin_slug,
				'name'		=> $this->plugin_name,
				'license'	=> $settings['license_key'],
			);
	
			$api_response = $this->issuem_api_request( 'plugin_information', $to_send );			
			if ( false !== $api_response ) 
				$_data = $api_response;
	
			return $_data;
			
		}
		
		function update_plugins( $_transient_data ) {

			if( empty( $_transient_data->checked ) ) 
				return $_transient_data;
				
			$settings = $this->get_settings();
				
			// The transient contains the 'checked' information
			// Now append to it information form your own API
	
			$to_send = array( 
				'slug' 		=> $this->plugin_slug,
				'name'		=> $this->plugin_name,
				'license'	=> $settings['license_key'],
			);
	
			$api_response = $this->issuem_api_request( 'plugin_latest_version', $to_send );
			
			if( false !== $api_response && is_object( $api_response ) )
				if( version_compare( $api_response->new_version, $_transient_data->checked[$this->basename], '>' ) )
					$_transient_data->response[$this->basename] = $api_response;
			
			return $_transient_data;
			
		}
		
	}
	
}
