<?php
/**
 * Periodic credential health checks (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Credential_Meta;
use CredentialVault\Rotation_Workflow;
use CredentialVault\Secrets_Bridge;

/**
 * Lightweight rotation test pings and dashboard status.
 */
final class Health_Monitor {

	private const OPTION = 'credential_vault_pro_health_status';

	public static function register(): void {
		add_action( 'credential_vault_pro_health_check', array( self::class, 'run_checks' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_filter( 'credential_vault_admin_context', array( self::class, 'inject_context' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( 'credential_vault_pro_health_check' ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'twicedaily', 'credential_vault_pro_health_check' );
		}
	}

	public static function run_checks(): void {
		if ( ! Secrets_Bridge::is_available() ) {
			return;
		}

		$status = array();
		$broken = Secrets_Bridge::find_undecryptable( false );

		if ( ! empty( $broken ) ) {
			do_action( 'credential_vault_pro_undecryptable_detected', $broken, false );
		}

		foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
			$name = (string) $row['name'];
			$test = Rotation_Workflow::run_post_save_test( $name, false );

			$status[ $name ] = array(
				'ok'        => ! is_wp_error( $test ),
				'checked'   => time(),
				'message'   => is_wp_error( $test ) ? $test->get_error_message() : '',
				'label'     => ( Credential_Meta::get( $name )['label'] ?? basename( $name ) ),
			);
		}

		update_option( self::OPTION, $status, false );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function inject_context( array $context ): array {
		$stored = get_option( self::OPTION, array() );
		$context['pro_health'] = is_array( $stored ) ? $stored : array();

		return $context;
	}

	/**
	 * @return array<string, array{ok: bool, checked: int, message: string, label: string}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}
}
