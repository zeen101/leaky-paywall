<?php
/**
 * Locate template.
 *
 * Locate the called template.
 * Search Order:
 * 1. /themes/theme/leaky-paywall/$template_name
 * 2. /themes/theme/$template_name
 * 3. /plugins/leaky-paywall/templates/$template_name.
 *
 * @since 4.14.5
 *
 * @param   string $template_name          Template to load.
 * @param   string $string $template_path  Path to templates.
 * @param   string $default_path           Default path to template files.
 * @return  string                          Path to the template file.
 */
function leaky_paywall_locate_template( $template_name, $template_path = '', $default_path = '' ) {

	// Set variable to search in leaky-paywall folder of theme.
	if ( ! $template_path ) :
		$template_path = 'leaky-paywall/';
	endif;

	// Set default plugin templates path.
	if ( ! $default_path ) :
		$default_path = LEAKY_PAYWALL_PATH . 'templates/'; // Path to the template folder.
	endif;

	// Search template file in theme folder.
	$template = locate_template(
		array(
			$template_path . $template_name,
			$template_name,
		)
	);

	// Get plugins template file.
	if ( ! $template ) :
		$template = $default_path . $template_name;
	endif;

	return apply_filters( 'leaky_paywall_locate_template', $template, $template_name, $template_path, $default_path );

}

/**
 * Get template.
 *
 * Search for the template and include the file.
 *
 * @since 4.14.5
 *
 * @param string $template_name          Template to load.
 * @param array  $args                   Args passed for the template file.
 * @param string $string $template_path  Path to templates.
 * @param string $default_path           Default path to template files.
 */
function leaky_paywall_get_template( $template_name, $args = array(), $tempate_path = '', $default_path = '' ) {

	if ( is_array( $args ) && isset( $args ) ) :
		extract( $args );
	endif;

	$template_file = leaky_paywall_locate_template( $template_name, $tempate_path, $default_path );

	if ( ! file_exists( $template_file ) ) :
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', esc_url( $template_file ) ), '1.0.0' );
		return;
	endif;

	include $template_file;

}
