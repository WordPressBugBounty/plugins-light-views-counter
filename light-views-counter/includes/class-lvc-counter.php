<?php
/**
 * Counter class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Counter
 *
 * Handles frontend view counting logic.
 */
class LIGHTVC_Counter {

	/**
	 * Initialize the counter.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ], 150 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ], 160 );
	}

	/**
	 * Enqueue frontend styles.
	 */
	public static function enqueue_styles() {

		// Register styles globally
		wp_register_style(
			'lightvc-styles',
			LIGHTVC_PLUGIN_URL . 'public/css/lvc-styles.css',
			[],
			LIGHTVC_VERSION
		);

		// Enqueue CSS in header if option is enabled
		if ( get_option( 'lightvc_load_css_in_header', 0 ) ) {
			wp_enqueue_style( 'lightvc-styles' );
		}
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public static function enqueue_scripts() {

		// Only load tracking script on single posts/pages
		if ( ! is_singular() ) {
			return;
		}

		global $post;

		// Skip if no post object
		if ( ! $post ) {
			return;
		}

		// Skip if post is not published
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Enqueue the tracking script
		wp_register_script(
			'lvc-tracker',
			LIGHTVC_PLUGIN_URL . 'public/js/lvc-tracker.js',
			[],
			LIGHTVC_VERSION,
			true
		);

		// Localize script with data
		wp_localize_script(
			'lvc-tracker',
			'lightvcData',
			[
				'postId'          => $post->ID,
				'ajaxUrl'         => rest_url( 'lightvc/v1/count' ),
				'scrollThreshold' => min( 95, absint( get_option( 'lightvc_scroll_threshold', 50 ) ) ),
				'timeWindow'      => absint( get_option( 'lightvc_time_window', 30 ) ),
				'fastMode'        => (bool) get_option( 'lightvc_fast_mode', 0 ),
				'excludeBots'     => (bool) get_option( 'lightvc_exclude_bots', 1 ),
			]
		);

		wp_enqueue_script( 'lvc-tracker' );
	}

	/**
	 * Check if user should be excluded from counting.
	 *
	 * @return bool True if user should be excluded.
	 */
	public static function should_exclude_user() {
		// Exclude logged-in administrators
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		/**
		 * Filter to exclude specific users from counting.
		 *
		 * @param bool $exclude True to exclude, false to include.
		 */
		return apply_filters( 'lightvc_exclude_user', false );
	}
}
