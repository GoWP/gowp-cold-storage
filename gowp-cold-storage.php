<?php
/**
 * Cold Storage
 *
 * @wordpress-plugin
 * Plugin Name: GoWP Cold Storage
 * Description: Prevents access to all files/assets of inactive plugins
 * Version:     1.0.0
 * Requires:    1.5.0
 * Tested:      4.9.8
 * Author:      GoWP
 * Author URI:  https://www.gowp.com
 * Text Domain: gowp-cold-storage
 */

/* Update Checker */

	require 'plugin-update-checker/plugin-update-checker.php';
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://tools.gowp.com/plugins/gowp-cold-storage/info.json',
		__FILE__
	);

/* Update .htaccess rules when one or more plugin's status changes */

	add_action( 'update_option_active_plugins', 'gcs_update_rules', 10, 3 );
	function gcs_update_rules( $old, $new, $option ) {
		$file = str_replace( WP_PLUGIN_DIR, '', implode( "/", array_slice( explode( "/", __FILE__ ), -2, 2 ) ) ); // eg. folder/plugin.php
		if ( ! in_array( $file, $new ) ) { // this plugin has been deactivated, remove all rules
			$rules = "";
		} else {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			foreach ( $plugins as $plugin => $data ) { // all installed plugins
				if ( ! in_array( $plugin, $new ) ) { // inactive plugin
					$folder = str_replace( home_url( '/' ), '', plugins_url() . "/" . dirname( $plugin ) ); // eg. /wp-content/plugins/folder/plugin.php
					$rules[] = "RedirectMatch 403 ^/{$folder}/.*$";
				}
			}
		}
		if ( ! insert_with_markers( WP_PLUGIN_DIR . "/.htaccess", "GoWP Cold Storage", $rules ) ) {
			add_option( 'gsc_update_failed', $rules );
		} else {
			delete_option( 'gsc_update_failed' );
		}
		
	}

/* Admin notice */

	add_action( 'admin_notices', 'gsc_admin_notice' );
	function gsc_admin_notice() {
		if ( $rules = get_option( 'gsc_update_failed' ) ) {
			$notice = translate( 'GoWP Cold Storage was unable to update configuration. Please ensure that <code>' . WP_PLUGIN_DIR . '/.htaccess</code> is writeable.' );
			echo "<div class='notice notice-error is-dismissable'><p>$notice</p></div>";
		}
	}