<?php
/**
 * Detects likely secret constant names in wp-config.php and .env (names only).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Read-only filesystem heuristics; never reads or stores secret values.
 */
final class Config_Constant_Scanner {

	/**
	 * @return list<array{file: string, name: string, kind: string}>
	 */
	public static function scan(): array {
		$hits = array();

		foreach ( self::scan_wp_config() as $name ) {
			$hits[] = array(
				'file' => 'wp-config.php',
				'name' => $name,
				'kind' => 'define',
			);
		}

		foreach ( self::scan_dotenv( ABSPATH . '.env' ) as $name ) {
			$hits[] = array(
				'file' => '.env',
				'name' => $name,
				'kind' => 'env',
			);
		}

		/**
		 * @param list<array{file: string, name: string, kind: string}> $hits
		 */
		return apply_filters( 'credential_vault_config_constant_scan', $hits );
	}

	/**
	 * @return list<string>
	 */
	private static function scan_wp_config(): array {
		$path = ABSPATH . 'wp-config.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$patterns = array(
			'STRIPE',
			'OPENAI',
			'SENDGRID',
			'MAILCHIMP',
			'AWS_',
			'API_KEY',
			'SECRET_KEY',
			'AUTH_KEY',
			'TWILIO',
			'GITHUB',
			'CLOUDFLARE',
		);

		$found = array();
		if ( preg_match_all( "/define\\s*\\(\\s*['\"]([A-Z0-9_]+)['\"]/", $raw, $matches ) ) {
			foreach ( $matches[1] as $const ) {
				$const = (string) $const;
				foreach ( $patterns as $needle ) {
					if ( false !== strpos( $const, $needle ) ) {
						$found[] = $const;
						break;
					}
				}
			}
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * @return list<string>
	 */
	private static function scan_dotenv( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$found = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			if ( ! preg_match( '/^([A-Z0-9_]+)\s*=/', $line, $m ) ) {
				continue;
			}
			$key = (string) $m[1];
			if ( preg_match( '/(KEY|SECRET|TOKEN|PASSWORD|API)/', $key ) ) {
				$found[] = $key;
			}
		}

		return array_values( array_unique( $found ) );
	}
}
