<?php
/**
 * Fired during plugin activation / deactivation.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Activator {

	public static function activate() {

		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( WPST_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'WP Server Toolkit Pro requires PHP 8.1 or higher.', 'wp-server-toolkit' )
			);
		}

		WPST_Database::install();
		WPST_Capabilities::register();

		// Sensible defaults — never overwrite existing settings on re-activation.
		add_option(
			'wpst_settings',
			array(
				'polling_interval_seconds' => 60,
				'metric_retention_days'    => 30,
				'error_retention_days'     => 90,
				'alert_email_recipients'   => get_option( 'admin_email' ),
				'telegram_bot_token'       => '',
				'telegram_chat_id'         => '',
				'webhook_url'              => '',
				'webhook_secret'           => '',
				'delete_data_on_uninstall' => false,
				'thresholds'               => array(
					'cpu_percent'        => 90,
					'cpu_minutes'        => 5,
					'ram_percent'        => 90,
					'disk_percent'       => 85,
					'ssl_days_remaining' => 30,
					'offline_seconds'    => 180,
				),
			)
		);

		if ( ! wp_next_scheduled( 'wpst_cron_minute' ) ) {
			wp_schedule_event( time(), 'wpst_every_minute', 'wpst_cron_minute' );
		}
		if ( ! wp_next_scheduled( 'wpst_cron_hourly' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpst_cron_hourly' );
		}
		if ( ! wp_next_scheduled( 'wpst_cron_daily' ) ) {
			wp_schedule_event( time(), 'daily', 'wpst_cron_daily' );
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpst_cron_minute' );
		wp_clear_scheduled_hook( 'wpst_cron_hourly' );
		wp_clear_scheduled_hook( 'wpst_cron_daily' );
		flush_rewrite_rules();
		// Data and tables are intentionally preserved here.
		// Full removal only happens in uninstall.php, and only when the
		// admin has opted in via "Delete monitoring data on uninstall".
	}
}
