<?php
/**
 * Standard audit source labels for programmatic reads.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Normalizes credential_vault_get() context sources.
 */
final class Query_Source {

	public const CLI           = 'cli';
	public const CRON          = 'wp-cron';
	public const REST          = 'rest-api';
	public const WOOCOMMERCE   = 'woocommerce';
	public const ROTATION_TEST = 'rotation-test';

	public static function register(): void {
		add_filter( 'credential_vault_normalize_query_source', array( self::class, 'normalize' ), 10, 2 );
	}

	/**
	 * @return array<string, string> key => description
	 */
	public static function presets(): array {
		$presets = array(
			self::CLI           => __( 'WP-CLI or shell automation', 'credvault' ),
			self::CRON          => __( 'Scheduled WordPress cron', 'credvault' ),
			self::REST          => __( 'REST API consumer', 'credvault' ),
			self::WOOCOMMERCE   => __( 'WooCommerce integration', 'credvault' ),
			self::ROTATION_TEST => __( 'Post-rotation smoke test', 'credvault' ),
		);

		/**
		 * @param array<string, string> $presets
		 */
		return apply_filters( 'credential_vault_query_source_presets', $presets );
	}

	public static function normalize( string $source, array $context ): string {
		unset( $context );
		$source = sanitize_key( $source );
		if ( '' === $source ) {
			return 'credential_vault_get';
		}

		$presets = self::presets();
		if ( isset( $presets[ $source ] ) ) {
			return $source;
		}

		return preg_replace( '/[^a-z0-9._-]/', '-', $source ) ?? $source;
	}
}
