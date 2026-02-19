<h1>Thank you for activating Leaky Paywall</h1>
<p class="leaky-paywall-onboarding--tagline">You are just minutes away from launching your membership site on WordPress!</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=default' ) ); ?>">
	<?php echo wp_nonce_field( 'leaky_paywall_onboarding_default', 'leaky_paywall_onboarding_default' ); ?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-setup&step=business' ) ); ?>" class="button-primary large">Get Started <i class="dashicons dashicons-arrow-right-alt"></i></a></p>

	<div class="leaky-paywall-onboarding--quote">
		<blockquote>
			"Leaky Paywall has transformed how we monetize our content and grow our subscriber base."
			<cite>Publisher Testimonial</cite>
		</blockquote>
	</div>

	<div class="leaky-paywall-onboarding--quote">
		<blockquote>
			"The flexibility and ease of use made setting up our membership site a breeze."
			<cite>Content Creator</cite>
		</blockquote>
	</div>

	<div class="leaky-paywall-onboarding--quote">
		<blockquote>
			"Our subscription revenue has grown significantly since implementing Leaky Paywall."
			<cite>Digital Publisher</cite>
		</blockquote>
	</div>

</form>
