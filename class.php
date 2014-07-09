<?php
/**
 * Registers IssueM's Leaky Paywall class
 *
 * @package IssueM's Leaky Paywall
 * @since 1.0.0
 */

/**
 * This class registers the main issuem functionality
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'IssueM_Leaky_Paywall' ) ) {
	
	class IssueM_Leaky_Paywall {
		
		private $plugin_name	= ISSUEM_LEAKY_PAYWALL_NAME;
		private $plugin_slug	= ISSUEM_LEAKY_PAYWALL_SLUG;
		private $basename		= ISSUEM_LEAKY_PAYWALL_BASENAME;
		
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
					Stripe::setApiVersion( '2013-12-03' ); //Last version before Stripe changed subscription model
									
				}
			
			}
			
			if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) ) {
				
				if ( empty( $settings['paypal_live_api_username'] ) || empty( $settings['paypal_live_api_password'] ) || empty( $settings['paypal_live_api_secret'] ) ) {
					
					add_action( 'admin_notices', array( $this, 'paypal_standard_secure_notice' ) );
					
				}
				
			}
			
		}
		
		function issuem_pdf_attachment_url( $attachment_url, $attachment_id ) {
			return add_query_arg( 'issuem-pdf-download', $attachment_id );
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
					
			add_menu_page( __( 'Leaky Paywall', 'issuem-leaky-paywall' ), __( 'Leaky Paywall', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $this, 'settings_page' ), ISSUEM_LEAKY_PAYWALL_URL . '/images/issuem-16x16.png' );
			
			add_submenu_page( 'issuem-leaky-paywall', __( 'Settings', 'issuem-leaky-paywall' ), __( 'Settings', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'issuem-leaky-paywall', array( $this, 'settings_page' ) );
			
			add_submenu_page( 'issuem-leaky-paywall', __( 'Subscribers', 'issuem-leaky-paywall' ), __( 'Subscribers', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-subscribers', array( $this, 'subscribers_page' ) );
									
			add_submenu_page( false, __( 'Update', 'issuem-leaky-paywall' ), __( 'Update', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-update', array( $this, 'update_page' ) );
			
		}
				
		function process_requests() {
				
			$settings = $this->get_settings();
			
			issuem_leaky_paywall_maybe_process_payment();
			if ( issuem_leaky_paywall_maybe_process_webhooks() )
				die(); //no point in loading the whole page for webhooks
								
			if ( isset( $_REQUEST['issuem-pdf-download'] ) ) {
				
				//Admins or subscribed users can download PDFs
				if ( current_user_can( 'manage_options' ) || is_issuem_leaky_subscriber_logged_in() ) {
				
					issuem_leaky_paywall_server_pdf_download( $_REQUEST['issuem-pdf-download'] );
				
				} else {
					
					$output = '<h3>' . __( 'Unauthorize PDF Download', 'issuem-leaky-paywall' ) . '</h3>';
		
					$output .= '<p>' . sprintf( __( 'You must be <a href="%s">logged in</a> with a valid subscription to download Issue PDFs.', 'issuem-leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</p>';
					$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
					
					wp_die( apply_filters( 'issuem_leaky_paywall_unauthorized_pdf_download_output', $output ) );
					
				}
				
			}
			
			if ( is_singular() ) {
			
				if ( !current_user_can( 'manage_options' ) ) { //Admins can see it all
				
					// We don't ever want to block the login, subscription
					if ( !is_page( array( $settings['page_for_login'], $settings['page_for_subscription'] ) ) ) {
					
						global $post;
						$post_type_id = '';
						$restricted_post_type = '';
						$is_restricted = false;
						
						$restrictions = issuem_leaky_paywall_subscriber_restrictions();
						
						if ( empty( $restrictions ) )
							$restrictions = $settings['restrictions']; //default restrictions
						
						if ( !empty( $restrictions['post_types'] ) ) {
							
							foreach( $restrictions['post_types'] as $key => $restriction ) {
								
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
					
						$level_id = issuem_leaky_paywall_susbscriber_current_level_id();
						$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
						
						if ( false !== $visibility && !empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {
													
							switch( $visibility['visibility_type'] ) {
								
								case 'only':
									if ( !in_array( $level_id, $visibility['only_visible'], true ) ) {
										add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
										return;
									}
									break;
									
								case 'always':
									if ( in_array( -1, $visibility['always_visible'] ) || in_array( $level_id, $visibility['always_visible'] ) ) { //-1 = Everyone
										$is_restricted = false;
									}
									break;
								
								case 'onlyalways':
									if ( !in_array( $level_id, $visibility['only_always_visible'] ) ) {
										add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
										return;
									} else {
										$is_restricted = false;
									}
									break;
								
								
							}
							
						}
						
						if ( $is_restricted ) {
								
							global $post;
							
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
														
							if ( !empty( $_COOKIE['issuem_lp'] ) )
								$available_content = maybe_unserialize( stripslashes( $_COOKIE['issuem_lp'] ) );
							
							if ( empty( $available_content[$restricted_post_type] ) )
								$available_content[$restricted_post_type] = array();							
						
							foreach ( $available_content[$restricted_post_type] as $key => $restriction ) {
								
								if ( time() > $restriction || 7200 > $restriction ) { 
									//this post view has expired
									//Or it is very old and based on the post ID rather than the expiration time
									unset( $available_content[$restricted_post_type][$key] );
									
								}
								
							}
														
							if( -1 != $restrictions['post_types'][$post_type_id]['allowed_value'] ) { //-1 means unlimited
																							
								if ( $restrictions['post_types'][$post_type_id]['allowed_value'] > count( $available_content[$restricted_post_type] ) ) { 
								
									if ( !array_key_exists( $post->ID, $available_content[$restricted_post_type] ) ) {
										
										$available_content[$restricted_post_type][$post->ID] = $expiration;
									
									}
									
								} else {
								
									if ( !array_key_exists( $post->ID, $available_content[$restricted_post_type] ) ) {
											
										add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
										
									}
									
								}
							
							}
							
							$serialized_available_content = maybe_serialize( $available_content );
							setcookie( 'issuem_lp', $serialized_available_content, $expiration, '/' );
							$_COOKIE['issuem_lp'] = $serialized_available_content;
							
							return; //We don't need to process anything else after this
							
						}
						
					}
					
				}
	
			}
			
			if ( is_issuem_leaky_subscriber_logged_in() ) {
						
				if ( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) 
					&& isset( $_REQUEST['cancel'] ) ) {
					
					wp_die( issuem_leaky_paywall_cancellation_confirmation() );
					
				}
				
			
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {
					
					wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
					
				}
			
			} else {
			
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] )  && !empty( $_REQUEST['r'] ) ) {

					$login_hash = $_REQUEST['r'];
					
					if ( verify_issuem_leaky_paywall_hash( $login_hash ) ) {
					
						issuem_leaky_paywall_attempt_login( $login_hash );
						wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
						
					} else {
					
						$output  = '<h3>' . __( 'Invalid or Expired Login Link', 'issuem-leaky-paywall' ) . '</h3>';
						$output .= '<p>' . sprintf( __( 'Sorry, this login link is invalid or has expired. <a href="%s">Try again?</a>', 'issuem-leaky-paywall' ), get_page_link( $settings['page_for_login'] ) ) . '</p>';
						$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'issuem-leak-paywall' ), $settings['site_name'] ) . '</a>';
						
						wp_die( apply_filters( 'issuem_leaky_paywall_invalid_login_link', $output ) );

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
			if ( !is_issuem_leaky_subscriber_logged_in() ) {
				$message .= $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
			} else {
				$message .= $this->replace_variables( stripslashes( $settings['subscribe_upgrade_message'] ) );
			}
			$message .= '</div>';
		
			$new_content = $content . $message;
		
			return apply_filters( 'issuem_leaky_paywall_subscriber_or_login_message', $new_content, $message, $content );
			
		}
				
		function replace_variables( $message ) {
	
			$settings = $this->get_settings();
			
			if ( 0 === $settings['page_for_subscription'] )
				$subscription_url = get_bloginfo( 'wpurl' ) . '/?subscription'; //CHANGEME -- I don't really know what this is suppose to do...
			else
				$subscription_url = get_page_link( $settings['page_for_subscription'] );
				
			$message = str_ireplace( '{{SUBSCRIBE_LOGIN_URL}}', $subscription_url, $message );
			
			//Deprecated
			$message = str_ireplace( '{{PRICE}}', $settings['price'], $message );
			$message = str_ireplace( '{{LENGTH}}', $this->human_readable_interval( $settings['interval_count'], $settings['interval'] ), $message );
			
			return $message;
			
		}
		
		function human_readable_interval( $interval_count, $interval ) {
			
			if ( 0 == $interval_count )
				return __( 'for life', 'issuem-leaky-paywall' );
		
			if ( 1 < $interval_count )
				$interval .= 's';
			
			if ( 1 == $interval_count )
				return __( 'every', 'issuem-leaky-paywall' ) . ' ' . $interval;
			else
				return __( 'every', 'issuem-leaky-paywall' ) . ' ' . $interval_count . ' ' . $interval;
			
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
				wp_enqueue_style( 'issuem_leaky_paywall_admin_style', ISSUEM_LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-admin.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
				
			if ( 'post.php' === $hook_suffix || 'post-new.php' === $hook_suffix )
				wp_enqueue_style( 'issuem_leaky_paywall_post_style', ISSUEM_LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-post.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
			
		}
	
		/**
		 * Enqueues backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_enqueue_scripts( $hook_suffix ) {
			
			if ( 'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix
				|| 'toplevel_page_issuem-leaky-paywall' === $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_paywall_js', ISSUEM_LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-settings.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			if ( 'leaky-paywall_page_leaky-paywall-subscribers' === $hook_suffix
				|| 'toplevel_page_issuem-leaky-paywall' === $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_paywall_subscribers_js', ISSUEM_LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-subscribers.js', array( 'jquery-ui-datepicker' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			if ( 'post.php' === $hook_suffix|| 'post-new.php' === $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_paywall_post_js', ISSUEM_LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-post.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			
		}
		
		/**
		 * Enqueues frontend scripts and styles
		 *
		 * @since 1.0.0
		 */
		function frontend_scripts() {
			
			wp_enqueue_style( 'issuem-leaky-paywall', ISSUEM_LEAKY_PAYWALL_URL . '/css/issuem-leaky-paywall.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );

			
		}
		
		function activate_license() {
		
			// listen for our activate button to be clicked
			if( isset( $_POST['issuem_leaky_paywall_license_activate'] ) ) {
		
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
				$response = wp_remote_get( add_query_arg( $api_params, ISSUEM_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );

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
			if( isset( $_POST['issuem_leaky_paywall_license_deactivate'] ) ) {
		
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
				$response = wp_remote_get( add_query_arg( $api_params, ISSUEM_STORE_URL ), array( 'timeout' => 15, 'sslverify' => false ) );
		
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
		 * Get IssueM's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function get_settings() {
			
			$defaults = array( 
				'license_key'					=> '',
				'license_status'				=> '',
				'page_for_login'				=> 0,
				'page_for_subscription'			=> 0,
				'post_types'					=> ISSUEM_ACTIVE_LP ? array( 'article' ) : array( 'post' ),
				'free_articles'					=> 2,
				'cookie_expiration' 			=> 24,
				'cookie_expiration_interval' 	=> 'day',
				'subscribe_login_message'		=> __( '<a href="{{SUBSCRIBE_LOGIN_URL}}">Subscribe or log in</a> to read the rest of this content.', 'issuem-leaky-paywall' ),
				'subscribe_upgrade_message'		=> __( 'You must <a href="{{SUBSCRIBE_LOGIN_URL}}">upgrade your account</a> to read the rest of this content.', 'issuem-leaky-paywall' ),
				'css_style'						=> 'default',
				'site_name'						=> get_option( 'blogname' ),
				'from_name'						=> get_option( 'blogname' ),
				'from_email'					=> get_option( 'admin_email' ),
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
				'restrict_pdf_downloads' 		=> 'off',
				'restrictions' 	=> array(
					'post_types' => array(
						'post_type' 	=> ISSUEM_ACTIVE_LP ? 'article' : 'post',
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
								'post_type' 		=> 'post',
								'allowed'			=> 'limited',
								'allowed_value' 	=> 5,
							),
							array(
								'post_type' 		=> 'article',
								'allowed'			=> 'limited',
								'allowed_value' 	=> 10,
							),
						),
					)
				),

			);
		
			$defaults = apply_filters( 'issuem_leaky_paywall_default_settings', $defaults );
			
			$settings = get_option( 'issuem-leaky-paywall' );
												
			return wp_parse_args( $settings, $defaults );
			
		}
		
		/**
		 * Update IssueM's Leaky Paywall options
		 *
		 * @since 1.0.0
		 */
		function update_settings( $settings ) {
			
			update_option( 'issuem-leaky-paywall', $settings );
			
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

			if ( isset( $_REQUEST['update_issuem_leaky_paywall_settings'] ) ) {
					
				if ( !empty( $_REQUEST['license_key'] ) )
					$settings['license_key'] = $_REQUEST['license_key'];
					
				if ( !empty( $_REQUEST['page_for_login'] ) )
					$settings['page_for_login'] = $_REQUEST['page_for_login'];
					
				if ( !empty( $_REQUEST['page_for_subscription'] ) )
					$settings['page_for_subscription'] = $_REQUEST['page_for_subscription'];
				
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
					
				if ( !empty( $_REQUEST['cookie_expiration'] ) )
					$settings['cookie_expiration'] = trim( $_REQUEST['cookie_expiration'] );
					
				if ( !empty( $_REQUEST['cookie_expiration_interval'] ) )
					$settings['cookie_expiration_interval'] = trim( $_REQUEST['cookie_expiration_interval'] );
					
				if ( !empty( $_REQUEST['restrict_pdf_downloads'] ) )
					$settings['restrict_pdf_downloads'] = $_REQUEST['restrict_pdf_downloads'];
				else
					$settings['restrict_pdf_downloads'] = 'off';
					
				if ( !empty( $_REQUEST['subscribe_login_message'] ) )
					$settings['subscribe_login_message'] = trim( $_REQUEST['subscribe_login_message']);
					
				if ( !empty( $_REQUEST['subscribe_upgrade_message'] ) )
					$settings['subscribe_upgrade_message'] = trim( $_REQUEST['subscribe_upgrade_message']);
					
				if ( !empty( $_REQUEST['css_style'] ) )
					$settings['css_style'] = $_REQUEST['css_style'];
					
				if ( !empty( $_REQUEST['test_mode'] ) )
					$settings['test_mode'] = $_REQUEST['test_mode'];
				else
					$settings['test_mode'] = 'off';
					
				if ( !empty( $_REQUEST['payment_gateway'] ) )
					$settings['payment_gateway'] = $_REQUEST['payment_gateway'];
				else
					$settings['payment_gateway'] = array( 'stripe' );
					
				if ( !empty( $_REQUEST['live_secret_key'] ) )
					$settings['live_secret_key'] = trim( $_REQUEST['live_secret_key']);
					
				if ( !empty( $_REQUEST['live_publishable_key'] ) )
					$settings['live_publishable_key'] = trim( $_REQUEST['live_publishable_key']);
					
				if ( !empty( $_REQUEST['test_secret_key'] ) )
					$settings['test_secret_key'] = trim( $_REQUEST['test_secret_key']);
					
				if ( !empty( $_REQUEST['test_publishable_key'] ) )
					$settings['test_publishable_key'] = trim( $_REQUEST['test_publishable_key'] );
					
				if ( !empty( $_REQUEST['paypal_live_email'] ) )
					$settings['paypal_live_email'] = trim( $_REQUEST['paypal_live_email']);
					
				if ( !empty( $_REQUEST['paypal_live_api_username'] ) )
					$settings['paypal_live_api_username'] = trim( $_REQUEST['paypal_live_api_username']);
					
				if ( !empty( $_REQUEST['paypal_live_api_password'] ) )
					$settings['paypal_live_api_password'] = trim( $_REQUEST['paypal_live_api_password']);
					
				if ( !empty( $_REQUEST['paypal_live_api_secret'] ) )
					$settings['paypal_live_api_secret'] = trim( $_REQUEST['paypal_live_api_secret']);
					
				if ( !empty( $_REQUEST['paypal_sand_email'] ) )
					$settings['paypal_sand_email'] = trim( $_REQUEST['paypal_sand_email']);
					
				if ( !empty( $_REQUEST['paypal_sand_api_username'] ) )
					$settings['paypal_sand_api_username'] = trim( $_REQUEST['paypal_sand_api_username']);
					
				if ( !empty( $_REQUEST['paypal_sand_api_password'] ) )
					$settings['paypal_sand_api_password'] = trim( $_REQUEST['paypal_sand_api_password']);
					
				if ( !empty( $_REQUEST['paypal_sand_api_secret'] ) )
					$settings['paypal_sand_api_secret'] = trim( $_REQUEST['paypal_sand_api_secret']);
					
				if ( !empty( $_REQUEST['restrictions'] ) )
					$settings['restrictions'] = $_REQUEST['restrictions'];
				else
					$settings['restrictions'] = array();
					
				if ( !empty( $_REQUEST['levels'] ) )
					$settings['levels'] = $_REQUEST['levels'];
				else
					$settings['levels'] = array();
				
				$this->update_settings( $settings );
				$settings_saved = true;
				
				do_action( 'issuem_leaky_paywall_update_settings', $settings );
				
			}
			
			if ( $settings_saved ) {
				
				// update settings notification ?>
				<div class="updated"><p><strong><?php _e( "IssueM's Leaky Paywall Settings Updated.", 'issuem-leaky-paywall' );?></strong></p></div>
				<?php
				
			}
			
			// Display HTML form for the options below
			?>
			<div class=wrap>
            <div style="width:70%;" class="postbox-container">
            <div class="metabox-holder">	
            <div class="meta-box-sortables ui-sortable">
            
                <form id="issuem" method="post" action="">
            
                    <h2 style='margin-bottom: 10px;' ><?php _e( "IssueM's Leaky Paywall Settings", 'issuem-leaky-paywall' ); ?></h2>
                    
                    <div id="license-key" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'License Key', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_license_key" class="leaky-paywall-table">
                        	<tr>
                                <th rowspan="1"> <?php _e( 'License Key', 'issuem-leaky-paywall' ); ?></th>
                                <td class="leenkme_plugin_name">
                                <input type="text" id="license_key" class="regular-text" name="license_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['license_key'] ) ); ?>" />
                                
                                <?php if( $settings['license_status'] !== false 
										&& $settings['license_status'] == 'valid' ) { ?>
									<span style="color:green;"><?php _e('active'); ?></span>
									<input type="submit" class="button-secondary" name="issuem_leaky_paywall_license_deactivate" value="<?php _e( 'Deactivate License', 'issuem-leaky-paywall' ); ?>"/>
								<?php } else { ?>
									<input type="submit" class="button-secondary" name="issuem_leaky_paywall_license_activate" value="<?php _e( 'Activate License', 'issuem-leaky-paywall' ); ?>"/>
								<?php } ?>
                                <?php wp_nonce_field( 'verify', 'license_wpnonce' ); ?>
                                </td>
                            </tr>
                        </table>
                                                                                                         
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>
                        
                        </div>
                        
                    </div>
                                        
					<?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>

                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Leaky Paywall Options', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_administrator_options" class="leaky-paywall-table">
                        
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
                                <th><?php _e( 'Subscribe or Login Message', 'issuem-leaky-paywall' ); ?></th>
                                <td>
                    				<textarea id="subscribe_login_message" class="large-text" name="subscribe_login_message" cols="50" rows="3"><?php echo stripslashes( $settings['subscribe_login_message'] ); ?></textarea>
                                    <p class="description">
                                    <?php _e( "Available replacement variables: {{SUBSCRIBE_LOGIN_URL}}", 'issuem-leaky-paywall' ); ?>
                                    </p>
                                </td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Upgrade Message', 'issuem-leaky-paywall' ); ?></th>
                                <td>
                    				<textarea id="subscribe_upgrade_message" class="large-text" name="subscribe_upgrade_message" cols="50" rows="3"><?php echo stripslashes( $settings['subscribe_upgrade_message'] ); ?></textarea>
                                    <p class="description">
                                    <?php _e( "Available replacement variables: {{SUBSCRIBE_LOGIN_URL}}", 'issuem-leaky-paywall' ); ?>
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
                            
                        </table>
                                                                          
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Leaky Paywall Email Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_administrator_options" class="leaky-paywall-table">
                        
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
                            
                        </table>
                        
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                                        
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Payment Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_stripe_options" class="leaky-paywall-table">
                        
                        	<tr>
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
                                                        
                            <tr>
                            	<th><?php _e( "Test Mode?", 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="test_mode" name="test_mode" <?php checked( 'on', $settings['test_mode'] ); ?> /></td>
                            </tr>
                            
                        </table>
                        
                        <?php
                        if ( in_array( 'stripe', $settings['payment_gateway'] ) ) {
                        ?>
                        
                        <table id="issuem_leaky_paywall_stripe_options" class="leaky-paywall-table">
                        
	                        <tr><td colspan="2"><h3><?php _e( 'Stripe Settings', 'issuem-leaky-paywall' ); ?></h3></td></tr>
                            
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
                            	<td><p class="description"><?php echo add_query_arg( 'issuem-leaky-paywall-stripe-live-webhook', '1', get_site_url() . '/' ); ?></p></td>
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
                            	<td><p class="description"><?php echo add_query_arg( 'issuem-leaky-paywall-stripe-test-webhook', '1', get_site_url() . '/' ); ?></p></td>
                            </tr>
                            
                        </table>

                    <?php } ?>
                    
                    <?php
                    if ( in_array( 'paypal_standard', $settings['payment_gateway'] ) ) { 
                    ?>
                                            
                        <table id="issuem_leaky_paywall_paypal_options" class="leaky-paywall-table">
                        
	                        <tr><td colspan="2"><h3><?php _e( 'PayPal Standard Settings', 'issuem-leaky-paywall' ); ?></h3></td></tr>
                        
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
                            	<td><p class="description"><?php echo add_query_arg( 'issuem-leaky-paywall-paypal-standard-live-ipn', '1', get_site_url() . '/' ); ?></p></td>
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
                            	<th><?php _e( 'Live IPN', 'issuem-leaky-paywall' ); ?></th>
                            	<td><p class="description"><?php echo add_query_arg( 'issuem-leaky-paywall-paypal-standard-test-ipn', '1', get_site_url() . '/' ); ?></p></td>
                            </tr>
                            
                        </table>

                    <?php } ?>
                        
                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
                                                  
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Content Restriction', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_default_restriction_options" class="leaky-paywall-table">
                        	                            
                        	<tr>
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
                                	<p class="description"><?php _e( 'How do you describe this?', 'issuem-leaky-paywall' ); ?></p>
                                </td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Restrict PDF Downloads?', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="restrict_pdf_downloads" name="restrict_pdf_downloads" <?php checked( 'on', $settings['restrict_pdf_downloads'] ); ?> /></td>
                            </tr>
                            
                        	<?php 
                        	$last_key = -1;
                        	if ( !empty( $settings['restrictions']['post_types'] ) ) {
                        	
	                        	foreach( $settings['restrictions']['post_types'] as $key => $restriction ) {
	                        	
	                        		echo build_issuem_leaky_paywall_default_restriction_row( $restriction, $key );
	                        		$last_key = $key;
	                        		
	                        	}
	                        	
                        	}
                        	?>
                            
                        </table>
                    
				        <script type="text/javascript" charset="utf-8">
				            var issuem_leaky_paywall_restriction_row_key = <?php echo $last_key; ?>;
				        </script>
                    	
                    	<p>
                       		<input class="button-secondary" id="add-restriction-row" class="add-new-issuem-leaky-paywall-restriction-row" type="submit" name="add_issuem_leaky_paywall_restriction_row" value="<?php _e( 'Add New Restricted Content', 'issuem-leaky-paywall-multilevel' ); ?>" />
                    	</p>

                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>
                        
                        </div>
                        
                    </div>
                                        
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Subscription Levels', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                    
                        <table id="issuem_leaky_paywall_subscription_level_options" class="leaky-paywall-table">

							<tr><td id="issuem-leaky-paywall-subscription-level-rows" colspan="2">
                        	<?php 
                        	$last_key = -1;
                        	if ( !empty( $settings['levels'] ) ) {
                        	
	                        	foreach( $settings['levels'] as $key => $level ) {
	                        	
	                        		echo build_issuem_leaky_paywall_subscription_levels_row( $level, $key );
	                        		$last_key = $key;
	                        		
	                        	} 
	                        	
                        	}
                        	?>
							</td></tr>

                        </table>

				        <script type="text/javascript" charset="utf-8">
				            var issuem_leaky_paywall_subscription_levels_row_key = <?php echo $last_key; ?>;
				        </script>
                        
                        <p>
	                        <input class="button-secondary" id="add-subscription-row" class="add-new-issuem-leaky-paywall-subscription-row" type="submit" name="add_issuem_leaky_paywall_row" value="<?php _e( 'Add New Level', 'issuem-leaky-paywall-multilevel' ); ?>" />
                        </p>

                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    
                    <?php  do_action( 'issuem_leaky_paywall_settings_form', $settings ); ?>
                    
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
			
			$date_format = get_option( 'date_format' );
			$jquery_date_format = issuem_leaky_paywall_jquery_datepicker_format( $date_format );
			$headings = apply_filters( 'issuem_leaky_paywall_bulk_add_headings', array( 'username', 'email', 'price', 'expires', 'status' ) );
			
			$settings = get_issuem_leaky_paywall_settings();
			$mode = 'off' === $settings['test_mode'] ? 'live' : 'test';
		   
			?>
			<div class="wrap">
			   
				<div id="icon-users" class="icon32"><br/></div>
				<h2><?php _e( 'Leaky Paywall Subscribers', 'issuem-leaky-paywall' ); ?></h2>
                    
                <?php
                if ( !empty( $_POST['issuem_leaky_paywall_add_subscriber'] ) )  {
                    if ( !wp_verify_nonce( $_POST['issuem_leaky_paywall_add_subscriber'], 'add_new_subscriber' ) ) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __( 'Unable to verify security token. Subscriber not added. Please try again.', 'issuem-leaky-paywall' ) . '</strong></p></div>';

					}  else {
						// process form data
						if ( !empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) ) ) 
							&& !empty( $_POST['leaky-paywall-subscriber-login'] ) ) {
							
							$login = trim( rawurldecode( $_POST['leaky-paywall-subscriber-login'] ) );
							$email = trim( rawurldecode( $_POST['leaky-paywall-subscriber-email'] ) );
							$unique_hash = issuem_leaky_paywall_hash( $email );
							if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
								$expires = 0;
							else 
								$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
							
							$customer = new stdClass;
							$customer->id = '';
							
							$meta = array(
								'level_id' 			=> $_POST['leaky-paywall-subscriber-level-id'],
								'subscriber_id' 	=> '',
								'price' 			=> trim( $_POST['leaky-paywall-subscriber-price'] ),
								'description' 		=> __( 'Manual Addition', 'issuem-leaky-paywall' ),
								'expires' 			=> $expires,
								'payment_gateway' 	=> 'manual',
								'payment_status' 	=> $_POST['leaky-paywall-subscriber-status'],
								'interval' 			=> 0,
							);
							
							$user_id = issuem_leaky_paywall_new_subscriber( $unique_hash, $email, $customer, $meta, $login );
							
							do_action( 'add_leaky_paywall_subscriber', $user_id );
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                } else if ( !empty( $_POST['issuem_leaky_paywall_edit_subscriber'] ) )  {
                    if ( !wp_verify_nonce( $_POST['issuem_leaky_paywall_edit_subscriber'], 'edit_subscriber' ) ) {
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
								
								if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
									$expires = 0;
								else 
									$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
									
								if ( $price !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price' ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price', $price );
								if ( $expires !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires', $price );
								if ( $status !== get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' ) )
									update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status', $price );
								
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
								
								update_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', $_POST['leaky-paywall-subscriber-level-id'] );
								
								do_action( 'update_leaky_paywall_subscriber', $user->ID );
							}
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                } else if ( !empty( $_POST['issuem_leaky_paywall_bulk_add_subscribers'] ) )  {
                    if ( !wp_verify_nonce( $_POST['issuem_leaky_paywall_bulk_add_subscribers'], 'bulk_add_subscribers' ) ) {
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
								
								$unique_hash = issuem_leaky_paywall_hash( $email );
								
								if ( empty( $import[$keys['expires']] ) )
									$expires = 0;
								else 
									$expires = date( 'Y-m-d 23:59:59', strtotime( trim( $import[$keys['expires']] ) ) );
									
								if ( empty( $import[$keys['status']] ) )
									$status = 'active';
								else 
									$status = trim( $import[$keys['status']] );
									
								if ( empty( $import[$keys['level-id']] ) )
									$level_id = '';
								else 
									$level_id = trim( $import[$keys['level-id']] );
								
								$customer = new stdClass;
								$customer->id = '';
								
								$meta = array(
									'level_id'			=> $level_id,
									'subscriber_id' 	=> '',
									'price' 			=> $price,
									'description' 		=> __( 'Bulk Addition', 'issuem-leaky-paywall' ),
									'expires' 			=> $expires,
									'payment_gateway' 	=> 'manual',
									'payment_status' 	=> $status,
									'interval' 			=> 0,
								);
								
								$user_id = issuem_leaky_paywall_new_subscriber( $unique_hash, $email, $customer, $meta, $login );
								
								if ( !empty( $user_id ) )
									do_action( 'bulk_add_leaky_paywall_subscriber', $user_id, $keys, $import );
								else
									do_action( 'bulk_add_leaky_paywall_subscriber_failed', $keys, $import, $unique_hash, $email, $customer, $meta, $login );
								
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
				$subscriber_table = new IssueM_Leaky_Paywall_Subscriber_List_Table();
				$pagenum = $subscriber_table->get_pagenum();
				//Fetch, prepare, sort, and filter our data...
				$subscriber_table->prepare_items();
                $total_pages = $subscriber_table->get_pagination_arg( 'total_pages' );
		        if ( $pagenum > $total_pages && $total_pages > 0 ) {
	                wp_redirect( add_query_arg( 'paged', $total_pages ) );
	                exit;
		        }
		        
                ?>
			   
				<div id="leaky-paywall-subscriber-add-edit">
                	<?php 
                	$email = !empty( $_GET['edit'] ) ? trim( rawurldecode( $_GET['edit'] ) ) : '';
            		$user = get_user_by( 'email', $email );

                	if ( !empty( $email ) && !empty( $user ) ) {
                	
                		$login = $user->user_login;
                		$subscriber_level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id', true );
                		$payment_status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status', true );
                		
                		$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires', true );
						if ( '0000-00-00 00:00:00' === $expires )
							$expires = '';
						else
							$expires = mysql2date( $date_format, $expires );
						
						?>
	                    <form id="leaky-paywall-susbcriber-edit" name="leaky-paywall-subscriber-edit" method="post">
	                    	<div style="display: table">
	                    	<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e( 'Username (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-login" /></p><input id="leaky-paywall-subscriber-original-login" type="hidden" value="<?php echo $login; ?>" name="leaky-paywall-subscriber-original-login" /></p>
	                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="<?php echo $email; ?>" placeholder="support@issuem.com" name="leaky-paywall-subscriber-email" /></p><input id="leaky-paywall-subscriber-original-email" type="hidden" value="<?php echo $email; ?>" name="leaky-paywall-subscriber-original-email" /></p>
	                    	<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e( 'Price Paid', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="<?php echo get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_price', true ); ?>"  placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
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
	                        <?php do_action( 'update_leaky_paywall_subscriber_form', $user->ID ); ?>
	                        </div>
	                        <?php submit_button( 'Update Subscriber' ); ?>
	                        <p>
	                        <a href="<?php echo remove_query_arg( 'edit' ); ?>"><?php _e( 'Cancel', 'issuem-leaky-paywall' ); ?></a>
	                        </p>
	                        <?php wp_nonce_field( 'edit_subscriber', 'issuem_leaky_paywall_edit_subscriber' ); ?>
						</form>
                    <?php } else { ?>
	                    <form id="leaky-paywall-susbcriber-add" name="leaky-paywall-subscriber-add" method="post">
	                    	<div style="display: table">
	                    	<p><label for="leaky-paywall-subscriber-login" style="display:table-cell"><?php _e( 'Username (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-login" class="regular-text" type="text" value="" name="leaky-paywall-subscriber-login" /></p>
	                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@issuem.com" name="leaky-paywall-subscriber-email" /></p>
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
	                        <?php do_action( 'add_leaky_paywall_subscriber_form' ); ?>
	                        </div>
	                        <?php submit_button( 'Add New Subscriber' ); ?>
	                        <?php wp_nonce_field( 'add_new_subscriber', 'issuem_leaky_paywall_add_subscriber' ); ?>
	                    </form>
	                    <form id="leaky-paywall-subscriber-bulk-add" name="leaky-paywall-subscriber-bulk-add" method="post">
	                    	<p><label for="leaky-paywall-subscriber-bulk-content" style="display:table-cell"><?php _e( 'Bulk Import', 'issuem-leaky-paywall' ); ?></label><textarea id="leaky-paywall-subscriber-bulk-add-content" name="leaky-paywall-subscriber-bulk-add-content"><?php echo join( ',', $headings ) . "\n"; ?></textarea></p>
	                    	<p class="description"><?php _e( 'Use double quotes " to enclose strings with commas', 'issuem-leaky-paywall' ); ?></p>
	                        <?php submit_button( 'Bulk Add Subscribers' ); ?>
	                        <?php wp_nonce_field( 'bulk_add_subscribers', 'issuem_leaky_paywall_bulk_add_subscribers' ); ?>
	                    </form>
                    <?php } ?>
					<br class="clear">
				</div>
			   
				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="leaky-paywall-subscribers" method="get">
					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
					<!-- Now we can render the completed list table -->
					<?php $subscriber_table->search_box( __( 'Search Subscribers' ), 'issuem-leaky-paywall' ); ?>
					<?php $subscriber_table->display() ?>
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
                                        
					<?php wp_nonce_field( 'issuem_leaky_paywall_update', 'issuem_leaky_paywall_update_nonce' ); ?>
					
                    <?php do_action( 'issuem_leaky_paywall_update_form' ); ?>
                    
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
						
			$settings['version'] = ISSUEM_LEAKY_PAYWALL_VERSION;
			$settings['db_version'] = ISSUEM_LEAKY_PAYWALL_DB_VERSION;
			
			$this->update_settings( $settings );
			
		}
				
		function update_2_0_0() {
			global $wpdb;
						
			echo '<h3>' . __( 'Version 2.0.0 Update Process', 'issuem-leaky-paywall' ) . '</h1>';
			echo '<p>' . __( 'We have decided to use the WordPress Users table to instead of maintaining our own subscribers table. This process will copy all existing leaky paywall subscriber data to individual WordPress users.', 'issuem-leaky-paywall' ) . '</p>';
			
            $n = ( isset($_GET['n']) ) ? intval($_GET['n']) : 0;

			$sql = "SELECT lps.* FROM " . $wpdb->prefix . "issuem_leaky_paywall_subscribers as lps LIMIT " . $n . ", 5";

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
	                	'hash' 				=> $subscriber->hash,
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
								$update_link    = add_query_arg( array( 'page' => 'leaky-paywall-update' ), admin_url( 'admin.php' ) );
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
					$settings_link    = add_query_arg( array( 'page' => 'issuem-leaky-paywall' ), admin_url( 'admin.php' ) );
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
                        'author' 		=> 'IssueM Development Team',
                );

                $request = wp_remote_post(
                        ISSUEM_STORE_URL,
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