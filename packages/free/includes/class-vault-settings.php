<?php
/**
 * Site-wide vault preferences (sudo TTL, sudo policy).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Reads/writes credential_vault_options.
 */
final class Vault_Settings {

	private const OPTION = 'credential_vault_options';

	public const SUDO_ALL_WRITES = 'all_writes';

	public const SUDO_REVEAL_ONLY = 'reveal_only';

	public static function get(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @return array{version: string, sudo_ttl: int, sudo_mode: string}
	 */
	public static function defaults(): array {
		return array(
			'version'   => CREDENTIAL_VAULT_VERSION,
			'sudo_ttl'  => 600,
			'sudo_mode' => self::SUDO_ALL_WRITES,
		);
	}

	public static function sudo_ttl_seconds(): int {
		$settings = self::get();
		$ttl      = (int) ( $settings['sudo_ttl'] ?? 600 );

		/**
		 * Sudo session length in seconds after successful password confirmation.
		 *
		 * @param int $ttl Seconds.
		 */
		$ttl = (int) apply_filters( 'credential_vault_sudo_ttl', $ttl );

		return max( 60, min( 3600, $ttl ) );
	}

	public static function sudo_mode(): string {
		$settings = self::get();
		$mode     = (string) ( $settings['sudo_mode'] ?? self::SUDO_ALL_WRITES );

		if ( ! in_array( $mode, array( self::SUDO_ALL_WRITES, self::SUDO_REVEAL_ONLY ), true ) ) {
			$mode = self::SUDO_ALL_WRITES;
		}

		/**
		 * Whether sudo is required for all secret writes or only for reveal/copy actions.
		 *
		 * @param string $mode `all_writes` or `reveal_only`.
		 */
		return (string) apply_filters( 'credential_vault_sudo_mode', $mode );
	}

	public static function sudo_required_for_writes(): bool {
		return self::SUDO_ALL_WRITES === self::sudo_mode();
	}

	/**
	 * @param array<string, mixed> $patch Partial settings.
	 */
	public static function update( array $patch ): void {
		$current = self::get();

		if ( isset( $patch['sudo_ttl'] ) ) {
			$current['sudo_ttl'] = max( 60, min( 3600, (int) $patch['sudo_ttl'] ) );
		}

		if ( isset( $patch['sudo_mode'] ) ) {
			$mode = sanitize_key( (string) $patch['sudo_mode'] );
			if ( in_array( $mode, array( self::SUDO_ALL_WRITES, self::SUDO_REVEAL_ONLY ), true ) ) {
				$current['sudo_mode'] = $mode;
			}
		}

		$current['version'] = CREDENTIAL_VAULT_VERSION;
		update_option( self::OPTION, $current, false );
	}
}
