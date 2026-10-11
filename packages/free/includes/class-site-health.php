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

		$tests['direct']['credential_vault_plaintext'] = array(
			'label' => __( 'Credential Vault: plaintext credential candidates', 'credvault' ),
			'test'  => array( self::class, 'test_plaintext_candidates' ),
		);

		$tests['direct']['credential_vault_sudo_ttl'] = array(
			'label' => __( 'Credential Vault: sudo session TTL', 'credvault' ),
			'test'  => array( self::class, 'test_sudo_ttl' ),
		);

		$tests['direct']['credential_vault_audit_capacity'] = array(
			'label' => __( 'Credential Vault: audit log capacity', 'credvault' ),
			'test'  => array( self::class, 'test_audit_capacity' ),
		);

		$tests['direct']['credential_vault_manifest'] = array(
			'label' => __( 'Credential Vault: secrets manifest', 'credvault' ),
			'test'  => array( self::class, 'test_manifest' ),
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
	 * @return array<string, mixed>
	 */
	public static function test_plaintext_candidates(): array {
		$count = count( Plaintext_Scanner::scan() );
		if ( 0 === $count ) {
			return self::result(
				'credential_vault_plaintext',
				__( 'No plaintext credential candidates detected', 'credvault' ),
				'good',
				'<p>' . esc_html__( 'Option scanner found no likely API keys in wp_options.', 'credvault' ) . '</p>'
			);
		}

		return self::result(
			'credential_vault_plaintext',
			__( 'Plaintext credential candidates found', 'credvault' ),
			'recommended',
			'<p>' . esc_html(
				sprintf(
					/* translators: %d: count */
					_n(
						'%d option may still store a secret in plaintext.',
						'%d options may still store secrets in plaintext.',
						$count,
						'credvault'
					),
					$count
				)
			) . '</p>'
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=credential-vault&tab=import' ) ) . '">' . esc_html__( 'Open import scanner', 'credvault' ) . '</a></p>'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function test_sudo_ttl(): array {
		$ttl = Vault_Settings::sudo_ttl_seconds();
		if ( $ttl >= 120 && $ttl <= 3600 ) {
			return self::result(
				'credential_vault_sudo_ttl',
				__( 'Vault sudo TTL looks reasonable', 'credvault' ),
				'good',
				'<p>' . esc_html( sprintf( __( 'Current TTL: %d seconds.', 'credvault' ), $ttl ) ) . '</p>'
			);
		}

		return self::result(
			'credential_vault_sudo_ttl',
			__( 'Review vault sudo TTL', 'credvault' ),
			'recommended',
			'<p>' . esc_html__( 'Very short or very long sudo sessions can frustrate operators or increase risk. Consider 5–30 minutes.', 'credvault' ) . '</p>'
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=credential-vault&tab=settings' ) ) . '">' . esc_html__( 'Vault settings', 'credvault' ) . '</a></p>'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function test_audit_capacity(): array {
		if ( ! Vault_Health::summary()['audit_near_capacity'] ) {
			return self::result(
				'credential_vault_audit_capacity',
				__( 'Audit log has headroom', 'credvault' ),
				'good',
				'<p>' . esc_html__( 'The option-backed audit log is below 90% capacity.', 'credvault' ) . '</p>'
			);
		}

		return self::result(
			'credential_vault_audit_capacity',
			__( 'Audit log is nearly full', 'credvault' ),
			'recommended',
			'<p>' . esc_html__( 'Export audit CSV and consider raising credential_vault_audit_max_entries or upgrading retention.', 'credvault' ) . '</p>'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function test_manifest(): array {
		$path = ABSPATH . 'credvault.manifest.json';
		if ( ! is_readable( $path ) ) {
			return self::result(
				'credential_vault_manifest',
				__( 'No credvault.manifest.json in site root', 'credvault' ),
				'good',
				'<p>' . esc_html__( 'Optional: add a manifest for CI validation.', 'credvault' ) . '</p>'
			);
		}

		$parsed = Secrets_Manifest::parse_file( $path );
		if ( is_wp_error( $parsed ) ) {
			return self::result(
				'credential_vault_manifest',
				__( 'Manifest file is invalid', 'credvault' ),
				'critical',
				'<p>' . esc_html( $parsed->get_error_message() ) . '</p>'
			);
		}

		$result = Secrets_Manifest::validate( $parsed );
		if ( ! empty( $result['errors'] ) ) {
			return self::result(
				'credential_vault_manifest',
				__( 'Manifest validation failed', 'credvault' ),
				'critical',
				'<p>' . esc_html( implode( ' ', $result['errors'] ) ) . '</p>'
			);
		}

		return self::result(
			'credential_vault_manifest',
			__( 'Manifest validates against vault', 'credvault' ),
			'good',
			'<p>' . esc_html__( 'credvault.manifest.json matches stored credentials.', 'credvault' ) . '</p>'
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
