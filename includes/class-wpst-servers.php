<?php
/**
 * Server registration and lifecycle.
 *
 * Every server gets its own high-entropy token. Only a hash of the
 * permanent auth token is ever stored — the plaintext is shown to the
 * admin exactly once, at generation time, the same way an API key would be.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Servers {

	/**
	 * Create a pending server record with a short-lived registration token.
	 * The registration token is exchanged for a permanent auth token the
	 * first time the agent calls /register — it is never used for ongoing
	 * authentication.
	 *
	 * @return array{server_id:int, registration_token:string, expires:string}
	 */
	public static function create_pending_server( $name, $tags = '' ) {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );

		$registration_token = self::generate_token( 32 );
		$expires             = gmdate( 'Y-m-d H:i:s', time() + 15 * MINUTE_IN_SECONDS );

		$wpdb->insert(
			$table,
			array(
				'name'                 => sanitize_text_field( $name ),
				'tags'                 => sanitize_text_field( $tags ),
				'status'               => 'pending',
				'registration_token'   => $registration_token,
				'registration_expires' => $expires,
				'created_at'           => current_time( 'mysql', true ),
			)
		);

		return array(
			'server_id'          => (int) $wpdb->insert_id,
			'registration_token' => $registration_token,
			'expires'            => $expires,
		);
	}

	/**
	 * Exchange a valid, unexpired registration token for a permanent auth
	 * token. Called once by the agent during installation.
	 *
	 * @return array|WP_Error
	 */
	public static function complete_registration( $registration_token, $hostname, $agent_id, $agent_version ) {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );

		$server = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE registration_token = %s AND status = 'pending' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$registration_token
			)
		);

		if ( ! $server ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or already-used registration token.', 'wp-server-toolkit' ), array( 'status' => 403 ) );
		}

		if ( strtotime( $server->registration_expires . ' UTC' ) < time() ) {
			return new WP_Error( 'expired_token', __( 'Registration token has expired.', 'wp-server-toolkit' ), array( 'status' => 403 ) );
		}

		$auth_token = self::generate_token( 48 );

		$wpdb->update(
			$table,
			array(
				'hostname'             => sanitize_text_field( $hostname ),
				'agent_id'             => sanitize_text_field( $agent_id ),
				'agent_version'        => sanitize_text_field( $agent_version ),
				'auth_token_hash'      => self::hash_token( $auth_token ),
				'registration_token'   => '',
				'registration_expires' => null,
				'status'               => 'online',
				'last_seen'            => current_time( 'mysql', true ),
			),
			array( 'id' => $server->id )
		);

		return array(
			'server_id'  => (int) $server->id,
			'auth_token' => $auth_token, // Shown to the agent exactly once; never stored in plaintext.
		);
	}

	/**
	 * Authenticate an incoming agent request by comparing the hashed
	 * bearer token. Returns the server row on success.
	 */
	public static function authenticate( $server_id, $auth_token ) {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );

		$server = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $server_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $server || empty( $server->auth_token_hash ) ) {
			return false;
		}

		if ( ! hash_equals( $server->auth_token_hash, self::hash_token( $auth_token ) ) ) {
			return false;
		}

		return $server;
	}

	/**
	 * Record a heartbeat / successful metrics submission.
	 */
	public static function touch_last_seen( $server_id, $agent_version = '' ) {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );

		$data = array(
			'last_seen' => current_time( 'mysql', true ),
			'status'    => 'online',
		);
		if ( $agent_version ) {
			$data['agent_version'] = sanitize_text_field( $agent_version );
		}

		$wpdb->update( $table, $data, array( 'id' => (int) $server_id ) );
	}

	/**
	 * Flip any server whose heartbeat is older than the configured
	 * offline threshold to "offline", so the dashboard never shows stale
	 * data as if it were current.
	 */
	public static function mark_offline_servers() {
		global $wpdb;
		$settings         = get_option( 'wpst_settings', array() );
		$offline_seconds  = absint( $settings['thresholds']['offline_seconds'] ?? 180 );
		$table            = WPST_Database::table( 'servers' );
		$cutoff           = gmdate( 'Y-m-d H:i:s', time() - $offline_seconds );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'offline' WHERE status = 'online' AND last_seen < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}

	public static function get_all() {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get( $id ) {
		global $wpdb;
		$table = WPST_Database::table( 'servers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( WPST_Database::table( 'servers' ), array( 'id' => $id ) );
		$wpdb->delete( WPST_Database::table( 'metrics' ), array( 'server_id' => $id ) );
		$wpdb->delete( WPST_Database::table( 'alerts' ), array( 'server_id' => $id ) );
	}

	private static function generate_token( $bytes ) {
		return bin2hex( random_bytes( $bytes ) );
	}

	public static function hash_token( $token ) {
		// A pepper stored outside the database (wp-config constant, optional)
		// hardens this beyond a plain hash if the DB is ever exposed alone.
		$pepper = defined( 'WPST_TOKEN_PEPPER' ) ? WPST_TOKEN_PEPPER : '';
		return hash( 'sha256', $pepper . $token );
	}
}
