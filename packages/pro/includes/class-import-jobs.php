<?php
/**
 * Async import job tracking (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Secrets_Bridge;

/**
 * Background-friendly import batches with REST status.
 */
final class Import_Jobs {

	private const OPTION = 'credential_vault_pro_import_jobs';

	public static function register(): void {
		add_action( 'credential_vault_pro_run_import_job', array( self::class, 'process' ), 10, 1 );
	}

	/**
	 * @param list<array{import_key: string, secret_name: string}> $items
	 */
	public static function create( array $items ): string {
		$id = wp_generate_uuid4();

		$job = array(
			'id'        => $id,
			'status'    => 'queued',
			'created'   => time(),
			'updated'   => time(),
			'items'     => $items,
			'completed' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$all[ $id ] = $job;
		update_option( self::OPTION, $all, false );

		wp_schedule_single_event( time() + 1, 'credential_vault_pro_run_import_job', array( $id ) );

		return $id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return null;
		}

		$row = $all[ $id ] ?? null;

		return is_array( $row ) ? $row : null;
	}

	public static function process( string $id ): void {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) || ! isset( $all[ $id ] ) || ! is_array( $all[ $id ] ) ) {
			return;
		}

		$job = $all[ $id ];
		$job['status'] = 'running';
		$job['updated'] = time();
		$all[ $id ]     = $job;
		update_option( self::OPTION, $all, false );

		foreach ( (array) ( $job['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$key  = sanitize_text_field( (string) ( $item['import_key'] ?? '' ) );
			$name = sanitize_text_field( (string) ( $item['secret_name'] ?? '' ) );

			if ( '' === $key || '' === $name ) {
				continue;
			}

			$result = Secrets_Bridge::import_from_source( $key, $name );
			if ( is_wp_error( $result ) ) {
				++$job['failed'];
				$job['errors'][] = array(
					'import_key' => $key,
					'message'    => $result->get_error_message(),
				);
			} else {
				++$job['completed'];
				Audit_Log::log_user_action(
					'imported',
					$name,
					array(
						'via'        => 'import_job',
						'import_key' => $key,
						'job_id'     => $id,
					)
				);
			}
		}

		$job['status']  = 'completed';
		$job['updated'] = time();
		$all[ $id ]     = $job;
		update_option( self::OPTION, $all, false );
	}
}
