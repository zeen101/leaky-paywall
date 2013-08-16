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

			if ( ISSUEM_ACTIVE ) {
				//Premium Plugin Filters
				add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins' ) );
			}
			
			if ( !empty( $settings['test_secret_key'] ) || !empty( $settings['live_secret_key'] ) ) {
				
				add_action( 'wp', array( $this, 'process_requests' ) );
				
				// Initialized Stripe...
				require_once('include/stripe/Stripe.php');
				
				define( 'ISSUEM_LP_PUBLISHABLE_KEY', ( 'on' === $settings['test_mode'] ) ? $settings['test_publishable_key'] : $settings['live_publishable_key'] );
				
				$secret_key = ( 'on' === $settings['test_mode'] ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
				Stripe::setApiKey( $secret_key );
			
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
			
			add_submenu_page( 'options-general.php', __( 'Leaky Paywall', 'issuem-leaky-paywall' ), __( 'Leaky Paywall', 'issuem-leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), 'leaky-paywall-settings', array( $this, 'settings_page' ) );
			
			if ( ISSUEM_ACTIVE )
				add_submenu_page( 'edit.php?post_type=article', __( 'Leaky Paywall', 'issuem-leaky-paywall' ), __( 'Leaky Paywall', 'issuem-leaky-paywall' ), apply_filters( 'manage_issuem_settings', 'manage_issuem_settings' ), 'leaky-paywall-settings', array( $this, 'settings_page' ) );
			
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
			
			if ( isset( $_REQUEST['logout'] ) ) {
			
				unset( $_SESSION['lp_hash'] );
				unset( $_SESSION['lp_email'] );
				unset( $_SESSION['lp_subscriber'] );
				setcookie( 'lp_subscriber', null, 0, '/' );
				wp_safe_redirect( get_page_link( $settings['page_for_login'] ) );
				
			}
			
			if ( is_issuem_leaky_subscriber_logged_in() ) {
						
				if ( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) 
					&& isset( $_REQUEST['cancel'] ) ) {
					
					wp_die( issuem_leaky_paywal_cancellation_confirmation() );
					
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
				
				/*
				if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) 
						&& ( !empty( $_SESSION['issuem_lp_hash'] ) || !empty( $_SESSION['issuem_lp_email'] ) ) ) {
													
						wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
				
				}
				*/
			
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
			
			if ( 'article_page_leaky-paywall-settings' === $hook_suffix )
				wp_enqueue_style( 'issuem_leaky_paywall_admin_style', ISSUEM_LEAKY_PAYWALL_URL . 'css/issuem-leaky-paywall-admin.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
			
		}
	
		/**
		 * Enqueues backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function admin_wp_enqueue_scripts( $hook_suffix ) {
			
			//echo "<h4>$hook_suffix</h4>";
			
			if ( 'article_page_leaky-paywall-settings' === $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_paywall_js', ISSUEM_LEAKY_PAYWALL_URL . 'js/issuem-leaky-paywall-settings.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
			
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
				'post_types'				=> ISSUEM_ACTIVE ? array( 'article' ) : array( 'post' ),
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
				'test_mode'					=> 'off',
				'live_secret_key'			=> '',
				'live_publishable_key'		=> '',
				'test_secret_key'			=> '',
				'test_publishable_key'		=> '',
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
					$settings['interval'] = (int)$_REQUEST['interval'];
					
				if ( isset( $_REQUEST['interval_count'] ) )
					$settings['interval_count'] = $_REQUEST['interval_count'];
					
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
					
				if ( isset( $_REQUEST['live_secret_key'] ) )
					$settings['live_secret_key'] = trim( $_REQUEST['live_secret_key']);
					
				if ( isset( $_REQUEST['live_publishable_key'] ) )
					$settings['live_publishable_key'] = trim( $_REQUEST['live_publishable_key']);
					
				if ( isset( $_REQUEST['test_secret_key'] ) )
					$settings['test_secret_key'] = trim( $_REQUEST['test_secret_key']);
					
				if ( isset( $_REQUEST['test_publishable_key'] ) )
					$settings['test_publishable_key'] = trim( $_REQUEST['test_publishable_key'] );
				
				$this->update_settings( $settings );
				$settings_saved = true;
				
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
                    
                    <?php if ( ISSUEM_ACTIVE ) { ?>
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
                                <td><?php echo wp_dropdown_pages( array( 'name' => 'page_for_login', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_login'] ) ); ?></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Page for Subscription', 'issuem-leaky-paywall' ); ?></th>
                                <td><?php echo wp_dropdown_pages( array( 'name' => 'page_for_subscription', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_subscription'] ) ); ?></td>
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
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Stripe Payment Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_administrator_options">
                            
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
                            
                            <?php
							if ( 'on' === $settings['recurring'] )
								$hidden = 'style="display: none;"';
							else
								$hidden = '';
							?>
                            
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
			
			if ( version_compare( $old_db_version, ISSUEM_LEAKY_PAYWALL_DB_VERSION, '<' ) )
				$this->init_db_table();

			$settings['version'] = ISSUEM_LEAKY_PAYWALL_VERSION;
			
			$this->update_settings( $settings );
			
		}
		
		function init_db_table() {
			
			global $wpdb;
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_subscribers';

			//available subscriber status = pending, unsubscribed, subscribed, bounced
			//Max Email Length is 254 http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
			$sql = "CREATE TABLE $table_name (
				hash 		VARCHAR(64) 	NOT NULL,
				email 		VARCHAR(254) 	NOT NULL,
				stripe_id 	VARCHAR(64) 	NOT NULL,
				price 		VARCHAR(8),
				description VARCHAR(254),
				plan 		VARCHAR(64),
				created 	DATETIME 		NOT NULL,
				expires 	DATETIME 		NOT NULL,
				UNIQUE KEY hash (hash),
				UNIQUE KEY email (email)
			);";
			
			dbDelta( $sql );
			
			$table_name = $wpdb->prefix . 'issuem_leaky_paywall_logins';

			//available subscriber status = pending, unsubscribed, subscribed, bounced
			//Max Email Length is 254 http://www.rfc-editor.org/errata_search.php?rfc=3696&eid=1690
			$sql = "CREATE TABLE $table_name (
				hash 		VARCHAR(64) 	NOT NULL,
				email 		VARCHAR(254) 	NOT NULL,
				created 	DATETIME 		NOT NULL,
				expires 	DATETIME 		NOT NULL,
				UNIQUE KEY hash (hash)
			);";
			
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