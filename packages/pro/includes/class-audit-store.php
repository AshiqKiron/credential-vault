<?php
/**
 * Long-retention immutable audit store (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;

/**
 * Append-only audit table with retention policies and REST/query support.
 */
final class Audit_Store {

	private const RETENTION_OPTION = 'credential_vault_pro_audit_retention_days';

	private const DEFAULT_RETENTION_DAYS = 365;

	public static function register(): void {
		add_action( 'credential_vault_audit_entry', array( self::class, 'persist' ), 5, 1 );
		add_filter( 'credential_vault_audit_merge_entries', array( self::class, 'merge_for_admin' ), 10, 2 );
		add_action( 'credential_vault_pro_audit_purge', array( self::class, 'purge_expired' ) );
		add_action( 'admin_post_credential_vault_pro_save_audit_retention', array( self::class, 'handle_save_retention' ) );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'credential_vault_audit';
	}

	public static function retention_days(): int {
		$days = (int) get_option( self::RETENTION_OPTION, self::DEFAULT_RETENTION_DAYS );

		return max( 30, min( 3650, $days ) );
	}

	public static function maybe_schedule_purge(): void {
		if ( ! wp_next_scheduled( 'credential_vault_pro_audit_purge' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'credential_vault_pro_audit_purge' );
		}
	}

	/**
	 * @param array<string, mixed> $entry Audit row from free plugin.
	 */
	public static function persist( array $entry ): void {
		global $wpdb;

		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			Activator::create_tables();
		}

		$payload = $entry;
		unset( $payload['time'], $payload['action'], $payload['secret'], $payload['user_id'], $payload['network'] );

		$wpdb->insert(
			$table,
			array(
				'recorded_at' => (int) ( $entry['time'] ?? time() ),
				'action'      => sanitize_key( (string) ( $entry['action'] ?? '' ) ),
				'secret'      => sanitize_text_field( (string) ( $entry['secret'] ?? '' ) ),
				'user_id'     => (int) ( $entry['user_id'] ?? 0 ),
				'network'     => ! empty( $entry['network'] ) ? 1 : 0,
				'payload'     => wp_json_encode( $payload ) ?: '{}',
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	public static function purge_expired(): void {
		global $wpdb;

		$cutoff = time() - ( self::retention_days() * DAY_IN_SECONDS );
		$table  = self::table_name();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE recorded_at < %d",
				$cutoff
			)
		);
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return list<array<string, mixed>>
	 */
	public static function query( array $filters = array(), int $limit = 500, int $offset = 0 ): array {
		global $wpdb;

		$table = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = sanitize_key( (string) $filters['action'] );
		}
		if ( ! empty( $filters['secret'] ) ) {
			$where[]  = 'secret LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $filters['secret'] ) ) . '%';
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['since'] ) ) {
			$where[]  = 'recorded_at >= %d';
			$params[] = (int) $filters['since'];
		}
		if ( ! empty( $filters['until'] ) ) {
			$where[]  = 'recorded_at <= %d';
			$params[] = (int) $filters['until'];
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where )
			. ' ORDER BY recorded_at DESC LIMIT %d OFFSET %d';

		$params[] = max( 1, min( 5000, $limit ) );
		$params[] = max( 0, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE with prepare args.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$extra = json_decode( (string) ( $row['payload'] ?? '{}' ), true );
			if ( ! is_array( $extra ) ) {
				$extra = array();
			}

			$out[] = array_merge(
				array(
					'time'    => (int) ( $row['recorded_at'] ?? 0 ),
					'action'  => (string) ( $row['action'] ?? '' ),
					'secret'  => (string) ( $row['secret'] ?? '' ),
					'user_id' => (int) ( $row['user_id'] ?? 0 ),
					'network' => ! empty( $row['network'] ),
				),
				$extra
			);
		}

		return $out;
	}

	/**
	 * Stream JSON Lines for SIEM sinks.
	 *
	 * @param array<string, mixed> $filters
	 */
	public static function stream_json_lines( array $filters = array(), int $limit = 5000 ): void {
		$rows = self::query( $filters, $limit, 0 );

		foreach ( $rows as $entry ) {
			$line = wp_json_encode(
				array(
					'ts'      => gmdate( 'c', (int) ( $entry['time'] ?? 0 ) ),
					'action'  => $entry['action'] ?? '',
					'secret'  => $entry['secret'] ?? '',
					'user_id' => $entry['user_id'] ?? 0,
					'site'    => home_url( '/' ),
					'extra'   => $entry,
				)
			);
			if ( is_string( $line ) ) {
				echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSONL export.
			}
		}
	}

	/**
	 * @param list<array<string, mixed>> $log
	 * @param array<string, mixed>       $filters
	 * @return list<array<string, mixed>>
	 */
	public static function merge_for_admin( array $log, array $filters ): array {
		$from_db = self::query( $filters, 500, 0 );
		if ( empty( $from_db ) ) {
			return $log;
		}

		return $from_db;
	}

	public static function handle_save_retention(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_audit_retention' );

		$days = isset( $_POST['audit_retention_days'] ) ? (int) $_POST['audit_retention_days'] : self::DEFAULT_RETENTION_DAYS;
		update_option( self::RETENTION_OPTION, max( 30, min( 3650, $days ) ), false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'audit_retention_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
