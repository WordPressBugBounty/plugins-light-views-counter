<?php
/**
 * Admin Plugin Links
 *
 * @package Light_Views_Counter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// PLUGIN LINKS & META
// ============================================================================

/**
 * Add Settings link to plugin action links.
 *
 * Adds a convenient Settings link on the plugins page.
 * Link URL adapts based on Foxiz Core activation status.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array Modified plugin action links with Settings prepended.
 * @since 1.0.0
 *
 */
function lightvc_plugin_action_links( $links ) {

	// Determine correct settings URL based on Foxiz Core status.
	$settings_url = lightvc_is_foxiz_core_active()
		? admin_url( 'admin.php?page=light-views-counter' )
		: admin_url( 'options-general.php?page=light-views-counter' );

	// Create settings link.
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $settings_url ),
		esc_html__( 'Settings', 'light-views-counter' )
	);

	// Prepend to existing links.
	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . LIGHTVC_PLUGIN_BASENAME, 'lightvc_plugin_action_links' );

/**
 * Add meta links to plugin row.
 *
 * Adds Documentation and Support links to plugin meta row.
 *
 * @param array $links Existing plugin meta links.
 * @param string $file Plugin file path.
 *
 * @return array Modified plugin meta links.
 * @since 1.0.0
 *
 */
function lightvc_plugin_row_meta( $links, $file ) {
	if ( LIGHTVC_PLUGIN_BASENAME !== $file ) {
		return $links;
	}

	// Add documentation and support links.
	$additional_links = [
		'docs'    => sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://themeruby.com/lvc-documentation/' ),
			esc_html__( 'Documentation', 'light-views-counter' )
		),
		'support' => sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://wordpress.org/support/plugin/light-views-counter/' ),
			esc_html__( 'Support', 'light-views-counter' )
		),
	];

	return array_merge( $links, $additional_links );
}

add_filter( 'plugin_row_meta', 'lightvc_plugin_row_meta', 10, 2 );
