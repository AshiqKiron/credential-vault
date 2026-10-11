<?php
/**
 * Compromise response workflow (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Usage_Map;

/**
 * Retire slots and notify operators for a suspected leak.
 */
final class Compromise_Playbook {

	public static function register(): void {
		add_action( 'admin_post_credential_vault_pro_run_compromise_playbook', array( self::class, 'handle_run' ) );
	}

	public static function handle_run(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to run the compromise playbook.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_run_compromise_playbook' );

		$slug = isset( $_POST['playbook_slug'] ) ? sanitize_key( (string) wp_unslash( $_POST['playbook_slug'] ) ) : '';
		if ( '' === $slug ) {
			wp_die( esc_html__( 'Select a vault slug.', 'credential-vault-pro' ) );
		}

		$name    = Secrets_Bridge::secret_name( $slug );
		$network = ! empty( $_POST['playbook_network'] );
		$related = self::related_slugs( $slug );

		$retired = array();
		if ( Secrets_Bridge::is_available() && wp_secrets_provider_is_writable() ) {
			$result = Secrets_Bridge::retire_previous( $name, $network );
			if ( ! is_wp_error( $result ) ) {
				$retired[] = $slug;
			}
		}

		Audit_Log::log_user_action(
			'compromise_playbook',
			$name,
			array(
				'related_slugs' => $related,
				'retired'       => $retired,
			)
		);

		Webhooks::dispatch_event(
			'security.compromise_playbook',
			array(
				'slug'          => $slug,
				'related_slugs' => $related,
				'retired'       => $retired,
				'network'       => $network,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'playbook_run',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return list<string>
	 */
	public static function related_slugs( string $slug ): array {
		$slug   = sanitize_key( $slug );
		$slugs  = array( $slug );
		$prefix = strtok( $slug, '-' ) ?: $slug;

		foreach ( Usage_Map::build() as $row ) {
			$suggested = sanitize_key( (string) ( $row['suggested_slug'] ?? '' ) );
			if ( '' === $suggested || $suggested === $slug ) {
				continue;
			}
			if ( str_starts_with( $suggested, $prefix . '-' ) ) {
				$slugs[] = $suggested;
			}
		}

		return array_values( array_unique( $slugs ) );
	}
}
