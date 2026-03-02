<?php
/**
 * Leaky Paywall REST API Subscribers
 *
 * REST endpoints for subscriber management and self-service.
 *
 * @package Leaky Paywall
 * @since 4.23.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_REST_Subscribers {

	const NAMESPACE = 'leaky-paywall/v1';

	/**
	 * Initialize the REST API endpoints.
	 */
	public static function init() {
		$instance = new self();
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {

		$settings = get_leaky_paywall_settings();

		if ( 'on' !== $settings['enable_rest_api'] ) {
			return;
		}

		// GET /subscribers — list subscribers (admin).
		// POST /subscribers — create subscriber (admin).
		register_rest_route(
			self::NAMESPACE,
			'/subscribers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_subscribers' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_subscriber' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_create_params(),
				),
			)
		);

		// GET /subscribers/{id} — single subscriber (admin).
		register_rest_route(
			self::NAMESPACE,
			'/subscribers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_subscriber' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_subscriber' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
					'args'                => $this->get_update_params(),
				),
			)
		);

		// GET /me — current user's subscription.
		register_rest_route(
			self::NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_current_user_subscription' ),
				'permission_callback' => array( $this, 'logged_in_permissions_check' ),
			)
		);
	}

	/**
	 * Permission check: admin only.
	 *
	 * @return bool|WP_Error
	 */
	public function admin_permissions_check() {
		if ( current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this resource.', 'leaky-paywall' ),
			array( 'status' => current_user_can( 'read' ) ? 403 : 401 )
		);
	}

	/**
	 * Permission check: logged-in user.
	 *
	 * @return bool|WP_Error
	 */
	public function logged_in_permissions_check() {
		if ( is_user_logged_in() ) {
			return true;
		}

		return new WP_Error(
			'rest_not_logged_in',
			__( 'You must be logged in to access this resource.', 'leaky-paywall' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * POST /subscribers — create a new subscriber.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_subscriber( $request ) {

		$email = $request->get_param( 'email' );

		// Check if email already has a subscription.
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			$mode     = leaky_paywall_get_current_mode();
			$site     = leaky_paywall_get_current_site();
			$existing_level = get_user_meta( $existing_user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

			if ( '' !== $existing_level && false !== $existing_level ) {
				return new WP_Error(
					'rest_subscriber_exists',
					__( 'A subscriber with this email already exists.', 'leaky-paywall' ),
					array( 'status' => 409 )
				);
			}
		}

		$level_id = $request->get_param( 'level_id' );
		$level    = get_leaky_paywall_subscription_level( $level_id );

		if ( ! $level ) {
			return new WP_Error(
				'rest_invalid_level',
				__( 'Invalid subscription level.', 'leaky-paywall' ),
				array( 'status' => 400 )
			);
		}

		$meta_args = array(
			'level_id'       => $level_id,
			'subscriber_id'  => $request->get_param( 'subscriber_id' ) ? $request->get_param( 'subscriber_id' ) : '',
			'price'          => $level['price'],
			'description'    => $level['label'],
			'payment_gateway' => $request->get_param( 'payment_gateway' ) ? $request->get_param( 'payment_gateway' ) : 'manual',
			'payment_status' => $request->get_param( 'payment_status' ) ? $request->get_param( 'payment_status' ) : 'active',
			'plan'           => $request->get_param( 'plan' ) ? $request->get_param( 'plan' ) : '',
			'interval'       => isset( $level['interval'] ) ? $level['interval'] : '',
			'interval_count' => isset( $level['interval_count'] ) ? $level['interval_count'] : '',
			'site'           => leaky_paywall_get_current_site(),
			'first_name'     => $request->get_param( 'first_name' ) ? $request->get_param( 'first_name' ) : '',
			'last_name'      => $request->get_param( 'last_name' ) ? $request->get_param( 'last_name' ) : '',
		);

		$user_id = leaky_paywall_new_subscriber( null, $email, $meta_args['subscriber_id'], $meta_args );

		if ( ! $user_id ) {
			return new WP_Error(
				'rest_subscriber_create_failed',
				__( 'Could not create subscriber.', 'leaky-paywall' ),
				array( 'status' => 500 )
			);
		}

		do_action( 'leaky_paywall_rest_after_create_subscriber', $user_id, $request, $level );

		$user               = get_user_by( 'id', $user_id );
		$data               = $this->prepare_subscriber( $user );
		$data['notes']      = lp_get_subscriber_meta( 'notes', $user );
		$data['status_log'] = leaky_paywall_get_status_log( $user_id );

		return new WP_REST_Response( $data, 201 );
	}

	/**
	 * GET /subscribers — list subscribers with pagination and filtering.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_subscribers( $request ) {

		$mode = leaky_paywall_get_current_mode();
		$site = leaky_paywall_get_current_site();

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$status   = $request->get_param( 'status' );
		$level_id = $request->get_param( 'level_id' );
		$search   = $request->get_param( 'search' );

		$meta_query = array(
			array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'compare' => 'EXISTS',
			),
		);

		if ( ! empty( $status ) ) {
			$meta_query['relation'] = 'AND';
			$meta_query[]           = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site,
				'value'   => $status,
				'compare' => '=',
			);
		}

		if ( '' !== $level_id && null !== $level_id ) {
			if ( ! isset( $meta_query['relation'] ) ) {
				$meta_query['relation'] = 'AND';
			}
			$meta_query[] = array(
				'key'     => '_issuem_leaky_paywall_' . $mode . '_level_id' . $site,
				'value'   => $level_id,
				'compare' => '=',
			);
		}

		$args = array(
			'number'     => $per_page,
			'paged'      => $page,
			'meta_query' => $meta_query,
			'orderby'    => 'registered',
			'order'      => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_email', 'user_login' );
		}

		$query = new WP_User_Query( $args );
		$users = $query->get_results();
		$total = $query->get_total();

		$subscribers = array();

		foreach ( $users as $user ) {
			$subscribers[] = $this->prepare_subscriber( $user );
		}

		$response = new WP_REST_Response( $subscribers, 200 );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * GET /subscribers/{id} — single subscriber.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_subscriber( $request ) {

		$user_id = $request->get_param( 'id' );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'rest_subscriber_not_found',
				__( 'Subscriber not found.', 'leaky-paywall' ),
				array( 'status' => 404 )
			);
		}

		$data               = $this->prepare_subscriber( $user );
		$data['notes']      = lp_get_subscriber_meta( 'notes', $user );
		$data['status_log'] = leaky_paywall_get_status_log( $user_id );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * PUT /subscribers/{id} — update subscriber fields.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_subscriber( $request ) {

		$user_id = $request->get_param( 'id' );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'rest_subscriber_not_found',
				__( 'Subscriber not found.', 'leaky-paywall' ),
				array( 'status' => 404 )
			);
		}

		$updatable_fields = array( 'level_id', 'payment_status', 'expires', 'subscriber_id', 'payment_gateway', 'plan' );

		foreach ( $updatable_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				lp_update_subscriber_meta( $field, $value, $user_id );
			}
		}

		// Refresh user object and return updated data.
		$user               = get_user_by( 'id', $user_id );
		$data               = $this->prepare_subscriber( $user );
		$data['notes']      = lp_get_subscriber_meta( 'notes', $user );
		$data['status_log'] = leaky_paywall_get_status_log( $user_id );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /me — current user's subscription data.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_current_user_subscription( $request ) {

		$user = wp_get_current_user();
		$data = $this->prepare_subscriber( $user );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Prepare subscriber data array from a WP_User object.
	 *
	 * @param WP_User $user
	 * @return array
	 */
	private function prepare_subscriber( $user ) {

		$level_id   = lp_get_subscriber_meta( 'level_id', $user );
		$level      = get_leaky_paywall_subscription_level( $level_id );
		$level_name = $level ? $level['label'] : '';

		$data = array(
			'id'              => $user->ID,
			'email'           => $user->user_email,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
			'level_id'        => $level_id,
			'level_name'      => $level_name,
			'subscriber_id'   => lp_get_subscriber_meta( 'subscriber_id', $user ),
			'price'           => lp_get_subscriber_meta( 'price', $user ),
			'plan'            => lp_get_subscriber_meta( 'plan', $user ),
			'created'         => lp_get_subscriber_meta( 'created', $user ),
			'expires'         => lp_get_subscriber_meta( 'expires', $user ),
			'has_access'      => leaky_paywall_user_has_access( $user ),
			'payment_gateway' => lp_get_subscriber_meta( 'payment_gateway', $user ),
			'payment_status'  => lp_get_subscriber_meta( 'payment_status', $user ),
		);

		return apply_filters( 'leaky_paywall_rest_subscriber', $data, $user );
	}

	/**
	 * Collection query parameters for the subscribers list endpoint.
	 *
	 * @return array
	 */
	private function get_collection_params() {
		return array(
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			),
			'per_page' => array(
				'default'           => 10,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0 && $param <= 100;
				},
			),
			'status'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'level_id' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Parameters for the create subscriber endpoint.
	 *
	 * @return array
	 */
	private function get_create_params() {
		return array(
			'email'           => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_email',
				'validate_callback' => function ( $param ) {
					return is_email( $param );
				},
			),
			'level_id'        => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'first_name'      => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'       => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment_status'  => array(
				'required'          => false,
				'default'           => 'active',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					$valid = array(
						'active', 'pending_cancel', 'trial', 'trialing', 'past_due',
						'unpaid', 'incomplete', 'incomplete_expired', 'paused',
						'canceled', 'expired', 'pending_activation', 'renewal_due',
						'on_hold', 'grace_period', 'deactivated',
					);
					return in_array( $param, $valid, true );
				},
			),
			'payment_gateway' => array(
				'required'          => false,
				'default'           => 'manual',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'subscriber_id'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'plan'            => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Parameters for the update subscriber endpoint.
	 *
	 * @return array
	 */
	private function get_update_params() {
		return array(
			'id'              => array(
				'required'          => true,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
				'sanitize_callback' => 'absint',
			),
			'level_id'        => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment_status'  => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					$valid = array(
						'active', 'pending_cancel', 'trial', 'trialing', 'past_due',
						'unpaid', 'incomplete', 'incomplete_expired', 'paused',
						'canceled', 'expired', 'pending_activation', 'renewal_due',
						'on_hold', 'grace_period', 'deactivated',
					);
					return in_array( $param, $valid, true );
				},
			),
			'expires'         => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'subscriber_id'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'payment_gateway' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'plan'            => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}

Leaky_Paywall_REST_Subscribers::init();
