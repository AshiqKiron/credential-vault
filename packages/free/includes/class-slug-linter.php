<?php
/**
 * Vault slug naming rules and manifest alignment.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

use WP_Error;

/**
 * Validates slug patterns before writes.
 */
final class Slug_Linter {

	public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

	public static function register(): void {
		add_filter( 'credential_vault_before_set_secret', array( self::class, 'block_invalid_slug' ), 5, 3 );
	}

	/**
	 * @return array{valid: bool, warnings: list<string>}
	 */
	public static function validate( string $slug ): array {
		$slug     = sanitize_key( $slug );
		$warnings = array();

		if ( '' === $slug ) {
			return array(
				'valid'    => false,
				'warnings' => array( __( 'Slug is empty.', 'credvault' ) ),
			);
		}

		if ( ! preg_match( self::PATTERN, $slug ) ) {
			$warnings[] = __( 'Slug should use lowercase letters, digits, and hyphens only.', 'credvault' );
		}

		if ( strlen( $slug ) > 64 ) {
			$warnings[] = __( 'Slug is longer than 64 characters.', 'credvault' );
		}

		$known = self::manifest_slugs();
		if ( ! empty( $known ) && ! in_array( $slug, $known, true ) ) {
			$warnings[] = __( 'Slug is not listed in the active manifest (if configured).', 'credvault' );
		}

		/**
		 * @param list<string> $warnings
		 * @param string       $slug
		 */
		$warnings = apply_filters( 'credential_vault_slug_lint_warnings', $warnings, $slug );

		return array(
			'valid'    => empty( $warnings ) || ! self::strict_mode(),
			'warnings' => $warnings,
		);
	}

	public static function strict_mode(): bool {
		return (bool) apply_filters( 'credential_vault_slug_lint_strict', false );
	}

	/**
	 * @return list<string>
	 */
	public static function manifest_slugs(): array {
		$path = apply_filters( 'credential_vault_manifest_path', '' );
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return array();
		}

		$parsed = Secrets_Manifest::parse_file( $path );
		if ( is_wp_error( $parsed ) || ! is_array( $parsed ) ) {
			return array();
		}

		$slugs = array();
		foreach ( Secrets_Manifest::normalize_entries( $parsed ) as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * @param true|WP_Error $gate
	 * @return true|WP_Error
	 */
	public static function block_invalid_slug( $gate, string $name, bool $network ) {
		unset( $network );
		if ( is_wp_error( $gate ) || true !== $gate ) {
			return $gate;
		}

		$slug = basename( $name );
		$lint = self::validate( $slug );
		if ( $lint['valid'] ) {
			return true;
		}

		if ( ! self::strict_mode() ) {
			return true;
		}

		return new WP_Error(
			'credential_vault_invalid_slug',
			implode( ' ', $lint['warnings'] )
		);
	}
}
