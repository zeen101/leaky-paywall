<?php
/**
 * Leaky Paywall Logging Class
 *
 * @package     Leaky Paywall
 * @since       4.16.14
 */

/**
 * Load the LP_Logging class
 */
class LP_Logging {

	public $is_writable = true;
	private $filename   = '';
	private $file       = '';

	/**
	 * Set up the Leaky Paywall Logging Class
	 *
	 * @since 4.16.14
	 */
	public function __construct() {}

	/**
	 * Sets up the log file if it is writable
	 *
	 * @since 4.16.14
	 * @return void
	 */
	public function setup_log_file()
	{

		if ( ! empty( $this->file ) ) {
			return;
		}

		$dir = leaky_paywall_get_log_dir( false );

		$this->filename = wp_hash( home_url( '/' ) . leaky_paywall_get_log_key() ) . '-lp-debug.log';
		$this->file     = $dir . $this->filename;

		if ( is_dir( $dir ) && ! wp_is_writable( $dir ) ) {
			$this->is_writable = false;
		}
	}

	/**
	 * Retrieve the log data
	 *
	 * @since 4.16.14
	 * @return string
	 */
	public function get_file_contents() {

		$contents = '';

		foreach ( $this->get_log_files() as $file ) {
			$contents .= (string) @file_get_contents( $file );
		}

		return $contents;
	}

	/**
	 * Log message to file
	 *
	 * @since 4.16.14
	 * @return void
	 */
	public function log_to_file( $message = '' ) {
		$message = gmdate( 'Y-m-d H:i:s' ) . ' - ' . $message . "\r\n";
		$this->write_to_log( $message );

	}

	/**
	 * Retrieve the file data is written to
	 *
	 * @since 4.16.14
	 * @return string
	 */
	protected function get_file() {

		$file = '';

		$this->setup_log_file();

		if ( ! $this->file ) {
			return $file;
		}

		if ( @file_exists( $this->file ) ) {

			if ( ! is_writeable( $this->file ) ) {
				$this->is_writable = false;
			}

			$file = @file_get_contents( $this->file );

		}

		return $file;
	}

	/**
	 * Write the log message
	 *
	 * @since 4.16.14
	 * @return void
	 */
	protected function write_to_log( $message = '' ) {

		$this->setup_log_file();

		if ( ! $this->file ) {
			return;
		}

		leaky_paywall_get_log_dir();

		leaky_paywall_maybe_rotate_log_for_size( $this->file );

		$is_new = ! file_exists( $this->file );

		// Append. Reading the whole log back in on every entry made each write
		// cost the size of the file, twice.
		@file_put_contents( $this->file, $message, FILE_APPEND );

		if ( $is_new ) {
			leaky_paywall_set_log_started();
		}
	}

	/**
	 * Delete the log file or removes all contents in the log file if we cannot delete it
	 *
	 * @since 4.16.14
	 * @return void
	 */
	public function clear_log_file() {
		$this->setup_log_file();

		// Everything in the directory, not just the paths the current key
		// computes, so Clear removes exactly what the screen reported.
		leaky_paywall_delete_log_files();

		delete_option( 'leaky_paywall_log_started' );

		@unlink( $this->file );

		if ( file_exists( $this->file ) ) {

			// it's still there, so maybe server doesn't have delete rights
			chmod( $this->file, 0664 ); // Try to give the server delete rights
			@unlink( $this->file );

			// See if it's still there
			if ( @file_exists( $this->file ) ) {

				/*
				 * Remove all contents of the log file if we cannot delete it
				 */
				if ( is_writeable( $this->file ) ) {

					file_put_contents( $this->file, '' );

				} else {

					return false;

				}

			}

		}

		$this->file = '';
		return true;

	}

	/**
	 * Describe the current log file for the Tools screen.
	 *
	 * @since 5.1.8
	 * @return array
	 */
	public function get_log_file_stats() {

		$stats = array(
			'exists'   => false,
			'size'     => 0,
			'entries'  => 0,
			'modified' => 0,
			'rotated'  => false,
		);

		$this->setup_log_file();

		if ( ! $this->file ) {
			return $stats;
		}

		foreach ( $this->get_log_files() as $file ) {
			$stats['exists']   = true;
			$stats['size']    += (int) filesize( $file );
			$stats['entries'] += $this->count_entries( $file );
			$stats['modified'] = max( $stats['modified'], (int) filemtime( $file ) );
		}

		$rotated = leaky_paywall_get_rotated_log_path();

		if ( $rotated && file_exists( $rotated ) ) {
			$stats['rotated'] = true;
		}

		return $stats;
	}

	/**
	 * Every generation of the log that currently exists, oldest first.
	 *
	 * @since 5.1.8
	 * @return array
	 */
	public function get_log_files() {

		$this->setup_log_file();

		if ( ! $this->file ) {
			return array();
		}

		// Read the directory rather than only the two paths the current key
		// computes. A log file can outlive the key that named it (a domain
		// move, a cleared option), and a file the admin screen cannot see is a
		// file nobody knows is holding subscriber data.
		$files = glob( leaky_paywall_get_log_dir( false ) . '*-lp-debug*.log' );

		if ( ! $files ) {
			return array();
		}

		// Oldest first, so a download reads in chronological order.
		usort(
			$files,
			function ( $a, $b ) {
				return filemtime( $a ) <=> filemtime( $b );
			}
		);

		return $files;
	}

	/**
	 * Count the entries in the log without reading it all into memory.
	 *
	 * @since 5.1.8
	 * @return int
	 */
	private function count_entries( $file = '' ) {

		if ( ! $file ) {
			$file = $this->file;
		}

		$handle = @fopen( $file, 'rb' );

		if ( ! $handle ) {
			return 0;
		}

		$count = 0;

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 8192 );

			if ( false === $chunk ) {
				break;
			}

			$count += substr_count( $chunk, "\n" );
		}

		fclose( $handle );

		return $count;
	}

	/**
	 * Return the location of the log file that LP_Logging will use.
	 *
	 * Note: Do not use this file to write to the logs, please use the `leaky_paywall_log` function to do so.
	 *
	 * @since 4.16.14
	 *
	 * @return string
	 */
	public function get_log_file_path() {
		$this->setup_log_file();

		return $this->file;
	}

}

// Initiate the logging system
$GLOBALS['lp_logs'] = new LP_Logging();

/**
 * Logs a message to the debug log file
 *
 * @since 4.16.14
 *
 * @param string $message The message to log.
 * @param bool   $force   Write the message even when debug mode is off. Used
 *                        for errors, which are always recorded.
 * @global $lp_logs LP Logs Object
 * @return void
 */
function leaky_paywall_debug_log( $message = '', $force = false ) {
	global $lp_logs;

	if ( leaky_paywall_is_debug_mode() || $force ) {

		if( function_exists( 'mb_convert_encoding' ) ) {

			$message = mb_convert_encoding( $message, 'UTF-8' );
	
		}
	
		$lp_logs->log_to_file( $message );

	}
}

/**
 * Check if debug logging is allowed
 *
 * Off unless a site turns it on. The LEAKY_PAYWALL_DEBUG constant overrides the
 * setting, and the leaky_paywall_is_debug_mode filter overrides both so the
 * long-standing __return_false snippet keeps working.
 *
 * Errors are logged regardless of this setting, via leaky_paywall_log_error().
 *
 * @since 4.16.14
 * @return boolean
 */
function leaky_paywall_is_debug_mode() {

	if ( defined( 'LEAKY_PAYWALL_DEBUG' ) ) {
		$is_debug_mode = (bool) LEAKY_PAYWALL_DEBUG;
	} elseif ( function_exists( 'get_leaky_paywall_settings' ) ) {
		$settings      = get_leaky_paywall_settings();
		$is_debug_mode = isset( $settings['debug_mode'] ) && 'on' === $settings['debug_mode'];
	} else {
		$is_debug_mode = false;
	}

	return (bool) apply_filters( 'leaky_paywall_is_debug_mode', $is_debug_mode );

}