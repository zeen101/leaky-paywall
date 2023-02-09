<?php
/**
 * Leaky Paywall My Account template.
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't allow direct access.
}

$user          = wp_get_current_user();
$page_action   = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'overview';
$settings      = get_leaky_paywall_settings();
$lp_mode       = leaky_paywall_get_current_mode();
$site          = leaky_paywall_get_current_site();
$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_subscriber_id' . $site, true );
$plan          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_plan' . $site, true );

$stripe = leaky_paywall_initialize_stripe_api();

if ( isset( $_POST['lp_update_card_form_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lp_update_card_form_field'] ) ), 'lp_update_card_form' ) && isset( $_POST['stripeToken'] ) && $subscriber_id ) {

	$payment_status   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_status' . $site, true );
	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_level_id' . $site, true );
	$plan     = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_plan' . $site, true );
	$level    = get_leaky_paywall_subscription_level( $level_id );

	try {

		$cu         = $stripe->customers->retrieve( $subscriber_id ); // stored in your application
		$cu->source = sanitize_text_field( wp_unslash( $_POST['stripeToken'] ) ); // obtained with Checkout
		$cu->save();

		$update_card_success = __( 'Your card details have been updated!', 'leaky-paywall' );

		leaky_paywall_log( $user->user_email, 'credit card updated' );

		if ( strcasecmp( 'deactivated', $payment_status ) == 0 ) { // only runs if the user account is deactivated

			$subs = $stripe->subscriptions->all(
				array(
					'customer' => $subscriber_id,
					'status'   => 'all',
				)
			);

			if ( ! empty( $subs->data ) ) {

				foreach ( $subs->data as $sub ) {

					// we are only checking against the subscribers current plan
					if ( $plan != $sub->items->data[0]->plan->id ) {
						continue;
					}

					if ( $sub->status == 'active' || $sub->status == 'past_due' || $sub->status == 'trialing' ) {

						leaky_paywall_log( $user->user_email, 'has a subscription, did not create a new subscription after card update' );
					} else {

						// only create a new subscription if the subscriber does not have a current subscription
						// such as expired or canceled
						$new_sub = \Stripe\Subscription::create(
							array(
								'customer' => $cu->id,
								'items'    => array( array( 'plan' => $plan ) ),
							)
						);

						leaky_paywall_log( $user->user_email, 'created new subscription after card update' );
					}
				}
			}

			$update_card_success .= __( ' Your subscription has been restarted!', 'leaky-paywall' );
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

?>
<div id="leaky-paywall-account-wrapper">

	<div class="leaky-paywall-account-navigation-wrapper">
		<ul class="leaky-paywall-account-menu">
			<li>
				<a class="<?php echo 'overview' == $page_action ? 'active' : ''; ?>" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Account Overview', 'leaky-paywall' ); ?></a>
			</li>
			<li>
				<a class="<?php echo 'edit_profile' == $page_action ? 'active' : ''; ?>" href="<?php the_permalink(); ?>?action=edit_profile"><?php esc_html_e( 'Edit Profile', 'leaky-paywall' ); ?></a>
			</li>

			<?php
			if ( ! leaky_paywall_user_can_bypass_paywall_by_role( $user ) && $plan ) {
				?>
				<li>
					<a class="<?php echo 'payment_info' == $page_action ? 'active' : ''; ?>" href="<?php the_permalink(); ?>?action=payment_info"><?php esc_html_e( 'Payment Info', 'leaky-paywall' ); ?></a>
				</li>
				<?php
			}
			?>
			<li>
				<a href="<?php echo esc_url( wp_logout_url( '/' ) ); ?>"><?php esc_html_e( 'Logout', 'leaky-paywall' ); ?></a>
			</li>
		</ul>
	</div>

	<div class="leaky-paywall-account-details-wrapper">

		<?php

		$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_gateway' . $site, true );
		$payment_status          = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_status' . $site, true );
		$expires         = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_expires' . $site, true );
		$plan            = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_plan' . $site, true );
		$level_id        = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_level_id' . $site, true );
		$level           = get_leaky_paywall_subscription_level( $level_id );

		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
			$expires = __( 'Never', 'leaky-paywall' );
		} else {
			$expires = mysql2date( 'F j, Y', $expires );
		}

		if ( strcasecmp( 'active', $payment_status ) == 0 && ! leaky_paywall_user_has_access( $user ) ) {
			$status_name = __( 'Expired', 'leaky-paywall' );
		} else {
			$status_name = ucfirst( $payment_status );
		}


		if ( 'stripe' == $payment_gateway ) {
			$profile_payment = __( 'Credit Card', 'leaky-paywall' );
		} else {
			$profile_payment = leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway );
		}

		$expires_label = __( 'Ends on', 'leaky-paywall' );

		if ( ! empty( $plan ) && 'Canceled' !== $plan && 'Never' !== $expires ) {

			if ( 'canceled' == $payment_status ) {
				$expires_label = __( 'Ends on', 'leaky-paywall' );
			} else {
				$expires_label = __( 'Recurs on', 'leaky-paywall' );
			}
		}

		if ( 'Expired' == $status_name ) {
			$expires_label = __( 'Expired on', 'leaky-paywall' );
		}

		$paid       = leaky_paywall_has_user_paid( $user->user_email, $site );
		$expiration = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_expires' . $site, true );
		$cancel_url     = '';
		$cancel_text = '';

		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expiration ) {
			$cancel_text = '';
		} elseif ( strcasecmp( 'active', $payment_status ) == 0 && $plan && 'Canceled' !== $plan ) {
			$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_subscriber_id' . $site, true );
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_gateway' . $site, true );

			if ( 'free_registration' == $payment_gateway ) {
				$cancel_text = '';
			} else {
				$cancel_text = __( 'Cancel your subscription', 'leaky-paywall' );
				$cancel_url = '?lp_cancel=request&cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id;
			}
		} elseif ( ! empty( $plan ) && 'Canceled' == $plan ) {
			$cancel_text = sprintf( esc_attr__( 'You have canceled your subscription, but your account will remain active until your expiration date. To reactivate your subscription, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) );
		}

		switch ( $page_action ) {
			case 'overview':
				?>
				<h2 class="leaky-paywall-account-page-title"><?php esc_html_e( 'Account overview', 'leaky-paywall' ); ?></h2>

				<h3 class="leaky-paywall-account-section-title"><?php esc_html_e( 'Profile', 'leaky-paywall' ); ?></h3>

				<table class="leaky-paywall-account-table leaky-paywall-account-profile-table">
					<tbody>
						<tr>
							<td class="profile-table-label"><?php esc_html_e( 'Name', 'leaky-paywall' ); ?></td>
							<td class="profile-table-value"><?php echo $user->first_name ? esc_html( $user->first_name ) . ' ' . esc_html( $user->last_name ) : esc_html( $user->display_name ); ?></td>
						</tr>
						<tr>
							<td class="profile-table-label"><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></td>
							<td class="profile-table-value"><?php echo esc_html( $user->user_email ); ?></td>
						</tr>
					</tbody>
				</table>

				<?php
				if ( leaky_paywall_user_can_bypass_paywall_by_role( $user ) ) {
					echo '<h3 class="leaky-paywall-account-section-title">' . esc_html__( 'Your user role can see all content.', 'leaky-paywall' ) . '</h3>';
				} else {
					?>
					<h3 class="leaky-paywall-account-section-title your-plan-title"><?php esc_html_e( 'Your plan', 'leaky-paywall' ); ?></h3>

					<table class="leaky-paywall-account-table leaky-paywall-account-plan-table">
						<tbody>
							<tr>
								<td class="profile-table-label"><?php esc_html_e( 'Name', 'leaky-paywall' ); ?></td>
								<td class="profile-table-value"><?php echo esc_html( $level['label'] ); ?></td>
							</tr>
							<tr>
								<td class="profile-table-label"><?php esc_html_e( 'Payment Status', 'leaky-paywall' ); ?></td>
								<td class="profile-table-value"><?php echo esc_html( $status_name ); ?></td>
							</tr>
							<tr>
								<td class="profile-table-label"><?php echo esc_html( $expires_label ); ?></td>
								<td class="profile-table-value"><?php echo esc_html( $expires ); ?></td>
							</tr>
							<tr>
								<td class="profile-table-label"><?php esc_html_e( 'Has Access', 'leaky-paywall' ); ?></td>
								<td class="profile-table-value"><?php echo leaky_paywall_user_has_access() ? esc_html__( 'Yes', 'leaky-paywall' ) : esc_html__( 'No', 'leaky-paywall' ); ?></td>
							</tr>
							<tr>
								<td class="profile-table-label"><?php esc_html_e( 'Payment Method', 'leaky-paywall' ); ?></td>
								<td class="profile-table-value"><?php echo esc_html( $profile_payment ); ?></td>
							</tr>
						</tbody>
					</table>
					<?php
				}

				break;

			case 'edit_profile':
				?>
				<h2 class="leaky-paywall-account-page-title"><?php esc_html_e( 'Edit your profile', 'leaky-paywall' ); ?></h2>

				<form id="leaky-paywall-account-edit-profile-form" action="<?php the_permalink(); ?>" method="POST">
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-display-name">
							<?php esc_attr_e( 'Display Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="<?php echo esc_attr( $user->display_name ); ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-first-name">
							<?php esc_attr_e( 'First Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-first-name" name="firstname" value="<?php echo esc_attr( $user->first_name ); ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-last-name">
							<?php esc_attr_e( 'Last Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-last-name" name="lastname" value="<?php echo esc_attr( $user->last_name ); ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-email">
							<?php esc_attr_e( 'Email', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-password1">
							<?php esc_attr_e( 'New Password', 'leaky-paywall' ); ?>
						</label>
						<input type="password" class="leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-password2">
							<?php esc_attr_e( 'New Password Confirm', 'leaky-paywall' ); ?>
						</label>
						<input type="password" class="leaky-paywall-field-input" id="leaky-paywall-password2" name="password2" value="" />
					</p>
					<?php wp_nonce_field( 'leaky_paywall_profile_edit', 'leaky_paywall_profile_edit_nonce' ); ?>
					<p class="form-row">
						<input type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Profile', 'leaky-paywall' ); ?>" />
					</p>
				</form>

				<?php do_action( 'leaky_paywall_after_edit_profile' ); ?>

				<?php
				break;

			case 'payment_info':
				$payment_form    = false;
				$def_payment_method = '';
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_gateway' . $site, true );
				$subscriber_id   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_subscriber_id' . $site, true );
				$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
				$secret_key      = ( 'test' === $lp_mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];


				if ( $subscriber_id && 'stripe' == $payment_gateway ) {

					$payment_form = true;

					try {
						$cu = $stripe->customers->retrieve(
							$subscriber_id,
						);

						if ( $cu->invoice_settings->default_payment_method ) {
							$def_payment_method = $stripe->paymentMethods->retrieve( $cu->invoice_settings->default_payment_method );
						} else if ( $cu->default_source ) {
							$def_payment_method = $stripe->paymentMethods->retrieve( $cu->default_source );
						}

					} catch (\Throwable $th) {
						$cu = '';
					}

					if ( isset( $update_card_error ) ) {
						echo '<div class="leaky_paywall_message error"><p>' . esc_html( $update_card_error ) . '</p></div>';
					} elseif ( isset( $update_card_success ) ) {
						echo '<div class="leaky_paywall_message success"><p>' . esc_html( $update_card_success ) . '</p></div>';
					}

					if ( strcasecmp( 'deactivated', $payment_status ) == 0 ) {
						$data_label = 'Update Credit Card Details & Restart Subscription';
					} else {
						$data_label = 'Update Credit Card Details';
					}

				}

				?>
				<h2 class="leaky-paywall-account-page-title"><?php esc_html_e( 'Your payment information', 'leaky-paywall' ); ?></h2>

				<?php
					if ( $def_payment_method ) {
						echo '<h3 class="leaky-paywall-account-section-title">' . esc_html__( 'Payment Method', 'leaky-paywall' ) . '</h3><p>' . esc_html( strtoupper( $def_payment_method->card->brand ) ) . ' ending in ' . esc_html( $def_payment_method->card->last4 ) . ' that expires ' . esc_html( $def_payment_method->card->exp_month ) . '/' . esc_html( $def_payment_method->card->exp_year ) . '</p>';
					}
				?>

				<?php

				if ( $payment_form ) {

					if ( 'on' == $settings['stripe_customer_portal'] ) {
						echo '<form method="POST" action="">';
						echo '<button type="submit">Manage billing</button>';
						wp_nonce_field( 'stripe_customer_portal_submit', 'stripe_customer_portal_field' );
						echo '</form>';
					} else {
						?>
						<form action="" method="POST">
							  <script
							  src="https://checkout.stripe.com/checkout.js" class="stripe-button"
							  data-key="<?php echo esc_attr( $publishable_key ); ?>"
							  data-name="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
							  data-panel-label="<?php echo esc_attr( $data_label ); ?>"
							  data-label="<?php echo esc_attr( $data_label ); ?>"
							  data-allow-remember-me=false
							  data-email="<?php echo esc_attr( $user->user_email ); ?>"
							  data-locale="auto">
							  </script>
							  <?php wp_nonce_field( 'lp_update_card_form', 'lp_update_card_form_field' ); ?>
						</form>
						<?php
					}

				}

				if ( $cancel_text ) {
					echo '<h3 class="leaky-paywall-account-section-title">' . esc_html__( 'Manage', 'leaky-paywall' ) . '</h3>';
					if ( $cancel_url ) {
						echo '<p class="leaky-paywall-cancel-link"><a href="' . esc_url( apply_filters( 'leaky_paywall_cancel_link', $cancel_url ) ) . '">' . esc_html( $cancel_text ) . '</a></p>';
					} else {
						echo '<p class="leaky-paywall-cancel-link">' . esc_html( $cancel_text ) . '</p>';
					}

				}

			default:
				break;
		}

		?>



	</div>
</div>
