<?php
/**
 * Team collections for grouping credentials.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Named collections stored in credential metadata.
 */
final class Secret_Collections {

	/**
	 * @return array<string, string> slug => label
	 */
	public static function choices(): array {
		$choices = array(
			''              => __( 'None', 'credvault' ),
			'payments'      => __( 'Payments', 'credvault' ),
			'email'         => __( 'Email & messaging', 'credvault' ),
			'ai'            => __( 'AI & data', 'credvault' ),
			'infrastructure' => __( 'Infrastructure & CDN', 'credvault' ),
			'auth'          => __( 'Auth & OAuth', 'credvault' ),
		);

		/**
		 * @param array<string, string> $choices
		 */
		return apply_filters( 'credential_vault_collection_choices', $choices );
	}

	public static function sanitize( string $collection ): string {
		$collection = sanitize_key( $collection );
		$choices    = self::choices();

		return isset( $choices[ $collection ] ) ? $collection : '';
	}
}
