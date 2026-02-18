<?php

/**
 * Renewal reminder email for non-recurring subscribers.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LP_Email_Renewal_Reminder extends LP_Email {

	/**
	 * Days before expiration to send the reminder.
	 *
	 * @var string
	 */
	public $days_before = '7';

	public function __construct() {
		$this->id              = 'renewal_reminder';
		$this->title           = __( 'Renewal Reminder Email', 'leaky-paywall' );
		$this->description     = __( 'Sent to non-recurring subscribers before their subscription expires.', 'leaky-paywall' );
		$this->recipient_type  = 'subscriber';
		$this->default_enabled = 'no';
		$this->default_subject = '';
		$this->default_body    = '';
		$this->template_tags   = array( '%blogname%', '%sitename%', '%username%', '%password%', '%firstname%', '%lastname%', '%displayname%' );

		parent::__construct();
	}

	/**
	 * @return array
	 */
	public function get_defaults() {
		$defaults                = parent::get_defaults();
		$defaults['days_before'] = '7';

		return $defaults;
	}

	/**
	 * @param array $settings Merged settings.
	 */
	protected function load_extra_settings( $settings ) {
		$this->days_before = isset( $settings['days_before'] ) ? $settings['days_before'] : '7';
	}

	/**
	 * @param array $settings Settings being saved.
	 * @return array
	 */
	protected function save_extra_settings( $settings ) {
		$settings['days_before'] = isset( $_POST[ $this->id . '_days_before' ] )
			? absint( $_POST[ $this->id . '_days_before' ] )
			: '7';

		return $settings;
	}

	/**
	 * Render the "days before" field.
	 */
	protected function output_extra_fields() {
		?>
		<tr>
			<th><?php esc_html_e( 'When to Send Reminder', 'leaky-paywall' ); ?></th>
			<td>
				<input type="number"
					id="<?php echo esc_attr( $this->id ); ?>_days_before"
					name="<?php echo esc_attr( $this->id ); ?>_days_before"
					value="<?php echo esc_attr( $this->days_before ); ?>"
					min="1" />
				<p class="description">
					<?php esc_html_e( "Days in advance of a non-recurring subscriber's expiration date to remind them to renew.", 'leaky-paywall' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Trigger the renewal reminder email.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Additional args.
	 */
	public function trigger( $user_id, $args = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$settings = get_leaky_paywall_settings();

		if ( 'traditional' !== $settings['login_method'] ) {
			return;
		}

		$password     = isset( $args['password'] ) ? $args['password'] : '';
		$user_info    = get_userdata( $user_id );
		$display_name = $user_info ? $user_info->display_name : '';

		$message = stripslashes( apply_filters( 'leaky_paywall_renewal_reminder_email_message', $this->body, $user_id ) );

		$filtered_subject = $this->replace_tags( $this->subject, $user_id, $display_name, $password );
		$filtered_message = $this->replace_tags( $message, $user_id, $display_name, $password );
		$filtered_message = wpautop( make_clickable( $filtered_message ) );

		$headers     = $this->get_headers();
		$attachments = apply_filters( 'leaky_paywall_email_attachments', array(), $user_info, 'renewal_reminder' );

		wp_mail( $user_info->user_email, $filtered_subject, $filtered_message, $headers, $attachments );
	}
}
