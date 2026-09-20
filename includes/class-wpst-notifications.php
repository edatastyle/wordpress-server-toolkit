<?php
/**
 * Notification dispatch: Email, Telegram, Webhook.
 * Extensible — new channels implement the same two static entry points.
 *
 * Developed by: Saiful Islam (aThemeArt)
 *
 * @package WP_Server_Toolkit_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPST_Notifications {

	public static function notify_opened( $server_id, $event_key, $severity, $message ) {
		$server_name = self::server_label( $server_id );
		$subject     = sprintf( '[WP Server Toolkit] %s: %s', strtoupper( $severity ), $server_name );
		$body        = sprintf( "Server: %s\nProblem: %s\nTime: %s", $server_name, $message, current_time( 'mysql' ) );

		self::email( $subject, $body );
		self::telegram( "🔴 SERVER ALERT\n\n{$server_name}\n\n{$message}\n\nDetected: " . current_time( 'H:i' ) );
		self::webhook(
			array(
				'event'     => $event_key,
				'server'    => $server_name,
				'severity'  => $severity,
				'message'   => $message,
				'timestamp' => current_time( 'mysql', true ),
			)
		);

		self::log( $server_id, $message );
	}

	public static function notify_resolved( $server_id, $event_key, $message, $duration ) {
		$server_name = self::server_label( $server_id );
		$subject     = sprintf( '[WP Server Toolkit] RESOLVED: %s', $server_name );
		$body        = sprintf( "Server: %s\nResolved: %s\nDuration: %s", $server_name, $message, $duration );

		self::email( $subject, $body );
		self::telegram( "✅ RESOLVED\n\n{$server_name}\n\n{$message}\n\nDuration: {$duration}" );
		self::webhook(
			array(
				'event'     => $event_key . '_resolved',
				'server'    => $server_name,
				'duration'  => $duration,
				'timestamp' => current_time( 'mysql', true ),
			)
		);
	}

	private static function email( $subject, $body ) {
		$settings    = get_option( 'wpst_settings', array() );
		$recipients  = $settings['alert_email_recipients'] ?? get_option( 'admin_email' );
		$recipients  = array_filter( array_map( 'trim', explode( ',', $recipients ) ) );

		if ( empty( $recipients ) ) {
			return;
		}

		wp_mail( $recipients, $subject, $body );
	}

	private static function telegram( $text ) {
		$settings = get_option( 'wpst_settings', array() );
		$token    = $settings['telegram_bot_token'] ?? '';
		$chat_id  = $settings['telegram_chat_id'] ?? '';

		if ( empty( $token ) || empty( $chat_id ) ) {
			return;
		}

		wp_remote_post(
			"https://api.telegram.org/bot{$token}/sendMessage",
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id' => $chat_id,
					'text'    => $text,
				),
			)
		);
	}

	private static function webhook( $payload ) {
		$settings = get_option( 'wpst_settings', array() );
		$url      = $settings['webhook_url'] ?? '';

		if ( empty( $url ) ) {
			return;
		}

		$secret  = $settings['webhook_secret'] ?? '';
		$body    = wp_json_encode( $payload );
		$headers = array( 'Content-Type' => 'application/json' );

		if ( $secret ) {
			$headers['X-WPST-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => $body,
			)
		);
	}

	private static function server_label( $server_id ) {
		if ( 0 === (int) $server_id ) {
			return get_bloginfo( 'name' ) . ' (' . __( 'this site', 'wp-server-toolkit' ) . ')';
		}
		$server = WPST_Servers::get( $server_id );
		return $server ? $server->name : sprintf( '#%d', $server_id );
	}

	/**
	 * Internal debug/audit log. Never logs secrets, tokens or passwords.
	 */
	public static function log( $server_id, $message, $type = 'alert', $context = null ) {
		global $wpdb;
		$wpdb->insert(
			WPST_Database::table( 'logs' ),
			array(
				'server_id'  => (int) $server_id,
				'log_type'   => $type,
				'message'    => wp_strip_all_tags( $message ),
				'context'    => $context ? wp_json_encode( $context ) : null,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}
}
