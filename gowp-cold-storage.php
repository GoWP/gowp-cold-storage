<?php
/**
 * Cold Storage
 *
 * @wordpress-plugin
 * Plugin Name: GoWP Cold Storage
 * Description: Prevents access to all files/assets of inactive plugins
 * Version:     1.0.0
 * Author:      GoWP
 * Author URI:  https://www.gowp.com
 * Text Domain: gowp-cold-storage
 */

add_action( 'update_option_active_plugins', 'gcs_update_rules', 10, 3 );
function gcs_update_rules( $old, $new, $option ) {
	if ( ! in_array( 'gowp-cold-storage/gowp-cold-storage.php', $new ) ) {
		$rules = "";
	} else {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( $plugins as $plugin => $data ) {
			if ( ! in_array( $plugin, $new ) ) {
				$folder = str_replace( home_url( '/' ), '', plugins_url() . "/" . dirname( $plugin ) );
				$rules[] = "RedirectMatch 403 ^/{$folder}/.*$";
			}
		}
	}
	insert_with_markers( WP_PLUGIN_DIR . "/.htaccess", "GoWP Cold Storage", $rules );
}