<?php
/**
 * Autoloader for Credential Vault Pro.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

final class Autoloader {

	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	public static function autoload( string $class ): void {
		if ( strpos( $class, __NAMESPACE__ . '\\' ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
		$relative = str_replace( '\\', '/', $relative );
		$relative = strtolower( str_replace( '_', '-', $relative ) );

		$path = CREDENTIAL_VAULT_PRO_DIR . 'includes/class-' . $relative . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
