<?php
/**
 * SSO / 2FA integration hooks before vault sudo (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use WP_Error;

/**
 * Optional requirement for a 2FA plugin session before sudo.
 */
final class Sso_Gate {

	public static function register(): void {
		add_filter( 'credential_vault_before_sudo_establish', array( self::class, 'enforce_2fa' ), 10, 3 );
		add_action( 'admin_post_credential_vault_pro_save_sso_settings', array( self::class, 'handle_save' ) );
	}

	public static function require_2fa(): bool {
		return (bool) get_option( 'credential_vault_pro_require_2fa_for_sudo', false );
	}

	/**
	 * @param true|WP_Error $gate
	 * @param \WP_User        $user
	 * @param string          $password
	 * @return true|WP_Error
	 */
	public static function enforce_2fa( $gate, $user, string $password ) {
		unset( $password );
		if ( is_wp_error( $gate ) || true !== $gate || ! self::require_2fa() ) {
			return $gate;
		}

		/**
		 * Return true when the user has satisfied IdP / 2FA requirements.
		 *
		 * @param bool     $verified Default false when enforcement is on.
		 * @param \WP_User $user     Current user.
		 */
		$verified = (bool) apply_filters( 'credential_vault_pro_2fa_verified', false, $user );

		if ( $verified ) {
			return true;
		}

		return new WP_Error(
			'credential_vault_2fa_required',
			__( 'Complete two-factor authentication before starting a sudo session.', 'credential-vault-pro' )
		);
	}

	public static function handle_save(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_sso_settings' );

		$require = ! empty( $_POST['require_2fa_for_sudo'] );
		update_option( 'credential_vault_pro_require_2fa_for_sudo', $require, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'sso_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
