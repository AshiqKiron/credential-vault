<?php
/**
 * Outbound webhooks for vault audit and rotation events (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

/**
 * Dispatches signed JSON payloads to configured HTTPS endpoints.
 */
final class Webhooks {

	private const OPTION = 'credential_vault_pro_webhooks';

	public static function register(): void {
		add_action( 'credential_vault_audit_entry', array( self::class, 'on_audit_entry' ), 10, 1 );
		add_action( 'admin_post_credential_vault_pro_save_webhooks', array( self::class, 'handle_save' ) );
	}

	/**
	 * @return list<array{url: string, events: list<string>, secret: string, enabled: bool}>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			$events = isset( $row['events'] ) && is_array( $row['events'] )
				? array_values( array_map( 'sanitize_key', $row['events'] ) )
				: array( 'all' );
			$out[] = array(
				'url'     => $url,
				'events'  => $events,
				'secret'  => (string) ( $row['secret'] ?? '' ),
				'enabled' => ! empty( $row['enabled'] ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $entry Audit row.
	 */
	public static function on_audit_entry( array $entry ): void {
		$action = sanitize_key( (string) ( $entry['action'] ?? '' ) );
		if ( '' === $action ) {
			return;
		}

		foreach ( self::all() as $hook ) {
			if ( empty( $hook['enabled'] ) ) {
				continue;
			}
			if ( ! self::matches_event( $hook['events'], $action ) ) {
				continue;
			}

			self::dispatch(
				$hook['url'],
				$hook['secret'],
				array(
					'event'   => 'audit.' . $action,
					'entry'   => $entry,
					'site'    => home_url( '/' ),
					'sent_at' => gmdate( 'c' ),
				)
			);
		}
	}

	public static function handle_save(): void {
		if ( ! \CredentialVault\Secrets_Bridge::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage credentials.', 'credential-vault-pro' ) );
		}

		check_admin_referer( 'credential_vault_pro_save_webhooks' );

		$urls    = isset( $_POST['webhook_url'] ) && is_array( $_POST['webhook_url'] )
			? wp_unslash( $_POST['webhook_url'] )
			: array();
		$secrets = isset( $_POST['webhook_secret'] ) && is_array( $_POST['webhook_secret'] )
			? wp_unslash( $_POST['webhook_secret'] )
			: array();
		$enabled = isset( $_POST['webhook_enabled'] ) && is_array( $_POST['webhook_enabled'] )
			? wp_unslash( $_POST['webhook_enabled'] )
			: array();

		$rows = array();
		$max  = max( count( $urls ), count( $secrets ), count( $enabled ) );

		for ( $i = 0; $i < $max; $i++ ) {
			$url = esc_url_raw( (string) ( $urls[ $i ] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}

			$rows[] = array(
				'url'     => $url,
				'secret'  => sanitize_text_field( (string) ( $secrets[ $i ] ?? '' ) ),
				'events'  => array( 'all' ),
				'enabled' => ! empty( $enabled[ $i ] ),
			);
		}

		update_option( self::OPTION, $rows, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'credential-vault',
					'tab'           => 'automation',
					'vault_notice'  => 'webhooks_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param list<string> $events
	 */
	private static function matches_event( array $events, string $action ): bool {
		if ( in_array( 'all', $events, true ) ) {
			return true;
		}

		return in_array( $action, $events, true );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function dispatch( string $url, string $secret, array $payload ): void {
		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return;
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $secret ) {
			$headers['X-Credential-Vault-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => $body,
			)
		);
	}
}
