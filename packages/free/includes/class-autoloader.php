<?php
/**
 * PSR-4-style autoloader for plugin classes.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Maps CredentialVault\* classes to includes/, admin/, and public/ directories.
 */
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

		$candidates = array(
			CREDENTIAL_VAULT_DIR . 'includes/class-' . $relative . '.php',
			CREDENTIAL_VAULT_DIR . 'admin/class-' . $relative . '.php',
			CREDENTIAL_VAULT_DIR . 'public/class-' . $relative . '.php',
		);

		if ( preg_match( '#^admin/(.+)$#', $relative, $matches ) ) {
			array_unshift(
				$candidates,
				CREDENTIAL_VAULT_DIR . 'admin/class-admin-' . $matches[1] . '.php',
				CREDENTIAL_VAULT_DIR . 'admin/class-' . $matches[1] . '.php'
			);
		}

		if ( preg_match( '#^public/(.+)$#', $relative, $matches ) ) {
			array_unshift(
				$candidates,
				CREDENTIAL_VAULT_DIR . 'public/class-public-' . $matches[1] . '.php'
			);
		}

		foreach ( $candidates as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
