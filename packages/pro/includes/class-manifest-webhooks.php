<?php
/**
 * Manifest validation failure notifications (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Dispatches webhooks when CI manifest validation fails.
 */
final class Manifest_Webhooks {

	public static function register(): void {
		add_action( 'credential_vault_manifest_validation_failed', array( self::class, 'on_failed' ), 10, 2 );
	}

	/**
	 * @param array<string, mixed> $result Validation result from Secrets_Manifest::validate().
	 */
	public static function on_failed( array $result, string $path ): void {
		Webhooks::dispatch_event(
			'vault.validation_failed',
			array(
				'manifest' => $path,
				'errors'   => $result['errors'] ?? array(),
				'missing'  => $result['missing'] ?? array(),
				'overdue'  => $result['overdue'] ?? array(),
			)
		);
	}
}
