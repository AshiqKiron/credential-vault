<?php
/**
 * Compare vault metadata inventories (staging vs production, manifest vs live).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Metadata-only diff — never includes secret values.
 */
final class Metadata_Diff {

	/**
	 * @param array<string, mixed> $left  Export payload or manifest.
	 * @param array<string, mixed> $right Export payload or manifest.
	 * @return array{only_left: list<string>, only_right: list<string>, changed: list<string>, details: array<string, array<string, mixed>>}
	 */
	public static function compare( array $left, array $right ): array {
		$left_map  = self::normalize_map( $left );
		$right_map = self::normalize_map( $right );

		$only_left  = array_values( array_diff( array_keys( $left_map ), array_keys( $right_map ) ) );
		$only_right = array_values( array_diff( array_keys( $right_map ), array_keys( $left_map ) ) );
		$changed    = array();
		$details    = array();

		foreach ( array_intersect( array_keys( $left_map ), array_keys( $right_map ) ) as $slug ) {
			$delta = self::row_delta( $left_map[ $slug ], $right_map[ $slug ] );
			if ( ! empty( $delta ) ) {
				$changed[]         = $slug;
				$details[ $slug ] = $delta;
			}
		}

		sort( $only_left );
		sort( $only_right );
		sort( $changed );

		return array(
			'only_left'  => $only_left,
			'only_right' => $only_right,
			'changed'    => $changed,
			'details'    => $details,
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_map( array $payload ): array {
		if ( isset( $payload['secrets'] ) && is_array( $payload['secrets'] ) ) {
			$map = array();
			foreach ( Secrets_Manifest::normalize_entries( $payload ) as $entry ) {
				$slug = sanitize_key( (string) ( $entry['slug'] ?? '' ) );
				if ( '' === $slug ) {
					continue;
				}
				$map[ $slug ] = array(
					'environment' => sanitize_key( (string) ( $entry['environment'] ?? '' ) ),
					'owner'       => sanitize_text_field( (string) ( $entry['owner'] ?? '' ) ),
					'rotate_by'   => (int) ( $entry['rotate_by'] ?? 0 ),
					'collection'  => sanitize_key( (string) ( $entry['collection'] ?? '' ) ),
				);
			}

			return $map;
		}

		$map = array();
		$rows = $payload['credentials'] ?? $payload;
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? $row['secret'] ?? '' );
			$slug = sanitize_key( (string) ( $row['slug'] ?? basename( $name ) ) );
			if ( '' === $slug ) {
				continue;
			}
			$map[ $slug ] = array(
				'environment' => sanitize_key( (string) ( $row['environment'] ?? '' ) ),
				'owner'       => sanitize_text_field( (string) ( $row['owner'] ?? '' ) ),
				'rotate_by'   => (int) ( $row['rotate_by'] ?? 0 ),
				'collection'  => sanitize_key( (string) ( $row['collection'] ?? '' ) ),
			);
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $a
	 * @param array<string, mixed> $b
	 * @return array<string, array{left: mixed, right: mixed}>
	 */
	private static function row_delta( array $a, array $b ): array {
		$delta = array();
		foreach ( array( 'environment', 'owner', 'rotate_by', 'collection' ) as $key ) {
			$left  = $a[ $key ] ?? '';
			$right = $b[ $key ] ?? '';
			if ( $left !== $right ) {
				$delta[ $key ] = array(
					'left'  => $left,
					'right' => $right,
				);
			}
		}

		return $delta;
	}

	/**
	 * Live site inventory vs manifest file.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function load_file( string $path ) {
		if ( ! is_readable( $path ) ) {
			return new \WP_Error( 'credvault_diff', __( 'File is not readable.', 'credvault' ) );
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new \WP_Error( 'credvault_diff', __( 'File is empty.', 'credvault' ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$parsed = Secrets_Manifest::parse_file( $path );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}

			return $parsed;
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function live_vs_manifest( string $manifest_path ) {
		$parsed = Secrets_Manifest::parse_file( $manifest_path );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$live = Secrets_Bridge::export_metadata_inventory( true );

		return self::compare( $parsed, $live );
	}
}
