<h1>Setup Guide</h1>
<p class="leaky-paywall-onboarding--tagline">The following important areas need to be configured to complete your setup of Leaky Paywall and start accepting subscribers.</p>
<p style="margin-top: 40px;"><a href="https://leakypaywall.com/docs/setup-guide/" class="button large" target="_blank"><span class="dashicons dashicons-book"></span> Read detailed setup guide with videos</a></p>

<div class="leaky-paywall-onboarding--step" id="leaky-paywall-onboarding--guide">
	<ol>

		<?php if ( ! get_option( 'leaky_paywall_address1' ) ) { ?>
			<li>
				<div>
					<p>Configure your subscription levels, pricing, and content access rules...</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall' ) ); ?>" class="button">Configure Settings</a>
				</div>
			</li>
		<?php } else { ?>
			<li class="completed">Subscription Configuration</li>
		<?php } ?>

		<li>
			<div>
				<p>Set up payment gateways to start accepting subscription payments</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=payments' ) ); ?>" class="button">Configure Payment Methods</a>
			</div>
		</li>

		<li>
			<div>
				<p>Connect your Stripe account to process payments securely</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=payments' ) ); ?>" class="button">Connect Stripe</a>
			</div>
		</li>

		<li>
			<div>
				<p>Create subscription levels with different access tiers</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=subscriptions' ) ); ?>" class="button">Create Subscription Levels</a>
			</div>
		</li>

		<li>
			<div>
				<p>Configure which content requires a subscription to access</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=content' ) ); ?>" class="button">Set Content Restrictions</a>
			</div>
		</li>

		<?php if ( ! get_option( 'leaky_paywall_logo' ) ) { ?>
			<li>
				<div>
					<p>Customize the look of your subscription pages with your branding</p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=display' ) ); ?>" class="button">Configure Branding</a>
				</div>
			</li>
		<?php } else { ?>
			<li class="completed">Branding</li>
		<?php } ?>

		<li>
			<div>
				<p>Set up email notifications for new subscriptions, renewals, and more</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&tab=emails' ) ); ?>" class="button">Configure Email Notifications</a>
			</div>
		</li>

		<li>
			<div>
				<p>Upgrade for advanced features to increase subscriber retention and revenue</p>
				<a href="https://leakypaywall.com/pricing/" class="button-primary" target="_blank">Learn more about Premium Features</a>
			</div>
		</li>

	</ol>
</div>

<div style="margin-top: 50px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
	<h3>Next Steps</h3>
	<p>Once you've completed the setup checklist above, you're ready to:</p>
	<ul style="list-style: disc; margin-left: 30px;">
		<li>Test your subscription flow with a test purchase</li>
		<li>Create restricted content for your subscribers</li>
		<li>Promote your membership site to start growing your subscriber base</li>
	</ul>
	<p style="margin-top: 20px;">
		<a href="https://leakypaywall.com/docs/" class="button" target="_blank">View Full Documentation</a>
		<a href="https://leakypaywall.com/support/" class="button" target="_blank">Get Support</a>
	</p>
</div>
