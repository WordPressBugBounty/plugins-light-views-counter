<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Light_Views_Counter
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin database table, options, and transients.
 *
 * This function completely removes all plugin data from the database
 * when the plugin is uninstalled (not just deactivated).
 */
function lightvc_uninstall() {
	// Security check: Verify user has capability to uninstall plugins.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lightvc_post_views" );

	// Delete all plugin options
	$options = [
		'lightvc_scroll_threshold',
		'lightvc_time_window',
		'lightvc_cache_duration',
		'lightvc_enable_caching',
		'lightvc_fast_mode',
		'lightvc_show_views_on_content',
		'lightvc_enable_get_endpoint',
		'lightvc_db_version',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear WordPress object cache
	wp_cache_flush();
}

lightvc_uninstall();
