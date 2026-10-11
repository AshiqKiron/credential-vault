<?php
/**
 * Admin area functionality.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Audit_Log;
use CredentialVault\Demo_Data;
use CredentialVault\Import_Candidates;
use CredentialVault\Plaintext_Scanner;
use CredentialVault\Duplicate_Detector;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Sudo_Gate;
use CredentialVault\Vault_Capabilities;
use CredentialVault\Audit_Activity;
use CredentialVault\Auditor_Experience;
use CredentialVault\Credentials_Filter;
use CredentialVault\Vault_Health;
use CredentialVault\Vault_Settings;

/**
 * Admin hooks and screens.
 */
final class Admin {

	public function register_menu(): void {
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			return;
		}

		add_menu_page(
			__( 'Credential Vault', 'credvault' ),
			__( 'Credential Vault', 'credvault' ),
			Vault_Capabilities::VIEW_CAP,
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
			'rotated'    => __( 'Credential updated. Previous key kept in the rotation slot until you retire it.', 'credvault' ),
			'rotate_warn' => __( 'Credential saved, but the post-rotation test reported a problem. Confirm integrations before retiring the previous slot.', 'credvault' ),
			'copy_ready' => __( 'Credential ready to copy — use the prompt if auto-copy did not run.', 'credvault' ),
			'imported'   => __( 'Import completed.', 'credvault' ),
			'meta_saved'      => __( 'Credential details updated.', 'credvault' ),
			'webhooks_saved'  => __( 'Webhook settings saved.', 'credvault' ),
			'settings_saved'  => __( 'Vault settings saved.', 'credvault' ),
			'demo_installed'  => __( 'Demo credentials installed.', 'credvault' ),
			'demo_removed'    => __( 'Demo credentials removed.', 'credvault' ),
		);

		if ( 'imported' === $code && isset( $_GET['count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages['imported'] = sprintf(
				/* translators: %d: number of secrets imported */
				_n( '%d secret imported.', '%d secrets imported.', $count, 'credvault' ),
				$count
			);
		}

		if ( 'demo_installed' === $code && isset( $_GET['count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count   = (int) $_GET['count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages['demo_installed'] = sprintf(
				/* translators: 1: number installed, 2: number skipped because slug already existed */
				__( 'Demo data: %1$d credential(s) added, %2$d already present (left unchanged).', 'credvault' ),
				$count,
				$skipped
			);
		}

		if ( 'demo_removed' === $code && isset( $_GET['count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['count']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages['demo_removed'] = sprintf(
				/* translators: %d: number of demo credentials removed */
				_n( '%d demo credential removed.', '%d demo credentials removed.', $count, 'credvault' ),
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
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'credvault' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		if ( ! Auditor_Experience::tab_allowed( $tab ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=credential-vault&tab=audit' ) );
			exit;
		}

		$can_manage    = Secrets_Bridge::current_user_can_manage();
		$api_available = Secrets_Bridge::is_available();

		$context = array(
			'can_manage'          => $can_manage,
			'api_available'       => $api_available,
			'sudo_active'         => Sudo_Gate::is_active(),
			'sudo_remaining'      => Sudo_Gate::remaining_seconds(),
			'sudo_mode'           => Vault_Settings::sudo_mode(),
			'vault_settings'      => Vault_Settings::get(),
			'writable'            => $can_manage && $api_available && function_exists( 'wp_secrets_provider_is_writable' ) && wp_secrets_provider_is_writable(),
			'network_available'   => Secrets_Bridge::supports_network(),
			'provider_label'      => Secrets_Bridge::provider_label(),
			'credentials'         => $api_available ? Secrets_Bridge::list_vault_secrets( false ) : array(),
			'network_credentials' => $api_available ? Secrets_Bridge::list_vault_secrets( true ) : array(),
			'external_secrets'    => $api_available ? Secrets_Bridge::list_external_secrets( false ) : array(),
			'audit_entries'       => Audit_Log::entries(
				(int) apply_filters( 'credential_vault_audit_page_limit', 100 ),
				self::audit_filters_from_request()
			),
			'import_candidates'   => Import_Candidates::detect(),
			'scan_results'        => $api_available ? Plaintext_Scanner::scan() : array(),
			'hygiene_issues'      => $api_available ? Duplicate_Detector::find() : array(),
			'audit_filters'       => self::audit_filters_from_request(),
			'pro_active'          => defined( 'CREDENTIAL_VAULT_PRO_VERSION' ),
			'demo_data_ui'        => Demo_Data::ui_enabled(),
			'demo_data_installed' => Demo_Data::is_installed(),
			'demo_data_count'     => count( Demo_Data::tracked_slugs() ),
			'vault_health'        => Vault_Health::summary(),
			'audit_activity'      => Audit_Activity::index_by_secret(),
			'auditor_only'        => Auditor_Experience::is_auditor_only(),
		);

		/**
		 * Filter data passed to the vault dashboard template.
		 *
		 * @param array<string, mixed> $context Template variables.
		 */
		$context = apply_filters( 'credential_vault_admin_context', $context );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_query = is_array( $_GET ) ? wp_unslash( $_GET ) : array();
		if ( is_array( $filter_query ) ) {
			$context['credentials']         = Credentials_Filter::apply( $context['credentials'], $filter_query );
			$context['network_credentials'] = Credentials_Filter::apply( $context['network_credentials'], $filter_query );
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $context, EXTR_SKIP );

		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-dashboard.php';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_credential-vault' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'credential-vault-admin',
			CREDENTIAL_VAULT_URL . 'admin/css/admin.css',
			array( 'dashicons' ),
			CREDENTIAL_VAULT_VERSION
		);

		wp_enqueue_script(
			'credential-vault-admin',
			CREDENTIAL_VAULT_URL . 'admin/js/admin.js',
			array(),
			CREDENTIAL_VAULT_VERSION,
			true
		);

		wp_localize_script(
			'credential-vault-admin',
			'credentialVaultConfig',
			array(
				'clipboardClearSeconds' => (int) apply_filters( 'credential_vault_clipboard_clear_seconds', 60 ),
			)
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
						'clipboard'       => $val,
						'clipboardNotice' => __( 'Credential copied. Clear your clipboard when finished.', 'credvault' ),
					)
				);
				delete_transient( $key );
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function audit_filters_from_request(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter UI.
		if ( ! isset( $_GET['page'] ) || 'credential-vault' !== $_GET['page'] ) {
			return array();
		}

		$filters = array();

		if ( isset( $_GET['audit_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters['action'] = sanitize_key( (string) wp_unslash( $_GET['audit_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['audit_secret'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters['secret'] = sanitize_text_field( (string) wp_unslash( $_GET['audit_secret'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_GET['audit_user'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters['user_id'] = (int) $_GET['audit_user']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return $filters;
	}
}
