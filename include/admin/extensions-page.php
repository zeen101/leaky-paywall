<?php
/**
 * Leaky Paywall > Extensions admin page.
 *
 * Renders the extension catalog fetched from leakypaywall.com and activates
 * installed extensions. Free installs see the full catalog with Get Pro CTAs.
 *
 * Core never downloads or installs extensions: WordPress.org-hosted plugins
 * may not install software from another server. One-click install and update
 * come from the Leaky Paywall Pro plugin, through the
 * `leaky_paywall_extension_card_button` filter and the
 * `leaky_paywall_extensions_page_after` action.
 *
 * @package Leaky Paywall
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leaky_Paywall_Extensions_Page {

	const CATALOG_TRANSIENT = 'leaky_paywall_extensions_catalog';
	const CATALOG_BACKUP    = 'leaky_paywall_extensions_catalog_backup';
	const UPGRADE_URL       = 'https://leakypaywall.com/upgrade-to-leaky-paywall-pro/?utm_source=plugin&utm_medium=extensions_page&utm_content=card&utm_campaign=upgrade';

	public function __construct() {
		add_action( 'wp_ajax_leaky_paywall_activate_extension', array( $this, 'ajax_activate' ) );
	}

	/**
	 * Catalog with caching: 12-hour transient, plus a long-lived backup option
	 * used as a fallback when the store is unreachable and the transient lapsed.
	 *
	 * @return array
	 */
	public function get_catalog() {
		$cached = get_transient( self::CATALOG_TRANSIENT );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$catalog = Leaky_Paywall_Store_Client::get_catalog();

		if ( is_wp_error( $catalog ) ) {
			$backup = get_option( self::CATALOG_BACKUP );
			return is_array( $backup ) ? $backup : array();
		}

		set_transient( self::CATALOG_TRANSIENT, $catalog, 12 * HOUR_IN_SECONDS );
		update_option( self::CATALOG_BACKUP, $catalog, false );

		return $catalog;
	}

	public function render_page() {
		if ( ! current_user_can( apply_filters( 'manage_leaky_paywall_settings', 'manage_options' ) ) ) {
			return;
		}

		$catalog    = $this->get_catalog();
		$pro_active = leaky_paywall_pro_is_active();

		$this->render_header();
		?>
		<div class="wrap">

			<?php if ( ! $pro_active ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php esc_html_e( 'Activate Leaky Paywall Pro to install these extensions with one click.', 'leaky-paywall' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=leaky-paywall-license' ) ); ?>"><?php esc_html_e( 'Add your license →', 'leaky-paywall' ); ?></a>
					</p>
				</div>
			<?php elseif ( ! leaky_paywall_pro_plugin_is_active() ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php esc_html_e( 'Install the Leaky Paywall Pro plugin to install and update these extensions with one click.', 'leaky-paywall' ); ?>
						<a href="<?php echo esc_url( leaky_paywall_pro_plugin_download_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download it from your account →', 'leaky-paywall' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $catalog ) ) : ?>
				<p><?php esc_html_e( 'The extension catalog could not be loaded right now. Please try again shortly.', 'leaky-paywall' ); ?></p>
			<?php else : ?>

				<div class="lp-extensions-toolbar">
					<div class="lp-extensions-filters">
						<button type="button" class="button lp-ext-filter button-primary" data-filter="all"><?php esc_html_e( 'All', 'leaky-paywall' ); ?></button>
						<button type="button" class="button lp-ext-filter" data-filter="installed"><?php esc_html_e( 'Installed', 'leaky-paywall' ); ?></button>
						<button type="button" class="button lp-ext-filter" data-filter="available"><?php esc_html_e( 'Available', 'leaky-paywall' ); ?></button>
						<button type="button" class="button lp-ext-filter" data-filter="updates"><?php esc_html_e( 'Updates', 'leaky-paywall' ); ?></button>
					</div>
					<input type="search" class="lp-ext-search regular-text" placeholder="<?php esc_attr_e( 'Search extensions…', 'leaky-paywall' ); ?>" />
				</div>

				<div class="lp-extensions-grid">
					<?php
					foreach ( $catalog as $ext ) {
						echo $this->render_card( $ext, $pro_active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within render_card.
					}
					?>
				</div>

			<?php endif; ?>
		</div>

		<?php $this->render_inline_assets(); ?>
		<?php

		/**
		 * Fires after the Extensions page renders. The Leaky Paywall Pro plugin
		 * prints its install and update script here.
		 *
		 * @since 5.3.3
		 *
		 * @param array $catalog The extension catalog.
		 */
		do_action( 'leaky_paywall_extensions_page_after', $catalog );
	}

	/**
	 * Standard LP admin header — orange bar with logo + page title. Matches
	 * Settings, Tools, Dashboard, etc.
	 */
	private function render_header() {
		?>
		<div id="lp-header" class="lp-header">
			<div id="lp-header-wrapper">
				<span id="lp-header-branding">
					<img class="lp-header-logo" width="200" src="<?php echo esc_url( LEAKY_PAYWALL_URL . 'images/leaky-paywall-logo.png' ); ?>" alt="<?php esc_attr_e( 'Leaky Paywall', 'leaky-paywall' ); ?>">
				</span>
				<span class="lp-header-page-title-wrap">
					<span class="lp-header-separator">/</span>
					<h1 class="lp-header-page-title"><?php esc_html_e( 'Extensions', 'leaky-paywall' ); ?></h1>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single extension card.
	 *
	 * @param array $ext        Catalog entry.
	 * @param bool  $pro_active Whether a valid Pro license is active.
	 * @return string
	 */
	private function render_card( $ext, $pro_active ) {
		$slug         = isset( $ext['slug'] ) ? sanitize_title( $ext['slug'] ) : '';
		if ( '' === $slug ) {
			return '';
		}

		$name         = isset( $ext['name'] ) ? (string) $ext['name'] : $slug;
		$desc         = isset( $ext['short_description'] ) ? (string) $ext['short_description'] : '';
		$icon         = isset( $ext['icon_url'] ) ? (string) $ext['icon_url'] : '';
		$requires_pro = ! empty( $ext['requires_pro'] );
		$catalog_ver  = isset( $ext['current_version'] ) ? (string) $ext['current_version'] : '';
		$learn_more   = isset( $ext['learn_more_url'] ) ? (string) $ext['learn_more_url'] : '';

		$plugin_file       = $this->get_plugin_file_by_slug( $slug );
		$installed         = '' !== $plugin_file;
		$active            = $installed && is_plugin_active( $plugin_file );
		$installed_version = $installed ? $this->get_installed_version( $plugin_file ) : '';
		$update_available  = $installed && $catalog_ver && $installed_version && version_compare( $installed_version, $catalog_ver, '<' );

		// Determine state for filtering + button.
		if ( $active && ! $update_available ) {
			$state = 'active';
		} elseif ( $active && $update_available ) {
			$state = 'update';
		} elseif ( $installed && ! $active ) {
			$state = 'installed_inactive';
		} else {
			$state = 'available';
		}

		$can_install = $pro_active || ! $requires_pro;

		ob_start();
		?>
		<div class="lp-ext-card" data-slug="<?php echo esc_attr( $slug ); ?>" data-state="<?php echo esc_attr( $state ); ?>" data-name="<?php echo esc_attr( strtolower( $name ) ); ?>">
			<div class="lp-ext-card__head">
				<?php if ( $icon ) : ?>
					<img class="lp-ext-card__icon" src="<?php echo esc_url( $icon ); ?>" alt="" width="48" height="48" />
				<?php endif; ?>
				<h3 class="lp-ext-card__title"><?php echo esc_html( $name ); ?></h3>
			</div>

			<p class="lp-ext-card__desc"><?php echo esc_html( $desc ); ?></p>

			<div class="lp-ext-card__footer">
				<span class="lp-ext-msg"></span>
				<?php
				/**
				 * Filters an extension card's action button. The Leaky Paywall Pro
				 * plugin uses it to add one-click install and update.
				 *
				 * @since 5.3.3
				 *
				 * @param string $button     Button HTML.
				 * @param string $state      available, update, installed_inactive or active.
				 * @param string $slug       Extension slug.
				 * @param array  $ext        Catalog entry.
				 * @param bool   $pro_active Whether a valid Pro license is active.
				 */
				$button = apply_filters( 'leaky_paywall_extension_card_button', $this->render_button( $state, $slug, $can_install ), $state, $slug, $ext, $pro_active );
				echo wp_kses_post( $button );
				?>
			</div>

			<?php if ( $learn_more ) : ?>
				<p class="lp-ext-card__learn-more"><a href="<?php echo esc_url( $learn_more ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'leaky-paywall' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_button( $state, $slug, $can_install ) {
		// Pro-required but no active license → Get Pro.
		if ( ! $can_install ) {
			return '<a href="' . esc_url( self::UPGRADE_URL ) . '" target="_blank" rel="noopener" class="button button-primary">' . esc_html__( 'Get Pro', 'leaky-paywall' ) . '</a>';
		}

		switch ( $state ) {
			case 'active':
				return '<span class="button button-disabled" disabled>' . esc_html__( 'Active', 'leaky-paywall' ) . '</span>';
			case 'update':
				return '<a href="' . esc_url( leaky_paywall_pro_plugin_download_url() ) . '" target="_blank" rel="noopener" class="button button-primary">' . esc_html__( 'Download update', 'leaky-paywall' ) . '</a>';
			case 'installed_inactive':
				return '<button type="button" class="button lp-ext-action" data-action="activate" data-slug="' . esc_attr( $slug ) . '">' . esc_html__( 'Activate', 'leaky-paywall' ) . '</button>';
			case 'available':
			default:
				return '<a href="' . esc_url( leaky_paywall_pro_plugin_download_url() ) . '" target="_blank" rel="noopener" class="button button-primary">' . esc_html__( 'Download', 'leaky-paywall' ) . '</a>';
		}
	}

	/**
	 * AJAX: activate an installed extension by slug.
	 */
	public function ajax_activate() {
		check_ajax_referer( 'leaky_paywall_extensions', 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to activate plugins.', 'leaky-paywall' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_send_json_error( array( 'message' => __( 'Missing extension.', 'leaky-paywall' ) ) );
		}

		// Derive the plugin file server-side from the slug so an arbitrary
		// plugin path can't be activated by a crafted request.
		$plugin_file = $this->get_plugin_file_by_slug( $slug );
		if ( '' === $plugin_file ) {
			wp_send_json_error( array( 'message' => __( 'That extension is not installed.', 'leaky-paywall' ) ) );
		}

		$result = activate_plugin( $plugin_file );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Activated.', 'leaky-paywall' ),
			'state'   => 'active',
		) );
	}

	public function get_catalog_entry( $slug ) {
		foreach ( $this->get_catalog() as $ext ) {
			if ( isset( $ext['slug'] ) && sanitize_title( $ext['slug'] ) === $slug ) {
				return $ext;
			}
		}
		return null;
	}

	public function get_plugin_file_by_slug( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $file;
			}
		}

		return '';
	}

	private function get_installed_version( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ]['Version'] ) ? (string) $plugins[ $plugin_file ]['Version'] : '';
	}

	public function render_inline_assets() {
		$nonce = wp_create_nonce( 'leaky_paywall_extensions' );
		?>
		<script>
		( function() {
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;

			function post( action, slug, cb ) {
				var data = new FormData();
				data.append( 'action', action );
				data.append( 'nonce', nonce );
				data.append( 'slug', slug );

				fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
					.then( function( r ) { return r.json(); } )
					.then( function( json ) { cb( json ); } )
					.catch( function() { cb( { success: false, data: { message: 'Request failed.' } } ); } );
			}

			function setMsg( card, text ) {
				var el = card.querySelector( '.lp-ext-msg' );
				if ( el ) { el.textContent = text || ''; }
			}

			document.addEventListener( 'click', function( e ) {
				var btn = e.target.closest( '.lp-ext-action' );
				if ( ! btn ) { return; }

				var card   = btn.closest( '.lp-ext-card' );
				var slug   = btn.getAttribute( 'data-slug' );
				var action = btn.getAttribute( 'data-action' );

				btn.setAttribute( 'disabled', 'disabled' );

				if ( 'activate' === action ) {
					setMsg( card, <?php echo wp_json_encode( __( 'Activating…', 'leaky-paywall' ) ); ?> );
					post( 'leaky_paywall_activate_extension', slug, function( json ) {
						if ( json.success ) {
							setMsg( card, '' );
							var wrap = btn.parentNode;
							btn.remove();
							var badge = document.createElement( 'span' );
							badge.className = 'button button-disabled';
							badge.textContent = <?php echo wp_json_encode( __( 'Active', 'leaky-paywall' ) ); ?>;
							wrap.appendChild( badge );
							card.setAttribute( 'data-state', 'active' );
						} else {
							setMsg( card, ( json.data && json.data.message ) || 'Error' );
							btn.removeAttribute( 'disabled' );
						}
					} );
				}
			} );

			// Filter pills — bind direct click handlers so nothing about event
			// delegation or pointer-events can interfere.
			document.querySelectorAll( '.lp-ext-filter' ).forEach( function( pill ) {
				pill.addEventListener( 'click', function( e ) {
					e.preventDefault();
					document.querySelectorAll( '.lp-ext-filter' ).forEach( function( p ) {
						p.classList.remove( 'button-primary' );
					} );
					pill.classList.add( 'button-primary' );

					var searchEl = document.querySelector( '.lp-ext-search' );
					applyFilter( pill.getAttribute( 'data-filter' ), searchEl ? searchEl.value : '' );
				} );
			} );

			// Search box.
			var searchInput = document.querySelector( '.lp-ext-search' );
			if ( searchInput ) {
				searchInput.addEventListener( 'input', function( e ) {
					var active = document.querySelector( '.lp-ext-filter.button-primary' );
					applyFilter( active ? active.getAttribute( 'data-filter' ) : 'all', e.target.value );
				} );
			}

			function applyFilter( filter, search ) {
				search = ( search || '' ).toLowerCase();
				document.querySelectorAll( '.lp-ext-card' ).forEach( function( card ) {
					var state = card.getAttribute( 'data-state' );
					var name  = card.getAttribute( 'data-name' ) || '';
					var matchFilter =
						'all' === filter ||
						( 'installed' === filter && state !== 'available' ) ||
						( 'available' === filter && state === 'available' ) ||
						( 'updates' === filter && state === 'update' );
					var matchSearch = '' === search || name.indexOf( search ) !== -1;
					card.classList.toggle( 'lp-ext-hidden', ! ( matchFilter && matchSearch ) );
				} );
			}
		} )();
		</script>
		<?php
	}
}

$GLOBALS['leaky_paywall_extensions_page'] = new Leaky_Paywall_Extensions_Page();
