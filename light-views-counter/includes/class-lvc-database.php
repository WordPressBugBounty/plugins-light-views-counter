<?php
/**
 * Database handler class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Database
 *
 * Handles all database operations for the plugin.
 */
class LIGHTVC_Database {

	/**
	 * Database version for table structure updates.
	 */
	const DB_VERSION = '1.0';

	/**
	 * Create custom database table on plugin activation.
	 *
	 * Creates the views tracking table with proper indexes for performance.
	 * Uses dbDelta() for safe table creation and updates.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// NOTE: dbDelta requires specific formatting:
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id bigint(20) UNSIGNED NOT NULL,
			view_count bigint(20) UNSIGNED DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version for future updates
		update_option( 'lightvc_db_version', self::DB_VERSION );
	}

	/**
	 * Get table name with WordPress prefix.
	 *
	 * @return string Table name.
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'lightvc_post_views';
	}

	/**
	 * Override the view count for a specific post.
	 *
	 * This function updates the view count directly in the custom database table
	 * using an atomic `INSERT ... ON DUPLICATE KEY UPDATE` query for performance.
	 *
	 * @param int $post_id The ID of the post for which views should be overridden.
	 * @param int $new_views The new view count to be set for the post.
	 *
	 * @return bool True on success, false on failure (e.g., invalid parameters).
	 */
	public static function override_views( $post_id, $new_views ) {

		$post_id   = absint( $post_id );
		$new_views = absint( $new_views );

		if ( ! $post_id || ! $new_views ) {
			return false;
		}

		// Update directly in database (optimized for speed)
		global $wpdb;

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "INSERT INTO $table_name (post_id, view_count) VALUES (%d, %d) ON DUPLICATE KEY UPDATE view_count = %d";

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id,
				$new_views,
				$new_views
			)
		);
	}

	/**
	 * Increment view count for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function increment_views( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		// Verify post exists and is published
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "INSERT INTO $table_name (post_id, view_count) VALUES (%d, 1) ON DUPLICATE KEY UPDATE view_count = view_count + 1";

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		if ( $result ) {
			// Clear cache for this post
			LIGHTVC_Cache::delete_post_cache( $post_id );

			/**
			 * Action fired after a view is successfully counted.
			 *
			 * @param int $post_id The post ID.
			 */
			do_action( 'lightvc_views_counted', $post_id );

			return true;
		}

		return false;
	}

	/**
	 * Get view count for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int View count.
	 */
	public static function get_views( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return 0;
		}

		// Try to get from cache first
		$cached = LIGHTVC_Cache::get_post_views( $post_id );
		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "SELECT view_count FROM $table_name WHERE post_id = %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		$count = $count ? absint( $count ) : 0;

		// Cache the result
		LIGHTVC_Cache::set_post_views( $post_id, $count );

		return $count;
	}

	/**
	 * Get statistics data.
	 *
	 * @return array Statistics data.
	 */
	public static function get_statistics() {
		global $wpdb;

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "SELECT COUNT(*) FROM $table_name WHERE view_count > 0";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total_posts = $wpdb->get_var( $sql );

		$stats = [
			'total_views'       => self::get_total_views(),
			'total_posts'       => $total_posts,
			'average_views'     => 0,
			'most_viewed_today' => [],
		];

		if ( $stats['total_posts'] > 0 ) {
			$stats['average_views'] = round( $stats['total_views'] / $stats['total_posts'] );
		}

		return $stats;
	}

	/**
	 * Get total views across all posts.
	 *
	 * @return int Total view count.
	 */
	public static function get_total_views() {
		global $wpdb;

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "SELECT SUM(view_count) FROM $table_name";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = $wpdb->get_var( $sql );

		return $total ? absint( $total ) : 0;
	}

	/**
	 * Get popular posts based on view count.
	 *
	 * This method performs an optimized query to fetch posts sorted by view count.
	 * Results are cached for performance (first page only).
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 * @type int $limit Number of posts to return. Default 10.
	 * @type string $post_type Post type to query. Default 'post'.
	 * @type int $date_range Days to filter by (0 = all time). Default 0.
	 * @type int $offset Query offset for pagination. Default 0.
	 * }
	 * @return array Array of post objects with ID, title, date, and views properties.
	 */
	public static function get_popular_posts( $args = [] ) {
		global $wpdb;

		// Set default arguments
		$defaults = [
			'limit'      => 10,
			'post_type'  => 'post',
			'date_range' => 0,  // 0 = all time, 7 = last 7 days, etc.
			'offset'     => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		// Check cache first (only for first page to avoid cache bloat)
		if ( 0 === $args['offset'] ) {
			$cached = LIGHTVC_Cache::get_popular_posts( $args );
			if ( false !== $cached ) {
				return $cached;  // Return cached results
			}
		}

		$table_name = self::get_table_name();
		$limit      = absint( $args['limit'] );
		$offset     = absint( $args['offset'] );

		// Build post type filter - sanitize for security
		$post_types = (array) $args['post_type'];
		$post_types = array_map( 'sanitize_key', $post_types );
		$post_types = array_filter( $post_types ); // Remove empty values

		// Fallback to 'post' if no valid post types
		if ( empty( $post_types ) ) {
			$post_types = [ 'post' ];
		}

		// Build date filter
		$date_where = '';
		if ( $args['date_range'] > 0 ) {
			$days       = absint( $args['date_range'] );
			$date_where = $wpdb->prepare(
				" AND {$wpdb->posts}.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			);
		}

		// Create a list of '%s' placeholders matching the number of post types for use in the prepared SQL IN() clause.
		// This ensures each post type is safely passed through $wpdb->prepare() using '%s'.
		$post_type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// Build the full query with proper escaping
		$query = "SELECT
				{$wpdb->posts}.ID,
				{$wpdb->posts}.post_title,
				{$wpdb->posts}.post_date,
				{$wpdb->posts}.post_type,
				COALESCE(v.view_count, 0) as views
			FROM {$wpdb->posts}
			LEFT JOIN {$table_name} v ON {$wpdb->posts}.ID = v.post_id
			WHERE {$wpdb->posts}.post_status = 'publish'
			AND {$wpdb->posts}.post_type IN ({$post_type_placeholders})
			{$date_where}
			ORDER BY views DESC, {$wpdb->posts}.post_date DESC
			LIMIT %d OFFSET %d";

		// Merge post types with limit and offset for prepare()
		$prepare_args = array_merge( $post_types, [ $limit, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $query, $prepare_args ) );

		// Cache the results
		if ( 0 === $offset && ! empty( $results ) ) {
			LIGHTVC_Cache::set_popular_posts( $args, $results );
		}

		return $results ? $results : [];
	}

	/**
	 * Reset view count for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function reset_post_views( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			[ 'post_id' => $post_id ],
			[ '%d' ]
		);

		if ( $result ) {
			LIGHTVC_Cache::delete_post_cache( $post_id );

			return true;
		}

		return false;
	}

	/**
	 * Reset all view counts.
	 *
	 * Truncates the views table, removing all view data.
	 * This operation cannot be undone.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function reset_all_views() {
		global $wpdb;

		$table_name = self::get_table_name();

		// NOTE: Table name is safe because it is constructed from $wpdb->prefix and a fixed table suffix.
		$sql = "TRUNCATE TABLE $table_name";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql );

		if ( false !== $result ) {
			LIGHTVC_Cache::clear_all();

			return true;
		}

		return false;
	}
}
