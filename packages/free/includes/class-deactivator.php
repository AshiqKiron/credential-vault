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
		Hygiene_Scheduler::unschedule();
		flush_rewrite_rules();
	}
}
