<?php
/**
 * Display Functions Class
 *
 * Handles frontend display of view counts including auto-append to content
 * and template tag helpers.
 *
 * @package Light_Views_Counter
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LIGHTVC_Display Class
 *
 * Manages display of view counts on the frontend.
 * Follows WordPress.org plugin standards with static methods and proper initialization.
 *
 * @since 1.0.0
 */
class LIGHTVC_Display {

	/**
	 * Allowed HTML tags for output (better performance than wp_kses_post).
	 *
	 * @var array
	 */
	public static $allowed_html = [
		'div'    => [ 'class' => true, 'id' => true, 'style' => true ],
		'span'   => [ 'class' => true, 'id' => true, 'style' => true ],
		'strong' => [ 'class' => true ],
		'b'      => [],
		'i'      => [ 'class' => true ],
		'em'     => [],
		'a'      => [ 'href' => true, 'title' => true, 'class' => true, 'rel' => true, 'target' => true ],
		'svg'    => [ 'class' => true, 'fill' => true, 'viewbox' => true, 'xmlns' => true ],
		'path'   => [ 'd' => true ],
	];

	/**
	 * Initialize display hooks and filters.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Auto-display view count at the end of post content.
		add_filter( 'the_content', [ __CLASS__, 'append_views_to_content' ], 20 );
	}

	/**
	 * Auto-display view count at the end of post content.
	 *
	 * Appends a beautifully styled view counter to post content when enabled in settings.
	 * Only runs on singular posts/pages, not in admin or feeds.
	 *
	 * @param string $content Post content.
	 *
	 * @return string Modified content with view count appended.
	 * @since 1.0.0
	 *
	 */
	public static function append_views_to_content( $content ) {
		// Early return if not on singular post/page, or in admin/feed/home/front page, or built with Elementor.
		if (
			! is_singular() ||
			is_admin() ||
			is_feed() ||
			is_home() ||
			is_front_page() ||
			( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->documents->get( get_the_ID() )->is_built_with_elementor() )
		) {
			return $content;
		}

		// Check if auto-display is enabled.
		if ( ! get_option( 'lightvc_show_views_on_content', 0 ) ) {
			return $content;
		}

		// Get current post ID.
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Get and format view count.
		$views           = lightvc_get_post_views( $post_id );
		$views_formatted = number_format_i18n( $views );

		// Build HTML output.
		$views_html = self::render_views_html( $views, $views_formatted, $post_id );

		// Load styles
		wp_enqueue_style( 'lightvc-styles' );

		// Append view counter to content.
		return $content . $views_html;
	}

	/**
	 * Render the views HTML output.
	 *
	 * Generates the styled HTML for displaying view count.
	 * Separated into its own method for better maintainability and testability.
	 *
	 * @param int $views The raw view count.
	 * @param string $views_formatted The formatted view count.
	 * @param int $post_id The post ID.
	 *
	 * @return string HTML output for views.
	 * @since 1.0.0
	 *
	 */
	private static function render_views_html( $views, $views_formatted, $post_id ) {
		// Build HTML with inline styles (avoids extra HTTP request).
		$views_html = sprintf(
			'<div class="lvc-post-views">
				<span class="lvc-view-icon">
					<svg fill="currentColor" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">
						<path d="m6 17a4 4 0 0 0 -4 4v8a4 4 0 0 0 8 0v-8a4 4 0 0 0 -4-4z"/>
						<path d="m17 1a4 4 0 0 0 -4 4v24a4 4 0 0 0 8 0v-24a4 4 0 0 0 -4-4z"/>
						<path d="m28 9a4 4 0 0 0 -4 4v16a4 4 0 0 0 8 0v-16a4 4 0 0 0 -4-4z"/>
					</svg>
				</span>
				<span class="lvc-views-label">%s:</span>
				<strong class="lvc-views-count">%s</strong>
			</div>',
			esc_html__( 'Total Views', 'light-views-counter' ),
			esc_html( $views_formatted )
		);

		/**
		 * Filters the auto-display HTML output.
		 *
		 * Allows complete customization of the view counter display.
		 *
		 * @param string $views_html The HTML output for views.
		 * @param int $views The view count.
		 * @param int $post_id The post ID.
		 *
		 * @since 1.0.0
		 *
		 */
		return wp_kses( apply_filters( 'lightvc_views_html', $views_html, $views, $post_id ), self::$allowed_html );
	}
}
