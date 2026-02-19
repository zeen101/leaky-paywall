<?php if ( isset( $_GET['license'] ) ) { ?>
<div class="leaky-paywall-onboarding--license-activated" style="background:green;padding: 5px 20px; text-align: center; color: #FFF; border-radius: 5px; max-width: 750px; margin: 0 auto -50px auto;">
	<p>Your license key has been activated</p>
</div>
<?php } ?>

<div id="leaky-paywall-onboarding--data" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=data' ) ); ?>">
		<?php echo wp_nonce_field( 'leaky_paywall_onboarding_data', 'leaky_paywall_onboarding_data' ); ?>

		<h2>Analytics & Data</h2>
		<p>By opting-in, you allow some basic data about how you use Leaky Paywall to help improve the plugin for you and others. If you skip this step, that's okay! Leaky Paywall will still work perfectly for you.</p>
		<p>If you agree, the information will be sent to and stored on leakypaywall.com solely for research purposes and will not be sold or given to any 3rd party.</p>
		<p style="font-weight: bold;">No subscriber data, payment information, or personally identifiable information about your members will be tracked in any way.</p>
		<p>Sites that opt in may receive personalized recommendations to improve their membership site setup and configuration.</p>
		<p><a href="https://leakypaywall.com/usage-tracking/" target="_blank">Learn more about what is tracked</a></p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit">Accept & Continue</button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=updates' ) ); ?>" class="leaky-paywall-onboarding--continue">Skip this step</a></p>
		</div>

	</form>

</div>
