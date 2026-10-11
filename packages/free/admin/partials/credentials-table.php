<?php
/**
 * Shared credentials table partial.
 *
 * @package CredentialVault
 *
 * @var list<array<string, mixed>> $credentials
 * @var bool                       $sudo_active
 * @var bool                       $api_available
 * @var bool                       $writable
 * @var string                     $scope site|network
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

$scope = $scope ?? 'site';

?>
<table class="widefat striped new-vault-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Label', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Name', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Preview', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Fingerprint', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Rotation', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'credvault' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $credentials ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No credentials in this scope yet.', 'credvault' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $credentials as $row ) : ?>
				<?php
				$name      = (string) $row['name'];
				$meta      = Credential_Meta::get( $name );
				$label     = $meta['label'] ?? basename( $name );
				$preview   = $meta['masked_preview'] ?? '[protected]';
				$has_prev  = ! empty( $row['has_previous'] );
				$needs_rot = ! empty( $row['needs_rotation'] );
				$in_vault  = 0 === strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $label ); ?></strong>
						<?php if ( $needs_rot ) : ?>
							<span class="new-vault-badge new-vault-badge--warn"><?php esc_html_e( 'Rotate recommended', 'credvault' ); ?></span>
						<?php endif; ?>
					</td>
					<td><code><?php echo esc_html( $name ); ?></code></td>
					<td><code class="new-vault-preview"><?php echo esc_html( ( $sudo_active && $in_vault ) ? $preview : '••••••••' ); ?></code></td>
					<td class="new-vault-fingerprint"><code><?php echo esc_html( substr( (string) $row['fingerprint'], 0, 16 ) . '…' ); ?></code></td>
					<td>
						<?php if ( $has_prev ) : ?>
							<span class="new-vault-badge"><?php esc_html_e( 'Previous slot in use', 'credvault' ); ?></span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td class="new-vault-actions">
						<?php if ( $sudo_active && $api_available && $in_vault ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-inline">
								<?php wp_nonce_field( 'credential_vault_copy' ); ?>
								<input type="hidden" name="action" value="credential_vault_copy" />
								<input type="hidden" name="secret_name" value="<?php echo esc_attr( $name ); ?>" />
								<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
								<button type="submit" class="button button-small"><?php esc_html_e( 'Copy', 'credvault' ); ?></button>
							</form>
							<?php if ( $has_prev && $writable ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-inline">
									<?php wp_nonce_field( 'credential_vault_retire' ); ?>
									<input type="hidden" name="action" value="credential_vault_retire" />
									<input type="hidden" name="secret_name" value="<?php echo esc_attr( $name ); ?>" />
									<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
									<button type="submit" class="button button-small"><?php esc_html_e( 'Retire previous', 'credvault' ); ?></button>
								</form>
							<?php endif; ?>
							<?php if ( $writable ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this credential permanently?', 'credvault' ) ); ?>');">
									<?php wp_nonce_field( 'credential_vault_delete' ); ?>
									<input type="hidden" name="action" value="credential_vault_delete" />
									<input type="hidden" name="secret_name" value="<?php echo esc_attr( $name ); ?>" />
									<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
									<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'credvault' ); ?></button>
								</form>
							<?php endif; ?>
						<?php elseif ( ! $in_vault ) : ?>
							<span class="description"><?php esc_html_e( 'Owned by another plugin', 'credvault' ); ?></span>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Unlock to manage', 'credvault' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
