<div id="leaky-paywall-onboarding--tracking" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=leaky-paywall-setup&step=tracking')); ?>">
		<?php wp_nonce_field('leaky_paywall_onboarding_tracking', 'leaky_paywall_onboarding_tracking'); ?>

		<h2><?php esc_html_e('Help Us Improve Leaky Paywall', 'leaky-paywall'); ?></h2>
		<p><?php esc_html_e('By opting in, you allow us to collect limited, non-sensitive usage data about how Leaky Paywall is configured on your site. This helps us improve the plugin and build better features for publishers like you.', 'leaky-paywall'); ?></p>
		<p><?php esc_html_e('Your site will continue to work exactly the same if you choose to skip this step.', 'leaky-paywall'); ?></p>
		<p style="font-weight: bold;"><?php esc_html_e('If you opt in:', 'leaky-paywall'); ?></p>
		<ul>
			<li>
				<?php esc_html_e('We collect basic technical and configuration data only', 'leaky-paywall'); ?>
			</li>
			<li>
				<?php esc_html_e('No subscriber data, payment information, or member personal data is ever tracked', 'leaky-paywall'); ?>
			</li>
			<li>
				<?php esc_html_e('Your data is used only to improve Leaky Paywall and will never be sold or shared with third parties', 'leaky-paywall'); ?>
			</li>

		</ul>
		<p><?php esc_html_e('Publishers who opt in may also receive helpful recommendations for improving their subscription and paywall setup.', 'leaky-paywall'); ?></p>
		<p><a href="https://leakypaywall.com/usage-tracking/" target="_blank"><?php esc_html_e('Learn more about what is tracked', 'leaky-paywall'); ?></a></p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e('Accept & Continue', 'leaky-paywall'); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url(admin_url('admin.php?page=leaky-paywall-setup&step=pages')); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e('Skip this step', 'leaky-paywall'); ?></a></p>
		</div>

	</form>
</div>