<?php
/**
 * Plugin Name:       Light Views Counter
 * Plugin URI:        https://themeruby.com/light-views-counter
 * Description:       Lightweight and fast post view counter with smart tracking, built for high-traffic sites and large post databases.
 * Tags:              views, counter, post views, popular posts
 * Author:            ThemeRuby
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author URI:        https://themeruby.com/
 * Text Domain:       light-views-counter
 * Domain Path:       /languages
 *
 * @package           light-views-counter
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// CONSTANTS
// ============================================================================

if ( ! defined( 'LIGHTVC_VERSION' ) ) {
	define( 'LIGHTVC_VERSION', '1.1.0' );
}

if ( ! defined( 'LIGHTVC_PLUGIN_DIR' ) ) {
	define( 'LIGHTVC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'LIGHTVC_PLUGIN_URL' ) ) {
	define( 'LIGHTVC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'LIGHTVC_PLUGIN_BASENAME' ) ) {
	define( 'LIGHTVC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// ============================================================================
// PLUGIN ACTIVATION & DEACTIVATION
// ============================================================================

/**
 * Plugin activation handler.
 *
 * Creates database table, sets default options, and schedules events.
 *
 * @since 1.0.0
 */
function lightvc_activate() {
	// Load required files for activation.
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-database.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-cache.php';

	// Create custom database table with indexes.
	LIGHTVC_Database::create_table();

	// Set default plugin options (only if not already set).
	$default_options = [
		'lightvc_scroll_threshold'      => 50,         // 50% scroll threshold
		'lightvc_time_window'           => 1800,       // 1800 seconds (30 minutes) duplicate prevention
		'lightvc_cache_duration'        => 300,        // 5 minutes cache
		'lightvc_enable_caching'        => 1,          // Caching enabled
		'lightvc_fast_mode'             => 1,          // sendBeacon API enabled
		'lightvc_show_views_on_content' => 0,          // Auto-display disabled by default
		'lightvc_load_css_in_header'    => 0,          // Load CSS disabled by default (on-demand only)
		'lightvc_enable_get_endpoint'   => 0,          // GET endpoint disabled by default for privacy
		'lightvc_supported_post_types'  => [ 'post' ], // Track 'post' type by default
		'lightvc_query_method'          => 'subquery', // Subquery method for ordering (default)
		'lightvc_exclude_bots'          => 1,          // Exclude bots enabled by default
	];

	foreach ( $default_options as $option => $value ) {
		add_option( $option, $value );
	}

	// Schedule cache warmup event for performance.
	LIGHTVC_Cache::schedule_warmup();

	// Clear WordPress object cache
	wp_cache_flush();
}

register_activation_hook( __FILE__, 'lightvc_activate' );

/**
 * Plugin deactivation handler.
 *
 * Cleans up scheduled events and caches.
 *
 * @since 1.0.0
 */
function lightvc_deactivate() {
	// Load cache class for cleanup.
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-cache.php';

	// Unschedule cache warmup event.
	LIGHTVC_Cache::unschedule_warmup();

	// Clear WordPress object cache
	wp_cache_flush();
}

register_deactivation_hook( __FILE__, 'lightvc_deactivate' );

// ============================================================================
// INITIALIZATION
// ============================================================================

/**
 * Initialize plugin core functionality.
 *
 * Loads all plugin classes, registers hooks, and initializes components.
 * This runs on 'plugins_loaded' hook with priority 10.
 *
 * @since 1.0.0
 */
function lightvc_init() {
	// Load core classes.
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-database.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-cache.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-counter.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-api.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-query.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-display.php';
	require_once LIGHTVC_PLUGIN_DIR . 'includes/class-lvc-shortcode.php';

	// Initialize core components.
	LIGHTVC_Counter::init();
	LIGHTVC_Base_API::init();
	LIGHTVC_Query::init();
	LIGHTVC_Display::init();
	LIGHTVC_Shortcode::init();

	// Load WordPress widget.
	require_once LIGHTVC_PLUGIN_DIR . 'widgets/class-lvc-popular-posts-widget.php';

	// Load admin interface (only in admin area).
	if ( is_admin() ) {
		require_once LIGHTVC_PLUGIN_DIR . 'admin/class-lvc-admin.php';
		require_once LIGHTVC_PLUGIN_DIR . 'admin/class-lvc-meta.php';
		require_once LIGHTVC_PLUGIN_DIR . 'admin/class-lvc-admin-stats.php';
		require_once LIGHTVC_PLUGIN_DIR . 'admin/class-lvc-links.php';

		LIGHTVC_Admin::init();
		LIGHTVC_Metaboxes::init();
	}
}

add_action( 'plugins_loaded', 'lightvc_init', 200 );

// ============================================================================
// PUBLIC API FUNCTIONS
// ============================================================================

/**
 * Get post views count.
 *
 * This is the main public API function for retrieving view counts.
 * Uses caching when enabled for better performance.
 *
 * @param int $post_id Post ID to get views for.
 *
 * @return int View count (0 if post doesn't exist).
 * @since 1.0.0
 *
 */
function lightvc_get_post_views( $post_id ) {

	$post_id = absint( $post_id );

	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	// Validate post ID.
	if ( ! $post_id ) {
		return 0;
	}

	// Get view count from database.
	$count = LIGHTVC_Database::get_views( $post_id );

	/**
	 * Filters the post views count before returning.
	 *
	 * Allows developers to modify the view count for custom logic.
	 *
	 * @param int $count The view count.
	 * @param int $post_id The post ID.
	 *
	 * @since 1.0.0
	 *
	 */
	return apply_filters( 'lightvc_post_views_count', $count, $post_id );
}

/**
 * Get popular posts based on view count.
 *
 * Returns an array of post objects ordered by views, with optional
 * date range filtering and post type selection.
 *
 * @param array $args {
 *     Optional. Query arguments.
 *
 * @type int $limit Number of posts to return. Default 10.
 * @type string|array $post_type Post type(s) to query. Default 'post'.
 * @type int $date_range Days to filter (0 = all time). Default 0.
 * @type int $offset Query offset for pagination. Default 0.
 * }
 * @return array Array of post objects with ID, post_title, post_date, and views properties.
 * @since 1.0.0
 *
 */
function lightvc_get_popular_posts( $args = [] ) {
	// Set defaults.
	$defaults = [
		'limit'      => 10,
		'post_type'  => 'post',
		'date_range' => 0, // 0 = all time, 7 = last 7 days, etc.
		'offset'     => 0,
	];

	// Merge with provided arguments.
	$args = wp_parse_args( $args, $defaults );

	// Get popular posts from database.
	return LIGHTVC_Database::get_popular_posts( $args );
}

/**
 * Check if Foxiz Core plugin is active.
 *
 * Helper function for checking Foxiz Core status across different contexts.
 * Checks multiple methods to ensure reliable detection.
 *
 * @return bool True if Foxiz Core is active, false otherwise.
 * @since 1.0.0
 *
 */
function lightvc_is_foxiz_core_active() {
	return class_exists( 'Foxiz_Core' ) ||
	       defined( 'FOXIZ_CORE_PATH' ) ||
	       ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'foxiz-core/foxiz-core.php' ) );
}
