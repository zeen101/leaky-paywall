<?php

if ( ! function_exists( 'leaky_paywall_errors' ) ) {
	/**
	 * Stores error messages
	 *
	 * @since       1.0
	 */
	function leaky_paywall_errors() {
		static $wp_error; // Will hold global variable safely.
		return isset( $wp_error ) ? $wp_error : ( $wp_error = new WP_Error( null, null, null ) );
	}
}

/**
 * Retrieves the HTML for error messages
 *
 * @access      public
 * @since       4.0.0
 */
function leaky_paywall_get_error_messages_html( $error_id = '' ) {

	$html   = '';
	$errors = leaky_paywall_errors()->get_error_codes();

	if ( $errors ) {

		$html .= '<div class="leaky_paywall_message error">';
		// Loop error codes and display errors.
		foreach ( $errors as $code ) {

			if ( leaky_paywall_errors()->get_error_data( $code ) == $error_id ) {

				$message = leaky_paywall_errors()->get_error_message( $code );

				$html .= '<p class="leaky_paywall_error ' . esc_attr( $code ) . '"><span>' . $message . '</span></p>';

			}
		}

		$html .= '</div>';

	}

	return apply_filters( 'leaky_paywall_error_messages_html', $html, $errors );

}

/**
 * Displays the HTML for error messages
 *
 * @access      public
 * @since       4.0.0
 *
 * @param string $error_id The error id.
 */
function leaky_paywall_show_error_messages( $error_id = '' ) {

	if ( leaky_paywall_errors()->get_error_codes() ) {

		do_action( 'leaky_paywall_errors_before' );
		echo leaky_paywall_get_error_messages_html( $error_id );
		do_action( 'leaky_paywall_errors_after' );

	}
}
