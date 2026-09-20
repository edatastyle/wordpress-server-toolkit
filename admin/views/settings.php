<?php
/**
 * Settings: polling interval, retention, notification channels, thresholds.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = get_option( 'wpst_settings', array() );
$thresholds = $settings['thresholds'] ?? array();
?>
<div class="wrap wpst-wrap">
	<h1><?php esc_html_e( 'Settings', 'wp-server-toolkit' ); ?></h1>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-server-toolkit' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wpst_save_settings' ); ?>
		<input type="hidden" name="action" value="wpst_save_settings">

		<h2><?php esc_html_e( 'Monitoring', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="polling_interval_seconds"><?php esc_html_e( 'Polling Interval', 'wp-server-toolkit' ); ?></label></th>
				<td>
					<select name="polling_interval_seconds" id="polling_interval_seconds">
						<?php foreach ( array( 30 => '30 seconds', 60 => '60 seconds', 120 => '2 minutes', 300 => '5 minutes', 600 => '10 minutes' ) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( absint( $settings['polling_interval_seconds'] ?? 60 ), $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'How often the dashboard refreshes. The agent reports independently on its own schedule.', 'wp-server-toolkit' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="metric_retention_days"><?php esc_html_e( 'Metric Retention', 'wp-server-toolkit' ); ?></label></th>
				<td>
					<select name="metric_retention_days" id="metric_retention_days">
						<?php foreach ( array( 7, 30, 90, 180 ) as $days ) : ?>
							<option value="<?php echo esc_attr( $days ); ?>" <?php selected( absint( $settings['metric_retention_days'] ?? 30 ), $days ); ?>><?php echo esc_html( $days ); ?> days</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="error_retention_days"><?php esc_html_e( 'Error Retention', 'wp-server-toolkit' ); ?></label></th>
				<td>
					<select name="error_retention_days" id="error_retention_days">
						<?php foreach ( array( 30, 90, 365 ) as $days ) : ?>
							<option value="<?php echo esc_attr( $days ); ?>" <?php selected( absint( $settings['error_retention_days'] ?? 90 ), $days ); ?>><?php echo esc_html( $days ); ?> days</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Alert Thresholds', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="threshold_cpu_percent"><?php esc_html_e( 'CPU %', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="number" min="1" max="100" name="threshold_cpu_percent" id="threshold_cpu_percent" value="<?php echo esc_attr( $thresholds['cpu_percent'] ?? 90 ); ?>" class="small-text"> %
					<?php esc_html_e( 'sustained for', 'wp-server-toolkit' ); ?>
					<input type="number" min="1" name="threshold_cpu_minutes" value="<?php echo esc_attr( $thresholds['cpu_minutes'] ?? 5 ); ?>" class="small-text"> <?php esc_html_e( 'minutes', 'wp-server-toolkit' ); ?>
				</td>
			</tr>
			<tr>
				<th><label for="threshold_ram_percent"><?php esc_html_e( 'RAM %', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="number" min="1" max="100" name="threshold_ram_percent" id="threshold_ram_percent" value="<?php echo esc_attr( $thresholds['ram_percent'] ?? 90 ); ?>" class="small-text"> %</td>
			</tr>
			<tr>
				<th><label for="threshold_disk_percent"><?php esc_html_e( 'Disk %', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="number" min="1" max="100" name="threshold_disk_percent" id="threshold_disk_percent" value="<?php echo esc_attr( $thresholds['disk_percent'] ?? 85 ); ?>" class="small-text"> %</td>
			</tr>
			<tr>
				<th><label for="threshold_ssl_days"><?php esc_html_e( 'SSL expiry warning', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="number" min="1" name="threshold_ssl_days" id="threshold_ssl_days" value="<?php echo esc_attr( $thresholds['ssl_days_remaining'] ?? 30 ); ?>" class="small-text"> <?php esc_html_e( 'days remaining', 'wp-server-toolkit' ); ?></td>
			</tr>
			<tr>
				<th><label for="threshold_offline_seconds"><?php esc_html_e( 'Offline detection', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="number" min="30" name="threshold_offline_seconds" id="threshold_offline_seconds" value="<?php echo esc_attr( $thresholds['offline_seconds'] ?? 180 ); ?>" class="small-text"> <?php esc_html_e( 'seconds without heartbeat', 'wp-server-toolkit' ); ?></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Email Notifications', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="alert_email_recipients"><?php esc_html_e( 'Recipients', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="text" name="alert_email_recipients" id="alert_email_recipients" class="regular-text" value="<?php echo esc_attr( $settings['alert_email_recipients'] ?? get_option( 'admin_email' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Comma-separated list of email addresses.', 'wp-server-toolkit' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Telegram Notifications', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="telegram_bot_token"><?php esc_html_e( 'Bot Token', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="text" name="telegram_bot_token" id="telegram_bot_token" class="regular-text" value="<?php echo esc_attr( $settings['telegram_bot_token'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="telegram_chat_id"><?php esc_html_e( 'Chat ID', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="text" name="telegram_chat_id" id="telegram_chat_id" class="regular-text" value="<?php echo esc_attr( $settings['telegram_chat_id'] ?? '' ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Webhook', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="webhook_url"><?php esc_html_e( 'Webhook URL', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="url" name="webhook_url" id="webhook_url" class="regular-text" value="<?php echo esc_attr( $settings['webhook_url'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'wp-server-toolkit' ); ?></label></th>
				<td><input type="text" name="webhook_secret" id="webhook_secret" class="regular-text" value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>">
					<p class="description"><?php esc_html_e( 'Used to sign the payload via an X-WPST-Signature HMAC header.', 'wp-server-toolkit' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Uninstall', 'wp-server-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'On plugin deletion', 'wp-server-toolkit' ); ?></th>
				<td>
					<label><input type="checkbox" name="delete_data_on_uninstall" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete all monitoring data (servers, metrics, alerts, errors) when the plugin is deleted', 'wp-server-toolkit' ); ?></label>
					<p class="description"><?php esc_html_e( 'Default is to keep your data. Enable this only if you want a clean removal.', 'wp-server-toolkit' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'wp-server-toolkit' ) ); ?>
	</form>
</div>
