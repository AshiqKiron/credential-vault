<?php
/**
 * Extra validation and fingerprint checks for WP Sudo sessions (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Records session fingerprints and flags suspicious sudo activity in the audit log.
 */
final class Sudo_Security_Validator {

	private const USER_META_FINGERPRINT = 'credential_vault_pro_sudo_fingerprint';

	public static function register(): void {
		if ( ! function_exists( 'wp_sudo_check' ) ) {
			return;
		}

		add_action( 'wp_sudo_activated', array( self::class, 'store_fingerprint' ), 20, 1 );
		add_action( 'wp_sudo_action_passed', array( self::class, 'validate_passed_action' ), 10, 3 );
		add_filter( 'credential_vault_pro_sudo_extra_validate', array( self::class, 'default_validate' ), 10, 2 );
	}

	public static function store_fingerprint( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::USER_META_FINGERPRINT, self::fingerprint() );
	}

	public static function validate_passed_action( int $user_id, string $rule_id, string $surface ): void {
		$context = array(
			'user_id'  => $user_id,
			'rule_id'  => $rule_id,
			'surface'  => $surface,
			'valid'    => true,
			'messages' => array(),
		);

		$stored = (string) get_user_meta( $user_id, self::USER_META_FINGERPRINT, true );
		if ( '' !== $stored && $stored !== self::fingerprint() ) {
			$context['messages'][] = 'client_fingerprint_mismatch';
			Sudo_Audit_Logger::log_event(
				'security_fingerprint_mismatch',
				$user_id,
				$rule_id,
				$surface,
				array(
					'stored_prefix' => substr( $stored, 0, 12 ),
					'current_prefix' => substr( self::fingerprint(), 0, 12 ),
				)
			);
		}

		/**
		 * Allow extensions to run extra validation when a gated action succeeds.
		 *
		 * @param array<string, mixed> $context Includes valid (bool) and messages (list<string>).
		 */
		$context = apply_filters( 'credential_vault_pro_sudo_extra_validate', $context, $rule_id );

		if ( empty( $context['valid'] ) ) {
			Sudo_Audit_Logger::log_event(
				'security_validation_failed',
				$user_id,
				$rule_id,
				$surface,
				array(
					'messages' => $context['messages'] ?? array(),
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $context Validation context.
	 * @return array<string, mixed>
	 */
	public static function default_validate( array $context, string $rule_id ): array {
		unset( $rule_id );

		return $context;
	}

	private static function fingerprint(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

		return hash( 'sha256', $ip . '|' . $ua );
	}
}
