<?php
/**
 * Alert history with filters and resolve/ignore action.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
$alerts = WPST_Alerts::get_history( null, $status ?: null, 100 );
?>
<div class="wrap wpst-wrap">
	<h1><?php esc_html_e( 'Alerts', 'wp-server-toolkit' ); ?></h1>

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpst-alerts' ) ); ?>" class="<?php echo '' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'wp-server-toolkit' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpst-alerts&status=open' ) ); ?>" class="<?php echo 'open' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Open', 'wp-server-toolkit' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpst-alerts&status=resolved' ) ); ?>" class="<?php echo 'resolved' === $status ? 'current' : ''; ?>"><?php esc_html_e( 'Resolved', 'wp-server-toolkit' ); ?></a></li>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Server', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Severity', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Message', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Started', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Resolved', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-server-toolkit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $alerts ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No alerts found.', 'wp-server-toolkit' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $alerts as $alert ) :
				$server_name = 0 === (int) $alert->server_id ? __( 'This site', 'wp-server-toolkit' ) : ( ( $s = WPST_Servers::get( $alert->server_id ) ) ? $s->name : '#' . $alert->server_id );
				?>
				<tr>
					<td><?php echo esc_html( $server_name ); ?></td>
					<td><span class="wpst-status wpst-status--<?php echo esc_attr( $alert->severity ); ?>"><?php echo esc_html( ucfirst( $alert->severity ) ); ?></span></td>
					<td><?php echo esc_html( $alert->message ); ?></td>
					<td><?php echo esc_html( ucfirst( $alert->status ) ); ?></td>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $alert->started_at ) ); ?></td>
					<td><?php echo $alert->resolved_at ? esc_html( mysql2date( 'Y-m-d H:i', $alert->resolved_at ) ) : '—'; ?></td>
					<td>
						<?php if ( 'open' === $alert->status && current_user_can( 'manage_wp_server_toolkit_alerts' ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'wpst_resolve_alert' ); ?>
								<input type="hidden" name="action" value="wpst_resolve_alert">
								<input type="hidden" name="alert_id" value="<?php echo esc_attr( $alert->id ); ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Ignore', 'wp-server-toolkit' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
