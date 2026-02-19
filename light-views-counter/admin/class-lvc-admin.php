<?php
/**
 * Admin class.
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Admin
 *
 * Handles admin interface and settings.
 *
 */
class LIGHTVC_Admin {

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
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 1000 );
		add_action( 'manage_posts_custom_column', [ __CLASS__, 'display_views_column' ], 10, 2 );
		add_filter( 'manage_posts_columns', [ __CLASS__, 'add_views_column' ] );
		add_filter( 'manage_edit-post_sortable_columns', [ __CLASS__, 'make_views_column_sortable' ] );

		// AJAX handlers
		add_action( 'wp_ajax_lightvc_save_setting', [ __CLASS__, 'ajax_save_setting' ] );
		add_action( 'wp_ajax_lightvc_save_post_types', [ __CLASS__, 'ajax_save_post_types' ] );
		add_action( 'wp_ajax_lightvc_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );
		add_action( 'wp_ajax_lightvc_reset_all_views', [ __CLASS__, 'ajax_reset_all_views' ] );
		add_action( 'wp_ajax_lightvc_get_popular_posts', [ __CLASS__, 'ajax_get_popular_posts' ] );
		add_action( 'wp_ajax_lightvc_import_from_pvc', [ __CLASS__, 'ajax_import_from_pvc' ] );
		add_action( 'wp_ajax_lightvc_reset_posts', [ __CLASS__, 'ajax_reset_posts' ] );
	}

	/**
	 * Add admin menu page.
	 *
	 * If Foxiz Core is active, adds submenu under Foxiz Admin.
	 * Otherwise, adds submenu under Settings.
	 * Uses Foxiz pattern with menu_id and load hook.
	 */
	public static function add_admin_menu() {
		// Check if Foxiz Core is active
		if ( self::is_foxiz_core_active() ) {
			// Add submenu under Foxiz Admin (same pattern as OpenAI Assistant)
			self::$menu_id = add_submenu_page(
					self::$parent_slug,
					esc_html__( 'Light Views Counter', 'light-views-counter' ),
					esc_html__( 'Light Views Counter', 'light-views-counter' ),
					'manage_options',
					'light-views-counter',
					[ __CLASS__, 'render_settings_page' ]
			);

			// Load assets only on our admin page
			add_action( 'load-' . self::$menu_id, [ __CLASS__, 'load_page_assets' ] );
		} else {
			// Fallback: Add under Settings menu if Foxiz Core not active
			self::$menu_id = add_options_page(
					esc_html__( 'Light Views Counter Settings', 'light-views-counter' ),
					esc_html__( 'Light Views Counter', 'light-views-counter' ),
					'manage_options',
					'light-views-counter',
					[ __CLASS__, 'render_settings_page' ]
			);

			// Load assets only on our admin page
			add_action( 'load-' . self::$menu_id, [ __CLASS__, 'load_page_assets' ] );
		}
	}

	/**
	 * Check if Foxiz Core plugin is active.
	 *
	 * @return bool True if Foxiz Core is active.
	 */
	public static function is_foxiz_core_active() {
		// Check if foxiz-core plugin is active
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check multiple ways to detect Foxiz Core
		return is_plugin_active( 'foxiz-core/foxiz-core.php' ) ||
		       class_exists( 'Foxiz_Core' ) ||
		       defined( 'FOXIZ_CORE_PATH' );
	}

	/**
	 * Add views column to posts list.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 */
	public static function add_views_column( $columns ) {
		$columns['lightvc_views'] = esc_html__( 'Light Views', 'light-views-counter' );

		return $columns;
	}

	/**
	 * Display views count in column.
	 *
	 * @param string $column Column name.
	 * @param int $post_id Post ID.
	 */
	public static function display_views_column( $column, $post_id ) {
		if ( 'lightvc_views' === $column ) {
			$views = LIGHTVC_Database::get_views( $post_id );
			echo esc_html( number_format_i18n( $views ) );
		}
	}

	/**
	 * Make views column sortable.
	 *
	 * @param array $columns Sortable columns.
	 *
	 * @return array Modified columns.
	 */
	public static function make_views_column_sortable( $columns ) {
		$columns['lightvc_views'] = 'lightvc_views';

		return $columns;
	}

	/**
	 * Load page assets.
	 *
	 * Called via load-{page_hook} action.
	 * Follows Foxiz pattern for loading assets only on our admin page.
	 */
	public static function load_page_assets() {
		// Hook into admin_enqueue_scripts with specific callback
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_page_scripts' ], 80 );
	}

	/**
	 * Enqueue page-specific scripts and styles.
	 *
	 * Only called when on Light Views Counter admin page.
	 */
	public static function enqueue_page_scripts() {

		// Enqueue our admin styles
		wp_enqueue_style( 'lightvc-admin', LIGHTVC_PLUGIN_URL . 'admin/css/lvc-admin.css', [], LIGHTVC_VERSION );

		// Enqueue our admin script
		wp_enqueue_script( 'lightvc-admin', LIGHTVC_PLUGIN_URL . 'admin/js/lvc-admin.js', [ 'jquery' ], LIGHTVC_VERSION, true );

		// Localize script with AJAX data
		wp_localize_script(
				'lightvc-admin',
				'lightvcAdmin',
				[
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'lightvc_admin_nonce' ),
				]
		);
	}

	/**
	 * AJAX handler: Save individual setting.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_save_setting() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get and sanitize input
		$setting_name  = isset( $_POST['setting_name'] ) ? sanitize_key( $_POST['setting_name'] ) : '';
		$setting_value = isset( $_POST['setting_value'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_value'] ) ) : '';

		// Validate setting name
		$allowed_settings = [
				'lightvc_scroll_threshold',
				'lightvc_time_window',
				'lightvc_cache_duration',
				'lightvc_enable_caching',
				'lightvc_fast_mode',
				'lightvc_show_views_on_content',
				'lightvc_load_css_in_header',
				'lightvc_enable_get_endpoint',
				'lightvc_query_method',
				'lightvc_exclude_bots',
		];

		if ( ! in_array( $setting_name, $allowed_settings, true ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid setting name.', 'light-views-counter' ) ] );
		}

		// Sanitize value based on type
		if ( in_array( $setting_name, [ 'lightvc_scroll_threshold', 'lightvc_time_window', 'lightvc_cache_duration' ], true ) ) {
			$setting_value = absint( $setting_value );
		} elseif ( 'lightvc_query_method' === $setting_name ) {
			$setting_value = self::sanitize_query_method( $setting_value );
		} else {
			$setting_value = self::sanitize_checkbox( $setting_value );
		}

		// Update option
		$updated = update_option( $setting_name, $setting_value );

		if ( $updated || get_option( $setting_name ) === $setting_value ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Setting saved successfully.', 'light-views-counter' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to save setting.', 'light-views-counter' ) ] );
		}
	}

	/**
	 * Sanitize query method input.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return string Sanitized query method.
	 */
	public static function sanitize_query_method( $value ) {
		$allowed_methods = [ 'subquery', 'join' ];

		if ( ! in_array( $value, $allowed_methods, true ) ) {
			return 'subquery';
		}

		return $value;
	}

	/**
	 * Sanitize checkbox input.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int 1 or 0.
	 */
	public static function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * AJAX handler: Save post types setting.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_save_post_types() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get post types from request
		$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ?
				array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [];

		// Sanitize post types
		$sanitized_types = self::sanitize_post_types( $post_types );

		// Update option
		$updated = update_option( 'lightvc_supported_post_types', $sanitized_types );

		if ( $updated || get_option( 'lightvc_supported_post_types' ) === $sanitized_types ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Post types saved successfully.', 'light-views-counter' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to save post types.', 'light-views-counter' ) ] );
		}
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return array Sanitized post types array.
	 */
	public static function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return [ 'post' ];
		}

		// Sanitize each post type
		$value = array_map( 'sanitize_key', $value );
		$value = array_filter( $value ); // Remove empty values

		// Validate against registered public post types
		$registered_types = array_keys( get_post_types( [ 'public' => true ] ) );
		$value = array_filter( $value, function( $type ) use ( $registered_types ) {
			return in_array( $type, $registered_types, true );
		});

		// Ensure at least 'post' is selected
		if ( empty( $value ) ) {
			return [ 'post' ];
		}

		return $value;
	}

	/**
	 * AJAX handler: Clear cache.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_clear_cache() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Clear cache
		LIGHTVC_Cache::clear_all();

		wp_send_json_success( [ 'message' => esc_html__( 'Cache cleared successfully!', 'light-views-counter' ) ] );
	}

	/**
	 * AJAX handler: Reset specific posts.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_reset_posts() {

		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get post IDs
		$post_ids = isset( $_POST['post_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['post_ids'] ) ) : '';

		if ( empty( $post_ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No post IDs provided.', 'light-views-counter' ) ] );
		}

		// Parse comma-separated IDs
		$ids = array_map( 'absint', explode( ',', $post_ids ) );
		$ids = array_filter( $ids ); // Remove zeros

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid post IDs.', 'light-views-counter' ) ] );
		}

		global $wpdb;
		$table_name  = $wpdb->prefix . 'lightvc_post_views';
		$reset_count = 0;

		foreach ( $ids as $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->delete(
					$table_name,
					[ 'post_id' => $post_id ],
					[ '%d' ]
			);

			if ( $result ) {
				$reset_count ++;
				LIGHTVC_Cache::delete_post_cache( $post_id );
			}
		}

		// translators: %d is the number of reset posts
		$message = sprintf( esc_html__( 'Successfully reset %d posts.', 'light-views-counter' ), $reset_count );

		wp_send_json_success( [ 'message' => $message ] );
	}

	/**
	 * AJAX handler: Reset all views.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_reset_all_views() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Reset all views
		$result = LIGHTVC_Database::reset_all_views();

		if ( $result ) {
			wp_send_json_success( [ 'message' => esc_html__( 'All view counts have been reset.', 'light-views-counter' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to reset views.', 'light-views-counter' ) ] );
		}
	}

	/**
	 * AJAX handler: Get popular posts.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_get_popular_posts() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get date range and post type parameters
		$date_range = isset( $_POST['date_range'] ) ? absint( $_POST['date_range'] ) : 0;
		$post_type  = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

		// If no specific post type selected, use all supported post types
		if ( empty( $post_type ) ) {
			$post_type = get_option( 'lightvc_supported_post_types', [ 'post' ] );
		}

		// Get popular posts
		$posts = LIGHTVC_Database::get_popular_posts(
				[
						'limit'      => 10,
						'date_range' => $date_range,
						'post_type'  => $post_type,
				]
		);

		if ( empty( $posts ) ) {
			wp_send_json_success(
					[
							'html' => '<tr><td colspan="5" class="lvc-empty-state">' . esc_html__( 'No posts found for this time period.', 'light-views-counter' ) . '</td></tr>',
					]
			);

			return;
		}

		// Build table rows HTML
		$html = '';
		$rank = 1;
		foreach ( $posts as $post ) {
			$post_url        = get_permalink( $post->ID );
			$post_date       = mysql2date( get_option( 'date_format' ), $post->post_date );
			$views           = number_format_i18n( $post->views );
			$post_type_obj   = get_post_type_object( $post->post_type );
			$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst( $post->post_type );

			$html .= '<tr>';
			$html .= '<td>' . esc_html( $rank ) . '</td>';
			$html .= '<td><a href="' . esc_url( $post_url ) . '" target="_blank">' . esc_html( $post->post_title ) . '</a></td>';
			$html .= '<td>' . esc_html( $post_type_label ) . '</td>';
			$html .= '<td>' . esc_html( $post_date ) . '</td>';
			$html .= '<td>' . esc_html( $views ) . '</td>';
			$html .= '</tr>';
			$rank ++;
		}

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * AJAX handler: Import from Post Views Counter plugin.
	 * Supports batch processing with progress tracking.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_import_from_pvc() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get batch parameters
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'start';
		$offset      = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		// Handle reset request
		if ( 'reset' === $action_type ) {
			self::reset_import_state();
			wp_send_json_success(
					[
							'message' => esc_html__( 'Migration state has been reset. You can start a new migration.', 'light-views-counter' ),
					]
			);

			return;
		}

		// Check if import is already in progress
		$import_state = self::get_import_state();

		// If starting new import, initialize state
		if ( 'start' === $action_type || 0 === $offset ) {
			// Check if there's an existing import in progress
			if ( $import_state && 'in_progress' === $import_state['status'] && $import_state['offset'] > 0 ) {
				// Resume from last offset
				$offset = $import_state['offset'];
			} else {
				// Start new import
				$offset = 0;
				self::initialize_import_state();
			}
		}

		global $wpdb;
		$batch_size    = 100;
		$lightvc_table = $wpdb->prefix . 'lightvc_post_views';
		$pvc_table     = $wpdb->prefix . 'post_views';

		// Check if PVC table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pvc_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pvc_table ) );

		if ( ! $pvc_table_exists ) {
			self::reset_import_state();
			wp_send_json_error(
					[
							'message' => esc_html__( 'Post Views Counter database table not found. Make sure the Post Views Counter plugin is installed and has recorded view data.', 'light-views-counter' ),
					]
			);

			return;
		}

		// Get total count of posts to import (only on first batch)
		if ( 0 === $offset ) {

			// Table name is safe because it comes from $wpdb->prefix, a trusted source.
			$total_posts_sql = "SELECT COUNT(DISTINCT id) FROM " . $pvc_table . " WHERE type = %d";

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_posts = $wpdb->get_var(
					$wpdb->prepare( $total_posts_sql, 4 ) //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);

			if ( ! $total_posts || $total_posts < 1 ) {
				self::reset_import_state();
				wp_send_json_error(
						[
								'message' => esc_html__( 'No Post Views Counter data found. The post_views table exists but contains no view data.', 'light-views-counter' ),
						]
				);

				return;
			}

			// Update state with total count
			self::update_import_state(
					[
							'total_posts' => absint( $total_posts ),
							'offset'      => 0,
							'imported'    => 0,
							'failed'      => 0,
					]
			);
		}

		// Get current batch of posts with their view counts directly from PVC table
		// Table name is safe because it comes from $wpdb->prefix, a trusted source.
		$pvc_posts_sql = "
		    SELECT id as post_id, SUM(count) as views
		    FROM {$pvc_table}
		    WHERE type = %d
		    GROUP BY id
		    ORDER BY id ASC
		    LIMIT %d OFFSET %d
		";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pvc_posts = $wpdb->get_results(
				$wpdb->prepare(
						$pvc_posts_sql, //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						4,           // total views column
						$batch_size, // limit
						$offset      // offset
				),
				ARRAY_A
		);

		// If no posts found, import is complete
		if ( empty( $pvc_posts ) ) {
			$import_state = self::get_import_state();
			self::complete_import();

			wp_send_json_success(
					[
							'complete' => true,
							'message'  => sprintf(
							/* translators: 1: Number of successfully imported posts, 2: Total number of posts, 3: Number of failed imports */
									esc_html__( 'Migration completed! Successfully migrated %1$d of %2$d posts. %3$d failed.', 'light-views-counter' ),
									$import_state['imported'],
									$import_state['total_posts'],
									$import_state['failed']
							),
							'imported' => $import_state['imported'],
							'total'    => $import_state['total_posts'],
							'failed'   => $import_state['failed'],
					]
			);

			return;
		}

		// Process current batch
		$imported_batch = 0;
		$failed_batch   = 0;

		foreach ( $pvc_posts as $pvc_post ) {
			$post_id = absint( $pvc_post['post_id'] );
			$views   = absint( $pvc_post['views'] );

			// Validate post ID and views
			if ( ! $post_id || ! $views ) {
				$failed_batch ++;
				continue;
			}

			// Verify post exists in WordPress
			if ( ! get_post( $post_id ) ) {
				$failed_batch ++;
				continue;
			}

			// Table name is safe because it comes from $wpdb->prefix, a trusted source.
			$lightvc_insert_sql = "INSERT INTO {$lightvc_table} (post_id, view_count)
					VALUES (%d, %d)
					ON DUPLICATE KEY UPDATE
					view_count = %d";

			// Insert or update view count in Light Views Counter database
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
					$wpdb->prepare( $lightvc_insert_sql,  //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$post_id,
							$views,
							$views
					)
			);

			if ( $result ) {
				$imported_batch ++;
				// Clear cache for this post
				LIGHTVC_Cache::delete_post_cache( $post_id );
			} else {
				$failed_batch ++;
			}
		}

		// Update import state
		$import_state   = self::get_import_state();
		$new_offset     = $offset + $batch_size;
		$total_imported = $import_state['imported'] + $imported_batch;
		$total_failed   = $import_state['failed'] + $failed_batch;

		self::update_import_state(
				[
						'offset'   => $new_offset,
						'imported' => $total_imported,
						'failed'   => $total_failed,
				]
		);

		// Calculate progress
		$total_posts = $import_state['total_posts'];
		$progress    = min( 100, round( ( $new_offset / $total_posts ) * 100 ) );

		// Return batch results
		wp_send_json_success(
				[
						'complete'   => false,
						'progress'   => $progress,
						'imported'   => $total_imported,
						'failed'     => $total_failed,
						'total'      => $total_posts,
						'offset'     => $new_offset,
						'batch_size' => count( $pvc_posts ),
						'message'    => sprintf(
						/* translators: 1: Progress percentage, 2: Number imported, 3: Total posts */
								esc_html__( 'Migrating... %1$d%% complete (%2$d / %3$d posts)', 'light-views-counter' ),
								$progress,
								$total_imported,
								$total_posts
						),
				]
		);
	}

	/**
	 * Reset import state.
	 *
	 * @since 1.0.0
	 */
	private static function reset_import_state() {
		delete_option( 'lightvc_pvc_import_state' );
	}

	/**
	 * Get current import state.
	 *
	 * @return array|false Import state or false if not found.
	 * @since 1.0.0
	 */
	private static function get_import_state() {
		return get_option( 'lightvc_pvc_import_state', false );
	}

	/**
	 * Initialize import state.
	 *
	 * @since 1.0.0
	 */
	private static function initialize_import_state() {
		$state = [
				'status'      => 'in_progress',
				'total_posts' => 0,
				'offset'      => 0,
				'imported'    => 0,
				'failed'      => 0,
				'started_at'  => current_time( 'mysql' ),
		];

		update_option( 'lightvc_pvc_import_state', $state, false );
	}

	/**
	 * Update import state.
	 *
	 * @param array $updates State updates.
	 *
	 * @since 1.0.0
	 */
	private static function update_import_state( $updates ) {
		$state = self::get_import_state();

		if ( ! $state ) {
			self::initialize_import_state();
			$state = self::get_import_state();
		}

		$state = array_merge( $state, $updates );
		update_option( 'lightvc_pvc_import_state', $state, false );
	}

	/**
	 * Complete import and update state.
	 *
	 * @since 1.0.0
	 */
	private static function complete_import() {
		$state = self::get_import_state();

		if ( $state ) {
			$state['status']       = 'completed';
			$state['completed_at'] = current_time( 'mysql' );
			update_option( 'lightvc_pvc_import_state', $state, false );
		}

		// Clear all cache after import completes
		LIGHTVC_Cache::clear_all();
	}


	/**
	 * Render settings page.
	 */
	public static function render_settings_page() {

		// Security check: Verify user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'light-views-counter' ),
					esc_html__( 'Access Denied', 'light-views-counter' ),
					[ 'response' => 403 ]
			);
		}

		// Render Foxiz admin header if Foxiz Core is active
		if ( self::is_foxiz_core_active() && class_exists( 'RB_ADMIN_CORE' ) ) {
			RB_ADMIN_CORE::get_instance()->header_template();
		}

		// Get current settings
		$scroll_threshold      = get_option( 'lightvc_scroll_threshold', 50 );
		$time_window           = get_option( 'lightvc_time_window', 1800 );
		$cache_duration        = get_option( 'lightvc_cache_duration', 300 );
		$enable_caching        = get_option( 'lightvc_enable_caching', 1 );
		$fast_mode             = get_option( 'lightvc_fast_mode', 1 );
		$show_views_on_content = get_option( 'lightvc_show_views_on_content', 0 );
		$load_css_in_header    = get_option( 'lightvc_load_css_in_header', 0 );
		$enable_get_endpoint   = get_option( 'lightvc_enable_get_endpoint', 0 );
		$supported_post_types  = get_option( 'lightvc_supported_post_types', [ 'post' ] );
		$query_method          = get_option( 'lightvc_query_method', 'subquery' );
		$exclude_bots          = get_option( 'lightvc_exclude_bots', 1 );
		?>
		<div class="lvc-admin-wrap">
			<!-- Header -->
			<div class="lvc-admin-header">
				<h1><?php esc_html_e( 'Light Views Counter', 'light-views-counter' ); ?></h1>
				<p><?php esc_html_e( 'Simple, lightweight post views tracking optimized for high-traffic and large post database WordPress sites.', 'light-views-counter' ); ?></p>
				<div class="lvc-tab-navigation">
					<button class="lvc-tab-btn active" data-tab="settings">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Settings', 'light-views-counter' ); ?>
					</button>
					<button class="lvc-tab-btn" data-tab="statistics">
						<span class="dashicons dashicons-chart-bar"></span>
						<?php esc_html_e( 'Statistics', 'light-views-counter' ); ?>
					</button>
					<button class="lvc-tab-btn" data-tab="tools">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Tools', 'light-views-counter' ); ?>
					</button>
					<button class="lvc-tab-btn" data-tab="usage">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Usage', 'light-views-counter' ); ?>
					</button>
				</div>
			</div>
			<!-- our services -->
			<div class="lvc-intro">
				<div class="lvc-intro-services">
					<svg class="lvc-intro-icon" fill="currentColor" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg"><path d="m108 16h-88c-11.027 0-20 8.973-20 20v56c0 11.027 8.973 20 20 20h88c11.027 0 20-8.973 20-20v-56c0-11.027-8.973-20-20-20zm12 76c0 6.617-5.383 12-12 12h-88c-6.617 0-12-5.383-12-12v-56c0-6.617 5.383-12 12-12h88c6.617 0 12 5.383 12 12zm-9.172-53.172-25.172 25.172 25.172 25.172c1.563 1.563 1.563 4.094 0 5.656-.781.781-1.805 1.172-2.828 1.172s-2.047-.391-2.828-1.172l-25.172-25.172-13.172 13.172c-.781.781-1.805 1.172-2.828 1.172s-2.047-.391-2.828-1.172l-13.172-13.172-25.172 25.172c-.781.781-1.805 1.172-2.828 1.172s-2.047-.391-2.828-1.172c-1.563-1.563-1.563-4.094 0-5.656l25.172-25.172-25.172-25.172c-1.563-1.563-1.563-4.094 0-5.656s4.094-1.563 5.656 0l41.172 41.172 41.172-41.172c1.563-1.563 4.094-1.563 5.656 0s1.563 4.094 0 5.656z"/></svg>
					<div>
						<h3 class="lvc-tips-headline"><?php esc_html_e( 'Custom Web Design & Development Services', 'light-views-counter' ); ?></h3>
						<div class="lvc-tips-description"><?php esc_html_e( 'We design and build stunning, easy-to-use websites that reflect your vision and help you reach your goals.', 'light-views-counter' ); ?></div>
					</div>
					<a href="https://themeruby.com/get-a-quote/" class="lvc-btn lvc-btn-primary" rel="nofollow" target="_blank"><?php esc_html_e( 'Get Free Quote', 'light-views-counter' ); ?></a>
				</div>
				<?php if ( ! lightvc_is_foxiz_core_active() ) : ?>
					<div class="lvc-intro-themes">
						<h3 class="lvc-intro-themes-header"><span class="dashicons dashicons-wordpress-alt"></span><?php esc_html_e( 'Premium News/Magazine Themes', 'light-views-counter' ); ?></h3>
						<div class="lvc-themes-cards">
							<div class="lvc-theme-card"><a class="lvc-theme-image" href="//1.envato.market/MXYjYo" rel="nofollow" target="_blank"><img alt="<?php esc_attr_e( 'Foxiz Theme', 'light-views-counter' ); ?>" height="300" src="//assets.themeruby.com/api/foxiz.jpg" width="590"></a>
								<div class="lvc-theme-content">
									<div>
										<h4><?php esc_html_e( 'Foxiz', 'light-views-counter' ); ?></h4>
										<p class="lvc-theme-tagline"><?php esc_html_e( 'Newspaper News and Magazine WordPress Theme', 'light-views-counter' ); ?></p>
									</div>
									<a class="lvc-btn lvc-btn-dark" href="//1.envato.market/MXYjYo" rel="nofollow" target="_blank"><span><?php esc_html_e( 'Learn More', 'light-views-counter' ); ?></span><span class="dashicons dashicons-arrow-right-alt"></span></a></div>
							</div>
							<div class="lvc-theme-card"><a class="lvc-theme-image" href="//1.envato.market/Z25Rz" rel="nofollow" target="_blank"><img alt="<?php esc_attr_e( 'Pixwell Theme', 'light-views-counter' ); ?>" height="300" src="//assets.themeruby.com/api/pixwell.jpg" width="590"></a>
								<div class="lvc-theme-content">
									<div>
										<h4><?php esc_html_e( 'Pixwell', 'light-views-counter' ); ?></h4>
										<p class="lvc-theme-tagline"><?php esc_html_e( 'Magazine WordPress Theme', 'light-views-counter' ); ?></p>
									</div>
									<a class="lvc-btn lvc-btn-dark" href="//1.envato.market/Z25Rz" rel="nofollow" target="_blank"><span><?php esc_html_e( 'Learn More', 'light-views-counter' ); ?></span><span class="dashicons dashicons-arrow-right-alt"></span></a></div>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<!-- Settings Tab -->
			<div id="lvc-tab-settings" class="lvc-tab-content active">
				<!-- Tracking Settings -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Tracking Settings', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<!-- Supported Post Types -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label><?php esc_html_e( 'Supported Post Types', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Select which post types should track view counts.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<div class="lvc-post-types-container">
									<?php
									$all_post_types = get_post_types( [ 'public' => true ], 'objects' );

									$excluded_types = [
											'attachment',     // Media uploads
											'revision',       // Post revisions
											'nav_menu_item',  // Menu items
											'custom_css',     // Customizer CSS
											'customize_changeset', // Customizer drafts
											'oembed_cache',   // Embed cache
											'rb-etemplate', // ruby template
											'user_request',   // Privacy requests
											'wp_block',       // Block patterns
											'wp_template',    // Full site editing templates
											'wp_template_part',
											'wp_navigation',
											'elementor_library', // Elementor templates
											'e-floating-buttons', // Elementor floating
											'fl-builder-template', // Beaver Builder
											'ct_template',    // Blocksy, Kadence, etc.
											'wpcf7_contact_form', // Contact Form 7
											'product_variation',  // WooCommerce
											'shop_order',
											'shop_coupon',
											'shop_order_refund',
											'shop_subscription',
									];

									foreach ( $all_post_types as $post_type ) :
										if ( in_array( $post_type->name, $excluded_types, true ) ) {
											continue;
										}
										$is_checked = in_array( $post_type->name, $supported_post_types, true );
										?>
										<label class="lvc-post-type-tag <?php echo $is_checked ? 'active' : ''; ?>">
											<input
													type="checkbox"
													class="lvc-post-type-checkbox"
													value="<?php echo esc_attr( $post_type->name ); ?>"
													<?php checked( $is_checked ); ?>
											/>
											<span class="lvc-post-type-label"><?php echo esc_html( $post_type->label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<!-- Scroll Threshold -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_scroll_threshold"><?php esc_html_e( 'Scroll Threshold', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Percentage of page that must be scrolled before counting a view. Set to 0 to count immediately.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<input type="number" id="lightvc_scroll_threshold" name="lightvc_scroll_threshold" value="<?php echo esc_attr( $scroll_threshold ); ?>" min="0" max="100" class="lvc-setting-input" />
							</div>
						</div>
						<!-- Time Window -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_time_window"><?php esc_html_e( 'Time Window (seconds)', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Seconds to wait before counting another view from the same user (uses localStorage). Example: 1800 = 30 minutes, 3600 = 1 hour.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<input type="number" id="lightvc_time_window" name="lightvc_time_window" value="<?php echo esc_attr( $time_window ); ?>" min="1" class="lvc-setting-input" />
							</div>
						</div>
						<!-- Fast Mode -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_fast_mode"><?php esc_html_e( 'Fast Mode', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Use sendBeacon API for fire-and-forget tracking. Improves performance for high-traffic sites.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_fast_mode" name="lightvc_fast_mode" value="1" <?php checked( $fast_mode, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Exclude Bots -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_exclude_bots"><?php esc_html_e( 'Exclude Bots', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Bypass view counting for search engine bots and web crawlers. Helps ensure accurate human visitor statistics.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_exclude_bots" name="lightvc_exclude_bots" value="1" <?php checked( $exclude_bots, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- Performance Settings -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Performance Settings', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<!-- Enable Caching -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_enable_caching"><?php esc_html_e( 'Enable Caching', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Cache view counts using WordPress Cache for better performance.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_enable_caching" name="lightvc_enable_caching" value="1" <?php checked( $enable_caching, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Cache Duration -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_cache_duration"><?php esc_html_e( 'Cache Duration (seconds)', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'How long to cache view counts. Set to 0 to disable caching.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<input type="number" id="lightvc_cache_duration" name="lightvc_cache_duration" value="<?php echo esc_attr( $cache_duration ); ?>" min="0" class="lvc-setting-input" />
							</div>
						</div>
						<!-- Query Method -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_query_method"><?php esc_html_e( 'Query Method', 'light-views-counter' ); ?></label>
									<span class="description">
										<?php esc_html_e( 'Used to query popular posts in widgets. Subquery: Simple, recommended for <100k posts. JOIN: Optimized for large databases (100k+ posts).', 'light-views-counter' ); ?>
									</span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<select id="lightvc_query_method" name="lightvc_query_method" class="lvc-setting-input">
									<option value="subquery" <?php selected( $query_method, 'subquery' ); ?>>
										<?php esc_html_e( 'Subquery (Default)', 'light-views-counter' ); ?>
									</option>
									<option value="join" <?php selected( $query_method, 'join' ); ?>>
										<?php esc_html_e( 'JOIN (Large Databases)', 'light-views-counter' ); ?>
									</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<!-- Display Settings -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Display Settings', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<!-- Show Views on Content -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_show_views_on_content"><?php esc_html_e( 'Show Views on Content', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Automatically display view count at the end of each post.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_show_views_on_content" name="lightvc_show_views_on_content" value="1" <?php checked( $show_views_on_content, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Load CSS in Header -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_load_css_in_header"><?php esc_html_e( 'Load CSS in Header', 'light-views-counter' ); ?></label>
									<span class="description"><?php esc_html_e( 'Enqueue CSS styles in the header for shortcode and display usage. Enable this if you use [lightvc_post_views] shortcode or display functions.', 'light-views-counter' ); ?></span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_load_css_in_header" name="lightvc_load_css_in_header" value="1" <?php checked( $load_css_in_header, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- API Settings -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'API Settings', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<!-- Enable GET Endpoint -->
						<div class="lvc-setting-row">
							<div class="lvc-setting-label">
								<div>
									<label for="lightvc_enable_get_endpoint"><?php esc_html_e( 'Enable GET Endpoint', 'light-views-counter' ); ?></label>
									<span class="description">
									<?php
									printf(
									/* translators: %s: REST API URL example */
											esc_html__( 'Allow retrieving view counts via REST API for third-party application: %s', 'light-views-counter' ),
											esc_html( site_url( '/wp-json/lightvc/v1/views/{postID}' ) )
									);
									?>
								</span>
								</div>
							</div>
							<div class="lvc-setting-control">
								<label class="lvc-toggle-switch">
									<input type="checkbox" id="lightvc_enable_get_endpoint" name="lightvc_enable_get_endpoint" value="1" <?php checked( $enable_get_endpoint, 1 ); ?> />
									<span class="lvc-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Statistics Tab -->
			<div id="lvc-tab-statistics" class="lvc-tab-content">
				<!-- Content will be loaded here via AJAX on first click -->
				<div class="lvc-stats-loading">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading statistics...', 'light-views-counter' ); ?></p>
				</div>
			</div>
			<!-- Tools Tab -->
			<div id="lvc-tab-tools" class="lvc-tab-content">
				<!-- Import Section -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Migrate Data', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-tool-box">
							<h4><?php esc_html_e( 'Migrate from Post Views Counter Plugin', 'light-views-counter' ); ?></h4>
							<p><?php esc_html_e( 'Automatically migrate view count data from the Post Views Counter plugin.', 'light-views-counter' ); ?></p>
							<?php
							// Check for existing import state
							$import_state = self::get_import_state();
							if ( $import_state && 'in_progress' === $import_state['status'] ) :
								$progress = $import_state['total_posts'] > 0 ? round( ( $import_state['offset'] / $import_state['total_posts'] ) * 100 ) : 0;
								?>
								<div class="lvc-import-notice">
									<p>
										<strong><?php esc_html_e( 'Migration in progress:', 'light-views-counter' ); ?></strong>
										<?php
										printf(
										/* translators: 1: Number of imported posts, 2: Total posts, 3: Progress percentage */
												esc_html__( '%1$d of %2$d posts migrated (%3$d%%)', 'light-views-counter' ),
												esc_html( $import_state['imported'] ),
												esc_html( $import_state['total_posts'] ),
												esc_html( $progress )
										);
										?>
									</p>
								</div>
							<?php endif; ?>
							<!-- Progress Bar -->
							<div id="lvc-import-progress" class="lvc-import-progress">
								<div class="lvc-progress-bar">
									<div id="lvc-progress-fill" class="lvc-progress-fill"></div>
									<div id="lvc-progress-text">0%</div>
								</div>
								<p id="lvc-import-status"></p>
							</div>
							<!-- Action Buttons -->
							<div class="lvc-import-actions">
								<button type="button" id="lvc-import-pvc-btn" class="lvc-action-btn primary">
									<span class="dashicons dashicons-download"></span>
									<?php
									if ( $import_state && 'in_progress' === $import_state['status'] ) {
										esc_html_e( 'Resume Migration', 'light-views-counter' );
									} else {
										esc_html_e( 'Migrate Data from Post View Counter Plugin', 'light-views-counter' );
									}
									?>
								</button>
								<?php if ( $import_state && 'in_progress' === $import_state['status'] ) : ?>
									<button type="button" id="lvc-reset-import-btn" class="lvc-action-btn secondary">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Reset Migration', 'light-views-counter' ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
				<!-- Maintenance Section -->
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Maintenance', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-tool-box">
							<h4><?php esc_html_e( 'Clear Cache', 'light-views-counter' ); ?></h4>
							<p><?php esc_html_e( 'Clear all cached view counts. Use this if you are seeing stale data.', 'light-views-counter' ); ?></p>
							<button type="button" id="lvc-clear-cache-btn" class="lvc-action-btn secondary">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear Cache', 'light-views-counter' ); ?>
							</button>
						</div>
						<div class="lvc-tool-box">
							<h4><?php esc_html_e( 'Reset Specific Posts', 'light-views-counter' ); ?></h4>
							<p><?php esc_html_e( 'Reset view counts for specific posts. Enter post IDs or titles below (comma-separated).', 'light-views-counter' ); ?></p>
							<input type="text" id="lvc-reset-posts-input" class="lvc-setting-input" placeholder="<?php esc_attr_e( 'Enter post IDs: 123, 456, 789', 'light-views-counter' ); ?>" />
							<button type="button" id="lvc-reset-posts-btn" class="lvc-action-btn danger">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Reset Selected Posts', 'light-views-counter' ); ?>
							</button>
						</div>
						<div class="lvc-tool-box">
							<h4><?php esc_html_e( 'Reset All Views', 'light-views-counter' ); ?></h4>
							<p><?php esc_html_e( 'Reset all view counts for all posts. This action cannot be undone!', 'light-views-counter' ); ?></p>
							<button type="button" id="lvc-reset-all-views-btn" class="lvc-action-btn danger">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Reset All Views', 'light-views-counter' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
			<!-- Usage Tab -->
			<div id="lvc-tab-usage" class="lvc-tab-content">
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Widget Usage', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Popular Posts Widget', 'light-views-counter' ); ?></h4>
							<code>
								Appearance &gt; Widgets &gt; Popular Posts (Light Views Counter)
							</code>
							<p><?php esc_html_e( 'Add the Popular Posts widget to your sidebar or footer to display the most viewed posts automatically. Supports multiple display options and date ranges.', 'light-views-counter' ); ?></p>
						</div>
					</div>
				</div>
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Template Tags', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Display Views with Shortcode', 'light-views-counter' ); ?></h4>
							<code>
								&lt;!-- Basic usage (current post) --&gt;<br />
								[lightvc_post_views]<br />
								<br />
								&lt;!-- Specific post by ID --&gt;<br />
								[lightvc_post_views post_id="123"]<br />
								<br />
								&lt;!-- Different display styles --&gt;<br />
								[lightvc_post_views style="default"] &lt;!-- Default style with border --&gt;<br />
								[lightvc_post_views style="minimal"] &lt;!-- Minimal inline style --&gt;<br />
								[lightvc_post_views style="badge"] &lt;!-- Badge style with purple background --&gt;<br />
								[lightvc_post_views style="compact"] &lt;!-- Compact style, smallest size --&gt;<br />
								<br />
								&lt;!-- Custom label text --&gt;<br />
								[lightvc_post_views label="Total Reads"]<br />
								[lightvc_post_views label="👀 Views"]<br />
								<br />
								&lt;!-- Hide label (show count only) --&gt;<br />
								[lightvc_post_views show_label="false"]<br />
								<br />
								&lt;!-- Combine multiple parameters --&gt;<br />
								[lightvc_post_views post_id="456" style="badge" label="Reads" show_label="true"]
							</code>
							<p>
								<?php esc_html_e( 'Display view counts anywhere using this flexible shortcode. Supports multiple parameters:', 'light-views-counter' ); ?>
								<br /><br />
								<strong><?php esc_html_e( 'Available Parameters:', 'light-views-counter' ); ?></strong><br />
								<code>post_id</code> - <?php esc_html_e( 'Specific post ID (default: current post)', 'light-views-counter' ); ?><br />
								<code>style</code> - <?php esc_html_e( 'Display style: default, minimal, badge, compact', 'light-views-counter' ); ?><br />
								<code>label</code> - <?php esc_html_e( 'Custom label text (default: "Views")', 'light-views-counter' ); ?><br />
								<code>show_label</code> - <?php esc_html_e( 'Show/hide label: true or false (default: true)', 'light-views-counter' ); ?>
							</p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Get Post Views', 'light-views-counter' ); ?></h4>
							<code>&lt;?php echo lightvc_get_post_views( $post_id ); ?&gt;</code>
							<p><?php esc_html_e( 'Returns the view count for a specific post. If no post ID is provided, uses the current post.', 'light-views-counter' ); ?></p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Get Popular Posts', 'light-views-counter' ); ?></h4>
							<code>
								&lt;?php<br />
								$popular_posts = lightvc_get_popular_posts( [<br />
								&nbsp;&nbsp;'limit' => 10,<br />
								&nbsp;&nbsp;'post_type' => 'post',<br />
								&nbsp;&nbsp;'date_range' => 7 // Last 7 days, 0 for all time<br />
								] );<br />
								?&gt;
							</code>
							<p><?php esc_html_e( 'Get an array of popular posts based on view count. Supports date range filtering and post type selection.', 'light-views-counter' ); ?></p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Display Popular Posts', 'light-views-counter' ); ?></h4>
							<code>
								&lt;?php<br />
								$popular_posts = lightvc_get_popular_posts( [ 'limit' => 5 ] );<br />
								<br />
								foreach ( $popular_posts as $post ) {<br />
								&nbsp;&nbsp;echo '&lt;div&gt;';<br />
								&nbsp;&nbsp;echo '&nbsp;&nbsp;&lt;h3&gt;' . esc_html( $post-&gt;post_title ) . '&lt;/h3&gt;';<br />
								&nbsp;&nbsp;echo '&nbsp;&nbsp;&lt;p&gt;Views: ' . number_format_i18n( $post-&gt;views ) . '&lt;/p&gt;';<br />
								&nbsp;&nbsp;echo '&lt;/div&gt;';<br />
								}<br />
								?&gt;
							</code>
							<p><?php esc_html_e( 'Loop through popular posts and display them with view counts.', 'light-views-counter' ); ?></p>
						</div>
					</div>
				</div>
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Query Integration', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Order Posts by Views in WP_Query', 'light-views-counter' ); ?></h4>
							<code>
								&lt;?php<br />
								$query = new WP_Query( [<br />
								&nbsp;&nbsp;'post_type' => 'post',<br />
								&nbsp;&nbsp;'posts_per_page' => 10,<br />
								&nbsp;&nbsp;'orderby' => 'lightvc_views',<br />
								&nbsp;&nbsp;'order' => 'DESC'<br />
								] );<br />
								?&gt;
							</code>
							<p><?php esc_html_e( 'Order posts by view count in any custom query using the orderby parameter.', 'light-views-counter' ); ?></p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Filter Posts by Minimum Views', 'light-views-counter' ); ?></h4>
							<code>
								&lt;?php<br />
								$query = new WP_Query( [<br />
								&nbsp;&nbsp;'post_type' => 'post',<br />
								&nbsp;&nbsp;'posts_per_page' => 10,<br />
								&nbsp;&nbsp;'orderby' => 'lightvc_views',<br />
								&nbsp;&nbsp;'order' => 'DESC',<br />
								&nbsp;&nbsp;'meta_query' => [<br />
								&nbsp;&nbsp;&nbsp;&nbsp;[<br />
								&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'key' => '_lightvc_views',<br />
								&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'value' => 1000,<br />
								&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'compare' => '&gt;=',<br />
								&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'type' => 'NUMERIC'<br />
								&nbsp;&nbsp;&nbsp;&nbsp;]<br />
								&nbsp;&nbsp;]<br />
								] );<br />
								?&gt;
							</code>
							<p><?php esc_html_e( 'Get only posts with a minimum number of views using meta_query.', 'light-views-counter' ); ?></p>
						</div>
					</div>
				</div>
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'REST API', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Get Views via REST API', 'light-views-counter' ); ?></h4>
							<code>
								GET <?php echo esc_html( site_url( '/wp-json/lightvc/v1/views/{post_id}' ) ); ?>
							</code>
							<p>
								<?php
								if ( get_option( 'lightvc_enable_get_endpoint', 0 ) ) {
									esc_html_e( 'Retrieve view counts via REST API for third-party applications. This endpoint is currently ENABLED in your settings.', 'light-views-counter' );
								} else {
									esc_html_e( 'Retrieve view counts via REST API for third-party applications. Enable this endpoint in the API Settings section.', 'light-views-counter' );
								}
								?>
							</p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Track Views via REST API', 'light-views-counter' ); ?></h4>
							<code>
								POST <?php echo esc_html( site_url( '/wp-json/lightvc/v1/track' ) ); ?><br />
								Body: { "post_id": 123 }
							</code>
							<p><?php esc_html_e( 'Track views via REST API. Respects your tracking settings (scroll threshold, time window, bot detection).', 'light-views-counter' ); ?></p>
						</div>
					</div>
				</div>
				<div class="lvc-settings-section">
					<div class="lvc-section-header">
						<h3><?php esc_html_e( 'Filters & Hooks', 'light-views-counter' ); ?></h3>
					</div>
					<div class="lvc-section-body">
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Modify View Count Output', 'light-views-counter' ); ?></h4>
							<code>
								add_filter( 'lightvc_post_views_count', function( $count, $post_id ) {<br />
								&nbsp;&nbsp;// Modify the view count before displaying<br />
								&nbsp;&nbsp;return $count * 2; // Example: Double the count<br />
								}, 10, 2 );
							</code>
							<p><?php esc_html_e( 'Filter the view count before it is displayed or returned by template tags.', 'light-views-counter' ); ?></p>
						</div>
						<div class="lvc-code-example">
							<h4><?php esc_html_e( 'Custom Display Format', 'light-views-counter' ); ?></h4>
							<code>
								add_filter( 'lightvc_display_views', function( $output, $count, $post_id ) {<br />
								&nbsp;&nbsp;// Customize the HTML output<br />
								&nbsp;&nbsp;return '&lt;span class="custom-views"&gt;' . $count . ' views&lt;/span&gt;';<br />
								}, 10, 3 );
							</code>
							<p><?php esc_html_e( 'Customize the HTML markup used when displaying view counts.', 'light-views-counter' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}


}
