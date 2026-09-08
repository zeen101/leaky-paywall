<?php

/**
 * Email manager/registry for Leaky Paywall.
 *
 * Singleton that loads all email classes, renders the list table,
 * and routes to individual email settings.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Emails {

	/**
	 * @var LP_Emails|null
	 */
	private static $instance = null;

	/**
	 * Registered email instances, keyed by ID.
	 *
	 * @var LP_Email[]
	 */
	private $emails = array();

	/**
	 * Get the singleton instance.
	 *
	 * @return LP_Emails
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor — loads email classes.
	 */
	private function __construct() {
		$this->load_emails();
	}

	/**
	 * Instantiate core email classes and apply filter for extensions.
	 */
	private function load_emails() {
		$email_classes = array(
			'LP_Email_Admin_New_Subscriber',
			'LP_Email_New_Subscriber',
			'LP_Email_Renewal_Reminder',
			'LP_Email_Payment_Receipt',
		);

		/**
		 * Filter the registered email classes.
		 *
		 * Extensions can add their own email classes here:
		 *
		 *     add_filter( 'leaky_paywall_email_classes', function( $classes ) {
		 *         $classes[] = 'LP_Email_Gift_Purchased';
		 *         return $classes;
		 *     } );
		 *
		 * @param array $email_classes Array of class name strings.
		 */
		$email_classes = apply_filters( 'leaky_paywall_email_classes', $email_classes );

		foreach ( $email_classes as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$email = new $class_name();
				$this->emails[ $email->id ] = $email;
			}
		}
	}

	/**
	 * Get all registered emails.
	 *
	 * @return LP_Email[]
	 */
	public function get_emails() {
		return $this->emails;
	}

	/**
	 * Get a specific email by ID.
	 *
	 * @param string $email_id Email ID.
	 * @return LP_Email|null
	 */
	public function get_email( $email_id ) {
		return isset( $this->emails[ $email_id ] ) ? $this->emails[ $email_id ] : null;
	}

	/**
	 * Render the email list view: global sender settings + email table.
	 *
	 * @param array $settings LP settings array (for global sender fields).
	 */
	public function output_email_list( $settings ) {
		$this->output_global_sender_settings( $settings );
		$this->output_email_table();
	}

	/**
	 * Render global sender settings (site name, from name, from email).
	 *
	 * @param array $settings LP settings array.
	 */
	private function output_global_sender_settings( $settings ) {
		?>
		<h2><?php esc_html_e( 'Email Settings', 'leaky-paywall' ); ?></h2>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Site Name', 'leaky-paywall' ); ?></th>
				<td>
					<input type="text" id="site_name" class="regular-text"
						name="site_name"
						value="<?php echo esc_attr( $settings['site_name'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'From Name', 'leaky-paywall' ); ?></th>
				<td>
					<input type="text" id="from_name" class="regular-text"
						name="from_name"
						value="<?php echo esc_attr( $settings['from_name'] ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'From Email', 'leaky-paywall' ); ?></th>
				<td>
					<input type="text" id="from_email" class="regular-text"
						name="from_email"
						value="<?php echo esc_attr( $settings['from_email'] ); ?>" />
				</td>
			</tr>
			<?php
			$email_logo   = isset( $settings['email_logo'] ) ? $settings['email_logo'] : '';
			$email_accent = isset( $settings['email_accent_color'] ) && $settings['email_accent_color'] ? $settings['email_accent_color'] : '#1e293b';
			$email_footer = isset( $settings['email_footer_text'] ) ? $settings['email_footer_text'] : '';
			?>
			<tr>
				<th><?php esc_html_e( 'Email Logo', 'leaky-paywall' ); ?></th>
				<td>
					<input type="text" id="email_logo" name="email_logo" class="regular-text"
						value="<?php echo esc_attr( $email_logo ); ?>" placeholder="https://" />
					<button type="button" class="button" id="email_logo_select"><?php esc_html_e( 'Select Image', 'leaky-paywall' ); ?></button>
					<button type="button" class="button-link" id="email_logo_clear" <?php echo $email_logo ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'leaky-paywall' ); ?></button>
					<p class="description"><?php esc_html_e( 'Shown at the top of every email. Leave blank to use your site name.', 'leaky-paywall' ); ?></p>
					<p><img id="email_logo_preview" src="<?php echo esc_url( $email_logo ); ?>" alt="" style="max-height:40px;max-width:220px;<?php echo $email_logo ? '' : 'display:none;'; ?>" /></p>
					<script>
					( function() {
						var field = document.getElementById( 'email_logo' );
						var preview = document.getElementById( 'email_logo_preview' );
						var clear = document.getElementById( 'email_logo_clear' );
						function sync() {
							var v = field.value.trim();
							preview.src = v;
							preview.style.display = v ? '' : 'none';
							clear.style.display = v ? '' : 'none';
						}
						field.addEventListener( 'input', sync );
						clear.addEventListener( 'click', function() { field.value = ''; sync(); } );
						document.getElementById( 'email_logo_select' ).addEventListener( 'click', function( e ) {
							e.preventDefault();
							if ( ! window.wp || ! window.wp.media ) { return; }
							var frame = wp.media( { title: <?php echo wp_json_encode( __( 'Select an image', 'leaky-paywall' ) ); ?>, multiple: false, library: { type: 'image' } } );
							frame.on( 'select', function() {
								var att = frame.state().get( 'selection' ).first().toJSON();
								field.value = att.url;
								sync();
							} );
							frame.open();
						} );
					} )();
					</script>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Accent Color', 'leaky-paywall' ); ?></th>
				<td>
					<input type="color" id="email_accent_color" name="email_accent_color"
						value="<?php echo esc_attr( $email_accent ); ?>" />
					<code id="email_accent_color_hex"><?php echo esc_html( $email_accent ); ?></code>
					<p class="description"><?php esc_html_e( 'Used for the top border, headings, and links.', 'leaky-paywall' ); ?></p>
					<script>
					( function() {
						var input = document.getElementById( 'email_accent_color' );
						var hex = document.getElementById( 'email_accent_color_hex' );
						input.addEventListener( 'input', function() { hex.textContent = input.value; } );
					} )();
					</script>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Footer Text', 'leaky-paywall' ); ?></th>
				<td>
					<textarea id="email_footer_text" name="email_footer_text" class="large-text" rows="3"
						placeholder="<?php echo esc_attr( "The Example Review\n123 Main St, Suite 200, Springfield, IL 62704\n© %year% %sitename%" ); ?>"><?php echo esc_textarea( $email_footer ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Appears at the bottom of every email. A good place for your mailing address.', 'leaky-paywall' ); ?>
						<?php esc_html_e( 'Tags:', 'leaky-paywall' ); ?> <code>%sitename% %siteurl% %year%</code>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the email list table.
	 */
	private function output_email_table() {
		$emails_url = admin_url( 'admin.php?page=leaky-paywall-settings&tab=emails' );
		?>
		<h2><?php esc_html_e( 'Email Notifications', 'leaky-paywall' ); ?></h2>

		<table class="widefat lp-emails-table" style="max-width: 800px;">
			<thead>
				<tr>
					<th style="width: 30px;"></th>
					<th><?php esc_html_e( 'Email', 'leaky-paywall' ); ?></th>
					<th><?php esc_html_e( 'Recipient', 'leaky-paywall' ); ?></th>
					<th style="width: 80px;"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->emails as $email ) : ?>
					<tr>
						<td style="text-align: center;">
							<button type="button" class="lp-toggle-email button-link" data-email-id="<?php echo esc_attr( $email->id ); ?>" title="<?php echo $email->is_enabled() ? esc_attr__( 'Enabled — click to disable', 'leaky-paywall' ) : esc_attr__( 'Disabled — click to enable', 'leaky-paywall' ); ?>" style="cursor: pointer;">
								<?php if ( $email->is_enabled() ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-marker" style="color: #ccc;"></span>
								<?php endif; ?>
							</button>
						</td>
						<td>
							<a href="<?php echo esc_url( $emails_url . '&section=' . $email->id ); ?>">
								<strong><?php echo esc_html( $email->title ); ?></strong>
							</a>
							<p class="description" style="margin: 2px 0 0;">
								<?php echo esc_html( $email->description ); ?>
							</p>
						</td>
						<td>
							<?php
							if ( 'admin' === $email->recipient_type ) {
								echo esc_html( $email->recipients ? $email->recipients : get_option( 'admin_email' ) );
							} else {
								esc_html_e( 'Subscriber', 'leaky-paywall' );
							}
							?>
						</td>
						<td>
							<a class="button" href="<?php echo esc_url( $emails_url . '&section=' . $email->id ); ?>">
								<?php esc_html_e( 'Manage', 'leaky-paywall' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render an individual email's settings page.
	 *
	 * @param string $email_id Email ID.
	 */
	public function output_email_settings( $email_id ) {
		$email = $this->get_email( $email_id );

		if ( ! $email ) {
			echo '<p>' . esc_html__( 'Email not found.', 'leaky-paywall' ) . '</p>';
			return;
		}

		$back_url = admin_url( 'admin.php?page=leaky-paywall-settings&tab=emails' );
		?>
		<p>
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to all emails', 'leaky-paywall' ); ?></a>
		</p>

		<h2><?php echo esc_html( $email->title ); ?></h2>
		<p><?php echo esc_html( $email->description ); ?></p>

		<?php $email->output_settings_form(); ?>
		<?php
	}

	/**
	 * Process saving for the emails tab.
	 *
	 * @param string $current_section 'general' for list view, or an email ID.
	 * @return bool
	 */
	public function process_save( $current_section ) {
		if ( empty( $current_section ) || 'general' === $current_section ) {
			return $this->save_global_settings();
		}

		$email = $this->get_email( $current_section );

		if ( $email ) {
			$email->process_admin_options();
			return true;
		}

		return false;
	}

	/**
	 * Save global sender settings to the monolithic LP settings array.
	 *
	 * @return bool
	 */
	private function save_global_settings() {
		$settings = get_leaky_paywall_settings();

		if ( ! empty( $_POST['site_name'] ) ) {
			$settings['site_name'] = sanitize_text_field( wp_unslash( $_POST['site_name'] ) );
		}

		if ( ! empty( $_POST['from_name'] ) ) {
			$settings['from_name'] = sanitize_text_field( wp_unslash( $_POST['from_name'] ) );
		}

		if ( ! empty( $_POST['from_email'] ) ) {
			$settings['from_email'] = sanitize_text_field( wp_unslash( $_POST['from_email'] ) );
		}

		// The settings form nonce is verified in
		// Leaky_Paywall_Settings::process_settings_update() before this runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['email_logo'] ) ) {
			$settings['email_logo'] = esc_url_raw( wp_unslash( $_POST['email_logo'] ) );
		}

		if ( ! empty( $_POST['email_accent_color'] ) ) {
			$accent                         = sanitize_hex_color( wp_unslash( $_POST['email_accent_color'] ) );
			$settings['email_accent_color'] = $accent ? $accent : '#1e293b';
		}

		if ( isset( $_POST['email_footer_text'] ) ) {
			$settings['email_footer_text'] = wp_kses_post( wp_unslash( $_POST['email_footer_text'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_option( 'issuem-leaky-paywall', $settings );

		return true;
	}
}
