<?php
/**
 * Local / development ergonomics (production behavior unchanged).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Applies filters when wp_get_environment_type() is local or development.
 */
final class Dev_Profile {

	public static function register(): void {
		add_filter( 'credential_vault_sudo_ttl', array( self::class, 'shorter_sudo_ttl' ), 5 );
		add_filter( 'credential_vault_demo_data_ui', array( self::class, 'enable_demo_ui' ), 5 );
		add_filter( 'credential_vault_skip_sudo_for_cli', array( self::class, 'allow_cli_sudo_skip' ), 5 );
		add_filter( 'credential_vault_test_mode', array( self::class, 'enable_test_storage_without_api' ), 5 );
		add_filter( 'credential_vault_skip_sudo_for_demo_install', array( self::class, 'allow_demo_install_without_sudo' ), 5 );
	}

	public static function is_active(): bool {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$type = wp_get_environment_type();
			if ( in_array( $type, array( 'local', 'development' ), true ) ) {
				return true;
			}
		}

		/**
		 * Force dev profile filters (e.g. wp-env, Docker).
		 *
		 * @param bool $active Default false outside local/development.
		 */
		return (bool) apply_filters( 'credential_vault_dev_profile', false );
	}

	/**
	 * @param int $ttl Seconds.
	 * @return int
	 */
	public static function shorter_sudo_ttl( int $ttl ): int {
		if ( ! self::is_active() ) {
			return $ttl;
		}

		return min( $ttl, 300 );
	}

	/**
	 * @param bool $enabled
	 * @return bool
	 */
	public static function enable_demo_ui( bool $enabled ): bool {
		if ( ! self::is_active() ) {
			return $enabled;
		}

		return true;
	}

	/**
	 * @param bool $skip
	 * @return bool
	 */
	public static function allow_cli_sudo_skip( bool $skip ): bool {
		if ( ! self::is_active() ) {
			return $skip;
		}

		return true;
	}

	/**
	 * In-memory vault when the Secrets API is absent (local QA only).
	 *
	 * @param mixed $enabled
	 * @return mixed
	 */
	public static function enable_test_storage_without_api( $enabled ) {
		if ( $enabled || Secrets_Bridge::is_available() ) {
			return $enabled;
		}

		if ( self::is_active() ) {
			return true;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		return $enabled;
	}

	/**
	 * @param bool $skip
	 * @return bool
	 */
	public static function allow_demo_install_without_sudo( bool $skip ): bool {
		if ( ! self::is_active() && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return $skip;
		}

		return true;
	}
}
