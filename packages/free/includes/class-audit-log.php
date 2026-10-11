<?php
/**
 * Vault audit trail (core change hook + local access events).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Append-only audit log stored in a site option (metadata only, never secret values).
 */
final class Audit_Log {

	private const OPTION = 'credential_vault_audit_log';

	private const MAX_ENTRIES = 500;

	public static function register_hooks( Loader $loader ): void {
		$loader->add_action( 'wp_secret_changed', self::class, 'on_secret_changed', 10, 7 );
	}

	/**
	 * @param string $name            Secret name.
	 * @param string $action          created|updated|deleted|imported|retired.
	 * @param int    $actor_id        User ID.
	 * @param int    $timestamp       Unix time.
	 * @param string $old_fingerprint Previous fingerprint.
	 * @param string $new_fingerprint New fingerprint.
	 * @param bool   $network         Network scope.
	 */
	public static function on_secret_changed(
		string $name,
		string $action,
		int $actor_id,
		int $timestamp,
		string $old_fingerprint,
		string $new_fingerprint,
		bool $network
	): void {
		if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			return;
		}

		self::append(
			array(
				'time'            => $timestamp,
				'action'          => $action,
				'secret'          => $name,
				'user_id'         => $actor_id,
				'old_fingerprint' => $old_fingerprint,
				'new_fingerprint' => $new_fingerprint,
				'network'         => $network,
			)
		);
	}

	/**
	 * @param string               $action  accessed|copied|sudo_started|sudo_ended.
	 * @param string               $secret  Fully qualified secret name.
	 * @param array<string, mixed> $extra   Optional context.
	 */
	public static function log_user_action( string $action, string $secret = '', array $extra = array() ): void {
		self::append(
			array_merge(
				array(
					'time'    => time(),
					'action'  => $action,
					'secret'  => $secret,
					'user_id' => get_current_user_id(),
				),
				$extra
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function entries( int $limit = 100 ): array {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		$log = array_slice( $log, -1 * max( 1, $limit ) );

		return array_reverse( $log );
	}

	/**
	 * @param array<string, mixed> $entry Sanitized entry.
	 */
	private static function append( array $entry ): void {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = $entry;

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -1 * self::MAX_ENTRIES );
		}

		update_option( self::OPTION, $log, false );
	}
}
