<?php
/**
 * Pro plugin orchestrator.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Secrets_Bridge;

/**
 * Boots pro features when the free plugin is available.
 */
final class Plugin {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::is_free_active() ) {
			deactivate_plugins( CREDENTIAL_VAULT_PRO_BASENAME );
			wp_die(
				esc_html__( 'Credential Vault Pro requires the free Credential Vault plugin.', 'credential-vault-pro' ),
				esc_html__( 'Plugin dependency check', 'credential-vault-pro' ),
				array( 'back_link' => true )
			);
		}
	}

	public static function init(): void {
		if ( ! self::is_free_active() ) {
			add_action( 'admin_notices', array( self::class, 'render_missing_free_notice' ) );
			return;
		}

		add_action( 'credential_vault_loaded', array( self::class, 'register_pro_features' ) );

		if ( did_action( 'credential_vault_loaded' ) ) {
			self::register_pro_features();
		}
	}

	public static function register_pro_features(): void {
		Rest_Controller::register();
		Sudo_Audit_Logger::register();
		Sudo_Security_Validator::register();
		Sudo_Vault_Experience::register();
		Sudo_Audit_Admin::register();
		Audit_Pro::register();
		Webhooks::register();

		add_filter(
			'credential_vault_admin_context',
			static function ( array $context ): array {
				if ( Secrets_Bridge::is_available() ) {
					$context['external_secrets'] = Secrets_Bridge::list_external_secrets( false );
				}
				$context['pro_active'] = true;
				return $context;
			}
		);
	}

	public static function render_missing_free_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		esc_html_e( 'Credential Vault Pro requires the free Credential Vault plugin.', 'credential-vault-pro' );
		echo '</p></div>';
	}

	private static function is_free_active(): bool {
		return defined( 'CREDENTIAL_VAULT_VERSION' );
	}
}
