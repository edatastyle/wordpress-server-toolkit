<?php
/**
 * Background processing: offline detection, aggregation, retention cleanup.
 * Nothing expensive runs on normal frontend/admin page loads.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Cron {

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( 'wpst_cron_minute', array( $this, 'run_minute_tasks' ) );
		add_action( 'wpst_cron_hourly', array( $this, 'run_hourly_tasks' ) );
		add_action( 'wpst_cron_daily', array( $this, 'run_daily_tasks' ) );
	}

	public function add_schedules( $schedules ) {
		$schedules['wpst_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute (WP Server Toolkit)', 'wp-server-toolkit' ),
		);
		return $schedules;
	}

	/**
	 * Runs every minute: offline detection + local (WP-side) health checks
	 * + threshold evaluation against the most recent metrics.
	 */
	public function run_minute_tasks() {
		WPST_Servers::mark_offline_servers();
		WPST_Monitor_Local::collect_and_store();
		WPST_Alerts::evaluate_all();
	}

	/**
	 * Runs hourly: roll raw metrics up into hourly aggregates.
	 */
	public function run_hourly_tasks() {
		$this->aggregate_metrics( 'hourly', '-1 hour' );
	}

	/**
	 * Runs daily: roll hourly aggregates into daily aggregates and enforce
	 * retention settings (raw data does not get kept forever).
	 */
	public function run_daily_tasks() {
		$this->aggregate_metrics( 'daily', '-1 day' );
		$this->enforce_retention();
	}

	/**
	 * Aggregate raw metric rows older than $since into averaged rows at
	 * the given $resolution, then remove the raw rows that were folded in.
	 */
	private function aggregate_metrics( $resolution, $since ) {
		global $wpdb;
		$table = WPST_Database::table( 'metrics' );
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( $since, current_time( 'timestamp', true ) ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- table name is a fixed constant, values are escaped via $wpdb->prepare below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT server_id, metric_type, payload FROM {$table} WHERE resolution = 'raw' AND recorded_at < %s",
				$cutoff
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$buckets = array();
		foreach ( $rows as $row ) {
			$key = $row->server_id . '|' . $row->metric_type;
			$data = json_decode( $row->payload, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			foreach ( $data as $k => $v ) {
				if ( is_numeric( $v ) ) {
					$buckets[ $key ]['sums'][ $k ]  = ( $buckets[ $key ]['sums'][ $k ] ?? 0 ) + $v;
					$buckets[ $key ]['counts'][ $k ] = ( $buckets[ $key ]['counts'][ $k ] ?? 0 ) + 1;
				}
			}
			$buckets[ $key ]['server_id']   = $row->server_id;
			$buckets[ $key ]['metric_type'] = $row->metric_type;
		}

		foreach ( $buckets as $bucket ) {
			$averaged = array();
			foreach ( $bucket['sums'] as $k => $sum ) {
				$averaged[ $k ] = round( $sum / max( 1, $bucket['counts'][ $k ] ), 2 );
			}
			$wpdb->insert(
				$table,
				array(
					'server_id'   => $bucket['server_id'],
					'metric_type' => $bucket['metric_type'],
					'resolution'  => $resolution,
					'payload'     => wp_json_encode( $averaged ),
					'recorded_at' => $cutoff,
				)
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE resolution = 'raw' AND recorded_at < %s",
				$cutoff
			)
		);
		// phpcs:enable
	}

	/**
	 * Delete data older than the configured retention windows.
	 */
	private function enforce_retention() {
		global $wpdb;
		$settings = get_option( 'wpst_settings', array() );

		$metric_days = absint( $settings['metric_retention_days'] ?? 30 );
		$error_days  = absint( $settings['error_retention_days'] ?? 90 );

		$metrics_cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$metric_days} days" ) );
		$errors_cutoff  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$error_days} days" ) );

		$metrics_table = WPST_Database::table( 'metrics' );
		$errors_table  = WPST_Database::table( 'errors' );
		$uptime_table  = WPST_Database::table( 'uptime' );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$metrics_table} WHERE recorded_at < %s", $metrics_cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$errors_table} WHERE last_seen < %s AND status != 'active'", $errors_cutoff ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$uptime_table} WHERE checked_at < %s", $metrics_cutoff ) );
		// phpcs:enable
	}
}
