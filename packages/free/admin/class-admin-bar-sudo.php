<?php
/**
 * Admin bar indicator for active vault sudo sessions.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Secrets_Bridge;
use CredentialVault\Sudo_Gate;

/**
 * Shows sudo expiry on vault-related admin screens.
 */
final class Admin_Bar_Sudo {

	public static function register( \CredentialVault\Loader $loader ): void {
		$loader->add_action( 'admin_bar_menu', self::class, 'menu', 100 );
		$loader->add_action( 'admin_enqueue_scripts', self::class, 'enqueue_timer' );
	}

	/**
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public static function menu( $bar ): void {
		if ( ! is_admin() || ! Secrets_Bridge::current_user_can_manage() || ! Sudo_Gate::is_active() ) {
			return;
		}

		$remaining = Sudo_Gate::remaining_seconds();
		$bar->add_node(
			array(
				'id'    => 'credential-vault-sudo',
				'title' => sprintf(
					/* translators: %s: time remaining */
					__( 'Vault sudo: %s left', 'credvault' ),
					gmdate( 'i:s', $remaining )
				),
				'href'  => admin_url( 'admin.php?page=credential-vault' ),
				'meta'  => array(
					'class' => 'credential-vault-sudo-active',
				),
			)
		);
	}

	public static function enqueue_timer( string $hook ): void {
		unset( $hook );

		if ( ! is_admin() || ! Secrets_Bridge::current_user_can_manage() || ! Sudo_Gate::is_active() ) {
			return;
		}

		wp_enqueue_script( 'credential-vault-admin' );
		wp_localize_script(
			'credential-vault-admin',
			'credentialVaultSudo',
			array(
				'remaining' => Sudo_Gate::remaining_seconds(),
			)
		);
	}
}
