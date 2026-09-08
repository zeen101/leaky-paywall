<?php

/**
 * Admin new subscriber notification email.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Email_Admin_New_Subscriber extends LP_Email {

	public function __construct() {
		$this->id                 = 'admin_new_subscriber';
		$this->title              = __( 'Admin New Subscriber Email', 'leaky-paywall' );
		$this->description        = __( 'Sent to the admin when a new subscriber signs up.', 'leaky-paywall' );
		$this->recipient_type     = 'admin';
		$this->default_enabled    = 'yes';
		$this->default_subject    = 'New subscription on ' . stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
		$this->default_body       = '';
		$this->default_recipients = get_option( 'admin_email' );

		parent::__construct();
	}

	/**
	 * Trigger the admin notification email.
	 *
	 * The body is auto-generated with subscriber details, not user-configurable.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Must include 'status'.
	 */
	public function trigger( $user_id, $args = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( empty( $this->recipients ) ) {
			return;
		}

		$status = isset( $args['status'] ) ? $args['status'] : 'new';

		if ( ! apply_filters( 'leaky_paywall_send_' . $status . '_admin_email', true, $user_id ) ) {
			return;
		}

		$user_info  = get_userdata( $user_id );
		$mode       = leaky_paywall_get_current_mode();
		$site       = leaky_paywall_get_current_site();
		$site_name  = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );
		$level_id   = get_user_meta( $user_info->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
		$level      = get_leaky_paywall_subscription_level( $level_id );
		$level_name = isset( $level['label'] ) ? $level['label'] : '';

		// Most recent transaction for this subscriber — populated by
		// LP_Transaction::create() during signup, so it carries the payment
		// gateway data, signup URL, customer IP, etc. that we surface below.
		$transactions = get_posts( array(
			'post_type'      => 'lp_transaction',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array( 'key' => '_email', 'value' => $user_info->user_email ),
			),
		) );
		$transaction = ! empty( $transactions ) ? $transactions[0] : null;

		// -- Subscriber details (always shown) --
		$admin_message  = '<p>A new user has signed up on ' . esc_html( $site_name ) . '.</p>';
		$admin_message .= '<h3>Subscriber details</h3><ul>';
		$admin_message .= '<li><strong>Subscription:</strong> ' . esc_html( $level_name );
		if ( '' !== $level_id && false !== $level_id ) {
			$admin_message .= ' (ID: ' . esc_html( $level_id ) . ')';
		}
		$admin_message .= '</li>';

		if ( $user_info->first_name ) {
			$admin_message .= '<li><strong>Name:</strong> ' . esc_html( trim( $user_info->first_name . ' ' . $user_info->last_name ) ) . '</li>';
		}
		$admin_message .= '<li><strong>Email:</strong> ' . esc_html( $user_info->user_email ) . '</li>';

		if ( $user_info->user_registered ) {
			$signup_ts     = strtotime( $user_info->user_registered );
			$signup_when   = $signup_ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $signup_ts ) : '';
			if ( $signup_when ) {
				$admin_message .= '<li><strong>Signup date:</strong> ' . esc_html( $signup_when ) . '</li>';
			}
		}

		// Signup page URL — resolved from the transaction's `_nag_location_id`
		// (the post ID where the visitor hit the paywall nag). Present for
		// most signups originating from paywall-triggered content, absent
		// for direct-to-registration flows.
		if ( $transaction ) {
			$nag_loc_id = (int) get_post_meta( $transaction->ID, '_nag_location_id', true );
			if ( $nag_loc_id ) {
				$signup_url = get_permalink( $nag_loc_id );
				if ( $signup_url ) {
					$admin_message .= '<li><strong>Signup page:</strong> <a href="' . esc_url( $signup_url ) . '">' . esc_html( $signup_url ) . '</a></li>';
				}
			}
		}

		$admin_message .= '</ul>';

		// -- Payment (only for paid signups) --
		// Free signups skip this section entirely per publisher preference —
		// showing "Amount: $0" on every free registration is just noise.
		if ( $transaction ) {
			$gateway = get_post_meta( $transaction->ID, '_gateway', true );
			$price   = get_post_meta( $transaction->ID, '_price', true );
			$is_free = 'free_registration' === $gateway || 0.0 === (float) $price;

			if ( ! $is_free ) {
				$status_meta    = get_post_meta( $transaction->ID, '_status', true );
				$is_recurring   = get_post_meta( $transaction->ID, '_is_recurring', true );
				$gateway_txn_id = get_post_meta( $transaction->ID, '_gateway_txn_id', true );
				$transaction_admin_url = admin_url( 'post.php?post=' . $transaction->ID . '&action=edit' );

				$gateway_names = array(
					'stripe'          => 'Stripe',
					'stripe_checkout' => 'Stripe Checkout',
					'paypal_standard' => 'PayPal Standard',
					'authorizenet'    => 'Authorize.Net',
					'manual'          => 'Manual',
				);
				$gateway_display = isset( $gateway_names[ $gateway ] )
					? $gateway_names[ $gateway ]
					: ucwords( str_replace( '_', ' ', (string) $gateway ) );

				$admin_message .= '<h3>Payment</h3><ul>';
				$admin_message .= '<li><strong>Gateway:</strong> ' . esc_html( $gateway_display ) . '</li>';
				if ( $price ) {
					$formatted_amount = function_exists( 'leaky_paywall_format_display_price' )
						? leaky_paywall_format_display_price( $price )
						: $price;
					$admin_message .= '<li><strong>Amount:</strong> ' . esc_html( $formatted_amount ) . '</li>';
				}
				$admin_message .= '<li><strong>Type:</strong> ' . ( $is_recurring ? 'Recurring subscription' : 'One-time payment' ) . '</li>';
				if ( $status_meta ) {
					$admin_message .= '<li><strong>Status:</strong> ' . esc_html( $status_meta ) . '</li>';
				}
				if ( $gateway_txn_id ) {
					$admin_message .= '<li><strong>Transaction:</strong> <a href="' . esc_url( $transaction_admin_url ) . '">' . esc_html( $gateway_txn_id ) . '</a></li>';
				}
				$admin_message .= '</ul>';
			}
		}

		// -- Attribution (only when the signals were captured) --
		// Customer IP (populated when leaky_paywall_get_customer_ip() resolved
		// one at transaction time) and UTM parameters (first-touch cookie set
		// by the Insights UTM capture script). Server-initiated signups —
		// webhooks, admin manual adds — will typically have neither, and the
		// whole section is skipped.
		$attribution_rows = array();

		if ( $transaction ) {
			$customer_ip = get_post_meta( $transaction->ID, '_customer_ip', true );
			if ( $customer_ip ) {
				$attribution_rows[] = '<li><strong>IP address:</strong> ' . esc_html( $customer_ip ) . '</li>';
			}
		}

		if ( ! empty( $_COOKIE['lp_utm'] ) ) {
			$utm = json_decode( stripslashes( $_COOKIE['lp_utm'] ), true );
			if ( is_array( $utm ) ) {
				$utm_labels = array(
					'utm_source'   => 'UTM source',
					'utm_medium'   => 'UTM medium',
					'utm_campaign' => 'UTM campaign',
					'utm_term'     => 'UTM term',
					'utm_content'  => 'UTM content',
				);
				foreach ( $utm_labels as $key => $label ) {
					if ( ! empty( $utm[ $key ] ) ) {
						$attribution_rows[] = '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( sanitize_text_field( $utm[ $key ] ) ) . '</li>';
					}
				}
			}
		}

		if ( ! empty( $attribution_rows ) ) {
			$admin_message .= '<h3>Attribution</h3><ul>' . implode( '', $attribution_rows ) . '</ul>';
		}

		// -- Quick actions — direct links to the admin views --
		$subscriber_admin_url = admin_url( 'admin.php?page=leaky-paywall-subscribers&action=show&id=' . $user_id );
		$admin_message .= '<p><strong>Quick actions:</strong><br>';
		$admin_message .= '<a href="' . esc_url( $subscriber_admin_url ) . '">View subscriber &rarr;</a>';
		if ( $transaction ) {
			$transaction_admin_url = admin_url( 'post.php?post=' . $transaction->ID . '&action=edit' );
			$admin_message .= '<br><a href="' . esc_url( $transaction_admin_url ) . '">View transaction &rarr;</a>';
		}
		$admin_message .= '</p>';

		// LP Basic Shipping (and other extensions) hook this filter to append
		// their own sections — e.g. shipping address for fulfilment. Keep it
		// as the last transform on the message so extensions get the full
		// body to work with.
		$admin_message = apply_filters( 'leaky_paywall_new_subscriber_admin_email', $admin_message, $user_info );

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, $status );

		wp_mail( $this->recipients, $this->subject, $this->wrap( $admin_message ), $headers, $attachments );
	}

	/**
	 * No body field for this email — the content is auto-generated.
	 */
	protected function output_body_field() {}
}
