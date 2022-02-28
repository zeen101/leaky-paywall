<?php

add_action('leaky_paywall_after_help_settings', 'lp_display_debug_log');

function lp_display_debug_log() {

	global $lp_logs;

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	?>
		<h3><span><?php esc_html_e( 'Debug Log', 'leaky-paywall' ); ?></span></h3>
		
			<form id="lp-debug-log" method="post">
				<p><?php _e( 'Use this tool to help debug Leaky Paywall functionality.', 'leaky-paywall' ); ?></p>
				<p class="submit">
					<input type="hidden" name="lp_action" value="submit_debug_log" />
					<?php
					submit_button( __( 'Download Debug Log File', 'leaky-paywall' ), 'primary', 'lp-download-debug-log', false );
					submit_button( __( 'Clear Log', 'leaky-paywall' ), 'secondary lp-inline-button', 'lp-clear-debug-log', false );
					?>
				</p>
				<?php wp_nonce_field( 'lp_debug_log_action', 'lp_debug_log_field' ); ?>
			</form>
			<p><?php _e( 'Log file', 'leaky-paywall' ); ?>: <code><?php echo $lp_logs->get_log_file_path(); ?></code></p>
		
	
	<?php 
}

function leaky_paywall_handle_submit_debug_log() {

	global $lp_logs;

	if ( ! isset( $_POST['lp_debug_log_field'] ) 
    	|| ! wp_verify_nonce( $_POST['lp_debug_log_field'], 'lp_debug_log_action' ) 
	) {
		return;
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	if ( isset( $_POST['lp-download-debug-log'] ) ) {
		nocache_headers();

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="lp-debug-log.txt"' );

		print_r( stripslashes_deep( wp_unslash( $lp_logs->get_file_contents() ) ) );
		die('end of lp log');

	} elseif ( isset( $_POST['lp-clear-debug-log'] ) ) {

		// Clear the debug log.
		$lp_logs->clear_log_file();

		wp_safe_redirect( admin_url( 'edit.php?page=issuem-leaky-paywall&tab=help' ) );
		exit;

	}
}

add_action('admin_init', 'leaky_paywall_handle_submit_debug_log' );