<?php
/**
 * Public developer helpers.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Test_Storage;

if ( ! function_exists( 'credential_vault_available' ) ) {
	/**
	 * Whether encrypted credential storage is available.
	 */
	function credential_vault_available(): bool {
		$available = Secrets_Bridge::is_available();

		/**
		 * Filter availability (test mode may force true).
		 *
		 * @param bool $available Default from Secrets API.
		 */
		return (bool) apply_filters( 'credential_vault_available', $available );
	}
}

if ( ! function_exists( 'credential_vault_get' ) ) {
	/**
	 * Retrieve a vault credential by slug (plaintext). Returns null if missing or unusable.
	 *
	 * @param string $slug    Vault slug (e.g. stripe-live), not the full secret name.
	 * @param bool   $network Use network-scope storage on multisite.
	 */
	function credential_vault_get( string $slug, bool $network = false, array $context = array() ): ?string {
		if ( Test_Storage::enabled() ) {
			$value = Test_Storage::get_value( $slug, $network );
			if ( null === $value ) {
				return null;
			}

			$source = isset( $context['source'] ) ? (string) $context['source'] : 'credential_vault_get_test';
			unset( $context['source'] );

			Audit_Log::log_secret_queried(
				Secrets_Bridge::secret_name( $slug ),
				array_merge(
					array(
						'slug'    => $slug,
						'network' => $network,
						'source'  => $source,
					),
					$context
				)
			);

			return $value;
		}

		/**
		 * Short-circuit get.
		 *
		 * @param null|string $pre     Return non-null string to skip storage.
		 * @param string      $slug    Vault slug.
		 * @param bool        $network Network scope.
		 */
		$pre = apply_filters( 'pre_credential_vault_get', null, $slug, $network );
		if ( is_string( $pre ) ) {
			Audit_Log::log_secret_queried(
				Secrets_Bridge::secret_name( $slug ),
				array_merge(
					array(
						'slug'    => $slug,
						'network' => $network,
						'source'  => 'credential_vault_get',
					),
					$context
				)
			);

			return $pre;
		}

		if ( ! credential_vault_available() ) {
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

		$source = isset( $context['source'] ) ? (string) $context['source'] : 'credential_vault_get';
		unset( $context['source'] );

		Audit_Log::log_secret_queried(
			$name,
			array_merge(
				array(
					'slug'    => $slug,
					'network' => $network,
					'source'  => $source,
				),
				$context
			)
		);

		return $value;
	}
}

if ( ! function_exists( 'credential_vault_set' ) ) {
	/**
	 * Store or rotate a vault credential programmatically.
	 *
	 * @param string               $slug    Vault slug (not the full secret name).
	 * @param string               $value   Plaintext secret value.
	 * @param bool                 $network Network scope on multisite.
	 * @param array<string, mixed> $meta    Optional label, service, notes, environment, rotate_by, owner, runbook_url, public_fields.
	 * @return true|\WP_Error
	 */
	function credential_vault_set( string $slug, string $value, bool $network = false, array $meta = array() ) {
		/**
		 * Short-circuit set (in-memory test mode).
		 *
		 * @param true|\WP_Error|null $pre
		 */
		$pre = apply_filters( 'pre_credential_vault_set', null, $slug, $value, $network, $meta );
		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! credential_vault_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'The WordPress Secrets API is not available.', 'credvault' ) );
		}

		$name   = Secrets_Bridge::secret_name( $slug );
		$result = Secrets_Bridge::set_secret( $name, $value, $network );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$label   = isset( $meta['label'] ) ? (string) $meta['label'] : $slug;
		$service = isset( $meta['service'] ) ? (string) $meta['service'] : 'custom';
		$notes   = isset( $meta['notes'] ) ? (string) $meta['notes'] : '';
		$owner   = isset( $meta['owner'] ) ? (string) $meta['owner'] : '';
		$runbook = isset( $meta['runbook_url'] ) ? (string) $meta['runbook_url'] : '';
		$env     = isset( $meta['environment'] ) ? (string) $meta['environment'] : '';
		$rotate  = isset( $meta['rotate_by'] ) ? (int) $meta['rotate_by'] : 0;
		$public  = isset( $meta['public_fields'] ) && is_array( $meta['public_fields'] ) ? $meta['public_fields'] : array();

		Credential_Meta::save(
			$name,
			$label,
			$service,
			Secrets_Bridge::masked_preview( $value ),
			$notes,
			$env,
			$rotate,
			$owner,
			$runbook,
			$public
		);

		Audit_Log::log_user_action(
			'updated',
			$name,
			array(
				'source'  => 'credential_vault_set',
				'network' => $network,
			)
		);

		Secrets_Bridge::memzero( $value );

		return true;
	}
}

if ( ! function_exists( 'credential_vault_register' ) ) {
	/**
	 * Register default metadata for a slug before a secret value exists (e.g. on plugin activation).
	 *
	 * @param string               $slug Vault slug.
	 * @param array<string, mixed> $meta label, service, notes, owner, runbook_url, environment, public_fields.
	 */
	function credential_vault_register( string $slug, array $meta = array() ): void {
		$name = Secrets_Bridge::secret_name( $slug );

		Credential_Meta::save(
			$name,
			isset( $meta['label'] ) ? (string) $meta['label'] : $slug,
			isset( $meta['service'] ) ? (string) $meta['service'] : 'custom',
			'',
			isset( $meta['notes'] ) ? (string) $meta['notes'] : '',
			isset( $meta['environment'] ) ? (string) $meta['environment'] : '',
			isset( $meta['rotate_by'] ) ? (int) $meta['rotate_by'] : 0,
			isset( $meta['owner'] ) ? (string) $meta['owner'] : '',
			isset( $meta['runbook_url'] ) ? (string) $meta['runbook_url'] : '',
			isset( $meta['public_fields'] ) && is_array( $meta['public_fields'] ) ? $meta['public_fields'] : array()
		);
	}
}
