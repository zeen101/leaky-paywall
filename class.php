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
		
			session_start(); //we're using sessios to track logins and subsribers
			
			$settings = $this->get_settings();
		
			add_action( 'admin_init', array( $this, 'upgrade' ) );
			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_wp_enqueue_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'admin_wp_print_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
					
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );

			if ( ISSUEM_ACTIVE_LP ) {
				//Premium Plugin Filters
				add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins' ) );
			}
			
			add_action( 'wp', array( $this, 'process_requests' ) );
			
			if ( 'stripe' === $settings['payment_gateway'] ) {
				
				if ( !empty( $settings['test_secret_key'] ) || !empty( $settings['live_secret_key'] ) ) {
										
					// Initialized Stripe...
					require_once('include/stripe/Stripe.php');
					
					$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
					Stripe::setApiKey( $secret_key );
				
				}
			
			} else if ( 'paypal_standard' === $settings['payment_gateway'] ) {
				
			}
			
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
			
		}
		
		function process_requests() {
				
			$settings = $this->get_settings();
			
			if ( is_singular( $settings['post_types'] ) ) {
				
				if ( !is_user_logged_in() && !is_issuem_leaky_subscriber_logged_in() ) {
					
					global $post;
				
					$free_articles = array();
					
					if ( !empty( $_COOKIE['issuem_lp'] ) )
						$free_articles = maybe_unserialize( $_COOKIE['issuem_lp'] );
					
					if ( $settings['free_articles'] > count( $free_articles ) ) { 
					
						$free_articles[] = $post->ID;
						
					} else {
					
						if ( !in_array( $post->ID, $free_articles ) ) {
								
							add_filter( 'the_content', array( $this, 'get_the_excerpt' ), 15 );
							add_filter( 'the_content', array( $this, 'the_content_paywall' ), 15 );
							
						}
						
					}
					
					setcookie( 'issuem_lp', maybe_serialize( $free_articles ), time() + ( $settings['cookie_expiration'] * 60 * 60 ), '/' );
	
				}
	
			}
						
			if ( !empty( $_REQUEST['issuem-leaky-paywall-paypal-standard-ipn'] ) )
				issuem_process_paypal_standard_ipn();
			
			if ( isset( $_REQUEST['logout'] ) ) {
						
				unset( $_SESSION['issuem_lp_hash'] );
				unset( $_SESSION['issuem_lp_email'] );
				unset( $_SESSION['issuem_lp_subscriber'] );
				setcookie( 'issuem_lp_subscriber', null, 0, '/' );
				wp_safe_redirect( get_page_link( $settings['page_for_login'] ) );
				
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
			
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) 
					&& !empty( $_REQUEST['r'] ) ) {
				
					$_SESSION['issuem_lp_hash'] = $_REQUEST['r'];
					wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
					
				}
			
				if ( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) 
						&& empty( $_SESSION['issuem_lp_hash'] ) && empty( $_SESSION['issuem_lp_email'] ) ) {
													
						wp_safe_redirect( get_page_link( $settings['page_for_login'] ) );
				
				}
			
			}
			
		}
		
		function get_the_excerpt( $content ) {
		
			global $post;
			
			remove_filter( 'the_content', array( $this, 'get_the_excerpt' ), 15 );
			add_filter( 'excerpt_more', '__return_false' );
			
			return apply_filters( 'get_the_excerpt', $post->post_excerpt );
			
		}
		
		function the_content_paywall( $content ) {
		
			$settings = $this->get_settings();
			
			$message  = '<div id="leaky_paywall_message">';
			$message .= $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
			$message .= '</div>';
		
			$new_content = $content . $message;
		
			return apply_filters( 'issuem_leaky_paywal_subscriber_or_login_message', $new_content, $message, $content );
			
		}
		
		function replace_variables( $message ) {
	
			$settings = $this->get_settings();
			
			if ( 0 === $settings['page_for_login'] )
				$login_url = get_bloginfo( 'wpurl' ) . '/?login';
			else
				$login_url = get_page_link( $settings['page_for_login'] );
				
			$message = str_ireplace( '{{SUBSCRIBE_LOGIN_URL}}', $login_url, $message );
			$message = str_ireplace( '{{PRICE}}', $settings['price'], $message );
			$message = str_ireplace( '{{LENGTH}}', $this->human_readable_interval( $settings['interval_count'], $settings['interval'] ), $message );
			
			return $message;
			
		}
		
		function human_readable_interval( $interval_count, $interval ) {
			
			if ( 0 == $interval_count )
				return __( 'for life', 'issuem-leaky-paywall' );
		
			if ( 1 < $interval_count )
				$interval .= 's';
			
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
				'license_key'				=> '',
				'license_status'			=> '',
				'page_for_login'			=> 0,
				'page_for_subscription'		=> 0,
				'post_types'				=> ISSUEM_ACTIVE_LP ? array( 'article' ) : array( 'post' ),
				'free_articles'				=> 2,
				'cookie_expiration'			=> 24,
				'subscribe_login_message'	=> __( '<a href="{{SUBSCRIBE_LOGIN_URL}}">Subscribe or log in</a> to read the rest of this article. Subscriptions include access to the website and <strong>all back issues</strong>.', 'issuem-leaky-paywall' ),
				'css_style'					=> 'default',
				'site_name'					=> get_option( 'blogname' ),
				'from_name'					=> get_option( 'blogname' ),
				'from_email'				=> get_option( 'admin_email' ),
				'price'						=> '1.99',
				'interval_count'			=> 1,
				'interval'					=> 'month',
				'recurring'					=> 'off',
				'plan_id'					=> '',
				'charge_description'		=> __( 'Magazine Subscription', 'issuem-leaky-paywall' ),
				'payment_gateway'			=> 'stripe',
				'test_mode'					=> 'off',
				'live_secret_key'			=> '',
				'live_publishable_key'		=> '',
				'test_secret_key'			=> '',
				'test_publishable_key'		=> '',
				'paypal_live_email'			=> '',
				'paypal_sand_email'			=> '',
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
			
			if ( isset( $_REQUEST['update_issuem_leaky_paywall_settings'] )
				|| isset( $_REQUEST['issuem_leaky_paywall_license_activate'] ) ) {
				
				if ( !empty( $_REQUEST['license_key'] ) ) {
					
					if ( $settings['license_key'] != $_REQUEST['license_key'] ) {
						
						$settings['license_key'] = $_REQUEST['license_key'];
						unset( $settings['license_status'] );
						
					}
					
				}
					
				if ( isset( $_REQUEST['page_for_login'] ) )
					$settings['page_for_login'] = $_REQUEST['page_for_login'];
					
				if ( isset( $_REQUEST['page_for_subscription'] ) )
					$settings['page_for_subscription'] = $_REQUEST['page_for_subscription'];
				
				if ( isset( $_REQUEST['post_types'] ) )
					$settings['post_types'] = $_REQUEST['post_types'];
					
				if ( isset( $_REQUEST['free_articles'] ) )
					$settings['free_articles'] = trim( $_REQUEST['free_articles'] );
					
				if ( isset( $_REQUEST['site_name'] ) )
					$settings['site_name'] = trim( $_REQUEST['site_name'] );
					
				if ( isset( $_REQUEST['from_name'] ) )
					$settings['from_name'] = trim( $_REQUEST['from_name'] );
					
				if ( isset( $_REQUEST['from_email'] ) )
					$settings['from_email'] = trim( $_REQUEST['from_email'] );
					
				if ( isset( $_REQUEST['cookie_expiration'] ) )
					$settings['cookie_expiration'] = trim( $_REQUEST['cookie_expiration'] );
					
				if ( isset( $_REQUEST['recurring'] ) )
					$settings['recurring'] = $_REQUEST['recurring'];
				else
					$settings['recurring'] = 'off';
					
				if ( isset( $_REQUEST['plan_id'] ) )
					$settings['plan_id'] = trim( $_REQUEST['plan_id'] );
					
				if ( isset( $_REQUEST['price'] ) )
					$settings['price'] = number_format( $_REQUEST['price'], 2, '.', '' );
					
				if ( isset( $_REQUEST['interval'] ) )
					$settings['interval'] = $_REQUEST['interval'];
					
				if ( isset( $_REQUEST['interval_count'] ) )
					$settings['interval_count'] = (int)$_REQUEST['interval_count'];
					
				if ( isset( $_REQUEST['charge_description'] ) )
					$settings['charge_description'] = trim( $_REQUEST['charge_description']);
					
				if ( isset( $_REQUEST['subscribe_login_message'] ) )
					$settings['subscribe_login_message'] = trim( $_REQUEST['subscribe_login_message']);
					
				if ( isset( $_REQUEST['css_style'] ) )
					$settings['css_style'] = $_REQUEST['css_style'];
					
				if ( isset( $_REQUEST['test_mode'] ) )
					$settings['test_mode'] = $_REQUEST['test_mode'];
				else
					$settings['test_mode'] = 'off';
					
				if ( isset( $_REQUEST['payment_gateway'] ) )
					$settings['payment_gateway'] = $_REQUEST['payment_gateway'];
				else
					$settings['payment_gateway'] = 'stripe';
					
				if ( isset( $_REQUEST['live_secret_key'] ) )
					$settings['live_secret_key'] = trim( $_REQUEST['live_secret_key']);
					
				if ( isset( $_REQUEST['live_publishable_key'] ) )
					$settings['live_publishable_key'] = trim( $_REQUEST['live_publishable_key']);
					
				if ( isset( $_REQUEST['test_secret_key'] ) )
					$settings['test_secret_key'] = trim( $_REQUEST['test_secret_key']);
					
				if ( isset( $_REQUEST['test_publishable_key'] ) )
					$settings['test_publishable_key'] = trim( $_REQUEST['test_publishable_key'] );
					
				if ( isset( $_REQUEST['paypal_live_email'] ) )
					$settings['paypal_live_email'] = trim( $_REQUEST['paypal_live_email']);
					
				if ( isset( $_REQUEST['paypal_sand_email'] ) )
					$settings['paypal_sand_email'] = trim( $_REQUEST['paypal_sand_email']);
				
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
            
                <form id="issuem" method="post" action="" enctype="multipart/form-data" encoding="multipart/form-data">
            
                    <h2 style='margin-bottom: 10px;' ><?php _e( "IssueM's Leaky Paywall Settings", 'issuem-leaky-paywall' ); ?></h2>
                    
                    <?php if ( ISSUEM_ACTIVE_LP ) { ?>
                    <div id="license-key" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'License Key', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_license_key">
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
                    
                   	<?php } ?>
                    
					<?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>

                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Leaky Paywall Options', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_administrator_options">
                        
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
                            
                            <?php 
							$hidden_post_types = array( 'attachment', 'revision', 'nav_menu_item' );
							$post_types = get_post_types( array(), 'objects' );
							?>
                            
                        	<tr>
                                <th><?php _e( 'Leaky Post Types', 'leaky-paywall' ); ?></th>
                                <td>
								<select name="post_types[]" multiple="multiple">
                                <?php
								foreach ( $post_types as $post_type ) {
									
									if ( in_array( $post_type->name, $hidden_post_types ) ) 
										continue;
										
									echo '<option value="' . $post_type->name . '" ' . selected( in_array( $post_type->name, $settings['post_types'] ) ) . '>' . $post_type->name . '</option>';
								
                                }
                                ?>
                                </select>
                                </td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Number of Free Articles?', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="free_articles" class="small-text" name="free_articles" value="<?php echo htmlspecialchars( stripcslashes( $settings['free_articles'] ) ); ?>" /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Free Article Cookie Expiration', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="cookie_expireation" class="small-text" name="cookie_expireation" value="<?php echo stripcslashes( $settings['cookie_expiration'] ); ?>" /> <?php _e( 'hours', 'issuem-leaky-paywal' ); ?></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Subscribe or Login Message', 'issuem-leaky-paywall' ); ?></th>
                                <td>
                    				<textarea id="subscribe_login_message" class="large-text" name="subscribe_login_message" cols="50" rows="3"><?php echo stripslashes( $settings['subscribe_login_message'] ); ?></textarea>
                                    <p class="description">
                                    <?php _e( "Available replacement variables: {{SUBSCRIBE_LOGIN_URL}}, {{PRICE}}, {{LENGTH}}", 'issuem-leaky-paywall' ); ?>
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
                                <th><?php _e( 'Payment Gateway', 'issuem-leaky-paywall' ); ?></th>
                                <td>
								<select id='payment_gateway' name='payment_gateway'>
									<option value='stripe' <?php selected( 'stripe', $settings['payment_gateway'] ); ?> ><?php _e( 'Stripe', 'issuem-leaky-paywall' ); ?></option>
									<option value='paypal_standard' <?php selected( 'paypal_standard', $settings['payment_gateway'] ); ?> ><?php _e( 'PayPal Standard', 'issuem-leaky-paywall' ); ?></option>
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
                        
                        <table id="issuem_leaky_paywall_administrator_options">
                        
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
                        
                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
                                                  
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    <?php if ( 'stripe' === $settings['payment_gateway'] ) { ?>
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Stripe Payment Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_stripe_options">
                            
                            <tr>
                            	<th><?php _e( "Recurring?", 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="recurring" name="recurring" <?php checked( 'on', $settings['recurring'] ); ?> /></td>
                            </tr>
                            
                            <?php
							if ( 'off' === $settings['recurring'] )
								$hidden = 'style="display: none;"';
							else
								$hidden = '';
							?>
                        
                        	<tr class="stripe_plan" <?php echo $hidden; ?>>
                            	<th><?php _e( "Plan ID", 'issuem-leaky-paywall' ); ?></th>
                                <td>
                                	<input type="text" id="plan_id" class="regular-text" name="plan_id" value="<?php echo $settings['plan_id']; ?>" />
                                    <p class="description">
                                        <?php _e( 'To setup recurring payments, you must setup a Plan in the Stripe dashboard.<br />Then copy/paste the plan ID into these settings.', 'issuem-leaky-paywall' ); ?>
                                    </p>
                                </td>
                            </tr>
                            
                        	<tr class="stripe_manual" <?php echo $hidden; ?>>
                                <th><?php _e( 'Subscription Price', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="price" class="small-text" name="price" value="<?php echo stripcslashes( $settings['price'] ); ?>" /></td>
                            </tr>
                            
                        	<tr class="stripe_manual" <?php echo $hidden; ?>>
                                <th><?php _e( 'Subscription Length', 'issuem-leaky-paywall' ); ?></th>
                                <td><?php _e( 'For', 'issuem-leaky-paywall' ); ?> <input type="text" id="interval_count" class="small-text" name="interval_count" value="<?php echo stripcslashes( $settings['interval_count'] ); ?>" /> 
                                <select id="interval" name="interval">
                                	<option value="day" <?php selected( 'day' === $settings['interval'] ); ?>><?php _e( 'Day(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="week" <?php selected( 'week' === $settings['interval'] ); ?>><?php _e( 'Week(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="month" <?php selected( 'month' === $settings['interval'] ); ?> ><?php _e( 'Month(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="year" <?php selected( 'year' === $settings['interval'] ); ?> ><?php _e( 'Year(s)', 'issuem-leaky-paywall' ); ?></option>
                                </select>
                                <p class="description"><?php _e( 'Enter 0 for unlimited access.', 'issuem-leaky-paywall' ); ?></p>
                                </td>
                            </tr>
                        
                        	<tr>
                                <th><?php _e( 'Charge Description', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="charge_description" class="regular-text" name="charge_description" value="<?php echo htmlspecialchars( stripcslashes( $settings['charge_description'] ) ); ?>" /></td>
                            </tr>
                            
                            <tr>
                            	<th><?php _e( "Test Mode?", 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="test_mode" name="test_mode" <?php checked( 'on', $settings['test_mode'] ); ?> /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Live Secret Key', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="live_secret_key" class="regular-text" name="live_secret_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['live_secret_key'] ) ); ?>" /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Live Publishable Key', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="live_publishable_key" class="regular-text" name="live_publishable_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['live_publishable_key'] ) ); ?>" /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Test Secret Key', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="test_secret_key" class="regular-text" name="test_secret_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['test_secret_key'] ) ); ?>" /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Test Publishable Key', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="test_publishable_key" class="regular-text" name="test_publishable_key" value="<?php echo htmlspecialchars( stripcslashes( $settings['test_publishable_key'] ) ); ?>" /></td>
                            </tr>
                            
                        </table>
                        
                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
                                                  
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    <?php } else if ( 'paypal_standard' === $settings['payment_gateway'] ) { ?>
                    
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'PayPal Standard Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_paypal_options">
                            
                            <tr>
                            	<th><?php _e( "Recurring?", 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="recurring" name="recurring" <?php checked( 'on', $settings['recurring'] ); ?> /></td>
                            </tr>
                            
                        	<tr class="subscription_price">
                                <th><?php _e( 'Subscription Price', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="price" class="small-text" name="price" value="<?php echo stripcslashes( $settings['price'] ); ?>" /></td>
                            </tr>
                            
                            <?php
							if ( 'off' === $settings['recurring'] )
								$hidden = 'style="display: none;"';
							else
								$hidden = '';
							?>
                            
                        	<tr class="paypal_recurring">
                                <th><?php _e( 'Subscription Length', 'issuem-leaky-paywall' ); ?></th>
                                <td><?php _e( 'For', 'issuem-leaky-paywall' ); ?> <input type="text" id="interval_count" class="small-text" name="interval_count" value="<?php echo stripcslashes( $settings['interval_count'] ); ?>" /> 
                                <select id="interval" name="interval">
                                	<option value="day" <?php selected( 'day' === $settings['interval'] ); ?>><?php _e( 'Day(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="week" <?php selected( 'week' === $settings['interval'] ); ?>><?php _e( 'Week(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="month" <?php selected( 'month' === $settings['interval'] ); ?> ><?php _e( 'Month(s)', 'issuem-leaky-paywall' ); ?></option>
                                	<option value="year" <?php selected( 'year' === $settings['interval'] ); ?> ><?php _e( 'Year(s)', 'issuem-leaky-paywall' ); ?></option>
                                </select>
                                <p class="description"><?php _e( 'Enter 0 for unlimited access.', 'issuem-leaky-paywall' ); ?></p>
                                </td>
                            </tr>
                        
                        	<tr>
                                <th><?php _e( 'Charge Description', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="charge_description" class="regular-text" name="charge_description" value="<?php echo htmlspecialchars( stripcslashes( $settings['charge_description'] ) ); ?>" /></td>
                            </tr>
                            
                            <tr>
                            	<th><?php _e( "Sandbox Mode?", 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="checkbox" id="test_mode" name="test_mode" <?php checked( 'on', $settings['test_mode'] ); ?> /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'PayPal Email', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="paypal_live_email" class="regular-text" name="paypal_live_email" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_live_email'] ) ); ?>" /></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'PayPal Sandbox Email', 'issuem-leaky-paywall' ); ?></th>
                                <td><input type="text" id="paypal_sand_email" class="regular-text" name="paypal_sand_email" value="<?php echo htmlspecialchars( stripcslashes( $settings['paypal_sand_email'] ) ); ?>" /></td>
                            </tr>
                            
                        </table>
                        
                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
                                                  
                        <p class="submit">
                            <input class="button-primary" type="submit" name="update_issuem_leaky_paywall_settings" value="<?php _e( 'Save Settings', 'issuem-leaky-paywall' ) ?>" />
                        </p>

                        </div>
                        
                    </div>
                    
                    <?php } ?>
                    
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
			$headings = apply_filters( 'issuem_leaky_paywall_bulk_add_headings', array( 'email', 'price', 'expires', 'status' ) );
		   
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
						if ( !empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( urldecode( $_POST['leaky-paywall-subscriber-email'] ) ) ) ) {
							
							$email = trim( urldecode( $_POST['leaky-paywall-subscriber-email'] ) );
							$unique_hash = issuem_leaky_paywall_hash( $email );
							if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
								$expires = 0;
							else 
								$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
							
							$customer = new stdClass;
							$customer->id = '';
							
							$args = array(
								'subscriber_id'   => '',
								'price'			  => trim( $_POST['leaky-paywall-subscriber-price'] ),
								'description'	  => __( 'Manual Addition', 'issuem-leaky-paywall' ),
								'expires'		  => $expires,
								'payment_gateway' => 'manual',
								'payment_status'  => $_POST['leaky-paywall-subscriber-status'],
								'interval'        => 0,
							);
							
							issuem_leaky_paywall_new_subscriber( $unique_hash, $email, $customer, $args );
								
							$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $email );
							do_action( 'add_leaky_paywall_subscriber', $subscriber );
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                } else if ( !empty( $_POST['issuem_leaky_paywall_edit_subscriber'] ) )  {
                    if ( !wp_verify_nonce( $_POST['issuem_leaky_paywall_edit_subscriber'], 'edit_subscriber' ) ) {
						echo '<div class="error settings-error" id="setting-error-invalid_nonce"><p><strong>' . __( 'Unable to verify security token. Subscriber not added. Please try again.', 'issuem-leaky-paywall' ) . '</strong></p></div>';

					}  else {
						// process form data
						if ( !empty( $_POST['leaky-paywall-subscriber-email'] ) && is_email( trim( urldecode( $_POST['leaky-paywall-subscriber-email'] ) ) )
							&& !empty( $_POST['leaky-paywall-subscriber-original-email'] ) && is_email( trim( urldecode( $_POST['leaky-paywall-subscriber-original-email'] ) ) ) ) {
							
							$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $_POST['leaky-paywall-subscriber-original-email'] );
							
							$email = trim( urldecode( $_POST['leaky-paywall-subscriber-email'] ) );
							$price = trim( $_POST['leaky-paywall-subscriber-price'] );
							$status = $_POST['leaky-paywall-subscriber-status'];
							
							if ( empty( $_POST['leaky-paywall-subscriber-expires'] ) )
								$expires = 0;
							else 
								$expires = date( 'Y-m-d 23:59:59', strtotime( trim( urldecode( $_POST['leaky-paywall-subscriber-expires'] ) ) ) );
								
							if ( $subscriber->price !== $price )
								issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'price', $price );
							if ( $subscriber->expires !== $expires )
								issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'expires', $expires );
							if ( $subscriber->payment_status !== $status )
								issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'payment_status', $status );
								
							do_action( 'update_leaky_paywall_subscriber', $subscriber );
							
							if ( $subscriber->email !== $email )
								issuem_leaky_paywall_update_subscriber_column( $subscriber->email, 'email', $email );
							
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
									$imports[] = str_getcsv( trim( urldecode( stripslashes( $line ) ) ), ',', '"' );
							}
							
							$heading_line = array_shift( $imports );
							
							foreach( $headings as $heading ) {
								if ( false !== $key = array_search( $heading, $heading_line ) )
									$keys[$heading] = $key;
									
							}
							
							if ( !array_key_exists( 'email', $keys ) ) { //header line was not included (or modified)
								$imports = array_unshift( $imports, $heading_line ); //so add the line back for processing
								//assume these keys
								$keys['email'] = 0;
								$keys['price'] = 1;
								$keys['expires'] = 2;
								$keys['status'] = 3;
							}
							
							foreach( $imports as $import ) {
								
								$email = trim( $import[$keys['email']] );
								if ( empty( $email ) || !is_email( $email ) ) {
									$errors[] = sprintf( __( 'Invalid Email, line: %s', 'issuem-leaky-paywall' ), join( ',', $import ) );
									continue;
								}
								$unique_hash = issuem_leaky_paywall_hash( $email );
								if ( empty( $import[$keys['expires']] ) )
									$expires = 0;
								else 
									$expires = date( 'Y-m-d 23:59:59', strtotime( trim( $import[$keys['expires']] ) ) );
								
								$customer = new stdClass;
								$customer->id = '';
								
								$args = array(
									'subscriber_id'   => '',
									'price'			  => trim( $import[$keys['price']] ),
									'description'	  => __( 'Bulk Addition', 'issuem-leaky-paywall' ),
									'expires'		  => $expires,
									'payment_gateway' => 'manual',
									'payment_status'  => trim( $import[$keys['status']] ),
									'interval'        => 0,
								);
								
								issuem_leaky_paywall_new_subscriber( $unique_hash, $email, $customer, $args );
									
								$subscriber = get_issuem_leaky_paywall_subscriber_by_email( $email );
								do_action( 'bulk_add_leaky_paywall_subscriber', $subscriber, $keys, $import );
									
							}
							
							if ( !empty( $errors ) ) {
							
								echo '<div class="error settings-error" id="setting-error-bulk-import">';
								foreach( $errors as $error ) {
									echo '<p><strong>' . $error . '</strong></p>';
								}
								echo '</div>';								
							}
							
						} else {
						
							echo '<div class="error settings-error" id="setting-error-missing_email"><p><strong>' . __( 'You must include a valid email address.', 'issuem-leaky-paywall' ) . '</strong></p></div>';
							
						}
					
					}
                }
					
				//Create an instance of our package class...
				$subscriber_table = new IssueM_Leaky_Paywall_Subscriber_List_Table();
				//Fetch, prepare, sort, and filter our data...
				$subscriber_table->prepare_items();
                
                ?>
			   
				<div id="leaky-paywall-subscriber-add-edit">
                	<?php if ( !empty( $_GET['edit'] ) && $email = trim( urldecode( $email = $_GET['edit'] ) )
						&& $subscriber = get_issuem_leaky_paywall_subscriber_by_email( $email ) ) {
					if ( '0000-00-00 00:00:00' === $subscriber->expires )
						$expires = '';
					else
						$expires = mysql2date( $date_format, $subscriber->expires );
						
					?>
                    <form id="leaky-paywall-susbcriber-edit" name="leaky-paywall-subscriber-edit" method="post">
                    	<div style="display: table">
                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="<?php echo $subscriber->email; ?>" placeholder="support@issuem.com" name="leaky-paywall-subscriber-email" /></p><input id="leaky-paywall-subscriber-original-email" type="hidden" value="<?php echo $subscriber->email; ?>" name="leaky-paywall-subscriber-original-email" /></p>
                    	<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e( 'Price Paid', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value="<?php echo $subscriber->price; ?>"  placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
                    	<p>
                        <label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e( 'Expires', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="<?php echo $expires; ?>" placeholder="<?php echo date_i18n( $date_format, time() ); ?>"name="leaky-paywall-subscriber-expires"  />
                        <input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
                        </p>
                    	<p>
                        <label for="leaky-paywall-subscriber-status" style="display:table-cell"><?php _e( 'Status', 'issuem-leaky-paywall' ); ?></label>
                        <select name="leaky-paywall-subscriber-status">
                            <option value="active" <?php selected( 'active', $subscriber->payment_status ); ?>><?php _e( 'Active', 'issuem-leaky-paywall' ); ?></option>
                            <option value="canceled" <?php selected( 'canceled', $subscriber->payment_status ); ?>><?php _e( 'Canceled', 'issuem-leaky-paywall' ); ?></option>
                            <option value="deactivated" <?php selected( 'deactivated', $subscriber->payment_status ); ?>><?php _e( 'Deactivated', 'issuem-leaky-paywall' ); ?></option>
                        </select>
                        </p>
                        <?php do_action( 'update_leaky_paywall_subscriber_form', $subscriber ); ?>
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
                    	<p><label for="leaky-paywall-subscriber-email" style="display:table-cell"><?php _e( 'Email Address (required)', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-email" class="regular-text" type="text" value="" placeholder="support@issuem.com" name="leaky-paywall-subscriber-email" /></p>
                    	<p><label for="leaky-paywall-subscriber-price" style="display:table-cell"><?php _e( 'Price Paid', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-price" class="regular-text" type="text" value=""  placeholder="0.00" name="leaky-paywall-subscriber-price" /></p>
                    	<p>
                        <label for="leaky-paywall-subscriber-expires" style="display:table-cell"><?php _e( 'Expires', 'issuem-leaky-paywall' ); ?></label><input id="leaky-paywall-subscriber-expires" class="regular-text datepicker" type="text" value="" placeholder="<?php echo date_i18n( $date_format, time() ); ?>"name="leaky-paywall-subscriber-expires"  />
                        <input type="hidden" name="date_format" value="<?php echo $jquery_date_format; ?>" />
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
			
			if ( version_compare( $old_db_version, '1.0.0', '<' ) )
				$this->init_db_table();
			
			if ( version_compare( $old_db_version, '1.0.3', '<' ) )
				$this->update_db_1_0_3();
			
			if ( version_compare( $old_db_version, '1.0.4', '<' ) )
				$this->update_db_1_0_4();

			$settings['version'] = ISSUEM_LEAKY_PAYWALL_VERSION;
			$settings['db_version'] = ISSUEM_LEAKY_PAYWALL_DB_VERSION;
			
			$this->update_settings( $settings );
			
		}
		
		function init_db_table() {
			
			global $wpdb;
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_subscribers';

			//available subscriber status = pending, unsubscribed, subscribed, bounced
			//Max Email Length is 254 http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
			$sql = "CREATE TABLE $table_name (
				hash        VARCHAR(64)   NOT NULL,
				email       VARCHAR(254)  NOT NULL,
				stripe_id   VARCHAR(64)   NOT NULL,
				price       VARCHAR(8),
				description VARCHAR(254),
				plan        VARCHAR(64),
				created     DATETIME      NOT NULL,
				expires     DATETIME      NOT NULL,
				stripe_mode VARCHAR(4),
				UNIQUE KEY hash (hash)
			);";
			
			dbDelta( $sql );
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_logins';

			//available subscriber status = pending, unsubscribed, subscribed, bounced
			//Max Email Length is 254 http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
			$sql = "CREATE TABLE $table_name (
				hash    VARCHAR(64)  NOT NULL,
				email   VARCHAR(254) NOT NULL,
				created DATETIME     NOT NULL,
				expires DATETIME     NOT NULL,
				UNIQUE KEY hash (hash)
			);";
			
			dbDelta( $sql );
			
		}
		
		function update_db_1_0_3() {
			
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_subscribers';
			$wpdb->query( 'ALTER TABLE ' . $table_name . ' CHANGE stripe_id subscriber_id VARCHAR(64);' );
			$wpdb->query( 'ALTER TABLE ' . $table_name . ' CHANGE stripe_mode mode VARCHAR(4);' );
			
		}
		
		function update_db_1_0_4() {
			
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_subscribers';

			//available subscriber status = pending, unsubscribed, subscribed, bounced
			//Max Email Length is 254 http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
			$sql = "CREATE TABLE $table_name (
				hash            VARCHAR(64)   NOT NULL, 
				email           VARCHAR(254)  NOT NULL, 
				subscriber_id   VARCHAR(64)   NOT NULL, 
				price           VARCHAR(8), 
				description     VARCHAR(254), 
				plan            VARCHAR(64), 
				created         DATETIME      NOT NULL, 
				expires         DATETIME      NOT NULL, 
				mode            VARCHAR(4), 
				payment_gateway VARCHAR(32)   DEFAULT 'stripe',
				payment_status  VARCHAR(32),
				UNIQUE KEY hash (hash) 
			);";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			
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
	
			$api_response = issuem_api_request( 'plugin_information', $to_send );			
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
	
			$api_response = issuem_api_request( 'plugin_latest_version', $to_send );
			
			if( false !== $api_response && is_object( $api_response ) )
				if( version_compare( $api_response->new_version, $_transient_data->checked[$this->basename], '>' ) )
					$_transient_data->response[$this->basename] = $api_response;
			
			return $_transient_data;
			
		}
		
	}
	
}