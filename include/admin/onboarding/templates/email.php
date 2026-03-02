<?php
$settings        = get_leaky_paywall_settings();
$default_subject = $settings['new_email_subject'];
$default_body    = $settings['new_email_body'];
?>
<div id="leaky-paywall-onboarding--email" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=email' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_email', 'leaky_paywall_onboarding_email' ); ?>

		<h2><?php esc_html_e( 'Welcome Email', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'This email is sent to new subscribers after they sign up. Customize the subject and body below.', 'leaky-paywall' ); ?></p>

		<p class="leaky-paywall-onboarding--field">
			<label for="email_subject"><?php esc_html_e( 'Subject', 'leaky-paywall' ); ?></label>
			<input type="text" id="email_subject" name="email_subject" class="large-text" value="<?php echo esc_attr( $default_subject ); ?>" />
		</p>

		<p class="leaky-paywall-onboarding--field">
			<label for="email_body"><?php esc_html_e( 'Body', 'leaky-paywall' ); ?></label>
			<textarea id="email_body" name="email_body" class="large-text" rows="12"><?php echo esc_textarea( $default_body ); ?></textarea>
		</p>

		<div class="leaky-paywall-onboarding--template-tags">
			<p><strong><?php esc_html_e( 'Available template tags:', 'leaky-paywall' ); ?></strong></p>
			<ul>
				<li><code>%blogname%</code> — <?php esc_html_e( 'Site name', 'leaky-paywall' ); ?></li>
				<li><code>%sitename%</code> — <?php esc_html_e( 'Site name', 'leaky-paywall' ); ?></li>
				<li><code>%username%</code> — <?php esc_html_e( 'Subscriber username', 'leaky-paywall' ); ?></li>
				<li><code>%useremail%</code> — <?php esc_html_e( 'Subscriber email', 'leaky-paywall' ); ?></li>
				<li><code>%password%</code> — <?php esc_html_e( 'Subscriber password', 'leaky-paywall' ); ?></li>
				<li><code>%firstname%</code> — <?php esc_html_e( 'First name', 'leaky-paywall' ); ?></li>
				<li><code>%lastname%</code> — <?php esc_html_e( 'Last name', 'leaky-paywall' ); ?></li>
				<li><code>%displayname%</code> — <?php esc_html_e( 'Display name', 'leaky-paywall' ); ?></li>
			</ul>
		</div>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Save & Finish', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=issuem-leaky-paywall&lp_onboarding_complete=1' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
