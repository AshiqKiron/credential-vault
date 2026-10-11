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

		Activator::activate();
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
		Audit_Store::register();
		Api_Tokens::register();
		Secret_Acl::register();
		Approval_Queue::register();
		Sso_Gate::register();
		Rotation_Nudges::register();
		Rotation_Automation::register();
		Import_Jobs::register();
		Env_Sync::register();
		Plugin_Adapters::register();
		Health_Monitor::register();
		Anomaly_Monitor::register();
		Siem_Export::register();
		Compliance_Reports::register();
		License_Seats::register();
		Template_Credentials::register();
		Multisite_Network::register();
		Rest_Controller::register();
		Sudo_Audit_Logger::register();
		Sudo_Security_Validator::register();
		Sudo_Vault_Experience::register();
		Sudo_Audit_Admin::register();
		Audit_Pro::register();
		Webhooks::register();
		Manifest_Webhooks::register();
		Webhook_Delivery_Log::register();
		Rest_Idempotency::register();
		Jit_Access::register();
		Compromise_Playbook::register();
		Remote_Health::register();

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
