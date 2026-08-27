<?php
/**
 * Admin notices for access configuration that silently locks subscribers out.
 *
 * Two conditions are invisible from the settings screens on their own:
 *
 *  1. A subscription level with no access rules saved. Its subscribers can see
 *     no restricted content at all. That can be deliberate, so the notice is
 *     dismissible.
 *  2. A restricted post type that no level grants. Gate 1 (is this content
 *     restricted) is opt-in, Gate 2 (may this subscriber see it) is an
 *     allow list, and a level rule is only consulted for a post type that also
 *     appears in the restrictions list. Adding a post type to Settings >
 *     Restrictions without adding it to any level locks out every subscriber,
 *     paid tiers included.
 *
 * @package Leaky Paywall
 * @since 5.1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether any content is actually restricted.
 *
 * The shipped default stores a single flat rule rather than a list of rows, and
 * the resolver skips non-numeric keys, so only numeric rows restrict anything.
 *
 * @return bool
 */
function leaky_paywall_has_active_restrictions() {
	$settings = get_leaky_paywall_settings();

	if ( empty( $settings['restrictions']['post_types'] ) || ! is_array( $settings['restrictions']['post_types'] ) ) {
		return false;
	}

	foreach ( $settings['restrictions']['post_types'] as $key => $restriction ) {
		if ( is_numeric( $key ) && ! empty( $restriction['post_type'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Find subscription levels that have no access rules saved.
 *
 * Returns nothing when no content is restricted, since a level that grants
 * nothing locks nobody out on a site that gates nothing.
 *
 * @return array Level id => level label.
 */
function leaky_paywall_levels_without_access_rules() {
	$settings = get_leaky_paywall_settings();
	$found    = array();

	if ( ! leaky_paywall_has_active_restrictions() ) {
		return $found;
	}

	if ( empty( $settings['levels'] ) || ! is_array( $settings['levels'] ) ) {
		return $found;
	}

	foreach ( $settings['levels'] as $level_id => $level ) {

		if ( ! empty( $level['deleted'] ) ) {
			continue;
		}

		if ( ! empty( $level['post_types'] ) && is_array( $level['post_types'] ) ) {
			continue;
		}

		$label = ! empty( $level['label'] ) ? $level['label'] : sprintf(
			/* translators: %s: subscription level id. */
			__( 'Level %s', 'leaky-paywall' ),
			$level_id
		);

		$found[ $level_id ] = $label;
	}

	return $found;
}

/**
 * Find restricted post types that no subscription level grants access to.
 *
 * Matching is by post type only, mirroring Leaky_Paywall_Restrictions, which
 * skips an access rule whose post type differs from the restriction's.
 *
 * @return array Post type slugs.
 */
function leaky_paywall_restricted_post_types_without_a_level() {
	$settings = get_leaky_paywall_settings();

	if ( empty( $settings['restrictions']['post_types'] ) || ! is_array( $settings['restrictions']['post_types'] ) ) {
		return array();
	}

	// Post types granted by at least one live level.
	$granted = array();

	if ( ! empty( $settings['levels'] ) && is_array( $settings['levels'] ) ) {
		foreach ( $settings['levels'] as $level ) {

			if ( ! empty( $level['deleted'] ) ) {
				continue;
			}

			if ( empty( $level['post_types'] ) || ! is_array( $level['post_types'] ) ) {
				continue;
			}

			foreach ( $level['post_types'] as $access_rule ) {
				if ( ! empty( $access_rule['post_type'] ) ) {
					$granted[ $access_rule['post_type'] ] = true;
				}
			}
		}
	}

	$ungranted = array();

	foreach ( $settings['restrictions']['post_types'] as $key => $restriction ) {

		// The default restriction is stored as a single flat rule rather than a
		// list, so non-numeric keys are not rows. Same guard the resolver uses.
		if ( ! is_numeric( $key ) ) {
			continue;
		}

		if ( empty( $restriction['post_type'] ) ) {
			continue;
		}

		if ( isset( $granted[ $restriction['post_type'] ] ) ) {
			continue;
		}

		$ungranted[ $restriction['post_type'] ] = true;
	}

	return array_keys( $ungranted );
}

/**
 * Human readable name for a post type, falling back to its slug.
 *
 * @param string $post_type The post type slug.
 * @return string
 */
function leaky_paywall_post_type_display_name( $post_type ) {
	$object = get_post_type_object( $post_type );

	if ( $object && ! empty( $object->labels->name ) ) {
		return $object->labels->name . ' (' . $post_type . ')';
	}

	return $post_type;
}

/**
 * Build the dismissal key for a notice.
 *
 * The key includes what is currently wrong, so dismissing today's problem does
 * not hide a different one that appears later.
 *
 * @param string $notice The notice slug.
 * @param array  $items  The affected level ids or post type slugs.
 * @return string
 */
function leaky_paywall_config_notice_key( $notice, $items ) {
	$items = array_map( 'strval', $items );
	sort( $items );

	return $notice . ':' . md5( implode( ',', $items ) );
}

/**
 * Whether the current user has dismissed this exact notice.
 *
 * @param string $key The dismissal key.
 * @return bool
 */
function leaky_paywall_config_notice_dismissed( $key ) {
	$dismissed = get_user_meta( get_current_user_id(), 'lp_dismissed_config_notices', true );

	return is_array( $dismissed ) && in_array( $key, $dismissed, true );
}

/**
 * Display configuration notices on Leaky Paywall admin screens.
 */
function leaky_paywall_display_config_notices() {

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || false === strpos( $screen->id, 'leaky-paywall' ) ) {
		return;
	}

	$settings_url = admin_url( 'admin.php?page=leaky-paywall-settings' );

	// 1. Levels with no access rules.
	$levels = leaky_paywall_levels_without_access_rules();

	if ( $levels ) {
		$key = leaky_paywall_config_notice_key( 'levels_without_rules', array_keys( $levels ) );

		if ( ! leaky_paywall_config_notice_dismissed( $key ) ) {
			$links = array();

			foreach ( $levels as $level_id => $label ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $settings_url . '&tab=subscriptions&level_id=' . rawurlencode( $level_id ) ),
					esc_html( $label )
				);
			}

			leaky_paywall_render_config_notice(
				'warning',
				$key,
				sprintf(
					/* translators: %s: comma separated list of linked level names. */
					_n(
						'This subscription level has no access rules saved, so its subscribers cannot view any restricted content: %s',
						'These subscription levels have no access rules saved, so their subscribers cannot view any restricted content: %s',
						count( $links ),
						'leaky-paywall'
					),
					implode( ', ', $links )
				),
				__( 'If that is intentional you can dismiss this. Otherwise, open the level and add an access option.', 'leaky-paywall' )
			);
		}
	}

	// 2. Restricted post types that no level grants.
	$post_types = leaky_paywall_restricted_post_types_without_a_level();

	if ( $post_types ) {
		$key = leaky_paywall_config_notice_key( 'post_types_without_level', $post_types );

		if ( ! leaky_paywall_config_notice_dismissed( $key ) ) {
			$names = array_map( 'leaky_paywall_post_type_display_name', $post_types );

			leaky_paywall_render_config_notice(
				'error',
				$key,
				sprintf(
					/* translators: %s: comma separated list of post type names. */
					_n(
						'This post type is restricted but no subscription level grants access to it, so every subscriber is locked out of it, including paid levels: %s',
						'These post types are restricted but no subscription level grants access to them, so every subscriber is locked out of them, including paid levels: %s',
						count( $names ),
						'leaky-paywall'
					),
					esc_html( implode( ', ', $names ) )
				),
				sprintf(
					/* translators: 1: opening link tag to the subscriptions tab, 2: closing link tag. */
					__( 'Add an access option for it to each level that should include it, under %1$sSettings > Subscriptions%2$s.', 'leaky-paywall' ),
					'<a href="' . esc_url( $settings_url . '&tab=subscriptions' ) . '">',
					'</a>'
				)
			);
		}
	}
}
add_action( 'admin_notices', 'leaky_paywall_display_config_notices' );

/**
 * Render one dismissible configuration notice.
 *
 * @param string $type    'warning' or 'error'.
 * @param string $key     The dismissal key.
 * @param string $message The primary message. May contain links.
 * @param string $hint    A second line telling the admin what to do.
 */
function leaky_paywall_render_config_notice( $type, $key, $message, $hint ) {
	$allowed = array( 'a' => array( 'href' => array() ) );
	?>
	<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible" data-lp-config-notice="<?php echo esc_attr( $key ); ?>" data-lp-nonce="<?php echo esc_attr( wp_create_nonce( 'lp_dismiss_config_notice' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'Leaky Paywall', 'leaky-paywall' ); ?>:</strong>
			<?php echo wp_kses( $message, $allowed ); ?>
		</p>
		<p><?php echo wp_kses( $hint, $allowed ); ?></p>
	</div>
	<script>
	( function() {
		var notice = document.querySelector( '.notice[data-lp-config-notice="<?php echo esc_js( $key ); ?>"]' );
		if ( ! notice ) { return; }

		// Core wires .notice-dismiss onto every .is-dismissible notice, so we
		// only need to persist the choice. The fade out is already handled.
		notice.addEventListener( 'click', function( e ) {
			if ( ! e.target.closest( '.notice-dismiss' ) ) { return; }

			var data = new FormData();
			data.append( 'action', 'lp_dismiss_config_notice' );
			data.append( 'nonce', notice.getAttribute( 'data-lp-nonce' ) );
			data.append( 'key', notice.getAttribute( 'data-lp-config-notice' ) );

			fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} );
		} );
	} )();
	</script>
	<?php
}

/**
 * AJAX handler for dismissing a configuration notice.
 */
function leaky_paywall_ajax_dismiss_config_notice() {
	if ( ! check_ajax_referer( 'lp_dismiss_config_notice', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
	}

	if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

	if ( ! $key ) {
		wp_send_json_error( array( 'message' => 'missing_key' ), 400 );
	}

	$user_id   = get_current_user_id();
	$dismissed = get_user_meta( $user_id, 'lp_dismissed_config_notices', true );

	if ( ! is_array( $dismissed ) ) {
		$dismissed = array();
	}

	if ( ! in_array( $key, $dismissed, true ) ) {
		$dismissed[] = $key;

		// Keep the list from growing without bound as configurations change.
		if ( count( $dismissed ) > 50 ) {
			$dismissed = array_slice( $dismissed, -50 );
		}

		update_user_meta( $user_id, 'lp_dismissed_config_notices', $dismissed );
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_lp_dismiss_config_notice', 'leaky_paywall_ajax_dismiss_config_notice' );
