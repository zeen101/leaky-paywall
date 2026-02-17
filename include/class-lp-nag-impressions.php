<?php
/**
 * Leaky Paywall Nag Impressions Tracking
 *
 * Tracks paywall nag impressions per post, per day, per nag type.
 *
 * @package Leaky Paywall
 * @since 4.23.0
 */

class LP_Nag_Impressions {

	/**
	 * Database version for this table.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option name for tracking DB version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'lp_nag_impressions_db_version';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_table' ) );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'lp_nag_impressions';
	}

	/**
	 * Create the table if it doesn't exist or needs updating.
	 */
	public static function maybe_create_table() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::create_table();
	}

	/**
	 * Create the nag impressions table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			post_id bigint(20) unsigned NOT NULL,
			nag_date date NOT NULL,
			nag_type varchar(100) NOT NULL DEFAULT 'subscribe',
			count bigint(20) unsigned NOT NULL DEFAULT 1,
			PRIMARY KEY  (post_id, nag_date, nag_type),
			KEY nag_date (nag_date),
			KEY nag_type (nag_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Record a nag impression.
	 *
	 * @param int    $post_id  The post ID where the nag was shown.
	 * @param string $nag_type The nag type (subscribe, upgrade, or targeted:{post_id}).
	 */
	public static function record( $post_id, $nag_type = 'subscribe' ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$today      = current_time( 'Y-m-d' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table_name (post_id, nag_date, nag_type, count)
				 VALUES (%d, %s, %s, 1)
				 ON DUPLICATE KEY UPDATE count = count + 1",
				$post_id,
				$today,
				$nag_type
			)
		);
	}

	/**
	 * Get total impressions for a period.
	 *
	 * @param string $period The date range period.
	 * @return int
	 */
	public static function get_total_impressions( $period ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$after_date = gmdate( 'Y-m-d', strtotime( leaky_paywall_insights_get_formatted_period( $period ) ) );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(count), 0) FROM $table_name WHERE nag_date >= %s",
				$after_date
			)
		);

		return (int) $total;
	}

	/**
	 * Get impressions broken down by nag type.
	 *
	 * @param string $period The date range period.
	 * @return array Array of objects with nag_type and total.
	 */
	public static function get_impressions_by_nag_type( $period ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$after_date = gmdate( 'Y-m-d', strtotime( leaky_paywall_insights_get_formatted_period( $period ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT nag_type, SUM(count) AS total
				 FROM $table_name
				 WHERE nag_date >= %s
				 GROUP BY nag_type
				 ORDER BY total DESC",
				$after_date
			)
		);
	}

	/**
	 * Get top posts by impressions with conversion data.
	 *
	 * @param string $period The date range period.
	 * @param int    $limit  Max number of results.
	 * @return array Array of objects with post_id, impressions, conversions, and rate.
	 */
	public static function get_top_posts_with_conversions( $period, $limit = 15 ) {
		global $wpdb;

		$table_name = self::get_table_name();
		$after_date = gmdate( 'Y-m-d', strtotime( leaky_paywall_insights_get_formatted_period( $period ) ) );

		// Get impressions per post.
		$impressions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, SUM(count) AS impressions
				 FROM $table_name
				 WHERE nag_date >= %s
				 GROUP BY post_id
				 ORDER BY impressions DESC
				 LIMIT %d",
				$after_date,
				$limit
			)
		);

		if ( empty( $impressions ) ) {
			return array();
		}

		// Get conversions per nag location for the same period.
		$after_datetime = gmdate( 'Y-m-d H:i:s', strtotime( leaky_paywall_insights_get_formatted_period( $period ) ) );

		$conversion_results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_nag.meta_value AS post_id, COUNT(*) AS conversions
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm_nag
					 ON p.ID = pm_nag.post_id AND pm_nag.meta_key = '_nag_location_id'
				 INNER JOIN {$wpdb->postmeta} pm_status
					 ON p.ID = pm_status.post_id AND pm_status.meta_key = '_status'
				 WHERE p.post_type = 'lp_transaction'
				 AND p.post_date >= %s
				 AND pm_status.meta_value != 'incomplete'
				 AND pm_nag.meta_value != ''
				 GROUP BY pm_nag.meta_value",
				$after_datetime
			)
		);

		$conversions = array();
		foreach ( $conversion_results as $row ) {
			$conversions[ $row->post_id ] = (int) $row->conversions;
		}

		// Combine data.
		$results = array();
		foreach ( $impressions as $row ) {
			$post_impressions = (int) $row->impressions;
			$post_conversions = isset( $conversions[ $row->post_id ] ) ? $conversions[ $row->post_id ] : 0;
			$rate             = $post_impressions > 0 ? round( ( $post_conversions / $post_impressions ) * 100, 1 ) : 0;

			$results[] = (object) array(
				'post_id'     => (int) $row->post_id,
				'impressions' => $post_impressions,
				'conversions' => $post_conversions,
				'rate'        => $rate,
			);
		}

		return $results;
	}

	/**
	 * Get a human-readable label for a nag type.
	 *
	 * @param string $nag_type The nag type string.
	 * @return string
	 */
	public static function get_nag_type_label( $nag_type ) {
		if ( 'subscribe' === $nag_type ) {
			return __( 'Subscribe', 'leaky-paywall' );
		}

		if ( 'upgrade' === $nag_type ) {
			return __( 'Upgrade', 'leaky-paywall' );
		}

		if ( 0 === strpos( $nag_type, 'targeted:' ) ) {
			$message_id = (int) substr( $nag_type, 9 );
			$title      = get_the_title( $message_id );

			if ( $title ) {
				return sprintf( __( 'Targeted: %s', 'leaky-paywall' ), $title );
			}

			return sprintf( __( 'Targeted: #%d', 'leaky-paywall' ), $message_id );
		}

		return $nag_type;
	}
}

LP_Nag_Impressions::init();
