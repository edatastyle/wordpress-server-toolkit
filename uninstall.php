<?php
/**
 * Fired when the plugin is deleted via the WordPress admin.
 * Only removes monitoring data if the admin explicitly opted in via
 * Settings → "Delete monitoring data on uninstall" (default: keep data).
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'wpst_settings', array() );

if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	// Keep data — the admin may reinstall the plugin later.
	return;
}

global $wpdb;

$tables = array(
	'servers',
	'metrics',
	'alerts',
	'errors',
	'uptime',
	'logs',
);

foreach ( $tables as $table ) {
	$table_name = $wpdb->prefix . 'wpst_' . $table;
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

delete_option( 'wpst_settings' );
delete_option( 'wpst_db_version' );
delete_option( 'wpst_debug_log_offset' );

// Remove custom capabilities from all roles.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpst-capabilities.php';
WPST_Capabilities::unregister();
