<?php
/**
 * REST API.
 *
 * SECURITY NOTE (see requirement #39/#91 of the spec this plugin was
 * built from): this API intentionally exposes only narrow, explicitly
 * defined monitoring endpoints (/system, /memory, /disk, /php-fpm,
 * /nginx, /mysql, /security, /register, /heartbeat). There is, and must
 * never be, a generic "/execute" endpoint that runs arbitrary shell
 * commands from the WordPress dashboard. Any future service-control
 * feature (e.g. "Restart Nginx") must be its own explicit, authorized
 * action — never free-form command input.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_REST_API {

	const NAMESPACE_AGENT     = 'wp-server-toolkit/v1';
	const NAMESPACE_DASHBOARD = 'wp-server-toolkit/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {

		/**
		 * ---------------------------------------------------------------
		 * Agent-facing endpoints (authenticated via server bearer token).
		 * ---------------------------------------------------------------
		 */
		register_rest_route(
			self::NAMESPACE_AGENT,
			'/agent/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'agent_register' ),
				'permission_callback' => array( $this, 'rate_limit_only' ),
				'args'                => array(
					'registration_token' => array( 'required' => true, 'type' => 'string' ),
					'hostname'           => array( 'required' => true, 'type' => 'string' ),
					'agent_id'           => array( 'required' => true, 'type' => 'string' ),
					'agent_version'      => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_AGENT,
			'/agent/heartbeat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'agent_heartbeat' ),
				'permission_callback' => array( $this, 'authenticate_agent' ),
			)
		);

		// One explicit endpoint per monitoring domain — never a catch-all.
		foreach ( array( 'system', 'memory', 'disk', 'network', 'processes', 'php', 'php-fpm', 'nginx', 'mysql', 'security' ) as $domain ) {
			register_rest_route(
				self::NAMESPACE_AGENT,
				'/agent/metrics/' . $domain,
				array(
					'methods'             => 'POST',
					'callback'            => function ( WP_REST_Request $request ) use ( $domain ) {
						return $this->agent_submit_metric( $request, str_replace( '-', '_', $domain ) );
					},
					'permission_callback' => array( $this, 'authenticate_agent' ),
				)
			);
		}

		/**
		 * ---------------------------------------------------------------
		 * Dashboard-facing endpoints (authenticated as a logged-in WP user
		 * with plugin capabilities, protected by the REST nonce).
		 * ---------------------------------------------------------------
		 */
		register_rest_route(
			self::NAMESPACE_DASHBOARD,
			'/dashboard/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'dashboard_overview' ),
				'permission_callback' => array( $this, 'require_view_capability' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_DASHBOARD,
			'/dashboard/servers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'dashboard_server_detail' ),
				'permission_callback' => array( $this, 'require_view_capability' ),
				'args'                => array( 'id' => array( 'required' => true, 'type' => 'integer' ) ),
			)
		);
	}

	/**
	 * ---------------------------------------------------------------------
	 * Permission callbacks
	 * ---------------------------------------------------------------------
	 */

	public function require_view_capability( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'wp-server-toolkit' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'view_wp_server_toolkit' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this data.', 'wp-server-toolkit' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Registration only requires a valid, unexpired, single-use token —
	 * plus a basic rate limit to slow brute-force guessing.
	 */
	public function rate_limit_only( WP_REST_Request $request ) {
		return $this->check_rate_limit( 'register_' . $this->client_ip(), 10, MINUTE_IN_SECONDS );
	}

	/**
	 * Bearer auth: "Authorization: Bearer <server_id>.<auth_token>"
	 */
	public function authenticate_agent( WP_REST_Request $request ) {
		if ( ! $this->check_rate_limit( 'agent_' . $this->client_ip(), 120, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'rate_limited', __( 'Too many requests.', 'wp-server-toolkit' ), array( 'status' => 429 ) );
		}

		$auth_header = $request->get_header( 'authorization' );
		if ( ! $auth_header || stripos( $auth_header, 'Bearer ' ) !== 0 ) {
			return new WP_Error( 'missing_auth', __( 'Missing bearer token.', 'wp-server-toolkit' ), array( 'status' => 401 ) );
		}

		$token = substr( $auth_header, 7 );
		if ( strpos( $token, '.' ) === false ) {
			return new WP_Error( 'malformed_token', __( 'Malformed token.', 'wp-server-toolkit' ), array( 'status' => 401 ) );
		}

		list( $server_id, $auth_token ) = explode( '.', $token, 2 );
		$server_id = absint( $server_id );

		$server = WPST_Servers::authenticate( $server_id, $auth_token );
		if ( ! $server ) {
			// Do not reveal whether the server_id exists.
			return new WP_Error( 'invalid_token', __( 'Invalid credentials.', 'wp-server-toolkit' ), array( 'status' => 403 ) );
		}

		// Basic replay protection: request must be within a 5-minute window.
		$timestamp = (int) $request->get_header( 'x-wpst-timestamp' );
		if ( $timestamp && abs( time() - $timestamp ) > 300 ) {
			return new WP_Error( 'stale_request', __( 'Request timestamp outside allowed window.', 'wp-server-toolkit' ), array( 'status' => 401 ) );
		}

		$request->set_param( '_wpst_server', $server );
		return true;
	}

	/**
	 * ---------------------------------------------------------------------
	 * Agent callbacks
	 * ---------------------------------------------------------------------
	 */

	public function agent_register( WP_REST_Request $request ) {
		$result = WPST_Servers::complete_registration(
			sanitize_text_field( $request->get_param( 'registration_token' ) ),
			sanitize_text_field( $request->get_param( 'hostname' ) ),
			sanitize_text_field( $request->get_param( 'agent_id' ) ),
			sanitize_text_field( $request->get_param( 'agent_version' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'server_id'  => $result['server_id'],
				// Combined bearer credential the agent stores locally, e.g. in
				// /etc/wp-server-toolkit/agent.conf with mode 600.
				'bearer'     => $result['server_id'] . '.' . $result['auth_token'],
			),
			201
		);
	}

	public function agent_heartbeat( WP_REST_Request $request ) {
		$server = $request->get_param( '_wpst_server' );
		WPST_Servers::touch_last_seen( $server->id, sanitize_text_field( $request->get_param( 'agent_version' ) ) );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function agent_submit_metric( WP_REST_Request $request, $metric_type ) {
		$server = $request->get_param( '_wpst_server' );
		$body   = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_payload', __( 'Expected a JSON object.', 'wp-server-toolkit' ), array( 'status' => 400 ) );
		}

		// Strip anything that isn't a plain scalar/array value — defence in
		// depth against unexpected payload shapes.
		$clean = $this->sanitize_metric_payload( $body );

		global $wpdb;
		$wpdb->insert(
			WPST_Database::table( 'metrics' ),
			array(
				'server_id'   => $server->id,
				'metric_type' => $metric_type,
				'resolution'  => 'raw',
				'payload'     => wp_json_encode( $clean ),
				'recorded_at' => current_time( 'mysql', true ),
			)
		);

		WPST_Servers::touch_last_seen( $server->id );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function sanitize_metric_payload( $data, $depth = 0 ) {
		if ( $depth > 4 ) {
			return null;
		}
		$clean = array();
		foreach ( $data as $key => $value ) {
			$key = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $key );
			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_metric_payload( $value, $depth + 1 );
			} elseif ( is_bool( $value ) || is_numeric( $value ) ) {
				$clean[ $key ] = $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $clean;
	}

	/**
	 * ---------------------------------------------------------------------
	 * Dashboard callbacks
	 * ---------------------------------------------------------------------
	 */

	public function dashboard_overview( WP_REST_Request $request ) {
		$servers = WPST_Servers::get_all();
		$out     = array();
		foreach ( $servers as $server ) {
			$out[] = array(
				'id'            => (int) $server->id,
				'name'          => $server->name,
				'status'        => $server->status,
				'last_seen'     => $server->last_seen,
				'agent_version' => $server->agent_version,
			);
		}

		return new WP_REST_Response(
			array(
				'servers' => $out,
				'local'   => array(
					'wordpress' => WPST_Monitor_Local::wordpress_health(),
					'php'       => WPST_Monitor_Local::php_health(),
					'cron'      => WPST_Monitor_Local::cron_health(),
					'ssl'       => WPST_Monitor_Local::ssl_health(),
				),
				'open_alerts' => count( WPST_Alerts::get_history( null, 'open', 50 ) ),
			),
			200
		);
	}

	public function dashboard_server_detail( WP_REST_Request $request ) {
		$server = WPST_Servers::get( (int) $request->get_param( 'id' ) );
		if ( ! $server ) {
			return new WP_Error( 'not_found', __( 'Server not found.', 'wp-server-toolkit' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$table = WPST_Database::table( 'metrics' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_type, payload, recorded_at FROM {$table} WHERE server_id = %d ORDER BY recorded_at DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$server->id
			)
		);

		$latest = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $latest[ $row->metric_type ] ) ) {
				$latest[ $row->metric_type ] = json_decode( $row->payload, true );
			}
		}

		return new WP_REST_Response(
			array(
				'server'  => $server,
				'metrics' => $latest,
			),
			200
		);
	}

	/**
	 * ---------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------
	 */

	private function check_rate_limit( $key, $max_requests, $window_seconds ) {
		$transient_key = 'wpst_rl_' . md5( $key );
		$count         = (int) get_transient( $transient_key );

		if ( $count >= $max_requests ) {
			return false;
		}

		set_transient( $transient_key, $count + 1, $window_seconds );
		return true;
	}

	private function client_ip() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return preg_replace( '/[^a-fA-F0-9.:]/', '', $ip );
	}
}
