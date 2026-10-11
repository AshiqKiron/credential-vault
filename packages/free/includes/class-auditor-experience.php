<?php
/**
 * Read-only auditor role UX (audit + hygiene without secret reveal).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Restricts vault tabs and surfaces for credvault_auditor users.
 */
final class Auditor_Experience {

	/**
	 * @return list<string> Tab slugs auditors may open.
	 */
	public static function allowed_tabs(): array {
		$tabs = array( 'dashboard', 'audit', 'health', 'developer' );

		/**
		 * @param list<string> $tabs
		 */
		return apply_filters( 'credential_vault_auditor_tabs', $tabs );
	}

	public static function is_auditor_only(): bool {
		if ( Secrets_Bridge::current_user_can_manage() ) {
			return false;
		}

		return current_user_can( Vault_Capabilities::VIEW_CAP );
	}

	public static function register( Loader $loader ): void {
		$loader->add_filter( 'credential_vault_admin_context', self::class, 'context' );
		$loader->add_filter( 'credential_vault_admin_tabs', self::class, 'filter_tabs', 100 );
		$loader->add_action( 'admin_notices', self::class, 'auditor_banner' );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function filter_tabs( array $tabs ): array {
		if ( ! self::is_auditor_only() ) {
			return $tabs;
		}

		unset( $tabs['migrate'] );

		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function context( array $context ): array {
		$context['auditor_only'] = self::is_auditor_only();

		return $context;
	}

	public static function auditor_banner(): void {
		if ( ! self::is_auditor_only() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'credential-vault' !== $page ) {
			return;
		}

		echo '<div class="notice notice-info"><p>' . esc_html__( 'Auditor mode: you can review audit and health data but cannot reveal, copy, or edit secrets.', 'credvault' ) . '</p></div>';
	}

	public static function tab_allowed( string $tab ): bool {
		if ( ! self::is_auditor_only() ) {
			return true;
		}

		return in_array( $tab, self::allowed_tabs(), true );
	}
}
