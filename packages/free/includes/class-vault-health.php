<?php
/**
 * Aggregated vault health metrics for dashboards and widgets.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Single source of truth for operational health counters.
 */
final class Vault_Health {

	/**
	 * @return array<string, int|bool|string>
	 */
	public static function summary(): array {
		$available = Secrets_Bridge::is_available();
		$writable  = $available && function_exists( 'wp_secrets_provider_is_writable' ) && wp_secrets_provider_is_writable();

		$undecryptable = 0;
		if ( $available ) {
			$undecryptable = count( Secrets_Bridge::find_undecryptable( false ) );
			if ( Secrets_Bridge::supports_network() ) {
				$undecryptable += count( Secrets_Bridge::find_undecryptable( true ) );
			}
		}

		$hygiene     = $available ? Hygiene_Scheduler::latest_snapshot() : array();
		$scan_count  = $available ? count( Plaintext_Scanner::scan() ) : 0;
		$dup_count   = $available ? count( Duplicate_Detector::find() ) : 0;
		$config_hits = count( Config_Constant_Scanner::scan() );
		$env_issues  = $available ? count( Environment_Guard::find_mismatches() ) : 0;

		return array(
			'api_available'          => $available,
			'writable'               => $writable,
			'provider'               => $available ? Secrets_Bridge::provider_label() : '',
			'site_secrets'           => $available ? count( Secrets_Bridge::list_vault_secrets( false ) ) : 0,
			'network_secrets'        => $available && Secrets_Bridge::supports_network() ? count( Secrets_Bridge::list_vault_secrets( true ) ) : 0,
			'undecryptable'          => $undecryptable,
			'overdue_rotate_by'      => $available ? count( Rotation_Reminders::overdue_secret_names() ) : 0,
			'plaintext_candidates'   => $scan_count,
			'duplicate_hygiene'      => $dup_count,
			'environment_mismatches' => $env_issues,
			'config_constant_hits'   => $config_hits,
			'hygiene_last_run'       => (int) ( $hygiene['last_run'] ?? 0 ),
			'hygiene_new_findings'   => (int) ( $hygiene['new_since_last'] ?? 0 ),
			'audit_near_capacity'    => self::audit_near_capacity(),
			'sudo_ttl_seconds'       => Vault_Settings::sudo_ttl_seconds(),
		);
	}

	public static function attention_count(): int {
		$s = self::summary();
		if ( ! $s['api_available'] ) {
			return 1;
		}

		$count = 0;
		$count += (int) $s['undecryptable'];
		$count += (int) $s['overdue_rotate_by'];
		$count += (int) $s['plaintext_candidates'];
		$count += (int) $s['duplicate_hygiene'];
		$count += (int) $s['environment_mismatches'];
		$count += (int) $s['hygiene_new_findings'];
		if ( ! empty( $s['audit_near_capacity'] ) ) {
			++$count;
		}

		return $count;
	}

	private static function audit_near_capacity(): bool {
		$log = get_option( Audit_Log::option_name(), array() );
		if ( ! is_array( $log ) ) {
			return false;
		}

		$max = (int) apply_filters( 'credential_vault_audit_max_entries', 500 );

		return count( $log ) >= (int) floor( $max * 0.9 );
	}
}
