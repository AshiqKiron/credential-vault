<?php
/**
 * Bridges vault sudo checks with the WP Sudo plugin (WordPress 7.2 sudo mode prototype).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Registers gated admin_post actions when WP Sudo is present.
 */
final class Sudo_Integration {

	public static function register( Loader $loader ): void {
		$loader->add_filter( 'wp_sudo_gated_actions', self::class, 'register_gated_actions' );
	}

	/**
	 * @param list<array<string, mixed>> $rules Existing WP Sudo rules.
	 * @return list<array<string, mixed>>
	 */
	public static function register_gated_actions( array $rules ): array {
		$actions = array(
			'credential_vault_sudo',
			'credential_vault_sudo_end',
			'credential_vault_save',
			'credential_vault_delete',
			'credential_vault_retire',
			'credential_vault_copy',
			'credential_vault_import',
		);

		$label = __( 'Credential Vault: view or change API keys', 'credvault' );

		/**
		 * Customize the action label shown on the WP Sudo challenge page for vault operations.
		 *
		 * @param string $label   Default label.
		 * @param string $rule_id Gated action rule ID.
		 */
		$label = (string) apply_filters( 'credential_vault_wp_sudo_gated_action_label', $label, 'credential_vault.manage' );

		$rules[] = array(
			'id'       => 'credential_vault.manage',
			'label'    => $label,
			'category' => 'security',
			'admin'    => array(
				'pagenow' => 'admin-post.php',
				'actions' => $actions,
				'method'  => 'POST',
			),
		);

		return $rules;
	}
}
