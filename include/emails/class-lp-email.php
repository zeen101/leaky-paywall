<?php

/**
 * Base email class for Leaky Paywall.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LP_Email base class.
 *
 * Each email type extends this class, defining its own ID, title, defaults,
 * and trigger logic. Settings are stored per-email in wp_options.
 */
class LP_Email {

	/**
	 * Unique email identifier (e.g. 'new_subscriber').
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Human-readable title shown in the email list table.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Short description shown in the email list table.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Whether this email goes to 'subscriber' or 'admin'.
	 *
	 * @var string
	 */
	public $recipient_type = 'subscriber';

	/**
	 * Whether this email is enabled ('yes' or 'no').
	 *
	 * @var string
	 */
	public $enabled = 'yes';

	/**
	 * Email subject line.
	 *
	 * @var string
	 */
	public $subject = '';

	/**
	 * Email body (HTML).
	 *
	 * @var string
	 */
	public $body = '';

	/**
	 * Comma-separated recipients (admin emails only).
	 *
	 * @var string
	 */
	public $recipients = '';

	/**
	 * Default values for subclass properties.
	 *
	 * @var string
	 */
	public $default_enabled    = 'yes';
	public $default_subject    = '';
	public $default_body       = '';
	public $default_recipients = '';

	/**
	 * Template tags available for this email, shown in the UI.
	 *
	 * @var array
	 */
	public $template_tags = array();

	/**
	 * Option key for this email's settings.
	 *
	 * @var string
	 */
	protected $option_key = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->option_key = 'leaky_paywall_email_' . $this->id . '_settings';
		$this->init_settings();
	}

	/**
	 * Load settings from wp_option, merged with defaults.
	 */
	public function init_settings() {
		$saved    = get_option( $this->option_key, array() );
		$defaults = $this->get_defaults();
		$settings = wp_parse_args( $saved, $defaults );

		$this->enabled    = $settings['enabled'];
		$this->subject    = $settings['subject'];
		$this->body       = $settings['body'];
		$this->recipients = isset( $settings['recipients'] ) ? $settings['recipients'] : '';

		$this->load_extra_settings( $settings );
	}

	/**
	 * Return default settings. Subclasses can override to add extra keys.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'enabled'    => $this->default_enabled,
			'subject'    => $this->default_subject,
			'body'       => $this->default_body,
			'recipients' => $this->default_recipients,
		);
	}

	/**
	 * Hook for subclasses to load additional settings from the merged array.
	 *
	 * @param array $settings Merged settings.
	 */
	protected function load_extra_settings( $settings ) {}

	/**
	 * Check if this email is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Get the "From" name from global LP settings.
	 *
	 * @return string
	 */
	public function get_from_name() {
		$settings  = get_leaky_paywall_settings();
		$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );

		return isset( $settings['from_name'] ) && $settings['from_name'] ? $settings['from_name'] : $site_name;
	}

	/**
	 * Get the "From" email from global LP settings.
	 *
	 * @return string
	 */
	public function get_from_email() {
		$settings = get_leaky_paywall_settings();

		return isset( $settings['from_email'] ) && $settings['from_email'] ? $settings['from_email'] : get_option( 'admin_email' );
	}

	/**
	 * Build email headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		$from_name  = $this->get_from_name();
		$from_email = $this->get_from_email();

		return array(
			'From: ' . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <{$from_email}>",
			'Reply-To: ' . $from_email,
			'Content-Type: text/html; charset=UTF-8',
		);
	}

	/**
	 * Replace template tags in a string.
	 *
	 * Calls the existing leaky_paywall_filter_email_tags() for backward compat,
	 * then applies a per-email filter.
	 *
	 * @param string $content      Content with template tags.
	 * @param int    $user_id      User ID.
	 * @param string $display_name Display name.
	 * @param string $password     Password (for new accounts).
	 * @return string
	 */
	public function replace_tags( $content, $user_id, $display_name = '', $password = '' ) {
		$content = leaky_paywall_filter_email_tags( $content, $user_id, $display_name, $password );

		return apply_filters( 'leaky_paywall_email_' . $this->id . '_replace_tags', $content, $user_id );
	}

	/**
	 * Get the recipient(s) for this email.
	 *
	 * @param int $user_id User ID (used for subscriber-type emails).
	 * @return string
	 */
	public function get_recipient( $user_id = 0 ) {
		if ( 'admin' === $this->recipient_type ) {
			return $this->recipients;
		}

		if ( $user_id ) {
			$user = get_userdata( $user_id );
			return $user ? $user->user_email : '';
		}

		return '';
	}

	/**
	 * Send the email.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Additional args (e.g., password).
	 * @return bool
	 */
	public function send( $user_id, $args = array() ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$recipient = $this->get_recipient( $user_id );
		if ( empty( $recipient ) ) {
			return false;
		}

		$user_info    = get_userdata( $user_id );
		$password     = isset( $args['password'] ) ? $args['password'] : '';
		$display_name = $user_info ? $user_info->display_name : '';

		$subject = $this->replace_tags( stripslashes( $this->subject ), $user_id, $display_name, $password );
		$message = $this->replace_tags( stripslashes( $this->body ), $user_id, $display_name, $password );
		$message = $this->wrap( wpautop( make_clickable( $message ) ) );

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, $this->id );

		return wp_mail( $recipient, $subject, $message, $headers, $attachments );
	}

	/**
	 * Trigger the email. Subclasses override to add conditional logic
	 * and preserve existing filters.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Additional args.
	 */
	public function trigger( $user_id, $args = array() ) {
		$this->send( $user_id, $args );
	}

	/**
	 * Wrap a finished email body in the branded HTML template.
	 *
	 * Every LP email calls this just before wp_mail(). Extensions can opt an
	 * email out with the leaky_paywall_use_email_template filter.
	 *
	 * @param string $message The email body HTML.
	 * @return string
	 */
	public function wrap( $message ) {
		if ( ! apply_filters( 'leaky_paywall_use_email_template', true, $this->id, $this->recipient_type ) ) {
			return $message;
		}

		if ( ! function_exists( 'leaky_paywall_get_email_template' ) ) {
			return $message;
		}

		return leaky_paywall_get_email_template( $message, array( 'email_id' => $this->id ) );
	}

	/**
	 * Send a test copy of this email to an address, using the saved settings
	 * and sample data for any tags that need a real event.
	 *
	 * @param string $to Recipient address.
	 * @return bool
	 */
	public function send_test( $to ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$user_id      = get_current_user_id();
		$user_info    = get_userdata( $user_id );
		$display_name = $user_info ? $user_info->display_name : '';

		$subject = $this->replace_tags( stripslashes( $this->subject ), $user_id, $display_name, 'sample-password-1234' );
		$message = $this->replace_tags( stripslashes( $this->body ), $user_id, $display_name, 'sample-password-1234' );

		$subject = $this->preview_tags( $subject );
		$message = $this->preview_tags( $message );

		$message = $this->wrap( wpautop( make_clickable( $message ) ) );

		return wp_mail(
			$to,
			/* translators: %s: email subject line. */
			sprintf( __( '[Test] %s', 'leaky-paywall' ), $subject ),
			$message,
			$this->get_headers()
		);
	}

	/**
	 * Hook for subclasses to swap event-specific tags for sample values in a
	 * test send. Default: no-op.
	 *
	 * @param string $content Content with tags.
	 * @return string
	 */
	protected function preview_tags( $content ) {
		return $content;
	}

	// ──────────────────────────────────────────────────────────────────────
	// Admin Settings UI
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Render the individual email settings form.
	 */
	public function output_settings_form() {
		?>
		<table class="form-table">
			<?php
			$this->output_enable_field();
			$this->output_recipients_field();
			$this->output_subject_field();
			$this->output_body_field();
			$this->output_extra_fields();
			$this->output_template_tags_hint();
			$this->output_test_send_field();
			?>
		</table>
		<?php
	}

	/**
	 * Render the "Send test email" row.
	 */
	protected function output_test_send_field() {
		$current_user = wp_get_current_user();
		?>
		<tr>
			<th><?php esc_html_e( 'Send Test Email', 'leaky-paywall' ); ?></th>
			<td>
				<input type="email" id="<?php echo esc_attr( $this->id ); ?>_test_to" class="regular-text"
					value="<?php echo esc_attr( $current_user->user_email ); ?>" />
				<button type="button" class="button lp-send-test-email"
					data-email-id="<?php echo esc_attr( $this->id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'lp_send_test_email' ) ); ?>">
					<?php esc_html_e( 'Send Test', 'leaky-paywall' ); ?>
				</button>
				<span class="lp-send-test-email-result" style="margin-left: 8px;"></span>
				<p class="description">
					<?php esc_html_e( 'Sends this email with sample data using your saved settings. Save changes first.', 'leaky-paywall' ); ?>
				</p>
				<script>
				( function() {
					var btn = document.querySelector( '.lp-send-test-email[data-email-id="<?php echo esc_js( $this->id ); ?>"]' );
					if ( ! btn || btn.dataset.lpBound ) { return; }
					btn.dataset.lpBound = '1';
					btn.addEventListener( 'click', function() {
						var to  = document.getElementById( '<?php echo esc_js( $this->id ); ?>_test_to' ).value;
						var out = btn.parentNode.querySelector( '.lp-send-test-email-result' );
						btn.disabled = true;
						out.textContent = <?php echo wp_json_encode( __( 'Sending…', 'leaky-paywall' ) ); ?>;
						var data = new FormData();
						data.append( 'action', 'lp_send_test_email' );
						data.append( 'email_id', btn.dataset.emailId );
						data.append( 'to', to );
						data.append( 'nonce', btn.dataset.nonce );
						fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: data } )
							.then( function( r ) { return r.json(); } )
							.then( function( j ) { out.textContent = ( j.data && j.data.message ) ? j.data.message : ( j.success ? 'Sent' : 'Failed' ); } )
							.catch( function() { out.textContent = 'Failed'; } )
							.then( function() { btn.disabled = false; } );
					} );
				} )();
				</script>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render enable/disable checkbox.
	 */
	protected function output_enable_field() {
		?>
		<tr>
			<th><?php esc_html_e( 'Enable', 'leaky-paywall' ); ?></th>
			<td>
				<input type="checkbox"
					id="<?php echo esc_attr( $this->id ); ?>_enabled"
					name="<?php echo esc_attr( $this->id ); ?>_enabled"
					value="yes"
					<?php checked( 'yes', $this->enabled ); ?> />
				<label for="<?php echo esc_attr( $this->id ); ?>_enabled">
					<?php esc_html_e( 'Enable this email', 'leaky-paywall' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render recipients field (admin emails only).
	 */
	protected function output_recipients_field() {
		if ( 'admin' !== $this->recipient_type ) {
			return;
		}
		?>
		<tr>
			<th><?php esc_html_e( 'Recipient(s)', 'leaky-paywall' ); ?></th>
			<td>
				<input type="text"
					id="<?php echo esc_attr( $this->id ); ?>_recipients"
					name="<?php echo esc_attr( $this->id ); ?>_recipients"
					class="regular-text"
					value="<?php echo esc_attr( $this->recipients ); ?>" />
				<p class="description">
					<?php esc_html_e( 'Enter recipients (comma separated) for this email.', 'leaky-paywall' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render subject field.
	 */
	protected function output_subject_field() {
		?>
		<tr>
			<th><?php esc_html_e( 'Subject', 'leaky-paywall' ); ?></th>
			<td>
				<input type="text"
					id="<?php echo esc_attr( $this->id ); ?>_subject"
					name="<?php echo esc_attr( $this->id ); ?>_subject"
					class="regular-text"
					value="<?php echo esc_attr( $this->subject ); ?>" />
				<p class="description">
					<?php esc_html_e( 'The subject line for this email.', 'leaky-paywall' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render body field with wp_editor. Override in subclasses to skip.
	 */
	protected function output_body_field() {
		?>
		<tr>
			<th><?php esc_html_e( 'Body', 'leaky-paywall' ); ?></th>
			<td>
				<?php wp_editor( stripslashes( $this->body ), $this->id . '_body' ); ?>
				<p class="description">
					<?php esc_html_e( 'The email body. HTML is allowed.', 'leaky-paywall' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Hook for subclasses to render additional fields.
	 */
	protected function output_extra_fields() {}

	/**
	 * Render available template tags hint.
	 */
	protected function output_template_tags_hint() {
		if ( empty( $this->template_tags ) ) {
			return;
		}
		?>
		<tr>
			<th><?php esc_html_e( 'Template Tags', 'leaky-paywall' ); ?></th>
			<td>
				<p class="description">
					<?php echo esc_html( implode( ', ', $this->template_tags ) ); ?>
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: 1: the %token|fallback% syntax, 2: an example using it. */
						esc_html__( 'Add a fallback for empty values with %1$s — for example, %2$s renders "there" when no first name is on file.', 'leaky-paywall' ),
						'<code>%token|fallback%</code>',
						'<code>%firstname|there%</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Process and save settings from POST.
	 */
	public function process_admin_options() {
		$settings = array();

		$settings['enabled']    = isset( $_POST[ $this->id . '_enabled' ] ) ? 'yes' : 'no';
		$settings['subject']    = isset( $_POST[ $this->id . '_subject' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '_subject' ] ) ) : '';
		$settings['body']       = isset( $_POST[ $this->id . '_body' ] ) ? wp_kses_post( wp_unslash( $_POST[ $this->id . '_body' ] ) ) : '';
		$settings['recipients'] = isset( $_POST[ $this->id . '_recipients' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '_recipients' ] ) ) : '';

		$settings = $this->save_extra_settings( $settings );
		$settings = apply_filters( 'leaky_paywall_email_' . $this->id . '_save_settings', $settings );

		update_option( $this->option_key, $settings );
		$this->init_settings();
	}

	/**
	 * Hook for subclasses to add extra fields to the saved settings.
	 *
	 * @param array $settings Settings being saved.
	 * @return array
	 */
	protected function save_extra_settings( $settings ) {
		return $settings;
	}
}
