<?php
/**
 * Servers list, add-server onboarding flow, and server detail.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$servers          = WPST_Servers::get_all();
$viewing_server_id = absint( $_GET['server'] ?? 0 );
$new_server_info   = null;

if ( isset( $_GET['wpst_new_server'] ) ) {
	$new_server_info = get_transient( 'wpst_new_server_' . get_current_user_id() );
	delete_transient( 'wpst_new_server_' . get_current_user_id() );
}

$site_url = rest_url( 'wp-server-toolkit/v1' );
?>
<div class="wrap wpst-wrap">
	<h1>
		<?php esc_html_e( 'Servers', 'wp-server-toolkit' ); ?>
		<button class="page-title-action" onclick="document.getElementById('wpst-add-server-modal').style.display='flex'"><?php esc_html_e( 'Add Server', 'wp-server-toolkit' ); ?></button>
	</h1>

	<?php if ( isset( $_GET['wpst_error'] ) ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Please enter a server name.', 'wp-server-toolkit' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $new_server_info ) : ?>
			<div class="notice notice-success wpst-onboarding">
	<h2><?php esc_html_e( 'Server created — install the agent to start monitoring', 'wp-server-toolkit' ); ?></h2>
	<p><?php esc_html_e( 'Run these commands on the target Linux server (Ubuntu/Debian). The registration token is single-use and expires in 15 minutes.', 'wp-server-toolkit' ); ?></p>

	<p><strong><?php esc_html_e( 'Registration token (expires in 15 minutes):', 'wp-server-toolkit' ); ?></strong>
		<code><?php echo esc_html( $new_server_info['registration_token'] ); ?></code>
	</p>

	<pre class="wpst-install-cmd"># 1. Download both files
curl -fsSL <?php echo esc_url( plugins_url( 'agent/install.sh', WPST_PLUGIN_FILE ) ); ?> -o wpst-install.sh
curl -fsSL <?php echo esc_url( plugins_url( 'agent/wpst-agent.sh', WPST_PLUGIN_FILE ) ); ?> -o wpst-agent.sh

# 2. Install the agent
sudo bash wpst-install.sh \
  --site-url "<?php echo esc_url( $site_url ); ?>" \
  --registration-token "<?php echo esc_html( $new_server_info['registration_token'] ); ?>" \
  --name "<?php echo esc_html( get_option( 'blogname' ) ); ?>"</pre>

	<p class="description">
		<strong><?php esc_html_e( 'Local development / self-signed certificate?', 'wp-server-toolkit' ); ?></strong>
		<?php esc_html_e( 'Add --insecure to the install command (never use this on a production server):', 'wp-server-toolkit' ); ?>
	</p>
	<pre class="wpst-install-cmd">sudo bash wpst-install.sh \
  --site-url "<?php echo esc_url( $site_url ); ?>" \
  --registration-token "<?php echo esc_html( $new_server_info['registration_token'] ); ?>" \
  --name "<?php echo esc_html( get_option( 'blogname' ) ); ?>" \
  --insecure</pre>

	<p class="description">
		<?php esc_html_e( 'If you get a 404, try adding /index.php before /wp-json/ in the --site-url (common when permalinks are set to Plain).', 'wp-server-toolkit' ); ?>
	</p>
</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Hostname', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Tags', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Last Seen', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Agent Version', 'wp-server-toolkit' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-server-toolkit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $servers ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No servers yet. Click "Add Server" to connect your first Linux server.', 'wp-server-toolkit' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $servers as $server ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $server->name ); ?></strong></td>
					<td><?php echo esc_html( $server->hostname ?: '—' ); ?></td>
					<td><span class="wpst-status wpst-status--<?php echo esc_attr( $server->status === 'online' ? 'healthy' : ( $server->status === 'offline' ? 'critical' : 'warning' ) ); ?>"><?php echo esc_html( ucfirst( $server->status ) ); ?></span></td>
					<td><?php echo esc_html( $server->tags ?: '—' ); ?></td>
					<td><?php echo $server->last_seen ? esc_html( human_time_diff( strtotime( $server->last_seen . ' UTC' ) ) . ' ' . __( 'ago', 'wp-server-toolkit' ) ) : esc_html__( 'Never', 'wp-server-toolkit' ); ?></td>
					<td><?php echo esc_html( $server->agent_version ?: '—' ); ?></td>
					<td>
						<?php if ( current_user_can( 'manage_wp_server_toolkit_servers' ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this server and its stored metrics?', 'wp-server-toolkit' ) ); ?>');" style="display:inline;">
								<?php wp_nonce_field( 'wpst_delete_server' ); ?>
								<input type="hidden" name="action" value="wpst_delete_server">
								<input type="hidden" name="server_id" value="<?php echo esc_attr( $server->id ); ?>">
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Remove', 'wp-server-toolkit' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div id="wpst-add-server-modal" class="wpst-modal" style="display:none;">
	<div class="wpst-modal-content">
		<h2><?php esc_html_e( 'Add Server', 'wp-server-toolkit' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wpst_add_server' ); ?>
			<input type="hidden" name="action" value="wpst_add_server">
			<p>
				<label for="server_name"><?php esc_html_e( 'Server Name', 'wp-server-toolkit' ); ?></label><br>
				<input type="text" id="server_name" name="server_name" class="regular-text" placeholder="Production" required>
			</p>
			<p>
				<label for="server_tags"><?php esc_html_e( 'Tags (comma separated)', 'wp-server-toolkit' ); ?></label><br>
				<input type="text" id="server_tags" name="server_tags" class="regular-text" placeholder="production, nginx">
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create & Get Install Command', 'wp-server-toolkit' ); ?></button>
				<button type="button" class="button" onclick="document.getElementById('wpst-add-server-modal').style.display='none'"><?php esc_html_e( 'Cancel', 'wp-server-toolkit' ); ?></button>
			</p>
		</form>
	</div>
</div>
