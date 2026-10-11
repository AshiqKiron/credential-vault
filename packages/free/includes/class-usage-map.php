<?php
/**
 * Maps wp_options / plugins to suggested vault slugs.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * "Where is this used?" inventory for migration planning.
 */
final class Usage_Map {

	/**
	 * @return list<array{source: string, option: string, suggested_slug: string, vault_name: string, in_vault: bool, adapter: string, label: string}>
	 */
	public static function build(): array {
		$rows = array();

		foreach ( Import_Candidates::all() as $candidate ) {
			$option = (string) $candidate['option'];
			$name   = (string) $candidate['suggested_name'];
			$slug   = sanitize_key( str_replace( '/', '-', $name ) );

			$rows[] = self::row(
				'import_candidate',
				$option,
				$slug,
				$name,
				(string) ( $candidate['label'] ?? $option ),
				''
			);
		}

		foreach ( Plaintext_Scanner::scan() as $scan ) {
			$key  = (string) ( $scan['import_key'] ?? $scan['option'] ?? '' );
			$name = (string) ( $scan['suggested_name'] ?? '' );
			if ( '' === $key || '' === $name ) {
				continue;
			}

			$slug = sanitize_key( basename( str_replace( '/', '-', $name ) ) );
			$rows[] = self::row(
				'scanner',
				$key,
				$slug,
				$name,
				(string) ( $scan['label'] ?? $key ),
				''
			);
		}

		/**
		 * Extend usage map rows.
		 *
		 * @param list<array{source: string, option: string, suggested_slug: string, vault_name: string, in_vault: bool, adapter: string, label: string}> $rows
		 */
		return apply_filters( 'credential_vault_usage_map', self::dedupe_rows( $rows ) );
	}

	/**
	 * @return list<array{source: string, option: string, suggested_slug: string, vault_name: string, in_vault: bool, adapter: string, label: string}>
	 */
	private static function dedupe_rows( array $rows ): array {
		$seen = array();
		$out  = array();

		foreach ( $rows as $row ) {
			$key = $row['option'] . '|' . $row['vault_name'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}

		return $out;
	}

	/**
	 * @return array{source: string, option: string, suggested_slug: string, vault_name: string, in_vault: bool, adapter: string, label: string}
	 */
	private static function row( string $source, string $option, string $slug, string $vault_name, string $label, string $adapter ): array {
		if ( false === strpos( $vault_name, '/' ) ) {
			$vault_name = Secrets_Bridge::secret_name( $slug );
		}

		$in_vault = false;
		if ( Secrets_Bridge::is_available() ) {
			$secret = Secrets_Bridge::get_secret( $vault_name );
			$in_vault = ! is_wp_error( $secret ) && null !== $secret;
		}

		return array(
			'source'          => $source,
			'option'          => $option,
			'suggested_slug'  => $slug,
			'vault_name'      => $vault_name,
			'in_vault'        => $in_vault,
			'adapter'         => $adapter,
			'label'           => $label,
		);
	}
}
