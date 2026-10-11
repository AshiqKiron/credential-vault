<?php
/**
 * Structured key rotation helpers aligned with the Secrets API two-slot model.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Documents and supports safe credential rotation (current + previous slots).
 */
final class Rotation_Workflow {

	/**
	 * Human-readable rotation steps for the admin UI.
	 *
	 * @return list<string>
	 */
	public static function steps(): array {
		return array(
			__( 'Save the new API key in the vault. The Secrets API keeps the previous value in a secondary slot automatically.', 'credvault' ),
			__( 'Verify integrations (checkout, email, AI calls, etc.) against the new key.', 'credvault' ),
			__( 'When everything works, retire the previous slot so only the new key remains.', 'credvault' ),
		);
	}

	/**
	 * Optional automated smoke test after saving a rotated key.
	 *
	 * @param string $secret_name Fully qualified secret name.
	 * @param bool   $network     Network scope.
	 * @return true|\WP_Error True when no test is registered or the test passed.
	 */
	public static function run_post_save_test( string $secret_name, bool $network = false ) {
		/**
		 * Run an integration-specific test after a credential is saved or rotated.
		 *
		 * Return true on success, WP_Error on failure, or omit the filter to skip testing.
		 *
		 * @param true|\WP_Error|null $result      Default null (skip).
		 * @param string              $secret_name Fully qualified secret name.
		 * @param bool                $network     Network scope.
		 */
		$result = apply_filters( 'credential_vault_rotation_test', null, $secret_name, $network );

		if ( null === $result ) {
			return true;
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
