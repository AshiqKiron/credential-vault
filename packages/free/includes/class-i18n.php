<?php
/**
 * Internationalization.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

final class I18n {

	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'credvault',
			false,
			dirname( CREDENTIAL_VAULT_BASENAME ) . '/languages'
		);
	}
}
