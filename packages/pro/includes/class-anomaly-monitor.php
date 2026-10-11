<?php
/**
 * Anomaly detection for vault access patterns (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Alerts when reveal/query volume spikes from one IP or user.
 */
final class Anomaly_Monitor {

	private const WINDOW = 900;

	private const THRESHOLD = 25;

	public static function register(): void {
		add_action( 'credential_vault_audit_entry', array( self::class, 'track' ), 20, 1 );
	}

	/**
	 * @param array<string, mixed> $entry
	 */
	public static function track( array $entry ): void {
		$action = sanitize_key( (string) ( $entry['action'] ?? '' ) );
		if ( ! in_array( $action, array( 'queried', 'copied', 'accessed' ), true ) ) {
			return;
		}

		$ip = self::client_ip();
		$key = 'credential_vault_pro_anomaly_' . md5( $ip . '|' . (int) ( $entry['user_id'] ?? 0 ) );

		$count = (int) get_transient( $key );
		++$count;
		set_transient( $key, $count, self::WINDOW );

		if ( $count < self::THRESHOLD ) {
			return;
		}

		delete_transient( $key );

		Webhooks::dispatch_event(
			'security.anomaly',
			array(
				'ip'      => $ip,
				'user_id' => (int) ( $entry['user_id'] ?? 0 ),
				'count'   => $count,
				'window'  => self::WINDOW,
				'action'  => $action,
			)
		);
	}

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		return sanitize_text_field( $ip );
	}
}
