<?php
/**
 * Activation logic.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Runs on plugin activation.
 */
final class Activator {

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::migrate_legacy_options();

		if ( false === get_option( 'credential_vault_options' ) ) {
			add_option( 'credential_vault_options', Vault_Settings::defaults() );
		}

		if ( false === get_option( 'credential_vault_audit_log' ) ) {
			add_option( 'credential_vault_audit_log', array(), false );
		}

		if ( false === get_option( 'credential_vault_credential_meta' ) ) {
			add_option( 'credential_vault_credential_meta', array(), false );
		}

		Vault_Capabilities::grant_caps_on_activate();
		Hygiene_Scheduler::schedule();

		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			if ( ! $admin->has_cap( 'manage_secrets' ) ) {
				$admin->add_cap( 'manage_secrets' );
			}
			if ( ! $admin->has_cap( Secrets_Bridge::VAULT_CAP ) ) {
				$admin->add_cap( Secrets_Bridge::VAULT_CAP );
			}
		}

		flush_rewrite_rules();
	}

	private static function migrate_legacy_options(): void {
		$map = array(
			'new_plugin_options'        => 'credential_vault_options',
			'new_vault_audit_log'       => 'credential_vault_audit_log',
			'new_vault_credential_meta' => 'credential_vault_credential_meta',
		);

		foreach ( $map as $old => $new ) {
			$value = get_option( $old, null );
			if ( null !== $value && false === get_option( $new, false ) ) {
				add_option( $new, $value, false );
			}
		}
	}
}
