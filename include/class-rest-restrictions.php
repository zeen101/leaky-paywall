<?php
/**
 * Leaky Paywall REST API Restrictions
 *
 * Optimized REST endpoint for checking content restrictions.
 * Replaces the slower admin-ajax.php approach with a lighter REST API endpoint.
 *
 * @package Leaky Paywall
 * @since 4.23.0
 */

/**
 * REST API Restrictions Class
 */
class Leaky_Paywall_REST_Restrictions {

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'leaky-paywall/v1';

	/**
	 * REST API route
	 *
	 * @var string
	 */
	const ROUTE = '/check-restrictions';

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
	 * Cached settings
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Cached level ids
	 *
	 * @var array|null
	 */
	private $level_ids = null;

	/**
	 * Cached post type
	 *
	 * @var string|null
	 */
	private $content_post_type = null;

	/**
	 * Cached visibility meta
	 *
	 * @var array|null
	 */
	private $visibility = null;

	/**
	 * Cached viewed content (passed from client)
	 *
	 * @var array|null
	 */
	private $viewed_content_override = null;

	/**
	 * Cached taxonomy match results
	 *
	 * @var array
	 */
	private $taxonomy_match_cache = [];

	/**
	 * Cached restrictions settings
	 *
	 * @var array|null
	 */
	private $restrictions = null;

	/**
	 * The resolved nag type for impression tracking.
	 *
	 * @var string
	 */
	private $nag_type = 'subscribe';

	/**
	 * Initialize the REST API endpoint
	 */
	public static function init() {
		$instance = new self();
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_restrictions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'        => array(
						'required'          => true,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'viewed_content' => array(
						'required'          => false,
						'default'           => array(),
						'sanitize_callback' => array( $this, 'sanitize_viewed_content' ),
					),
				),
			)
		);
	}

	/**
	 * Sanitize viewed content parameter
	 *
	 * @param mixed $param The parameter value.
	 * @return array Sanitized viewed content array.
	 */
	public function sanitize_viewed_content( $param ) {
		if ( empty( $param ) ) {
			return array();
		}

		// If passed as JSON string, decode it.
		if ( is_string( $param ) ) {
			$param = json_decode( $param, true );
		}

		if ( ! is_array( $param ) ) {
			return array();
		}

		// Sanitize: only allow expected structure.
		$sanitized = array();
		foreach ( $param as $post_type => $posts ) {
			if ( ! is_string( $post_type ) || ! is_array( $posts ) ) {
				continue;
			}
			$clean_post_type = sanitize_key( $post_type );
			$sanitized[ $clean_post_type ] = array();

			foreach ( $posts as $pid => $expiration ) {
				$sanitized[ $clean_post_type ][ absint( $pid ) ] = absint( $expiration );
			}
		}

		return $sanitized;
	}

	/**
	 * REST API callback to check restrictions
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function check_restrictions( $request ) {
		$this->post_id                 = $request->get_param( 'post_id' );
		$this->viewed_content_override = $request->get_param( 'viewed_content' );

		// Verify post exists.
		if ( ! get_post( $this->post_id ) ) {
			return new WP_REST_Response(
				array(
					'show_paywall' => false,
					'error'        => 'Invalid post ID',
				),
				400
			);
		}

		if ( ! $this->is_content_restricted() ) {
			return new WP_REST_Response(
				array(
					'show_paywall'   => false,
					'viewed_content' => $this->get_content_viewed_by_user(),
				),
				200
			);
		}

		// Content is restricted, check if current user can access.
		$can_access = $this->current_user_can_access();

		if ( apply_filters( 'leaky_paywall_current_user_can_access', $can_access, $this->post_id ) ) {
			return new WP_REST_Response(
				array(
					'show_paywall'   => false,
					'viewed_content' => $this->get_content_viewed_by_user(),
				),
				200
			);
		}

		$response_data = array(
			'show_paywall'          => true,
			'nag_content'           => $this->get_subscribe_nag(),
			'viewed_content'        => $this->get_content_viewed_by_user(),
			'subscriber_level_ids'  => $this->get_level_ids(),
			'nag_type'              => $this->nag_type,
		);

		do_action( 'leaky_paywall_is_restricted_content', $this->post_id, $this->nag_type );

		// Record the nag impression.
		LP_Nag_Impressions::record( $this->post_id, $this->nag_type );

		/**
		 * Filter the REST API response when the paywall is shown.
		 *
		 * Allows other plugins to add custom data to the response.
		 *
		 * @since 4.23.0
		 *
		 * @param array $response_data The response data.
		 * @param int   $post_id       The post ID being restricted.
		 * @param Leaky_Paywall_REST_Restrictions $instance The restrictions instance.
		 */
		$response_data = apply_filters( 'leaky_paywall_rest_paywall_response', $response_data, $this->post_id, $this );

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get Leaky Paywall settings with caching
	 *
	 * @return array
	 */
	private function get_settings() {
		if ( null === $this->settings ) {
			$this->settings = get_leaky_paywall_settings();
		}
		return $this->settings;
	}

	/**
	 * Get current user's level IDs with caching
	 *
	 * @return array
	 */
	private function get_level_ids() {
		if ( null === $this->level_ids ) {
			$this->level_ids = leaky_paywall_subscriber_current_level_ids();
		}
		return $this->level_ids;
	}

	/**
	 * Get post type with caching
	 *
	 * @return string
	 */
	private function get_content_post_type() {
		if ( null === $this->content_post_type ) {
			$this->content_post_type = get_post_type( $this->post_id );
		}
		return $this->content_post_type;
	}

	/**
	 * Get visibility meta with caching
	 *
	 * @return array|false
	 */
	private function get_visibility() {
		if ( null === $this->visibility ) {
			$this->visibility = get_post_meta( $this->post_id, '_issuem_leaky_paywall_visibility', true );
		}
		return $this->visibility;
	}

	/**
	 * Get restriction settings with caching
	 *
	 * @return array
	 */
	private function get_restriction_settings() {
		if ( null === $this->restrictions ) {
			$settings           = $this->get_settings();
			$this->restrictions = $settings['restrictions'];
		}
		return $this->restrictions;
	}

	/**
	 * Check if content is restricted
	 *
	 * @return bool
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
	 *
	 * @return bool
	 */
	public function content_matches_restriction_rules() {
		if ( $this->user_role_can_bypass_paywall() ) {
			return false;
		}

		// Allow access by capability for more fine grain control.
		if ( current_user_can( apply_filters( 'leaky_paywall_current_user_can_view_all_content', 'manage_options' ) ) ) {
			return false;
		}

		// We don't ever want to block the login page, subscription page, etc.
		if ( $this->is_unblockable_content() ) {
			return false;
		}

		// Check if this post matches any restriction exceptions.
		if ( $this->content_matches_restriction_exceptions() ) {
			return false;
		}

		// Check if content is set to be open to everyone.
		if ( $this->visibility_allows_access() ) {
			return false;
		}

		if ( $this->visibility_restricts_access() ) {
			return true;
		}

		// Check if content is restricted based on main restriction settings.
		if ( $this->content_restricted_by_settings() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current user can access content
	 *
	 * @return bool
	 */
	public function current_user_can_access() {
		$level_ids = $this->get_level_ids();

		// User does not have a level id.
		if ( empty( $level_ids ) ) {
			if ( $this->visibility_restricts_access() ) {
				return false;
			}

			return ! $this->allowed_value_exceeded();
		}

		// User has level id(s).
		if ( ! leaky_paywall_user_has_access() ) {
			return false;
		}

		if ( $this->visibility_restricts_access() ) {
			return false;
		}

		return $this->level_id_allows_access();
	}

	/**
	 * Check if level allows access
	 *
	 * @return bool
	 */
	public function level_id_allows_access() {
		$settings          = $this->get_settings();
		$level_ids         = $this->get_level_ids();
		$restrictions      = $this->get_restriction_settings();
		$viewed_content    = $this->get_content_viewed_by_user();
		$content_post_type = $this->get_content_post_type();
		$allows_access     = false;

		// Pre-index restrictions by post type for faster lookup.
		$restrictions_by_type = array();
		foreach ( $restrictions['post_types'] as $restriction ) {
			$type = $restriction['post_type'];
			if ( ! isset( $restrictions_by_type[ $type ] ) ) {
				$restrictions_by_type[ $type ] = array();
			}
			$restrictions_by_type[ $type ][] = $restriction;
		}

		// Skip if no restrictions for this post type.
		if ( ! isset( $restrictions_by_type[ $content_post_type ] ) ) {
			return true;
		}

		foreach ( $level_ids as $level_id ) {
			if ( ! isset( $settings['levels'][ $level_id ]['post_types'] ) ) {
				continue;
			}

			$access_rules = $settings['levels'][ $level_id ]['post_types'];

			// Pre-scan: does a taxonomy-specific rule on this level block this content?
			$taxonomy_blocked = $this->has_taxonomy_specific_block( $access_rules, $content_post_type, $viewed_content );

			foreach ( $access_rules as $access_rule ) {
				// Skip if access rule doesn't match this post type.
				if ( $access_rule['post_type'] !== $content_post_type ) {
					continue;
				}

				foreach ( $restrictions_by_type[ $content_post_type ] as $restriction ) {
					// First, see if the content has already been viewed.
					if ( isset( $viewed_content[ $content_post_type ] ) && array_key_exists( $this->post_id, $viewed_content[ $content_post_type ] ) ) {
						return true;
					}

					$access_taxonomy      = isset( $access_rule['taxonomy'] ) ? $access_rule['taxonomy'] : 'all';
					$restriction_taxonomy = isset( $restriction['taxonomy'] ) ? $restriction['taxonomy'] : 'all';

					// Unlimited access to all content of this post type.
					if ( 'unlimited' === $access_rule['allowed'] && 'all' === $access_taxonomy ) {
						if ( ! $taxonomy_blocked ) {
							return true;
						}
						// A specific taxonomy rule blocks this content — skip this catch-all.
						break;
					}

					// Unlimited access to specific taxonomy.
					if ( 'unlimited' === $access_rule['allowed'] && $access_taxonomy === $restriction_taxonomy && $this->content_taxonomy_matches( $access_taxonomy ) ) {
						return true;
					}

					// Unlimited access to a different taxonomy than restricted.
					if ( 'unlimited' === $access_rule['allowed'] && $access_taxonomy !== $restriction_taxonomy && $this->content_taxonomy_matches( $restriction_taxonomy ) ) {
						if ( $this->allowed_value_exceeded() ) {
							if ( ! $allows_access ) {
								$allows_access = false;
							}
						} else {
							$allows_access = true;
						}
					}

					// Unlimited access to specific taxonomy when restriction is "all".
					if ( 'unlimited' === $access_rule['allowed'] && 'all' === $restriction_taxonomy && $this->content_taxonomy_matches( $access_taxonomy ) ) {
						return true;
					}

					if ( 'unlimited' === $access_rule['allowed'] && 'all' === $restriction_taxonomy && ! $this->content_taxonomy_matches( $access_taxonomy ) ) {
						if ( $this->allowed_value_exceeded() ) {
							$allows_access = false;
						} else {
							$allows_access = true;
						}
					}

					// Limited access to specific taxonomy.
					if ( 'limited' === $access_rule['allowed'] && 'all' !== $access_taxonomy && $this->content_taxonomy_matches( $access_taxonomy ) ) {
						$allowed_value = intval( $access_rule['allowed_value'] );
						$allowed_value = apply_filters( 'leaky_paywall_meter_allowed_value', $allowed_value, $level_id, $access_rule, $this->post_id );

						// 0 means no access to this taxonomy — block immediately.
						if ( $allowed_value <= 0 ) {
							$allows_access = false;
						} else {
							$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $access_taxonomy ) : 0;

							if ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
								$allows_access = false;
							} else {
								$this->update_content_viewed_by_user();
								$allows_access = true;
							}
						}
					}

					// Limited access to all content of this post type.
					if ( 'limited' === $access_rule['allowed'] && 'all' === $access_taxonomy ) {
						$allowed_value = apply_filters( 'leaky_paywall_meter_allowed_value', intval( $access_rule['allowed_value'] ), $level_id, $access_rule, $this->post_id );
						$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $restriction_taxonomy ) : 0;

						if ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
							$allows_access = false;
						} else {
							$this->update_content_viewed_by_user();
							$allows_access = true;
						}
					}

					// Limited access matching specific taxonomy.
					if ( 'limited' === $access_rule['allowed'] && $access_taxonomy === $restriction_taxonomy && $this->content_taxonomy_matches( $restriction_taxonomy ) ) {
						$allowed_value = apply_filters( 'leaky_paywall_meter_allowed_value', intval( $access_rule['allowed_value'] ), $level_id, $access_rule, $this->post_id );
						$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_number_viewed_by_term( $restriction_taxonomy ) : 0;

						if ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
							$allows_access = false;
						} else {
							$this->update_content_viewed_by_user();
							$allows_access = true;
						}
					}
				}
			}
		}

		return $allows_access;
	}

	/**
	 * Check if any taxonomy-specific access rule on a level blocks the current post.
	 *
	 * Pre-scans all access rules for a given level to find "limited" rules targeting
	 * a specific taxonomy. If the current post belongs to that taxonomy and the limit
	 * has been reached (or is 0), the post is blocked — even if a catch-all
	 * "unlimited + all" rule also exists on the same level.
	 *
	 * @since 4.23.0
	 *
	 * @param array  $access_rules     The level's post_types access rules.
	 * @param string $content_post_type The current post's post type.
	 * @param array  $viewed_content    Content already viewed by the user.
	 * @return bool True if a taxonomy-specific rule blocks access.
	 */
	private function has_taxonomy_specific_block( $access_rules, $content_post_type, $viewed_content ) {

		foreach ( $access_rules as $rule ) {

			if ( $rule['post_type'] !== $content_post_type ) {
				continue;
			}

			$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : 'all';

			if ( 'all' === $taxonomy || 'limited' !== $rule['allowed'] ) {
				continue;
			}

			if ( ! $this->content_taxonomy_matches( $taxonomy ) ) {
				continue;
			}

			// Post is in this specifically-limited taxonomy.
			$allowed_value = intval( $rule['allowed_value'] );

			// 0 means complete block — no access regardless of view history.
			if ( $allowed_value <= 0 ) {
				return true;
			}

			// Check if view limit exceeded.
			$number_viewed = isset( $viewed_content[ $content_post_type ] )
				? $this->get_number_viewed_by_term( $taxonomy )
				: 0;

			if ( $number_viewed >= $allowed_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if allowed value has been exceeded
	 *
	 * @return bool
	 */
	public function allowed_value_exceeded() {
		$settings          = $this->get_settings();
		$viewed_content    = $this->get_content_viewed_by_user();
		$restrictions      = $this->get_restriction_settings();
		$content_post_type = $this->get_content_post_type();

		foreach ( $restrictions['post_types'] as $key => $restriction ) {
			// Skip non-numeric keys (metadata entries).
			if ( ! is_numeric( $key ) ) {
				continue;
			}

			// Skip if post_type is not set or doesn't match.
			if ( ! isset( $restriction['post_type'] ) || $restriction['post_type'] !== $content_post_type ) {
				continue;
			}

			$restriction_taxonomy = isset( $restriction['taxonomy'] ) ? $restriction['taxonomy'] : 'all';

			// Check if content was already viewed.
			if ( isset( $viewed_content[ $content_post_type ] ) && array_key_exists( $this->post_id, $viewed_content[ $content_post_type ] ) ) {
				return false;
			}

			$matches_restriction = ( 'all' === $restriction_taxonomy ) || $this->content_taxonomy_matches( $restriction_taxonomy );

			if ( ! $matches_restriction && 'on' !== $settings['enable_combined_restrictions'] ) {
				continue;
			}

			// Calculate allowed value and views.
			if ( 'on' === $settings['enable_combined_restrictions'] ) {
				$allowed_value         = $settings['combined_restrictions_total_allowed'];
				$number_already_viewed = isset( $viewed_content[ $content_post_type ] ) ? $this->get_total_content_viewed() : 0;
			} else {
				$allowed_value         = $restriction['allowed_value'];
				$number_already_viewed = isset( $viewed_content[ $content_post_type ] )
					? ( 'all' === $restriction_taxonomy ? count( $viewed_content[ $content_post_type ] ) : $this->get_number_viewed_by_term( $restriction_taxonomy ) )
					: 0;
			}

			if ( 0 == $allowed_value ) {
				return true;
			} elseif ( ! empty( $viewed_content ) && $number_already_viewed >= $allowed_value ) {
				return true;
			} else {
				$this->update_content_viewed_by_user();
				return false;
			}
		}

		return false;
	}

	/**
	 * Get number of posts viewed by term
	 *
	 * @param int|string $term_id The term id.
	 * @return int
	 */
	public function get_number_viewed_by_term( $term_id ) {
		$viewed_content = $this->get_content_viewed_by_user();
		$num            = 0;

		foreach ( $viewed_content as $post_type => $items ) {
			foreach ( $items as $post_id => $item ) {
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
	 * Get total content viewed
	 *
	 * @return int
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
	 * Get the paywall subscribe nag
	 *
	 * @return string
	 */
	public function get_subscribe_nag() {
		$content     = get_the_content( null, false, $this->post_id );
		$message     = $this->the_content_paywall_message();
		$new_content = $message;

		return apply_filters( 'leaky_paywall_subscribe_or_login_message', $new_content, $message, $content, $this->post_id );
	}

	/**
	 * Get the nag excerpt
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function get_nag_excerpt( $content ) {
		$settings = $this->get_settings();

		if ( isset( $settings['custom_excerpt_length'] ) && strlen( $settings['custom_excerpt_length'] ) > 0 ) {
			$excerpt = substr( wp_strip_all_tags( $content ), 0, intval( $settings['custom_excerpt_length'] ) );
		} else {
			$excerpt = substr( wp_strip_all_tags( $content ), 0, 100 );
		}

		return apply_filters( 'leaky_paywall_nag_excerpt', strip_shortcodes( $excerpt ), $this->post_id );
	}

	/**
	 * The content paywall message
	 *
	 * @return string
	 */
	public function the_content_paywall_message() {
		$settings             = $this->get_settings();
		$show_upgrade_message = get_post_meta( $this->post_id, '_issuem_leaky_paywall_show_upgrade_message', true );

		$message = '<div class="leaky_paywall_message_wrap"><div id="leaky_paywall_message">';

		if ( ! is_user_logged_in() && 'on' !== $show_upgrade_message ) {
			$text           = $this->replace_variables( stripslashes( $settings['subscribe_login_message'] ) );
			$this->nag_type = 'subscribe';
		} else {
			$text           = $this->replace_variables( stripslashes( $settings['subscribe_upgrade_message'] ) );
			$this->nag_type = 'upgrade';
		}

		$message .= apply_filters( 'leaky_paywall_nag_message_text', $text, $this->post_id );

		/**
		 * Filter the resolved nag type for impression tracking.
		 *
		 * Allows other plugins (e.g. Targeted Subscribe Messages) to report
		 * which specific message was shown.
		 *
		 * @since 4.23.0
		 *
		 * @param string $nag_type The nag type (subscribe, upgrade, or targeted:{post_id}).
		 * @param int    $post_id  The restricted post ID.
		 */
		$this->nag_type = apply_filters( 'leaky_paywall_nag_type', $this->nag_type, $this->post_id );

		$message .= '</div></div>';

		return do_shortcode( $message );
	}

	/**
	 * Replace variables in message
	 *
	 * @param string $message The message.
	 * @return string
	 */
	public function replace_variables( $message ) {
		$settings = $this->get_settings();

		$subscription_url = ( 0 === $settings['page_for_subscription'] )
			? get_bloginfo( 'wpurl' ) . '/?subscription'
			: get_page_link( $settings['page_for_subscription'] );

		$my_account_url = ( 0 === $settings['page_for_profile'] )
			? get_bloginfo( 'wpurl' ) . '/?my-account'
			: get_page_link( $settings['page_for_profile'] );

		$login_url = ( 0 === $settings['page_for_login'] )
			? get_bloginfo( 'wpurl' ) . '/?login'
			: get_page_link( $settings['page_for_login'] );

		$message = str_ireplace( '{{SUBSCRIBE_LOGIN_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{SUBSCRIBE_URL}}', $subscription_url, $message );
		$message = str_ireplace( '{{MY_ACCOUNT_URL}}', $my_account_url, $message );
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
	 * Check if user role can bypass paywall
	 *
	 * @return bool
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
	 * Check if content is unblockable
	 *
	 * @return bool
	 */
	public function is_unblockable_content() {
		$settings = $this->get_settings();

		$unblockable_content = array(
			$settings['page_for_login'],
			$settings['page_for_subscription'],
			$settings['page_for_profile'],
			$settings['page_for_register'],
		);

		if ( in_array( $this->post_id, apply_filters( 'leaky_paywall_unblockable_content', $unblockable_content ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if content matches restriction exceptions
	 *
	 * @return bool
	 */
	public function content_matches_restriction_exceptions() {
		$cache_key = 'lp_restriction_exception_' . $this->post_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$match    = false;
		$settings = $this->get_settings();

		$category_exceptions    = $settings['post_category_exceptions'];
		$category_exception_ids = array_filter( explode( ',', $category_exceptions ) );

		if ( ! empty( $category_exception_ids ) ) {
			$post_categories = get_the_category( $this->post_id );

			foreach ( $post_categories as $cat ) {
				if ( in_array( $cat->term_id, $category_exception_ids, false ) ) {
					$match = true;
					break;
				}
			}
		}

		if ( ! $match ) {
			$tag_exceptions    = $settings['post_tag_exceptions'];
			$tag_exception_ids = array_filter( explode( ',', $tag_exceptions ) );

			if ( ! empty( $tag_exception_ids ) ) {
				$post_tags = get_the_tags( $this->post_id );

				if ( is_array( $post_tags ) ) {
					foreach ( $post_tags as $tag ) {
						if ( in_array( $tag->term_id, $tag_exception_ids, false ) ) {
							$match = true;
							break;
						}
					}
				}
			}
		}

		set_transient( $cache_key, $match ? 'yes' : 'no', 900 );

		return $match;
	}

	/**
	 * Check if visibility allows access
	 *
	 * @return bool
	 */
	public function visibility_allows_access() {
		$visibility = $this->get_visibility();
		$level_ids  = $this->get_level_ids();

		if ( false === $visibility || empty( $visibility['visibility_type'] ) || 'default' === $visibility['visibility_type'] ) {
			return false;
		}

		switch ( $visibility['visibility_type'] ) {
			case 'only':
				$only = array_intersect( $level_ids, $visibility['only_visible'] );
				if ( empty( $only ) ) {
					return false;
				}
				break;

			case 'always':
				if ( in_array( -1, $visibility['always_visible'] ) ) {
					return true;
				}

				$always = array_intersect( $level_ids, $visibility['always_visible'] );
				if ( ! empty( $always ) && leaky_paywall_user_has_access() ) {
					return true;
				}
				break;

			case 'onlyalways':
				$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
				if ( empty( $onlyalways ) ) {
					return false;
				} elseif ( leaky_paywall_user_has_access() ) {
					return true;
				}
				break;
		}

		return false;
	}

	/**
	 * Check if visibility restricts access
	 *
	 * @return bool
	 */
	public function visibility_restricts_access() {
		$visibility = $this->get_visibility();
		$level_ids  = $this->get_level_ids();

		if ( false === $visibility || empty( $visibility['visibility_type'] ) || 'default' === $visibility['visibility_type'] ) {
			return false;
		}

		if ( 'only' === $visibility['visibility_type'] ) {
			$only = array_intersect( $level_ids, $visibility['only_visible'] );
			if ( empty( $only ) ) {
				return true;
			}
		}

		if ( 'onlyalways' === $visibility['visibility_type'] ) {
			$onlyalways = array_intersect( $level_ids, $visibility['only_always_visible'] );
			if ( empty( $onlyalways ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if content is restricted by settings
	 *
	 * @return bool
	 */
	public function content_restricted_by_settings() {
		$restrictions      = $this->get_restriction_settings();
		$content_post_type = $this->get_content_post_type();

		if ( empty( $restrictions ) ) {
			return false;
		}

		foreach ( $restrictions['post_types'] as $key => $restriction ) {
			if ( ! is_numeric( $key ) ) {
				continue;
			}

			$restriction_taxonomy = isset( $restriction['taxonomy'] ) ? $restriction['taxonomy'] : 'all';

			if ( $restriction['post_type'] === $content_post_type && 'all' === $restriction_taxonomy ) {
				return true;
			}

			if ( $restriction['post_type'] === $content_post_type && $this->content_taxonomy_matches( $restriction_taxonomy ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if content taxonomy matches restriction
	 *
	 * @param int|string $restricted_term_id The term id.
	 * @param int        $post_id Optional post id.
	 * @return bool
	 */
	public function content_taxonomy_matches( $restricted_term_id, $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = $this->post_id;
		}

		$cache_key = $post_id . '_' . $restricted_term_id;

		if ( isset( $this->taxonomy_match_cache[ $cache_key ] ) ) {
			return $this->taxonomy_match_cache[ $cache_key ];
		}

		$taxonomies = get_post_taxonomies( $post_id );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term->term_id == $restricted_term_id ) {
						$this->taxonomy_match_cache[ $cache_key ] = true;
						return true;
					}
				}
			}
		}

		$this->taxonomy_match_cache[ $cache_key ] = false;
		return false;
	}

	/**
	 * Get content viewed by user (from parameter or cookie)
	 *
	 * @return array
	 */
	public function get_content_viewed_by_user() {
		// Use override if set (from REST parameter).
		if ( null !== $this->viewed_content_override ) {
			return apply_filters( 'leaky_paywall_available_content', $this->viewed_content_override );
		}

		// Fall back to cookie.
		if ( ! empty( $_COOKIE[ $this->get_cookie_name() ] ) ) {
			$content_viewed = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ $this->get_cookie_name() ] ) ), true );
		} else {
			$content_viewed = array();
		}

		return apply_filters( 'leaky_paywall_available_content', $content_viewed );
	}

	/**
	 * Update content viewed by user
	 *
	 * Note: For REST requests, we return the updated content in the response
	 * so the client can update their local storage.
	 */
	public function update_content_viewed_by_user() {
		$viewed_content       = $this->get_content_viewed_by_user();
		$restricted_post_type = $this->get_content_post_type();
		$expiration_time      = $this->get_expiration_time();

		$viewed_content[ $restricted_post_type ][ $this->post_id ] = $expiration_time;

		// Update the override so subsequent calls see the update.
		$this->viewed_content_override = $viewed_content;

		/**
		 * Fires when content is tracked as viewed.
		 *
		 * @since 4.23.0
		 *
		 * @param int    $post_id       The post ID being tracked.
		 * @param string $post_type     The post type.
		 * @param int    $expiration    The expiration timestamp.
		 * @param array  $viewed_content The updated viewed content array.
		 */
		do_action( 'leaky_paywall_content_viewed', $this->post_id, $restricted_post_type, $expiration_time, $viewed_content );

		// Still set cookie for backwards compatibility.
		$json_viewed_content                     = wp_json_encode( $viewed_content );
		$_COOKIE[ $this->get_cookie_name() ]     = $json_viewed_content;
		setcookie( $this->get_cookie_name(), $json_viewed_content, $expiration_time, '/' );
	}

	/**
	 * Get cookie name
	 *
	 * @return string
	 */
	public function get_cookie_name() {
		$site = leaky_paywall_get_current_site();
		return apply_filters( 'leaky_paywall_restriction_cookie_name', $this->cookie_name . $site );
	}

	/**
	 * Get expiration time
	 *
	 * @return int
	 */
	public function get_expiration_time() {
		$settings = $this->get_settings();

		switch ( $settings['cookie_expiration_interval'] ) {
			case 'hour':
				$multiplier = 60 * 60;
				break;
			case 'day':
				$multiplier = 60 * 60 * 24;
				break;
			case 'week':
				$multiplier = 60 * 60 * 24 * 7;
				break;
			case 'month':
				$multiplier = 60 * 60 * 24 * 7 * 4;
				break;
			case 'year':
				$multiplier = 60 * 60 * 24 * 7 * 52;
				break;
			default:
				$multiplier = 60 * 60 * 24;
		}

		$expiration = time() + ( $settings['cookie_expiration'] * $multiplier );

		return apply_filters( 'leaky_paywall_expiration_time', $expiration );
	}
}

Leaky_Paywall_REST_Restrictions::init();