<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'credential_vault_options' );
delete_option( 'credential_vault_audit_log' );
delete_option( 'credential_vault_credential_meta' );
