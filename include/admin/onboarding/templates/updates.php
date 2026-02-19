<div id="leaky-paywall-onboarding--updates" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=updates' ) ); ?>">
		<?php echo wp_nonce_field( 'leaky_paywall_onboarding_updates', 'leaky_paywall_onboarding_updates' ); ?>

		<h2>Stay up-to-date with Leaky Paywall</h2>
		<p>Join our email newsletter to receive important updates, new features, membership growth tips and how to make the most of Leaky Paywall (you can always unsubscribe).</p>
		<p id="leaky-paywall-onboarding--updates--fields">
			<?php $current_user = wp_get_current_user(); ?>
			<label>
				First name<br />
				<input type="text" required name="first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" placeholder="First name" />
			</label>
			<label>
				Email
				<input type="email" required name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" placeholder="Email" />
			</label>
		</p>

		<p style="margin-top: 40px;">You can also follow Leaky Paywall on:</p>
		<ul>
			<li><a href="https://www.facebook.com/leakypaywall" target="_blank"><span class="dashicons dashicons-facebook"></span> Facebook</a> Get updates and latest news</li>
			<li><a href="https://twitter.com/leakypaywall" target="_blank"><span class="dashicons dashicons-twitter"></span> Twitter</a> Follow for quick tips and announcements</li>
			<li><a href="https://www.youtube.com/@leakypaywall" target="_blank"><span class="dashicons dashicons-youtube"></span> YouTube</a> How-to videos and guides</li>
		</ul>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit">Join & Continue</button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=guide' ) ); ?>" class="leaky-paywall-onboarding--continue">Skip step</a></p>
		</div>

	</form>

</div>
