<?php
/**
 * In-memory vault for PHPUnit / wp-env when Secrets API is absent.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Enable with define( 'CREDENTIAL_VAULT_TEST_MODE', true ) or filter credential_vault_test_mode.
 */
final class Test_Storage {

	private const VALUES_OPTION = 'credential_vault_test_secret_values';

	/** @var array<string, string> slug => value (site scope). */
	private static array $site = array();

	/** @var array<string, string> slug => value (network scope). */
	private static array $network = array();

	private static bool $hydrated = false;

	public static function register(): void {
		add_filter( 'credential_vault_test_mode', array( self::class, 'default_test_mode' ) );
		add_filter( 'credential_vault_available', array( self::class, 'filter_available' ) );
		add_filter( 'pre_credential_vault_get', array( self::class, 'pre_get' ), 10, 3 );
		add_filter( 'pre_credential_vault_set', array( self::class, 'pre_set' ), 10, 4 );
	}

	/**
	 * @param mixed $pre
	 * @return mixed
	 */
	public static function default_test_mode( $pre ) {
		if ( null !== $pre && false !== $pre ) {
			return $pre;
		}

		return defined( 'CREDENTIAL_VAULT_TEST_MODE' ) && CREDENTIAL_VAULT_TEST_MODE;
	}

	public static function enabled(): bool {
		return (bool) apply_filters( 'credential_vault_test_mode', false );
	}

	public static function get_value( string $slug, bool $network ): ?string {
		self::hydrate();
		$slug  = sanitize_key( $slug );
		$store = $network ? self::$network : self::$site;

		if ( ! array_key_exists( $slug, $store ) ) {
			return null;
		}

		return $store[ $slug ];
	}

	/**
	 * @param bool $available
	 * @return bool
	 */
	public static function filter_available( bool $available ): bool {
		if ( $available ) {
			return true;
		}

		return self::enabled();
	}

	/**
	 * @param mixed  $pre
	 * @param string $slug
	 * @param bool   $network
	 * @return mixed
	 */
	public static function pre_get( $pre, string $slug, bool $network ) {
		if ( ! self::enabled() ) {
			return $pre;
		}

		self::hydrate();
		$slug  = sanitize_key( $slug );
		$store = $network ? self::$network : self::$site;

		return array_key_exists( $slug, $store ) ? $store[ $slug ] : null;
	}

	/**
	 * @param mixed                $pre
	 * @param string               $slug
	 * @param string               $value
	 * @param bool                 $network
	 * @param array<string, mixed> $meta
	 * @return mixed
	 */
	public static function pre_set( $pre, string $slug, string $value, bool $network, array $meta = array() ) {
		unset( $meta );
		if ( ! self::enabled() ) {
			return $pre;
		}

		self::hydrate();
		$slug = sanitize_key( $slug );
		if ( $network ) {
			self::$network[ $slug ] = $value;
		} else {
			self::$site[ $slug ] = $value;
		}

		self::persist();

		return true;
	}

	public static function reset(): void {
		self::$site     = array();
		self::$network  = array();
		self::$hydrated = true;
		delete_option( self::VALUES_OPTION );
	}

	public static function forget( string $slug, bool $network ): void {
		self::hydrate();
		$slug = sanitize_key( $slug );
		if ( $network ) {
			unset( self::$network[ $slug ] );
		} else {
			unset( self::$site[ $slug ] );
		}

		self::persist();
	}

	/**
	 * @return list<string> Vault slugs stored in test mode (site scope).
	 */
	public static function site_slugs(): array {
		self::hydrate();

		return array_keys( self::$site );
	}

	private static function hydrate(): void {
		if ( self::$hydrated ) {
			return;
		}

		self::$hydrated = true;
		$stored         = get_option( self::VALUES_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return;
		}

		$site = $stored['site'] ?? array();
		if ( is_array( $site ) ) {
			foreach ( $site as $slug => $value ) {
				$key = sanitize_key( (string) $slug );
				if ( '' !== $key && is_string( $value ) ) {
					self::$site[ $key ] = $value;
				}
			}
		}

		$network = $stored['network'] ?? array();
		if ( is_array( $network ) ) {
			foreach ( $network as $slug => $value ) {
				$key = sanitize_key( (string) $slug );
				if ( '' !== $key && is_string( $value ) ) {
					self::$network[ $key ] = $value;
				}
			}
		}
	}

	private static function persist(): void {
		update_option(
			self::VALUES_OPTION,
			array(
				'site'    => self::$site,
				'network' => self::$network,
			),
			false
		);
	}
}
