<?php
/**
 * Custom database tables.
 *
 * Monitoring data does not belong in wp_options — it uses dedicated,
 * indexed tables so multi-server, historical data stays performant.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Database {

	/**
	 * Create / upgrade all plugin tables using dbDelta().
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'wpst_';

		$sql = array();

		// Servers.
		$sql[] = "CREATE TABLE {$prefix}servers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			hostname VARCHAR(191) DEFAULT '',
			agent_id VARCHAR(64) DEFAULT '',
			auth_token_hash VARCHAR(255) DEFAULT '',
			registration_token VARCHAR(64) DEFAULT '',
			registration_expires DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			tags VARCHAR(255) DEFAULT '',
			agent_version VARCHAR(20) DEFAULT '',
			last_seen DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY agent_id (agent_id)
		) {$charset_collate};";

		// Metrics (raw + aggregated, distinguished by `resolution`).
		$sql[] = "CREATE TABLE {$prefix}metrics (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			server_id BIGINT UNSIGNED NOT NULL,
			metric_type VARCHAR(40) NOT NULL,
			resolution VARCHAR(10) NOT NULL DEFAULT 'raw',
			payload LONGTEXT NOT NULL,
			recorded_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY server_id (server_id),
			KEY metric_type (metric_type),
			KEY recorded_at (recorded_at),
			KEY server_type_time (server_id, metric_type, recorded_at)
		) {$charset_collate};";

		// Alerts.
		$sql[] = "CREATE TABLE {$prefix}alerts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			server_id BIGINT UNSIGNED NOT NULL,
			event_key VARCHAR(100) NOT NULL,
			severity VARCHAR(20) NOT NULL DEFAULT 'warning',
			message TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			started_at DATETIME NOT NULL,
			resolved_at DATETIME NULL,
			last_notified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY server_id (server_id),
			KEY status (status),
			KEY event_key (event_key)
		) {$charset_collate};";

		// WordPress / application errors, deduplicated by fingerprint.
		$sql[] = "CREATE TABLE {$prefix}errors (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			server_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			fingerprint VARCHAR(64) NOT NULL,
			level VARCHAR(20) NOT NULL DEFAULT 'unknown',
			message TEXT NOT NULL,
			file VARCHAR(500) DEFAULT '',
			line INT DEFAULT 0,
			occurrences BIGINT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY server_id (server_id),
			KEY status (status)
		) {$charset_collate};";

		// Uptime checks.
		$sql[] = "CREATE TABLE {$prefix}uptime (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			server_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			url VARCHAR(500) NOT NULL,
			status VARCHAR(20) NOT NULL,
			http_code SMALLINT DEFAULT 0,
			response_ms INT DEFAULT 0,
			checked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY server_id (server_id),
			KEY checked_at (checked_at)
		) {$charset_collate};";

		// Internal plugin debug/audit log (never stores secrets).
		$sql[] = "CREATE TABLE {$prefix}logs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			server_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			log_type VARCHAR(30) NOT NULL DEFAULT 'system',
			message TEXT NOT NULL,
			context LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY server_id (server_id),
			KEY log_type (log_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'wpst_db_version', WPST_DB_VERSION );
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpst_' . $name;
	}
}
