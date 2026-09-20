<?php
/**
 * Alert engine.
 *
 * Alerts are deduplicated: a single ongoing problem (e.g. "PHP-FPM down")
 * stays as ONE open row that gets touched, not one row per check cycle,
 * and produces exactly one notification on open + one on resolve.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Alerts {

	/**
	 * Re-evaluate every server's most recent metrics against thresholds.
	 * Called once a minute from WPST_Cron.
	 */
	public static function evaluate_all() {
		$settings   = get_option( 'wpst_settings', array() );
		$thresholds = $settings['thresholds'] ?? array();

		// Local (this WordPress install) checks that don't need the agent.
		self::evaluate_local( $thresholds );

		// Remote agent-reported servers.
		foreach ( WPST_Servers::get_all() as $server ) {
			if ( 'offline' === $server->status ) {
				self::open_or_touch(
					$server->id,
					'server_offline',
					'critical',
					sprintf( /* translators: %s: server name */ __( 'Server "%s" has not sent a heartbeat.', 'wp-server-toolkit' ), $server->name )
				);
			} else {
				self::resolve( $server->id, 'server_offline' );
			}

			$latest = self::latest_metric( $server->id, 'system' );
			if ( $latest ) {
				self::check_threshold( $server->id, 'cpu_high', $latest['cpu_percent'] ?? null, $thresholds['cpu_percent'] ?? 90, 'CPU usage is %s%% (threshold %s%%).' );
				self::check_threshold( $server->id, 'ram_high', $latest['ram_percent'] ?? null, $thresholds['ram_percent'] ?? 90, 'RAM usage is %s%% (threshold %s%%).' );
				self::check_threshold( $server->id, 'disk_high', $latest['disk_percent'] ?? null, $thresholds['disk_percent'] ?? 85, 'Disk usage is %s%% (threshold %s%%).' );
			}

			foreach ( array( 'nginx', 'php_fpm', 'mysql' ) as $service ) {
				$svc = self::latest_metric( $server->id, $service );
				if ( $svc && isset( $svc['running'] ) && ! $svc['running'] ) {
					self::open_or_touch( $server->id, $service . '_down', 'critical', ucfirst( str_replace( '_', '-', $service ) ) . ' is not running.' );
				} else {
					self::resolve( $server->id, $service . '_down' );
				}
			}
		}
	}

	private static function evaluate_local( $thresholds ) {
		$ssl = self::latest_metric( WPST_Monitor_Local::LOCAL_SERVER_ID, 'local_ssl' );
		if ( $ssl && isset( $ssl['days_remaining'] ) && null !== $ssl['days_remaining'] ) {
			$limit = absint( $thresholds['ssl_days_remaining'] ?? 30 );
			if ( $ssl['days_remaining'] <= 0 ) {
				self::open_or_touch( 0, 'ssl_expired', 'critical', __( 'SSL certificate has expired.', 'wp-server-toolkit' ) );
			} elseif ( $ssl['days_remaining'] <= $limit ) {
				self::open_or_touch(
					0,
					'ssl_expiring',
					'warning',
					sprintf( /* translators: %d: days remaining */ __( 'SSL certificate expires in %d day(s).', 'wp-server-toolkit' ), $ssl['days_remaining'] )
				);
			} else {
				self::resolve( 0, 'ssl_expiring' );
				self::resolve( 0, 'ssl_expired' );
			}
		}

		$wp = self::latest_metric( WPST_Monitor_Local::LOCAL_SERVER_ID, 'local_wordpress' );
		if ( $wp ) {
			// Informational, not alerted by default — surfaced on the Security screen instead.
		}
	}

	private static function check_threshold( $server_id, $key, $value, $limit, $message_template ) {
		if ( null === $value ) {
			return;
		}
		if ( $value >= $limit ) {
			self::open_or_touch(
				$server_id,
				$key,
				'warning',
				sprintf( $message_template, $value, $limit )
			);
		} else {
			self::resolve( $server_id, $key );
		}
	}

	/**
	 * Open a new alert, or silently touch an existing open one — this is
	 * what prevents "30 emails" for a single ongoing outage.
	 */
	private static function open_or_touch( $server_id, $event_key, $severity, $message ) {
		global $wpdb;
		$table = WPST_Database::table( 'alerts' );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE server_id = %d AND event_key = %s AND status = 'open' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$server_id,
				$event_key
			)
		);

		if ( $existing ) {
			return; // Already open — no duplicate row, no duplicate notification.
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'server_id'  => $server_id,
				'event_key'  => $event_key,
				'severity'   => $severity,
				'message'    => $message,
				'status'     => 'open',
				'started_at' => $now,
			)
		);

		WPST_Notifications::notify_opened( $server_id, $event_key, $severity, $message );
	}

	/**
	 * Resolve an open alert (if any) and send exactly one recovery notice.
	 */
	private static function resolve( $server_id, $event_key ) {
		global $wpdb;
		$table = WPST_Database::table( 'alerts' );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE server_id = %d AND event_key = %s AND status = 'open' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$server_id,
				$event_key
			)
		);

		if ( ! $existing ) {
			return;
		}

		$now = current_time( 'mysql', true );
		$wpdb->update(
			$table,
			array(
				'status'      => 'resolved',
				'resolved_at' => $now,
			),
			array( 'id' => $existing->id )
		);

		$duration = human_time_diff( strtotime( $existing->started_at . ' UTC' ), strtotime( $now . ' UTC' ) );
		WPST_Notifications::notify_resolved( $server_id, $event_key, $existing->message, $duration );
	}

	private static function latest_metric( $server_id, $metric_type ) {
		global $wpdb;
		$table = WPST_Database::table( 'metrics' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT payload FROM {$table} WHERE server_id = %d AND metric_type = %s ORDER BY recorded_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$server_id,
				$metric_type
			)
		);

		return $row ? json_decode( $row->payload, true ) : null;
	}

	public static function get_history( $server_id = null, $status = null, $limit = 100 ) {
		global $wpdb;
		$table = WPST_Database::table( 'alerts' );
		$where = array( '1=1' );
		$args  = array();

		if ( null !== $server_id ) {
			$where[] = 'server_id = %d';
			$args[]  = $server_id;
		}
		if ( $status ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}
		$args[] = $limit;

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY started_at DESC LIMIT %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
