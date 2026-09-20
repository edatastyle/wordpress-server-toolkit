<?php
/**
 * Custom capabilities so access does not rely solely on manage_options.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Capabilities {

	const CAPS = array(
		'manage_wp_server_toolkit',
		'view_wp_server_toolkit',
		'manage_wp_server_toolkit_servers',
		'manage_wp_server_toolkit_alerts',
		'view_wp_server_toolkit_logs',
	);

	/**
	 * Grant all plugin capabilities to Administrator by default.
	 * Safe to call on every request — add_cap() is idempotent.
	 */
	public static function register() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( self::CAPS as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove capabilities from all roles (used on uninstall).
	 */
	public static function unregister() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles();
		}
		foreach ( $wp_roles->role_objects as $role ) {
			foreach ( self::CAPS as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
