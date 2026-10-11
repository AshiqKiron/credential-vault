<?php
/**
 * Custom capabilities and break-glass read-only access.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Delegates vault management without full admin when caps are assigned.
 */
final class Vault_Capabilities {

	public const VIEW_CAP = 'view_credential_vault';

	public const AUDITOR_ROLE = 'credvault_auditor';

	public static function register( Loader $loader ): void {
		$loader->add_action( 'init', self::class, 'ensure_auditor_role' );
	}

	public static function ensure_auditor_role(): void {
		if ( get_role( self::AUDITOR_ROLE ) instanceof \WP_Role ) {
			return;
		}

		add_role(
			self::AUDITOR_ROLE,
			__( 'Credential Vault Auditor', 'credvault' ),
			array(
				'read'                   => true,
				self::VIEW_CAP           => true,
			)
		);
	}

	public static function grant_caps_on_activate(): void {
		self::ensure_auditor_role();

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			if ( ! $admin->has_cap( self::VIEW_CAP ) ) {
				$admin->add_cap( self::VIEW_CAP );
			}
		}
	}
}
