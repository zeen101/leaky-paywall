<?php
/**
 * Leaky Paywall shortcodes
 *
 * @package zeen101's Leaky Paywall
 * @since 1.0.0
 */

if ( ! function_exists( 'do_leaky_paywall_login' ) ) {

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function do_leaky_paywall_login( $atts ) {
		$settings = get_leaky_paywall_settings();

		$defaults = array(
			'heading'            => __( 'Email address:', 'leaky-paywall' ),
			'description'        => __( 'Check your email for a link to log in.', 'leaky-paywall' ),
			'email_sent'         => __( 'Email sent. Please check your email for the login link.', 'leaky-paywall' ),
			'error_msg'          => __( 'Error sending login email, please try again later.', 'leaky-paywall' ),
			'missing_email_msg'  => __( 'Please supply a valid email address.', 'leaky-paywall' ),
			'login_redirect_url' => '',
		);

		// Merge defaults with passed atts.
		$args    = shortcode_atts( $defaults, $atts );
		$results = '';

		if ( $args['login_redirect_url'] ) {
			$page_link = $args['login_redirect_url'];
		} elseif ( ! empty( $settings['page_for_profile'] ) ) {
			$page_link = get_page_link( $settings['page_for_profile'] );
		} elseif ( ! empty( $settings['page_for_subscription'] ) ) {
			$page_link = get_page_link( $settings['page_for_subscription'] );
		}

		$results .= apply_filters( 'leaky_paywall_before_login_form', '' );

		$results .= '<div id="leaky-paywall-login-form">';

		if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) {
			$results .= '<div class="leaky_paywall_message error"><p>' . __( 'Incorrect username or password.', 'leaky-paywall' ) . '</p></div>';
		}

		add_action( 'login_form_bottom', 'leaky_paywall_add_lost_password_link' );
		$args     = array(
			'echo'     => false,
			'redirect' => $page_link,
		);
		$results .= wp_login_form( apply_filters( 'leaky_paywall_login_form_args', $args ) );

		$results .= '</div>';

		return $results;
	}
	add_shortcode( 'leaky_paywall_login', 'do_leaky_paywall_login' );
}

if ( ! function_exists( 'do_leaky_paywall_subscription' ) ) {

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 */
	function do_leaky_paywall_subscription( $atts ) {
		if ( isset( $_REQUEST['level_id'] ) ) {
			return do_leaky_paywall_register_form( $atts );
		}

		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();

		$defaults = array(
			'login_heading' => __( 'Enter your email address to start your subscription:', 'leaky-paywall' ),
			'login_desc'    => __( 'Check your email for a link to start your subscription.', 'leaky-paywall' ),
		);

		$args = shortcode_atts( $defaults, $atts );

		$results = '';

		if ( is_user_logged_in() ) {

			$sites = array( '' );
			if ( is_multisite_premium() ) {
				global $blog_id;
				if ( ! is_main_site( $blog_id ) ) {
					$sites = array( '_all', '_' . $blog_id );
				} else {
					$sites = array( '_all', '_' . $blog_id, '' );
				}
			}

			$user = wp_get_current_user();

			$results .= apply_filters( 'leaky_paywall_subscriber_info_start', '' );

			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';

			foreach ( $sites as $site ) {

				$expires = leaky_paywall_has_user_paid( $user->user_email, $site );

				if ( false !== $expires ) {

					$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );

					$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
					$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
					$plan            = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
					$status          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );

					if ( empty( $payment_gateway ) && empty( $subscriber_id ) ) {
						continue;
					}

					$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_end', '' );

					$results .= '<p><a href="' . wp_logout_url( get_page_link( $settings['page_for_login'] ) ) . '">' . __( 'Log Out', 'leaky-paywall' ) . '</a></p>';

					break; // We only want one.

				}
			}

			$results .= '</div>';

			$results .= apply_filters( 'leaky_paywall_subscriber_info_end', '' );
		}

		$results .= leaky_paywall_subscription_options();

		return $results;
	}
	add_shortcode( 'leaky_paywall_subscription', 'do_leaky_paywall_subscription' );
}

if ( ! function_exists( 'do_leaky_paywall_profile' ) ) {

	/**
	 * Shortcode for zeen101's Leaky Paywall
	 * Prints out the zeen101's Leaky Paywall
	 *
	 * @throws Exception Stripe error.
	 */
	function do_leaky_paywall_profile() {
		$settings = get_leaky_paywall_settings();
		$mode     = leaky_paywall_get_current_mode();
		$site     = leaky_paywall_get_current_site();
		$results  = '';
		
		if ( is_user_logged_in() ) {

			$user = wp_get_current_user();

			if ( isset( $_POST['stripeToken'] ) ) {

				$secret_key    = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
				$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
				$status        = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
				$level_id      = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
				$plan          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
				$level         = get_leaky_paywall_subscription_level( $level_id );

				$stripe = leaky_paywall_initialize_stripe_api();

				try {

					// update source.
					$cu      = $stripe->customers->update( $subscriber_id, array( 'source' => sanitize_text_field( wp_unslash( $_POST['stripeToken'] ) ) ) );
					$sources = $stripe->customers->allSources( $subscriber_id );
					$card_id = isset( $sources->data[0]->id ) ? $sources->data[0]->id : '';

					if ( $card_id ) {
						// update customer default source with the card ID.
						$cu                  = $stripe->customers->update(
							$subscriber_id,
							array(
								'invoice_settings' => array(
									'default_payment_method' => $card_id,
								),
							)
						);
						$update_card_success = __( 'Your card details have been updated!', 'leaky-paywall' );
						leaky_paywall_log( $user->user_email, 'credit card updated to ' . $card_id );
					}

					if ( strcasecmp( 'deactivated', $status ) === 0 ) {

						$subs = $stripe->subscriptions->all(
							array(
								'customer' => $subscriber_id,
								'status'   => 'all',
							)
						);

						if ( ! empty( $subs->data ) ) {

							foreach ( $subs->data as $sub ) {

								// we are only checking against the subscribers current plan.
								if ( $plan !== $sub->items->data[0]->plan->id ) {
									continue;
								}

								if ( 'active' === $sub->status || 'past_due' === $sub->status || 'trialing' === $sub->status ) {
									leaky_paywall_log( $user->user_email, 'has a subscription, did not create a new subscription after card update' );
								} else {

									// only create a new subscription if the subscriber does not have a current subscription.
									// such as expired or canceled.
									$new_sub = $stripe->subscriptions->create(
										array(
											'customer' => $cu->id,
											'items'    => array( array( 'plan' => $plan ) ),
										)
									);

									leaky_paywall_log( $user->user_email, 'created new subscription after card update' );
								}
							}
						}

						/* Translators: %s - site name */
						$update_card_success .= sprintf( __( 'Your subscription has been restarted! Please <a href="%s">click here</a> to see your updated account status.', 'leaky-paywall' ), get_the_permalink( get_the_ID() ) );

					}
				} catch ( \Stripe\Exception\ApiErrorException $e ) {

					$body              = $e->getJsonBody();
					$err               = $body['error'];
					$update_card_error = $err['message'];
				} catch ( \Stripe\Exception\ApiErrorException $e ) {
					$body              = $e->getJsonBody();
					$err               = $body['error'];
					$update_card_error = $err['message'];
				}
			}

			/* Translators: %1$s - user login, %2$s - logout url */
			$results .= '<p class="leaky-paywall-logout-link">' . sprintf( __( 'Welcome %1$s, you are currently logged in. <a href="%2$s">Click here to log out.</a>', 'leaky-paywall' ) . '</p>', $user->user_login, wp_logout_url( get_page_link( $settings['page_for_login'] ) ) );

			$results .= '<h2 class="leaky-paywall-profile-subscription-title">' . __( 'Your Subscription', 'leaky-paywall' ) . '</h2>';

			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_start', '' );

			$profile_table  = '<table class="leaky-paywall-profile-subscription-details">';
			$profile_table .= '<thead>';
			$profile_table .= '<tr>';
			$profile_table .= '	<th>' . __( 'Have Access', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Status', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Type', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Payment Method', 'leaky-paywall' ) . '</th>';
			$profile_table .= '	<th>' . __( 'Expiration', 'leaky-paywall' ) . '</th>';
			$profile_table .= '</tr>';
			$profile_table .= '</thead>';

			$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );

			if ( leaky_paywall_user_has_access() ) {
				$has_access = __( 'Yes', 'leaky-paywall' );
			} else {
				$has_access = __( 'No', 'leaky-paywall' );
			}

			$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
			$level_id = apply_filters( 'get_leaky_paywall_users_level_id', $level_id, $user, $mode, $site );
			$level_id = apply_filters( 'get_leaky_paywall_subscription_level_level_id', $level_id );
			if ( false === $level_id || empty( $settings['levels'][ $level_id ]['label'] ) ) {
				$level_name = __( 'Undefined', 'leaky-paywall' );
			} else {
				$level_name = stripcslashes( $settings['levels'][ $level_id ]['label'] );
			}

			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );

			$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
			$expires = apply_filters( 'do_leaky_paywall_profile_shortcode_expiration_column', $expires, $user, $mode, $site, $level_id );
			if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
				$expires = __( 'Never', 'leaky-paywall' );
			} else {
				$date_format = 'F j, Y';
				$expires     = mysql2date( $date_format, $expires );
			}

			$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
			if ( ! empty( $plan ) && 'Canceled' !== $plan && 'Never' !== $expires ) {

				if ( 'canceled' === $status ) {
					/* Translators: %s - expiration date */
					$expires = sprintf( __( 'Ends on %s', 'leaky-paywall' ), $expires );
				} else {
					/* Translators: %s - recurrs on date */
					$expires = sprintf( __( 'Recurs on %s', 'leaky-paywall' ), $expires );
				}
			}

			$paid       = leaky_paywall_has_user_paid( $user->user_email, $site );
			$expiration = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
			$cancel     = '';

			if ( empty( $expires ) || '0000-00-00 00:00:00' === $expiration ) {
				$cancel = '';
			} elseif ( strcasecmp( 'active', $status ) === 0 && $plan && 'Canceled' !== $plan ) {
				$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );

				if ( 'free_registration' === $payment_gateway ) {
					$cancel = '';
				} else {
					/* Translators: %s - cancel url */
					$cancel = sprintf( __( '<a href="%s">Cancel your subscription</a>', 'leaky-paywall' ), '?cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id );
				}
			} elseif ( ! empty( $plan ) && 'Canceled' === $plan ) {
				/* Translators: %s - subscription url */
				$cancel .= '<p>' . sprintf( __( 'You have canceled your subscription, but your account will remain active until your expiration date. To reactivate your subscription, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
			}

			if ( 'stripe' === $payment_gateway ) {
				$profile_payment = __( 'Credit Card', 'leaky-paywall' );
			} else {
				$profile_payment = leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway );
			}

			if ( strcasecmp( 'active', $status ) === 0 && ! leaky_paywall_user_has_access() ) {
				$status_name = __( 'Expired', 'leaky-paywall' );
			} else {
				$status_name = ucfirst( $status );

				if ( 'Active' === $status_name ) {
					$status_name = __( 'Active', 'leaky-paywall' );
				}
			}

			if ( ! empty( $status ) && ! empty( $level_name ) && ! empty( $payment_gateway ) && ! empty( $expires ) ) {
				$profile_table .= '<tbody>';
				$profile_table .= ' <td>' . $has_access . '</td>';
				$profile_table .= '	<td>' . $status_name . '</td>';
				$profile_table .= '	<td>' . $level_name . '</td>';
				$profile_table .= '	<td>' . $profile_payment . '</td>';
				$profile_table .= '	<td>' . $expires . '</td>';
				$profile_table .= '</tbody>';
			}

			$profile_table .= '</table>';

			if ( $cancel ) {
				$profile_table .= '<p class="leaky-paywall-cancel-link">' . apply_filters( 'leaky_paywall_cancel_link', $cancel ) . '</p>';
			}

			$results .= apply_filters( 'leaky_paywall_profile_table', $profile_table, $user, $site, $mode, $settings );
			$results .= apply_filters( 'leaky_paywall_profile_your_subscription_end', '' );

			$results .= '<div class="issuem-leaky-paywall-subscriber-info">';

			$results .= apply_filters( 'leaky_paywall_profile_your_payment_info_start', '' );
			$results .= apply_filters( 'leaky_paywall_subscriber_info_paid_subscriber_start', '' );

			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
			$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			$status          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
			$expires         = leaky_paywall_has_user_paid( $user->user_email, $site );

			if ( strcasecmp( 'active', $status ) === 0 || strcasecmp( 'deactivated', $status ) === 0 ) {
				$payment_form = '';
				switch ( $payment_gateway ) {

					case 'stripe':
						if ( isset( $update_card_error ) ) {
							$results .= '<div class="leaky_paywall_message error"><p>' . $update_card_error . '</p></div>';
						} elseif ( isset( $update_card_success ) ) {
							$results .= '<div class="leaky_paywall_message success"><p>' . $update_card_success . '</p></div>';
						}

						$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];

						$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );

						$stripe = leaky_paywall_initialize_stripe_api();

						try {
							$cu = $stripe->customers->retrieve( $subscriber_id );
						} catch (\Throwable $th) {
							// 
						}

						if ( isset( $cu->default_source->brand ) ) {
							$payment_form .= '<p><strong>Method</strong><br>' . $cu->default_source->brand . ' ending in ' . $cu->default_source->last4 . ' that expires ' . $cu->default_source->exp_month . '/' . $cu->default_source->exp_year . '</p>';
						}

						if ( strcasecmp( 'deactivated', $status ) === 0 ) {
							$data_label = __( 'Update Credit Card Details & Restart Subscription', 'leaky-paywall' );
						} else {
							$data_label = __( 'Update Credit Card Details', 'leaky-paywall' );
						}

						$payment_form .= '<form action="" method="POST">
									<script
									src="https://checkout.stripe.com/checkout.js" class="stripe-button"
									data-key="' . $publishable_key . '"
									data-name="' . get_bloginfo( 'name' ) . '"
									data-panel-label="' . $data_label . '"
									data-label="' . $data_label . '"
									data-allow-remember-me=false
									data-email="' . $user->user_email . '"
									data-locale="auto">	
									</script>	
									</form>';
					
						break;

					case 'paypal-standard':
					case 'paypal_standard':
						$paypal_url    = 'test' === $mode ? 'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
						$paypal_email  = 'test' === $mode ? $settings['paypal_sand_email'] : $settings['paypal_live_email'];
						$payment_form .= '<p>' . __( "You can update your payment details through PayPal's website.", 'leaky-paywall' ) . '</p>';
						$payment_form .= '<p><a href="' . $paypal_url . '"><img src="' . LEAKY_PAYWALL_URL . 'images/btn_pponly_142x27.png" border="0"></a></p>';
						break;
				}

				if ( $payment_form ) {
					$results .= '<hr>';
					$results .= '<h2 class="leaky-paywall-your-payment-information-header">' . __( 'Your Payment Information', 'leaky-paywall' ) . '</h2>';
					$results .= $payment_form;
				}
			} elseif ( ! empty( $plan ) && 'Canceled' === $plan ) {
				$results .= '<h2 class="leaky-paywall-subscription-status-header">' . __( 'Your Subscription Has Been Canceled', 'leaky-paywall' ) . '</h2>';
			} else {

				if ( leaky_paywall_user_can_bypass_paywall_by_role( $user ) ) {
					$results .= '<h2 class="leaky-paywall-subscription-status-header">' . __( 'Your user role can see all content.', 'leaky-paywall' ) . '</h2>';
				} else {
					$results .= '<h2 class="leaky-paywall-subscription-status-header">' . __( 'Your Account is Not Currently Active', 'leaky-paywall' ) . '</h2>';
					/* Translators: %s - page for subscription */
					$results .= '<p>' . sprintf( __( 'To reactivate your account, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
				}
			}

			$results .= '</div>';
			$results .= apply_filters( 'leaky_paywall_profile_your_payment_info_end', '' );

			$results .= '<hr>';

			$results .= apply_filters( 'leaky_paywall_profile_your_profile_start', '' );

			$results .= '<h2 class="leaky-paywall-your-profile-header">' . __( 'Your Profile', 'leaky-paywall' ) . '</h2>';

			if ( ! empty( $_POST['leaky-paywall-profile-nonce'] ) ) {

				if ( wp_verify_nonce( sanitize_key( wp_unslash( $_POST['leaky-paywall-profile-nonce'] ) ), 'leaky-paywall-profile' ) ) {

					try {
						$userdata = get_userdata( $user->ID );
						$args     = array(
							'ID'           => $user->ID,
							'user_login'   => $userdata->user_login,
							'display_name' => $userdata->display_name,
							'user_email'   => $userdata->user_email,
						);

						if ( ! empty( $_POST['username'] ) ) {
							$args['user_login'] = sanitize_text_field( wp_unslash( $_POST['username'] ) );
						}

						if ( ! empty( $_POST['displayname'] ) ) {
							$args['display_name'] = sanitize_text_field( wp_unslash( $_POST['displayname'] ) );
						}

						if ( ! empty( $_POST['email'] ) ) {
							if ( is_email( wp_unslash( $_POST['email'] ) ) ) {
								$args['user_email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
							} else {
								throw new Exception( __( 'Invalid email address.', 'leaky-paywall' ) );
							}
						}

						if ( ! empty( $_POST['password1'] ) && ! empty( $_POST['password2'] ) ) {
							if ( $_POST['password1'] === $_POST['password2'] ) {
								wp_set_password( sanitize_text_field( wp_unslash( ( $_POST['password1'] ) ) ), $user->ID );
							} else {
								throw new Exception( __( 'Passwords do not match.', 'leaky-paywall' ) );
							}
						}

						$user_id = wp_update_user( $args );

						if ( is_wp_error( $user_id ) ) {
							throw new Exception( $user_id->get_error_message() );
						} else {
							$user     = get_userdata( $user_id ); // Refresh the user object.
							$results .= '<div class="leaky_paywall_message success"><p>' . __( 'Profile Changes Saved.', 'leaky-paywall' ) . '</p></div>';

							do_action( 'leaky_paywall_after_profile_changes_saved', $user_id, $args, $userdata );
						}
					} catch ( Exception $e ) {
						$results .= '<div class="leaky_paywall_message error"><p class="error">' . $e->getMessage() . '</p></div>';
					}
				}
			}

			$results .= '<form id="leaky-paywall-profile" action="" method="post">';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-username">' . __( 'Username', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-username" name="username" value="' . $user->user_login . '" disabled="disabled" readonly="readonly" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-display-name">' . __( 'Display Name', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="' . $user->display_name . '" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-email">' . __( 'Email', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="text" class="issuem-leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="' . $user->user_email . '" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-password1">' . __( 'New Password', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />';
			$results .= '</p>';

			$results .= '<p>';
			$results .= '<label class="leaky-paywall-field-label" for="leaky-paywall-gift-subscription-password2">' . __( 'New Password (again)', 'leaky-paywall' ) . '</label>';
			$results .= '<input type="password" class="issuem-leaky-paywall-field-input" id="leaky-paywall-gift-subscription-password2" name="password2" value="" />';
			$results .= '</p>';

			$results .= apply_filters( 'leaky_paywall_profile_your_profile_before_submit', '' );

			$results .= wp_nonce_field( 'leaky-paywall-profile', 'leaky-paywall-profile-nonce', true, false );

			$results .= '<p class="submit"><input type="submit" id="submit" class="button button-primary" value="' . __( 'Save Profile Changes', 'leaky-paywall' ) . '"  /></p>';
			$results .= '</form>';

			if ( 'on' === $settings['enable_user_delete_account'] ) {
				$results .= '<form id="leaky-paywall-delete-account" action="" method="post">';
				$results .= '<p><button type="submit" onclick="return confirm(\'Deleting your account will delete your access and all your information on this site. If you have a recurring subscription, you must cancel that first to stop payments. Are you sure you want to continue?\')">' . __( 'Delete Account', 'leaky-paywall' ) . '</button></p>';
				$results .= wp_nonce_field( 'leaky-paywall-delete-account', 'leaky-paywall-delete-account-nonce', true, false );
				$results .= '</form>';
			}

			$results .= apply_filters( 'leaky_paywall_profile_your_profile_end', '' );
		} else {

			$results .= do_leaky_paywall_login( array() );
		}

		return $results;
	}
	add_shortcode( 'leaky_paywall_profile', 'do_leaky_paywall_profile' );
}

/**
 * Outputs the default Leaky Paywall register form
 *
 * @since 3.7.0
 *
 * @param array $atts Shortcode attributes.
 */
function do_leaky_paywall_register_form( $atts ) {
	$a = shortcode_atts(
		array(
			'level_id' => '',
		),
		$atts
	);

	$settings = get_leaky_paywall_settings();

	if ( is_numeric( $a['level_id'] ) ) {
		$level_id = $a['level_id'];
	} else {
		$level_id = isset( $_GET['level_id'] ) ? sanitize_text_field( wp_unslash( $_GET['level_id'] ) ) : null;
	}

	$level = get_leaky_paywall_subscription_level( $level_id );

	if ( is_null( $level_id ) || ! $level || is_level_deleted( $level_id ) ) {
		$content = '<p>' . __( 'Please', 'leaky-paywall' ) . ' <a href="' . get_page_link( $settings['page_for_subscription'] ) . '">' . __( 'go to the subscribe page', 'leaky-paywall' ) . '</a> ' . __( 'to choose a subscription level.', 'leaky-paywall' ) . '</p>';
		return $content;
	}

	// do not let free users register for the same level.
	if ( is_user_logged_in() && apply_filters( 'leaky_paywall_disable_same_free_level', __return_true() ) ) {

		$user_level_id = leaky_paywall_subscriber_current_level_id();

		if ( $level['price'] < 1 && $user_level_id === $level_id ) {
			$content = '<p>' . __( 'You are already subscribed to this level. Please', 'leaky-paywall' ) . ' <a href="' . get_page_link( $settings['page_for_subscription'] ) . '">' . __( 'go to the subscribe page', 'leaky-paywall' ) . '</a> ' . __( 'to choose a different subscription level.', 'leaky-paywall' ) . '</p>';
			return $content;
		}
	}

	global $blog_id;
	if ( is_multisite_premium() ) {
		$site = '_' . $blog_id;
	} else {
		$site = '';
	}

	$currency        = leaky_paywall_get_currency();
	$currencies      = leaky_paywall_supported_currencies();
	$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];

	$userdata = get_userdata( get_current_user_id() );
	if ( ! empty( $userdata ) ) {
		$email    = $userdata->user_email;
		$username = $userdata->user_login;
		$first    = $userdata->first_name;
		$last     = $userdata->last_name;
	} else {
		$email    = leaky_paywall_old_form_value( 'email_address', false );
		$username = leaky_paywall_old_form_value( 'username', false );
		$first    = leaky_paywall_old_form_value( 'first_name', false );
		$last     = leaky_paywall_old_form_value( 'last_name', false );
	}

	$gateways = leaky_paywall_get_enabled_payment_gateways( $level_id );
	$one_page_form = in_array( array( 'Stripe Checkout', 'Credit / Debit Card' ), $gateways ) ? false : true;

	if ( array_key_exists( 'stripe', $gateways ) || array_key_exists( 'stripe_checkout', $gateways ) ) {
		$one_page_form = false;
	} else {
		$one_page_form = true;
	}

	ob_start();

	// show any error messages after form submission.
	leaky_paywall_show_error_messages( 'register' );
	?>

	<div class="leaky-paywall-subscription-details-wrapper">

		<h3 class="leaky-paywall-subscription-details-title"><?php printf( esc_attr__( 'Order Summary', 'leaky-paywall' ) ); ?></h3>

		<ul class="leaky-paywall-subscription-details">
			<li class="leaky-paywall-subscription-details-subscription-name"><strong><?php printf( esc_attr__( 'Your Order:', 'leaky-paywall' ) ); ?></strong> <?php echo wp_kses_post( apply_filters( 'leaky_paywall_registration_level_name', $level['label'] ) ); ?></li>
			<li class="leaky-paywall-subscription-details-subscription-length"><strong><?php printf( esc_attr__( 'Subscription Length:', 'leaky-paywall' ) ); ?></strong> <?php echo 'unlimited' === $level['subscription_length_type'] ? esc_attr__( 'Forever', 'leaky-paywall' ) : esc_attr( $level['interval_count'] ) . ' ' . esc_attr( $level['interval'] ) . ( $level['interval_count'] > 1 ? 's' : '' ); ?></li>
			<li class="leaky-paywall-subscription-details-recurring"><strong><?php printf( esc_attr__( 'Recurring:', 'leaky-paywall' ) ); ?> </strong> <?php echo ! empty( $level['recurring'] ) && 'on' === $level['recurring'] ? esc_attr__( 'Yes', 'leaky-paywall' ) : esc_attr__( 'No', 'leaky-paywall' ); ?></li>
			<li class="leaky-paywall-subscription-details-content-access"><strong><?php printf( esc_attr__( 'Content Access:', 'leaky-paywall' ) ); ?></strong>

				<?php
				$content_access_description = '';
				$i                          = 0;

				if ( isset( $level['post_types'] ) && ! empty( $level['post_types'] && ! $level['registration_form_description'] ) ) {
					foreach ( $level['post_types'] as $type ) {
						if ( $i > 0 ) {
							$content_access_description .= ', ';
						}

						$post_type = get_post_type_object( $type['post_type'] );

						if ( null == $post_type ) {
							continue;
						}

						if ( 'unlimited' === $type['allowed'] ) {
							$content_access_description .= ucfirst( $type['allowed'] ) . ' ' . $post_type->labels->name;
						} else {
							$post_type_label             = '1' === $type['allowed_value'] ? $post_type->labels->singular_name : $post_type->labels->name;
							$content_access_description .= $type['allowed_value'] . ' ' . $post_type_label;
						}

						$i++;
					}
				} else {
					$content_access_description = stripslashes( $level['registration_form_description'] );
				}

				echo wp_kses_post( apply_filters( 'leaky_paywall_content_access_description', $content_access_description, $level, $level_id ) );
				?>

			</li>

		</ul>

		<p class="leaky-paywall-subscription-total">

			<?php $display_price = leaky_paywall_get_level_display_price( $level ); ?>

			<strong><?php echo esc_html__( 'Total:', 'leaky-paywall' ); ?></strong> <?php echo esc_html( apply_filters( 'leaky_paywall_your_subscription_total', $display_price, $level ) ); ?>
		</p>

	</div>

	<?php do_action( 'leaky_paywall_before_registration_form', $level ); ?>

	<?php
	if ( $level['price'] > 0 && !$one_page_form ) {
		?>
		<div class="leaky-paywall-form-steps">
			<div class="leaky-paywall-form-account-setup-step leaky-paywall-form-step active">
				<span class="step-number">1</span>
				<span class="step-title"><?php esc_html_e( 'Account Setup', 'leaky-paywall' ); ?></span>
			</div>
			<div class="leaky-paywall-form-payment-setup-step leaky-paywall-form-step">
				<span class="step-number">2</span>
				<span class="step-title"><?php esc_html_e( 'Payment', 'leaky-paywall' ); ?></span>
			</div>
		</div>
		<?php
	}
	?>



	<form action="" method="POST" name="payment-form" id="leaky-paywall-payment-form" class="leaky-paywall-payment-form">
		<span class="payment-errors"></span>

		<div id="leaky-paywall-registration-errors"></div>

		<div class="leaky-paywall-registration-user-container">

			<?php do_action( 'leaky_paywall_before_registration_form_user_fields', $level ); ?>

			<div class="leaky-paywall-user-fields">

				<h3><?php printf( esc_attr__( 'Your Details', 'leaky-paywall' ) ); ?></h3>

				<p class="form-row first-name">
					<label for="first_name"><?php printf( esc_attr__( 'First Name', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
					<input type="text" size="20" name="first_name" required value="<?php echo esc_attr( $first ); ?>" />
				</p>

				<p class="form-row last-name">
					<label for="last_name"><?php printf( esc_attr__( 'Last Name', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
					<input type="text" size="20" name="last_name" required value="<?php echo esc_attr( $last ); ?>" />
				</p>

				<p class="form-row email-address">
					<label for="email_address"><?php printf( esc_attr__( 'Email Address', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
					<input type="email" size="20" id="email_address" name="email_address" required value="<?php echo esc_attr( $email ); ?>" <?php echo ! empty( $email ) && ! empty( $userdata ) ? 'disabled="disabled"' : ''; ?> />
				</p>

			</div>

			<?php do_action( 'leaky_paywall_before_registration_form_account_fields', $level ); ?>

			<div class="leaky-paywall-account-fields">

				<h3><?php printf( esc_attr__( 'Account Details', 'leaky-paywall' ) ); ?></h3>

				<?php
				if ( 'off' === $settings['remove_username_field'] ) {
					?>
					<p class="form-row username">
						<label for="username"><?php printf( esc_attr__( 'Username', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
						<input type="text" size="20" name="username" id="username" required value="<?php echo esc_attr( $username ); ?>" <?php echo ! empty( $username ) && ! empty( $userdata ) ? 'disabled="disabled"' : ''; ?> />
					</p>
					<?php
				}
				?>



				<?php if ( ! is_user_logged_in() ) { ?>

					<p class="form-row password">
						<label for="password"><?php printf( esc_attr__( 'Password', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
						<input type="password" size="20" id="password" required name="password" />
					</p>

					<p class="form-row confirm-password">
						<label for="confirm_password"><?php printf( esc_attr__( 'Confirm Password', 'leaky-paywall' ) ); ?> <i class="required">*</i></label>
						<input type="password" size="20" id="confirm_password" required name="confirm_password" />
					</p>

				<?php } ?>

			</div>

			<?php do_action( 'leaky_paywall_after_password_registration_field', $level_id, $level ); ?>

			<?php
			if ( 0 != $level['price'] && !$one_page_form ) {
				?>
				<p>
					<button id="leaky-paywall-registration-next" type="button"><?php esc_html_e( 'Next', 'leaky-paywall' ); ?></button>
				</p>
				<?php
			}
			?>


		</div> <!-- leaky-paywall-registration-user-container -->

		<div class="leaky-paywall-registration-payment-container">

			<?php

			if ( $gateways && 0 != $level['price'] ) {

				foreach ( $gateways as $key => $gateway ) {

					echo '<input type="hidden" name="gateway" value="' . esc_attr( $key ) . '" />';
				}
			} else {
				echo '<input type="hidden" name="gateway" value="free_registration" />';
			}

			?>

			<?php
			if ( $level['price'] > 0 ) {
				$total_price = str_replace( ',', '', number_format( $level['price'], 2 ) );
			} else {
				$total_price = 0;
			}

			if ( $total_price > 0 ) {
				?>
				<h3><?php printf( esc_attr__( 'Payment Information', 'leaky-paywall' ) ); ?></h3>
			<?php } ?>

			<?php
			if ( leaky_paywall_get_current_mode() === 'test' ) {
				?>
				<div class="leaky-paywall-test-mode-wrapper">
					<p class="leaky-paywall-test-mode-text">The site is currently in test mode.</p>
				<?php
			}
			?>

				<?php do_action( 'leaky_paywall_before_registration_submit_field', $gateways, $level_id ); ?>

				<?php
				if ( leaky_paywall_get_current_mode() === 'test' ) {
					?>
				</div>
			<?php } ?>

			<div class="leaky-paywall-checkout-button">
				<button id="leaky-paywall-submit" type="submit"><?php echo esc_html( leaky_paywall_get_registration_checkout_button_text() ); ?></button>
			</div>


		</div> <!-- .leaky-paywall-registration-payment-container -->

		<input type="hidden" name="level_price" value="<?php echo esc_attr( $total_price ); ?>" />
		<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>" />
		<input type="hidden" name="description" value="<?php echo esc_attr( $level['label'] ); ?>" />
		<input type="hidden" name="level_id" id="level-id" value="<?php echo esc_attr( $level_id ); ?>" />
		<input type="hidden" name="interval" value="<?php echo esc_attr( $level['interval'] ); ?>" />
		<input type="hidden" name="interval_count" value="<?php echo esc_attr( $level['interval_count'] ); ?>" />
		<input type="hidden" name="recurring" value="<?php echo empty( $level['recurring'] ) ? '' : esc_attr( $level['recurring'] ); ?>" />
		<input type="hidden" name="site" value="<?php echo esc_attr( $site ); ?>" />
		<input type="hidden" name="idem_key" value="<?php echo esc_attr( uniqid() ); ?>" />

		<input type="hidden" name="leaky_paywall_register_nonce" value="<?php echo esc_attr( wp_create_nonce( 'leaky-paywall-register-nonce' ) ); ?>" />

	</form>

	<?php
	if ( 0 != $level['price'] && !$one_page_form ) {
		?>
		<style>
			.leaky-paywall-registration-payment-container {
				display: none;
			}
		</style>
		<?php
	}
	?>

	<style>
		#leaky-paywall-registration-errors {
			display: none;
			padding: .75rem 1.25rem;
			margin: 1rem 0;
			border-radius: .25rem;
			color: #772b35;
			background: #fadddd;
			border: 1px solid #f8cfcf;
		}

		#leaky-paywall-registration-errors p {
			margin: 0;
			font-size: .75em;
		}
	</style>

	<?php do_action( 'leaky_paywall_after_registration_form', $gateways ); ?>

	<?php

	$content = ob_get_contents();
	ob_end_clean();

	return $content;
}
add_shortcode( 'leaky_paywall_register_form', 'do_leaky_paywall_register_form' );

/**
 * Shortcode to show/hide content to an active user, optionally filtered by a comma separated list of level ids
 *
 * @since 4.14.6
 *
 * @param array  $atts Shortcode attributes.
 * @param string $content The content.
 */
function do_leaky_paywall_subscriber_shortcode( $atts, $content = null ) {
	$a = shortcode_atts(
		array(
			'levels'  => '',
			'message' => '',
		),
		$atts
	);

	if ( ! is_user_logged_in() ) {
		$content = '';
	}

	$user     = wp_get_current_user();
	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();

	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

	if ( ! is_numeric( $level_id ) ) {
		$content = '';
	}

	if ( ! leaky_paywall_user_has_access( $user ) ) {
		$content = '';
	}

	if ( ! empty( $a['levels'] ) ) {

		$match            = false;
		$shortcode_levels = explode( ',', $a['levels'] );

		foreach ( $shortcode_levels as $level ) {
			if ( $level === $level_id ) {
				$match = true;
				break;
			}
		}

		if ( ! $match ) {
			$content = '';
		}
	}

	if ( ! empty( $a['message'] ) && ! $content ) {
		$content = '<p>' . $a['message'] . '</p>';
	}

	return do_shortcode( $content );
}
add_shortcode( 'leaky_paywall_subscriber', 'do_leaky_paywall_subscriber_shortcode' );


/**
 * Shortcode to show content to a user who isn't an active subscriber
 *
 * @param array  $atts Shortcode attributes.
 * @param string $content The content.
 * @since 4.14.6
 *
 * @return string new content
 */
function do_leaky_paywall_not_subscriber_shortcode( $atts, $content = null ) {
	$a = shortcode_atts(
		array(
			'levels'  => '',
			'message' => '',
		),
		$atts
	);

	if ( ! is_user_logged_in() ) {
		return $content;
	}

	$user     = wp_get_current_user();
	$settings = get_leaky_paywall_settings();
	$mode     = leaky_paywall_get_current_mode();
	$site     = leaky_paywall_get_current_site();

	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );

	if ( ! is_numeric( $level_id ) ) {
		return $content;
	}

	if ( ! leaky_paywall_user_has_access( $user ) ) {
		return $content;
	}

}

add_shortcode( 'leaky_paywall_not_subscriber', 'do_leaky_paywall_not_subscriber_shortcode' );

/**
 * Profile Editor Shortcode
 *
 * Outputs the Leaky Paywall Profile Editor to allow users to amend their details from the
 * front-end.
 *
 * @since 4.14.5
 *
 * @param array  $atts Shortcode attributes.
 * @param string $content The content.
 * @return string Output generated from the profile editor
 */
function leaky_paywall_account_shortcode( $atts, $content = null ) {
	ob_start();

	if ( is_user_logged_in() ) {
		leaky_paywall_get_template( 'shortcode-account.php' );
	} else {
		echo do_shortcode( '[leaky_paywall_login]' );
	}

	$display = ob_get_clean();

	return $display;
}
add_shortcode( 'leaky_paywall_account', 'leaky_paywall_account_shortcode' );


/**
 * Process a user editing their profile
 *
 * @since 4.14.5
 * @throws Exception The exception message.
 */
function leaky_paywall_process_profile_edit() {
	if (
		! isset( $_POST['leaky_paywall_profile_edit_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['leaky_paywall_profile_edit_nonce'] ), 'leaky_paywall_profile_edit' )
	) {
		return;
	}

	$user = wp_get_current_user();

	try {

		$args = array(
			'ID'           => $user->ID,
			'user_login'   => $user->user_login,
			'display_name' => $user->display_name,
			'user_email'   => $user->user_email,
		);

		if ( isset( $_POST['displayname'] ) ) {
			$args['display_name'] = sanitize_text_field( wp_unslash( $_POST['displayname'] ) );
		}

		if ( isset( $_POST['firstname'] ) ) {
			$args['first_name'] = sanitize_text_field( wp_unslash( $_POST['firstname'] ) );
		}

		if ( isset( $_POST['lastname'] ) ) {
			$args['last_name'] = sanitize_text_field( wp_unslash( $_POST['lastname'] ) );
		}

		if ( isset( $_POST['email'] ) ) {
			if ( is_email( wp_unslash( $_POST['email'] ) ) ) {
				$args['user_email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
			} else {
				throw new Exception( __( 'Invalid email address.', 'leaky-paywall' ) );
			}
		}

		if ( ! empty( $_POST['password1'] ) && ! empty( $_POST['password2'] ) ) {
			if ( $_POST['password1'] === $_POST['password2'] ) {
				wp_set_password( sanitize_text_field( wp_unslash( $_POST['password1'] ) ), $user->ID );
			} else {
				throw new Exception( __( 'Passwords do not match.', 'leaky-paywall' ) );
			}
		}

		$user_id = wp_update_user( $args );

		if ( is_wp_error( $user_id ) ) {
			throw new Exception( $user_id->get_error_message() );
		} else {
			$user = get_userdata( $user_id );
			do_action( 'leaky_paywall_after_profile_changes_saved', $user_id, $args, $user );
		}
	} catch ( Exception $e ) {
		$results = '<div class="leaky_paywall_message error"><p class="error">' . $e->getMessage() . '</p></div>';
	}

	$referrer = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';

	wp_safe_redirect( home_url() . $referrer );
	exit();
}
add_action( 'init', 'leaky_paywall_process_profile_edit' );
