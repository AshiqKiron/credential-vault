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

	/**
	 * Plain-language label for an audit action slug.
	 */
	public static function action_label( string $action ): string {
		$labels = array(
			'created'              => __( 'Created', 'credvault' ),
			'updated'              => __( 'Updated', 'credvault' ),
			'deleted'              => __( 'Deleted', 'credvault' ),
			'imported'             => __( 'Imported', 'credvault' ),
			'retired'              => __( 'Previous key retired', 'credvault' ),
			'accessed'             => __( 'Viewed', 'credvault' ),
			'copied'               => __( 'Copied', 'credvault' ),
			'queried'              => __( 'Read by code', 'credvault' ),
			'rotated'              => __( 'Rotated', 'credvault' ),
			'bundle_exported'      => __( 'Exported', 'credvault' ),
			'bundle_imported'      => __( 'Imported from bundle', 'credvault' ),
			'meta_updated'         => __( 'Details saved', 'credvault' ),
			'rollback'             => __( 'Rolled back', 'credvault' ),
			'sudo_started'         => __( 'Vault unlocked', 'credvault' ),
			'sudo_ended'           => __( 'Vault locked', 'credvault' ),
			'jit_access_requested' => __( 'Access requested', 'credvault' ),
			'jit_access_approved'  => __( 'Access approved', 'credvault' ),
			'change_pending'       => __( 'Change pending', 'credvault' ),
			'change_approved'      => __( 'Change approved', 'credvault' ),
			'compromise_playbook'  => __( 'Compromise playbook', 'credvault' ),
		);

		if ( isset( $labels[ $action ] ) ) {
			return $labels[ $action ];
		}

		if ( '' === $action ) {
			return '';
		}

		return ucfirst( str_replace( '_', ' ', $action ) );
	}
}
