<?php
/**
 * Health, migration, and extended admin tabs.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Auditor_Experience;
use CredentialVault\Config_Constant_Scanner;
use CredentialVault\Environment_Guard;
use CredentialVault\Block_Scanner;
use CredentialVault\Secret_Health_Scores;
use CredentialVault\Secret_Timeline;
use CredentialVault\Vault_Health;

/**
 * Registers supplemental vault admin tabs.
 */
final class Features_Admin {

	public static function register(): void {
		add_filter( 'credential_vault_admin_tabs', array( self::class, 'register_tabs' ) );
		add_action( 'credential_vault_render_admin_tab', array( self::class, 'render_tab' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function register_tabs( array $tabs ): array {
		$tabs['health']  = __( 'Health', 'credvault' );
		$tabs['migrate'] = __( 'Migration', 'credvault' );

		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function render_tab( string $tab, array $context ): void {
		if ( ! Auditor_Experience::tab_allowed( $tab ) ) {
			wp_die( esc_html__( 'This tab is not available in auditor mode.', 'credvault' ) );
		}

		if ( 'health' === $tab ) {
			$health_summary   = Vault_Health::summary();
			$env_mismatches   = Environment_Guard::find_mismatches();
			$config_constants = Config_Constant_Scanner::scan();
			$health_scores    = Secret_Health_Scores::all_site();
			$block_hits       = Block_Scanner::scan( 50 );
			$timeline_secret  = isset( $_GET['cv_timeline'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['cv_timeline'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$timeline         = '' !== $timeline_secret ? Secret_Timeline::for_secret( $timeline_secret, 30 ) : array();
			include CREDENTIAL_VAULT_DIR . 'admin/views/vault-health.php';
			return;
		}

		if ( 'migrate' === $tab ) {
			if ( empty( $context['can_manage'] ) ) {
				echo '<p>' . esc_html__( 'Migration tools require manage access.', 'credvault' ) . '</p>';
				return;
			}
			$api_available = ! empty( $context['api_available'] );
			$writable      = ! empty( $context['writable'] );
			$sudo_active   = ! empty( $context['sudo_active'] );
			include CREDENTIAL_VAULT_DIR . 'admin/views/vault-migrate.php';
		}
	}
}
