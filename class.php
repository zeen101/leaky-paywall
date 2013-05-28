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
		
		/**
		 * Class constructor, puts things in motion
		 *
		 * @since 1.0.0
		 */
		function IssueM_Leaky_Paywall() {
		
			session_start(); //we're using sessios to track logins and subsribers
			
			$settings = $this->get_issuem_leaky_paywall_settings();
		
			add_action( 'admin_init', array( $this, 'upgrade' ) );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'issuem_leaky_paywall_admin_wp_enqueue_scripts' ) );
			add_action( 'admin_print_styles', array( $this, 'issuem_leaky_paywall_admin_wp_print_styles' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'issuem_leaky_paywall_frontend_scripts' ) );
					
			add_action( 'admin_menu', array( $this, 'issuem_leaky_paywall_admin_menu' ) );

			//Premium Plugin Filters
			add_filter( 'plugins_api', array( $this, 'issuem_leaky_paywall_plugins_api' ), 10, 3 );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'issuem_leaky_paywall_update_plugins' ) );
			
			if ( !empty( $settings['test_secret_key'] ) || !empty( $settings['live_secret_key'] ) ) {
				
				add_action( 'wp', array( $this, 'process_leaky_paywall_requests' ) );
				
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
		function issuem_leaky_paywall_admin_menu() {
			
			add_submenu_page( 'edit.php?post_type=article', __( 'Leaky Paywall', 'pigeonpack' ), __( 'Leaky Paywall', 'pigeonpack' ), apply_filters( 'manage_issuem_settings', 'manage_issuem_settings' ), 'leaky-paywall-settings', array( $this, 'issuem_leaky_paywall_settings_page' ) );
			
		}
		
		function process_leaky_paywall_requests() {
				
			$settings = $this->get_issuem_leaky_paywall_settings();
			
			if ( is_singular( 'article' ) ) {
				
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
			
			if ( is_issuem_leaky_subscriber_logged_in() ) {
				
				if ( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) 
					&& isset( $_REQUEST['logout'] ) ) {
				
					unset( $_SESSION['issuem_lp_hash'] );
					unset( $_SESSION['issuem_lp_email'] );
					unset( $_SESSION['issuem_lp_subscriber'] );
					setcookie( 'issuem_lp_subscriber', null, 0, '/' );
					wp_safe_redirect( get_page_link( $settings['page_for_login'] ) );
					
				}
						
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
		
			$settings = $this->get_issuem_leaky_paywall_settings();
			
			$message  = '<div id="leaky_paywall_message">';
			$message .= $this->replace_leaky_paywall_variables( stripslashes( $settings['subscribe_login_message'] ) );
			$message .= '</div>';
		
			$new_content = $content . $message;
		
			return apply_filters( 'issuem_leaky_paywal_subscriber_or_login_message', $new_content, $message, $content );
			
		}
		
		function replace_leaky_paywall_variables( $message ) {
	
			$settings = $this->get_issuem_leaky_paywall_settings();
			
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
		function issuem_leaky_paywall_admin_wp_print_styles() {
		
			global $hook_suffix;
			
			if ( 'article_page_leaky-paywall-settings' === $hook_suffix )
				wp_enqueue_style( 'issuem_leaky_paywall_admin_style', ISSUEM_LEAKY_PAYWALL_PLUGIN_URL . 'css/issuem-leaky-paywall-admin.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
			
		}
	
		/**
		 * Enqueues backend IssueM styles
		 *
		 * @since 1.0.0
		 */
		function issuem_leaky_paywall_admin_wp_enqueue_scripts( $hook_suffix ) {
			
			//echo "<h4>$hook_suffix</h4>";
			
			if ( 'article_page_leaky-paywall-settings' === $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_paywall_js', ISSUEM_LEAKY_PAYWALL_PLUGIN_URL . 'js/issuem-leaky-paywall-settings.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			/*
			if ( 'post.php' == $hook_suffix )
				wp_enqueue_script( 'issuem_leaky_issue-edit-article-hacks', IM_URL . '/js/issuem_leaky_issue-edit-article-hacks.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			if ( 'article_page_issuem' == $hook_suffix )
				wp_enqueue_script( 'issuem-admin', IM_URL . '/js/issuem-admin.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
				
			if ( 'article_page_issuem-migration-tool' == $hook_suffix )
				wp_enqueue_script( 'issuem-migrate', IM_URL . '/js/issuem-migrate.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
			*/
		}
		
		/**
		 * Enqueues frontend scripts and styles
		 *
		 * @since 1.0.0
		 */
		function issuem_leaky_paywall_frontend_scripts() {
			
			/*
			wp_enqueue_script( 'jquery-issuem-flexslider', IM_URL . '/js/jquery.flexslider-min.js', array( 'jquery' ), ISSUEM_LEAKY_PAYWALL_VERSION );
			wp_enqueue_style( 'jquery-issuem-flexslider', IM_URL . '/css/flexslider.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
			*/
			wp_enqueue_style( 'issuem-leaky-paywall', ISSUEM_LEAKY_PAYWALL_PLUGIN_URL . '/css/issuem-leaky-paywall.css', '', ISSUEM_LEAKY_PAYWALL_VERSION );
			
		}
		
		/**
		 * Get IssueM options set in options table
		 *
		 * @since 1.0.0
		 */
		function get_issuem_leaky_paywall_settings() {
			
			$defaults = array( 
								'page_for_login'				=> 0,
								'page_for_subscription'			=> 0,
								'free_articles'					=> 2,
								'cookie_expiration'				=> 24,
								'subscribe_login_message'		=> __( '<a href="{{SUBSCRIBE_LOGIN_URL}}">Subscribe or log in</a> to read the rest of this article. Subscriptions include access to the website and <strong>all back issues</strong>.', 'issuem-leaky-paywall' ),
								'css_style'						=> 'default',
								'site_name'						=> get_option( 'blogname' ),
								'from_name'						=> get_option( 'blogname' ),
								'from_email'					=> get_option( 'admin_email' ),
								'price'							=> '1.99',
								'interval_count'				=> 1,
								'interval'						=> 'month',
								'recurring'						=> 'off',
								'plan_id'						=> '',
								'charge_description'			=> __( 'Magazine Subscription', 'issuem-leaky-paywall' ),
								'test_mode'						=> 'off',
								'live_secret_key'				=> '',
								'live_publishable_key'			=> '',
								'test_secret_key'				=> '',
								'test_publishable_key'			=> '',
							);
		
			$defaults = apply_filters( 'issuem_leaky_paywall_default_settings', $defaults );
			
			$settings = get_option( 'issuem-leaky-paywall' );
												
			return wp_parse_args( $settings, $defaults );
			
		}
		
		/**
		 * Create and Display IssueM settings page
		 *
		 * @since 1.0.0
		 */
		function issuem_leaky_paywall_settings_page() {
			
			// Get the user options
			$settings = $this->get_issuem_leaky_paywall_settings();
			
			if ( isset( $_REQUEST['update_issuem_leaky_paywall_settings'] ) ) {
					
				if ( isset( $_REQUEST['page_for_login'] ) )
					$settings['page_for_login'] = $_REQUEST['page_for_login'];
					
				if ( isset( $_REQUEST['page_for_subscription'] ) )
					$settings['page_for_subscription'] = $_REQUEST['page_for_subscription'];
					
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
					
				if ( isset( $_REQUEST['Charge Description'] ) )
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
				
				update_option( 'issuem-leaky-paywall', $settings );
					
				// It's not pretty, but the easiest way to get the menu to refresh after save...
				?>
					<script type="text/javascript">
					<!--
					window.location = "<?php echo $_SERVER['PHP_SELF'] .'?post_type=article&page=leaky-paywall-settings&settings_saved'; ?>"
					//-->
					</script>
				<?php
				
			}
			
			if ( isset( $_POST['update_issuem_leaky_paywall_settings'] ) || isset( $_GET['settings_saved'] ) ) {
				
				// update settings notification ?>
				<div class="updated"><p><strong><?php _e( 'IssueM Settings Updated.', 'issuem-leaky-paywall' );?></strong></p></div>
				<?php
				
			}
			
			// Display HTML form for the options below
			?>
			<div class=wrap>
            <div style="width:70%;" class="postbox-container">
            <div class="metabox-holder">	
            <div class="meta-box-sortables ui-sortable">
            
                <form id="issuem" method="post" action="" enctype="multipart/form-data" encoding="multipart/form-data">
            
                    <h2 style='margin-bottom: 10px;' ><?php _e( 'IssueM Leaky Paywall Settings', 'issuem-leaky-paywall' ); ?></h2>
                    
                    <div id="modules" class="postbox">
                    
                        <div class="handlediv" title="Click to toggle"><br /></div>
                        
                        <h3 class="hndle"><span><?php _e( 'Leaky Paywall Options', 'issuem-leaky-paywall' ); ?></span></h3>
                        
                        <div class="inside">
                        
                        <table id="issuem_leaky_paywall_administrator_options">
                        
                        	<tr>
                                <th><?php _e( 'Page for Log In', 'issuem' ); ?></th>
                                <td><?php echo wp_dropdown_pages( array( 'name' => 'page_for_login', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_login'] ) ); ?></td>
                            </tr>
                            
                        	<tr>
                                <th><?php _e( 'Page for Subscription', 'issuem' ); ?></th>
                                <td><?php echo wp_dropdown_pages( array( 'name' => 'page_for_subscription', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $settings['page_for_subscription'] ) ); ?></td>
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
                        
                        <?php wp_nonce_field( 'issuem_leaky_general_options', 'issuem_leaky_general_options_nonce' ); ?>
                                                  
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
                        
                        <h3 class="hndle"><span><?php _e( 'Strip Payment Gateway Settings', 'issuem-leaky-paywall' ); ?></span></h3>
                        
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
                                <th><?php _e( 'Subscription Price', 'issuem' ); ?></th>
                                <td><input type="text" id="price" class="small-text" name="price" value="<?php echo stripcslashes( $settings['price'] ); ?>" /></td>
                            </tr>
                            
                        	<tr class="stripe_manual" <?php echo $hidden; ?>>
                                <th><?php _e( 'Subscription Length', 'issuem' ); ?></th>
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
			
			$settings = $this->get_issuem_leaky_paywall_settings();
			
			if ( isset( $settings['version'] ) )
				$old_version = $settings['version'];
			else
				$old_version = 0;
			
			if ( version_compare( $old_version, '1.0.0', '<' ) )
				$this->upgrade_to_1_0_0();
				
			/* Table Version Changes */
			if ( isset( $settings['db_version'] ) )
				$old_db_version = $settings['db_version'];
			else
				$old_db_version = 0;
			
			if ( version_compare( $old_db_version, ISSUEM_LEAKY_PAYWALL_DB_VERSION, '<' ) )
				$this->init_db_table();

			$settings['version'] = ISSUEM_LEAKY_PAYWALL_VERSION;
			update_option( 'issuem-leaky-paywall', $settings );
			
		}
		
		/**
		 * Initialized permissions
		 *
		 * @since 1.0.0
		 */
		function upgrade_to_1_0_0() {
				
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
		
		function issuem_leaky_paywall_plugins_api( $false, $action, $args ) {
		
			$plugin_slug = ISSUEM_LEAKY_PAYWALL_PLUGIN_SLUG;
			
			// Check if this plugins API is about this plugin
			if( $args->slug != $plugin_slug )
				return $false;
				
			// POST data to send to your API
			$args = array(
				'action' 		=> 'get-plugin-information',
				'plugin_slug'	=> $plugin_slug,
			);
				
			// Send request for detailed information
			$response = issuem_api_request( $args );
				
			return $response;
			
		}
		
		function issuem_leaky_paywall_update_plugins( $transient ) {
			
			// Check if the transient contains the 'checked' information
    		// If no, just return its value without hacking it
			if ( empty( $transient->checked ) )
				return $transient;
		
			// The transient contains the 'checked' information
			// Now append to it information form your own API
			$plugin_slug = ISSUEM_LEAKY_PAYWALL_PLUGIN_SLUG;
				
			// POST data to send to your API
			$args = array(
				'action' 		=> 'check-latest-version',
				'plugin_slug'	=> $plugin_slug,
			);
			
			// Send request checking for an update
			$response = issuem_api_request( $args );
				
			// If there is a new version, modify the transient
			if ( isset( $response->new_version ) )
				if( version_compare( $response->new_version, $transient->checked[ISSUEM_LEAKY_PAYWALL_BASENAME], '>' ) )
					$transient->response[ISSUEM_LEAKY_PAYWALL_BASENAME] = $response;
				
			return $transient;
			
		}
		
	}
	
}