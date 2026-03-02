<div id="leaky-paywall-onboarding--welcome" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=welcome' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_welcome', 'leaky_paywall_onboarding_welcome' ); ?>

		<h1><?php esc_html_e( 'Thank you for activating Leaky Paywall', 'leaky-paywall' ); ?></h1>
		<p class="leaky-paywall-onboarding--tagline"><?php esc_html_e( 'You are just minutes away from launching your membership site on WordPress!', 'leaky-paywall' ); ?></p>

		<h2><?php esc_html_e( 'What type of content do you publish?', 'leaky-paywall' ); ?></h2>
		<ul id="leaky-paywall-onboarding--publication-types">
			<li><label><input type="checkbox" name="publication_types[]" value="local_news" /><?php esc_html_e( 'Local News', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="magazine" /><?php esc_html_e( 'Magazine', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="membership" /><?php esc_html_e( 'Membership', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="business_journal" /><?php esc_html_e( 'Business Journal', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="news" /><?php esc_html_e( 'News', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="financial" /><?php esc_html_e( 'Financial', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="association" /><?php esc_html_e( 'Association', 'leaky-paywall' ); ?></label></li>
			<li><label><input type="checkbox" name="publication_types[]" value="other" /><?php esc_html_e( 'Other', 'leaky-paywall' ); ?></label></li>
		</ul>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Get Started', 'leaky-paywall' ); ?> <i class="dashicons dashicons-arrow-right-alt"></i></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=tracking' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
