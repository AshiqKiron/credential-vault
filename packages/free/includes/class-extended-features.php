<?php
/**
 * Registers supplemental free-tier feature modules.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Boots feature classes from a single plugin hook.
 */
final class Extended_Features {

	public static function register( Loader $loader ): void {
		unset( $loader );
		Slug_Linter::register();
		Rotation_Calendar::register();
		Query_Source::register();
	}
}
