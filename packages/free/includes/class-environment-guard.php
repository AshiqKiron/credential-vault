<?php
/**
 * Environment metadata vs wp_get_environment_type() checks.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Warns when credential environment labels disagree with the site environment.
 */
final class Environment_Guard {

	/**
	 * @return list<array{secret: string, meta_env: string, site_env: string, message: string}>
	 */
	public static function find_mismatches(): array {
		if ( ! Secrets_Bridge::is_available() ) {
			return array();
		}

		$site_env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$issues   = array();

		$rows = Secrets_Bridge::list_vault_secrets( false );
		if ( Secrets_Bridge::supports_network() ) {
			$rows = array_merge( $rows, Secrets_Bridge::list_vault_secrets( true ) );
		}

		foreach ( $rows as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			if ( null === $meta ) {
				continue;
			}

			$meta_env = (string) ( $meta['environment'] ?? '' );
			if ( '' === $meta_env ) {
				continue;
			}

			$problem = self::is_mismatch( $meta_env, $site_env );
			if ( null === $problem ) {
				continue;
			}

			$issues[] = array(
				'secret'    => $name,
				'meta_env'  => $meta_env,
				'site_env'  => $site_env,
				'message'   => $problem,
			);
		}

		return $issues;
	}

	/**
	 * @return string|null Human-readable mismatch reason.
	 */
	public static function mismatch_for_secret( string $secret_name ): ?string {
		foreach ( self::find_mismatches() as $row ) {
			if ( $row['secret'] === $secret_name ) {
				return $row['message'];
			}
		}

		return null;
	}

	private static function is_mismatch( string $meta_env, string $site_env ): ?string {
		$production_meta = in_array( $meta_env, array( 'production', 'live' ), true );
		$non_prod_site   = in_array( $site_env, array( 'local', 'development', 'staging' ), true );
		$prod_site       = 'production' === $site_env;

		if ( $production_meta && $non_prod_site ) {
			return __( 'Credential is tagged production but this site is non-production.', 'credvault' );
		}

		if ( in_array( $meta_env, array( 'local', 'development', 'staging' ), true ) && $prod_site ) {
			return __( 'Credential is tagged non-production but this site is production.', 'credvault' );
		}

		return null;
	}
}
