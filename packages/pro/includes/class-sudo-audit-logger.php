<?php
/**
 * Site-wide audit log for WP Sudo gated actions and session events.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Subscribes to WP Sudo audit hooks and stores enriched entries for the vault UI.
 */
final class Sudo_Audit_Logger {

	private const OPTION = 'credential_vault_pro_sudo_audit_log';

	private const MAX_ENTRIES = 1000;

	public static function register(): void {
		if ( ! function_exists( 'wp_sudo_check' ) ) {
			return;
		}

		$hooks = array(
			'wp_sudo_activated'             => array( self::class, 'on_activated', 10, 3 ),
			'wp_sudo_deactivated'           => array( self::class, 'on_deactivated', 10, 1 ),
			'wp_sudo_reauth_failed'           => array( self::class, 'on_reauth_failed', 10, 2 ),
			'wp_sudo_lockout'                 => array( self::class, 'on_lockout', 10, 3 ),
			'wp_sudo_action_gated'            => array( self::class, 'on_action_triplet', 10, 3 ),
			'wp_sudo_action_blocked'          => array( self::class, 'on_action_triplet', 10, 3 ),
			'wp_sudo_action_allowed'          => array( self::class, 'on_action_triplet', 10, 3 ),
			'wp_sudo_action_passed'           => array( self::class, 'on_action_triplet', 10, 3 ),
			'wp_sudo_escalation_blocked'      => array( self::class, 'on_action_triplet', 10, 3 ),
			'wp_sudo_replay_refused'          => array( self::class, 'on_replay_refused', 10, 3 ),
			'wp_sudo_recovery_mode_active'    => array( self::class, 'on_recovery_mode', 10, 1 ),
			'wp_sudo_session_revoked'         => array( self::class, 'on_session_revoked', 10, 4 ),
			'wp_sudo_capability_tampered'     => array( self::class, 'on_capability_tampered', 10, 2 ),
			'wp_sudo_role_drift_detected'     => array( self::class, 'on_role_drift', 10, 1 ),
		);

		foreach ( $hooks as $hook => $callback ) {
			add_action( $hook, $callback[0], $callback[1], $callback[2] );
		}
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
	 * @param array<string, mixed> $extra Optional context.
	 */
	public static function log_event( string $event, int $user_id = 0, string $rule_id = '', string $surface = '', array $extra = array() ): void {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		self::append(
			array_merge(
				array(
					'time'    => time(),
					'event'   => $event,
					'user_id' => $user_id,
					'rule_id' => $rule_id,
					'surface' => $surface,
					'ip'      => self::client_ip(),
					'ua'      => self::client_user_agent(),
				),
				$extra
			)
		);
	}

	public static function on_activated( int $user_id, int $expires, int $duration ): void {
		self::log_event(
			'session_activated',
			$user_id,
			'',
			'',
			array(
				'expires'  => $expires,
				'duration' => $duration,
			)
		);
	}

	public static function on_deactivated( int $user_id ): void {
		self::log_event( 'session_deactivated', $user_id );
	}

	public static function on_reauth_failed( int $user_id, int $attempts ): void {
		self::log_event(
			'reauth_failed',
			$user_id,
			'',
			'',
			array( 'attempts' => $attempts )
		);
	}

	public static function on_lockout( int $user_id, int $attempts, string $ip ): void {
		self::log_event(
			'lockout',
			$user_id,
			'',
			'',
			array(
				'attempts' => $attempts,
				'report_ip' => $ip,
			)
		);
	}

	public static function on_action_triplet( int $user_id, string $rule_id, string $surface ): void {
		$event = str_replace( 'wp_sudo_', '', (string) current_action() );
		self::log_event( $event, $user_id, $rule_id, $surface );
	}

	public static function on_replay_refused( int $user_id, string $rule_id, string $reason ): void {
		self::log_event(
			'replay_refused',
			$user_id,
			$rule_id,
			'',
			array( 'reason' => $reason )
		);
	}

	public static function on_recovery_mode( int $user_id ): void {
		self::log_event( 'recovery_mode_active', $user_id );
	}

	/**
	 * @param int    $target_user_id User whose session was revoked.
	 * @param int    $revoker_user_id Operator who revoked.
	 * @param string $reason          Revocation reason code.
	 * @param int    $site_id         Blog ID (multisite).
	 */
	public static function on_session_revoked( int $target_user_id, int $revoker_user_id, string $reason, int $site_id ): void {
		self::log_event(
			'session_revoked',
			$target_user_id,
			'',
			'',
			array(
				'revoker_user_id' => $revoker_user_id,
				'reason'          => $reason,
				'site_id'         => $site_id,
			)
		);
	}

	public static function on_capability_tampered( string $role, string $capability ): void {
		self::log_event(
			'capability_tampered',
			get_current_user_id(),
			'',
			'',
			array(
				'role'       => $role,
				'capability' => $capability,
			)
		);
	}

	/**
	 * @param mixed $report Role drift report from WP Sudo.
	 */
	public static function on_role_drift( $report ): void {
		self::log_event(
			'role_drift_detected',
			get_current_user_id(),
			'',
			'',
			array( 'report' => is_scalar( $report ) ? (string) $report : wp_json_encode( $report ) )
		);
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

	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

		return (string) apply_filters( 'credential_vault_pro_sudo_audit_ip', $ip );
	}

	private static function client_user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return substr( $ua, 0, 255 );
	}
}
