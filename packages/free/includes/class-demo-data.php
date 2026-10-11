<?php
/**
 * Sample credentials for local QA and UI exploration.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Installs and removes clearly fake vault entries (slug prefix {@see Demo_Data::SLUG_PREFIX}).
 */
final class Demo_Data {

	public const SLUG_PREFIX = 'demo-';

	private const TRACKING_OPTION = 'credential_vault_demo_slugs';

	/**
	 * Whether demo install/remove controls appear in admin.
	 */
	public static function ui_enabled(): bool {
		/**
		 * Show demo data tools on the settings tab.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'credential_vault_demo_data_ui', true );
	}

	public static function is_installed(): bool {
		return ! empty( self::tracked_slugs() );
	}

	/**
	 * @return list<string> Vault slugs (without namespace).
	 */
	public static function tracked_slugs(): array {
		$slugs = get_option( self::TRACKING_OPTION, array() );

		if ( ! is_array( $slugs ) ) {
			return array();
		}

		$clean = array();
		foreach ( $slugs as $slug ) {
			$key = sanitize_key( (string) $slug );
			if ( '' !== $key && 0 === strpos( $key, self::SLUG_PREFIX ) ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * @return list<array{slug: string, value: string, meta: array<string, mixed>}>
	 */
	public static function fixtures(): array {
		$now      = time();
		$overdue  = $now - ( 14 * DAY_IN_SECONDS );
		$upcoming = $now + ( 21 * DAY_IN_SECONDS );

		$fixtures = array(
			array(
				'slug'  => self::SLUG_PREFIX . 'stripe-live',
				'value' => 'sk_test_DEMO_NOT_REAL_credential_vault_stripe_001',
				'meta'  => array(
					'label'       => __( 'Stripe (demo live)', 'credvault' ),
					'service'     => 'stripe',
					'environment' => 'production',
					'notes'       => __( 'Sample production payment key for UI testing only.', 'credvault' ),
					'owner'       => 'demo-team',
					'runbook_url' => 'https://example.com/runbooks/stripe-rotation',
					'rotate_by'   => $overdue,
				),
			),
			array(
				'slug'  => self::SLUG_PREFIX . 'openai-dev',
				'value' => 'sk-DEMO-NOT-REAL-openai-credential-vault',
				'meta'  => array(
					'label'       => __( 'OpenAI (demo dev)', 'credvault' ),
					'service'     => 'openai',
					'environment' => 'development',
					'notes'       => __( 'Non-production model API key placeholder.', 'credvault' ),
					'owner'       => 'demo-team',
					'rotate_by'   => $upcoming,
				),
			),
			array(
				'slug'  => self::SLUG_PREFIX . 'aws-staging',
				'value' => 'wJalrXUtnFEMI/DEMO+NOT+REAL/KEY+credential+vault',
				'meta'  => array(
					'label'         => __( 'AWS secret (demo staging)', 'credvault' ),
					'service'       => 'aws',
					'environment'   => 'staging',
					'notes'         => __( 'Pairs with the demo access key ID below.', 'credvault' ),
					'public_fields' => array(
						'access_key_id' => 'AKIADEMO000000000001',
					),
					'rotate_by'     => $overdue,
				),
			),
			array(
				'slug'  => self::SLUG_PREFIX . 'webhook-local',
				'value' => 'whsec_DEMO_NOT_REAL_webhook_signing_secret',
				'meta'  => array(
					'label'         => __( 'Inbound webhook (demo local)', 'credvault' ),
					'service'       => 'webhook',
					'environment'   => 'local',
					'public_fields' => array(
						'webhook_url' => 'https://example.test/wp-json/credential-vault/v1/demo-hook',
					),
					'notes'         => __( 'Exercises webhook preset fields and local environment badge.', 'credvault' ),
				),
			),
			array(
				'slug'  => self::SLUG_PREFIX . 'slack-signing',
				'value' => 'demo-slack-signing-secret-not-real-abc123',
				'meta'  => array(
					'label'       => 'Slack signing (demo)',
					'service'     => 'slack',
					'environment' => 'staging',
					'owner'       => 'integrations',
				),
			),
			array(
				'slug'  => self::SLUG_PREFIX . 'custom-internal',
				'value' => 'demo-custom-shared-secret-vault-2026',
				'meta'  => array(
					'label'       => __( 'Internal HMAC (demo custom)', 'credvault' ),
					'service'     => 'custom',
					'environment' => 'production',
					'notes'       => __( 'Custom service preset with operator notes.', 'credvault' ),
					'runbook_url' => 'https://example.com/runbooks/custom-hmac',
				),
			),
		);

		/**
		 * Demo credential fixtures before install.
		 *
		 * @param list<array{slug: string, value: string, meta: array<string, mixed>}> $fixtures
		 */
		return apply_filters( 'credential_vault_demo_data_fixtures', $fixtures );
	}

	/**
	 * @return array{installed: int, skipped: int}|\WP_Error
	 */
	public static function install() {
		if ( ! Secrets_Bridge::is_available() ) {
			return new \WP_Error( 'credential_vault_demo_no_api', __( 'The Secrets API is not available.', 'credvault' ) );
		}

		if ( function_exists( 'wp_secrets_provider_is_writable' ) && ! wp_secrets_provider_is_writable() ) {
			return new \WP_Error( 'credential_vault_demo_readonly', __( 'Demo data requires a writable secrets provider.', 'credvault' ) );
		}

		$installed = 0;
		$skipped   = 0;
		$tracked   = self::tracked_slugs();

		foreach ( self::fixtures() as $fixture ) {
			$slug = sanitize_key( (string) ( $fixture['slug'] ?? '' ) );
			if ( '' === $slug || 0 !== strpos( $slug, self::SLUG_PREFIX ) ) {
				continue;
			}

			$name     = Secrets_Bridge::secret_name( $slug );
			$existing = Secrets_Bridge::get_secret( $name );
			if ( ! is_wp_error( $existing ) && null !== $existing ) {
				++$skipped;
				if ( ! in_array( $slug, $tracked, true ) ) {
					$tracked[] = $slug;
				}
				continue;
			}

			$value = (string) ( $fixture['value'] ?? '' );
			$meta  = isset( $fixture['meta'] ) && is_array( $fixture['meta'] ) ? $fixture['meta'] : array();

			if ( '' === $value ) {
				continue;
			}

			$result = credential_vault_set( $slug, $value, false, $meta );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			++$installed;
			if ( ! in_array( $slug, $tracked, true ) ) {
				$tracked[] = $slug;
			}
		}

		update_option( self::TRACKING_OPTION, array_values( array_unique( $tracked ) ), false );

		return array(
			'installed' => $installed,
			'skipped'   => $skipped,
		);
	}

	/**
	 * @return array{removed: int}|\WP_Error
	 */
	public static function remove() {
		if ( ! Secrets_Bridge::is_available() ) {
			return new \WP_Error( 'credential_vault_demo_no_api', __( 'The Secrets API is not available.', 'credvault' ) );
		}

		if ( function_exists( 'wp_secrets_provider_is_writable' ) && ! wp_secrets_provider_is_writable() ) {
			return new \WP_Error( 'credential_vault_demo_readonly', __( 'Removing demo data requires a writable secrets provider.', 'credvault' ) );
		}

		$removed = 0;
		foreach ( self::tracked_slugs() as $slug ) {
			$name = Secrets_Bridge::secret_name( $slug );

			$result = Secrets_Bridge::delete_secret( $name, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			Credential_Meta::delete( $name );
			++$removed;
		}

		delete_option( self::TRACKING_OPTION );

		return array(
			'removed' => $removed,
		);
	}
}
