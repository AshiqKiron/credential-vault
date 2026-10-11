<?php
/**
 * Heuristic scan for API keys stored as plaintext options.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Detects likely secrets in wp_options, user meta, and common plugin options.
 */
final class Plaintext_Scanner {

	private const SCAN_LIMIT = 400;

	private const USER_META_LIMIT = 200;

	/**
	 * @return list<array{option: string, import_key: string, source_type: string, label: string, suggested_name: string, reason: string, preview_hint: string}>
	 */
	public static function scan(): array {
		global $wpdb;

		$curated = array();
		foreach ( Import_Candidates::all() as $row ) {
			$curated[ $row['option'] ] = $row;
		}

		$found   = array();
		$seen    = array();
		$patterns = self::value_patterns();

		$like_clauses = array(
			"option_name LIKE '%api%key%'",
			"option_name LIKE '%secret%'",
			"option_name LIKE '%_token%'",
			"option_name LIKE '%password%'",
			"option_name LIKE '%auth%key%'",
		);

		/**
		 * SQL fragments used to find option names that may hold API keys.
		 *
		 * @param list<string> $like_clauses OR-combined LIKE conditions (no user input).
		 */
		$like_clauses = apply_filters( 'credential_vault_scanner_option_clauses', $like_clauses );

		$sql = "SELECT option_name, option_value FROM {$wpdb->options} WHERE ( " . implode( ' OR ', $like_clauses ) . ' ) AND autoload IN (\'yes\', \'auto\', \'on\', \'off\', \'no\') LIMIT ' . (int) self::SCAN_LIMIT;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- clauses are filter-controlled, not user input.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		foreach ( $curated as $option => $row ) {
			$value = get_option( $option, null );
			if ( ! self::is_importable_string( $value ) ) {
				continue;
			}
			$match = self::match_value( (string) $value, $patterns );
			if ( null === $match && ! self::looks_like_secret_option_name( $option ) ) {
				continue;
			}

			$import_key = self::option_import_key( $option );
			$found[]    = self::format_row(
				$option,
				$import_key,
				'option',
				$row['label'],
				$row['suggested_name'],
				$match ?? __( 'Known integration option', 'credvault' ),
				(string) $value
			);
			$seen[ $import_key ] = true;
		}

		foreach ( self::plugin_option_prefixes() as $prefix ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prefix is sanitized key from filter.
			$sql_prefix = $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
				$wpdb->esc_like( $prefix ) . '%',
				50
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$pref_rows = $wpdb->get_results( $sql_prefix, ARRAY_A );
			if ( ! is_array( $pref_rows ) ) {
				continue;
			}
			foreach ( $pref_rows as $prow ) {
				$option = (string) ( $prow['option_name'] ?? '' );
				$import_key = self::option_import_key( $option );
				if ( '' === $option || isset( $seen[ $import_key ] ) ) {
					continue;
				}
				$value = $prow['option_value'] ?? '';
				if ( is_serialized( $value ) ) {
					$maybe = maybe_unserialize( $value );
					$value = is_string( $maybe ) ? $maybe : '';
				}
				if ( ! self::is_importable_string( $value ) ) {
					continue;
				}
				$match = self::match_value( (string) $value, $patterns );
				if ( null === $match && ! self::looks_like_secret_option_name( $option ) ) {
					continue;
				}
				$found[] = self::format_row(
					$option,
					$import_key,
					'option',
					/* translators: %s: wp_options option name */
					sprintf( __( 'Plugin option: %s', 'credvault' ), $option ),
					self::suggested_secret_name( $option ),
					$match ?? __( 'Plugin option prefix', 'credvault' ),
					(string) $value
				);
				$seen[ $import_key ] = true;
			}
		}

		foreach ( $rows as $row ) {
			$option = (string) ( $row['option_name'] ?? '' );
			if ( '' === $option || isset( $seen[ $option ] ) ) {
				continue;
			}
			if ( 0 === strpos( $option, 'credential_vault_' ) ) {
				continue;
			}

			$value = $row['option_value'] ?? '';
			if ( is_serialized( $value ) ) {
				$maybe = maybe_unserialize( $value );
				if ( is_string( $maybe ) && self::is_importable_string( $maybe ) ) {
					$value = $maybe;
				} else {
					continue;
				}
			}

			if ( ! self::is_importable_string( $value ) ) {
				continue;
			}

			$match = self::match_value( (string) $value, $patterns );
			if ( null === $match && ! self::looks_like_secret_option_name( $option ) ) {
				continue;
			}

			$suggested = self::suggested_secret_name( $option );
			$import_key = self::option_import_key( $option );
			$found[]    = self::format_row(
				$option,
				$import_key,
				'option',
				/* translators: %s: wp_options option name */
				sprintf( __( 'Option: %s', 'credvault' ), $option ),
				$suggested,
				$match ?? __( 'Option name pattern', 'credvault' ),
				(string) $value
			);
			$seen[ $import_key ] = true;
		}

		$meta_clauses = array(
			"meta_key LIKE '%api%key%'",
			"meta_key LIKE '%secret%'",
			"meta_key LIKE '%auth%token%'",
			"meta_key LIKE '%access%token%'",
		);

		/**
		 * SQL fragments for user meta keys that may hold API keys.
		 *
		 * @param list<string> $meta_clauses
		 */
		$meta_clauses = apply_filters( 'credential_vault_scanner_usermeta_clauses', $meta_clauses );

		$meta_sql = 'SELECT user_id, meta_key, meta_value FROM ' . $wpdb->usermeta . ' WHERE ( ' . implode( ' OR ', $meta_clauses ) . ' ) LIMIT ' . (int) self::USER_META_LIMIT;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$meta_rows = $wpdb->get_results( $meta_sql, ARRAY_A );

		if ( is_array( $meta_rows ) ) {
			foreach ( $meta_rows as $mrow ) {
				$user_id    = (int) ( $mrow['user_id'] ?? 0 );
				$meta_key   = (string) ( $mrow['meta_key'] ?? '' );
				$import_key = self::usermeta_import_key( $user_id, $meta_key );
				if ( $user_id <= 0 || '' === $meta_key || isset( $seen[ $import_key ] ) ) {
					continue;
				}

				$value = $mrow['meta_value'] ?? '';
				if ( is_serialized( $value ) ) {
					$maybe = maybe_unserialize( $value );
					$value = is_string( $maybe ) ? $maybe : '';
				}

				if ( ! self::is_importable_string( $value ) ) {
					continue;
				}

				$match = self::match_value( (string) $value, $patterns );
				if ( null === $match && ! self::looks_like_secret_option_name( $meta_key ) ) {
					continue;
				}

				$display = sprintf(
					/* translators: 1: user ID, 2: meta key */
					__( 'User #%1$d meta: %2$s', 'credvault' ),
					$user_id,
					$meta_key
				);

				$found[] = self::format_row(
					$user_id . ':' . $meta_key,
					$import_key,
					'usermeta',
					$display,
					self::suggested_secret_name( 'user-' . $user_id . '-' . $meta_key ),
					$match ?? __( 'User meta pattern', 'credvault' ),
					(string) $value
				);
				$seen[ $import_key ] = true;
			}
		}

		usort(
			$found,
			static function ( array $a, array $b ): int {
				return strcmp( $a['option'], $b['option'] );
			}
		);

		/**
		 * Filter plaintext scanner results before display.
		 *
		 * @param list<array{option: string, label: string, suggested_name: string, reason: string, preview_hint: string}> $found
		 */
		return apply_filters( 'credential_vault_plaintext_scan_results', $found );
	}

	public static function option_import_key( string $option ): string {
		return 'option:' . $option;
	}

	public static function usermeta_import_key( int $user_id, string $meta_key ): string {
		return 'usermeta:' . $user_id . ':' . $meta_key;
	}

	/**
	 * @return list<string>
	 */
	private static function plugin_option_prefixes(): array {
		$prefixes = array();
		foreach ( Import_Candidates::all() as $row ) {
			$plugin = sanitize_key( (string) ( $row['plugin'] ?? '' ) );
			if ( '' !== $plugin ) {
				$prefixes[ $plugin . '_' ] = true;
			}
		}

		/**
		 * Option name prefixes (e.g. `woocommerce_`) scanned beyond LIKE heuristics.
		 *
		 * @param list<string> $prefixes
		 */
		$prefixes = apply_filters( 'credential_vault_scanner_plugin_prefixes', array_keys( $prefixes ) );

		return array_values( array_unique( array_map( 'strval', $prefixes ) ) );
	}

	public static function suggested_secret_name( string $option ): string {
		$slug = sanitize_key( str_replace( array( '_', '-' ), '-', $option ) );
		if ( '' === $slug ) {
			$slug = 'imported';
		}

		return 'credential-vault/imported-' . $slug;
	}

	/**
	 * @return array<string, string> pattern => reason label
	 */
	private static function value_patterns(): array {
		$patterns = array(
			'/^sk_(live|test)_[a-zA-Z0-9]{16,}$/' => 'Stripe secret key',
			'/^rk_(live|test)_[a-zA-Z0-9]{16,}$/' => 'Stripe restricted key',
			'/^sk-[a-zA-Z0-9]{20,}$/'             => 'OpenAI API key',
			'/^AIza[0-9A-Za-z\-_]{35}$/'          => 'Google API key',
			'/^xox[baprs]-[0-9A-Za-z-]{10,}$/'    => 'Slack token',
			'/^gh[pousr]_[A-Za-z0-9_]{20,}$/'     => 'GitHub token',
			'/^[a-f0-9]{32}-us[0-9]{1,2}$/'        => 'Mailchimp API key',
			'/^SG\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}$/' => 'SendGrid API key',
			'/^[A-Za-z0-9]{32}$/'                            => 'Twilio auth token',
			'/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/' => 'Postmark server token',
		);

		/**
		 * Regex (PCRE) => human-readable reason for matched plaintext values.
		 *
		 * @param array<string, string> $patterns
		 */
		return apply_filters( 'credential_vault_scanner_value_patterns', $patterns );
	}

	private static function looks_like_secret_option_name( string $option ): bool {
		$option = strtolower( $option );

		return (bool) preg_match( '/(?:api[_-]?key|secret[_-]?key|auth[_-]?token|access[_-]?token|private[_-]?key)/', $option );
	}

	/**
	 * @param mixed $value Option value.
	 */
	private static function is_importable_string( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value || strlen( $value ) < 8 || strlen( $value ) > 4096 ) {
			return false;
		}

		if ( preg_match( '/[\r\n]/', $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, string> $patterns
	 */
	private static function match_value( string $value, array $patterns ): ?string {
		foreach ( $patterns as $regex => $reason ) {
			if ( 1 === preg_match( $regex, $value ) ) {
				return $reason;
			}
		}

		return null;
	}

	/**
	 * @return array{option: string, import_key: string, source_type: string, label: string, suggested_name: string, reason: string, preview_hint: string}
	 */
	private static function format_row( string $option, string $import_key, string $source_type, string $label, string $suggested_name, string $reason, string $value ): array {
		return array(
			'option'         => $option,
			'import_key'     => $import_key,
			'source_type'    => $source_type,
			'label'          => $label,
			'suggested_name' => $suggested_name,
			'reason'         => $reason,
			'preview_hint'   => Secrets_Bridge::masked_preview( $value ),
		);
	}
}
