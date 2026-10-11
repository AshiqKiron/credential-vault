<?php
/**
 * Developer tools admin tab.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Plugin_Adapters;
use CredentialVault\Usage_Map;

/**
 * Usage map, manifest docs, and validation UI.
 */
final class Developer_Admin {

	public static function register(): void {
		add_filter( 'credential_vault_admin_tabs', array( self::class, 'register_tab' ) );
		add_action( 'credential_vault_render_admin_tab', array( self::class, 'render_tab' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function register_tab( array $tabs ): array {
		$tabs['developer'] = __( 'Developer', 'credvault' );

		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function render_tab( string $tab, array $context ): void {
		if ( 'developer' !== $tab ) {
			return;
		}

		$usage_map = Usage_Map::build();
		$manifest_path = ABSPATH . 'credvault.manifest.json';
		$manifest_result = get_transient( 'credential_vault_manifest_result_' . get_current_user_id() );
		if ( is_array( $manifest_result ) ) {
			delete_transient( 'credential_vault_manifest_result_' . get_current_user_id() );
		} else {
			$manifest_result = null;
		}
		$plugin_adapters = Plugin_Adapters::catalog();

		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-developer.php';
	}
}
