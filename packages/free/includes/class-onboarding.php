<?php
/**
 * First-run onboarding wizard state.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Tracks whether the operator completed vault onboarding.
 */
final class Onboarding {

	private const OPTION = 'credential_vault_onboarding_done';

	public static function is_complete(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	public static function mark_complete(): void {
		update_option( self::OPTION, true, false );
	}

	public static function reset(): void {
		delete_option( self::OPTION );
	}

	/**
	 * @return list<array{key: string, done: bool, label: string}>
	 */
	public static function checklist(): array {
		$api = Secrets_Bridge::is_available();
		$sudo = Sudo_Gate::is_active();
		$has_secret = $api && count( Secrets_Bridge::list_vault_secrets( false ) ) > 0;
		$imported = $api && count( Plaintext_Scanner::scan() ) === 0;
		$audit    = count( Audit_Log::entries( 1, array() ) ) > 0;
		$tested   = $api && self::has_rotation_test_audit();

		return array(
			array(
				'key'   => 'api',
				'done'  => $api,
				'label' => __( 'Secrets API available', 'credvault' ),
			),
			array(
				'key'   => 'sudo',
				'done'  => $sudo,
				'label' => __( 'Vault unlocked (sudo session)', 'credvault' ),
			),
			array(
				'key'   => 'secret',
				'done'  => $has_secret,
				'label' => __( 'At least one credential stored', 'credvault' ),
			),
			array(
				'key'   => 'hygiene',
				'done'  => $imported,
				'label' => __( 'No plaintext scanner hits (optional)', 'credvault' ),
			),
			array(
				'key'   => 'rotation_test',
				'done'  => $tested,
				'label' => __( 'At least one rotation smoke test logged (optional)', 'credvault' ),
			),
			array(
				'key'   => 'audit',
				'done'  => $audit,
				'label' => __( 'Audit log has activity', 'credvault' ),
			),
		);
	}

	private static function has_rotation_test_audit(): bool {
		foreach ( Audit_Log::entries( 50, array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$action = sanitize_key( (string) ( $entry['action'] ?? '' ) );
			if ( in_array( $action, array( 'rotate', 'rotated', 'rotation_test' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function progress_percent(): int {
		$items = self::checklist();
		$done  = 0;
		foreach ( $items as $item ) {
			if ( ! empty( $item['done'] ) ) {
				++$done;
			}
		}

		return (int) round( ( $done / max( 1, count( $items ) ) ) * 100 );
	}
}
