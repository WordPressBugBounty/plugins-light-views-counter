<?php
/**
 * Query Integration Class
 *
 * Handles WP_Query integration and Foxiz theme query modifications.
 * Extends WordPress query functionality to support ordering and filtering by view counts.
 *
 * @package Light_Views_Counter
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LIGHTVC_Query Class
 *
 * Integrates view counting with WordPress queries and custom queries.
 *
 * @since 1.0.0
 */
class LIGHTVC_Query {

	/**
	 * Initialize query hooks and filters.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Get query method setting
		$query_method = get_option( 'lightvc_query_method', 'subquery' );

		// Hook appropriate methods based on query method setting
		if ( 'join' === $query_method ) {
			// Use JOIN method for large databases
			add_filter( 'posts_join', [ __CLASS__, 'posts_join' ], 10, 2 );
			add_filter( 'posts_orderby', [ __CLASS__, 'posts_orderby_join' ], 10, 2 );
		} else {
			// Use subquery method (default)
			add_filter( 'posts_orderby', [ __CLASS__, 'posts_orderby' ], 10, 2 );
		}
	}

	/**
	 * Extend WP_Query to support ordering by views.
	 *
	 * Allows posts to be sorted by view count using standard WP_Query.
	 * Example: new WP_Query( array( 'orderby' => 'lightvc_views' ) );
	 *
	 * Supports both 'lightvc_views' and 'post_views'.
	 *
	 * @param string $orderby The ORDER BY clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified ORDER BY clause.
	 * @since 1.0.0
	 *
	 */
	public static function posts_orderby( $orderby, $query ) {
		$orderby_param = $query->get( 'orderby' );

		// Check if ordering by views is requested.
		if ( self::is_views_orderby( $orderby_param ) ) {
			global $wpdb;

			// Use subquery with COALESCE to handle posts with no views (returns 0).
			$orderby = "(SELECT COALESCE(view_count, 0) FROM {$wpdb->prefix}lightvc_post_views WHERE post_id = {$wpdb->posts}.ID) DESC";
		}

		return $orderby;
	}

	/**
	 * Check if orderby parameter is for views.
	 *
	 * Helper method to check if an orderby parameter matches view sorting keys.
	 * Prevents code duplication across multiple methods.
	 *
	 * @param string $orderby_param The orderby parameter to check.
	 *
	 * @return bool True if ordering by views, false otherwise.
	 * @since 1.0.0
	 *
	 */
	public static function is_views_orderby( $orderby_param ) {
		return ( 'lightvc_views' === $orderby_param || ( 'post_views' === $orderby_param && ! class_exists( 'Post_Views_Counter' ) ) );
	}

	/**
	 * Join views table to WP_Query for better performance.
	 *
	 * Alternative approach using JOIN instead of subquery for large datasets.
	 * Currently not active - can be enabled if subquery performance is an issue.
	 *
	 * @param string $join The JOIN clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified JOIN clause.
	 * @since 1.0.0
	 *
	 */
	public static function posts_join( $join, $query ) {
		$orderby_param = $query->get( 'orderby' );

		if ( self::is_views_orderby( $orderby_param ) ) {
			global $wpdb;

			// LEFT JOIN to include posts with no views.
			$join .= " LEFT JOIN {$wpdb->prefix}lightvc_post_views ON {$wpdb->posts}.ID = {$wpdb->prefix}lightvc_post_views.post_id";
		}

		return $join;
	}

	/**
	 * Modify ORDER BY clause when using JOIN method.
	 *
	 * Works in conjunction with posts_join() for JOIN-based ordering.
	 * Currently not active - can be enabled if subquery performance is an issue.
	 *
	 * @param string $orderby The ORDER BY clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified ORDER BY clause.
	 * @since 1.0.0
	 *
	 */
	public static function posts_orderby_join( $orderby, $query ) {
		$orderby_param = $query->get( 'orderby' );

		if ( self::is_views_orderby( $orderby_param ) ) {
			global $wpdb;

			// Order by view count from Light Views Counter table.
			// Use COALESCE to handle NULL values from LEFT JOIN.
			$orderby = "COALESCE({$wpdb->prefix}lightvc_post_views.view_count, 0) DESC";
		}

		return $orderby;
	}

	/**
	 * Filter posts by minimum view count.
	 *
	 * Allows filtering posts that have at least X views.
	 * Usage: new WP_Query( array( 'lightvc_min_views' => 100 ) );
	 *
	 * @param string $where The WHERE clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 *
	 */
	public static function posts_where( $where, $query ) {

		$min_views = $query->get( 'lightvc_min_views' );

		if ( $min_views && is_numeric( $min_views ) ) {

			global $wpdb;
			$min_views = absint( $min_views );

			// Add subquery to filter by minimum views.
			$where .= $wpdb->prepare(
				" AND {$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->prefix}lightvc_post_views WHERE view_count >= %d)",
				$min_views
			);
		}

		return $where;
	}

	/**
	 * Add view count to query results.
	 *
	 * Attaches view count as a property to each post object in query results.
	 * Usage: Access via $post->view_count after query.
	 *
	 * @param array $posts Array of post objects.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return array Modified array of post objects with view_count property.
	 * @since 1.0.0
	 *
	 */
	public static function the_posts( $posts, $query ) {

		// Only add view counts if specifically requested.
		if ( ! $query->get( 'lightvc_add_views' ) ) {
			return $posts;
		}

		if ( empty( $posts ) ) {
			return $posts;
		}

		// Get all post IDs.
		$post_ids = wp_list_pluck( $posts, 'ID' );

		// Get view counts for all posts in a single query.
		global $wpdb;

		// Prepare placeholders for IN ( %d, %d, %d ...)
		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

		$sql = "SELECT post_id, view_count 
        FROM {$wpdb->prefix}lightvc_post_views 
        WHERE post_id IN ($placeholders)";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $sql, $post_ids ),
			OBJECT_K
		);

		// Attach view counts to post objects.
		foreach ( $posts as $post ) {
			$post->view_count = isset( $results[ $post->ID ] ) ? (int) $results[ $post->ID ]->view_count : 0;
		}

		return $posts;
	}

	/**
	 * Check if current query is ordering by views.
	 *
	 * Helper method to determine if a query is using view-based sorting.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return bool True if ordering by views, false otherwise.
	 * @since 1.0.0
	 *
	 */
	public static function is_views_query( $query ) {
		$orderby = $query->get( 'orderby' );

		return self::is_views_orderby( $orderby );
	}
}