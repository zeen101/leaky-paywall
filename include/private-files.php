<?php
/**
 * Leaky Paywall private file storage
 *
 * Leaky Paywall writes two kinds of file that hold subscriber data: the debug
 * log, and subscriber export CSVs. Both are kept in their own directories inside
 * uploads, closed to direct requests, and given names that cannot be guessed.
 *
 * This file provides the shared directory and protection helpers, everything
 * specific to the debug log (naming, rotation, retention), and the migrations
 * that clean up the publicly readable files earlier versions left behind. The
 * export side lives in include/admin/tools/export.php.
 *
 * @package Leaky Paywall
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get the directory Leaky Paywall stores its log file in.
 *
 * Defaults to a leaky-paywall folder inside wp-content/uploads. Define the
 * LEAKY_PAYWALL_LOG_DIR constant to store the log somewhere else, which is the
 * right answer on servers that ignore .htaccess rules.
 *
 * @param bool $create Create and protect the directory if it does not exist.
 * @return string Directory path with a trailing slash.
 */
function leaky_paywall_get_log_dir( $create = true ) {

	if ( defined( 'LEAKY_PAYWALL_LOG_DIR' ) && LEAKY_PAYWALL_LOG_DIR ) {
		$dir = trailingslashit( LEAKY_PAYWALL_LOG_DIR );

		$dir = trailingslashit( apply_filters( 'leaky_paywall_log_directory', $dir ) );

		if ( $create ) {
			leaky_paywall_prepare_protected_dir( $dir );
		}

		return $dir;
	}

	// Deliberately not uploads/leaky-paywall, which holds subscriber export
	// CSVs and predates this directory.
	$dir = leaky_paywall_get_protected_dir( 'leaky-paywall-logs', false );
	$dir = trailingslashit( apply_filters( 'leaky_paywall_log_directory', $dir ) );

	if ( $create ) {
		leaky_paywall_prepare_protected_dir( $dir );
	}

	return $dir;
}

/**
 * Path of a Leaky Paywall directory inside uploads that is closed to direct
 * requests.
 *
 * @param string $name   Directory name, relative to the uploads base.
 * @param bool   $create Create and protect it if it does not exist.
 * @return string Directory path with a trailing slash.
 */
function leaky_paywall_get_protected_dir( $name, $create = true ) {

	$upload_dir = wp_get_upload_dir();
	$dir        = trailingslashit( trailingslashit( $upload_dir['basedir'] ) . $name );

	if ( $create ) {
		leaky_paywall_prepare_protected_dir( $dir );
	}

	return $dir;
}

/**
 * Create a directory if needed and write its access-protection files.
 *
 * @param string $dir Directory path with a trailing slash.
 * @return void
 */
function leaky_paywall_prepare_protected_dir( $dir ) {

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	leaky_paywall_write_protection_files( $dir );
}

/**
 * Write the files that keep the log directory closed to direct requests.
 *
 * Runs once a day, and whenever the directory is first created, so a protection
 * file removed by a migration or a backup restore comes back on its own.
 *
 * @param bool $force Skip the once-a-day check.
 * @return void
 */
function leaky_paywall_create_log_protection_files( $force = false ) {

	if ( ! $force && get_transient( 'leaky_paywall_log_protection_checked' ) ) {
		return;
	}

	leaky_paywall_write_protection_files( leaky_paywall_get_log_dir( false ) );

	set_transient( 'leaky_paywall_log_protection_checked', true, DAY_IN_SECONDS );
}
add_action( 'admin_init', 'leaky_paywall_create_log_protection_files' );

/**
 * Write the deny rules and index files into a directory.
 *
 * @param string $dir Directory path with a trailing slash.
 * @return void
 */
function leaky_paywall_write_protection_files( $dir ) {

	if ( ! $dir || ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
		return;
	}

	// Apache 2.4 wants Require, 2.2 wants Deny. Write both so neither version
	// serves the contents, and turn off directory listing either way.
	$rules = "Options -Indexes\n"
		. "<IfModule mod_authz_core.c>\n"
		. "\tRequire all denied\n"
		. "</IfModule>\n"
		. "<IfModule !mod_authz_core.c>\n"
		. "\tOrder allow,deny\n"
		. "\tDeny from all\n"
		. "</IfModule>\n";

	$rules = apply_filters( 'leaky_paywall_protected_directory_htaccess_rules', $rules );

	$htaccess = $dir . '.htaccess';

	if ( ! file_exists( $htaccess ) || $rules !== @file_get_contents( $htaccess ) ) {
		@file_put_contents( $htaccess, $rules );
	}

	if ( ! file_exists( $dir . 'index.php' ) ) {
		@file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
	}

	if ( ! file_exists( $dir . 'index.html' ) ) {
		@file_put_contents( $dir . 'index.html', '' );
	}
}

/**
 * Get the secret that is mixed into the log filename.
 *
 * @return string
 */
function leaky_paywall_get_log_key() {

	$key = get_option( 'leaky_paywall_log_key' );

	if ( ! $key ) {
		$key = wp_generate_password( 20, false );
		update_option( 'leaky_paywall_log_key', $key, false );
	}

	return $key;
}

/**
 * Give the log file a new name and remove the old one.
 *
 * Called when detailed logging is switched on, so a filename that has been
 * shared in a screenshot or a support ticket stops resolving, and so each
 * debugging session starts from an empty log.
 *
 * @return void
 */
function leaky_paywall_rotate_log_key() {

	// Delete first. Once the key changes, the old file is orphaned in place.
	leaky_paywall_delete_log_files();

	update_option( 'leaky_paywall_log_key', wp_generate_password( 20, false ), false );
}

/**
 * Delete every Leaky Paywall log file in the log directory.
 *
 * @return int Number of files deleted.
 */
function leaky_paywall_delete_log_files() {

	$deleted = 0;
	$files   = glob( leaky_paywall_get_log_dir( false ) . '*-lp-debug*.log' );

	if ( ! $files ) {
		return $deleted;
	}

	foreach ( $files as $file ) {
		if ( @unlink( $file ) ) {
			++$deleted;
		}
	}

	return $deleted;
}

/**
 * Delete debug logs left behind in the uploads root by earlier versions.
 *
 * Before 5.1.8 the log was written to wp-content/uploads, where a plain .log
 * file is served to anyone who knows the URL. Those files are deleted rather
 * than moved: nothing reads them, and every one of them is an exposure the
 * site owner never opted into.
 *
 * @return void
 */
function leaky_paywall_maybe_migrate_debug_log() {

	if ( get_option( 'lp_debug_log_migrated' ) ) {
		return;
	}

	$upload_dir = wp_get_upload_dir();
	$legacy     = glob( trailingslashit( $upload_dir['basedir'] ) . '*-lp-debug*.log' );
	$deleted    = 0;

	if ( $legacy ) {
		foreach ( $legacy as $file ) {
			if ( @unlink( $file ) ) {
				++$deleted;
			}
		}
	}

	update_option( 'lp_debug_log_migrated', '1' );

	// Create the protected directory now, so the next write has somewhere to go.
	leaky_paywall_get_log_dir();

	if ( $deleted ) {
		leaky_paywall_log_error( $deleted . ' legacy debug log file(s) removed from the uploads directory', 'migration' );
	}
}
add_action( 'admin_init', 'leaky_paywall_maybe_migrate_debug_log', 5 );

/**
 * Size at which the log is rotated.
 *
 * @return int Bytes.
 */
function leaky_paywall_get_log_size_limit() {

	$limit = (int) apply_filters( 'leaky_paywall_log_size_limit', 5 * MB_IN_BYTES );

	return $limit > 0 ? $limit : 5 * MB_IN_BYTES;
}

/**
 * Age at which the log is rotated.
 *
 * A single append-only file cannot expire line by line without reading and
 * rewriting the whole thing, which is the cost we removed from the write path.
 * Rotating on age instead bounds the log to two generations and needs no
 * parsing: anything older than twice this window is gone.
 *
 * @return int Seconds.
 */
function leaky_paywall_get_log_retention_period() {

	$days = (int) apply_filters( 'leaky_paywall_log_retention_days', 30 );

	return ( $days > 0 ? $days : 30 ) * DAY_IN_SECONDS;
}

/**
 * When the current log file was started.
 *
 * @return int Unix timestamp, or 0 if there is no current log.
 */
function leaky_paywall_get_log_started() {

	return (int) get_option( 'leaky_paywall_log_started', 0 );
}

/**
 * Record the moment a fresh log file begins.
 *
 * @return void
 */
function leaky_paywall_set_log_started() {

	update_option( 'leaky_paywall_log_started', time(), false );
}

/**
 * Move the current log aside, keeping one previous generation.
 *
 * @return bool True if a rotation happened.
 */
function leaky_paywall_rotate_log_file() {

	global $lp_logs;

	if ( ! $lp_logs instanceof LP_Logging ) {
		return false;
	}

	$current = $lp_logs->get_log_file_path();

	if ( ! $current || ! file_exists( $current ) ) {
		return false;
	}

	$previous = leaky_paywall_get_rotated_log_path();

	// Only one generation is kept, so the existing one makes way.
	if ( file_exists( $previous ) ) {
		@unlink( $previous );
	}

	if ( ! @rename( $current, $previous ) ) {
		return false;
	}

	leaky_paywall_set_log_started();

	return true;
}

/**
 * Path of the previous generation of the log.
 *
 * Keeps the .log extension so it stays inside the patterns the delete and
 * protection helpers use.
 *
 * @return string
 */
function leaky_paywall_get_rotated_log_path() {

	global $lp_logs;

	if ( ! $lp_logs instanceof LP_Logging ) {
		return '';
	}

	$current = $lp_logs->get_log_file_path();

	if ( ! $current ) {
		return '';
	}

	return substr( $current, 0, -4 ) . '.1.log';
}

/**
 * Rotate the log if it has grown past the size limit.
 *
 * Checked on the write path, so a burst of webhook traffic cannot run the file
 * past the ceiling before the daily job notices.
 *
 * @param string $file Path of the log about to be written to.
 * @return void
 */
function leaky_paywall_maybe_rotate_log_for_size( $file ) {

	if ( ! $file || ! file_exists( $file ) ) {
		return;
	}

	if ( filesize( $file ) < leaky_paywall_get_log_size_limit() ) {
		return;
	}

	leaky_paywall_rotate_log_file();
}

/**
 * Rotate the log if it has been open longer than the retention window.
 *
 * @return void
 */
function leaky_paywall_maybe_rotate_log_for_age() {

	$started = leaky_paywall_get_log_started();

	if ( ! $started ) {
		leaky_paywall_set_log_started();
		return;
	}

	if ( ( time() - $started ) < leaky_paywall_get_log_retention_period() ) {
		return;
	}

	leaky_paywall_rotate_log_file();
}
add_action( 'leaky_paywall_rotate_debug_log', 'leaky_paywall_maybe_rotate_log_for_age' );

/**
 * Delete log files that no longer belong to the current filename.
 *
 * The filename is derived from home_url() and the rotating key, so moving a
 * site to a new domain, or turning detailed logging on, leaves the previous
 * file stranded in the directory. Nothing reads it and nothing else would ever
 * remove it, so the daily job sweeps it up.
 *
 * @return int Number of files deleted.
 */
function leaky_paywall_delete_orphan_log_files() {

	global $lp_logs;

	if ( ! $lp_logs instanceof LP_Logging ) {
		return 0;
	}

	$keep = array_filter( array( $lp_logs->get_log_file_path(), leaky_paywall_get_rotated_log_path() ) );

	if ( ! $keep ) {
		return 0;
	}

	$files = glob( leaky_paywall_get_log_dir( false ) . '*-lp-debug*.log' );

	if ( ! $files ) {
		return 0;
	}

	$deleted = 0;

	foreach ( $files as $file ) {
		if ( in_array( $file, $keep, true ) ) {
			continue;
		}

		if ( @unlink( $file ) ) {
			++$deleted;
		}
	}

	return $deleted;
}
add_action( 'leaky_paywall_rotate_debug_log', 'leaky_paywall_delete_orphan_log_files' );

/**
 * Schedule the daily age check.
 *
 * @return void
 */
function leaky_paywall_register_log_rotation() {

	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		return;
	}

	if ( as_has_scheduled_action( 'leaky_paywall_rotate_debug_log' ) ) {
		return;
	}

	as_schedule_recurring_action(
		time() + DAY_IN_SECONDS,
		DAY_IN_SECONDS,
		'leaky_paywall_rotate_debug_log',
		array(),
		'leaky-paywall'
	);
}
add_action( 'init', 'leaky_paywall_register_log_rotation' );

/**
 * Delete subscriber export files left in the public uploads directory.
 *
 * Before 5.1.8 the Export tool wrote exports to uploads/leaky-paywall and handed
 * the admin a public URL. The filename combined a browser-generated integer
 * between 0 and 1000 with wp_hash( home_url('/') ), which is constant per site,
 * so one leaked URL exposed every export the site had produced. Nothing reads
 * these files and they are regenerable in one click, so they are deleted rather
 * than moved.
 *
 * @return void
 */
function leaky_paywall_maybe_migrate_subscriber_exports() {

	if ( get_option( 'lp_subscriber_exports_migrated' ) ) {
		return;
	}

	$upload_dir = wp_get_upload_dir();
	$legacy_dir = trailingslashit( $upload_dir['basedir'] ) . 'leaky-paywall/';
	$deleted    = 0;

	$files = glob( $legacy_dir . 'leaky-paywall-report-*.csv' );

	if ( $files ) {
		foreach ( $files as $file ) {
			if ( @unlink( $file ) ) {
				++$deleted;
			}
		}
	}

	update_option( 'lp_subscriber_exports_migrated', '1' );

	// Nothing serves from this directory any more, so close it in case an
	// extension or a retired add-on writes an export there.
	if ( is_dir( $legacy_dir ) ) {
		leaky_paywall_write_protection_files( $legacy_dir );
	}

	if ( $deleted ) {
		leaky_paywall_log_error( $deleted . ' publicly readable subscriber export file(s) removed from the uploads directory', 'migration' );
	}
}
add_action( 'admin_init', 'leaky_paywall_maybe_migrate_subscriber_exports', 5 );
