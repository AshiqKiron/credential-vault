<?php
/**
 * Admin notices when credentials pass their rotate-by date.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault;

/**
 * Surfaces overdue rotation metadata on vault screens.
 */
final class Rotation_Reminders {

	public static function register( Loader $loader ): void {
		$loader->add_action( 'admin_notices', self::class, 'maybe_render_notice' );
	}

	public static function maybe_render_notice(): void {
		if ( ! is_admin() || ! Secrets_Bridge::current_user_can_manage() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'credential-vault' !== $page && 'index.php' !== $GLOBALS['pagenow'] ) {
			return;
		}

		if ( ! Secrets_Bridge::is_available() ) {
			return;
		}

		$overdue = self::overdue_secret_names();
		if ( empty( $overdue ) ) {
			return;
		}

		$url = admin_url( 'admin.php?page=credential-vault&tab=credentials' );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of credentials */
					_n(
						'%d vault credential is past its rotate-by date.',
						'%d vault credentials are past their rotate-by dates.',
						count( $overdue ),
						'credvault'
					),
					count( $overdue )
				)
			),
			esc_url( $url ),
			esc_html__( 'Review credentials', 'credvault' )
		);
	}

	/**
	 * @return list<string> Fully qualified secret names.
	 */
	public static function overdue_secret_names(): array {
		$now     = time();
		$names   = array();
		$secrets = Secrets_Bridge::list_vault_secrets( false );

		if ( Secrets_Bridge::supports_network() ) {
			$secrets = array_merge( $secrets, Secrets_Bridge::list_vault_secrets( true ) );
		}

		foreach ( $secrets as $row ) {
			$name = (string) $row['name'];
			$meta = Credential_Meta::get( $name );
			if ( null === $meta ) {
				continue;
			}
			$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
			if ( $rotate_by > 0 && $rotate_by < $now ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}
}
