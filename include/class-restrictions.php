<?php
/**
 * Registers Leaky Paywall Restrictions class
 *
 * @package Leaky Paywall
 */

/**
 * Load the Restrictions Class
 */
class Leaky_Paywall_Restrictions {

	/**
	 * Name of the restriction cookie
	 *
	 * @var string
	 */
	public $cookie_name = 'issuem_lp';

	/**
	 * The post id
	 *
	 * @var integer
	 */
	private $post_id;

	/**
	 * Is this an ajax request
	 *
	 * @var bool
	 */
	public $is_ajax;

	/**
	 * Is this an REST request
	 *
	 * @var bool
	 */
	public $is_rest;

	/**
	 * Constructor
	 *
	 * @param integer $post_id The post id.
	 */
	public function __construct( $post_id = '' ) {
		$this->post_id = $post_id ? $post_id : get_the_ID();
		$this->is_ajax = false;
		$this->is_rest = false;

		add_action( 'wp_footer', array( $this, 'hide_initial_content_display' ) );
	}

	/**
	 * Process content restrictions
	 */
	public function process_content_restrictions() {
		do_action( 'leaky_paywall_before_process_requests', get_leaky_paywall_settings() );

		if ( ! $this->is_content_restricted() ) {
			return;
		}

		// content is restricted, so see if the current user can access it.
		if ( apply_filters( 'leaky_paywall_current_user_can_access', $this->current_user_can_access(), $this->post_id ) ) {
			return;
		}

		$this->display_subscribe_nag();

		do_action( 'leaky_paywall_is_restricted_content', $this->post_id );
	}

	/**
	 * Process WP REST API content restrictions
	 */
	public function process_rest_content_restrictions( $content ) {

		global $post;

		if ( !isset( $post->ID ) ) {
			return $content;
		}

		$this->post_id = $post->ID;
		$this->is_rest = true;

		if ( ! $this->is_content_restricted() ) {
			return $content;
		}

		// content is restricted, so see if the current user can access it.
		if ( apply_filters( 'leaky_paywall_current_user_can_access', $this->current_user_can_access(), $this->post_id ) ) {
			return $content;
		}

		return $this->get_subscribe_nag();
	}

	/**
	 * Process javascript content restrictions
	 */
	public function process_js_content_restrictions() {
		add_action( 'wp_ajax_nopriv_leaky_paywall_process_cookie', array( $this, 'check_js_restrictions' ) );
		add_action( 'wp_ajax_leaky_paywall_process_cookie', array( $this, 'check_js_restrictions' ) );
	}

	/**
	 * Check javascript restrictions
	 */
	public function check_js_restrictions() {
		$this->is_ajax = true;
		$this->post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : '';

		if ( ! $this->is_content_restricted() ) {
			echo json_encode( 'do not show paywall - 1' );
			exit();
		}

		// content is restricted, so see if the current user can access it.
		if ( apply_filters( 'leaky_paywall_current_user_can_access', $this->current_user_can_access(), $this->post_id ) ) {
			echo json_encode( 'do not show paywall - 2' );
			exit();
		}

		echo json_encode( $this->get_subscribe_nag() );
		do_action( 'leaky_paywall_is_restricted_content', $this->post_id );
		exit();
	}

	/**
	 * Helper method when restrictions need to be checked manually (like custom fields).
	 */
	public function subscriber_can_view() {
		if ( ! $this->is_content_restricted() ) {
			return true;
		}

		// content is restricted, so see if the current user can access it.
		if ( $this->current_user_can_access() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if content is restricted
	 */
	public function is_content_restricted() {
		$is_restricted = false;

		if ( $this->content_matches_restriction_rules() ) {
			$is_restricted = true;
		}

		return apply_filters( 'leaky_paywall_filter_is_restricted', $is_restricted, $this->get_restriction_settings(), $this->post_id );
	}

	/**
	 * Check if content matches restriction rules
	 */
	public function content_matches_restriction_rules() {
		$settings = get_leaky_paywall_settings();

		if ( ! $this->is_single() && ! $this->is_rest ) {
			return false;
		}

		if ( $this->user_role_can_bypass_paywall() ) {
			return false;
		}

		// allow access by capability for more fine grain control.
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) {
			return false;
		}

		// We don't ever want to block the login page, subscription page, etc.
		if ( $this->is_unblockable_content() ) {
			return false;
		}

		// check if this post matches any restriction exceptions.
		if ( $this->content_matches_restriction_exceptions() ) {
			return false;
		}

		// check if content is set to be open to everyone.
		if ( $this->visibility_allows_access() ) {
			return false;
		}

		if ( $this->visibility_restricts_access() ) {
			return true;
		}

		// check if content is restricted based on main restriction settings.
		if ( $this->content_restricted_by_settings() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current page is a single
	 */
	public function is_single() {
		$is_single = false;

		if ( is_single( $this->post_id ) ) {
			$is_single = true;
		}

		if ( is_page( $this->post_id ) ) {
			$is_single = true;
		}

		// for ajax.
		if ( $this->is_ajax ) {
			$is_single = true;
		}

		return $is_single;
	}

	/**
	 * Check if current user can access content
	 */
	public function current_user_can_access() {
		// get their level.
		$level_ids = leaky_paywall_subscriber_current_level_ids();

		// compare to the restrictions, and see if they can access the content.

		// user does not have a level id, so see if their allowed value lets them view the content.
		if ( empty( $level_ids ) ) {

			// if they do not have a level id, and the content is restricted by level, then they can't view it.
			if ( $this->visibility_restricts_access() ) {
				return false;
			}

			if ( $this->allowed_value_exceeded() ) {
				return false;
			} else {
				return true;
			}
		} else {

			if ( ! leaky_paywall_user_has_access() ) {
				return false;
			}

			if ( $this->visibility_restricts_access() ) {
				return false;
			}

			if ( $this->level_id_allows_access() ) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 * Check if level allows access
	 */
	public function level_id_allows_access() {
		$settings          = get_leaky_paywall_settings();
		$level_ids         = leaky_paywall_subscriber_current_level_ids();
		$restrictions      = $this->get_restriction_settings();
		$viewed_content    = $this->get_content_viewed_by_user();
		$content_post_type = get_post_type( $this->post_id );
		$allows_access     = false;

		foreach ( $level_ids as $level_id ) {

			$access_rules = $settings['levels'][ $level_id ]['post_types'];

			foreach ( $access_rules as $access_rule ) {

				foreach ( $restrictions['post_types'] as $restriction ) {

					if ( $access_rule['post_type'] != $restriction['post_type'] ) {
						continue;
					}

					// first, see if the content has already been viewed. if so, let them view it (keys are the post_id).
					if ( isset( $viewed_content[ $content_post_type ] ) && in_array( $this->post_id, array_keys( $viewed_content[ $content_post_type ] ) ) ) {
						return true;
					}

					if ( ! isset( $access_rule['taxonomy'] ) ) {
						$access_rule['taxonomy'] = 'all';
					}

					if ( ! isset( $restriction['taxonomy'] ) ) {
						$restriction['taxonomy'] = 'all';
					}

					if ( 'unlimited' == $access_rule['allowed'] && 'all' == $access_rule['taxonomy'] && $content_post_type == $access_rule['post_type'] ) {
						return true;
					}

					if ( 'unlimited' == $access_rule['allowed'] && $access_rule['taxonomy'] == $restriction['taxonomy'] && $content_post_type == $access_rule['post_type'] && $this->content_taxonomy_matches( $access_rule['taxonomy'] ) ) {
						return true;
					}

					// they have access to some taxonomy, but not this one.
					if ( 'unlimited' == $access_rule['allowed'] && $access_rule['taxonomy'] != $restriction['taxonomy'] && $content_post_type == $restriction['post_type'] && $this->content_taxonomy_matches( $restriction['taxonomy'] ) ) {

						if ( $this->allowed_value_exceeded() ) {

							// if it has already been set to true by a previous rule, do not unset it
							if ( !$allows_access ) {
								$allows_access = false;
							}

						} else {
							$allows_access = true;
						}
					}

					if ( 'unlimited' == $access_rule['allowed'] && 'all' == $restriction['taxonomy'] && $content_post_type == $restriction['post_type'] && $this->content_taxonomy_matches( $access_rule['taxonomy'] ) ) {
						return true; // the subscriber should have access to this content
					}

					if ( 'unlimited' == $access_rule['allowed'] && 'all' == $restriction['taxonomy'] && $content_post_type == $restriction['post_type'] && ! $this->content_taxonomy_matches( $access_rule['taxonomy'] ) ) {

						if ( $this->allowed_value_exceeded() ) {
							$allows_access = false;
						} else {
							$allows_access = true;
						}
					}


					if ('limited' == $access_rule['allowed'] && 'all' != $access_rule['taxonomy'] && $content_post_type == $access_rule['post_type']&& $this->content_taxonomy_matches($access_rule['taxonomy']) ) {

						$number_already_viewed = isset($viewed_content[$content_post_type]) ? $this->get_number_viewed_by_term($access_rule['taxonomy']) : 0;

						// max views reached so block the content.
						if (!empty($viewed_content) && $number_already_viewed >= $access_rule['allowed_value']) {
							$allows_access = false;
						} else {
							$this->update_content_viewed_by_user();
							$allows_access = true;
						}
					}



					if ( 'limited' == $access_rule['allowed'] && 'all' == $access_rule['taxonomy'] && $content_post_type == $access_rule['post_type'] ) {

						$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $restriction['taxonomy'] ) : 0;

						// max views reached so block the content.
						if ( ! empty( $viewed_content ) && $number_already_viewed >= $access_rule['allowed_value'] ) {
							$allows_access = false;
						} else {
							$this->update_content_viewed_by_user();
							$allows_access = true;
						}
					}

					if ( 'limited' == $access_rule['allowed'] && $access_rule['taxonomy'] == $restriction['taxonomy'] && $content_post_type == $access_rule['post_type'] && $this->content_taxonomy_matches( $restriction['taxonomy'] ) ) {

						// this only needs to calculate for this term.
						$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $restriction['taxonomy'] ) : 0;

						// max views reached so block the content.
						if ( ! empty( $viewed_content ) && $number_already_viewed >= $access_rule['allowed_value'] ) {
							$allows_access = false;
						} else {
							$this->update_content_viewed_by_user();
							$allows_access = true;
						}
					}

					if ('limited' == $access_rule['allowed'] && $access_rule['taxonomy'] != $restriction['taxonomy'] && $content_post_type == $access_rule['post_type']) {
						continue;
					}
				}
			}
		}

		return $allows_access;
	}

	/**
	 * Check if allowed value has been exceeded
	 */
	public function allowed_value_exceeded() {
		$settings = get_leaky_paywall_settings();

		// get viewed content.
		$viewed_content    = $this->get_content_viewed_by_user();
		$restrictions      = $this->get_restriction_settings();
		$content_post_type = get_post_type( $this->post_id );

		foreach ( $restrictions['post_types'] as $restriction ) {

			if ( ! isset( $restriction['taxonomy'] ) ) {
				$restriction['taxonomy'] = 'all';
			}

			if ( $restriction['post_type'] == $content_post_type && 'all' == $restriction['taxonomy'] ) {

				if ( 'on' === $settings['enable_combined_restrictions'] ) {
					$allowed_value         = $settings['combined_restrictions_total_allowed'];
					$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_total_content_viewed() : 0;
				} else {
					$allowed_value         = $restriction['allowed_value'];
					$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? count( $viewed_content[ $content_post_type ] ) : 0;
				}

				// If the content has already been viewed, then let them view it (keys are the post_id).
				if ( isset( $viewed_content[ $content_post_type ] ) && in_array( $this->post_id, array_keys( $viewed_content[ $content_post_type ] ) ) ) {
					return false;
				}

				if ( 0 == $allowed_value ) {
					return true;
				} elseif ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
					// max views reached so block the content.
					return true;
				} else {
					$this->update_content_viewed_by_user();
					return false;
				}
			}

			if ( $restriction['post_type'] == $content_post_type && $this->content_taxonomy_matches( $restriction['taxonomy'] ) ) {

				// this only needs to calculate for this term (unless combined).
				if ( 'on' === $settings['enable_combined_restrictions'] ) {
					$allowed_value         = $settings['combined_restrictions_total_allowed'];
					$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_total_content_viewed() : 0;
				} else {
					$allowed_value         = $restriction['allowed_value'];
					$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $restriction['taxonomy'] ) : 0;
				}

				// first, see if the content has already been viewed. if so, let them view it (keys are the post_id).
				if ( isset( $viewed_content[ $content_post_type ] ) && in_array( $this->post_id, array_keys( $viewed_content[ $content_post_type ] ) ) ) {
					return false;
				}

				// max views reached so block the content.
				if ( 0 == $allowed_value ) {
					return true;
				} elseif ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
					return true;
				} else {
					$this->update_content_viewed_by_user();
					return false;
				}
			}

			if ( $restriction['post_type'] == $content_post_type && 'on' == $settings['enable_combined_restrictions'] ) {

				$allowed_value = $settings['combined_restrictions_total_allowed'];

				// first, see if the content has already been viewed. if so, let them view it (keys are the post_id).
				if ( isset( $viewed_content[ $content_post_type ] ) && in_array( $this->post_id, array_keys( $viewed_content[ $content_post_type ] ) ) ) {
					return false;
				}

				// calculate for all content since its combined restrictions.
				$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_total_content_viewed() : 0;

				// max views reached so block the content.
				if ( 0 == $allowed_value ) {
					return true;
				} elseif ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
					return true;
				} else {
					$this->update_content_viewed_by_user();
					return false;
				}
			}
		}
	}

	/**
	 * Go through each content item viewed and see if its term matches any restrictions.
	 *
	 * @param integer $term_id The term id.
	 */
	public function get_number_viewed_by_term( $term_id ) {

		$viewed_content = $this->get_content_viewed_by_user();
		$num            = 0;

		foreach ( $viewed_content as $post_type => $items ) {

			foreach ( $items as $post_id => $item ) {

				// if all, then count every one.
				// @todo had to add this condition to account for the "all" term.
				if ( 'all' === $term_id ) {
					$num++;
				} elseif ( $this->content_taxonomy_matches( $term_id, $post_id ) ) {
					$num++;
				}
			}
		}

		return $num;
	}

	/**
	 * Calculate all content items that have been viewed the current user
	 *
	 * @since 4.10.3
	 *
	 * @return string $total_viewed Number of content items viewed
	 */
	public function get_total_content_viewed() {
		$viewed_content = $this->get_content_viewed_by_user();
		$total_viewed   = 0;

		foreach ( $viewed_content as $content ) {

			$total_viewed += count( $content );
		}

		return $total_viewed;
	}

	/**
	 * Display he paywall subscribe nag
	 */
	public function display_subscribe_nag() {
		add_filter('the_content', array($this, 'get_subscribe_nag'), 999);
	}

	/**
	 * The paywall subscribe nag
	 *
	 * @param string $content The content of the post.
	 */
	public function get_subscribe_nag( $content = '' ) {

		if ( ! $content ) {
			$content = get_the_content( $this->post_id );
		}

		$message     = $this->the_content_paywall_message();
		$new_content = $this->get_nag_excerpt( $content ) . $message;

		return apply_filters( 'leaky_paywall_subscribe_or_login_message', $new_content, $message, $content, $this->post_id );
	}

	/**
	 * The paywall nag excerpt
	 *
	 * @param string $content The content of the post.
	 */
	public function get_nag_excerpt( $content ) {
		$settings = get_leaky_paywall_settings();

		if ( isset( $settings['custom_excerpt_length'] ) && strlen( $settings['custom_excerpt_length'] ) > 0 ) {
			$excerpt = substr( wp_strip_all_tags( get_the_content( get_the_ID() ) ), 0, intval( $settings['custom_excerpt_length'] ) );
		} else {
			$excerpt = substr( wp_strip_all_tags( $content ), 0, 100 );
		}

		return apply_filters( 'leaky_paywall_nag_excerpt', strip_shortcodes( $excerpt ), $this->post_id );
	}

	/**
	 * The paywall message to display in content
	 */
	public function the_content_paywall_message() {
		$settings = get_leaky_paywall_settings();
		$text     = '';

		$show_upgrade_message = get_post_meta( $this->post_id, '_issuem_leaky_paywall_show_upgrade_message', true );

		$message = '<div class="leaky_paywall_message_wrap"><div id="leaky_paywall_message">';

		if ( ! is_user_logged_in() && 'on' != $show_upgrade_message ) {
			$text .= $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
		} else {
			$text .= $this->replace_variables( stripslashes( $settings['subscribe_upgrade_message'] ) );
		}

		$message .= apply_filters( 'leaky_paywall_nag_message_text', $text, $this->post_id );
		$message .= '</div></div>';

		return do_shortcode( $message );
	}

	/**
	 * Replace any variables in the content paywall barrier message with dyanmic values
	 *
	 * @since 4.10.3
	 *
	 * @param string $message The message.
	 *
	 * @return string $message Message with dynamic values inserted
	 */
	public function replace_variables( $message ) {

		$settings = get_leaky_paywall_settings();

		if ( 0 === $settings['page_for_subscription'] ) {
			$subscription_url = get_bloginfo( 'wpurl' ) . '/?subscription'; // CHANGEME -- I don't really know what this is suppose to do...
		} else {
			$subscription_url = get_page_link( $settings['page_for_subscription'] );
		}

		if ( 0 === $settings['page_for_profile'] ) {
			$my_account_url = get_bloginfo( 'wpurl' ) . '/?my-account'; // CHANGEME -- I don't really know what this is suppose to do...
		} else {
			$my_account_url = get_page_link( $settings['page_for_profile'] );
		}

		$message = str_ireplace( '{{SUBSCRIBE_LOGIN_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{SUBSCRIBE_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{MY_ACCOUNT_URL}}', $my_account_url, $message );

		if ( 0 === $settings['page_for_login'] ) {
			$login_url = get_bloginfo( 'wpurl' ) . '/?login'; // CHANGEME -- I don't really know what this is suppose to do...
		} else {
			$login_url = get_page_link( $settings['page_for_login'] );
		}

		$message = str_ireplace( '{{LOGIN_URL}}', $login_url, $message );

		// Deprecated.
		if ( ! empty( $settings['price'] ) ) {
			$message = str_ireplace( '{{PRICE}}', $settings['price'], $message );
		}
		if ( ! empty( $settings['interval_count'] ) && ! empty( $settings['interval'] ) ) {
			$message = str_ireplace( '{{LENGTH}}', leaky_paywall_human_readable_interval( $settings['interval_count'], $settings['interval'] ), $message );
		}

		return $message;
	}

	/**
	 * Determine if the current user has a role that can bypass the paywall
	 *
	 * @since 4.13.9
	 *
	 * @return boolean
	 */
	public function user_role_can_bypass_paywall() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		if ( leaky_paywall_user_can_bypass_paywall_by_role( $user ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if the current content is unblockable by the content paywall barrier
	 *
	 * @since 4.10.3
	 *
	 * @return boolean
	 */
	public function is_unblockable_content() {
		$settings = get_leaky_paywall_settings();

		$unblockable_content = array(
			$settings['page_for_login'],
			$settings['page_for_subscription'],
			$settings['page_for_profile'],
			$settings['page_for_register'],
		);

		if ( in_array( $this->post_id, apply_filters( 'leaky_paywall_unblockable_content', $unblockable_content ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if the current content matches any restriction exception settings
	 *
	 * @since 4.15.3
	 *
	 * @return boolean
	 */
	public function content_matches_restriction_exceptions() {
		$match    = false;
		$settings = get_leaky_paywall_settings();

		$category_exceptions    = $settings['post_category_exceptions'];
		$category_exception_ids = explode( ',', $category_exceptions );

		if ( ! empty( $category_exception_ids ) ) {
			$post_categories = get_the_category( $this->post_id );

			foreach ( $post_categories as $cat ) {
				if ( in_array( $cat->term_id, $category_exception_ids ) ) {
					$match = true;
				}
			}
		}

		$tag_exceptions    = $settings['post_tag_exceptions'];
		$tag_exception_ids = explode( ',', $tag_exceptions );

		if ( ! empty( $tag_exception_ids ) ) {
			$post_tag = get_the_tags( $this->post_id );

			if ( is_array( $post_tag ) ) {
				foreach ( $post_tag as $tag ) {
					if ( in_array( $tag->term_id, $tag_exception_ids ) ) {
						$match = true;
					}
				}
			}
		}

		return $match;
	}

	/**
	 * Determine if the current content can be viewed based on the Leaky Paywall visibility settings on the content item
	 *
	 * @since 4.10.3
	 *
	 * @return boolean
	 */
	public function visibility_allows_access() {
		$visibility = get_post_meta( $this->post_id, '_issuem_leaky_paywall_visibility', true );
		$level_ids  = leaky_paywall_subscriber_current_level_ids();

		if ( false !== $visibility && ! empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {

			switch ( $visibility['visibility_type'] ) {

				case 'only':
					$only = array_intersect( $level_ids, $visibility['only_visible'] );
					if ( empty( $only ) ) {
						return false;
					}
					break;

				case 'always':
					$always = array_intersect( $level_ids, $visibility['always_visible'] );

					if ( in_array( -1, $visibility['always_visible'] ) ) { // -1 = Everyone.
						return true; // always visible, don't need process anymore.
					}

					// level id of the user matches those selected in the settings, and the user currently has access to that level.
					if ( ! empty( $always ) && leaky_paywall_user_has_access() ) {
						return true;
					}
					break;

				case 'onlyalways':
					$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
					if ( empty( $onlyalways ) ) {
						return false;
					} elseif ( ! empty( $onlyalways ) && leaky_paywall_user_has_access() ) {
						return true; // always visible, don't need process anymore.
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Check if the Leaky Paywall visibility settings for this post restrict its access to the current user
	 *
	 * @since 4.10.3
	 *
	 * @return bool $is_restricted
	 */
	public function visibility_restricts_access() {
		$visibility    = get_post_meta( $this->post_id, '_issuem_leaky_paywall_visibility', true );
		$level_ids     = leaky_paywall_subscriber_current_level_ids();
		$is_restricted = false;

		if ( false !== $visibility && ! empty( $visibility['visibility_type'] ) && 'default' !== $visibility['visibility_type'] ) {

			if ( 'only' === $visibility['visibility_type'] ) {
				$only = array_intersect( $level_ids, $visibility['only_visible'] );
				if ( empty( $only ) ) {
					$is_restricted = true;
				}
			}
		}

		return $is_restricted;
	}

	/**
	 * Check if the Leaky Paywall visibility settings for this post restrict its access to the current user
	 *
	 * @since 4.10.3
	 *
	 * @return bool $is_restricted
	 */
	public function content_restricted_by_settings() {
		$restrictions = $this->get_restriction_settings();

		if ( empty( $restrictions ) ) {
			return false;
		}

		$content_post_type = get_post_type( $this->post_id );

		foreach ( $restrictions['post_types'] as $key => $restriction ) {

			if ( ! is_numeric( $key ) ) {
				continue;
			}

			$restriction_taxomony = isset( $restriction['taxonomy'] ) ? $restriction['taxonomy'] : 'all';

			if ( $restriction['post_type'] === $content_post_type && 'all' === $restriction_taxomony ) {
				return true;
			}

			if ( $restriction['post_type'] === $content_post_type && $this->content_taxonomy_matches( $restriction_taxomony ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the user has pdf access
	 *
	 * @since 4.10.3
	 */
	public function pdf_access() {
		$settings       = get_leaky_paywall_settings();
		$has_pdf_access = apply_filters( 'leaky_paywall_pdf_access', leaky_paywall_user_has_access() );

		// Admins or subscribed users can download PDFs.
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) || $has_pdf_access ) {

			if ( isset( $_REQUEST['issuem-pdf-download'] ) ) {
				leaky_paywall_server_pdf_download( sanitize_text_field( wp_unslash( $_REQUEST['issuem-pdf-download'] ) ) );
			}
		} else {

			$output = '<h3>' . __( 'Unauthorized PDF Download', 'leaky-paywall' ) . '</h3>';
			/* translators: %1$ - Login url, %2$s - page for subscription */
			$output .= '<p>' . sprintf( __( 'You must be logged in with a valid subscription to download Issue PDFs. Please <a href="%1$s">log in</a> or <a href="%2$s">subscribe</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_login'] ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
			/* Translators: %s: site name. */
			$output .= '<a href="' . get_home_url() . '">' . sprintf( __( 'back to %s', 'leaky-paywall' ), $settings['site_name'] ) . '</a>';

			wp_die( wp_kses_post( apply_filters( 'leaky_paywall_unauthorized_pdf_download_output', $output ) ), esc_html( $settings['site_name'] ) . ' - Error' );
		}
	}

	/**
	 * Get restriction settings
	 */
	public function get_restriction_settings() {
		$settings = get_leaky_paywall_settings();
		return $settings['restrictions'];
	}

	/**
	 * Find taxonomy match
	 *
	 * @param integer $restricted_term_id The term id.
	 * @param integer $post_id The post id.
	 */
	public function content_taxonomy_matches( $restricted_term_id, $post_id = '' ) {

		if ( ! $post_id ) {
			$post_id = $this->post_id;
		}

		// get current post taxonomies.
		$taxonomies = get_post_taxonomies( $post_id );

		foreach ( $taxonomies as $taxonomy ) {
			// get all terms for current post.
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( $terms ) {
				foreach ( $terms as $term ) {
					// see if one of the term_ids matches the restricted_term_id.
					if ( $term->term_id == $restricted_term_id ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get the content that the user has already viewed
	 *
	 * @since 4.10.3
	 *
	 * @return array $available_content Array of post ids that have been viewed
	 */
	public function get_content_viewed_by_user() {
		if ( ! empty( $_COOKIE[ $this->get_cookie_name() ] ) ) {
			$content_viewed = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ $this->get_cookie_name() ] ) ), true );
		} else {
			$content_viewed = array();
		}

		return apply_filters( 'leaky_paywall_available_content', $content_viewed );
	}

	/**
	 * Update content viewed
	 */
	public function update_content_viewed_by_user() {
		$viewed_content       = $this->get_content_viewed_by_user();
		$restricted_post_type = get_post_type( $this->post_id );
		$viewed_content[ $restricted_post_type ][ $this->post_id ] = $this->get_expiration_time();
		$json_viewed_content                                       = json_encode( $viewed_content );

		$cookie                              = setcookie( $this->get_cookie_name(), $json_viewed_content, $this->get_expiration_time(), '/' );
		$_COOKIE[ $this->get_cookie_name() ] = $json_viewed_content;
	}

	/**
	 * Clear cookie
	 */
	public function clear_cookie() {
		setcookie( $this->get_cookie_name(), '', $this->get_expiration_time(), '/' );
	}

	/**
	 * Get the cookie name used for Leaky Paywall restrictions
	 *
	 * @since 4.10.10
	 *
	 * @return string
	 */
	public function get_cookie_name() {
		$site = leaky_paywall_get_current_site();
		return apply_filters( 'leaky_paywall_restriction_cookie_name', $this->cookie_name . $site );
	}

	/**
	 * Calculate expiration time for cookie
	 *
	 * @since 4.10.3
	 *
	 * @return string $expiration
	 */
	public function get_expiration_time() {
		$settings = get_leaky_paywall_settings();

		switch ( $settings['cookie_expiration_interval'] ) {
			case 'hour':
				$multiplier = 60 * 60; // seconds in an hour.
				break;
			case 'day':
				$multiplier = 60 * 60 * 24; // seconds in a day.
				break;
			case 'week':
				$multiplier = 60 * 60 * 24 * 7; // seconds in a week.
				break;
			case 'month':
				$multiplier = 60 * 60 * 24 * 7 * 4; // seconds in a month (4 weeks).
				break;
			case 'year':
				$multiplier = 60 * 60 * 24 * 7 * 52; // seconds in a year (52 weeks).
				break;
		}

		$expiration = time() + ( $settings['cookie_expiration'] * $multiplier );

		return apply_filters( 'leaky_paywall_expiration_time', $expiration );
	}

	/**
	 * Hide initial content display for Alternative Restriction Handling
	 */
	public function hide_initial_content_display() {
		$settings = get_leaky_paywall_settings();

		if ( 'on' === $settings['enable_js_cookie_restrictions'] ) {
			$container_setting = $settings['js_restrictions_post_container'];
			$containers        = explode( ',', $container_setting );

			echo '<style>';
			foreach ( $containers as $container ) {
				?>
				.single <?php echo esc_attr( $container ); ?> {
				display: none;
				}
				<?php
			}

			echo '</style>';
		}
	}
}
