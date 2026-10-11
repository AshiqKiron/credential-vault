<?php
/**
 * Just-in-time reveal grants (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Secrets_Bridge;
use WP_Error;

/**
 * Time-boxed reveal permission for non-super-admins.
 */
final class Jit_Access {

	private const OPTION = 'credential_vault_pro_jit_grants';

	public static function register(): void {
		add_filter( 'credential_vault_user_can', array( self::class, 'filter_reveal' ), 20, 4 );
		add_action( 'admin_post_credential_vault_pro_save_jit_settings', array( self::class, 'handle_save_settings' ) );
		add_action( 'admin_post_credential_vault_pro_request_jit', array( self::class, 'handle_request' ) );
		add_action( 'admin_post_credential_vault_pro_approve_jit', array( self::class, 'handle_approve' ) );
		add_action( 'admin_post_credential_vault_pro_revoke_jit', array( self::class, 'handle_revoke' ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? array_values( $stored ) : array();
	}

	public static function enforcement_enabled(): bool {
		return (bool) get_option( 'credential_vault_pro_jit_enforcement', false );
	}

	public static function handle_save_settings(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_jit_settings' );

		update_option( 'credential_vault_pro_jit_enforcement', ! empty( $_POST['jit_enforcement'] ), false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'jit_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function active_grant_for_user( int $user_id, string $secret_name ): ?array {
		$now = time();
		foreach ( self::all() as $grant ) {
			if ( ! is_array( $grant ) ) {
				continue;
			}
			if ( (int) ( $grant['user_id'] ?? 0 ) !== $user_id ) {
				continue;
			}
			if ( (string) ( $grant['secret'] ?? '' ) !== $secret_name ) {
				continue;
			}
			if ( (int) ( $grant['expires'] ?? 0 ) < $now ) {
				continue;
			}
			if ( empty( $grant['approved'] ) ) {
				continue;
			}

			return $grant;
		}

		return null;
	}

	/**
	 * @param bool $allowed
	 */
	public static function filter_reveal( $allowed, string $action, string $secret_name, bool $network ): bool {
		unset( $network );
		if ( ! self::enforcement_enabled() || ! $allowed || 'reveal' !== $action || '' === $secret_name ) {
			return $allowed;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}

		return null !== self::active_grant_for_user( $user_id, $secret_name );
	}

	public static function handle_request(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to request access.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_request_jit' );

		$slug    = isset( $_POST['jit_slug'] ) ? sanitize_key( (string) wp_unslash( $_POST['jit_slug'] ) ) : '';
		$minutes = max( 5, min( 120, (int) ( $_POST['jit_minutes'] ?? 15 ) ) );
		$reason  = isset( $_POST['jit_reason'] ) ? sanitize_textarea_field( (string) wp_unslash( $_POST['jit_reason'] ) ) : '';

		if ( '' === $slug || '' === $reason ) {
			wp_die( esc_html__( 'Slug and reason are required.', 'credential-vault-pro' ) );
		}

		$name   = Secrets_Bridge::secret_name( $slug );
		$grants = self::all();
		$grants[] = array(
			'id'       => wp_generate_uuid4(),
			'user_id'  => get_current_user_id(),
			'secret'   => $name,
			'slug'     => $slug,
			'reason'   => $reason,
			'minutes'  => $minutes,
			'requested'=> time(),
			'approved' => current_user_can( 'manage_options' ) ? 1 : 0,
			'expires'  => time() + ( $minutes * MINUTE_IN_SECONDS ),
		);

		update_option( self::OPTION, $grants, false );

		Audit_Log::log_user_action(
			'jit_access_requested',
			$name,
			array(
				'minutes' => $minutes,
				'reason'  => $reason,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'jit_requested',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_approve(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only administrators can approve JIT access.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_approve_jit' );

		$id     = isset( $_POST['grant_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['grant_id'] ) ) : '';
		$grants = self::all();
		foreach ( $grants as &$grant ) {
			if ( is_array( $grant ) && (string) ( $grant['id'] ?? '' ) === $id ) {
				$grant['approved'] = 1;
				$minutes           = max( 5, (int) ( $grant['minutes'] ?? 15 ) );
				$grant['expires']  = time() + ( $minutes * MINUTE_IN_SECONDS );
				Audit_Log::log_user_action(
					'jit_access_approved',
					(string) ( $grant['secret'] ?? '' ),
					array( 'grant_id' => $id )
				);
				break;
			}
		}
		unset( $grant );

		update_option( self::OPTION, $grants, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'jit_approved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_revoke(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only administrators can revoke JIT access.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_revoke_jit' );

		$id     = isset( $_POST['grant_id'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['grant_id'] ) ) : '';
		$grants = array_values(
			array_filter(
				self::all(),
				static function ( $grant ) use ( $id ) {
					return is_array( $grant ) && (string) ( $grant['id'] ?? '' ) !== $id;
				}
			)
		);

		update_option( self::OPTION, $grants, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'jit_revoked',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
