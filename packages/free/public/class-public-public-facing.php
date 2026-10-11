<?php
/**
 * Public-facing functionality.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Public;

/**
 * Front-end hooks.
 */
final class Public_Facing {

	/**
	 * Enqueue public styles and scripts.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'credential-vault-public',
			CREDENTIAL_VAULT_URL . 'public/css/public.css',
			array(),
			CREDENTIAL_VAULT_VERSION
		);

		wp_enqueue_script(
			'credential-vault-public',
			CREDENTIAL_VAULT_URL . 'public/js/public.js',
			array(),
			CREDENTIAL_VAULT_VERSION,
			true
		);
	}
}
