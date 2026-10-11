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

	private const DEFAULT_MAX_ENTRIES = 500;

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
	 * Record programmatic secret access (never stores plaintext).
	 *
	 * @param string               $secret_name Fully qualified secret name.
	 * @param array<string, mixed> $extra       Optional context (e.g. source, slug).
	 */
	public static function log_secret_queried( string $secret_name, array $extra = array() ): void {
		if ( 0 !== strpos( $secret_name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			return;
		}

		/**
		 * Whether to append audit entries when code reads a vault credential.
		 *
		 * @param bool   $log         Default true.
		 * @param string $secret_name Fully qualified secret name.
		 * @param array  $extra       Context passed to the logger.
		 */
		if ( ! apply_filters( 'credential_vault_log_secret_queries', true, $secret_name, $extra ) ) {
			return;
		}

		self::log_user_action( 'queried', $secret_name, $extra );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function entries( int $limit = 100, array $filters = array() ): array {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		$log = self::apply_filters( $log, $filters );
		$log = array_slice( $log, -1 * max( 1, $limit ) );

		return array_reverse( $log );
	}

	/**
	 * @param array<string, mixed> $filters Optional keys: action, secret, user_id, since, until.
	 * @return list<array<string, mixed>>
	 */
	public static function all_entries( array $filters = array() ): array {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		$log = self::apply_filters( $log, $filters );

		return array_reverse( $log );
	}

	/**
	 * Stream audit rows as CSV to the output buffer.
	 *
	 * @param array<string, mixed> $filters Same keys as entries().
	 */
	public static function export_csv( array $filters = array() ): void {
		$rows = self::all_entries( $filters );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}

		fputcsv(
			$out,
			array( 'time_utc', 'action', 'secret', 'user_id', 'old_fingerprint', 'new_fingerprint', 'network', 'extra' )
		);

		foreach ( $rows as $entry ) {
			$extra = $entry;
			unset( $extra['time'], $extra['action'], $extra['secret'], $extra['user_id'], $extra['old_fingerprint'], $extra['new_fingerprint'], $extra['network'] );

			fputcsv(
				$out,
				array(
					gmdate( 'c', (int) ( $entry['time'] ?? 0 ) ),
					(string) ( $entry['action'] ?? '' ),
					(string) ( $entry['secret'] ?? '' ),
					(string) ( $entry['user_id'] ?? '' ),
					(string) ( $entry['old_fingerprint'] ?? '' ),
					(string) ( $entry['new_fingerprint'] ?? '' ),
					! empty( $entry['network'] ) ? '1' : '0',
					wp_json_encode( $extra ),
				)
			);
		}

		fclose( $out );
	}

	/**
	 * @param list<array<string, mixed>> $log
	 * @param array<string, mixed>       $filters
	 * @return list<array<string, mixed>>
	 */
	private static function apply_filters( array $log, array $filters ): array {
		if ( empty( $filters ) ) {
			return $log;
		}

		$action  = isset( $filters['action'] ) ? sanitize_key( (string) $filters['action'] ) : '';
		$secret  = isset( $filters['secret'] ) ? sanitize_text_field( (string) $filters['secret'] ) : '';
		$user_id = isset( $filters['user_id'] ) ? (int) $filters['user_id'] : 0;
		$since   = isset( $filters['since'] ) ? (int) $filters['since'] : 0;
		$until   = isset( $filters['until'] ) ? (int) $filters['until'] : 0;

		return array_values(
			array_filter(
				$log,
				static function ( array $entry ) use ( $action, $secret, $user_id, $since, $until ): bool {
					if ( $action && $action !== (string) ( $entry['action'] ?? '' ) ) {
						return false;
					}
					if ( $secret && false === stripos( (string) ( $entry['secret'] ?? '' ), $secret ) ) {
						return false;
					}
					if ( $user_id > 0 && $user_id !== (int) ( $entry['user_id'] ?? 0 ) ) {
						return false;
					}
					$time = (int) ( $entry['time'] ?? 0 );
					if ( $since > 0 && $time < $since ) {
						return false;
					}
					if ( $until > 0 && $time > $until ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	private static function max_entries(): int {
		/**
		 * Maximum audit log rows retained in the database.
		 *
		 * @param int $max Default 500.
		 */
		return (int) apply_filters( 'credential_vault_audit_max_entries', self::DEFAULT_MAX_ENTRIES );
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

		$max = max( 100, self::max_entries() );
		if ( count( $log ) > $max ) {
			$log = array_slice( $log, -1 * $max );
		}

		update_option( self::OPTION, $log, false );

		/**
		 * Fires after an audit row is stored (Pro may dispatch webhooks).
		 *
		 * @param array<string, mixed> $entry Sanitized audit row.
		 */
		do_action( 'credential_vault_audit_entry', $entry );
	}
}
