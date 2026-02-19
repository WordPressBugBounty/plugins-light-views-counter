<?php
/**
 * Metaboxes handler class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LIGHTVC_Metaboxes {

	/**
	 * Menu ID returned from add_submenu_page.
	 *
	 * @var string
	 */
	public static $menu_id;
	/**
	 * Parent menu slug for Foxiz Admin integration.
	 *
	 * @var string
	 */
	private static $parent_slug = 'foxiz-admin';

	/**
	 * Initialize the admin.
	 */
	public static function init() {

		// Post editor meta box
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_meta_box_data' ], 200, 2 );

		// Enqueue meta box assets
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_meta_box_assets' ] );

	}

	/**
	 * Enqueue meta box assets.
	 *
	 * Loads CSS and JavaScript files for the meta box only on post editor screens.
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_meta_box_assets( $hook_suffix ) {
		// Only load on post editor screens
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		// Get current post type
		global $post;
		if ( ! $post ) {
			return;
		}

		// Check if current post type is supported
		$supported_post_types = get_option( 'lightvc_supported_post_types', [ 'post' ] );
		if ( ! in_array( $post->post_type, $supported_post_types, true ) ) {
			return;
		}

		// Enqueue meta box stylesheet
		wp_enqueue_style(
			'lightvc-meta-box',
			plugins_url( 'css/lvc-meta-box.css', __FILE__ ),
			[],
			LIGHTVC_VERSION
		);

		// Enqueue meta box script
		wp_enqueue_script(
			'lightvc-meta-box',
			plugins_url( 'js/lvc-meta-box.js', __FILE__ ),
			[ 'jquery' ],
			LIGHTVC_VERSION,
			true
		);
	}

	/**
	 * Register meta box for post editor.
	 *
	 * Adds a meta box to supported post types for editing view counts.
	 *
	 * @since 1.0.0
	 */
	public static function register_meta_box() {
		// Get supported post types
		$supported_post_types = get_option( 'lightvc_supported_post_types', [ 'post' ] );

		// Add meta box for each supported post type
		foreach ( $supported_post_types as $post_type ) {
			add_meta_box(
					'lightvc_views_meta_box',
					esc_html__( 'Post Views', 'light-views-counter' ),
					[ __CLASS__, 'render_meta_box' ],
					$post_type,
					'side',
					'default'
			);
		}
	}

	/**
	 * Render meta box content.
	 *
	 * Displays current view count and allows manual override.
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @since 1.0.0
	 */
	public static function render_meta_box( $post ) {
		// Add nonce for security
		wp_nonce_field( 'lightvc_save_meta_box', 'lightvc_meta_box_nonce' );

		// Get current view count from database
		$current_views = LIGHTVC_Database::get_views( $post->ID );
		?>
		<div class="lvc-meta-box-content">
			<p>
				<strong><?php esc_html_e( 'Current Light Views Counter:', 'light-views-counter' ); ?></strong>
				<span>
					<?php echo esc_html( number_format_i18n( $current_views ) ); ?>
				</span>
			</p>
			<!-- Button to show input field -->
			<button type="button" id="lvc-show-input-btn" class="lvc-action-btn button-primary">
				<span class="dashicons dashicons-visibility"></span>
				<span><?php esc_html_e( 'Change View Count', 'light-views-counter' ); ?></span>
			</button>
			<!-- Input field (hidden by default) -->
			<div id="lvc-input-container">
				<p>
					<label for="lightvc_custom_views">
						<?php esc_html_e( 'New View Count:', 'light-views-counter' ); ?>
					</label>
				</p>
				<input
						type="number"
						id="lightvc_custom_views"
						name="lightvc_custom_views"
						value=""
						min="0"
						step="1"
						class="lvc-setting-input"
						placeholder="<?php echo esc_attr( $current_views ); ?>"
				/>
				<p class="description">
					<?php esc_html_e( 'Enter new view count and save the post to apply changes. This action will directly override the current view data.', 'light-views-counter' ); ?>
				</p>
				<button type="button" id="lvc-cancel-btn" class="lvc-action-btn button-secondary">
					<?php esc_html_e( 'Cancel', 'light-views-counter' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * Sets view count directly in database when post is saved.
	 * Optimized for speed - no post meta, direct database update only.
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post Post object.
	 *
	 * @since 1.0.0
	 */
	public static function save_meta_box_data( $post_id, $post ) {

		// Security checks
		if ( ! isset( $_POST['lightvc_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lightvc_meta_box_nonce'] ) ), 'lightvc_save_meta_box' ) ) {
			return;
		}

		// Skip autosaves
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if post type is supported
		$supported_post_types = get_option( 'lightvc_supported_post_types', [ 'post' ] );
		if ( ! in_array( $post->post_type, $supported_post_types, true ) ) {
			return;
		}

		// Get custom views value
		if ( ! isset( $_POST['lightvc_custom_views'] ) || '' === $_POST['lightvc_custom_views'] ) {
			return; // Empty = keep current value
		}

		$new_views = absint( wp_unslash( $_POST['lightvc_custom_views'] ) );

		LIGHTVC_Database::override_views( $post_id, $new_views );

		// Clear cache for this post
		LIGHTVC_Cache::delete_post_cache( $post_id );
	}
}