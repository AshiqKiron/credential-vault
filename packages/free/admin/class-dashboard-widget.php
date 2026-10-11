<?php
/**
 * wp-admin dashboard widget for vault health.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

use CredentialVault\Secrets_Bridge;
use CredentialVault\Vault_Health;
/**
 * Surfaces vault health on the Dashboard home screen.
 */
final class Dashboard_Widget {

	public static function register( \CredentialVault\Loader $loader ): void {
		$loader->add_action( 'wp_dashboard_setup', self::class, 'setup' );
	}

	public static function setup(): void {
		if ( ! Secrets_Bridge::current_user_can_view() ) {
			return;
		}

		wp_add_dashboard_widget(
			'credential_vault_health',
			__( 'Credential Vault', 'credvault' ),
			array( self::class, 'render' )
		);
	}

	public static function render(): void {
		$summary = Vault_Health::summary();
		$url     = admin_url( 'admin.php?page=credential-vault&tab=health' );
		?>
		<ul>
			<li><?php echo esc_html( sprintf( __( 'API: %s', 'credvault' ), ! empty( $summary['api_available'] ) ? __( 'available', 'credvault' ) : __( 'missing', 'credvault' ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Site credentials: %d', 'credvault' ), (int) $summary['site_secrets'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Overdue rotation: %d', 'credvault' ), (int) $summary['overdue_rotate_by'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Plaintext candidates: %d', 'credvault' ), (int) $summary['plaintext_candidates'] ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Undecryptable: %d', 'credvault' ), (int) $summary['undecryptable'] ) ); ?></li>
		</ul>
		<p><a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open vault health', 'credvault' ); ?></a></p>
		<?php
	}
}
