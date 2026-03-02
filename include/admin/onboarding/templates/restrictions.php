<div id="leaky-paywall-onboarding--restrictions" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=restrictions' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_restrictions', 'leaky_paywall_onboarding_restrictions' ); ?>

		<h2><?php esc_html_e( 'Content Restrictions', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Control how many free articles non-subscribers can view before the paywall appears.', 'leaky-paywall' ); ?></p>

		<p class="leaky-paywall-onboarding--field">
			<label for="free_posts"><?php esc_html_e( 'Free posts before paywall', 'leaky-paywall' ); ?></label>
			<input type="number" id="free_posts" name="free_posts" value="1" min="0" max="100" step="1" />
		</p>

		<p class="description"><?php esc_html_e( 'We recommend starting with 1 free post to maximize conversions while still allowing readers to sample your content.', 'leaky-paywall' ); ?></p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Save & Continue', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=stripe' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
