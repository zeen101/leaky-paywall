<?php
$onboarding = new Leaky_Paywall_Onboarding();
$connect_url = $onboarding->get_connect_url();
?>
<div id="leaky-paywall-onboarding--stripe" class="leaky-paywall-onboarding--step">

	<h2>Connect Your Stripe Account</h2>
	<p>Connect your Stripe account to start accepting payments. Stripe securely processes credit cards and deposits funds directly into your bank account.</p>
	<p>With Leaky Paywall, a 10% revenue share is applied to payments. There are no setup fees, monthly fees, or hidden charges.</p>
	<p>As your publication grows, you can <a target="_blank" href="https://leakypaywall.com/">upgrade to a paid Leaky Paywall plan</a> at any time to reduce or remove the revenue share.</p>

	<div class="leaky-paywall-onboarding--stripe-connect">
		<p><a href="<?php echo esc_url($connect_url); ?>" class="button-primary large">Connect to Stripe <i class="dashicons dashicons-arrow-right-alt"></i></a></p>
		<p style="color: #666; margin-top: 20px;">You will be redirected to Stripe to complete the connection, then brought back to continue setup.</p>
	</div>

	<div class="leaky-paywall-onboarding--step--actions" style="margin-top: 40px;">
		<p style="font-size:16px;"><a href="<?php echo esc_url(admin_url('admin.php?page=leaky-paywall-setup&step=email')); ?>" class="leaky-paywall-onboarding--continue">Skip this step</a></p>
	</div>

</div>