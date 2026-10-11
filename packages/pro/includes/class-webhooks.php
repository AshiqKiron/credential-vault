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
		add_action( 'credential_vault_rotation_test_failed', array( self::class, 'on_rotation_test_failed' ), 10, 3 );
		add_action( 'credential_vault_pro_undecryptable_detected', array( self::class, 'on_undecryptable' ), 10, 2 );
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
			$format = sanitize_key( (string) ( $row['format'] ?? 'generic' ) );
			if ( ! in_array( $format, array( 'generic', 'slack', 'pagerduty' ), true ) ) {
				$format = 'generic';
			}

			$out[] = array(
				'url'     => $url,
				'events'  => $events,
				'secret'  => (string) ( $row['secret'] ?? '' ),
				'enabled' => ! empty( $row['enabled'] ),
				'format'  => $format,
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
		$formats = isset( $_POST['webhook_format'] ) && is_array( $_POST['webhook_format'] )
			? wp_unslash( $_POST['webhook_format'] )
			: array();
		$events_raw = isset( $_POST['webhook_events'] ) && is_array( $_POST['webhook_events'] )
			? wp_unslash( $_POST['webhook_events'] )
			: array();

		$rows = array();
		$max  = max( count( $urls ), count( $secrets ), count( $enabled ), count( $formats ) );

		for ( $i = 0; $i < $max; $i++ ) {
			$url = esc_url_raw( (string) ( $urls[ $i ] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}

			$format = sanitize_key( (string) ( $formats[ $i ] ?? 'generic' ) );
			if ( ! in_array( $format, array( 'generic', 'slack', 'pagerduty' ), true ) ) {
				$format = 'generic';
			}

			$events_line = sanitize_text_field( (string) ( $events_raw[ $i ] ?? 'all' ) );
			$events      = array_values( array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $events_line ) ) ) ) );
			if ( empty( $events ) ) {
				$events = array( 'all' );
			}

			$rows[] = array(
				'url'     => $url,
				'secret'  => sanitize_text_field( (string) ( $secrets[ $i ] ?? '' ) ),
				'events'  => $events,
				'enabled' => ! empty( $enabled[ $i ] ),
				'format'  => $format,
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
	/**
	 * @param array<string, mixed> $context
	 */
	public static function dispatch_event( string $event, array $context = array() ): void {
		foreach ( self::all() as $hook ) {
			if ( empty( $hook['enabled'] ) ) {
				continue;
			}
			$action = str_replace( array( 'rotation.', 'security.' ), '', $event );
			if ( ! self::matches_event( $hook['events'], $action ) && ! self::matches_event( $hook['events'], $event ) ) {
				if ( ! in_array( 'all', $hook['events'], true ) ) {
					continue;
				}
			}

			self::dispatch(
				$hook['url'],
				$hook['secret'],
				array(
					'event'   => $event,
					'context' => $context,
					'site'    => home_url( '/' ),
					'sent_at' => gmdate( 'c' ),
				),
				(string) ( $hook['format'] ?? 'generic' )
			);
		}
	}

	/**
	 * @param \WP_Error $test
	 */
	public static function on_rotation_test_failed( string $name, bool $network, $test ): void {
		self::dispatch_event(
			'rotation.test_failed',
			array(
				'secret'  => $name,
				'network' => $network,
				'error'   => $test->get_error_message(),
			)
		);
	}

	/**
	 * @param list<string> $names
	 */
	public static function on_undecryptable( array $names, bool $network ): void {
		self::dispatch_event(
			'security.undecryptable',
			array(
				'secrets' => $names,
				'network' => $network,
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function dispatch( string $url, string $secret, array $payload, string $format = 'generic' ): void {
		$event_id = wp_generate_uuid4();
		if ( Webhook_Delivery_Log::seen_event_id( $event_id ) ) {
			return;
		}

		$payload['event_id'] = $event_id;
		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) ) {
			return;
		}

		if ( 'slack' === $format ) {
			$body = wp_json_encode(
				array(
					'text' => sprintf(
						'[%s] %s',
						(string) ( $payload['event'] ?? 'vault.event' ),
						wp_json_encode( $payload['context'] ?? array() )
					),
				)
			);
		} elseif ( 'pagerduty' === $format ) {
			$body = wp_json_encode(
				array(
					'routing_key' => $secret,
					'event_action' => 'trigger',
					'payload'      => array(
						'summary'  => (string) ( $payload['event'] ?? 'Credential Vault event' ),
						'severity' => 'warning',
						'source'   => home_url( '/' ),
						'custom_details' => $payload,
					),
				)
			);
		}

		if ( ! is_string( $body ) ) {
			return;
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $secret && 'pagerduty' !== $format ) {
			$headers['X-Credential-Vault-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		$headers['X-Credential-Vault-Event-Id'] = $event_id;

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 8,
				'blocking' => true,
				'headers'  => $headers,
				'body'     => $body,
			)
		);

		$code    = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$success = ! is_wp_error( $response ) && $code >= 200 && $code < 300;
		Webhook_Delivery_Log::record( $url, $event_id, $payload, $code, $success );
	}
}
