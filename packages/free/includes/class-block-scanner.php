<?php
/**
 * Scan block editor content for likely secret material.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Pattern-based scan of post_content (no options table values).
 */
final class Block_Scanner {

	/**
	 * @return list<array{post_id: int, title: string, pattern: string, excerpt: string}>
	 */
	public static function scan( int $limit = 200 ): array {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => max( 1, min( 500, $limit ) ),
				'fields'         => 'ids',
			)
		);

		$patterns = self::patterns();
		$hits     = array();

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$content = (string) $post->post_content;
			if ( '' === $content ) {
				continue;
			}

			foreach ( $patterns as $label => $regex ) {
				if ( preg_match( $regex, $content, $matches ) ) {
					$snippet = isset( $matches[0] ) ? (string) $matches[0] : '';
					$hits[]  = array(
						'post_id' => (int) $post_id,
						'title'   => get_the_title( $post_id ),
						'pattern' => $label,
						'excerpt' => self::mask_snippet( $snippet ),
					);
					break;
				}
			}
		}

		return $hits;
	}

	/**
	 * @return array<string, string>
	 */
	private static function patterns(): array {
		$patterns = array(
			'stripe_sk'   => '/\bsk_(?:live|test)_[A-Za-z0-9]{8,}\b/',
			'aws_key_id'  => '/\bAKIA[0-9A-Z]{16}\b/',
			'openai_sk'   => '/\bsk-[A-Za-z0-9]{20,}\b/',
			'github_pat'  => '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/',
		);

		/**
		 * @param array<string, string> $patterns Regex map label => pattern.
		 */
		return apply_filters( 'credential_vault_block_scanner_patterns', $patterns );
	}

	private static function mask_snippet( string $snippet ): string {
		$len = strlen( $snippet );
		if ( $len <= 8 ) {
			return str_repeat( '•', $len );
		}

		return substr( $snippet, 0, 4 ) . str_repeat( '•', max( 4, $len - 8 ) ) . substr( $snippet, -4 );
	}
}
