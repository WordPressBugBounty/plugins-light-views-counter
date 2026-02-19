<?php
/**
 * Cache handler class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Cache
 *
 * Handles caching operations using WordPress caches.
 */
class LIGHTVC_Cache {

	/**
	 * Get cached post views count.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int|false View count or false if not cached.
	 */
	public static function get_post_views( $post_id ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$cache_key = 'lightvc_views_' . absint( $post_id );
		$cached    = wp_cache_get( $cache_key, 'lightvc' );

		return false !== $cached ? absint( $cached ) : false;
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool True if caching is enabled.
	 */
	public static function is_enabled() {
		return (bool) get_option( 'lightvc_enable_caching', 1 );
	}

	/**
	 * Set cached post views count.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count View count.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set_post_views( $post_id, $count ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$cache_key = 'lightvc_views_' . absint( $post_id );
		$duration  = self::get_duration();

		return wp_cache_set( $cache_key, absint( $count ), 'lightvc', $duration );
	}

	/**
	 * Get cache duration in seconds.
	 *
	 * @return int Cache duration in seconds.
	 */
	public static function get_duration() {
		return absint( get_option( 'lightvc_cache_duration', 300 ) );
	}

	/**
	 * Delete cached post views count.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete_post_cache( $post_id ) {
		$cache_key = 'lightvc_views_' . absint( $post_id );

		return wp_cache_delete( $cache_key, 'lightvc' );
	}

	/**
	 * Clear all plugin caches.
	 *
	 * Clears object cache.
	 *
	 * @return bool True on success.
	 */
	public static function clear_all() {

		// Clear WordPress object cache
		wp_cache_flush();

		return true;
	}

	/**
	 * Warm up cache for popular posts.
	 *
	 * Preloads cache with frequently accessed data.
	 *
	 * @return void
	 */
	public static function warmup() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Preload popular posts with default arguments
		$default_args = [
			'limit'      => 10,
			'post_type'  => 'post',
			'date_range' => 0,
		];

		// Check if already cached
		$cached = self::get_popular_posts( $default_args );
		if ( false === $cached ) {
			// Not cached, load and cache it
			$results = LIGHTVC_Database::get_popular_posts( $default_args );
			self::set_popular_posts( $default_args, $results );
		}
	}

	/**
	 * Get cached popular posts.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array|false Popular posts or false if not cached.
	 */
	public static function get_popular_posts( $args ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$cache_key = 'lightvc_popular_' . md5( wp_json_encode( $args ) );
		$cached    = wp_cache_get( $cache_key, 'lightvc' );

		return false !== $cached ? $cached : false;
	}

	/**
	 * Set cached popular posts.
	 *
	 * @param array $args Query arguments.
	 * @param array $results Popular posts data.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set_popular_posts( $args, $results ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$cache_key = 'lightvc_popular_' . md5( wp_json_encode( $args ) );
		$duration  = self::get_duration();

		return wp_cache_set( $cache_key, $results, 'lightvc', $duration );
	}

	/**
	 * Schedule cache warmup.
	 *
	 * @return void
	 */
	public static function schedule_warmup() {
		if ( ! wp_next_scheduled( 'lightvc_cache_warmup' ) ) {
			wp_schedule_event( time(), 'hourly', 'lightvc_cache_warmup' );
		}
	}

	/**
	 * Unschedule cache warmup.
	 *
	 * @return void
	 */
	public static function unschedule_warmup() {
		$timestamp = wp_next_scheduled( 'lightvc_cache_warmup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'lightvc_cache_warmup' );
		}
	}
}

// Register cache warmup hook
add_action( 'lightvc_cache_warmup', [ 'LIGHTVC_Cache', 'warmup' ] );
