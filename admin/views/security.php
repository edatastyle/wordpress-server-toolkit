<?php
/**
 * Security checks: WordPress + reported Linux findings.
 * Reports configuration findings — never claims the server is "fully secure".
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp = WPST_Monitor_Local::wordpress_health();

$findings = array();

$findings[] = array(
	'label'  => __( 'WP_DEBUG', 'wp-server-toolkit' ),
	'status' => $wp['debug'] ? 'warning' : 'healthy',
	'detail' => $wp['debug'] ? __( 'Debug mode is enabled. Disable on production.', 'wp-server-toolkit' ) : __( 'Disabled.', 'wp-server-toolkit' ),
);
$findings[] = array(
	'label'  => __( 'Debug display', 'wp-server-toolkit' ),
	'status' => $wp['debug_display'] ? 'critical' : 'healthy',
	'detail' => $wp['debug_display'] ? __( 'Errors may be displayed to visitors.', 'wp-server-toolkit' ) : __( 'Not exposed to visitors.', 'wp-server-toolkit' ),
);
$findings[] = array(
	'label'  => __( 'File editor', 'wp-server-toolkit' ),
	'status' => $wp['file_edit_enabled'] ? 'warning' : 'healthy',
	'detail' => $wp['file_edit_enabled'] ? __( 'Plugin/theme file editor is enabled in wp-admin.', 'wp-server-toolkit' ) : __( 'Disabled via DISALLOW_FILE_EDIT.', 'wp-server-toolkit' ),
);
$findings[] = array(
	'label'  => __( 'XML-RPC', 'wp-server-toolkit' ),
	'status' => $wp['xmlrpc_enabled'] ? 'warning' : 'healthy',
	'detail' => $wp['xmlrpc_enabled'] ? __( 'XML-RPC is enabled — a common brute-force target.', 'wp-server-toolkit' ) : __( 'Disabled.', 'wp-server-toolkit' ),
);

// Outdated plugin/theme/core checks reuse WordPress's own update APIs.
$core_updates = get_core_updates();
$core_current = empty( $core_updates ) || ( isset( $core_updates[0]->response ) && 'latest' === $core_updates[0]->response );
$findings[]   = array(
	'label'  => __( 'WordPress core', 'wp-server-toolkit' ),
	'status' => $core_current ? 'healthy' : 'warning',
	'detail' => $core_current ? __( 'Up to date.', 'wp-server-toolkit' ) : __( 'An update is available.', 'wp-server-toolkit' ),
);

$plugin_updates = get_plugin_updates();
$findings[]     = array(
	'label'  => __( 'Plugins', 'wp-server-toolkit' ),
	'status' => empty( $plugin_updates ) ? 'healthy' : 'warning',
	'detail' => empty( $plugin_updates )
		? __( 'All plugins up to date.', 'wp-server-toolkit' )
		: sprintf( /* translators: %d: plugin count */ __( '%d plugin(s) have updates available.', 'wp-server-toolkit' ), count( $plugin_updates ) ),
);

$theme_updates = get_theme_updates();
$findings[]    = array(
	'label'  => __( 'Themes', 'wp-server-toolkit' ),
	'status' => empty( $theme_updates ) ? 'healthy' : 'warning',
	'detail' => empty( $theme_updates )
		? __( 'All themes up to date.', 'wp-server-toolkit' )
		: sprintf( /* translators: %d: theme count */ __( '%d theme(s) have updates available.', 'wp-server-toolkit' ), count( $theme_updates ) ),
);

// Remote (agent-reported) security metrics, per server.
global $wpdb;
$servers = WPST_Servers::get_all();
?>
<div class="wrap wpst-wrap">
	<h1><?php esc_html_e( 'Security', 'wp-server-toolkit' ); ?></h1>
	<p class="description"><?php esc_html_e( 'These are configuration findings, not a guarantee of security. Review each item and remediate as appropriate for your environment.', 'wp-server-toolkit' ); ?></p>

	<div class="wpst-card wpst-card--wide">
		<h2><?php esc_html_e( 'WordPress', 'wp-server-toolkit' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Check', 'wp-server-toolkit' ); ?></th><th><?php esc_html_e( 'Status', 'wp-server-toolkit' ); ?></th><th><?php esc_html_e( 'Detail', 'wp-server-toolkit' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $findings as $f ) : ?>
					<tr>
						<td><?php echo esc_html( $f['label'] ); ?></td>
						<td><span class="wpst-status wpst-status--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( ucfirst( $f['status'] ) ); ?></span></td>
						<td><?php echo esc_html( $f['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( ! empty( $servers ) ) : ?>
		<div class="wpst-card wpst-card--wide">
			<h2><?php esc_html_e( 'Linux Servers', 'wp-server-toolkit' ); ?></h2>
			<?php foreach ( $servers as $server ) :
				$security = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT payload FROM ' . WPST_Database::table( 'metrics' ) . ' WHERE server_id = %d AND metric_type = %s ORDER BY recorded_at DESC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$server->id,
						'security'
					)
				);
				$data = $security ? json_decode( $security->payload, true ) : null;
				?>
				<h3><?php echo esc_html( $server->name ); ?></h3>
				<?php if ( ! $data ) : ?>
					<p class="wpst-empty"><?php esc_html_e( 'No security data reported yet by the agent.', 'wp-server-toolkit' ); ?></p>
				<?php else : ?>
					<table class="wpst-metric-table">
						<?php foreach ( $data as $key => $value ) : ?>
							<tr>
								<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
								<td><?php echo esc_html( is_bool( $value ) ? ( $value ? __( 'Yes', 'wp-server-toolkit' ) : __( 'No', 'wp-server-toolkit' ) ) : $value ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
