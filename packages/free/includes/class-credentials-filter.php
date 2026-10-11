<?php
/**
 * Credentials table filtering and sorting for admin lists.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Applies GET-driven filters to credential rows.
 */
final class Credentials_Filter {

	/**
	 * @param list<array<string, mixed>> $credentials
	 * @param array<string, mixed>       $query       Typically $_GET.
	 * @return list<array<string, mixed>>
	 */
	public static function apply( array $credentials, array $query ): array {
		$search   = isset( $query['cv_search'] ) ? sanitize_text_field( (string) $query['cv_search'] ) : '';
		$env      = isset( $query['cv_env'] ) ? sanitize_key( (string) $query['cv_env'] ) : '';
		$filter   = isset( $query['cv_filter'] ) ? sanitize_key( (string) $query['cv_filter'] ) : '';
		$collection = isset( $query['cv_collection'] ) ? sanitize_key( (string) $query['cv_collection'] ) : '';
		$sort     = isset( $query['cv_sort'] ) ? sanitize_key( (string) $query['cv_sort'] ) : 'label';
		$activity = Audit_Activity::index_by_secret();

		$out = array();
		foreach ( $credentials as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			$label = is_array( $meta ) ? (string) ( $meta['label'] ?? basename( $name ) ) : basename( $name );
			$row_env = is_array( $meta ) ? (string) ( $meta['environment'] ?? '' ) : '';
			$rotate_by = is_array( $meta ) ? (int) ( $meta['rotate_by'] ?? 0 ) : 0;

			if ( '' !== $search ) {
				$hay = strtolower( $label . ' ' . $name . ' ' . basename( $name ) );
				if ( false === strpos( $hay, strtolower( $search ) ) ) {
					continue;
				}
			}

			if ( '' !== $env && $env !== $row_env ) {
				continue;
			}

			if ( 'overdue' === $filter && ( $rotate_by <= 0 || $rotate_by >= time() ) ) {
				continue;
			}
			if ( 'needs_rotation' === $filter && empty( $row['needs_rotation'] ) ) {
				continue;
			}
			if ( 'has_previous' === $filter && empty( $row['has_previous'] ) ) {
				continue;
			}
			if ( 'env_mismatch' === $filter && null === Environment_Guard::mismatch_for_secret( $name ) ) {
				continue;
			}

			if ( '' !== $collection ) {
				$row_collection = is_array( $meta ) ? (string) ( $meta['collection'] ?? '' ) : '';
				if ( $collection !== $row_collection ) {
					continue;
				}
			}

			$row['_cv_label']     = $label;
			$row['_cv_rotate_by'] = $rotate_by;
			$row['_cv_activity']  = $activity[ $name ]['time'] ?? 0;
			$out[]                = $row;
		}

		usort(
			$out,
			static function ( array $a, array $b ) use ( $sort ): int {
				if ( 'rotate_by' === $sort ) {
					return ( $a['_cv_rotate_by'] ?? 0 ) <=> ( $b['_cv_rotate_by'] ?? 0 );
				}
				if ( 'activity' === $sort ) {
					return ( $b['_cv_activity'] ?? 0 ) <=> ( $a['_cv_activity'] ?? 0 );
				}
				if ( 'slug' === $sort ) {
					return strcmp( (string) $a['name'], (string) $b['name'] );
				}

				return strcasecmp( (string) ( $a['_cv_label'] ?? '' ), (string) ( $b['_cv_label'] ?? '' ) );
			}
		);

		return $out;
	}
}
