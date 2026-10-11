<?php
/**
 * Flags plaintext copies of credentials already stored in the vault.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Compares import/scan sources against vault inventory.
 */
final class Duplicate_Detector {

	/**
	 * @return list<array{type: string, label: string, vault_name: string, source: string, detail: string}>
	 */
	public static function find(): array {
		if ( ! Secrets_Bridge::is_available() ) {
			return array();
		}

		$issues = array();

		foreach ( Import_Candidates::all() as $row ) {
			$option = $row['option'];
			$value  = get_option( $option, null );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}

			$name = $row['suggested_name'];
			if ( ! self::vault_has_secret( $name ) ) {
				continue;
			}

			$issues[] = array(
				'type'       => 'duplicate',
				'label'      => $row['label'],
				'vault_name' => $name,
				'source'     => 'option:' . $option,
				'detail'     => __( 'Plaintext option still populated while the vault holds this credential.', 'credvault' ),
			);
		}

		foreach ( Plaintext_Scanner::scan() as $row ) {
			$key = (string) ( $row['import_key'] ?? $row['option'] ?? '' );
			if ( '' === $key ) {
				continue;
			}

			$name = (string) $row['suggested_name'];
			if ( ! self::vault_has_secret( $name ) ) {
				continue;
			}

			if ( self::issue_exists( $issues, $name, $key ) ) {
				continue;
			}

			$issues[] = array(
				'type'       => 'duplicate',
				'label'      => (string) ( $row['label'] ?? $key ),
				'vault_name' => $name,
				'source'     => $key,
				'detail'     => __( 'Scanner found plaintext that matches a vault secret name.', 'credvault' ),
			);
		}

		/**
		 * Filter duplicate/orphan hygiene findings.
		 *
		 * @param list<array{type: string, label: string, vault_name: string, source: string, detail: string}> $issues
		 */
		return apply_filters( 'credential_vault_hygiene_issues', $issues );
	}

	private static function vault_has_secret( string $name ): bool {
		$secret = Secrets_Bridge::get_secret( $name );

		return ! is_wp_error( $secret ) && null !== $secret;
	}

	/**
	 * @param list<array{type: string, label: string, vault_name: string, source: string, detail: string}> $issues
	 */
	private static function issue_exists( array $issues, string $vault_name, string $source ): bool {
		foreach ( $issues as $issue ) {
			if ( $issue['vault_name'] === $vault_name && $issue['source'] === $source ) {
				return true;
			}
		}

		return false;
	}
}
