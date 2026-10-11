<?php
/**
 * Per-secret audit timeline (metadata only).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Filters audit entries for lifecycle visualization.
 */
final class Secret_Timeline {

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function for_secret( string $secret_name, int $limit = 50 ): array {
		$entries = Audit_Log::entries(
			$limit,
			array(
				'secret' => $secret_name,
			)
		);

		$timeline = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$timeline[] = array(
				'time'   => (int) ( $entry['time'] ?? 0 ),
				'action' => (string) ( $entry['action'] ?? '' ),
				'user'   => (int) ( $entry['user_id'] ?? 0 ),
				'meta'   => is_array( $entry['meta'] ?? null ) ? $entry['meta'] : array(),
			);
		}

		return $timeline;
	}
}
