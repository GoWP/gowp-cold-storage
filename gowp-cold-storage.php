<?php

/**
 * GoWP Cold Storage
 *
 * @wordpress-plugin
 * Plugin Name: GoWP Cold Storage
 * Description: Prevents access to assets of inactive plugins/themes via .htaccess rules
 * Version:     1.0.0
 * Author:      GoWP
 * Author URI:  https://www.gowp.com
 * Text Domain: gowp-cold-storage
 */

if ( ! defined( 'WPINC' ) ) die;

register_activation_hook( __FILE__, 'gowp_cold_storage_update_theme_rules' );
register_deactivation_hook( __FILE__, 'gowp_cold_storage_remove_rules' );
register_uninstall_hook( __FILE__, 'gowp_cold_storage_uninstall' );
add_action( 'update_option_active_plugins', 'gowp_cold_storage_update_plugin_rules', 10, 3 );
add_action( 'update_option_stylesheet', 'gowp_cold_storage_update_theme_rules', 10, 3 );
add_action( 'admin_notices', 'gowp_cold_storage_admin_notice' );

function gowp_cold_storage_uninstall() {
	delete_option( 'gowp_cold_storage_update_failures' );
}	

function gowp_cold_storage_update_plugin_rules( $old, $new, $option ) {
	$file = str_replace( WP_PLUGIN_DIR, '', implode( "/", array_slice( explode( "/", str_replace( "trunk/", "", __FILE__ ) ), -2, 2 ) ) );
	if ( in_array( $file, $new ) ) {
		$rules = gowp_cold_storage_get_plugin_rules( $new );
		gowp_cold_storage_write_rules( $rules, 'plugins' );
	}
}

function gowp_cold_storage_get_plugin_rules( $active ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	foreach ( $plugins as $plugin => $data ) {
		if ( ! in_array( $plugin, $active ) ) {
			$slugs[] = dirname( $plugin );
		}
	}
	if ( ! empty( $slugs ) ) {
		$rules[] = 'RewriteRule ^(' . implode( '|', $slugs ) . ')/.*$ - [F]';
		return $rules;
	}
}

function gowp_cold_storage_update_theme_rules( $old = NULL, $new = NULL, $option = NULL ) {
	$rules = gowp_cold_storage_get_theme_rules( $new );
	gowp_cold_storage_write_rules( $rules, 'themes' );
}

function gowp_cold_storage_get_theme_rules( $active ) {
	$themes = wp_get_themes();
	if ( empty( $active ) ) $active = get_stylesheet();
	foreach ( $themes as $slug => $theme ) {
		if ( ( $slug != $active ) && ( $slug != $themes[ $active ]->Template ) ) {
			$slugs[] = $slug;
		}
	}
	if ( ! empty( $slugs ) ) {
		$rules[] = 'RewriteRule ^(' . implode( '|', $slugs ) . ')/.*$ - [F]';
		return $rules;
	}
}

function gowp_cold_storage_write_rules( $rules, $context ) {
	if ( ! empty( $rules ) ) {
		array_unshift(
			$rules,
			'RewriteEngine On',
			'RewriteCond %{HTTP_REFERER} !customize_changeset_uuid [NC]'
		);
	}
	$files = array(
		'plugins' => WP_PLUGIN_DIR . '/.htaccess',
		'themes' => get_theme_root() . '/.htaccess',
	);
	$file = $files[ $context ];
	$gowp_cold_storage_update_failures = get_option( 'gowp_cold_storage_update_failures' );
	if ( insert_with_markers( $file, "GoWP Cold Storage", $rules ) ) {
		unset( $gowp_cold_storage_update_failures[ $file ] );
	} else {
		$gowp_cold_storage_update_failures[ $file ] = $rules;
	}
	update_option( 'gowp_cold_storage_update_failures', $gowp_cold_storage_update_failures, FALSE );
}

function gowp_cold_storage_remove_rules() {
	gowp_cold_storage_write_rules( "", 'plugins' );
	gowp_cold_storage_write_rules( "", 'themes' );
}

function gowp_cold_storage_admin_notice() {
	if ( $gowp_cold_storage_update_failures = get_option( 'gowp_cold_storage_update_failures' ) ) {
		echo "<div class='notice notice-error is-dismissable'>";
		echo "<p>GoWP Cold Storage was unable to update configuration.</p>";
		foreach ( $gowp_cold_storage_update_failures as $file => $rules ) {
			echo "<p>Please ensure that <code>{$file}</code> is writeable.</p>";
		}
		echo "</div>";
	}
}