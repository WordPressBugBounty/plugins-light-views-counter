<?php
/**
 * Admin Statistics Tab Handler
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Admin_Stats
 *
 * Handles statistics tab rendering and AJAX operations.
 */
class LIGHTVC_Admin_Stats {

	/**
	 * Register AJAX handlers.
	 */
	public static function init() {
		add_action( 'wp_ajax_lightvc_load_statistics', [ __CLASS__, 'ajax_load_statistics' ] );
	}

	/**
	 * AJAX handler: Load statistics tab content.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_load_statistics() {
		// Verify nonce
		check_ajax_referer( 'lightvc_admin_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'light-views-counter' ) ] );
		}

		// Get statistics
		$stats = LIGHTVC_Database::get_statistics();

		// Get supported post types for filter
		$supported_post_types = get_option( 'lightvc_supported_post_types', [ 'post' ] );

		// Start output buffering
		ob_start();
		?>
		<div class="lvc-stats-dashboard">
			<h2><?php esc_html_e( 'Statistics', 'light-views-counter' ); ?></h2>
			<div class="lvc-stats-grid">
				<div class="lvc-stat-box">
					<h3><?php echo esc_html( self::format_number_short( $stats['total_views'] ) ); ?></h3>
					<p><?php esc_html_e( 'Total Views', 'light-views-counter' ); ?></p>
				</div>
				<div class="lvc-stat-box">
					<h3><?php echo esc_html( self::format_number_short( $stats['total_posts'] ) ); ?></h3>
					<p><?php esc_html_e( 'Posts with Views', 'light-views-counter' ); ?></p>
				</div>
				<div class="lvc-stat-box">
					<h3><?php echo esc_html( self::format_number_short( $stats['average_views'] ) ); ?></h3>
					<p><?php esc_html_e( 'Average Views per Post', 'light-views-counter' ); ?></p>
				</div>
			</div>
			<!-- Popular Posts Table with Time Filters -->
			<div class="lvc-popular-posts-section">
				<div class="lvc-popular-header">
					<div>
						<h3><?php esc_html_e( 'Most Viewed Posts', 'light-views-counter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Top 10 posts sorted by view count, filtered by the post publish date.', 'light-views-counter' ); ?></p>
					</div>
					<!-- Post Type Filter -->
					<?php if ( count( $supported_post_types ) > 1 ) : ?>
						<div class="lvc-post-type-filter">
							<label for="lvc-stats-post-type-filter"><?php esc_html_e( 'Post Type:', 'light-views-counter' ); ?></label>
							<select id="lvc-stats-post-type-filter" class="lvc-setting-input">
								<option value=""><?php esc_html_e( 'All Post Types', 'light-views-counter' ); ?></option>
								<?php
								$all_post_types = get_post_types( [ 'public' => true ], 'objects' );
								foreach ( $all_post_types as $post_type ) :
									if ( in_array( $post_type->name, $supported_post_types, true ) ) :
										?>
										<option value="<?php echo esc_attr( $post_type->name ); ?>">
											<?php echo esc_html( $post_type->label ); ?>
										</option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>
				<!-- Time Period Tabs -->
				<div class="lvc-time-tabs">
					<button class="lvc-time-tab active" data-range="0"><?php esc_html_e( 'All Time', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="1"><?php esc_html_e( '1 Day', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="3"><?php esc_html_e( '3 Days', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="7"><?php esc_html_e( '7 Days', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="14"><?php esc_html_e( '14 Days', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="30"><?php esc_html_e( '1 Month', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="90"><?php esc_html_e( '3 Months', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="180"><?php esc_html_e( '6 Months', 'light-views-counter' ); ?></button>
					<button class="lvc-time-tab" data-range="365"><?php esc_html_e( '1 Year', 'light-views-counter' ); ?></button>
				</div>
				<!-- Popular Posts Table -->
				<div class="lvc-popular-table-wrapper">
					<table class="lvc-popular-table">
						<thead>
						<tr>
							<th><?php esc_html_e( 'Rank', 'light-views-counter' ); ?></th>
							<th><?php esc_html_e( 'Post Title', 'light-views-counter' ); ?></th>
							<th><?php esc_html_e( 'Post Type', 'light-views-counter' ); ?></th>
							<th><?php esc_html_e( 'Publish Date', 'light-views-counter' ); ?></th>
							<th><?php esc_html_e( 'Views', 'light-views-counter' ); ?></th>
						</tr>
						</thead>
						<tbody id="lvc-popular-table-body">
						<!-- Data loaded via AJAX -->
						<tr>
							<td colspan="5" class="lvc-table-loading">
								<span class="spinner is-active"></span>
							</td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Format a number into a short, human-readable form.
	 *
	 * Examples:
	 * - 1,200 → 1.2K
	 * - 5,400,000 → 5.4M
	 * - 3,200,000,000 → 3.2B
	 *
	 * @param int|float $number The number to format.
	 *
	 * @return string|int Formatted number with suffix (K, M, B) or the original number if below 1000.
	 */
	public static function format_number_short( $number ) {

		$number = intval( $number );

		if ( $number >= 1000000000 ) { // 1 Billion
			return round( $number / 1000000000, 1 ) . 'B';
		} elseif ( $number >= 1000000 ) { // 1 Million
			return round( $number / 1000000, 1 ) . 'M';
		} elseif ( $number >= 1000 ) { // 1 Thousand
			return round( $number / 1000, 1 ) . 'K';
		}

		return $number;
	}

}

// Initialize
LIGHTVC_Admin_Stats::init();
