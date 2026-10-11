<?php
/**
 * Encrypted vault bundle export/import for site migration.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Password-protected bundles (AES-256-GCM). Never writes plaintext to disk from CLI without --reveal.
 */
final class Vault_Bundle {

	private const BUNDLE_VERSION = 1;

	private const CIPHER = 'aes-256-gcm';

	/**
	 * @param array<string, mixed> $options include_values (bool), network (bool).
	 * @return string|\WP_Error Base64-encoded bundle.
	 */
	public static function export( string $password, array $options = array() ) {
		if ( ! Secrets_Bridge::is_available() ) {
			return new \WP_Error( 'credvault_bundle', __( 'Secrets API is not available.', 'credvault' ) );
		}

		if ( '' === $password ) {
			return new \WP_Error( 'credvault_bundle', __( 'Export password is required.', 'credvault' ) );
		}

		$include_values = ! empty( $options['include_values'] );
		$network        = ! empty( $options['network'] );

		$payload = array(
			'version'     => self::BUNDLE_VERSION,
			'exported_at' => gmdate( 'c' ),
			'network'     => $network,
			'secrets'     => array(),
		);

		foreach ( Secrets_Bridge::list_vault_secrets( $network ) as $row ) {
			$name = (string) $row['name'];
			$slug = basename( $name );
			$meta = Credential_Meta::get( $name );

			$item = array(
				'slug'  => $slug,
				'meta'  => array(
					'label'         => $meta['label'] ?? $slug,
					'service'       => $meta['service'] ?? 'custom',
					'notes'         => $meta['notes'] ?? '',
					'owner'         => $meta['owner'] ?? '',
					'runbook_url'   => $meta['runbook_url'] ?? '',
					'environment'   => $meta['environment'] ?? '',
					'rotate_by'     => (int) ( $meta['rotate_by'] ?? 0 ),
					'public_fields' => $meta['public_fields'] ?? array(),
				),
				'flags' => array(
					'has_previous'   => ! empty( $row['has_previous'] ),
					'needs_rotation' => ! empty( $row['needs_rotation'] ),
				),
			);

			if ( $include_values ) {
				$secret = $network
					? Secrets_Bridge::get_network_secret( $name )
					: Secrets_Bridge::get_secret( $name );
				if ( is_wp_error( $secret ) || null === $secret ) {
					continue;
				}
				$value = $secret->reveal();
				if ( is_wp_error( $value ) || ! is_string( $value ) ) {
					continue;
				}
				$item['value'] = $value;
				Secrets_Bridge::memzero( $value );
			}

			$payload['secrets'][] = $item;
		}

		return self::seal( $payload, $password );
	}

	/**
	 * @return array{imported: int, skipped: int}|\WP_Error
	 */
	public static function import( string $bundle_b64, string $password, array $options = array() ) {
		if ( ! Secrets_Bridge::is_available() ) {
			return new \WP_Error( 'credvault_bundle', __( 'Secrets API is not available.', 'credvault' ) );
		}

		if ( function_exists( 'wp_secrets_provider_is_writable' ) && ! wp_secrets_provider_is_writable() ) {
			return new \WP_Error( 'credvault_bundle', __( 'Import requires a writable secrets provider.', 'credvault' ) );
		}

		$payload = self::open( $bundle_b64, $password );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$skip_existing = ! empty( $options['skip_existing'] );
		$imported      = 0;
		$skipped       = 0;

		foreach ( (array) ( $payload['secrets'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$slug = sanitize_key( (string) ( $item['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$name     = Secrets_Bridge::secret_name( $slug );
			$existing = Secrets_Bridge::get_secret( $name );
			if ( $skip_existing && ! is_wp_error( $existing ) && null !== $existing ) {
				++$skipped;
				continue;
			}

			$meta = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();

			if ( isset( $item['value'] ) && is_string( $item['value'] ) && '' !== $item['value'] ) {
				$result = credential_vault_set( $slug, $item['value'], ! empty( $payload['network'] ), $meta );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				++$imported;
				continue;
			}

			credential_vault_register( $slug, $meta );
			++$imported;
		}

		Audit_Log::log_user_action(
			'bundle_imported',
			'',
			array(
				'imported' => $imported,
				'skipped'  => $skipped,
			)
		);

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Decrypt bundle and list slugs (dry-run preview).
	 *
	 * @return array{slugs: list<string>, with_values: int, network: bool}|\WP_Error
	 */
	public static function preview( string $bundle_b64, string $password ) {
		$payload = self::open( $bundle_b64, $password );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$slugs       = array();
		$with_values = 0;

		foreach ( (array) ( $payload['secrets'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug = sanitize_key( (string) ( $item['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$slugs[] = $slug;
			if ( isset( $item['value'] ) && is_string( $item['value'] ) && '' !== $item['value'] ) {
				++$with_values;
			}
		}

		return array(
			'slugs'        => $slugs,
			'with_values'  => $with_values,
			'network'      => ! empty( $payload['network'] ),
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return string|\WP_Error
	 */
	private static function seal( array $payload, string $password ) {
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) ) {
			return new \WP_Error( 'credvault_bundle', __( 'Could not encode bundle.', 'credvault' ) );
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error( 'credvault_bundle', __( 'OpenSSL is required for bundle encryption.', 'credvault' ) );
		}

		$salt = random_bytes( 16 );
		$key  = hash_pbkdf2( 'sha256', $password, $salt, 120000, 32, true );
		$iv   = random_bytes( 12 );

		$tag     = '';
		$encoded = openssl_encrypt( $json, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		if ( false === $encoded ) {
			return new \WP_Error( 'credvault_bundle', __( 'Encryption failed.', 'credvault' ) );
		}

		$out = wp_json_encode(
			array(
				'v'    => self::BUNDLE_VERSION,
				'salt' => base64_encode( $salt ),
				'iv'   => base64_encode( $iv ),
				'tag'  => base64_encode( $tag ),
				'data' => base64_encode( $encoded ),
			)
		);

		return is_string( $out ) ? base64_encode( $out ) : new \WP_Error( 'credvault_bundle', __( 'Could not encode bundle wrapper.', 'credvault' ) );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function open( string $bundle_b64, string $password ) {
		$wrapper_json = base64_decode( $bundle_b64, true );
		if ( false === $wrapper_json ) {
			return new \WP_Error( 'credvault_bundle', __( 'Invalid bundle encoding.', 'credvault' ) );
		}

		$wrapper = json_decode( $wrapper_json, true );
		if ( ! is_array( $wrapper ) ) {
			return new \WP_Error( 'credvault_bundle', __( 'Invalid bundle format.', 'credvault' ) );
		}

		$salt = base64_decode( (string) ( $wrapper['salt'] ?? '' ), true );
		$iv   = base64_decode( (string) ( $wrapper['iv'] ?? '' ), true );
		$tag  = base64_decode( (string) ( $wrapper['tag'] ?? '' ), true );
		$data = base64_decode( (string) ( $wrapper['data'] ?? '' ), true );

		if ( false === $salt || false === $iv || false === $tag || false === $data ) {
			return new \WP_Error( 'credvault_bundle', __( 'Corrupt bundle.', 'credvault' ) );
		}

		$key = hash_pbkdf2( 'sha256', $password, $salt, 120000, 32, true );
		$json = openssl_decrypt( $data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $json ) {
			return new \WP_Error( 'credvault_bundle', __( 'Wrong password or corrupt bundle.', 'credvault' ) );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'credvault_bundle', __( 'Invalid bundle payload.', 'credvault' ) );
		}

		return $payload;
	}
}
