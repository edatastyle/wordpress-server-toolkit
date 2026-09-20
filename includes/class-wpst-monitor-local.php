<?php
/**
 * Local monitoring: everything observable directly from PHP running
 * inside WordPress, without needing the remote Linux agent —
 * WordPress health, PHP config, WP-Cron, filesystem permissions, SSL.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Monitor_Local {

	/** Local checks are stored against a virtual "server_id = 0" (this site). */
	const LOCAL_SERVER_ID = 0;

	public static function collect_and_store() {
		$snapshot = array(
			'wordpress' => self::wordpress_health(),
			'php'       => self::php_health(),
			'cron'      => self::cron_health(),
			'fs'        => self::filesystem_health(),
			'ssl'       => self::ssl_health(),
		);

		global $wpdb;
		$table = WPST_Database::table( 'metrics' );
		foreach ( $snapshot as $type => $payload ) {
			$wpdb->insert(
				$table,
				array(
					'server_id'   => self::LOCAL_SERVER_ID,
					'metric_type' => 'local_' . $type,
					'resolution'  => 'raw',
					'payload'     => wp_json_encode( $payload ),
					'recorded_at' => current_time( 'mysql', true ),
				)
			);
		}

		self::scan_debug_log();

		return $snapshot;
	}

	public static function wordpress_health() {
		global $wp_version;
		return array(
			'wp_version'        => $wp_version,
			'php_version'       => PHP_VERSION,
			'wp_memory_limit'   => WP_MEMORY_LIMIT,
			'debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log'         => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'debug_display'     => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'file_edit_enabled' => ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ),
			'xmlrpc_enabled'    => apply_filters( 'xmlrpc_enabled', true ),
			'is_multisite'      => is_multisite(),
		);
	}

	public static function php_health() {
		return array(
			'version'            => PHP_VERSION,
			'sapi'               => php_sapi_name(),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'upload_max_filesize'=> ini_get( 'upload_max_filesize' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_input_vars'     => ini_get( 'max_input_vars' ),
			'opcache_enabled'    => function_exists( 'opcache_get_status' ) && opcache_get_status( false ) !== false,
		);
	}

	public static function cron_health() {
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$crons         = _get_cron_array();
		$now           = time();
		$scheduled     = 0;
		$overdue       = 0;

		if ( is_array( $crons ) ) {
			foreach ( $crons as $timestamp => $hooks ) {
				$scheduled += count( $hooks );
				if ( $timestamp < $now - 300 ) {
					$overdue += count( $hooks );
				}
			}
		}

		return array(
			'enabled'          => ! $cron_disabled,
			'scheduled_events' => $scheduled,
			'overdue_events'   => $overdue,
			'status'           => ( $overdue > 0 ) ? 'warning' : 'healthy',
		);
	}

	public static function filesystem_health() {
		$paths = array(
			'wp-content'          => WP_CONTENT_DIR,
			'uploads'             => wp_get_upload_dir()['basedir'] ?? WP_CONTENT_DIR . '/uploads',
			'plugins'             => WP_PLUGIN_DIR,
			'themes'              => get_theme_root(),
			'wp-config.php'       => ABSPATH . 'wp-config.php',
		);

		$results = array();
		foreach ( $paths as $label => $path ) {
			if ( ! file_exists( $path ) ) {
				$results[ $label ] = array( 'exists' => false );
				continue;
			}
			$perms   = substr( sprintf( '%o', fileperms( $path ) ), -3 );
			$writable = is_writable( $path );
			$world_writable = ( (int) substr( $perms, -1 ) >= 6 );

			$results[ $label ] = array(
				'exists'         => true,
				'permissions'    => $perms,
				'writable'       => $writable,
				'world_writable' => $world_writable,
				'status'         => $world_writable ? 'critical' : 'healthy',
			);
		}

		$disk_free  = @disk_free_space( ABSPATH );
		$disk_total = @disk_total_space( ABSPATH );

		if ( $disk_total ) {
			$results['disk'] = array(
				'free_bytes'    => $disk_free,
				'total_bytes'   => $disk_total,
				'used_percent'  => round( ( 1 - ( $disk_free / $disk_total ) ) * 100, 1 ),
			);
		}

		return $results;
	}

	/**
	 * Check the SSL certificate of the site's own hostname without any
	 * external dependency — connects via a TLS stream context.
	 */
	public static function ssl_health() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host || ! is_ssl() && strpos( home_url(), 'https://' ) !== 0 ) {
			return array( 'enabled' => false );
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
				),
			)
		);

		$errno  = 0;
		$errstr = '';
		$client = @stream_socket_client(
			"ssl://{$host}:443",
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ( ! $client ) {
			return array(
				'enabled' => true,
				'status'  => 'error',
				'error'   => $errstr,
			);
		}

		$params = stream_context_get_params( $client );
		fclose( $client );

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return array( 'enabled' => true, 'status' => 'unknown' );
		}

		$cert_info = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		$expires   = $cert_info['validTo_time_t'] ?? 0;
		$days_left = $expires ? round( ( $expires - time() ) / DAY_IN_SECONDS ) : null;

		return array(
			'enabled'        => true,
			'issuer'         => $cert_info['issuer']['O'] ?? ( $cert_info['issuer']['CN'] ?? '' ),
			'hostname'       => $host,
			'expires_at'     => $expires ? gmdate( 'Y-m-d H:i:s', $expires ) : null,
			'days_remaining' => $days_left,
			'status'         => is_null( $days_left ) ? 'unknown' : ( $days_left < 0 ? 'expired' : 'valid' ),
		);
	}

	/**
	 * Parse wp-content/debug.log for new entries and store them,
	 * deduplicated by a hash of level + normalized message + file + line,
	 * so identical recurring errors increment an occurrence counter
	 * instead of creating new rows every time.
	 */
	public static function scan_debug_log() {
		$log_file = WP_CONTENT_DIR . '/debug.log';
		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			return;
		}

		$last_offset = (int) get_option( 'wpst_debug_log_offset', 0 );
		$size        = filesize( $log_file );

		// Log rotated/truncated — start over.
		if ( $size < $last_offset ) {
			$last_offset = 0;
		}

		$handle = fopen( $log_file, 'r' );
		if ( ! $handle ) {
			return;
		}
		fseek( $handle, $last_offset );

		global $wpdb;
		$table = WPST_Database::table( 'errors' );
		$now   = current_time( 'mysql', true );

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$parsed = self::parse_error_line( $line );
			if ( ! $parsed ) {
				continue;
			}

			$fingerprint = hash( 'sha256', $parsed['level'] . '|' . $parsed['message'] . '|' . $parsed['file'] . '|' . $parsed['line'] );

			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT id, occurrences FROM {$table} WHERE fingerprint = %s", $fingerprint ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( $existing ) {
				$wpdb->update(
					$table,
					array(
						'occurrences' => $existing->occurrences + 1,
						'last_seen'   => $now,
					),
					array( 'id' => $existing->id )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'server_id'   => self::LOCAL_SERVER_ID,
						'fingerprint' => $fingerprint,
						'level'       => $parsed['level'],
						'message'     => $parsed['message'],
						'file'        => $parsed['file'],
						'line'        => $parsed['line'],
						'occurrences' => 1,
						'status'      => 'active',
						'first_seen'  => $now,
						'last_seen'   => $now,
					)
				);
			}
		}

		update_option( 'wpst_debug_log_offset', ftell( $handle ) );
		fclose( $handle );
	}

	private static function parse_error_line( $line ) {
		// Typical line: [18-Sep-2026 10:12:03 UTC] PHP Fatal error: Uncaught Error: ... in /path/file.php:123
		if ( ! preg_match( '/PHP (Fatal error|Warning|Notice|Deprecated|Parse error)?:?\s*(.+)/i', $line, $matches ) ) {
			return false;
		}

		$level_raw = strtolower( trim( $matches[1] ?? '' ) );
		$level_map = array(
			'fatal error' => 'fatal',
			'warning'     => 'warning',
			'notice'      => 'notice',
			'deprecated'  => 'deprecated',
			'parse error' => 'fatal',
		);
		$level = $level_map[ $level_raw ] ?? 'unknown';

		$message = trim( $matches[2] );
		$file    = '';
		$lineno  = 0;

		if ( preg_match( '/ in (.+?) on line (\d+)/', $message, $loc ) ) {
			$file   = $loc[1];
			$lineno = (int) $loc[2];
			$message = trim( str_replace( $loc[0], '', $message ) );
		} elseif ( preg_match( '/ in (.+?):(\d+)/', $message, $loc ) ) {
			$file   = $loc[1];
			$lineno = (int) $loc[2];
		}

		return array(
			'level'   => $level,
			'message' => mb_substr( $message, 0, 1000 ),
			'file'    => mb_substr( $file, 0, 500 ),
			'line'    => $lineno,
		);
	}
}
