<?php
/**
 * License / seat management placeholder (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Tracks licensed site count for agency networks.
 */
final class License_Seats {

	private const OPTION = 'credential_vault_pro_license';

	public static function register(): void {
		add_action( 'admin_post_credential_vault_pro_save_license', array( self::class, 'handle_save' ) );
		add_filter( 'credential_vault_admin_context', array( self::class, 'inject_context' ) );
	}

	/**
	 * @return array{seats: int, key: string, sites_in_use: int}
	 */
	public static function status(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array(
			'seats'       => max( 1, (int) ( $stored['seats'] ?? 1 ) ),
			'key'         => (string) ( $stored['key'] ?? '' ),
			'sites_in_use' => is_multisite() ? count( get_sites( array( 'number' => 5000 ) ) ) : 1,
		);
	}

	public static function within_limits(): bool {
		$status = self::status();

		return $status['sites_in_use'] <= $status['seats'];
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public static function inject_context( array $context ): array {
		$context['pro_license'] = self::status();
		$context['pro_license_ok'] = self::within_limits();

		return $context;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_network' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage license settings.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_license' );

		update_option(
			self::OPTION,
			array(
				'seats' => max( 1, (int) ( $_POST['license_seats'] ?? 1 ) ),
				'key'   => sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ?? '' ) ),
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'license_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
