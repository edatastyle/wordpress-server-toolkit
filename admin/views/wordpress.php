<?php
/**
 * WordPress health: debug errors, cron, filesystem permissions, PHP config.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$errors_table = WPST_Database::table( 'errors' );
$errors       = $wpdb->get_results( "SELECT * FROM {$errors_table} WHERE status = 'active' ORDER BY last_seen DESC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$php    = WPST_Monitor_Local::php_health();
$cron   = WPST_Monitor_Local::cron_health();
$fs     = WPST_Monitor_Local::filesystem_health();
?>
<div class="wrap wpst-wrap">
	<h1><?php esc_html_e( 'WordPress Health', 'wp-server-toolkit' ); ?></h1>

	<div class="wpst-grid">
		<div class="wpst-card">
			<h2><?php esc_html_e( 'PHP Configuration', 'wp-server-toolkit' ); ?></h2>
			<table class="wpst-metric-table">
				<tr><th><?php esc_html_e( 'Version', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $php['version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'SAPI', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $php['sapi'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Memory Limit', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $php['memory_limit'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Max Execution Time', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $php['max_execution_time'] ); ?>s</td></tr>
				<tr><th><?php esc_html_e( 'Upload Max Filesize', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $php['upload_max_filesize'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'OPcache', 'wp-server-toolkit' ); ?></th><td><?php echo $php['opcache_enabled'] ? esc_html__( 'Enabled', 'wp-server-toolkit' ) : esc_html__( 'Disabled', 'wp-server-toolkit' ); ?></td></tr>
			</table>
		</div>

		<div class="wpst-card">
			<h2><?php esc_html_e( 'WP-Cron', 'wp-server-toolkit' ); ?></h2>
			<table class="wpst-metric-table">
				<tr><th><?php esc_html_e( 'Enabled', 'wp-server-toolkit' ); ?></th><td><?php echo $cron['enabled'] ? esc_html__( 'Yes', 'wp-server-toolkit' ) : esc_html__( 'No (DISABLE_WP_CRON)', 'wp-server-toolkit' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Scheduled Events', 'wp-server-toolkit' ); ?></th><td><?php echo (int) $cron['scheduled_events']; ?></td></tr>
				<tr><th><?php esc_html_e( 'Overdue Events', 'wp-server-toolkit' ); ?></th><td><?php echo (int) $cron['overdue_events']; ?></td></tr>
			</table>
		</div>

		<div class="wpst-card wpst-card--wide">
			<h2><?php esc_html_e( 'Filesystem Permissions', 'wp-server-toolkit' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th><?php esc_html_e( 'Path', 'wp-server-toolkit' ); ?></th><th><?php esc_html_e( 'Permissions', 'wp-server-toolkit' ); ?></th><th><?php esc_html_e( 'Status', 'wp-server-toolkit' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $fs as $label => $info ) : ?>
						<?php if ( 'disk' === $label || empty( $info['exists'] ) ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo esc_html( $info['permissions'] ); ?></td>
							<td>
								<?php if ( 'critical' === $info['status'] ) : ?>
									<span class="wpst-status wpst-status--critical"><?php esc_html_e( 'World-writable', 'wp-server-toolkit' ); ?></span>
								<?php else : ?>
									<span class="wpst-status wpst-status--healthy"><?php esc_html_e( 'Healthy', 'wp-server-toolkit' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( isset( $fs['disk'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'Disk (site root)', 'wp-server-toolkit' ); ?></td>
							<td><?php echo esc_html( $fs['disk']['used_percent'] ); ?>% <?php esc_html_e( 'used', 'wp-server-toolkit' ); ?></td>
							<td>
								<?php if ( $fs['disk']['used_percent'] >= 85 ) : ?>
									<span class="wpst-status wpst-status--warning"><?php esc_html_e( 'High', 'wp-server-toolkit' ); ?></span>
								<?php else : ?>
									<span class="wpst-status wpst-status--healthy"><?php esc_html_e( 'Healthy', 'wp-server-toolkit' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="wpst-card wpst-card--wide">
			<h2><?php esc_html_e( 'WordPress Errors', 'wp-server-toolkit' ); ?> <span class="description">(<?php esc_html_e( 'from wp-content/debug.log, grouped by fingerprint', 'wp-server-toolkit' ); ?>)</span></h2>
			<?php if ( empty( $errors ) ) : ?>
				<p class="wpst-empty"><?php esc_html_e( 'No active errors detected. Enable WP_DEBUG_LOG to capture PHP errors here.', 'wp-server-toolkit' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Level', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Message', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'File:Line', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Occurrences', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Last Seen', 'wp-server-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $errors as $error ) : ?>
							<tr>
								<td><span class="wpst-status wpst-status--<?php echo esc_attr( 'fatal' === $error->level ? 'critical' : ( 'warning' === $error->level ? 'warning' : 'healthy' ) ); ?>"><?php echo esc_html( ucfirst( $error->level ) ); ?></span></td>
								<td><?php echo esc_html( wp_trim_words( $error->message, 20 ) ); ?></td>
								<td><code><?php echo esc_html( $error->file . ':' . $error->line ); ?></code></td>
								<td><?php echo (int) $error->occurrences; ?></td>
								<td><?php echo esc_html( human_time_diff( strtotime( $error->last_seen . ' UTC' ) ) . ' ' . __( 'ago', 'wp-server-toolkit' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
