<?php
/**
 * Admin area functionality.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Audit_Log;
use CredentialVault\Import_Candidates;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Sudo_Gate;

/**
 * Admin hooks and screens.
 */
final class Admin {

	public function register_menu(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			return;
		}

		add_menu_page(
			__( 'Credential Vault', 'credvault' ),
			__( 'Credential Vault', 'credvault' ),
			'manage_options',
			'credential-vault',
			array( $this, 'render_vault_page' ),
			'dashicons-lock',
			80
		);
	}

	public function vault_admin_notices(): void {
		if ( ! isset( $_GET['page'] ) || 'credential-vault' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( isset( $_GET['vault_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message = sanitize_text_field( wp_unslash( (string) $_GET['vault_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
		}

		if ( ! isset( $_GET['vault_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$code = sanitize_key( (string) wp_unslash( $_GET['vault_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'sudo'       => __( 'Vault unlocked. Sensitive actions are available for a limited time.', 'credvault' ),
			'sudo_end'   => __( 'Sudo session ended.', 'credvault' ),
			'saved'      => __( 'Credential saved.', 'credvault' ),
			'deleted'    => __( 'Credential deleted.', 'credvault' ),
			'retired'    => __( 'Previous rotation slot cleared.', 'credvault' ),
			'copy_ready' => __( 'Credential ready to copy — use the prompt if auto-copy did not run.', 'credvault' ),
			'imported'   => __( 'Import completed.', 'credvault' ),
		);

		if ( 'imported' === $code && isset( $_GET['count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages['imported'] = sprintf(
				/* translators: %d: number of secrets imported */
				_n( '%d secret imported.', '%d secrets imported.', $count, 'credvault' ),
				$count
			);
		}

		if ( isset( $messages[ $code ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $code ] )
			);
		}
	}

	public function render_vault_page(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'credvault' ) );
		}

		$api_available = Secrets_Bridge::is_available();

		$context = array(
			'api_available'       => $api_available,
			'sudo_active'         => Sudo_Gate::is_active(),
			'sudo_remaining'      => Sudo_Gate::remaining_seconds(),
			'writable'            => $api_available && function_exists( 'wp_secrets_provider_is_writable' ) && wp_secrets_provider_is_writable(),
			'network_available'   => Secrets_Bridge::supports_network(),
			'provider_label'      => Secrets_Bridge::provider_label(),
			'credentials'         => $api_available ? Secrets_Bridge::list_vault_secrets( false ) : array(),
			'network_credentials' => $api_available ? Secrets_Bridge::list_vault_secrets( true ) : array(),
			'external_secrets'    => array(),
			'audit_entries'       => Audit_Log::entries( 100 ),
			'import_candidates'   => Import_Candidates::detect(),
			'pro_active'          => defined( 'CREDENTIAL_VAULT_PRO_VERSION' ),
		);

		/**
		 * Filter data passed to the vault dashboard template.
		 *
		 * @param array<string, mixed> $context Template variables.
		 */
		$context = apply_filters( 'credential_vault_admin_context', $context );

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $context, EXTR_SKIP );

		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-dashboard.php';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_credential-vault' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'credential-vault-admin',
			CREDENTIAL_VAULT_URL . 'admin/css/admin.css',
			array(),
			CREDENTIAL_VAULT_VERSION
		);

		wp_enqueue_script(
			'credential-vault-admin',
			CREDENTIAL_VAULT_URL . 'admin/js/admin.js',
			array(),
			CREDENTIAL_VAULT_VERSION,
			true
		);

		if ( Sudo_Gate::is_active()
			&& isset( $_GET['vault_notice'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'copy_ready' === sanitize_key( (string) wp_unslash( $_GET['vault_notice'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			$key = 'credential_vault_clipboard_' . get_current_user_id();
			$val = get_transient( $key );
			if ( is_string( $val ) && '' !== $val ) {
				wp_localize_script(
					'credential-vault-admin',
					'credentialVault',
					array(
						'clipboard' => $val,
					)
				);
				delete_transient( $key );
			}
		}
	}
}
