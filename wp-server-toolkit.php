<?php
/**
 * Plugin Name:       WP Server Toolkit
 * Plugin URI:        https://athemeart.com/wp-server-toolkit-pro
 * Description:       Monitor WordPress, Linux, Nginx, PHP-FPM, MySQL/MariaDB, SSL, uptime, logs and server health from the WordPress dashboard.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Saiful Islam (aThemeArt)
 * Author URI:        https://athemeart.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wp-server-toolkit
 * Domain Path:       /languages
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ---------------------------------------------------------------------
 * Core constants
 * ---------------------------------------------------------------------
 */
define( 'WPST_VERSION', '1.0.0' );
define( 'WPST_DB_VERSION', '1.0.0' );
define( 'WPST_PLUGIN_FILE', __FILE__ );
define( 'WPST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPST_TEXT_DOMAIN', 'wp-server-toolkit' );

// Developer credit — used in admin footer, REST responses (debug only) and readme.
define( 'WPST_DEVELOPER_NAME', 'Saiful Islam' );
define( 'WPST_DEVELOPER_COMPANY', 'aThemeArt' );
define( 'WPST_DEVELOPER_CREDIT', 'Developed by: Saiful Islam (aThemeArt)' );

/**
 * ---------------------------------------------------------------------
 * Autoload / require core classes
 * ---------------------------------------------------------------------
 * Simple explicit requires are used instead of a PSR-4 autoloader to
 * keep the plugin dependency-free and easy to audit.
 */
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-capabilities.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-database.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-activator.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-servers.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-monitor-local.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-alerts.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-notifications.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-rest-api.php';
require_once WPST_PLUGIN_DIR . 'includes/class-wpst-cron.php';

if ( is_admin() ) {
	require_once WPST_PLUGIN_DIR . 'includes/class-wpst-admin.php';
}

/**
 * ---------------------------------------------------------------------
 * Activation / deactivation / uninstall hooks
 * ---------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'WPST_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPST_Activator', 'deactivate' ) );

/**
 * ---------------------------------------------------------------------
 * Bootstrap
 * ---------------------------------------------------------------------
 */
function wpst_run_plugin() {

	// Load translations.
	load_plugin_textdomain( WPST_TEXT_DOMAIN, false, dirname( WPST_PLUGIN_BASENAME ) . '/languages' );

	// Register custom capabilities on every load (cheap, idempotent).
	WPST_Capabilities::register();

	// REST API (agent + dashboard endpoints).
	new WPST_REST_API();

	// Background jobs: heartbeat offline checks, aggregation, retention cleanup.
	new WPST_Cron();

	// Admin UI.
	if ( is_admin() ) {
		new WPST_Admin();
	}
}
add_action( 'plugins_loaded', 'wpst_run_plugin' );
