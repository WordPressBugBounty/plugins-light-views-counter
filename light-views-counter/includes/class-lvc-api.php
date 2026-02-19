<?php
/**
 * REST API handler class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Base_API
 *
 * Handles REST API endpoints for view counting.
 */
class LIGHTVC_Base_API {

	/**
	 * API namespace.
	 */
	const NAMESPACE = 'lightvc/v1';

	/**
	 * Rate limit key prefix.
	 */
	const RATE_LIMIT_PREFIX = 'lightvc_rate_limit_';

	/**
	 * Initialize the API.
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		// Always register POST endpoint for counting views
		register_rest_route(
			self::NAMESPACE,
			'/count',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'count_view' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'post_id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => [ __CLASS__, 'validate_post_id' ],
					],
				],
			]
		);

		//GET endpoint (always available for editors)
		register_rest_route(
			self::NAMESPACE,
			'/views/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_views' ],
				'permission_callback' => [ __CLASS__, 'get_views_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Validate post ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_post_id( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );

		return $post && 'publish' === $post->post_status;
	}

	/**
	 * Count a view for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public static function count_view( $request ) {
		$post_id = $request->get_param( 'post_id' );

		// Check if user should be excluded (e.g., administrators)
		if ( LIGHTVC_Counter::should_exclude_user() ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => esc_html__( 'User excluded', 'light-views-counter' ),
				],
				200
			);
		}

		// Check rate limiting (primary security measure)
		$rate_limit_check = self::check_rate_limit( $post_id );
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		// Increment view count
		$success = LIGHTVC_Database::increment_views( $post_id );

		if ( $success ) {
			// Set rate limit
			self::set_rate_limit( $post_id );

			return new WP_REST_Response(
				[
					'success'    => true,
					'post_id'    => $post_id,
					'view_count' => LIGHTVC_Database::get_views( $post_id ),
					'message'    => esc_html__( 'Ok', 'light-views-counter' ),
				],
				200
			);
		}

		return new WP_Error(
			'count_failed',
			esc_html__( 'Failed', 'light-views-counter' ),
			[ 'status' => 500 ]
		);
	}

	/**
	 * Check rate limiting for a post view count.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited.
	 */
	private static function check_rate_limit( $post_id ) {
		$ip_address = self::get_client_ip();
		$cache_key  = self::RATE_LIMIT_PREFIX . $post_id . '_' . md5( $ip_address );

		$last_count = wp_cache_get( $cache_key, 'lightvc' );

		if ( false !== $last_count ) {
			return new WP_Error(
				'rate_limited',
				esc_html__( 'Rate Limited', 'light-views-counter' ),
				[ 'status' => 429 ]
			);
		}

		return true;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		$ip_address = '';

		// Check for various proxy headers
		$headers_to_check = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $headers_to_check as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip_address = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				// If X-Forwarded-For contains multiple IPs, get the first one
				if ( strpos( $ip_address, ',' ) !== false ) {
					$ip_addresses = explode( ',', $ip_address );
					$ip_address   = trim( $ip_addresses[0] );
				}

				// Validate IP address
				if ( filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
					break;
				}
			}
		}

		return $ip_address ?: '0.0.0.0';
	}

	/**
	 * Set rate limit for a post view count.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True on success.
	 */
	private static function set_rate_limit( $post_id ) {
		$ip_address = self::get_client_ip();
		$cache_key  = self::RATE_LIMIT_PREFIX . $post_id . '_' . md5( $ip_address );

		// Get time window from settings (in minutes)
		$time_window = absint( get_option( 'lightvc_time_window', 30 ) );

		// Set transient for the time window
		return wp_cache_set( $cache_key, time(), 'lightvc', $time_window * 60 );
	}

	/**
	 * Get view count for a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public static function get_views( $request ) {
		$post_id = $request->get_param( 'id' );

		$view_count = LIGHTVC_Database::get_views( $post_id );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'post_id' => $post_id,
					'views'   => $view_count,
				],
			],
			200
		);
	}

	/**
	 * Permission callback for the get_views endpoint.
	 *
	 * Determines whether the current user has permission to access the view count.
	 * Access is granted if:
	 * - The GET endpoint is enabled in plugin options, OR
	 * - The current user has capability to edit posts.
	 *
	 * @return bool True if access is allowed, false otherwise.
	 */
	public static function get_views_permission() {
		// Allow if GET endpoint is enabled OR user can edit posts
		return get_option( 'lightvc_enable_get_endpoint', 0 ) || current_user_can( 'edit_posts' );
	}
}
