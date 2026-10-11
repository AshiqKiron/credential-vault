<?php
/**
 * SIEM-friendly audit export (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * JSON Lines push and syslog forwarding.
 */
final class Siem_Export {

	public static function register(): void {
		add_action( 'credential_vault_pro_siem_push', array( self::class, 'push' ) );
		add_action( 'init', array( self::class, 'schedule' ) );
		add_action( 'admin_post_credential_vault_pro_export_jsonl', array( self::class, 'download_jsonl' ) );
		add_action( 'admin_post_credential_vault_pro_save_siem', array( self::class, 'handle_save' ) );
	}

	public static function schedule(): void {
		if ( self::push_enabled() && ! wp_next_scheduled( 'credential_vault_pro_siem_push' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'credential_vault_pro_siem_push' );
		}
	}

	public static function push_enabled(): bool {
		return (bool) get_option( 'credential_vault_pro_siem_push_enabled', false );
	}

	public static function push(): void {
		if ( ! self::push_enabled() ) {
			return;
		}

		$settings = get_option( 'credential_vault_pro_siem_sink', array() );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$type = sanitize_key( (string) ( $settings['type'] ?? 'https' ) );
		$since = (int) get_option( 'credential_vault_pro_siem_cursor', time() - HOUR_IN_SECONDS );

		$rows = Audit_Store::query( array( 'since' => $since ), 1000, 0 );
		if ( empty( $rows ) ) {
			return;
		}

		if ( 'syslog' === $type && function_exists( 'syslog' ) ) {
			openlog( 'credential-vault', LOG_ODELAY, LOG_USER );
			foreach ( $rows as $entry ) {
				$line = wp_json_encode( $entry );
				if ( is_string( $line ) ) {
					syslog( LOG_INFO, $line );
				}
			}
			closelog();
		} elseif ( 'https' === $type ) {
			$url = esc_url_raw( (string) ( $settings['url'] ?? '' ) );
			if ( '' === $url ) {
				return;
			}

			$body = '';
			foreach ( $rows as $entry ) {
				$line = wp_json_encode( $entry );
				if ( is_string( $line ) ) {
					$body .= $line . "\n";
				}
			}

			wp_remote_post(
				$url,
				array(
					'timeout' => 10,
					'headers' => array(
						'Content-Type' => 'application/x-ndjson',
					),
					'body'    => $body,
				)
			);
		}

		update_option( 'credential_vault_pro_siem_cursor', time(), false );
	}

	public static function download_jsonl(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to view the credential vault.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_export_jsonl' );

		nocache_headers();
		header( 'Content-Type: application/x-ndjson; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=credential-vault-audit-' . gmdate( 'Y-m-d' ) . '.jsonl' );

		Audit_Store::stream_json_lines( array(), 10000 );
		exit;
	}

	public static function handle_save(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_siem' );

		update_option( 'credential_vault_pro_siem_push_enabled', ! empty( $_POST['siem_push_enabled'] ), false );
		update_option(
			'credential_vault_pro_siem_sink',
			array(
				'type' => sanitize_key( (string) wp_unslash( $_POST['siem_type'] ?? 'https' ) ),
				'url'  => esc_url_raw( (string) wp_unslash( $_POST['siem_url'] ?? '' ) ),
			),
			false
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'credential-vault',
					'tab'          => 'automation',
					'vault_notice' => 'siem_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
