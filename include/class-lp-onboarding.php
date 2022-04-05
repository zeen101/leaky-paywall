<?php
/**
 * Leaky Paywall Onboarding Class
 *
 * @package     Leaky Paywall
 * @since       4.17.2
 */

/**
 * Load the LP_Onbarding class
 */
class LP_Onboarding {

	const STEPS_NUMBER = 3;

	const ONBOARDING_BASE_SLUG = 'lp-onboarding';

	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'register_pages' ) );
		// add_action( 'activated_plugin', array( $this, 'maybe_start_onboarding' ) );
		add_action( 'admin_init', array( $this, 'maybe_start_onboarding' ) );
	}

	public function maybe_start_onboarding()
	{
		
		$title = false;

		$title = isset( $_GET['test_lp_onboarding'] );

		if ( $title ) {
			wp_redirect( admin_url( sprintf( 'admin.php?page=%s-1', self::ONBOARDING_BASE_SLUG ) ) );
			exit();
		}

	}

	public function register_pages()
	{
		for ( $page_number = 1; $page_number <= self::STEPS_NUMBER; $page_number ++ ) {
			$this->register_page( 'Onboarding wizzard', $this->get_page_slug( $page_number ), array(
				$this,
				sprintf( 'step_%s', $page_number )
			) );
		}
	}

	/**
	 * @param int $page_number
	 *
	 * @return string
	 */
	protected function get_page_slug( $page_number ) {
		return sprintf( '%s-%d', self::ONBOARDING_BASE_SLUG, $page_number );
	}

	/**
	 * @param string $title
	 * @param string $slug
	 * @param callable $callable
	 */
	protected function register_page( $title, $slug, $callable ) {
		add_submenu_page( '', __( $title, 'leaky-paywall' ), __( $title, 'leaky-paywall' ), apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ), $slug, $callable );
	}

	public function step_1() {
		?>
		<div class="ssp-onboarding ssp-onboarding-step-1">
			<?php $this->steps_header(); ?>
			<div class="ssp-onboarding__settings">
				<div class="ssp-onboarding__settings-header">
					<h1>Let's get your publication started</h1>
				</div>
				<form class="ssp-onboarding__settings-body" action="<?php echo $step_urls[ $step_number + 1 ] ?>" method="post">
					<div class="ssp-onboarding__settings-item">
						<h2>What’s the name of your publication?</h2>
						<label for="show_name">This will be the title shown to listeners. You can always change it later.</label>
						<input id="show_name" class="js-onboarding-field" type="text" name="data_title" value="<?php echo $data_title ?>">
					</div>
					<div class="ssp-onboarding__settings-item">
						<h2>What’s your publication about?</h2>
						<label for="show_description">Pique listeners' interest with a a few details about your podcast.</label>
						<textarea id="show_description" class="js-onboarding-field" name="data_description" rows="7"><?php echo $data_description ?></textarea>
					</div>
					<div class="ssp-onboarding__submit">
						<button type="submit" class="js-onboarding-btn" <?php if( empty( $data_title ) || empty( $data_description ) ) echo 'disabled="disabled"' ?>>Proceed</button>
					</div>
				</form>
			</div>
			<?php $this->steps_footer(); ?>
		</div>
		<?php 
	}

	public function steps_header()
	{
		?>
			<p style="text-align: center;">
				<img width="300" src="<?php echo LEAKY_PAYWALL_URL; ?>/images/LP_logo_color_lrg.png">
			</p>
		<?php 
	}

	public function steps_footer()
	{
		?>
			<h3 style="color: #9DA3AD; text-align: center;">Skip Setup</h3>
		<?php 
	}
}

new LP_Onboarding();