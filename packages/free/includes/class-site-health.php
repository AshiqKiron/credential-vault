<?php
/**
 * Site Health tests for vault operators.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Surfaces undecryptable and pending-rotation credentials in Site Health.
 */
final class Site_Health {

	public static function register( Loader $loader ): void {
		$loader->add_filter( 'site_status_tests', self::class, 'register_tests' );
		$loader->add_filter( 'debug_information', self::class, 'debug_info' );
	}

	/**
	 * @param array<string, mixed> $tests Site Health tests.
	 * @return array<string, mixed>
	 */
	public static function register_tests( array $tests ): array {
		if ( ! Secrets_Bridge::is_available() ) {
			return $tests;
		}

		$tests['direct']['credential_vault_undecryptable'] = array(
			'label' => __( 'Credential Vault: undecryptable secrets', 'credvault' ),
			'test'  => array( self::class, 'test_undecryptable' ),
		);

		$tests['direct']['credential_vault_rotation'] = array(
			'label' => __( 'Credential Vault: credentials pending rotation', 'credvault' ),
			'test'  => array( self::class, 'test_needs_rotation' ),
		);

		return $tests;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function test_undecryptable(): array {
		$broken = Secrets_Bridge::find_undecryptable( false );

		if ( Secrets_Bridge::supports_network() ) {
			$broken = array_merge( $broken, Secrets_Bridge::find_undecryptable( true ) );
		}

		$broken = array_values( array_unique( $broken ) );

		if ( empty( $broken ) ) {
			return self::result(
				'credential_vault_undecryptable',
				__( 'All stored secrets decrypt successfully', 'credvault' ),
				'good',
				'<p>' . esc_html__( 'No undecryptable secrets were detected.', 'credvault' ) . '</p>'
			);
		}

		$list = '<ul><li><code>' . implode( '</code></li><li><code>', array_map( 'esc_html', $broken ) ) . '</code></li></ul>';

		return self::result(
			'credential_vault_undecryptable',
			__( 'Some secrets cannot be decrypted', 'credvault' ),
			'critical',
			'<p>' . esc_html__( 'These credentials exist but failed decryption (often after key rotation or migration). Re-enter them in Credential Vault.', 'credvault' ) . '</p>' . $list
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=credential-vault' ) ) . '">' . esc_html__( 'Open Credential Vault', 'credvault' ) . '</a></p>'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function test_needs_rotation(): array {
		$pending = array();

		foreach ( Secrets_Bridge::list_secrets( '', false ) as $row ) {
			if ( ! empty( $row['needs_rotation'] ) ) {
				$pending[] = (string) $row['name'];
			}
		}

		if ( empty( $pending ) ) {
			return self::result(
				'credential_vault_rotation',
				__( 'No credentials flagged for rotation', 'credvault' ),
				'good',
				'<p>' . esc_html__( 'No secrets are marked needs_rotation.', 'credvault' ) . '</p>'
			);
		}

		$list = '<ul><li><code>' . implode( '</code></li><li><code>', array_map( 'esc_html', $pending ) ) . '</code></li></ul>';

		return self::result(
			'credential_vault_rotation',
			__( 'Credentials should be rotated', 'credvault' ),
			'recommended',
			'<p>' . esc_html__( 'These secrets were imported or flagged by the API as still exposed in old backups. Rotate the live credential with your provider.', 'credvault' ) . '</p>' . $list
		);
	}

	/**
	 * @param array<string, mixed> $info Debug info sections.
	 * @return array<string, mixed>
	 */
	public static function debug_info( array $info ): array {
		if ( ! Secrets_Bridge::is_available() ) {
			return $info;
		}

		$info['credential-vault'] = array(
			'label'  => __( 'Credential Vault', 'credvault' ),
			'fields' => array(
				'provider'    => array(
					'label' => __( 'Secrets provider', 'credvault' ),
					'value' => Secrets_Bridge::provider_label(),
				),
				'vault_count' => array(
					'label' => __( 'Vault credentials', 'credvault' ),
					'value' => (string) count( Secrets_Bridge::list_vault_secrets( false ) ),
				),
				'external'    => array(
					'label' => __( 'Other plugin secrets', 'credvault' ),
					'value' => (string) count( Secrets_Bridge::list_external_secrets( false ) ),
				),
			),
		);

		return $info;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function result( string $test, string $label, string $status, string $description ): array {
		$colors = array(
			'good'        => 'green',
			'recommended' => 'orange',
			'critical'    => 'red',
		);

		return array(
			'test'        => $test,
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Security', 'credvault' ),
				'color' => $colors[ $status ] ?? 'blue',
			),
			'description' => $description,
		);
	}
}
