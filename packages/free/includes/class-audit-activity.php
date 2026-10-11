<?php
/**
 * Per-secret activity rollups from the audit log (metadata only).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Builds last-activity indexes for vault credentials.
 */
final class Audit_Activity {

	/**
	 * @return array<string, array{time: int, action: string, source: string}>
	 */
	public static function index_by_secret(): array {
		$index = array();

		foreach ( Audit_Log::all_entries() as $entry ) {
			$secret = (string) ( $entry['secret'] ?? '' );
			if ( '' === $secret || 0 !== strpos( $secret, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
				continue;
			}

			$time = (int) ( $entry['time'] ?? 0 );
			if ( isset( $index[ $secret ] ) && $index[ $secret ]['time'] >= $time ) {
				continue;
			}

			$index[ $secret ] = array(
				'time'   => $time,
				'action' => (string) ( $entry['action'] ?? '' ),
				'source' => (string) ( $entry['source'] ?? '' ),
			);
		}

		return $index;
	}

	/**
	 * @return array{time: int, action: string, source: string}|null
	 */
	public static function for_secret( string $secret_name ): ?array {
		$index = self::index_by_secret();

		return $index[ $secret_name ] ?? null;
	}
}
