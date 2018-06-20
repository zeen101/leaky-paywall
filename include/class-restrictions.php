<?php

/**
* Load the Restrictions Class
*/
class Leaky_Paywall_Restrictions {

	/** @var string Name of the restriction cookie */
	private $cookie_name = 'issuem_lp';

	/**
	 * Kick off the restriction process
	 *
	 * @since 4.10.3
	 *
	 */
	public function process()
	{

		$settings = get_leaky_paywall_settings();

		do_action( 'leaky_paywall_before_process_requests', $settings );

		$has_subscriber_paid = leaky_paywall_has_user_paid();

		if ( isset( $_REQUEST['issuem-pdf-download'] ) ) {
			$this->pdf_access( $has_subscriber_paid );
		}

		if ( is_singular() ) {
			$this->content_access();
		}

		if ( $has_subscriber_paid ) {

			if ( $this->is_cancel_request() ) {
				wp_die( leaky_paywall_cancellation_confirmation(), $settings['site_name'] . ' - Cancel Request' );
			}

			$this->redirect_from_login_page();

		} else {

			if ( !empty( $_REQUEST['r'] ) ) {
				$this->process_passwordless_login();
			}
		}

	}

	public function subscriber_can_view()
	{

		if ( !is_singular() ) {
			return true;
		}

		global $post;
		$settings = get_leaky_paywall_settings();

		// allow admins to view all content
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) {
			return true;
		}

		// We don't ever want to block the login, subscription, etc.
		if ( $this->is_unblockable_content() ) {
			return true;
		}

		if ( $this->visibility_allows_access( $post ) ) {
			return true;
		}

		if ( $this->visibility_is_restricted( $post ) ) {
			return false;
		}

		$restrictions = $this->get_subscriber_restrictions();

		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;

		if ( !empty( $restrictions ) ) {

			foreach( $restrictions as $key => $restriction ) {

				if ( is_singular( $restriction['post_type'] ) ) {

					// this will only be ignored if the allowed value is unlimited ( -1 )
					if ( 0 <= $restriction['allowed_value'] ) {

						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						$is_restricted = true;
						break;

					}

				}

			}

		}

		$is_restricted = apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $restrictions, $post );

		if ( !$is_restricted ) {
			return true;
		}

		$available_content = $this->get_available_content($restricted_post_type);

		if ( $this->combined_restrictions_enabled() ) {

			if ( $this->is_restricted_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type ) ) {
				return false;
			}

		} else {

			if ( $this->is_restricted_default( $restrictions, $available_content, $post_type_id, $restricted_post_type ) ) {
				return false;
			}

		}

		return true;

	}


	/**
	 * Determine if the user has pdf access
	 *
	 * @since 4.10.3
	 *
	 * @param boolean $has_subscriber_paid
	 */
	public function pdf_access( $has_subscriber_paid )
	{

		$settings = get_leaky_paywall_settings();

		//Admins or subscribed users can download PDFs
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) || $has_subscriber_paid ) {
			leaky_paywall_server_pdf_download( $_REQUEST['issuem-pdf-download'] );
		} else {

			$output = '<h3>' . __( 'Unauthorize PDF Download', 'leaky-paywall' ) . '</h3>';
			$output .= '<p>' . sprintf( __( 'You must be logged in with a valid subscription to download Issue PDFs. Please <a href="%s">log in</a> or <a href="%s">subscribe</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_login'] ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
			$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'leak-paywall' ), $settings['site_name'] ) . '</a>';

			wp_die( apply_filters( 'leaky_paywall_unauthorized_pdf_download_output', $output ), $settings['site_name'] . ' - Error' );

		}

	}

	/**
	 * Determine if the user has content access
	 *
	 * @since 4.10.3
	 *
	 */
	public function content_access()
	{

		global $post;
		$settings = get_leaky_paywall_settings();

		// allow admins to view all content
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) {
			return;
		}

		// We don't ever want to block the login, subscription, etc.
		if ( $this->is_unblockable_content() ) {
			return;
		}

		if ( $this->visibility_allows_access( $post ) ) {
			return;
		}

		if ( $this->visibility_is_restricted( $post ) ) {
			$this->restrict_content();
		}

		// determine if current content's post type has a limited allowed value in the restriction settings
		$restrictions = $this->get_subscriber_restrictions();

		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;

		if ( !empty( $restrictions ) ) {

			foreach( $restrictions as $key => $restriction ) {

				if ( isset( $restriction['post_type'] ) && is_singular( $restriction['post_type'] ) ) {

					// this will only be ignored if the allowed value is unlimited ( -1 )
					if ( 0 <= $restriction['allowed_value'] ) {

						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						$is_restricted = true;
						break;

					}

				}

			}

		}

		$is_restricted = apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $restrictions, $post );

		if ( !$is_restricted ) {
			return;
		}

		// content that can be accessed because the user has viewed it already
		$available_content = $this->get_available_content($restricted_post_type);

		if ( $this->combined_restrictions_enabled() ) {

			// maybe update available content array
			$available_content = $this->update_available_content_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type );

			if ( $this->is_restricted_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type ) ) {
				$this->restrict_content();
			}

			// $available_content = $this->combined_restriction_access( $restrictions, $available_content, $post_type_id, $restricted_post_type );
		} else {

			$available_content = $this->update_available_content_default( $restrictions, $available_content, $post_type_id, $restricted_post_type );

			if ( $this->is_restricted_default( $restrictions, $available_content, $post_type_id, $restricted_post_type ) ) {
				$this->restrict_content();
			}

			// $available_content = $this->default_restriction_access( $restrictions, $available_content, $post_type_id, $restricted_post_type );
		}

		$this->set_available_content_cookie( $available_content );

	}

	/**
	 * Process the default restriction access
	 *
	 * @since 4.10.3
	 *
	 * @param array $restrictions
	 * @param array $available_content
	 * @param string $post_type_id
	 * @param string $restricted_post_type
	 *
	 * @return array $availabled_content
	 */
	public function default_restriction_access( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		if( -1 != $restrictions[$post_type_id]['allowed_value'] ) { //-1 means unlimited

			if ( $restrictions[$post_type_id]['allowed_value'] > count( $available_content[$restricted_post_type] ) ) {

				// if this post hasn't been added to the available content array, do so now
				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					$available_content[$restricted_post_type][$post_id] = $this->get_expiration_time();
				}

			} else {

				// if this post hasn't been viewed before, then it needs to be restricted
				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					$this->restrict_content();
				}

			}

		}

		return $available_content;

	}

	public function update_available_content_default( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		if( -1 != $restrictions[$post_type_id]['allowed_value'] ) { //-1 means unlimited

			if ( $restrictions[$post_type_id]['allowed_value'] > count( $available_content[$restricted_post_type] ) ) {

				// if this post hasn't been added to the available content array, do so now
				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					$available_content[$restricted_post_type][$post_id] = $this->get_expiration_time();
				}

			}

		}

		return $available_content;

	}

	public function is_restricted_default( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		if( -1 != $restrictions[$post_type_id]['allowed_value'] ) { //-1 means unlimited

			if ( $restrictions[$post_type_id]['allowed_value'] <= count( $available_content[$restricted_post_type] ) ) {

				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					return true;
				}

			}

		}

		return false;

	}

	public function combined_restrictions_enabled()
	{

		$settings = get_leaky_paywall_settings();

		if ( 'on' == $settings['enable_combined_restrictions'] ) {
			return true;
		}

		return false;

	}

	/**
	 * Process the combined restriction access
	 *
	 * @since 4.10.3
	 *
	 * @param array $restrictions
	 * @param array $available_content
	 * @param string $post_type_id
	 * @param string $restricted_post_type
	 *
	 * @return array $availabled_content
	 */
	public function combined_restriction_access( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$total_allowed = $this->get_combined_restriction_total_allowed();
		$total_viewed = $this->get_total_content_viewed( $available_content );

		if ( -1 != $restrictions[$post_type_id]['allowed_value'] ) {

			if ( $total_allowed > $total_viewed ) {

				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					$available_content[$restricted_post_type][$post_id] = $this->get_expiration_time();
				}

			} else {

				if ( $this->content_never_viewed( $available_content, $post_id ) ) {
					$this->restrict_content();
				}

			}
		}

		return $available_content;

	}

	public function update_available_content_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{
		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$total_allowed = $this->get_combined_restriction_total_allowed();
		$total_viewed = $this->get_total_content_viewed( $available_content );

		if ( -1 != $restrictions[$post_type_id]['allowed_value'] ) {

			if ( $total_allowed > $total_viewed ) {

				if ( !array_key_exists( $post_id, $available_content[$restricted_post_type] ) ) {
					$available_content[$restricted_post_type][$post_id] = $this->get_expiration_time();
				}

			}

		}

		return $available_content;
	}



	public function is_restricted_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		$restricted = false;
		$total_allowed = $this->get_combined_restriction_total_allowed();
		$total_viewed = $this->get_total_content_viewed( $available_content );

		if ( -1 != $restrictions[$post_type_id]['allowed_value'] ) {

			if ( $total_allowed <= $total_viewed ) {

				if ( $this->content_never_viewed( $available_content, $post_id ) ) {

					$restricted = true;
				}

			}
		}

		return $restricted;
	}

	/**
	 * Determine if the current content has never been viewed by the current user
	 *
	 * @since 4.10.3
	 *
	 * @param array $available_content
	 *
	 * @return boolean
	 */
	public function content_never_viewed( $available_content, $post_id = null )
	{

		if ( !$post_id ) {
			global $post;
			$post_id = $post->ID;
		}

		foreach( $available_content as $content ) {

			if ( array_key_exists( $post_id, $content ) ) {
				return false;
			}

		}

		return true;

	}

	/**
	 * Calculate all content items that have been viewed the current user
	 *
	 * @since 4.10.3
	 *
	 * @param array $available_content
	 *
	 * @return string $total_viewed Number of content items viewed
	 */
	public function get_total_content_viewed( $available_content )
	{

		$total_viewed = 0;

		foreach( $available_content as $content ) {

			$total_viewed += count( $content );

		}

		return $total_viewed;

	}

	/**
	 * Get the combined restriction total allowed content items
	 *
	 * @since 4.10.3
	 *
	 * @return string $content_items_allowed
	 */
	public function get_combined_restriction_total_allowed()
	{
		$settings = get_leaky_paywall_settings();
		$total_allowed = $settings['combined_restrictions_total_allowed'];

		return $total_allowed;
	}

	/**
	 * Get the content that the user has already viewed
	 *
	 * @since 4.10.3
	 *
	 * @return array $available_content Array of post ids that have been viewed
	 */
	public function get_available_content($restricted_post_type)
	{

		if ( !empty( $_COOKIE[$this->get_cookie_name()] ) ) {
			$available_content = json_decode( stripslashes( $_COOKIE[$this->get_cookie_name()] ), true );
		} else {
			$available_content = array();
		}

		if ( empty( $available_content[$restricted_post_type] ) ) {
			$available_content[$restricted_post_type] = array();
		}

		// Current post view has expired or it is very old and based on the post ID rather than the expiration time
		foreach ( $available_content[$restricted_post_type] as $key => $restriction ) {

			if ( time() > $restriction || 7200 > $restriction ) {
				unset( $available_content[$restricted_post_type][$key] );
			}

		}

		return apply_filters( 'leaky_paywall_available_content', $available_content );
	}

	/**
	 * Update the available content cookie with the current post id
	 *
	 * @since 4.10.3
	 *=
	 * @param array $available_content
	 *
	 */
	public function set_available_content_cookie( $available_content )
	{

		$json_available_content = json_encode( $available_content );

		$cookie = setcookie( $this->get_cookie_name(), $json_available_content, $this->get_expiration_time(), '/' );
		$_COOKIE[$this->get_cookie_name()] = $json_available_content;

	}

	/**
	 * Determine if the current content can be viewed based on the Leaky Paywall visibility settings on the content item
	 *
	 * @since 4.10.3
	 *
	 * @param object $post
	 *
	 * @return boolean
	 */
	public function visibility_allows_access( $post )
	{

		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
		$level_ids = leaky_paywall_subscriber_current_level_ids();

		if ( false !== $visibility && !empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {

			switch( $visibility['visibility_type'] ) {

				case 'only':
					$only = array_intersect( $level_ids, $visibility['only_visible'] );
					if ( empty( $only ) ) {
						// $this->restrict_content();
					}
					break;

				case 'always':
					$always = array_intersect( $level_ids, $visibility['always_visible'] );
					if ( in_array( -1, $visibility['always_visible'] ) || !empty( $always ) ) { //-1 = Everyone
						return true; //always visible, don't need process anymore
					}
					break;

				case 'onlyalways':
					$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
					if ( empty( $onlyalways ) ) {
						$this->restrict_content();
					} else if ( !empty( $onlyalways ) ) {
						return true; //always visible, don't need process anymore
					}
					break;

			}

		}

		return false;

	}

	public function visibility_is_restricted( $post )
	{
		$visibility = get_post_meta( $post->ID, '_issuem_leaky_paywall_visibility', true );
		$level_ids = leaky_paywall_subscriber_current_level_ids();
		$is_restricted = false;

		if ( false !== $visibility && !empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {

			if ( $visibility['visibility_type'] == 'only' ) {
				$only = array_intersect( $level_ids, $visibility['only_visible'] );
				if ( empty( $only ) ) {
					$is_restricted = true;
				}
			}



		}

		return $is_restricted;
	}

	/**
	 * If the content needs to be restricted, perform the actions necessary for that to happen
	 *
	 * @since 4.10.3
	 */
	public function restrict_content()
	{
		add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
		do_action( 'leaky_paywall_is_restricted_content' );
	}

	/**
	 * Display the content paywall barrier
	 *
	 * @since 4.10.3
	 *
	 * @param string $content
	 *
	 * @return string $new_content conten for paywall barrier
	 */
	public function the_content_paywall( $content ) {

		$settings = get_leaky_paywall_settings();

		add_filter( 'excerpt_more', '__return_false' );

		//Remove the_content filter for get_the_excerpt calls
		remove_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
		$content = get_the_excerpt();
		add_filter( 'the_content', array( $this, 'the_content_paywall' ), 999 );
		//Add the_content filter back for futhre the_content calls

		$message = $this->the_content_paywall_message();
		$new_content = $content . $message;

		return apply_filters( 'leaky_paywall_subscribe_or_login_message', $new_content, $message, $content );

	}

	public function the_content_paywall_message() 
	{

		$settings = get_leaky_paywall_settings();
		
		$message  = '<div class="leaky_paywall_message_wrap"><div id="leaky_paywall_message">';
		if ( !is_user_logged_in() ) {
			$message .= $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
		} else {
			$message .= $this->replace_variables( stripslashes( $settings['subscribe_upgrade_message'] ) );
		}
		$message .= '</div></div>';

		return $message;

	}

	/**
	 * Replace any variables in the content paywall barrier message with dyanmic values
	 *
	 * @since 4.10.3
	 *
	 * @param string $message
	 *
	 * @return string $message Message with dynamic values inserted
	 */
	public function replace_variables( $message ) {

		$settings = get_leaky_paywall_settings();

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
	 * Calculate expiration time for cookie
	 *
	 * @since 4.10.3
	 *
	 * @return string $expiration
	 */
	public function get_expiration_time()
	{

		$settings = get_leaky_paywall_settings();

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

		return apply_filters( 'leaky_paywall_expiration_time', $expiration );

	}

	/**
	 * Determine if the user is trying to cancel their subscription
	 *
	 * @since 4.10.3
	 *
	 * @return boolean
	 */
	public function is_cancel_request()
	{
		$settings = get_leaky_paywall_settings();

		if ( isset( $_REQUEST['cancel'] ) ) {

			if (
				( !empty( $settings['page_for_subscription'] ) && is_page( $settings['page_for_subscription'] ) )
				|| ( !empty( $settings['page_for_profile'] ) && is_page( $settings['page_for_profile'] )  )
			) {
				return true;
			} else {
				return false;
			}

		} else {
			return false;
		}

	}

	/**
	 * Send the user to the my account page if they user has paid and they try to access the login page
	 *
	 * @since 4.10.3
	 */
	public function redirect_from_login_page()
	{

		$settings = get_leaky_paywall_settings();

		if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {

			if ( !empty( $settings['page_for_profile'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_profile'] ) );
			} else if ( !empty( $settings['page_for_subscription'] ) ) {
				wp_safe_redirect( get_page_link( $settings['page_for_subscription'] ) );
			}

		}

	}

	/**
	 * Process the passwordless login functionality
	 *
	 * @since 4.10.3
	 */
	public function process_passwordless_login()
	{

		$settings = get_leaky_paywall_settings();

		if ( !empty( $settings['page_for_login'] ) && is_page( $settings['page_for_login'] ) ) {

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

		}

	}

	/**
	 * Determine if the current content is unblockable by the content paywall barrier
	 *
	 * @since 4.10.3
	 *
	 * @return boolean
	 */
	public function is_unblockable_content()
	{

		$settings = get_leaky_paywall_settings();

		$unblockable_content = array(
			$settings['page_for_login'],
			$settings['page_for_subscription'],
			$settings['page_for_profile'],
			$settings['page_for_register']
		);

		if ( is_page( apply_filters( 'leaky_paywall_unblockable_content', $unblockable_content ) ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Returns current user's subscription restrictions
	 *
	 * @since 2.0.0
	 *
	 * @return array subscriber's subscription restrictions
	 */
	public function get_subscriber_restrictions()
	{

		$settings = get_leaky_paywall_settings();

		if ( isset( $settings['restrictions']['post_types'] ) ) {
			$restrictions = $settings['restrictions']['post_types']; //defaults
		} else {
			$restrictions = '';
		}

		if ( is_multisite_premium() ) {
			$restriction_levels = leaky_paywall_subscriber_current_level_ids();
			if ( !empty( $restriction_levels ) ) {

				$restrictions = array();
				$merged_restrictions = array();
				foreach( $restriction_levels as $restriction_level ) {
					if ( !empty( $settings['levels'][$restriction_level]['post_types'] ) ) {
						$restrictions = array_merge( $restrictions, $settings['levels'][$restriction_level]['post_types'] );
					}
				}
				$merged_restrictions = array();
				foreach( $restrictions as $key => $restriction ) {
					if ( empty( $merged_restrictions ) ) {
						$merged_restrictions[$key] = $restriction;
						continue;
					} else {
						$post_type_found = false;
						foreach( $merged_restrictions as $tmp_key => $tmp_restriction ) {
							if ( $restriction['post_type'] === $tmp_restriction['post_type'] ) {
								$post_type_found = true;
								$post_type_found_key = $tmp_key;
								break;
							}
						}
						if ( !$post_type_found ) {
							$merged_restrictions[$key] = $restriction;
						} else {
							if ( -1 == $restriction['allowed_value'] ) { //-1 is unlimited, just use it
								$merged_restrictions[$post_type_found_key] = $restriction;
							} else if ( $merged_restrictions[$post_type_found_key]['allowed_value'] < $restriction['allowed_value'] ) {
								$merged_restrictions[$post_type_found_key] = $restriction;
							}
						}
					}
				}
				$restrictions = $merged_restrictions;

			}
		} else {
			if ( false !== $restriction_level = leaky_paywall_subscriber_current_level_id() ) {

				if ( !empty( $settings['levels'][$restriction_level]['post_types'] ) ) {
					$restrictions = $settings['levels'][$restriction_level]['post_types'];
				}

			}
		}

		return apply_filters( 'leaky_paywall_subscriber_restrictions', $restrictions );

	}

	public function process_js()
	{

		add_action( 'wp_ajax_nopriv_leaky_paywall_process_cookie', array( $this, 'process_cookie_requests' ) );
		add_action( 'wp_ajax_leaky_paywall_process_cookie', array( $this, 'process_cookie_requests' ) );

	}

	/**
	 * Process ajax requests for restricting content with javascript cookies
	 *
	 * @since 4.7.1
	 *
	 */
	public function process_cookie_requests()
	{

		$post_id = $_REQUEST['post_id'];
		$post_obj = get_post( $post_id );
		$current_post_type = $post_obj->post_type;
		$settings = get_leaky_paywall_settings();
		$restrictions = $this->get_subscriber_restrictions();


		if ( $this->is_unblockable_content() ) {
			echo json_ecode( 'do not show paywall' );
			exit();
		}

		if ( $this->visibility_allows_access( $post_obj ) ) {
			echo json_ecode( 'do not show paywall' );
			exit();
		}

		if ( $this->visibility_is_restricted( $post_obj ) ) {
			echo json_encode( $this->the_content_paywall_message() );
			exit();
		}

		// at this point we need to test against the global restriction settings

		$post_type_id = '';
		$restricted_post_type = '';
		$is_restricted = false;

		if ( !empty( $restrictions ) ) {

			foreach( $restrictions as $key => $restriction ) {

				if ( $restriction['post_type'] == $current_post_type ) {

					// this will only be ignored if the allowed value is unlimited ( -1 )
					if ( 0 <= $restriction['allowed_value'] ) {

						$post_type_id = $key;
						$restricted_post_type = $restriction['post_type'];
						$is_restricted = true;
						break;

					}

				}

			}

		}

		$is_restricted = apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $restrictions, $post );

		if ( !$is_restricted ) {
			echo json_ecode( 'do not show paywall' );
		}

		// content that can be accessed because the user has viewed it already
		$available_content = $this->get_available_content($restricted_post_type);

		if ( $this->combined_restrictions_enabled() ) {

			// maybe update available content array
			$available_content = $this->update_available_content_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id );

			if ( $this->is_restricted_combined( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id ) ) {
				echo json_encode( $this->the_content_paywall_message() );
			}

		} else {

			$available_content = $this->update_available_content_default( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id );
		
			if ( $this->is_restricted_default( $restrictions, $available_content, $post_type_id, $restricted_post_type, $post_id ) ) {

				echo json_encode( $this->the_content_paywall_message() );
			}

		}

		$this->set_available_content_cookie( $available_content );

		die();

	}

	/**
	 * Get the cookie name used for Leaky Paywall restrictions
	 *
	 * @since 4.10.10
	 *
	 * @return string
	 */
	public function get_cookie_name() 
	{
		$site = leaky_paywall_get_current_site();
		return apply_filters( 'leaky_paywall_restriction_cookie_name', $this->cookie_name . $site );
	}

}
