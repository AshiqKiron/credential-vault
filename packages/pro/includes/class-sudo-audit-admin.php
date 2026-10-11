<?php
/**
 * Renders the WP Sudo audit log on the vault Audit tab (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Admin output for sudo-gated action audit entries.
 */
final class Sudo_Audit_Admin {

	public static function register(): void {
		add_action( 'credential_vault_after_audit_log', array( self::class, 'render_sudo_audit_section' ) );
	}

	public static function render_sudo_audit_section(): void {
		if ( ! function_exists( 'wp_sudo_check' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$entries = Sudo_Audit_Logger::entries( 100 );

		include CREDENTIAL_VAULT_PRO_DIR . 'admin/views/sudo-audit-log.php';
	}
}
