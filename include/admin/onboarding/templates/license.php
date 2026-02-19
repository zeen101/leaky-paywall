<div id="leaky-paywall-onboarding--license" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=license' ) ); ?>">
		<?php echo wp_nonce_field( 'leaky_paywall_onboarding_license', 'leaky_paywall_onboarding_license' ); ?>

		<h2>Leaky Paywall License</h2>
		<p>Enter your license key to unlock premium features and updates</p>
		<p>
			<input type="text" name="license" value="" placeholder="Enter your license key" style="width: 100%; max-width: 500px; padding: 8px;" />
		</p>
		<p style="color: #666; margin-top: 30px;">A paid license is not required to use Leaky Paywall. You may use the free version as long as you want!</p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit">Activate & Continue</button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=data' ) ); ?>" class="leaky-paywall-onboarding--continue">I do not have a license yet</a></p>
		</div>

	</form>

</div>

<div id="leaky-paywall-upgrade">
	<div id="leaky-paywall-upgrade--header">
		<div id="leaky-paywall-upgrade--header--content">
			<h2>Grow your membership revenue <em>with Leaky Paywall Pro!</em></h2>
			<p>Get access to premium features that help you increase subscriber retention and revenue — <strong>pay for itself with just a few new subscribers!</strong></p>
			<p>
				<a href="https://leakypaywall.com/pricing/" class="button-primary large" target="_blank" style="margin-top: 15px;">Upgrade to Pro Today!</a>
				<br />
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=data' ) ); ?>" style="font-size: 14px;">Not now, I will check this out later</a>
			</p>
		</div>
	</div>

	<div id="leaky-paywall-upgrade--features">
		<h3>Premium Features Include:</h3>
		<ul>
			<li><strong>Advanced Subscription Tiers</strong> - Create unlimited subscription levels with custom access rules</li>
			<li><strong>Drip Content</strong> - Schedule content releases to keep subscribers engaged</li>
			<li><strong>Email Marketing Integration</strong> - Connect with your favorite email service provider</li>
			<li><strong>Advanced Analytics</strong> - Track subscriber behavior and revenue metrics</li>
			<li><strong>Custom Payment Gateways</strong> - Accept payments through multiple providers</li>
			<li><strong>Priority Support</strong> - Get help from our expert support team</li>
		</ul>
	</div>

	<div id="leaky-paywall-upgrade--cta">
		<p>
			<a href="https://leakypaywall.com/pricing/" class="button-primary large" target="_blank" style="margin-top: 15px;">See All Premium Features</a>
		</p>
		<ul>
			<li>
				<span class="dashicons dashicons-shield-alt"></span>
				30-day money back guarantee
			</li>
			<li>
				<span class="dashicons dashicons-groups"></span>
				Priority customer support
			</li>
			<li>
				<span class="dashicons dashicons-update"></span>
				Regular updates and new features
			</li>
		</ul>
	</div>
</div>

<p style="margin-top: 50px; text-align: center;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=data' ) ); ?>" class="button">Continue with Free Version</a></p>
