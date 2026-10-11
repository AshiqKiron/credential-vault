<?php
/**
 * Example plugin adapter snippets (filters/options → vault).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Copy-paste patterns for common WordPress plugins.
 */
final class Plugin_Adapters {

	/**
	 * @return list<array{plugin: string, slug: string, description: string, snippet: string}>
	 */
	public static function catalog(): array {
		$items = array(
			array(
				'plugin'      => 'WooCommerce',
				'slug'        => 'woocommerce-stripe-secret',
				'description' => __( 'Read Stripe secret from vault instead of wc_stripe settings.', 'credvault' ),
				'snippet'     => "add_filter( 'option_woocommerce_stripe_settings', function ( \$settings ) {\n    \$key = credential_vault_get( 'woocommerce-stripe-secret' );\n    if ( null !== \$key ) {\n        \$settings['secret_key'] = \$key;\n    }\n    return \$settings;\n} );",
			),
			array(
				'plugin'      => 'Easy Digital Downloads',
				'slug'        => 'edd-stripe-secret',
				'description' => __( 'Inject Stripe secret into EDD Stripe gateway settings.', 'credvault' ),
				'snippet'     => "add_filter( 'edd_get_option_stripe_secret_key', function ( \$value ) {\n    \$vault = credential_vault_get( 'edd-stripe-secret' );\n    return null !== \$vault ? \$vault : \$value;\n} );",
			),
			array(
				'plugin'      => 'Contact Form 7',
				'slug'        => 'cf7-recaptcha-secret',
				'description' => __( 'Supply reCAPTCHA secret to CF7 via filter.', 'credvault' ),
				'snippet'     => "add_filter( 'wpcf7_recaptcha_secret_key', function () {\n    return credential_vault_get( 'cf7-recaptcha-secret' ) ?? '';\n} );",
			),
			array(
				'plugin'      => 'WP Mail SMTP',
				'slug'        => 'wpmailsmtp-api-key',
				'description' => __( 'Override mailer API key at runtime.', 'credvault' ),
				'snippet'     => "add_filter( 'wp_mail_smtp_custom_options', function ( \$options ) {\n    \$key = credential_vault_get( 'wpmailsmtp-api-key' );\n    if ( null !== \$key ) {\n        \$options['api_key'] = \$key;\n    }\n    return \$options;\n} );",
			),
		);

		/**
		 * @param list<array{plugin: string, slug: string, description: string, snippet: string}> $items
		 */
		return apply_filters( 'credential_vault_plugin_adapters', $items );
	}

	public static function register( Loader $loader ): void {
		$loader->add_filter( 'credential_vault_integration_snippets', self::class, 'merge_adapter_hint', 20, 3 );
	}

	/**
	 * @param array<string, string> $snippets
	 * @return array<string, string>
	 */
	public static function merge_adapter_hint( array $snippets, string $slug, bool $network ): array {
		unset( $network );

		foreach ( self::catalog() as $row ) {
			if ( $row['slug'] === $slug ) {
				$snippets['php_adapter'] = (string) $row['snippet'];
				break;
			}
		}

		return $snippets;
	}
}
