<div id="leaky-paywall-onboarding--listbuilder" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=listbuilder' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_listbuilder', 'leaky_paywall_onboarding_listbuilder' ); ?>

		<h2><?php esc_html_e( 'Enable List Builder', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'List Builder displays a slide-out form on restricted content to capture email addresses and grow your subscriber list. It works with your default free subscription level to automatically register new readers.', 'leaky-paywall' ); ?></p>

		<p class="leaky-paywall-onboarding--checkbox">
			<label>
				<input type="checkbox" name="enable_listbuilder" value="1" checked />
				<?php esc_html_e( 'Enable List Builder', 'leaky-paywall' ); ?>
			</label>
		</p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Save & Continue', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=restrictions' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
