<?php
/**
 * Public developer helpers.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

use CredentialVault\Secrets_Bridge;

if ( ! function_exists( 'credential_vault_available' ) ) {
	/**
	 * Whether encrypted credential storage is available.
	 */
	function credential_vault_available(): bool {
		return Secrets_Bridge::is_available();
	}
}

if ( ! function_exists( 'credential_vault_get' ) ) {
	/**
	 * Retrieve a vault credential by slug (plaintext). Returns null if missing or unusable.
	 *
	 * @param string $slug    Vault slug (e.g. stripe-live), not the full secret name.
	 * @param bool   $network Use network-scope storage on multisite.
	 */
	function credential_vault_get( string $slug, bool $network = false ): ?string {
		if ( ! Secrets_Bridge::is_available() ) {
			return null;
		}

		$name   = Secrets_Bridge::secret_name( $slug );
		$secret = $network
			? Secrets_Bridge::get_network_secret( $name )
			: Secrets_Bridge::get_secret( $name );

		if ( is_wp_error( $secret ) || null === $secret ) {
			return null;
		}

		$value = $secret->reveal();

		if ( is_wp_error( $value ) ) {
			return null;
		}

		return $value;
	}
}
