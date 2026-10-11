<?php
/**
 * Webhook delivery log and event IDs (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Stores recent webhook attempts for troubleshooting.
 */
final class Webhook_Delivery_Log {

	private const OPTION = 'credential_vault_pro_webhook_deliveries';

	private const MAX = 100;

	public static function register(): void {
		// Wired from Webhooks::dispatch().
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function record( string $url, string $event_id, array $payload, int $status_code, bool $success ): void {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		array_unshift(
			$stored,
			array(
				'time'        => time(),
				'url'         => esc_url_raw( $url ),
				'event_id'    => sanitize_text_field( $event_id ),
				'event'       => sanitize_text_field( (string) ( $payload['event'] ?? '' ) ),
				'status_code' => $status_code,
				'success'     => $success,
			)
		);

		if ( count( $stored ) > self::MAX ) {
			$stored = array_slice( $stored, 0, self::MAX );
		}

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function recent( int $limit = 25 ): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_slice( $stored, 0, max( 1, min( self::MAX, $limit ) ) );
	}

	public static function seen_event_id( string $event_id ): bool {
		$event_id = sanitize_text_field( $event_id );
		if ( '' === $event_id ) {
			return false;
		}

		$key = 'credvault_wh_evt_' . md5( $event_id );
		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, 1, DAY_IN_SECONDS );

		return false;
	}
}
