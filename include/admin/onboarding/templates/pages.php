<div id="leaky-paywall-onboarding--pages" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=pages' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_pages', 'leaky_paywall_onboarding_pages' ); ?>

		<h2><?php esc_html_e( 'Create Required Pages', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Leaky Paywall needs a few pages for login, subscription, registration, and account management. We can create these for you automatically with the correct shortcodes. If a page already exists, it will be skipped.', 'leaky-paywall' ); ?></p>

		<table class="leaky-paywall-onboarding--pages-list">
			<tr>
				<td><strong><?php esc_html_e( 'Login', 'leaky-paywall' ); ?></strong></td>
				<td><code>[leaky_paywall_login]</code></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Subscribe', 'leaky-paywall' ); ?></strong></td>
				<td><code>[leaky_paywall_subscription]</code></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Register', 'leaky-paywall' ); ?></strong></td>
				<td><code>[leaky_paywall_register_form]</code></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'My Account', 'leaky-paywall' ); ?></strong></td>
				<td><code>[leaky_paywall_profile]</code></td>
			</tr>
		</table>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Create Pages & Continue', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=listbuilder' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
