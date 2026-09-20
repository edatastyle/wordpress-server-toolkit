<?php
/**
 * Admin UI: menu registration, scoped asset loading, settings + server
 * actions. Assets only load on WPST screens — never across the whole
 * WordPress admin.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Admin {

	private $screen_ids = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wpst_add_server', array( $this, 'handle_add_server' ) );
		add_action( 'admin_post_wpst_delete_server', array( $this, 'handle_delete_server' ) );
		add_action( 'admin_post_wpst_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wpst_resolve_alert', array( $this, 'handle_resolve_alert' ) );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_credit' ) );
	}

	public function register_menu() {
		$this->screen_ids[] = add_menu_page(
			__( 'WP Server Toolkit', 'wp-server-toolkit' ),
			__( 'Server Toolkit', 'wp-server-toolkit' ),
			'view_wp_server_toolkit',
			'wpst-overview',
			array( $this, 'render_overview' ),
			'dashicons-shield',
			75
		);

		$pages = array(
			'wpst-overview'  => __( 'Overview', 'wp-server-toolkit' ),
			'wpst-servers'   => __( 'Servers', 'wp-server-toolkit' ),
			'wpst-wordpress' => __( 'WordPress', 'wp-server-toolkit' ),
			'wpst-security'  => __( 'Security', 'wp-server-toolkit' ),
			'wpst-alerts'    => __( 'Alerts', 'wp-server-toolkit' ),
			'wpst-settings'  => __( 'Settings', 'wp-server-toolkit' ),
		);

		foreach ( $pages as $slug => $label ) {
			$callback = 'wpst-overview' === $slug ? array( $this, 'render_overview' ) : array( $this, 'render_' . str_replace( 'wpst-', '', $slug ) );
			$cap      = ( 'wpst-settings' === $slug ) ? 'manage_wp_server_toolkit' : 'view_wp_server_toolkit';
			$this->screen_ids[] = add_submenu_page( 'wpst-overview', $label, $label, $cap, $slug, $callback );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'wpst-' ) === false && ! in_array( $hook, $this->screen_ids, true ) ) {
			return;
		}

		wp_enqueue_style( 'wpst-admin', WPST_PLUGIN_URL . 'assets/css/admin.css', array(), WPST_VERSION );
		wp_enqueue_script( 'wpst-admin', WPST_PLUGIN_URL . 'assets/js/admin.js', array(), WPST_VERSION, true );

		$settings = get_option( 'wpst_settings', array() );

		wp_localize_script(
			'wpst-admin',
			'WPST',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'wp-server-toolkit/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'pollingSeconds'  => absint( $settings['polling_interval_seconds'] ?? 60 ),
				'i18n'            => array(
					'online'  => __( 'Online', 'wp-server-toolkit' ),
					'offline' => __( 'Offline', 'wp-server-toolkit' ),
					'warning' => __( 'Warning', 'wp-server-toolkit' ),
				),
			)
		);
	}

	/**
	 * ---------------------------------------------------------------------
	 * Page renders
	 * ---------------------------------------------------------------------
	 */

	public function render_overview() {
		$this->guard( 'view_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/overview.php';
	}

	public function render_servers() {
		$this->guard( 'view_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/servers.php';
	}

	public function render_wordpress() {
		$this->guard( 'view_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/wordpress.php';
	}

	public function render_security() {
		$this->guard( 'view_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/security.php';
	}

	public function render_alerts() {
		$this->guard( 'view_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/alerts.php';
	}

	public function render_settings() {
		$this->guard( 'manage_wp_server_toolkit' );
		include WPST_PLUGIN_DIR . 'admin/views/settings.php';
	}

	private function guard( $cap ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-server-toolkit' ) );
		}
	}

	/**
	 * ---------------------------------------------------------------------
	 * Form handlers (admin-post.php) — every one nonce-checked + capability-checked.
	 * ---------------------------------------------------------------------
	 */

	public function handle_add_server() {
		check_admin_referer( 'wpst_add_server' );
		if ( ! current_user_can( 'manage_wp_server_toolkit_servers' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-server-toolkit' ) );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['server_name'] ?? '' ) );
		$tags = sanitize_text_field( wp_unslash( $_POST['server_tags'] ?? '' ) );

		if ( '' === $name ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'wpst-servers', 'wpst_error' => 'name_required' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$result = WPST_Servers::create_pending_server( $name, $tags );

		WPST_Notifications::log( $result['server_id'], sprintf( 'Admin added server "%s".', $name ), 'audit' );

		set_transient( 'wpst_new_server_' . get_current_user_id(), $result, 15 * MINUTE_IN_SECONDS );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'wpst-servers', 'wpst_new_server' => $result['server_id'] ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_delete_server() {
		check_admin_referer( 'wpst_delete_server' );
		if ( ! current_user_can( 'manage_wp_server_toolkit_servers' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-server-toolkit' ) );
		}

		$id = absint( $_POST['server_id'] ?? 0 );
		if ( $id ) {
			$server = WPST_Servers::get( $id );
			WPST_Servers::delete( $id );
			WPST_Notifications::log( $id, sprintf( 'Admin removed server "%s".', $server->name ?? $id ), 'audit' );
		}

		wp_safe_redirect( add_query_arg( 'page', 'wpst-servers', admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_resolve_alert() {
		check_admin_referer( 'wpst_resolve_alert' );
		if ( ! current_user_can( 'manage_wp_server_toolkit_alerts' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-server-toolkit' ) );
		}

		global $wpdb;
		$id = absint( $_POST['alert_id'] ?? 0 );
		if ( $id ) {
			$wpdb->update(
				WPST_Database::table( 'alerts' ),
				array( 'status' => 'ignored' ),
				array( 'id' => $id )
			);
			WPST_Notifications::log( 0, sprintf( 'Admin ignored alert #%d.', $id ), 'audit' );
		}

		wp_safe_redirect( add_query_arg( 'page', 'wpst-alerts', admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save_settings() {
		check_admin_referer( 'wpst_save_settings' );
		if ( ! current_user_can( 'manage_wp_server_toolkit' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-server-toolkit' ) );
		}

		$settings = array(
			'polling_interval_seconds' => absint( $_POST['polling_interval_seconds'] ?? 60 ),
			'metric_retention_days'    => absint( $_POST['metric_retention_days'] ?? 30 ),
			'error_retention_days'     => absint( $_POST['error_retention_days'] ?? 90 ),
			'alert_email_recipients'   => sanitize_text_field( wp_unslash( $_POST['alert_email_recipients'] ?? '' ) ),
			'telegram_bot_token'       => sanitize_text_field( wp_unslash( $_POST['telegram_bot_token'] ?? '' ) ),
			'telegram_chat_id'         => sanitize_text_field( wp_unslash( $_POST['telegram_chat_id'] ?? '' ) ),
			'webhook_url'              => esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ),
			'webhook_secret'           => sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ),
			'delete_data_on_uninstall' => isset( $_POST['delete_data_on_uninstall'] ),
			'thresholds'               => array(
				'cpu_percent'        => absint( $_POST['threshold_cpu_percent'] ?? 90 ),
				'cpu_minutes'        => absint( $_POST['threshold_cpu_minutes'] ?? 5 ),
				'ram_percent'        => absint( $_POST['threshold_ram_percent'] ?? 90 ),
				'disk_percent'       => absint( $_POST['threshold_disk_percent'] ?? 85 ),
				'ssl_days_remaining' => absint( $_POST['threshold_ssl_days'] ?? 30 ),
				'offline_seconds'    => absint( $_POST['threshold_offline_seconds'] ?? 180 ),
			),
		);

		update_option( 'wpst_settings', $settings );
		WPST_Notifications::log( 0, 'Admin updated settings.', 'audit' );

		wp_safe_redirect( add_query_arg( array( 'page' => 'wpst-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function admin_footer_credit( $text ) {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'wpst-' ) !== false ) {
			return esc_html( WPST_DEVELOPER_CREDIT ) . ' &middot; WP Server Toolkit Pro v' . esc_html( WPST_VERSION );
		}
		return $text;
	}
}
