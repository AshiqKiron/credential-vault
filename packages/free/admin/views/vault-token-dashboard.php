<?php
/**
 * Centralized token dashboard for common third-party integrations.
 *
 * @package CredentialVault
 *
 * @var bool   $api_available
 * @var bool   $sudo_active
 * @var int    $sudo_remaining
 * @var bool   $writable
 * @var list<array<string, mixed>> $credentials
 * @var list<array<string, mixed>> $audit_entries
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;
use CredentialVault\Credential_Meta;

$integrations = Credential_Meta::token_integrations();
$base_url     = admin_url( 'admin.php?page=credential-vault' );
$manage_url   = add_query_arg( 'tab', 'credentials', $base_url );

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';
include CREDENTIAL_VAULT_DIR . 'admin/views/vault-onboarding.php';

if ( ! empty( $vault_health ) && is_array( $vault_health ) ) :
	$attention = (int) ( $vault_health['overdue_rotate_by'] ?? 0 )
		+ (int) ( $vault_health['plaintext_candidates'] ?? 0 )
		+ (int) ( $vault_health['undecryptable'] ?? 0 );
	if ( $attention > 0 ) :
		?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				esc_html_e( 'Vault health items need attention.', 'credvault' );
				echo ' ';
				printf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=credential-vault&tab=health' ) ),
					esc_html__( 'View health summary', 'credvault' )
				);
				?>
			</p>
		</div>
		<?php
	endif;
endif;

?>
<p class="description">
	<?php esc_html_e( 'Manage encrypted API keys for popular services. Values are stored in the WordPress Secrets API; unlock the vault to view previews or save changes.', 'credvault' ); ?>
</p>

<?php if ( ! $api_available ) : ?>
	<p><?php esc_html_e( 'Secrets API required before tokens can be stored.', 'credvault' ); ?></p>
<?php else : ?>
	<div class="new-vault-integrations">
		<?php foreach ( $integrations as $integration ) : ?>
			<?php
			$status = Credential_Meta::integration_status( $integration, $credentials );
			if ( null === $status ) {
				continue;
			}
			$configure_url = add_query_arg(
				array(
					'tab'     => 'credentials',
					'service' => $integration['service'],
					'slug'    => $integration['slug'],
					'label'   => rawurlencode( $integration['label'] ),
				),
				$base_url
			);
			?>
			<div class="new-vault-integration card">
				<header class="new-vault-integration__head">
					<h2 class="new-vault-integration__title new-vault-heading-tip">
						<?php echo esc_html( $integration['label'] ); ?>
						<?php echo Field_Help::icon( (string) $integration['description'] ); ?>
					</h2>
					<?php if ( $status['configured'] ) : ?>
						<span class="new-vault-badge new-vault-badge--active"><?php esc_html_e( 'Configured', 'credvault' ); ?></span>
					<?php else : ?>
						<span class="new-vault-badge"><?php esc_html_e( 'Not configured', 'credvault' ); ?></span>
					<?php endif; ?>
					<?php if ( $status['needs_rotation'] ) : ?>
						<span class="new-vault-badge new-vault-badge--warn"><?php esc_html_e( 'Rotate recommended', 'credvault' ); ?></span>
					<?php endif; ?>
				</header>
				<?php if ( $status['configured'] && $sudo_active && '' !== $status['masked_preview'] ) : ?>
					<p>
						<strong><?php esc_html_e( 'Preview:', 'credvault' ); ?></strong>
						<code class="new-vault-preview"><?php echo esc_html( $status['masked_preview'] ); ?></code>
					</p>
				<?php elseif ( $status['configured'] ) : ?>
					<p class="description"><?php esc_html_e( 'Unlock the vault to view a masked preview.', 'credvault' ); ?></p>
				<?php endif; ?>
				<p class="new-vault-integration__actions">
					<?php if ( $writable ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $configure_url ); ?>">
							<?php echo $status['configured'] ? esc_html__( 'Update key', 'credvault' ) : esc_html__( 'Add key', 'credvault' ); ?>
						</a>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( $manage_url ); ?>"><?php esc_html_e( 'All credentials', 'credvault' ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e( 'Recent vault activity', 'credvault' ); ?></h2>
	<p class="description">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit', $base_url ) ); ?>"><?php esc_html_e( 'View full audit log', 'credvault' ); ?></a>
	</p>
	<?php
	$audit_entries = array_slice( $audit_entries, 0, 5 );
	include CREDENTIAL_VAULT_DIR . 'admin/views/vault-audit.php';
	?>
<?php endif; ?>
