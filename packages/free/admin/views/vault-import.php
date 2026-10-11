<?php
/**
 * Bulk import wizard tab.
 *
 * @package CredentialVault
 *
 * @var bool $sudo_active
 * @var bool $api_available
 * @var list<array<string, mixed>> $import_candidates
 * @var list<array<string, mixed>> $scan_results
 * @var list<array<string, mixed>> $hygiene_issues
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;

include CREDENTIAL_VAULT_DIR . 'admin/views/vault-sudo-panel.php';

$hygiene_issues = $hygiene_issues ?? array();

?>
<?php if ( ! empty( $hygiene_issues ) ) : ?>
<div class="card new-vault-hygiene">
	<h2><?php esc_html_e( 'Duplicate / orphan hygiene', 'credvault' ); ?></h2>
	<p class="description"><?php esc_html_e( 'These sources still hold plaintext while the vault already stores the same integration.', 'credvault' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Integration', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'Vault name', 'credvault' ); ?></th>
				<th><?php esc_html_e( 'Source', 'credvault' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $hygiene_issues as $issue ) : ?>
				<tr>
					<td>
						<span class="new-vault-heading-tip">
							<?php echo esc_html( (string) ( $issue['label'] ?? '' ) ); ?>
							<?php
							$detail = (string) ( $issue['detail'] ?? '' );
							if ( '' !== $detail ) {
								echo Field_Help::icon( $detail );
							}
							?>
						</span>
					</td>
					<td><code><?php echo esc_html( (string) ( $issue['vault_name'] ?? '' ) ); ?></code></td>
					<td><code><?php echo esc_html( (string) ( $issue['source'] ?? '' ) ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
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

<div class="card new-vault-add">
	<h2><?php esc_html_e( 'Plaintext scanner', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Heuristic scan of wp_options, user meta, and common plugin options for API-key-shaped values. Only masked previews are shown; import moves selected rows into encrypted storage.', 'credvault' ); ?>
	</p>
	<?php if ( ! $api_available ) : ?>
		<p><?php esc_html_e( 'Secrets API required.', 'credvault' ); ?></p>
	<?php elseif ( empty( $scan_results ) ) : ?>
		<p><?php esc_html_e( 'No likely plaintext API keys detected in the curated list or option name scan.', 'credvault' ); ?></p>
	<?php elseif ( ! $sudo_active ) : ?>
		<p><?php esc_html_e( 'Unlock the vault to import scan results.', 'credvault' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'credential_vault_scan_import' ); ?>
			<input type="hidden" name="action" value="credential_vault_scan_import" />
			<table class="widefat striped">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="cv-scan-all" /></td>
						<th><?php esc_html_e( 'Source', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Type', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Match', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Preview', 'credvault' ); ?></th>
						<th><?php esc_html_e( 'Secret name', 'credvault' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $scan_results as $row ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="scan_import[]" value="<?php echo esc_attr( (string) ( $row['import_key'] ?? $row['option'] ) ); ?>" class="cv-scan-row" />
							</th>
							<td><code><?php echo esc_html( $row['option'] ); ?></code><br /><span class="description"><?php echo esc_html( $row['label'] ); ?></span></td>
							<td><?php echo esc_html( (string) ( $row['source_type'] ?? 'option' ) ); ?></td>
							<td><?php echo esc_html( $row['reason'] ); ?></td>
							<td><code class="new-vault-preview"><?php echo esc_html( $row['preview_hint'] ); ?></code></td>
							<td><code><?php echo esc_html( $row['suggested_name'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Import selected scan results', 'credvault' ) ); ?>
		</form>
	<?php endif; ?>
</div>
