<?php
/**
 * Customized re-auth prompts and vault ↔ WP Sudo UX (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Wires vault admin UI to WP Sudo challenge flow and tailored copy.
 */
final class Sudo_Vault_Experience {

	public static function register(): void {
		add_filter( 'credential_vault_admin_context', array( self::class, 'extend_admin_context' ) );
		add_filter( 'credential_vault_wp_sudo_gated_action_label', array( self::class, 'vault_challenge_label' ), 10, 2 );
		add_filter( 'credential_vault_sudo_required_message', array( self::class, 'vault_required_message' ) );
		add_filter( 'credential_vault_sudo_panel_intro', array( self::class, 'vault_panel_intro' ) );
	}

	/**
	 * @param array<string, mixed> $context Template variables.
	 * @return array<string, mixed>
	 */
	public static function extend_admin_context( array $context ): array {
		if ( ! function_exists( 'wp_sudo_check' ) ) {
			return $context;
		}

		$return_url = admin_url( 'admin.php?page=credential-vault' );
		if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab = sanitize_key( (string) wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $tab ) {
				$return_url = add_query_arg( 'tab', $tab, $return_url );
			}
		}

		$challenge_url = '';
		if ( function_exists( 'wp_sudo_build_challenge_url' ) ) {
			$challenge_url = wp_sudo_build_challenge_url(
				admin_url( 'admin.php' ),
				$return_url,
				array(
					'page' => 'wp-sudo-challenge',
				)
			);
		}

		$context['sudo_challenge_url']     = $challenge_url;
		$context['sudo_hide_local_unlock'] = '' !== $challenge_url;
		$context['wp_sudo_integrated']     = true;

		return $context;
	}

	public static function vault_challenge_label( string $label, string $rule_id ): string {
		if ( 'credential_vault.manage' !== $rule_id ) {
			return $label;
		}

		return __( 'View, copy, or change stored API keys in Credential Vault', 'credential-vault-pro' );
	}

	public static function vault_required_message( string $message ): string {
		if ( function_exists( 'wp_sudo_check' ) && ! wp_sudo_check() ) {
			return __( 'Activate a site sudo session (or unlock the vault) before viewing or changing credentials.', 'credential-vault-pro' );
		}

		return $message;
	}

	public static function vault_panel_intro( string $intro ): string {
		if ( function_exists( 'wp_sudo_check' ) ) {
			return __( 'Sensitive vault actions require a site sudo session. Use the button below to re-authenticate with WP Sudo, or unlock with your password only for this vault.', 'credential-vault-pro' );
		}

		return $intro;
	}
}
