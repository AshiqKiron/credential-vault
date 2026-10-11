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

		if ( false !== strpos( $slug, 'sendgrid' ) ) {
			return self::test_bearer_get( $secret_name, 'https://api.sendgrid.com/v3/scopes', __( 'SendGrid', 'credvault' ) );
		}

		if ( false !== strpos( $slug, 'mailchimp' ) ) {
			return self::test_mailchimp( $secret_name );
		}

		if ( false !== strpos( $slug, 'twilio' ) ) {
			return self::test_twilio( $secret_name );
		}

		if ( false !== strpos( $slug, 'github' ) ) {
			return self::test_bearer_get( $secret_name, 'https://api.github.com/user', __( 'GitHub', 'credvault' ), 'token' );
		}

		if ( false !== strpos( $slug, 'cloudflare' ) ) {
			return self::test_cloudflare( $secret_name );
		}

		return null;
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_bearer_get( string $secret_name, string $url, string $provider, string $scheme = 'Bearer' ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key || '' === $key ) {
			return true;
		}

		$auth = 'Bearer' === $scheme ? 'Bearer ' . $key : $key;

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => $auth,
				),
				'timeout' => 15,
			)
		);

		Secrets_Bridge::memzero( $key );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'credential_vault_rotation_test',
				sprintf(
					/* translators: 1: provider name, 2: error message */
					__( '%1$s rotation test failed: %2$s', 'credvault' ),
					$provider,
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
				/* translators: 1: provider name, 2: HTTP status code */
				__( '%1$s rotation test returned HTTP %2$d.', 'credvault' ),
				$provider,
				$code
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_mailchimp( string $secret_name ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key || '' === $key ) {
			return true;
		}

		$dc = 'us1';
		if ( false !== strpos( $key, '-' ) ) {
			$parts = explode( '-', $key );
			$dc    = end( $parts );
		}

		$url      = sprintf( 'https://%s.api.mailchimp.com/3.0/ping', rawurlencode( (string) $dc ) );
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'credvault:' . $key ),
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
					__( 'Mailchimp rotation test failed: %s', 'credvault' ),
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
				__( 'Mailchimp rotation test returned HTTP %d.', 'credvault' ),
				$code
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_twilio( string $secret_name ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key || '' === $key ) {
			return true;
		}

		$response = wp_remote_get(
			'https://api.twilio.com/2010-04-01/Accounts.json?PageSize=1',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $key . ':' . $key ),
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
					__( 'Twilio rotation test failed: %s', 'credvault' ),
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
				__( 'Twilio rotation test returned HTTP %d.', 'credvault' ),
				$code
			)
		);
	}

	/**
	 * @return true|\WP_Error
	 */
	private static function test_cloudflare( string $secret_name ) {
		$key = credential_vault_get( basename( $secret_name ) );
		if ( null === $key || '' === $key ) {
			return true;
		}

		$response = wp_remote_get(
			'https://api.cloudflare.com/client/v4/user/tokens/verify',
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
					__( 'Cloudflare rotation test failed: %s', 'credvault' ),
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
				__( 'Cloudflare rotation test returned HTTP %d.', 'credvault' ),
				$code
			)
		);
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
