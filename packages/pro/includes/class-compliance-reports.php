<?php
/**
 * Compliance report helpers (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

/**
 * Production rotation coverage and access reports.
 */
final class Compliance_Reports {

	public static function register(): void {
		add_filter( 'credential_vault_admin_tabs', array( self::class, 'register_tab' ) );
		add_action( 'credential_vault_render_admin_tab', array( self::class, 'render_tab' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function register_tab( array $tabs ): array {
		$tabs['compliance'] = __( 'Compliance', 'credential-vault-pro' );
		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function render_tab( string $tab, array $context ): void {
		unset( $context );
		if ( 'compliance' !== $tab ) {
			return;
		}

		$production_rotated = self::production_rotated_within_days( 90 );
		$access_report      = self::secret_access_within_days( '', 7 );

		include CREDENTIAL_VAULT_PRO_DIR . 'admin/views/compliance-reports.php';
	}

	/**
	 * @return list<array{name: string, label: string, rotate_by: int, ok: bool}>
	 */
	public static function production_rotated_within_days( int $days ): array {
		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$out    = array();

		foreach ( Secrets_Bridge::list_vault_secrets( false ) as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			if ( null === $meta || 'production' !== ( $meta['environment'] ?? '' ) ) {
				continue;
			}

			$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
			$ok        = $rotate_by >= $cutoff || empty( $row['needs_rotation'] );

			$out[] = array(
				'name'      => $name,
				'label'     => (string) ( $meta['label'] ?? $name ),
				'rotate_by' => $rotate_by,
				'ok'        => $ok,
			);
		}

		return $out;
	}

	/**
	 * @return list<array{time: int, action: string, user_id: int, secret: string}>
	 */
	public static function secret_access_within_days( string $secret_filter, int $days ): array {
		$since = time() - ( $days * DAY_IN_SECONDS );
		$rows  = Audit_Store::query(
			array(
				'secret' => $secret_filter,
				'since'  => $since,
			),
			500,
			0
		);

		return array_values(
			array_filter(
				$rows,
				static function ( array $entry ): bool {
					return in_array( (string) ( $entry['action'] ?? '' ), array( 'copied', 'queried', 'accessed' ), true );
				}
			)
		);
	}
}
