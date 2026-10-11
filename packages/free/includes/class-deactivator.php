<?php
/**
 * Deactivation logic.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Runs on plugin deactivation.
 */
final class Deactivator {

	/**
	 * Fired when the plugin is deactivated.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
