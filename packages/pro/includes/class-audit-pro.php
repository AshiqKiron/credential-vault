<?php
/**
 * Extended audit retention and search UI (Pro).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

namespace CredentialVaultPro;

use CredentialVault\Audit_Log;
use CredentialVault\Secrets_Bridge;

/**
 * Raises audit retention and enhances the audit tab.
 */
final class Audit_Pro {

	private const MAX_ENTRIES = 5000;

	public static function register(): void {
		add_filter( 'credential_vault_audit_max_entries', array( self::class, 'max_entries' ) );
		add_filter( 'credential_vault_audit_page_limit', array( self::class, 'page_limit' ) );
		add_action( 'credential_vault_audit_toolbar', array( self::class, 'render_search_fields' ), 5, 1 );
		add_filter( 'credential_vault_admin_tabs', array( self::class, 'register_tab' ) );
		add_action( 'credential_vault_render_admin_tab', array( self::class, 'render_automation_tab' ), 10, 1 );
	}

	/**
	 * @param array<string, string> $tabs
	 * @return array<string, string>
	 */
	public static function register_tab( array $tabs ): array {
		$tabs['automation'] = __( 'Automation', 'credential-vault-pro' );
		return $tabs;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function render_automation_tab( string $tab, array $context ): void {
		unset( $context );
		if ( 'automation' !== $tab ) {
			return;
		}

		include CREDENTIAL_VAULT_PRO_DIR . 'admin/views/automation-settings.php';
	}

	public static function max_entries( int $max ): int {
		unset( $max );
		return self::MAX_ENTRIES;
	}

	public static function page_limit( int $limit ): int {
		unset( $limit );
		return 250;
	}

	/**
	 * @param array<string, mixed> $filters
	 */
	public static function render_search_fields( array $filters ): void {
		if ( ! Secrets_Bridge::current_user_can_manage() ) {
			return;
		}

		$base = admin_url( 'admin.php?page=credential-vault&tab=audit' );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="new-vault-audit-filters">
			<input type="hidden" name="page" value="credential-vault" />
			<input type="hidden" name="tab" value="audit" />
			<label>
				<?php esc_html_e( 'Action', 'credential-vault-pro' ); ?>
				<input type="text" name="audit_action" value="<?php echo esc_attr( (string) ( $filters['action'] ?? '' ) ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Credential contains', 'credential-vault-pro' ); ?>
				<input type="text" name="audit_secret" value="<?php echo esc_attr( (string) ( $filters['secret'] ?? '' ) ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'User ID', 'credential-vault-pro' ); ?>
				<input type="number" min="0" name="audit_user" value="<?php echo esc_attr( (string) (int) ( $filters['user_id'] ?? 0 ) ); ?>" />
			</label>
			<?php submit_button( __( 'Filter', 'credential-vault-pro' ), 'secondary', '', false ); ?>
			<a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Reset', 'credential-vault-pro' ); ?></a>
		</form>
		<p class="description">
			<?php
			printf(
				/* translators: %d: max audit rows */
				esc_html__( 'Pro retains up to %d audit events (metadata only). Export CSV from this tab for client reports.', 'credential-vault-pro' ),
				(int) self::MAX_ENTRIES
			);
			?>
		</p>
		<?php
	}
}
