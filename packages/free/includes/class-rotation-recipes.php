<?php
/**
 * Built-in rotation smoke tests for common integrations.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Registers default credential_vault_rotation_test handlers.
 */
final class Rotation_Recipes {

	public static function register( Loader $loader ): void {
		$loader->add_filter( 'credential_vault_rotation_test', self::class, 'run_test', 10, 3 );
	}

	/**
	 * @param true|\WP_Error|null $result
	 * @return true|\WP_Error|null
	 */
	public static function run_test( $result, string $secret_name, bool $network ) {
		unset( $network );

		if ( null !== $result ) {
			return $result;
		}

		$slug = strtolower( basename( $secret_name ) );

		if ( false !== strpos( $slug, 'stripe' ) ) {
			return self::test_stripe( $secret_name );
		}

		if ( false !== strpos( $slug, 'openai' ) ) {
			return self::test_openai( $secret_name );
		}

		return null;
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_stripe( string $secret_name ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key || 0 !== strpos( $key, 'sk_' ) ) {
			return true;
		}

		$response = wp_remote_get(
			'https://api.stripe.com/v1/balance',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
				),
				'timeout' => 15,
			)
		);

		Secrets_Bridge::memzero( $key );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'credential_vault_rotation_test',
				sprintf(
					/* translators: %s: error message */
					__( 'Stripe rotation test failed: %s', 'credvault' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new \WP_Error(
			'credential_vault_rotation_test',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Stripe rotation test returned HTTP %d.', 'credvault' ),
				$code
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_openai( string $secret_name ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key ) {
			return true;
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
				),
				'timeout' => 15,
			)
		);

		Secrets_Bridge::memzero( $key );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'credential_vault_rotation_test',
				sprintf(
					/* translators: %s: error message */
					__( 'OpenAI rotation test failed: %s', 'credvault' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new \WP_Error(
			'credential_vault_rotation_test',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'OpenAI rotation test returned HTTP %d.', 'credvault' ),
				$code
			)
		);
	}
}
