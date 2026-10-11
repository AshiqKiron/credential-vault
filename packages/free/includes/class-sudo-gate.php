<?php
/**
 * Password re-authentication before sensitive vault actions.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Short-lived sudo sessions per user (until core sudo mode lands).
 */
final class Sudo_Gate {

	private const USER_META_EXPIRES = 'credential_vault_sudo_expires';

	private const SESSION_LENGTH = 900; // 15 minutes.

	private const MAX_ATTEMPTS = 5;

	private const LOCKOUT_LENGTH = 300; // 5 minutes.

	/**
	 * @return bool
	 */
	public static function is_active(): bool {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$expires = (int) get_user_meta( $user_id, self::USER_META_EXPIRES, true );

		return $expires > time();
	}

	/**
	 * Remaining sudo time in seconds.
	 */
	public static function remaining_seconds(): int {
		if ( ! self::is_active() ) {
			return 0;
		}

		$user_id = get_current_user_id();
		$expires = (int) get_user_meta( $user_id, self::USER_META_EXPIRES, true );

		return max( 0, $expires - time() );
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function establish( string $password ) {
		$user = wp_get_current_user();

		if ( ! $user instanceof \WP_User || 0 === $user->ID ) {
			return new \WP_Error( 'credential_vault_sudo', __( 'You must be logged in.', 'credvault' ) );
		}

		if ( self::is_locked_out( $user->ID ) ) {
			return new \WP_Error(
				'credential_vault_sudo_locked',
				__( 'Too many failed attempts. Try again in a few minutes.', 'credvault' )
			);
		}

		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			self::record_failed_attempt( $user->ID );

			return new \WP_Error( 'credential_vault_sudo', __( 'Incorrect password.', 'credvault' ) );
		}

		self::clear_failed_attempts( $user->ID );
		update_user_meta( $user->ID, self::USER_META_EXPIRES, time() + self::SESSION_LENGTH );

		return true;
	}

	public static function revoke(): void {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, self::USER_META_EXPIRES );
		}
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function require_active() {
		if ( self::is_active() ) {
			return true;
		}

		return new \WP_Error(
			'credential_vault_sudo_required',
			__( 'Confirm your password to view or change credentials.', 'credvault' )
		);
	}

	private static function lockout_key( int $user_id ): string {
		return 'credential_vault_sudo_lock_' . $user_id;
	}

	private static function attempts_key( int $user_id ): string {
		return 'credential_vault_sudo_attempts_' . $user_id;
	}

	private static function is_locked_out( int $user_id ): bool {
		return (bool) get_transient( self::lockout_key( $user_id ) );
	}

	private static function record_failed_attempt( int $user_id ): void {
		$key      = self::attempts_key( $user_id );
		$attempts = (int) get_transient( $key ) + 1;

		set_transient( $key, $attempts, self::LOCKOUT_LENGTH );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			set_transient( self::lockout_key( $user_id ), 1, self::LOCKOUT_LENGTH );
			delete_transient( $key );
		}
	}

	private static function clear_failed_attempts( int $user_id ): void {
		delete_transient( self::attempts_key( $user_id ) );
		delete_transient( self::lockout_key( $user_id ) );
	}
}
