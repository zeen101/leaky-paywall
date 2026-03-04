<div id="leaky-paywall-onboarding--restrictions" class="leaky-paywall-onboarding--step">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=restrictions' ) ); ?>">
		<?php wp_nonce_field( 'leaky_paywall_onboarding_restrictions', 'leaky_paywall_onboarding_restrictions' ); ?>

		<h2><?php esc_html_e( 'Content Restrictions', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Control how many free articles non-subscribers can view before the paywall appears.', 'leaky-paywall' ); ?></p>

		<p class="leaky-paywall-onboarding--field">
			<label for="free_posts"><?php esc_html_e( 'Free posts before paywall', 'leaky-paywall' ); ?></label>
			<input type="number" id="free_posts" name="free_posts" value="1" min="0" max="100" step="1" />
		</p>

		<p class="description"><?php esc_html_e('We recommend starting with 1 free post to maximize conversions while still allowing readers to sample your content. You can also set restrictions to 0 to keep AI and search bots out. List Builder will continue to grow your list.', 'leaky-paywall' ); ?></p>

		<hr style="margin: 32px 0;">

		<h2><?php esc_html_e( 'Paid Subscription Level', 'leaky-paywall' ); ?></h2>
		<p><?php esc_html_e( 'Optionally create a paid subscription level. Free subscribers who hit the paywall will be prompted to subscribe at this price. You can edit this level and add more levels later.', 'leaky-paywall' ); ?></p>

		<p class="leaky-paywall-onboarding--field">
			<label for="paid_level_label"><?php esc_html_e( 'Level Name', 'leaky-paywall' ); ?></label>
			<input type="text" id="paid_level_label" name="paid_level_label" placeholder="<?php esc_attr_e( 'Digital Subscription', 'leaky-paywall' ); ?>" />
		</p>

		<p class="leaky-paywall-onboarding--field">
			<label for="paid_level_price"><?php esc_html_e( 'Price', 'leaky-paywall' ); ?></label>
			<input type="text" id="paid_level_price" name="paid_level_price" placeholder="9.99" />
		</p>

		<p class="leaky-paywall-onboarding--field">
			<label for="paid_level_interval"><?php esc_html_e( 'Access Length', 'leaky-paywall' ); ?></label>
			<select id="paid_level_interval" name="paid_level_interval">
				<option value="month"><?php esc_html_e( 'Month', 'leaky-paywall' ); ?></option>
				<option value="year"><?php esc_html_e( 'Year', 'leaky-paywall' ); ?></option>
			</select>
		</p>

		<div class="leaky-paywall-onboarding--step--actions">
			<p><button class="button-primary large" type="submit"><?php esc_html_e( 'Save & Continue', 'leaky-paywall' ); ?></button></p>
			<p style="font-size:16px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=stripe' ) ); ?>" class="leaky-paywall-onboarding--continue"><?php esc_html_e( 'Skip this step', 'leaky-paywall' ); ?></a></p>
		</div>

	</form>
</div>
