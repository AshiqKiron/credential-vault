<?php
/**
 * Version-controlled vault manifest (metadata only, no secret values).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Parses credvault.manifest.json / .yaml and validates against the live vault.
 */
final class Secrets_Manifest {

	public const MANIFEST_VERSION = 1;

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse_file( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'credvault_manifest', __( 'Manifest file is not readable.', 'credvault' ) );
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new \WP_Error( 'credvault_manifest', __( 'Manifest file is empty.', 'credvault' ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = self::parse_simple_yaml( $raw );
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'credvault_manifest', __( 'Manifest must be valid JSON or supported YAML.', 'credvault' ) );
		}

		/**
		 * Filter manifest array after parsing.
		 *
		 * @param array<string, mixed> $data Parsed manifest.
		 * @param string               $path Source path.
		 */
		return apply_filters( 'credential_vault_manifest_parsed', $data, $path );
	}

	/**
	 * @param array<string, mixed> $manifest
	 * @param array<string, mixed> $options Keys: fail_on_overdue (bool), fail_on_undecryptable (bool).
	 * @return array{ok: bool, errors: list<string>, warnings: list<string>, missing: list<string>, overdue: list<string>}
	 */
	public static function validate( array $manifest, array $options = array() ): array {
		$fail_overdue        = ! empty( $options['fail_on_overdue'] );
		$fail_undecryptable  = ! empty( $options['fail_on_undecryptable'] );
		$errors              = array();
		$warnings            = array();
		$missing             = array();
		$overdue             = array();

		$entries = self::normalize_entries( $manifest );
		if ( empty( $entries ) ) {
			$errors[] = __( 'Manifest contains no secrets entries.', 'credvault' );
		}

		foreach ( $entries as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' === $slug ) {
				$errors[] = __( 'Manifest entry is missing a slug.', 'credvault' );
				continue;
			}

			if ( ! Secrets_Bridge::is_available() ) {
				$warnings[] = sprintf(
					/* translators: %s: slug */
					__( 'Secrets API unavailable — cannot verify slug %s.', 'credvault' ),
					$slug
				);
				continue;
			}

			$name   = Secrets_Bridge::secret_name( $slug );
			$secret = Secrets_Bridge::get_secret( $name );
			if ( is_wp_error( $secret ) ) {
				$errors[] = sprintf(
					/* translators: 1: slug, 2: error message */
					__( 'Slug %1$s is undecryptable: %2$s', 'credvault' ),
					$slug,
					$secret->get_error_message()
				);
				continue;
			}

			if ( null === $secret ) {
				$missing[] = $slug;
				$errors[]  = sprintf(
					/* translators: %s: slug */
					__( 'Required secret missing in vault: %s', 'credvault' ),
					$slug
				);
				continue;
			}

			$rotate_by = (int) ( $entry['rotate_by'] ?? 0 );
			if ( $rotate_by <= 0 ) {
				$meta      = Credential_Meta::get( $name );
				$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
			}

			if ( $rotate_by > 0 && $rotate_by < time() ) {
				$overdue[] = $slug;
				$message   = sprintf(
					/* translators: %s: slug */
					__( 'Rotate-by date is overdue for %s.', 'credvault' ),
					$slug
				);
				if ( $fail_overdue ) {
					$errors[] = $message;
				} else {
					$warnings[] = $message;
				}
			}
		}

		if ( $fail_undecryptable && Secrets_Bridge::is_available() ) {
			$broken = Secrets_Bridge::find_undecryptable( false );
			if ( ! empty( $broken ) ) {
				foreach ( $broken as $name ) {
					$errors[] = sprintf(
						/* translators: %s: secret name */
						__( 'Undecryptable site secret: %s', 'credvault' ),
						$name
					);
				}
			}
		}

		$ok = empty( $errors );

		/**
		 * Filter manifest validation outcome.
		 *
		 * @param array{ok: bool, errors: list<string>, warnings: list<string>, missing: list<string>, overdue: list<string>} $result
		 * @param array<string, mixed>                                                                                        $manifest
		 * @param array<string, mixed>                                                                                        $options
		 */
		return apply_filters(
			'credential_vault_manifest_validation',
			array(
				'ok'       => $ok,
				'errors'   => $errors,
				'warnings' => $warnings,
				'missing'  => $missing,
				'overdue'  => $overdue,
			),
			$manifest,
			$options
		);
	}

	/**
	 * Register metadata from manifest entries (no values).
	 *
	 * @param array<string, mixed> $manifest
	 * @return array{registered: int}
	 */
	public static function apply_register( array $manifest ): array {
		$count = 0;
		foreach ( self::normalize_entries( $manifest ) as $entry ) {
			$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}

			$meta = array(
				'label'         => (string) ( $entry['label'] ?? $slug ),
				'service'       => (string) ( $entry['service'] ?? 'custom' ),
				'notes'         => (string) ( $entry['notes'] ?? '' ),
				'owner'         => (string) ( $entry['owner'] ?? '' ),
				'runbook_url'   => (string) ( $entry['runbook_url'] ?? '' ),
				'environment'   => (string) ( $entry['environment'] ?? '' ),
				'rotate_by'     => isset( $entry['rotate_by'] ) ? (int) $entry['rotate_by'] : 0,
				'public_fields' => isset( $entry['public_fields'] ) && is_array( $entry['public_fields'] ) ? $entry['public_fields'] : array(),
			);

			credential_vault_register( $slug, $meta );
			++$count;
		}

		return array( 'registered' => $count );
	}

	/**
	 * @param array<string, mixed> $manifest
	 * @return list<array<string, mixed>>
	 */
	public static function normalize_entries( array $manifest ): array {
		$secrets = $manifest['secrets'] ?? $manifest['credentials'] ?? array();
		if ( ! is_array( $secrets ) ) {
			return array();
		}

		$out = array();
		foreach ( $secrets as $row ) {
			if ( is_string( $row ) ) {
				$out[] = array( 'slug' => sanitize_key( $row ) );
				continue;
			}
			if ( is_array( $row ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Minimal YAML parser for manifest documents (no external dependencies).
	 *
	 * @return array<string, mixed>|null
	 */
	private static function parse_simple_yaml( string $raw ): ?array {
		$lines   = preg_split( '/\r\n|\r|\n/', $raw );
		$secrets = array();
		$current = null;
		$version = self::MANIFEST_VERSION;

		if ( ! is_array( $lines ) ) {
			return null;
		}

		foreach ( $lines as $line ) {
			$trim = trim( (string) $line );
			if ( '' === $trim || '#' === $trim[0] ) {
				continue;
			}

			if ( preg_match( '/^version:\s*(\d+)/i', $trim, $m ) ) {
				$version = (int) $m[1];
				continue;
			}

			if ( preg_match( '/^-\s+slug:\s*(.+)$/i', $trim, $m ) ) {
				if ( is_array( $current ) ) {
					$secrets[] = $current;
				}
				$current = array( 'slug' => sanitize_key( trim( $m[1], " \t\"'" ) ) );
				continue;
			}

			if ( preg_match( '/^([a-z_]+):\s*(.+)$/i', $trim, $m ) && is_array( $current ) ) {
				$key = strtolower( $m[1] );
				$val = trim( $m[2], " \t\"'" );
				if ( 'rotate_by' === $key ) {
					$current[ $key ] = (int) strtotime( $val );
				} else {
					$current[ $key ] = $val;
				}
			}
		}

		if ( is_array( $current ) ) {
			$secrets[] = $current;
		}

		if ( empty( $secrets ) ) {
			return null;
		}

		return array(
			'version' => $version,
			'secrets' => $secrets,
		);
	}
}
