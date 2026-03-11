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

if ( isset( $_POST['lp_update_billing_address_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lp_update_billing_address_nonce'] ) ), 'lp_update_billing_address' ) && $subscriber_id ) {

	$billing_address = array(
		'name'        => isset( $_POST['lp_billing_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_name'] ) ) : '',
		'line1'       => isset( $_POST['lp_billing_line1'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_line1'] ) ) : '',
		'line2'       => isset( $_POST['lp_billing_line2'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_line2'] ) ) : '',
		'city'        => isset( $_POST['lp_billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_city'] ) ) : '',
		'state'       => isset( $_POST['lp_billing_state'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_state'] ) ) : '',
		'postal_code' => isset( $_POST['lp_billing_postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['lp_billing_postal_code'] ) ) : '',
		'country'     => isset( $_POST['lp_billing_country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['lp_billing_country'] ) ) ) : '',
	);

	update_user_meta( $user->ID, '_lp_billing_address', $billing_address );

	try {
		leaky_paywall_stripe_update_billing_address( $stripe, $subscriber_id, $billing_address );
		$update_billing_address_success = __( 'Your billing address has been updated.', 'leaky-paywall' );
	} catch ( \Stripe\Exception\ApiErrorException $e ) {
		$body                          = $e->getJsonBody();
		$update_billing_address_error  = $body['error']['message'];
	}
}

if ( isset( $_POST['lp_update_card_form_field'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lp_update_card_form_field'] ) ), 'lp_update_card_form' ) && isset( $_POST['stripeToken'] ) && $subscriber_id ) {

	$payment_status   = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_payment_status' . $site, true );
	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_level_id' . $site, true );
	$plan     = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_plan' . $site, true );
	$level    = get_leaky_paywall_subscription_level( $level_id );

	try {

		$cu         = $stripe->customers->retrieve( $subscriber_id, [], leaky_paywall_get_stripe_connect_params() ); // stored in your application
		$cu->source = sanitize_text_field( wp_unslash( $_POST['stripeToken'] ) ); // obtained with Checkout
		$cu->save();

		$update_card_success = __( 'Your card details have been updated!', 'leaky-paywall' );

		leaky_paywall_log( $user->user_email, 'credit card updated' );

		if ( strcasecmp( 'deactivated', $payment_status ) == 0 ) { // only runs if the user account is deactivated

			$subs = $stripe->subscriptions->all(
				array(
					'customer' => $subscriber_id,
					'status'   => 'all',
				),
				leaky_paywall_get_stripe_connect_params()
			);

			if ( ! empty( $subs->data ) ) {

				foreach ( $subs->data as $sub ) {

					// we are only checking against the subscribers current plan
					if ( $plan != $sub->items->data[0]->plan->id ) {
						continue;
					}

					if ( $sub->status == 'active' || $sub->status == 'past_due' || $sub->status == 'trialing' ) {

						// Update the existing subscription to use the new payment source.
						$stripe->subscriptions->update(
							$sub->id,
							array( 'default_source' => $cu->default_source ),
							leaky_paywall_get_stripe_connect_params()
						);

						leaky_paywall_log( $user->user_email, 'updated subscription payment source after card update' );
					} else {

						// only create a new subscription if the subscriber does not have a current subscription
						// such as expired or canceled
						$new_sub = \Stripe\Subscription::create(
							array(
								'customer' => $cu->id,
								'items'    => array( array( 'plan' => $plan ) ),
							),
							leaky_paywall_get_stripe_connect_params()
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

		if ( 'expired' === $payment_status ) {
			$status_name = __( 'Expired', 'leaky-paywall' );
		} elseif ( 'pending_cancel' === $payment_status ) {
			$status_name = __( 'Pending Cancel', 'leaky-paywall' );
		} elseif ( 'past_due' === $payment_status ) {
			$status_name = __( 'Past Due', 'leaky-paywall' );
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

			if ( 'canceled' == $payment_status || 'pending_cancel' == $payment_status ) {
				$expires_label = __( 'Ends on', 'leaky-paywall' );
			} else {
				$expires_label = __( 'Recurs on', 'leaky-paywall' );
			}
		}

		if ( 'Expired' == $status_name ) {
			$expires_label = __( 'Expired on', 'leaky-paywall' );
		}

		if ( 'past_due' === $payment_status ) {
			$expires_label = __( 'Recurs on', 'leaky-paywall' );
		}

		$paid       = leaky_paywall_has_user_paid( $user->user_email, $site );
		$expiration = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $lp_mode . '_expires' . $site, true );
		$cancel_url     = '';
		$cancel_text = '';

		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expiration ) {
			$cancel_text = '';
		} elseif ( 'past_due' === $payment_status ) {
			$cancel_text = sprintf( esc_attr__( 'Your recent payment failed. Please <a href="%s">update your payment method</a> to keep your subscription active.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) );
		} elseif ( 'pending_cancel' == $payment_status ) {
			$cancel_text = sprintf( esc_attr__( 'You have canceled your subscription, but your account will remain active until your expiration date. To reactivate your subscription, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) );
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

					do_action( 'leaky_paywall_after_profile_overview' );
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

				do_action('leaky_paywall_profile_your_payment_info_start');

				if ( 'stripe' === $payment_gateway && 'on' === $settings['stripe_billing_address'] ) :

					$billing_address = get_user_meta( $user->ID, '_lp_billing_address', true );
					if ( ! is_array( $billing_address ) ) {
						$billing_address = array();
					}
					$billing_address = wp_parse_args( $billing_address, array(
						'name'        => '',
						'line1'       => '',
						'line2'       => '',
						'city'        => '',
						'state'       => '',
						'postal_code' => '',
						'country'     => '',
					) );

					?>
					<h2 class="leaky-paywall-account-page-title"><?php esc_html_e( 'Billing Address', 'leaky-paywall' ); ?></h2>

					<?php if ( ! empty( $update_billing_address_success ) ) : ?>
						<div class="leaky_paywall_message success"><p><?php echo esc_html( $update_billing_address_success ); ?></p></div>
					<?php endif; ?>
					<?php if ( ! empty( $update_billing_address_error ) ) : ?>
						<div class="leaky_paywall_message error"><p><?php echo esc_html( $update_billing_address_error ); ?></p></div>
					<?php endif; ?>

					<form id="leaky-paywall-billing-address-form" action="<?php the_permalink(); ?>?action=payment_info" method="POST">
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-name"><?php esc_html_e( 'Name on Card', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-name" name="lp_billing_name" value="<?php echo esc_attr( $billing_address['name'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-line1"><?php esc_html_e( 'Address Line 1', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-line1" name="lp_billing_line1" value="<?php echo esc_attr( $billing_address['line1'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-line2"><?php esc_html_e( 'Address Line 2', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-line2" name="lp_billing_line2" value="<?php echo esc_attr( $billing_address['line2'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-city"><?php esc_html_e( 'City', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-city" name="lp_billing_city" value="<?php echo esc_attr( $billing_address['city'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-state"><?php esc_html_e( 'State / Province', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-state" name="lp_billing_state" value="<?php echo esc_attr( $billing_address['state'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-postal-code"><?php esc_html_e( 'Postal Code', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-postal-code" name="lp_billing_postal_code" value="<?php echo esc_attr( $billing_address['postal_code'] ); ?>" />
						</p>
						<p class="form-row">
							<label class="leaky-paywall-field-label" for="lp-billing-country"><?php esc_html_e( 'Country (2-letter code)', 'leaky-paywall' ); ?></label>
							<input type="text" class="leaky-paywall-field-input" id="lp-billing-country" name="lp_billing_country" maxlength="2" value="<?php echo esc_attr( $billing_address['country'] ); ?>" placeholder="US" />
						</p>
						<?php wp_nonce_field( 'lp_update_billing_address', 'lp_update_billing_address_nonce' ); ?>
						<p class="form-row">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update Billing Address', 'leaky-paywall' ); ?>" />
						</p>
					</form>

				<?php endif;

				do_action('leaky_paywall_profile_your_subscription_end');

			default:
				break;
		}


		do_action('leaky_paywall_profile_your_account_end');

	?>



	</div>
</div>
