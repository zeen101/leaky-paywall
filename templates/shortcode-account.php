<?php 

/**
 * Redeem gift card template.
 *
 * This template can be overriden by copying this file to your-theme/woocommerce-plugin-templates/redeem-gift-card.php
 *
 * @package 	WooCommerce Plugin Templates/Templates
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Don't allow direct access

$user = wp_get_current_user();
$action = isset( $_GET['action'] ) ? $_GET['action'] : 'overview';
$settings = get_leaky_paywall_settings();
$mode = leaky_paywall_get_current_mode();
$site = leaky_paywall_get_current_site();
$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );

if ( isset( $_POST['stripeToken'] ) && $subscriber_id ) {

	$secret_key = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
	$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
	$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
	$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
	$level = get_leaky_paywall_subscription_level( $level_id );

	\Stripe\Stripe::setApiKey( $secret_key );

	try {

	    $cu = \Stripe\Customer::retrieve($subscriber_id); // stored in your application
	    $cu->source = $_POST['stripeToken']; // obtained with Checkout
	    $cu->save();

	    $update_card_success = __( 'Your card details have been updated!', 'leaky-paywall' );

	    leaky_paywall_log( $user->user_email, 'credit card updated');

	    if ( strcasecmp('deactivated', $status) == 0 ) { // only runs if the user account is deactivated

	    	$subs = \Stripe\Subscription::all(['customer' => $subscriber_id, 'status' => 'all']);

		    if ( !empty( $subs->data ) ) {

		    	foreach( $subs->data as $sub ) {

		    		// we are only checking against the subscribers current plan
		    		if ( $plan != $sub->items->data[0]->plan->id ) {
		    			continue;
		    		}

		    		if ( $sub->status == 'active' || $sub->status == 'past_due' || $sub->status == 'trialing' ) {
		    			
		    			leaky_paywall_log( $user->user_email, 'has a subscription, did not create a new subscription after card update');

		    		} else {
		    				
		    			// only create a new subscription if the subscriber does not have a current subscription
		    			// such as expired or canceled
		    			$new_sub = \Stripe\Subscription::create([
		    			  'customer' => $cu->id,
		    			  'items' => [['plan' => $plan]],
		    			]);

		    			leaky_paywall_log( $user->user_email, 'created new subscription after card update');

		    		}
		    	}
		    }
			
	    	$update_card_success .= __( ' Your subscription has been restarted!', 'leaky-paywall' );

	    }

	  }
	  catch(\Stripe\Error\Card $e) {

	    $body = $e->getJsonBody();
	    $err  = $body['error'];
	    $update_card_error = $err['message'];

	  } catch(\Stripe\Error\InvalidRequest $e) {
	  	$body = $e->getJsonBody();
	  	$err  = $body['error'];
	  	$update_card_error = $err['message'];
	}

}

?>
<div id="leaky-paywall-account-wrapper">
	
	<div class="leaky-paywall-account-navigation-wrapper">
		<ul class="leaky-paywall-account-menu">
			<li>
				<a class="<?php echo $action == 'overview' ? 'active' : ''; ?>" href="<?php the_permalink(); ?>"><?php _e( 'Account Overview', 'leaky-paywall' ); ?></a>
			</li>
			<li>
				<a class="<?php echo $action == 'edit_profile' ? 'active' : ''; ?>" href="<?php the_permalink(); ?>?action=edit_profile"><?php _e( 'Edit Profile', 'leaky-paywall' ); ?></a>
			</li>

			<?php if ( !leaky_paywall_user_can_bypass_paywall_by_role( $user ) && $plan ) {
				?>
				<li>
					<a class="<?php echo $action == 'payment_info' ? 'active' : ''; ?>" href="<?php the_permalink(); ?>?action=payment_info"><?php _e( 'Payment Info', 'leaky-paywall' ); ?></a>
				</li>
				<?php 
			} ?>
			<li>
				<a href="<?php echo wp_logout_url('/'); ?>"><?php _e( 'Logout', 'leaky-paywall' ); ?></a>
			</li>
		</ul>
	</div>

	<div class="leaky-paywall-account-details-wrapper">

	<?php 

		$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
		$status = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_status' . $site, true );
		$expires = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
		$plan = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_plan' . $site, true );
		$level_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_level_id' . $site, true );
		$level = get_leaky_paywall_subscription_level( $level_id );

		if ( empty( $expires ) || '0000-00-00 00:00:00' === $expires ) {
			$expires = __( 'Never', 'leaky-paywall' );
		} else {
			$expires = mysql2date( 'F j, Y', $expires );
		}

		if ( strcasecmp('active', $status) == 0 && !leaky_paywall_user_has_access( $user ) ) {
			$status_name = 'Expired';
		} else {
			$status_name = ucfirst( $status );
		}


		if ( 'stripe' == $payment_gateway ) {
			$profile_payment = 'Credit Card';
		} else {
			$profile_payment = leaky_paywall_translate_payment_gateway_slug_to_name( $payment_gateway );
		}

		$expires_label = 'Ends on';

		if ( !empty( $plan ) && 'Canceled' !== $plan && 'Never' !== $expires ) {
			
			if ( $status == 'canceled' ) {
				$expires_label = 'Ends on';
			} else {
				$expires_label = 'Recurs on';
			}
			
		}

		if ( $status_name == 'Expired' ) {
			$expires_label = 'Expired on';
		}

		$paid = leaky_paywall_has_user_paid( $user->user_email, $site );
		$expiration = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_expires' . $site, true );
		$cancel = '';

		if ( empty( $expires) || '0000-00-00 00:00:00' === $expiration) {
			$cancel = '';
		} else if ( strcasecmp('active', $status) == 0 && $plan && 'Canceled' !== $plan ) {
			$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
			$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );

			if ( $payment_gateway == 'free_registration' ) {
				$cancel = '';
			} else {
				$cancel = sprintf( __( '<a href="%s">Cancel your subscription</a>', 'leaky-paywall' ), '?lp_cancel=request&cancel&payment_gateway=' . $payment_gateway . '&subscriber_id=' . $subscriber_id );
			}
			
		} else if ( !empty( $plan ) && 'Canceled' == $plan ) {
			$cancel .= '<p>' . sprintf( __( 'You have canceled your subscription, but your account will remain active until your expiration date. To reactivate your subscription, please visit our <a href="%s">Subscription page</a>.', 'leaky-paywall' ), get_page_link( $settings['page_for_subscription'] ) ) . '</p>';
		}

		switch ( $action ) {
			case 'overview':
				?>
				<h2 class="leaky-paywall-account-page-title"><?php _e( 'Account overview', 'leaky-paywall' ); ?></h2>
				
				<h3 class="leaky-paywall-account-section-title"><?php _e( 'Profile', 'leaky-paywall' ); ?></h3>

				<table class="leaky-paywall-account-table leaky-paywall-account-profile-table">
					<tbody>
						<tr>
							<td class="profile-table-label"><?php _e( 'Name', 'leaky-paywall' ); ?></td>
							<td class="profile-table-value"><?php echo $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->display_name; ?></td>
						</tr>
						<tr>
							<td class="profile-table-label"><?php _e( 'Email', 'leaky-paywall' ); ?></td>
							<td class="profile-table-value"><?php echo $user->user_email; ?></td>
						</tr>
					</tbody>
				</table>

				<?php
					if ( leaky_paywall_user_can_bypass_paywall_by_role( $user ) ) {
						echo '<h3 class="leaky-paywall-account-section-title">Your user role can see all content.</h3>';
					} else {
						?>
						<h3 class="leaky-paywall-account-section-title your-plan-title"><?php _e( 'Your plan', 'leaky-paywall' ); ?></h3>

						<table class="leaky-paywall-account-table leaky-paywall-account-plan-table">
							<tbody>
								<tr>
									<td class="profile-table-label"><?php _e( 'Name', 'leaky-paywall' ); ?></td>
									<td class="profile-table-value"><?php echo $level['label']; ?></td>
								</tr>
								<tr>
									<td class="profile-table-label"><?php _e( 'Status', 'leaky-paywall' ); ?></td>
									<td class="profile-table-value"><?php echo esc_attr( $status_name ); ?></td>
								</tr>
								<tr>
									<td class="profile-table-label"><?php echo $expires_label; ?></td>
									<td class="profile-table-value"><?php echo $expires; ?></td>
								</tr>
								<tr>
									<td class="profile-table-label"><?php _e( 'Has Access', 'leaky-paywall' ); ?></td>
									<td class="profile-table-value"><?php echo leaky_paywall_user_has_access() ? 'Yes' : 'No'; ?></td>
								</tr>
								<tr>
									<td class="profile-table-label"><?php _e( 'Payment Method', 'leaky-paywall' ); ?></td>
									<td class="profile-table-value"><?php echo $profile_payment; ?></td>
								</tr>
							</tbody>
						</table>
						<?php 
					} 
	
				break;

			case 'edit_profile':
				?>
				<h2 class="leaky-paywall-account-page-title"><?php _e( 'Edit your profile', 'leaky-paywall' ); ?></h2>
				
				<form id="leaky-paywall-account-edit-profile-form" action="<?php the_permalink(); ?>" method="POST">
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-display-name">
							<?php _e( 'Display Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-display-name" name="displayname" value="<?php echo $user->display_name; ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-first-name">
							<?php _e( 'First Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-first-name" name="firstname" value="<?php echo $user->first_name; ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-last-name">
							<?php _e( 'Last Name', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-last-name" name="lastname" value="<?php echo $user->last_name; ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-email">
							<?php _e( 'Email', 'leaky-paywall' ); ?>
						</label>
						<input type="text" class="leaky-paywall-field-input" id="leaky-paywall-email" name="email" value="<?php echo $user->user_email; ?>" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-password1">
							<?php _e( 'New Password', 'leaky-paywall' ); ?>
						</label>
						<input type="password" class="leaky-paywall-field-input" id="leaky-paywall-password1" name="password1" value="" />
					</p>
					<p class="form-row">
						<label class="leaky-paywall-field-label" for="leaky-paywall-password2">
							<?php _e( 'New Password Confirm', 'leaky-paywall' ); ?>
						</label>
						<input type="password" class="leaky-paywall-field-input" id="leaky-paywall-password2" name="password2" value="" />
					</p>
					<?php wp_nonce_field( 'leaky_paywall_profile_edit', 'leaky_paywall_profile_edit_nonce' ); ?>
					<p class="form-row">
						<input type="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Profile', 'leaky-paywall' ); ?>" />
					</p>
				</form>

				<?php 
				break;
			
			case 'payment_info':

				$payment_form = '';
				$payment_gateway = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_payment_gateway' . $site, true );
				$subscriber_id = get_user_meta( $user->ID, '_issuem_leaky_paywall_' . $mode . '_subscriber_id' . $site, true );
				$publishable_key = 'on' === $settings['test_mode'] ? $settings['test_publishable_key'] : $settings['live_publishable_key'];
				$secret_key = ( 'test' === $mode ) ? $settings['test_secret_key'] : $settings['live_secret_key'];
				

				if ( $subscriber_id && 'stripe' == $payment_gateway ) {
					\Stripe\Stripe::setApiKey( $secret_key );
					$cu = \Stripe\Customer::Retrieve(
					  ["id" => $subscriber_id, "expand" => ["default_source"]]
					);

					if ( isset( $update_card_error ) ) {
					  echo '<div class="leaky_paywall_message error"><p>' . $update_card_error . '</p></div>';
					} elseif ( isset( $update_card_success ) ) {
					  echo '<div class="leaky_paywall_message success"><p>' . $update_card_success . '</p></div>';
					}

					$payment_form .= '<h3 class="leaky-paywall-account-section-title">Payment Method</h3><p>' . $cu->default_source->brand . ' ending in ' . $cu->default_source->last4 . ' that expires ' . $cu->default_source->exp_month . '/' . $cu->default_source->exp_year . '</p>';

					if ( strcasecmp('deactivated', $status) == 0 ) {
						$data_label = 'Update Credit Card Details & Restart Subscription';
					} else {
						$data_label = 'Update Credit Card Details';
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
				}
				
				?>
				<h2 class="leaky-paywall-account-page-title"><?php _e( 'Your payment information', 'leaky-paywall' ); ?></h2>

				<?php 
					if ( $payment_form ) {
						echo $payment_form;
					}
				?>
				
				<?php
					if ( $cancel ) {
					echo '<h3 class="leaky-paywall-account-section-title">Manage</h3>';
					echo '<p class="leaky-paywall-cancel-link">' . apply_filters( 'leaky_paywall_cancel_link', $cancel ) . '</p>';
				}
				?>

				<?php 
			default:
				
				break;
		}

	?>

		

	</div>
</div>