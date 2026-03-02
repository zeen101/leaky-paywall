<div id="leaky-paywall-onboarding--tracking" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=tracking' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_tracking', 'leaky_paywall_onboarding_tracking' ); ?>

		<h2><?php esc_html_e( 'Analytics & Data', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'By opting-in, you allow some basic data about how you use Leaky Paywall to help improve the plugin for you and others. If you skip this step, that\'s okay! Leaky Paywall will still work perfectly for you.', 'leaky-paywall' ); ?></p>
		<p><?php esc_html_e( 'If you agree, the information will be sent to and stored on leakypaywall.com solely for research purposes and will not be sold or given to any 3rd party.', 'leaky-paywall' ); ?></p>
		<p style="font-weight: bold;"><?php esc_html_e( 'No subscriber data, payment information, or personally identifiable information about your members will be tracked in any way.', 'leaky-paywall' ); ?></p>
		<p><?php esc_html_e( 'Sites that opt in may receive personalized recommendations to improve their membership site setup and configuration.', 'leaky-paywall' ); ?></p>
		<p><a href="https://leakypaywall.com/usage-tracking/" target="_blank"><?php esc_html_e( 'Learn more about what is tracked', 'leaky-paywall' ); ?></a></p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Accept & Continue', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=pages' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
