<?php
/**
 * Scheduled rotation nudges when rotate_by passes (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Credential_Meta;
use CredentialVault\Rotation_Reminders;
use CredentialVault\Secrets_Bridge;

/**
 * WP-Cron emails and webhooks for overdue rotate-by metadata.
 */
final class Rotation_Nudges {

	public static function register(): void {
		add_action( 'credential_vault_pro_rotation_nudge', array( self::class, 'run' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( 'admin_post_credential_vault_pro_save_nudge_settings', array( self::class, 'handle_save' ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( 'credential_vault_pro_rotation_nudge' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'credential_vault_pro_rotation_nudge' );
		}
	}

	public static function email_enabled(): bool {
		return (bool) get_option( 'credential_vault_pro_nudge_email', true );
	}

	public static function run(): void {
		if ( ! Secrets_Bridge::is_available() ) {
			return;
		}

		$overdue = Rotation_Reminders::overdue_secret_names();
		if ( empty( $overdue ) ) {
			return;
		}

		if ( self::email_enabled() ) {
			$admin_email = get_option( 'admin_email' );
			if ( is_email( $admin_email ) ) {
				wp_mail(
					$admin_email,
					__( 'Credential Vault: rotation overdue', 'credential-vault-pro' ),
					sprintf(
						/* translators: %d: credential count */
						__( "%d credential(s) are past their rotate-by date. Review them in the vault admin.\n\n%s", 'credential-vault-pro' ),
						count( $overdue ),
						admin_url( 'admin.php?page=credential-vault&tab=credentials' )
					)
				);
			}
		}

		Webhooks::dispatch_event(
			'rotation.overdue',
			array(
				'secrets' => array_map(
					static function ( string $name ): array {
						$meta = Credential_Meta::get( $name );

						return array(
							'name'      => $name,
							'label'     => $meta['label'] ?? '',
							'rotate_by' => (int) ( $meta['rotate_by'] ?? 0 ),
						);
					},
					$overdue
				),
			)
		);
	}

	public static function handle_save(): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_nudge_settings' );

		update_option( 'credential_vault_pro_nudge_email', ! empty( $_POST['nudge_email'] ), false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'nudge_settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
