<?php
/**
 * Thin wrapper around the WordPress Secrets API (core 7.2+ or Secrets API feature plugin).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Detects availability and normalizes secret operations for the vault UI.
 */
final class Secrets_Bridge {

	public const VAULT_NAMESPACE = 'credential-vault';

	/**
	 * Capability for vault admin screens (granted to administrators on activate).
	 */
	public const VAULT_CAP = 'manage_credential_vault';

	public static function is_available(): bool {
		return function_exists( 'wp_list_secrets' ) && function_exists( 'wp_set_secret' );
	}

	public static function supports_network(): bool {
		return is_multisite() && function_exists( 'wp_list_network_secrets' );
	}

	public static function manage_capability(): string {
		if ( defined( 'WP_SECRETS_CAP_MANAGE' ) ) {
			return WP_SECRETS_CAP_MANAGE;
		}

		return 'manage_secrets';
	}

	public static function network_manage_capability(): string {
		if ( defined( 'WP_SECRETS_CAP_MANAGE_NETWORK' ) ) {
			return WP_SECRETS_CAP_MANAGE_NETWORK;
		}

		return 'manage_network_secrets';
	}

	public static function current_user_can_view( bool $network = false ): bool {
		if ( self::current_user_can_manage( $network ) ) {
			return true;
		}

		return current_user_can( Vault_Capabilities::VIEW_CAP );
	}

	public static function current_user_can_manage( bool $network = false ): bool {
		if ( current_user_can( Vault_Capabilities::VIEW_CAP ) && ! current_user_can( self::VAULT_CAP )
			&& ! current_user_can( self::manage_capability() ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( $network ) {
			if ( current_user_can( self::network_manage_capability() ) ) {
				return true;
			}
			return is_multisite() && current_user_can( 'manage_network' );
		}

		if ( current_user_can( self::VAULT_CAP ) ) {
			return true;
		}

		if ( current_user_can( self::manage_capability() ) ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	public static function secret_name( string $slug ): string {
		$slug = sanitize_key( $slug );

		return self::VAULT_NAMESPACE . '/' . $slug;
	}

	/**
	 * @return list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}>
	 */
	public static function list_vault_secrets( bool $network = false ): array {
		return self::list_secrets( self::VAULT_NAMESPACE, $network );
	}

	/**
	 * @return list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}>
	 */
	public static function list_secrets( string $namespace = '', bool $network = false ): array {
		if ( ! self::is_available() ) {
			return array();
		}

		$list = $network ? wp_list_network_secrets( $namespace ) : wp_list_secrets( $namespace );

		if ( is_wp_error( $list ) ) {
			return array();
		}

		return $list;
	}

	/**
	 * Secrets registered by other plugins (outside this vault namespace).
	 *
	 * @return list<array{name: string, fingerprint: string, created: int, has_previous: bool, needs_rotation: bool}>
	 */
	public static function list_external_secrets( bool $network = false ): array {
		$all = self::list_secrets( '', $network );
		$out = array();

		foreach ( $all as $row ) {
			$name = (string) $row['name'];
			if ( 0 === strpos( $name, self::VAULT_NAMESPACE . '/' ) ) {
				continue;
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * @return list<string> Secret names that exist but cannot be decrypted.
	 */
	public static function find_undecryptable( bool $network = false ): array {
		if ( ! self::is_available() ) {
			return array();
		}

		$broken = array();

		foreach ( self::list_secrets( '', $network ) as $row ) {
			$name   = (string) $row['name'];
			$secret = $network ? self::get_network_secret( $name ) : self::get_secret( $name );

			if ( is_wp_error( $secret ) ) {
				$broken[] = $name;
			}
		}

		return $broken;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function set_secret( string $name, string $value, bool $network = false ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'The WordPress Secrets API is not available on this site.', 'credvault' ) );
		}

		if ( ! wp_secrets_provider_is_writable() ) {
			return new \WP_Error(
				'credential_vault_read_only',
				__( 'Secrets are managed by your host and cannot be changed from WordPress.', 'credvault' )
			);
		}

		return $network ? wp_set_network_secret( $name, $value ) : wp_set_secret( $name, $value );
	}

	/**
	 * @return \WP_Secret|null|\WP_Error
	 */
	public static function get_secret( string $name, ?string $version = null ) {
		return self::get_scoped_secret( $name, false, $version );
	}

	/**
	 * @return \WP_Secret|null|\WP_Error
	 */
	public static function get_network_secret( string $name, ?string $version = null ) {
		return self::get_scoped_secret( $name, true, $version );
	}

	/**
	 * @return \WP_Secret|null|\WP_Error
	 */
	private static function get_scoped_secret( string $name, bool $network, ?string $version = null ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'The WordPress Secrets API is not available on this site.', 'credvault' ) );
		}

		if ( null === $version && class_exists( 'WP_Secret_Version' ) ) {
			$version = \WP_Secret_Version::CURRENT;
		}

		if ( null === $version ) {
			$version = 'current';
		}

		return $network ? wp_get_network_secret( $name, $version ) : wp_get_secret( $name, $version );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function delete_secret( string $name, bool $network = false ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'The WordPress Secrets API is not available on this site.', 'credvault' ) );
		}

		return $network ? wp_delete_network_secret( $name ) : wp_delete_secret( $name );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function retire_previous( string $name, bool $network = false ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'The WordPress Secrets API is not available on this site.', 'credvault' ) );
		}

		if ( $network ) {
			return function_exists( 'wp_retire_network_secret_version' )
				? wp_retire_network_secret_version( $name )
				: new \WP_Error( 'credential_vault_no_api', __( 'Network secret retirement is unavailable.', 'credvault' ) );
		}

		return function_exists( 'wp_retire_secret_version' )
			? wp_retire_secret_version( $name )
			: new \WP_Error( 'credential_vault_no_api', __( 'Secret retirement is unavailable.', 'credvault' ) );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function import_option( string $option, string $name ) {
		if ( ! self::is_available() || ! function_exists( 'wp_import_option_as_secret' ) ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'Import is unavailable.', 'credvault' ) );
		}

		return wp_import_option_as_secret( $option, $name );
	}

	/**
	 * Import plaintext from a scanner/import reference key.
	 *
	 * @param string $import_key e.g. option:foo or usermeta:1:api_key.
	 * @return true|\WP_Error
	 */
	public static function import_from_source( string $import_key, string $name ) {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'credential_vault_no_api', __( 'Import is unavailable.', 'credvault' ) );
		}

		if ( 0 === strpos( $import_key, 'option:' ) ) {
			$option = substr( $import_key, 7 );
			if ( '' === $option ) {
				return new \WP_Error( 'credential_vault_import', __( 'Invalid option reference.', 'credvault' ) );
			}

			if ( function_exists( 'wp_import_option_as_secret' ) ) {
				return wp_import_option_as_secret( $option, $name );
			}

			$value = get_option( $option, null );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return new \WP_Error( 'credential_vault_import', __( 'Option value is missing or not a string.', 'credvault' ) );
			}

			return self::set_secret( $name, $value );
		}

		if ( 0 === strpos( $import_key, 'usermeta:' ) ) {
			$parts = explode( ':', $import_key, 3 );
			if ( count( $parts ) < 3 ) {
				return new \WP_Error( 'credential_vault_import', __( 'Invalid user meta reference.', 'credvault' ) );
			}

			$user_id  = (int) $parts[1];
			$meta_key = $parts[2];
			$value    = get_user_meta( $user_id, $meta_key, true );

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return new \WP_Error( 'credential_vault_import', __( 'User meta value is missing or not a string.', 'credvault' ) );
			}

			return self::set_secret( $name, $value );
		}

		return new \WP_Error( 'credential_vault_import', __( 'Unknown import source.', 'credvault' ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function export_metadata_inventory( bool $include_network = true ): array {
		$rows    = self::list_vault_secrets( false );
		if ( $include_network && self::supports_network() ) {
			$rows = array_merge( $rows, self::list_vault_secrets( true ) );
		}

		$out = array();
		foreach ( $rows as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			$out[] = array(
				'name'            => $name,
				'slug'            => basename( $name ),
				'label'           => $meta['label'] ?? '',
				'service'         => $meta['service'] ?? '',
				'environment'     => $meta['environment'] ?? '',
				'owner'           => $meta['owner'] ?? '',
				'runbook_url'     => $meta['runbook_url'] ?? '',
				'public_fields'   => $meta['public_fields'] ?? array(),
				'rotate_by'       => (int) ( $meta['rotate_by'] ?? 0 ),
				'fingerprint'     => (string) ( $row['fingerprint'] ?? '' ),
				'needs_rotation'  => ! empty( $row['needs_rotation'] ),
				'has_previous'    => ! empty( $row['has_previous'] ),
				'masked_preview'  => $meta['masked_preview'] ?? '',
			);
		}

		return $out;
	}

	public static function masked_preview( string $plaintext ): string {
		$length = strlen( $plaintext );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 8 ) {
			return str_repeat( '•', $length );
		}

		$head = substr( $plaintext, 0, min( 7, $length - 4 ) );

		return $head . '…' . substr( $plaintext, -4 );
	}

	public static function provider_label(): string {
		if ( ! self::is_available() || ! function_exists( 'wp_secrets_provider_label' ) ) {
			return '';
		}

		return wp_secrets_provider_label();
	}

	public static function memzero( string &$value ): void {
		if ( function_exists( 'wp_secrets_memzero' ) ) {
			wp_secrets_memzero( $value );
		}
	}
}
