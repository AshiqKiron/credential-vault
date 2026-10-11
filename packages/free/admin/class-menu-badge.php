<?php
/**
 * Admin menu badge for vault attention items.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Secrets_Bridge;
use CredentialVault\Vault_Health;

/**
 * Adds a count bubble to the Credential Vault menu item.
 */
final class Menu_Badge {

	public static function register( \CredentialVault\Loader $loader ): void {
		$loader->add_action( 'admin_menu', self::class, 'badge', 999 );
	}

	public static function badge(): void {
		global $menu;

		if ( ! is_array( $menu ) || ! Secrets_Bridge::current_user_can_view() ) {
			return;
		}

		$count = Vault_Health::attention_count();
		if ( $count <= 0 ) {
			return;
		}

		foreach ( $menu as $index => $item ) {
			if ( ! isset( $item[2] ) || 'credential-vault' !== $item[2] ) {
				continue;
			}

			$menu[ $index ][0] .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				min( 99, $count )
			);
			break;
		}
	}
}
