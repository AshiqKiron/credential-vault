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
use CredentialVault\Import_Candidates;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Sudo_Gate;

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
	}

	private static function is_network_scope(): bool {
		return isset( $_POST['credential_scope'] ) && 'network' === sanitize_key( (string) wp_unslash( $_POST['credential_scope'] ) );
	}

	private static function redirect_tab( string $query, string $tab = 'credentials' ): void {
		if ( 'network' === $tab ) {
			$query = '' === $query ? 'tab=network' : $query . '&tab=network';
		} elseif ( 'import' === $tab ) {
			$query = '' === $query ? 'tab=import' : $query . '&tab=import';
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

		$sudo = Sudo_Gate::require_active();
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
			Secrets_Bridge::masked_preview( $value )
		);

		Secrets_Bridge::memzero( $value );

		self::redirect_tab( 'vault_notice=saved', $network ? 'network' : 'credentials' );
	}

	public static function handle_delete(): void {
		self::verify_request( 'credential_vault_delete' );

		$sudo = Sudo_Gate::require_active();
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

		$sudo = Sudo_Gate::require_active();
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

		Audit_Log::log_user_action( 'copied', $name );

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

		$sudo = Sudo_Gate::require_active();
		if ( is_wp_error( $sudo ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( $sudo->get_error_message() ), 'import' );
		}

		$imports = isset( $_POST['import'] ) && is_array( $_POST['import'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['import'] ) )
			: array();

		if ( empty( $imports ) ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Select at least one option to import.', 'credvault' ) ), 'import' );
		}

		$map = array();
		foreach ( Import_Candidates::all() as $row ) {
			$map[ $row['option'] ] = $row['suggested_name'];
		}

		$errors = 0;
		$ok     = 0;

		foreach ( $imports as $option ) {
			if ( ! isset( $map[ $option ] ) ) {
				continue;
			}

			$result = Secrets_Bridge::import_option( $option, $map[ $option ] );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$ok;
				Audit_Log::log_user_action(
					'imported',
					$map[ $option ],
					array( 'option' => $option )
				);
			}
		}

		if ( 0 === $ok ) {
			self::redirect_tab( 'vault_error=' . rawurlencode( __( 'Import failed for all selected options.', 'credvault' ) ), 'import' );
		}

		self::redirect_tab( 'vault_notice=imported&count=' . $ok . ( $errors ? '&failed=' . $errors : '' ), 'import' );
	}
}
