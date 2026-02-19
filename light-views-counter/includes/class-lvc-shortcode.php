<?php
/**
 * Shortcode Handler Class
 *
 * Handles all shortcode functionality for displaying post views.
 * Provides multiple display styles and customization options.
 *
 * @package Light_Views_Counter
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LIGHTVC_Shortcode class.
 *
 * Manages shortcode registration and rendering for post views display.
 *
 * @since 1.0.0
 */
class LIGHTVC_Shortcode {

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
	 * Initialize shortcode functionality.
	 *
	 * Registers the shortcode with WordPress.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_shortcode( 'lightvc_post_views', [ __CLASS__, 'render' ] );
	}

	/**
	 * Render shortcode output.
	 *
	 * Provides a flexible shortcode with multiple styles and customization options.
	 * Can be used anywhere shortcodes are supported (posts, pages, widgets, etc.).
	 *
	 * Available styles: default, minimal, badge, compact
	 *
	 * Usage examples:
	 * - Basic: [lightvc_post_views]
	 * - Custom style: [lightvc_post_views style="badge"]
	 * - Custom label: [lightvc_post_views label="Total Reads"]
	 * - Specific post: [lightvc_post_views post_id="123"]
	 * - Full customization: [lightvc_post_views post_id="123" style="badge" label="Views" icon="📊"]
	 *
	 * @param array $atts {
	 *     Shortcode attributes.
	 *
	 * @type int $post_id Post ID to display views for. Default current post.
	 * @type string $label Label text to display. Default 'Views'.
	 * @type string $show_label Whether to show label. Default 'true'.
	 * @type string $style Display style. Default 'default'. Accepts 'default', 'minimal', 'badge', 'compact'.
	 * }
	 * @return string HTML output for the shortcode.
	 * @since 1.0.0
	 *
	 */
	public static function render( $atts ) {
		// Parse and sanitize attributes.
		$atts = shortcode_atts(
			[
				'post_id'    => get_the_ID(),
				'label'      => esc_html__( 'Views', 'light-views-counter' ),
				'show_label' => 'true',
				'style'      => 'default',
			],
			$atts,
			'lightvc_post_views'
		);

		// Sanitize attributes.
		$post_id    = absint( $atts['post_id'] );
		$label      = sanitize_text_field( $atts['label'] );
		$show_label = filter_var( $atts['show_label'], FILTER_VALIDATE_BOOLEAN );
		$style      = sanitize_key( $atts['style'] );

		// Validate post exists.
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return '';
		}

		// Get and format view count.
		$views           = lightvc_get_post_views( $post_id );
		$views_formatted = number_format_i18n( $views );

		// Generate HTML based on selected style.
		$output = self::get_html( $style, $label, $show_label, $views_formatted );

		// Load styles
		wp_enqueue_style( 'lightvc-styles' );

		/**
		 * Filters the shortcode HTML output.
		 *
		 * Allows developers to customize or replace the entire shortcode output.
		 *
		 * @param string $output The complete HTML output (including styles).
		 * @param int $views The view count.
		 * @param int $post_id The post ID.
		 * @param array $atts Shortcode attributes.
		 *
		 * @since 1.0.0
		 *
		 */
		return wp_kses( apply_filters( 'lightvc_shortcode_html', $output, $views, $post_id, $atts ), self::$allowed_html );
	}


	/**
	 * Generate HTML markup based on selected style.
	 *
	 * Creates the appropriate HTML structure for the chosen display style.
	 *
	 * @param string $style Display style (default, minimal, badge, compact).
	 * @param string $label Label text.
	 * @param bool $show_label Whether to show the label.
	 * @param string $views_formatted Formatted view count.
	 *
	 * @return string HTML markup.
	 * @since 1.0.0
	 *
	 */
	private static function get_html( $style, $label, $show_label, $views_formatted ) {

		$svg = '<span class="lvc-icon">
				<svg fill="currentColor" viewBox="0 0 34 34" xmlns="http://www.w3.org/2000/svg">
				<path d="m6 17a4 4 0 0 0 -4 4v8a4 4 0 0 0 8 0v-8a4 4 0 0 0 -4-4z"/>
				<path d="m17 1a4 4 0 0 0 -4 4v24a4 4 0 0 0 8 0v-24a4 4 0 0 0 -4-4z"/>
				<path d="m28 9a4 4 0 0 0 -4 4v16a4 4 0 0 0 8 0v-16a4 4 0 0 0 -4-4z"/></svg>
				</span>';

		$label_html = $show_label ? '<span class="lvc-label">' . esc_html( $label ) . ':</span>' : '';
		$views_html = '<span class="lvc-count">' . esc_html( $views_formatted ) . '</span>';

		$styles = [
			'minimal' => '<span class="lvc-views lvc-views-minimal">%s%s%s</span>',
			'badge'   => '<span class="lvc-views lvc-views-badge">%s%s%s</span>',
			'compact' => '<span class="lvc-views lvc-views-compact">%s%s</span>',
			'default' => '<div class="lvc-views lvc-views-default">%s%s%s</div>',
		];

		$format = isset( $styles[ $style ] ) ? $styles[ $style ] : $styles['default'];

		return sprintf( $format, $svg, $label_html, $style === 'compact' ? $views_html : $views_html );
	}
}
