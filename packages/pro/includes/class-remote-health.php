<?php
/**
 * Remote monitoring summary (ManageWP / MainWP / custom probes).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Secrets_Manifest;
use CredentialVault\Vault_Health;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes metadata-only health for external dashboards.
 */
final class Remote_Health {

	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
		add_filter( 'credential_vault_remote_health_summary', array( self::class, 'build_summary' ) );
	}

	public static function register_route(): void {
		register_rest_route(
			'credential-vault/v1',
			'/remote-health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_callback' ),
				'permission_callback' => static function (): bool {
					return \CredentialVault\Secrets_Bridge::current_user_can_view();
				},
			)
		);
	}

	public static function rest_callback( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		return new WP_REST_Response( self::build_summary( array() ) );
	}

	/**
	 * @param array<string, mixed> $summary
	 * @return array<string, mixed>
	 */
	public static function build_summary( array $summary ): array {
		$health = Vault_Health::summary();
		$scores = \CredentialVault\Secret_Health_Scores::all_site();
		$low    = 0;
		foreach ( $scores as $row ) {
			if ( (int) ( $row['score'] ?? 100 ) < 60 ) {
				++$low;
			}
		}

		$manifest_path = apply_filters( 'credential_vault_manifest_path', '' );
		$manifest_ok   = null;
		if ( is_string( $manifest_path ) && '' !== $manifest_path && is_readable( $manifest_path ) ) {
			$parsed = Secrets_Manifest::parse_file( $manifest_path );
			if ( is_array( $parsed ) ) {
				$result      = Secrets_Manifest::validate( $parsed );
				$manifest_ok = ! empty( $result['ok'] );
			}
		}

		return array_merge(
			$summary,
			array(
				'plugin'              => 'credential-vault',
				'pro'                 => true,
				'attention_count'     => Vault_Health::attention_count(),
				'health'              => $health,
				'low_health_secrets'  => $low,
				'manifest_configured' => is_string( $manifest_path ) && '' !== $manifest_path,
				'manifest_valid'      => $manifest_ok,
				'license_within_limits' => License_Seats::within_limits(),
				'checked_at'          => gmdate( 'c' ),
			)
		);
	}
}
