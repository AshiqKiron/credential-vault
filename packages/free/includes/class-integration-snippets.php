<?php
/**
 * Copy-paste integration examples for developers.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Generates safe PHP snippets (never includes secret values).
 */
final class Integration_Snippets {

	public static function for_slug( string $slug, bool $network = false ): array {
		$slug    = sanitize_key( $slug );
		$network = (bool) $network;

		$get_call = $network
			? sprintf( "credential_vault_get( '%s', true );", $slug )
			: sprintf( "credential_vault_get( '%s' );", $slug );

		$register = sprintf(
			"credential_vault_register( '%s', array(\n    'label' => '%s',\n    'environment' => 'staging',\n) );",
			$slug,
			$slug
		);

		$context = sprintf(
			"credential_vault_get( '%s', %s, array( 'source' => 'my-plugin/v1' ) );",
			$slug,
			$network ? 'true' : 'false'
		);

		$cli = sprintf( 'wp credvault get %s --reveal', $slug );

		/**
		 * Filter integration snippets shown in admin.
		 *
		 * @param array<string, string> $snippets Keys: php_get, php_register, php_context, cli.
		 * @param string                $slug
		 * @param bool                  $network
		 */
		return apply_filters(
			'credential_vault_integration_snippets',
			array(
				'php_get'      => $get_call,
				'php_register' => $register,
				'php_context'  => $context,
				'cli'          => $cli,
			),
			$slug,
			$network
		);
	}
}
