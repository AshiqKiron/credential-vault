<?php
/**
 * Form handlers for the credential vault.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Audit_Log;
use CredentialVault\Credential_Meta;
use CredentialVault\Demo_Data;
use CredentialVault\Import_Candidates;
use CredentialVault\Plaintext_Scanner;
use CredentialVault\Rotation_Workflow;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Onboarding;
use CredentialVault\Secrets_Manifest;
use CredentialVault\Sudo_Gate;
use CredentialVault\Vault_Bundle;
use CredentialVault\Vault_Settings;

/**
 * admin_post_* handlers for vault mutations.
 */
final class Vault_Actions {

	public static function register( \CredentialVault\Loader $loader ): void {
		$loader->add_action( 'admin_post_credential_vault_sudo', self::class, 'handle_sudo' );
		$loader->add_action( 'admin_post_credential_vault_sudo_end', self::class, 'handle_sudo_end' );
		$loader->add_action( 'admin_post_credential_vault_save', self::class, 'handle_save' );
		$loader->add_action( 'admin_post_credential_vault_delete', self::class, 'handle_delete' );
		$loader->add_action( 'admin_post_credential_vault_retire', self::class, 'handle_retire' );
		$loader->add_action( 'admin_post_credential_vault_copy', self::class, 'handle_copy' );
		$loader->add_action( 'admin_post_credential_vault_import', self::class, 'handle_import' );
		$loader->add_action( 'admin_post_credential_vault_scan_import', self::class, 'handle_scan_import' );
		$loader->add_action( 'admin_post_credential_vault_update_meta', self::class, 'handle_update_meta' );
		$loader->add_action( 'admin_post_credential_vault_export_audit', self::class, 'handle_export_audit' );
		$loader->add_action( 'admin_post_credential_vault_export_metadata', self::class, 'handle_export_metadata' );
		$loader->add_action( 'admin_post_credential_vault_save_settings', self::class, 'handle_save_settings' );
		$loader->add_action( 'admin_post_credential_vault_install_demo', self::class, 'handle_install_demo' );
		$loader->add_action( 'admin_post_credential_vault_remove_demo', self::class, 'handle_remove_demo' );
		$loader->add_action( 'admin_post_credential_vault_bundle_export', self::class, 'handle_bundle_export' );
		$loader->add_action( 'admin_post_credential_vault_bundle_import', self::class, 'handle_bundle_import' );
		$loader->add_action( 'admin_post_credential_vault_bulk_meta', self::class, 'handle_bulk_meta' );
		$loader->add_action( 'admin_post_credential_vault_onboarding_done', self::class, 'handle_onboarding_done' );
		$loader->add_action( 'admin_post_credential_vault_validate_manifest', self::class, 'handle_validate_manifest' );
	}

	private static function is_network_scope(): bool {
		return isset( $_POST['credential_scope'] ) && 'network' === sanitize_key( (string) wp_unslash( $_POST['credential_scope'] ) );
	}

	private static function redirect_tab( string $query, string $tab = 'credentials' ): void {
		if ( 'network' === $tab ) {
			$query = '' === $query ? 'tab=network' : $query . '&tab=network';
		} elseif ( 'import' === $tab ) {
			$query = '' === $query ? 'tab=import' : $query . '&tab=import';
		} elseif ( 'audit' === $tab ) {
			$query = '' === $query ? 'tab=audit' : $query . '&tab=audit';
		} elseif ( 'health' === $tab ) {
			$query = '' === $query ? 'tab=health' : $query . '&tab=health';
		} elseif ( 'migrate' === $tab ) {
			$query = '' === $query ? 'tab=migrate' : $query . '&tab=migrate';
		} elseif ( 'developer' === $tab ) {
			$query = '' === $query ? 'tab=developer' : $query . '&tab=developer';
		}
		self::redirect( $query );
	}

	private static function redirect( string $query = '' ): void {
		$url = admin_url( 'admin.php?page=credential-vault' );

		if ( '' !== $query ) {
			$url = add_query_arg( $query, '', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	private static function verify_request( string $action ): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credvault' ) );
		}

		check_admin_referer( $action );
	}

	private static function verify_view_request( string $action ): void {
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to view the credential vault.', 'credvault' ) );
		}

		check_admin_referer( $action );
	}

	private static function require_sudo_for_writes(): ?\WP_Error {
		if ( ! Vault_Settings::sudo_required_for_writes() ) {
			return null;
		}

		$sudo = Sudo_Gate::require_active();

		return is_wp_error( $sudo ) ? $sudo : null;
	}

	public static function handle_sudo(): void {
		self::verify_request( 'credential_vault_sudo' );

		$password = isset( $_POST['credential_vault_password'] )
			? (string) wp_unslash( $_POST['credential_vault_password'] )
			: '';

		$result = Sudo_Gate::establish( $password );

		if ( is_wp_error( $result ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $result->get_error_message() ) );
		}

		Audit_Log::log_user_action( 'sudo_started' );
		self::redirect( 'vault_notice=sudo' );
	}

	public static function handle_sudo_end(): void {
		self::verify_request( 'credential_vault_sudo_end' );

		Sudo_Gate::revoke();
		Audit_Log::log_user_action( 'sudo_ended' );
		self::redirect( 'vault_notice=sudo_end' );
	}

	public static function handle_save(): void {
		self::verify_request( 'credential_vault_save' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$slug    = isset( $_POST['credential_slug'] ) ? sanitize_key( (string) wp_unslash( $_POST['credential_slug'] ) ) : '';
		$label   = isset( $_POST['credential_label'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['credential_label'] ) ) : '';
		$service = isset( $_POST['credential_service'] ) ? sanitize_key( (string) wp_unslash( $_POST['credential_service'] ) ) : 'custom';
		$value   = isset( $_POST['credential_value'] ) ? (string) wp_unslash( $_POST['credential_value'] ) : '';

		if ( '' === $slug || '' === $value ) {
			self::redirect( 'vault_error=' . rawurlencode( __( 'Slug and credential value are required.', 'credvault' ) ) );
		}

		$network = self::is_network_scope();

		if ( $network && ! Secrets_Bridge::current_user_can_manage( true ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'You cannot manage network credentials.', 'credvault' ) ), 'network' );
		}

		$name = Secrets_Bridge::secret_name( $slug );

		$existing = $network
			? Secrets_Bridge::get_network_secret( $name )
			: Secrets_Bridge::get_secret( $name );
		$is_rotation = ! is_wp_error( $existing ) && null !== $existing;

		$result = Secrets_Bridge::set_secret( $name, $value, $network );

		if ( is_wp_error( $result ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $result->get_error_message() ) );
		}

		if ( '' === $label ) {
			$label = $slug;
		}

		Credential_Meta::save(
			$name,
			$label,
			$service,
			Secrets_Bridge::masked_preview( $value ),
			self::posted_notes(),
			self::posted_environment(),
			self::posted_rotate_by(),
			self::posted_owner(),
			self::posted_runbook_url(),
			self::posted_public_fields( $service )
		);

		Secrets_Bridge::memzero( $value );

		if ( $is_rotation ) {
			$test = Rotation_Workflow::run_post_save_test( $name, $network );
			if ( is_wp_error( $test ) ) {
				/**
				 * Fires when a post-rotation smoke test fails (Pro webhooks/alerts).
				 *
				 * @param string    $name    Fully qualified secret name.
				 * @param bool      $network Network scope.
				 * @param \WP_Error $test    Test failure.
				 */
				do_action( 'credential_vault_rotation_test_failed', $name, $network, $test );

				self::redirect_tab(
					'vault_notice=rotate_warn&vault_error=' . rawurlencode( $test->get_error_message() ),
					$network ? 'network' : 'credentials'
				);
			}

			self::redirect_tab( 'vault_notice=rotated', $network ? 'network' : 'credentials' );
		}

		self::redirect_tab( 'vault_notice=saved', $network ? 'network' : 'credentials' );
	}

	public static function handle_delete(): void {
		self::verify_request( 'credential_vault_delete' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$name = isset( $_POST['secret_name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secret_name'] ) ) : '';

		if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			self::redirect( 'vault_error=' . rawurlencode( __( 'Invalid credential.', 'credvault' ) ) );
		}

		$network = self::is_network_scope();

		$result = Secrets_Bridge::delete_secret( $name, $network );

		if ( is_wp_error( $result ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $result->get_error_message() ), $network ? 'network' : 'credentials' );
		}

		Credential_Meta::delete( $name );
		self::redirect_tab( 'vault_notice=deleted', $network ? 'network' : 'credentials' );
	}

	public static function handle_retire(): void {
		self::verify_request( 'credential_vault_retire' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$name = isset( $_POST['secret_name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secret_name'] ) ) : '';

		if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			self::redirect( 'vault_error=' . rawurlencode( __( 'Invalid credential.', 'credvault' ) ) );
		}

		$network = self::is_network_scope();

		$result = Secrets_Bridge::retire_previous( $name, $network );

		if ( is_wp_error( $result ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $result->get_error_message() ), $network ? 'network' : 'credentials' );
		}

		self::redirect_tab( 'vault_notice=retired', $network ? 'network' : 'credentials' );
	}

	public static function handle_copy(): void {
		self::verify_request( 'credential_vault_copy' );

		$sudo = Sudo_Gate::require_active();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$name = isset( $_POST['secret_name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secret_name'] ) ) : '';

		if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			self::redirect( 'vault_error=' . rawurlencode( __( 'Invalid credential.', 'credvault' ) ) );
		}

		$network = self::is_network_scope();

		$secret = $network ? Secrets_Bridge::get_network_secret( $name ) : Secrets_Bridge::get_secret( $name );

		if ( is_wp_error( $secret ) || null === $secret ) {
			self::redirect( 'vault_error=' . rawurlencode( __( 'Credential could not be loaded.', 'credvault' ) ) );
		}

		$plaintext = $secret->reveal();

		if ( is_wp_error( $plaintext ) ) {
			self::redirect( 'vault_error=' . rawurlencode( $plaintext->get_error_message() ) );
		}

		Audit_Log::log_user_action(
			'copied',
			$name,
			array(
				'source' => 'admin',
			)
		);

		set_transient(
			'credential_vault_clipboard_' . get_current_user_id(),
			$plaintext,
			60
		);

		Secrets_Bridge::memzero( $plaintext );

		self::redirect_tab( 'vault_notice=copy_ready&secret=' . rawurlencode( $name ), $network ? 'network' : 'credentials' );
	}

	public static function handle_import(): void {
		self::verify_request( 'credential_vault_import' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'import' );
		}

		$imports = isset( $_POST['import'] ) && is_array( $_POST['import'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['import'] ) )
			: array();

		if ( empty( $imports ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Select at least one option to import.', 'credvault' ) ), 'import' );
		}

		$map = self::import_option_map();

		$errors = 0;
		$ok     = 0;

		foreach ( $imports as $option ) {
			$import_key  = Plaintext_Scanner::option_import_key( $option );
			$secret_name = $map[ $import_key ] ?? '';
			if ( '' === $secret_name ) {
				continue;
			}

			$result = Secrets_Bridge::import_from_source( $import_key, $secret_name );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$ok;
				Audit_Log::log_user_action(
					'imported',
					$secret_name,
					array( 'option' => $option )
				);
			}
		}

		if ( 0 === $ok ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Import failed for all selected options.', 'credvault' ) ), 'import' );
		}

		self::redirect_tab( 'vault_notice=imported&count=' . $ok . ( $errors ? '&failed=' . $errors : '' ), 'import' );
	}

	public static function handle_scan_import(): void {
		self::verify_request( 'credential_vault_scan_import' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'import' );
		}

		$imports = isset( $_POST['scan_import'] ) && is_array( $_POST['scan_import'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['scan_import'] ) )
			: array();

		if ( empty( $imports ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Select at least one scan result to import.', 'credvault' ) ), 'import' );
		}

		$map = self::import_option_map();
		$ok  = 0;
		$errors = 0;

		foreach ( $imports as $import_key ) {
			$secret_name = $map[ $import_key ] ?? '';
			if ( '' === $secret_name ) {
				continue;
			}

			$result = Secrets_Bridge::import_from_source( $import_key, $secret_name );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$ok;
				Audit_Log::log_user_action(
					'imported',
					$secret_name,
					array(
						'source' => 'scanner',
						'ref'    => $import_key,
					)
				);
			}
		}

		if ( 0 === $ok ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Import failed for all selected scan results.', 'credvault' ) ), 'import' );
		}

		self::redirect_tab( 'vault_notice=imported&count=' . $ok . ( $errors ? '&failed=' . $errors : '' ), 'import' );
	}

	public static function handle_update_meta(): void {
		self::verify_request( 'credential_vault_update_meta' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'credentials' );
		}

		$name = isset( $_POST['secret_name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secret_name'] ) ) : '';

		if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Invalid credential.', 'credvault' ) ), 'credentials' );
		}

		$label = isset( $_POST['credential_label'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['credential_label'] ) ) : '';
		$network = self::is_network_scope();

		$meta_row = Credential_Meta::get( $name );
		$service  = is_array( $meta_row ) ? (string) ( $meta_row['service'] ?? 'custom' ) : 'custom';

		Credential_Meta::update_details(
			$name,
			$label,
			self::posted_notes(),
			self::posted_environment(),
			self::posted_rotate_by(),
			self::posted_owner(),
			self::posted_runbook_url(),
			self::posted_public_fields( $service )
		);

		self::redirect_tab( 'vault_notice=meta_saved', $network ? 'network' : 'credentials' );
	}

	public static function handle_export_audit(): void {
		self::verify_view_request( 'credential_vault_export_audit' );

		$filters = array();
		if ( isset( $_POST['audit_action'] ) ) {
			$filters['action'] = sanitize_key( (string) wp_unslash( $_POST['audit_action'] ) );
		}
		if ( isset( $_POST['audit_secret'] ) ) {
			$filters['secret'] = sanitize_text_field( (string) wp_unslash( $_POST['audit_secret'] ) );
		}
		if ( isset( $_POST['audit_user'] ) ) {
			$filters['user_id'] = (int) $_POST['audit_user'];
		}

		$filename = 'credential-vault-audit-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		Audit_Log::export_csv( $filters );
		exit;
	}

	public static function handle_export_metadata(): void {
		self::verify_view_request( 'credential_vault_export_metadata' );

		$payload = Secrets_Bridge::export_metadata_inventory( true );
		$json    = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'Could not encode metadata export.', 'credvault' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=credential-vault-metadata-' . gmdate( 'Y-m-d' ) . '.json' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON attachment.
		exit;
	}

	public static function handle_save_settings(): void {
		self::verify_request( 'credential_vault_save_settings' );

		$ttl = isset( $_POST['credential_vault_sudo_ttl'] ) ? (int) $_POST['credential_vault_sudo_ttl'] : 600;
		$mode = isset( $_POST['credential_vault_sudo_mode'] )
			? sanitize_key( (string) wp_unslash( $_POST['credential_vault_sudo_mode'] ) )
			: Vault_Settings::SUDO_ALL_WRITES;

		Vault_Settings::update(
			array(
				'sudo_ttl'  => $ttl,
				'sudo_mode' => $mode,
			)
		);

		self::redirect( 'tab=settings&vault_notice=settings_saved' );
	}

	public static function handle_install_demo(): void {
		self::verify_request( 'credential_vault_install_demo' );

		if ( ! Demo_Data::ui_enabled() ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( __( 'Demo data tools are disabled.', 'credvault' ) ) );
		}

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$result = Demo_Data::install();
		if ( is_wp_error( $result ) ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( $result->get_error_message() ) );
		}

		$query = sprintf(
			'tab=settings&vault_notice=demo_installed&count=%d&skipped=%d',
			(int) ( $result['installed'] ?? 0 ),
			(int) ( $result['skipped'] ?? 0 )
		);
		self::redirect( $query );
	}

	public static function handle_remove_demo(): void {
		self::verify_request( 'credential_vault_remove_demo' );

		if ( ! Demo_Data::ui_enabled() ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( __( 'Demo data tools are disabled.', 'credvault' ) ) );
		}

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( $sudo->get_error_message() ) );
		}

		$result = Demo_Data::remove();
		if ( is_wp_error( $result ) ) {
			self::redirect( 'tab=settings&vault_error=' . rawurlencode( $result->get_error_message() ) );
		}

		self::redirect(
			'tab=settings&vault_notice=demo_removed&count=' . (int) ( $result['removed'] ?? 0 )
		);
	}

	/**
	 * @return array<string, string> import_key => secret_name
	 */
	private static function import_option_map(): array {
		$map = array();
		foreach ( Import_Candidates::all() as $row ) {
			$map[ Plaintext_Scanner::option_import_key( $row['option'] ) ] = $row['suggested_name'];
		}
		foreach ( Plaintext_Scanner::scan() as $row ) {
			$key = (string) ( $row['import_key'] ?? Plaintext_Scanner::option_import_key( (string) $row['option'] ) );
			$map[ $key ] = (string) $row['suggested_name'];
		}

		return $map;
	}

	private static function posted_notes(): string {
		return isset( $_POST['credential_notes'] )
			? sanitize_textarea_field( (string) wp_unslash( $_POST['credential_notes'] ) )
			: '';
	}

	private static function posted_environment(): string {
		return isset( $_POST['credential_environment'] )
			? sanitize_key( (string) wp_unslash( $_POST['credential_environment'] ) )
			: '';
	}

	private static function posted_owner(): string {
		return isset( $_POST['credential_owner'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['credential_owner'] ) )
			: '';
	}

	private static function posted_runbook_url(): string {
		return isset( $_POST['credential_runbook_url'] )
			? esc_url_raw( (string) wp_unslash( $_POST['credential_runbook_url'] ) )
			: '';
	}

	/**
	 * @return array<string, string>
	 */
	private static function posted_public_fields( string $service ): array {
		$templates = Credential_Meta::service_field_templates();
		if ( ! isset( $templates[ $service ] ) ) {
			return array();
		}

		$fields = array();
		foreach ( $templates[ $service ] as $field ) {
			$id = (string) ( $field['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$key = 'credential_public_' . $id;
			if ( isset( $_POST[ $key ] ) ) {
				$fields[ $id ] = sanitize_text_field( (string) wp_unslash( $_POST[ $key ] ) );
			}
		}

		return $fields;
	}

	private static function posted_rotate_by(): int {
		if ( ! isset( $_POST['credential_rotate_by'] ) ) {
			return 0;
		}

		$raw = sanitize_text_field( (string) wp_unslash( $_POST['credential_rotate_by'] ) );
		if ( '' === $raw ) {
			return 0;
		}

		$timestamp = strtotime( $raw . ' 23:59:59' );

		return false === $timestamp ? 0 : $timestamp;
	}

	public static function handle_bundle_export(): void {
		self::verify_request( 'credential_vault_bundle_export' );

		$password = isset( $_POST['bundle_password'] ) ? (string) wp_unslash( $_POST['bundle_password'] ) : '';
		$include  = ! empty( $_POST['bundle_include_values'] );
		$network  = ! empty( $_POST['bundle_network'] );

		if ( $include ) {
			$sudo = self::require_sudo_for_writes();
			if ( is_wp_error( $sudo ) ) {
				self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'migrate' );
			}
		}

		$bundle = Vault_Bundle::export(
			$password,
			array(
				'include_values' => $include,
				'network'        => $network,
			)
		);

		if ( is_wp_error( $bundle ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $bundle->get_error_message() ), 'migrate' );
		}

		Audit_Log::log_user_action( 'bundle_exported', '', array( 'include_values' => $include, 'network' => $network ) );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=credential-vault-' . gmdate( 'Y-m-d' ) . '.cvbundle' );
		echo $bundle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- encrypted blob download.
		exit;
	}

	public static function handle_bundle_import(): void {
		self::verify_request( 'credential_vault_bundle_import' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'migrate' );
		}

		$password = isset( $_POST['bundle_password'] ) ? (string) wp_unslash( $_POST['bundle_password'] ) : '';
		$dry_run  = ! empty( $_POST['bundle_dry_run'] );

		if ( ! isset( $_FILES['bundle_file'] ) || ! is_array( $_FILES['bundle_file'] ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Bundle file is required.', 'credvault' ) ), 'migrate' );
		}

		$tmp = (string) ( $_FILES['bundle_file']['tmp_name'] ?? '' );
		$raw = is_readable( $tmp ) ? file_get_contents( $tmp ) : false;
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Could not read bundle file.', 'credvault' ) ), 'migrate' );
		}

		if ( $dry_run ) {
			$preview = Vault_Bundle::preview( trim( $raw ), $password );
			if ( is_wp_error( $preview ) ) {
				self::redirect_tab( 'vault_error=' . rawurlencode( $preview->get_error_message() ), 'migrate' );
			}
			$msg = sprintf(
				/* translators: 1: slug count, 2: count with values */
				__( 'Dry run: %1$d slug(s), %2$d with values. Network: %3$s', 'credvault' ),
				count( $preview['slugs'] ),
				(int) $preview['with_values'],
				! empty( $preview['network'] ) ? 'yes' : 'no'
			);
			self::redirect_tab( 'vault_notice=imported&vault_error=' . rawurlencode( $msg ), 'migrate' );
		}

		$result = Vault_Bundle::import(
			trim( $raw ),
			$password,
			array( 'skip_existing' => ! empty( $_POST['bundle_skip_existing'] ) )
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $result->get_error_message() ), 'migrate' );
		}

		self::redirect_tab(
			sprintf(
				'vault_notice=imported&count=%d',
				(int) $result['imported']
			),
			'migrate'
		);
	}

	public static function handle_bulk_meta(): void {
		self::verify_request( 'credential_vault_bulk_meta' );

		$sudo = self::require_sudo_for_writes();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'credentials' );
		}

		$names = isset( $_POST['bulk_secrets'] ) ? (array) wp_unslash( $_POST['bulk_secrets'] ) : array();
		if ( empty( $names ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Select at least one credential.', 'credvault' ) ), 'credentials' );
		}

		$bulk_env = isset( $_POST['bulk_environment'] ) ? sanitize_key( (string) wp_unslash( $_POST['bulk_environment'] ) ) : '';
		$bulk_owner = isset( $_POST['bulk_owner'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['bulk_owner'] ) ) : '';
		$bulk_rotate = 0;
		if ( isset( $_POST['bulk_rotate_by'] ) ) {
			$raw = sanitize_text_field( (string) wp_unslash( $_POST['bulk_rotate_by'] ) );
			if ( '' !== $raw ) {
				$ts = strtotime( $raw . ' 23:59:59' );
				$bulk_rotate = false === $ts ? 0 : $ts;
			}
		}

		$network = self::is_network_scope();
		$updated = 0;

		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( 0 !== strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' ) ) {
				continue;
			}

			$row = Credential_Meta::get( $name );
			if ( null === $row ) {
				continue;
			}

			$label = (string) ( $row['label'] ?? basename( $name ) );
			$notes = (string) ( $row['notes'] ?? '' );
			$env   = '' !== $bulk_env ? $bulk_env : (string) ( $row['environment'] ?? '' );
			$owner = '' !== $bulk_owner ? $bulk_owner : (string) ( $row['owner'] ?? '' );
			$rotate = $bulk_rotate > 0 ? $bulk_rotate : (int) ( $row['rotate_by'] ?? 0 );
			$runbook = (string) ( $row['runbook_url'] ?? '' );
			$public = is_array( $row['public_fields'] ?? null ) ? $row['public_fields'] : array();

			Credential_Meta::update_details( $name, $label, $notes, $env, $rotate, $owner, $runbook, $public );
			++$updated;
		}

		self::redirect_tab(
			'vault_notice=meta_saved&vault_error=' . rawurlencode(
				sprintf(
					/* translators: %d: number updated */
					_n( '%d credential updated.', '%d credentials updated.', $updated, 'credvault' ),
					$updated
				)
			),
			$network ? 'network' : 'credentials'
		);
	}

	public static function handle_onboarding_done(): void {
		self::verify_request( 'credential_vault_onboarding_done' );
		Onboarding::mark_complete();
		self::redirect( 'vault_notice=settings_saved' );
	}

	public static function handle_validate_manifest(): void {
		self::verify_view_request( 'credential_vault_validate_manifest' );

		$manifest = null;
		if ( isset( $_FILES['manifest_file'] ) && is_readable( (string) ( $_FILES['manifest_file']['tmp_name'] ?? '' ) ) ) {
			$raw = file_get_contents( (string) $_FILES['manifest_file']['tmp_name'] );
			if ( is_string( $raw ) && '' !== trim( $raw ) ) {
				$manifest = json_decode( $raw, true );
			}
		} elseif ( isset( $_POST['manifest_json'] ) ) {
			$manifest = json_decode( (string) wp_unslash( $_POST['manifest_json'] ), true );
		}

		if ( ! is_array( $manifest ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Invalid manifest JSON.', 'credvault' ) ), 'developer' );
		}

		$result = Secrets_Manifest::validate( $manifest );
		set_transient( 'credential_vault_manifest_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		self::redirect_tab( 'vault_notice=meta_saved', 'developer' );
	}
}
