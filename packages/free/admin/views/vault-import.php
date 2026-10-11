<?php
/**
 * Bulk import wizard tab.
 *
 * @package CredentialVault
 *
 * @var bool $sudo_active
 * @var bool $api_available
 * @var list<array<string, mixed>> $import_candidates
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';

?>
<div class="card new-vault-add">
	<h2><?php esc_html_e( 'Import from options table', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Moves known plaintext options into the Secrets API. Source options are left unchanged; imported secrets are flagged for rotation.', 'credvault' ); ?>
	</p>
	<?php if ( ! $api_available ) : ?>
		<p><?php esc_html_e( 'Secrets API required.', 'credvault' ); ?></p>
	<?php elseif ( ! $sudo_active ) : ?>
		<p><?php esc_html_e( 'Unlock the vault to import.', 'credvault' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'credential_vault_import' ); ?>
			<input type="hidden" name="action" value="credential_vault_import" />
			<table class="widefat striped">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="cv-import-all" /></td>
						<th><?php esc_html_e( 'Option', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Secret name', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Status', 'credvault' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $import_candidates as $row ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<?php if ( ! empty( $row['exists'] ) && ! empty( $row['is_string'] ) ) : ?>
									<input type="checkbox" name="import[]" value="<?php echo esc_attr( $row['option'] ); ?>" class="cv-import-row" />
								<?php endif; ?>
							</th>
							<td><code><?php echo esc_html( $row['option'] ); ?></code><br /><span class="description"><?php echo esc_html( $row['label'] ); ?></span></td>
							<td><code><?php echo esc_html( $row['suggested_name'] ); ?></code></td>
							<td>
								<?php
								if ( empty( $row['exists'] ) ) {
									esc_html_e( 'Not found', 'credvault' );
								} elseif ( empty( $row['is_string'] ) ) {
									esc_html_e( 'Not a string (manual migration)', 'credvault' );
								} else {
									esc_html_e( 'Ready', 'credvault' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Import selected', 'credvault' ) ); ?>
		</form>
	<?php endif; ?>
</div>
