<?php
/**
 * Overview dashboard.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$local_wp   = WPST_Monitor_Local::wordpress_health();
$local_php  = WPST_Monitor_Local::php_health();
$local_cron = WPST_Monitor_Local::cron_health();
$local_ssl  = WPST_Monitor_Local::ssl_health();
$servers    = WPST_Servers::get_all();
$open_alerts = WPST_Alerts::get_history( null, 'open', 20 );
?>
<div class="wrap wpst-wrap">
	<h1><?php esc_html_e( 'WP Server Toolkit by aThemeArt.com — Overview', 'wp-server-toolkit' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Live status refreshes automatically based on your configured polling interval.', 'wp-server-toolkit' ); ?>
		<span id="wpst-last-updated"></span>
	</p>

	<div class="wpst-grid">

		<div class="wpst-card">
			<h2><?php esc_html_e( 'This WordPress Site', 'wp-server-toolkit' ); ?></h2>
			<table class="wpst-metric-table">
				<tr><th><?php esc_html_e( 'WordPress', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $local_wp['wp_version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'PHP', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $local_php['version'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'WP Memory Limit', 'wp-server-toolkit' ); ?></th><td><?php echo esc_html( $local_wp['wp_memory_limit'] ); ?></td></tr>
				<tr>
					<th><?php esc_html_e( 'Debug Mode', 'wp-server-toolkit' ); ?></th>
					<td><?php echo $local_wp['debug'] ? '<span class="wpst-status wpst-status--warning">' . esc_html__( 'Enabled', 'wp-server-toolkit' ) . '</span>' : '<span class="wpst-status wpst-status--healthy">' . esc_html__( 'Disabled', 'wp-server-toolkit' ) . '</span>'; ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'WP-Cron', 'wp-server-toolkit' ); ?></th>
					<td>
						<?php if ( 'healthy' === $local_cron['status'] ) : ?>
							<span class="wpst-status wpst-status--healthy"><?php esc_html_e( 'Healthy', 'wp-server-toolkit' ); ?></span>
						<?php else : ?>
							<span class="wpst-status wpst-status--warning"><?php printf( esc_html__( '%d overdue', 'wp-server-toolkit' ), (int) $local_cron['overdue_events'] ); ?></span>
						<?php endif; ?>
						(<?php echo (int) $local_cron['scheduled_events']; ?> <?php esc_html_e( 'scheduled', 'wp-server-toolkit' ); ?>)
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SSL Certificate', 'wp-server-toolkit' ); ?></th>
					<td>
						<?php if ( empty( $local_ssl['enabled'] ) ) : ?>
							<span class="wpst-status wpst-status--warning"><?php esc_html_e( 'Not using HTTPS', 'wp-server-toolkit' ); ?></span>
						<?php elseif ( 'valid' === ( $local_ssl['status'] ?? '' ) ) : ?>
							<span class="wpst-status wpst-status--healthy"><?php printf( esc_html__( '%d days remaining', 'wp-server-toolkit' ), (int) $local_ssl['days_remaining'] ); ?></span>
						<?php else : ?>
							<span class="wpst-status wpst-status--critical"><?php esc_html_e( 'Issue detected', 'wp-server-toolkit' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<div class="wpst-card">
			<h2><?php esc_html_e( 'Open Alerts', 'wp-server-toolkit' ); ?></h2>
			<?php if ( empty( $open_alerts ) ) : ?>
				<p class="wpst-empty"><?php esc_html_e( 'No open alerts. Everything looks healthy.', 'wp-server-toolkit' ); ?></p>
			<?php else : ?>
				<ul class="wpst-alert-list">
					<?php foreach ( $open_alerts as $alert ) : ?>
						<li class="wpst-alert wpst-alert--<?php echo esc_attr( $alert->severity ); ?>">
							<strong><?php echo esc_html( ucfirst( $alert->severity ) ); ?>:</strong>
							<?php echo esc_html( $alert->message ); ?>
							<span class="wpst-alert-time"><?php echo esc_html( human_time_diff( strtotime( $alert->started_at . ' UTC' ) ) ); ?> <?php esc_html_e( 'ago', 'wp-server-toolkit' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="wpst-card wpst-card--wide">
			<h2><?php esc_html_e( 'Servers', 'wp-server-toolkit' ); ?></h2>
			<?php if ( empty( $servers ) ) : ?>
				<p class="wpst-empty">
					<?php esc_html_e( 'No Linux servers connected yet.', 'wp-server-toolkit' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpst-servers' ) ); ?>"><?php esc_html_e( 'Add your first server', 'wp-server-toolkit' ); ?></a>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" id="wpst-server-overview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Server', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Last Seen', 'wp-server-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Agent Version', 'wp-server-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $servers as $server ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpst-servers&server=' . $server->id ) ); ?>"><?php echo esc_html( $server->name ); ?></a></td>
								<td><span class="wpst-status wpst-status--<?php echo esc_attr( $server->status === 'online' ? 'healthy' : ( $server->status === 'offline' ? 'critical' : 'warning' ) ); ?>"><?php echo esc_html( ucfirst( $server->status ) ); ?></span></td>
								<td><?php echo $server->last_seen ? esc_html( human_time_diff( strtotime( $server->last_seen . ' UTC' ) ) . ' ' . __( 'ago', 'wp-server-toolkit' ) ) : esc_html__( 'Never', 'wp-server-toolkit' ); ?></td>
								<td><?php echo esc_html( $server->agent_version ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>
</div>
