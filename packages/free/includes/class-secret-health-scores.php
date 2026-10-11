<?php
/**
 * Per-credential health scores (metadata-only signals).
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Computes 0–100 health scores without reading secret values.
 */
final class Secret_Health_Scores {

	/**
	 * @return array{score: int, grade: string, issues: list<string>}
	 */
	public static function for_secret( string $secret_name, array $row = array() ): array {
		$score  = 100;
		$issues = array();

		$meta = Credential_Meta::get( $secret_name );
		if ( null === $meta ) {
			$score -= 5;
			$issues[] = __( 'Missing metadata record.', 'credvault' );
			$meta = array(
				'owner'       => '',
				'runbook_url' => '',
				'rotate_by'   => 0,
				'environment' => '',
				'collection'  => '',
			);
		}

		$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
		if ( $rotate_by > 0 && $rotate_by < time() ) {
			$score -= 30;
			$issues[] = __( 'Rotate-by date is overdue.', 'credvault' );
		}

		if ( '' === (string) ( $meta['owner'] ?? '' ) ) {
			$score -= 10;
			$issues[] = __( 'No owner assigned.', 'credvault' );
		}

		if ( '' === (string) ( $meta['runbook_url'] ?? '' ) ) {
			$score -= 5;
			$issues[] = __( 'No runbook URL.', 'credvault' );
		}

		if ( Environment_Guard::mismatch_for_secret( $secret_name ) ) {
			$score -= 20;
			$issues[] = __( 'Environment tag may not match this site.', 'credvault' );
		}

		if ( ! empty( $row['needs_rotation'] ) ) {
			$score -= 15;
			$issues[] = __( 'Secrets API flagged needs_rotation.', 'credvault' );
		}

		$slug = basename( $secret_name );
		$lint = Slug_Linter::validate( $slug );
		if ( ! empty( $lint['warnings'] ) ) {
			$score -= 10;
			$issues   = array_merge( $issues, $lint['warnings'] );
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'score'  => $score,
			'grade'  => self::grade( $score ),
			'issues' => $issues,
		);
	}

	/**
	 * @return list<array{name: string, slug: string, score: int, grade: string, issues: list<string>}>
	 */
	public static function all_site(): array {
		if ( ! Secrets_Bridge::is_available() ) {
			return array();
		}

		$out = array();
		foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
			$name   = (string) $row['name'];
			$result = self::for_secret( $name, $row );
			$out[]  = array(
				'name'   => $name,
				'slug'   => basename( $name ),
				'score'  => $result['score'],
				'grade'  => $result['grade'],
				'issues' => $result['issues'],
			);
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return ( $a['score'] ?? 0 ) <=> ( $b['score'] ?? 0 );
			}
		);

		return $out;
	}

	public static function grade( int $score ): string {
		if ( $score >= 90 ) {
			return 'A';
		}
		if ( $score >= 75 ) {
			return 'B';
		}
		if ( $score >= 60 ) {
			return 'C';
		}
		if ( $score >= 40 ) {
			return 'D';
		}

		return 'F';
	}
}
