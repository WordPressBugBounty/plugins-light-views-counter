<?php
/**
 * Popular Posts Widget
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LIGHTVC_Popular_Posts_Widget
 *
 * Standard WordPress widget for displaying popular posts.
 */
class LIGHTVC_Popular_Posts_Widget extends WP_Widget {

	/**
	 * Allowed HTML tags for widget output.
	 *
	 * @var array
	 */
	public static $allowed_html = [
		'div'     => [ 'class' => true, 'id' => true, 'style' => true ],
		'span'    => [ 'class' => true, 'id' => true ],
		'aside'   => [ 'class' => true, 'id' => true ],
		'section' => [ 'class' => true, 'id' => true ],
		'h1'      => [ 'class' => true, 'id' => true ],
		'h2'      => [ 'class' => true, 'id' => true ],
		'h3'      => [ 'class' => true, 'id' => true ],
		'h4'      => [ 'class' => true, 'id' => true ],
		'strong'  => [],
		'b'       => [],
		'i'       => [ 'class' => true ],
		'em'      => [],
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'lightvc_popular_posts',
			esc_html__( 'Popular Posts (Light Views Counter)', 'light-views-counter' ),
			array(
				'description' => esc_html__( 'Display most popular posts based on view counts.', 'light-views-counter' ),
			)
		);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$title      = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Popular Posts', 'light-views-counter' );
		$title      = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$limit      = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		$date_range = isset( $instance['date_range'] ) ? absint( $instance['date_range'] ) : 0;
		$show_views = isset( $instance['show_views'] ) ? (bool) $instance['show_views'] : true;
		$show_date  = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$show_thumb = isset( $instance['show_thumb'] ) ? (bool) $instance['show_thumb'] : true;

		// Get popular posts
		$popular_posts = lightvc_get_popular_posts(
			array(
				'limit'      => $limit,
				'date_range' => $date_range,
			)
		);

		if ( empty( $popular_posts ) ) {
			return;
		}

		// Load styles
		wp_enqueue_style( 'lightvc-styles' );

		echo wp_kses( $args['before_widget'], self::$allowed_html );

		if ( $title ) {
			echo wp_kses( $args['before_title'], self::$allowed_html ) . esc_html( $title ) . wp_kses( $args['after_title'], self::$allowed_html );
		}

		echo '<ul class="lvc-popular-posts-list">';

		foreach ( $popular_posts as $post ) {
			echo '<li class="lvc-popular-post-item">';

			if ( $show_thumb && has_post_thumbnail( $post->ID ) ) {
				echo '<div class="lvc-post-thumb">';
				echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">';
				echo get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'class' => 'lvc-thumb-img' ) );
				echo '</a>';
				echo '</div>';
			}

			echo '<div class="lvc-post-content">';

			echo '<h4 class="lvc-post-title">';
			echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">';
			echo esc_html( $post->post_title );
			echo '</a>';
			echo '</h4>';

			if ( $show_views || $show_date ) {
				echo '<div class="lvc-post-meta">';

				if ( $show_date ) {
					echo '<span class="lvc-meta-date">';
					echo esc_html( get_the_date( '', $post->ID ) );
					echo '</span>';
				}

				if ( $show_views ) {
					echo '<span class="lvc-meta-views">';
					echo esc_html( number_format_i18n( $post->views ) ) . ' ' . esc_html__( 'views', 'light-views-counter' );
					echo '</span>';
				}

				echo '</div>';
			}

			echo '</div>';

			echo '</li>';
		}

		echo '</ul>';

		echo wp_kses( $args['after_widget'], self::$allowed_html );
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values from database.
	 * @return string
	 */
	public function form( $instance ) {
		$title      = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Popular Posts', 'light-views-counter' );
		$limit      = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		$date_range = ! empty( $instance['date_range'] ) ? absint( $instance['date_range'] ) : 0;
		$show_views = isset( $instance['show_views'] ) ? (bool) $instance['show_views'] : true;
		$show_date  = isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$show_thumb = isset( $instance['show_thumb'] ) ? (bool) $instance['show_thumb'] : true;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'light-views-counter' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">
				<?php esc_html_e( 'Number of posts:', 'light-views-counter' ); ?>
			</label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" max="20" value="<?php echo esc_attr( $limit ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'date_range' ) ); ?>">
				<?php esc_html_e( 'Time period:', 'light-views-counter' ); ?>
			</label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'date_range' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'date_range' ) ); ?>">
				<option value="0" <?php selected( $date_range, 0 ); ?>><?php esc_html_e( 'All time', 'light-views-counter' ); ?></option>
				<option value="7" <?php selected( $date_range, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'light-views-counter' ); ?></option>
				<option value="15" <?php selected( $date_range, 15 ); ?>><?php esc_html_e( 'Last 15 days', 'light-views-counter' ); ?></option>
				<option value="30" <?php selected( $date_range, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'light-views-counter' ); ?></option>
			</select>
		</p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_thumb ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_thumb' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>">
				<?php esc_html_e( 'Display thumbnail', 'light-views-counter' ); ?>
			</label>
		</p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_views ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_views' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_views' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_views' ) ); ?>">
				<?php esc_html_e( 'Display view count', 'light-views-counter' ); ?>
			</label>
		</p>

		<p>
			<input class="checkbox" type="checkbox" <?php checked( $show_date ); ?> id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>" />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>">
				<?php esc_html_e( 'Display post date', 'light-views-counter' ); ?>
			</label>
		</p>
		<?php
		return '';
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title']      = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['limit']      = ! empty( $new_instance['limit'] ) ? absint( $new_instance['limit'] ) : 5;
		$instance['date_range'] = ! empty( $new_instance['date_range'] ) ? absint( $new_instance['date_range'] ) : 0;
		$instance['show_views'] = ! empty( $new_instance['show_views'] ) ? 1 : 0;
		$instance['show_date']  = ! empty( $new_instance['show_date'] ) ? 1 : 0;
		$instance['show_thumb'] = ! empty( $new_instance['show_thumb'] ) ? 1 : 0;

		return $instance;
	}
}

/**
 * Register the widget.
 */
function lightvc_register_popular_posts_widget() {
	register_widget( 'LIGHTVC_Popular_Posts_Widget' );
}

add_action( 'widgets_init', 'lightvc_register_popular_posts_widget' );
