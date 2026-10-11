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
 * @var bool                       $can_manage
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Vault_Settings;

$scope       = $scope ?? 'site';
$can_manage  = $can_manage ?? Secrets_Bridge::current_user_can_manage();
$can_edit    = $can_manage && ( $sudo_active || ! Vault_Settings::sudo_required_for_writes() );
$field_templates = Credential_Meta::service_field_templates();

?>
<table class="widefat striped new-vault-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Label', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Name', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Preview', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Fingerprint', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Environment', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Rotate by', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Rotation', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'credvault' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $credentials ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No credentials in this scope yet.', 'credvault' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $credentials as $row ) : ?>
				<?php
				$name      = (string) $row['name'];
				$meta      = Credential_Meta::get( $name );
				$label     = $meta['label'] ?? basename( $name );
				$preview   = $meta['masked_preview'] ?? '[protected]';
				$notes     = $meta['notes'] ?? '';
				$owner     = $meta['owner'] ?? '';
				$runbook   = $meta['runbook_url'] ?? '';
				$public    = $meta['public_fields'] ?? array();
				$env       = $meta['environment'] ?? '';
				$service   = $meta['service'] ?? 'custom';
				$rotate_by = (int) ( $meta['rotate_by'] ?? 0 );
				$env_labels = Credential_Meta::environment_choices();
				$has_prev  = ! empty( $row['has_previous'] );
				$needs_rot = ! empty( $row['needs_rotation'] );
				$in_vault  = 0 === strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' );
				$overdue   = $rotate_by > 0 && $rotate_by < time();
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $label ); ?></strong>
						<?php if ( $needs_rot ) : ?>
							<span class="new-vault-badge new-vault-badge--warn"><?php esc_html_e( 'Rotate recommended', 'credvault' ); ?></span>
						<?php endif; ?>
						<?php if ( $overdue ) : ?>
							<span class="new-vault-badge new-vault-badge--warn"><?php esc_html_e( 'Overdue', 'credvault' ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $owner ) : ?>
							<p class="description"><?php echo esc_html( sprintf( __( 'Owner: %s', 'credvault' ), $owner ) ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $notes ) : ?>
							<p class="description"><?php echo esc_html( $notes ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $runbook ) : ?>
							<p class="description"><a href="<?php echo esc_url( $runbook ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Runbook', 'credvault' ); ?></a></p>
						<?php endif; ?>
						<?php if ( is_array( $public ) && ! empty( $public ) ) : ?>
							<ul class="new-vault-public-fields">
								<?php foreach ( $public as $pk => $pv ) : ?>
									<li><code><?php echo esc_html( (string) $pk ); ?></code>: <?php echo esc_html( (string) $pv ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<td>
						<code>
							<?php
							if ( $sudo_active || ! $in_vault ) {
								echo esc_html( $name );
							} else {
								echo esc_html( Secrets_Bridge::VAULT_NAMESPACE . '/••••••' );
							}
							?>
						</code>
					</td>
					<td><code class="new-vault-preview"><?php echo esc_html( ( $sudo_active && $in_vault ) ? $preview : '••••••••' ); ?></code></td>
					<td class="new-vault-fingerprint"><code><?php echo esc_html( substr( (string) $row['fingerprint'], 0, 16 ) . '…' ); ?></code></td>
					<td>
						<?php
						if ( '' !== $env && isset( $env_labels[ $env ] ) ) {
							$badge = Credential_Meta::environment_badge_class( $env );
							printf(
								'<span class="new-vault-badge %1$s">%2$s</span>',
								esc_attr( $badge ),
								esc_html( $env_labels[ $env ] )
							);
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<?php
						if ( $rotate_by > 0 ) {
							echo esc_html( wp_date( get_option( 'date_format' ), $rotate_by ) );
						} else {
							echo '—';
						}
						?>
					</td>
					<td>
						<?php if ( $has_prev ) : ?>
							<span class="new-vault-badge"><?php esc_html_e( 'Previous slot in use', 'credvault' ); ?></span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td class="new-vault-actions">
						<?php if ( $can_edit && $api_available && $in_vault ) : ?>
							<details class="new-vault-meta-details">
								<summary><?php esc_html_e( 'Details', 'credvault' ); ?></summary>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'credential_vault_update_meta' ); ?>
									<input type="hidden" name="action" value="credential_vault_update_meta" />
									<input type="hidden" name="secret_name" value="<?php echo esc_attr( $name ); ?>" />
									<input type="hidden" name="credential_scope" value="<?php echo esc_attr( $scope ); ?>" />
									<p>
										<label><?php esc_html_e( 'Label', 'credvault' ); ?>
											<input type="text" name="credential_label" class="regular-text" value="<?php echo esc_attr( $label ); ?>" />
										</label>
									</p>
									<p>
										<label><?php esc_html_e( 'Environment', 'credvault' ); ?>
											<select name="credential_environment">
												<?php foreach ( $env_labels as $env_key => $env_label ) : ?>
													<option value="<?php echo esc_attr( $env_key ); ?>" <?php selected( $env, $env_key ); ?>><?php echo esc_html( $env_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
									<p>
										<label><?php esc_html_e( 'Rotate by', 'credvault' ); ?>
											<input type="date" name="credential_rotate_by" value="<?php echo esc_attr( $rotate_by > 0 ? gmdate( 'Y-m-d', $rotate_by ) : '' ); ?>" />
										</label>
									</p>
									<p>
										<label><?php esc_html_e( 'Owner', 'credvault' ); ?>
											<input type="text" name="credential_owner" class="regular-text" value="<?php echo esc_attr( $owner ); ?>" />
										</label>
									</p>
									<p>
										<label><?php esc_html_e( 'Runbook URL', 'credvault' ); ?>
											<input type="url" name="credential_runbook_url" class="large-text" value="<?php echo esc_attr( $runbook ); ?>" />
										</label>
									</p>
									<?php if ( isset( $field_templates[ $service ] ) ) : ?>
										<?php foreach ( $field_templates[ $service ] as $field ) : ?>
											<?php
											$field_id = (string) ( $field['id'] ?? '' );
											if ( '' === $field_id ) {
												continue;
											}
											$input_name = 'credential_public_' . $field_id;
											$val        = is_array( $public ) ? (string) ( $public[ $field_id ] ?? '' ) : '';
											?>
											<p>
												<label><?php echo esc_html( (string) ( $field['label'] ?? $field_id ) ); ?>
													<input type="text" name="<?php echo esc_attr( $input_name ); ?>" class="large-text" value="<?php echo esc_attr( $val ); ?>" />
												</label>
											</p>
										<?php endforeach; ?>
									<?php endif; ?>
									<p>
										<label><?php esc_html_e( 'Notes', 'credvault' ); ?>
											<textarea name="credential_notes" rows="2" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
										</label>
									</p>
									<?php submit_button( __( 'Save details', 'credvault' ), 'secondary small', '', false ); ?>
								</form>
							</details>
						<?php endif; ?>
						<?php if ( $can_edit && $api_available && $in_vault && $writable ) : ?>
							<?php
							$rotate_tab = 'network' === $scope ? 'network' : 'credentials';
							$rotate_url = add_query_arg(
								array(
									'tab'    => $rotate_tab,
									'slug'   => basename( $name ),
									'rotate' => '1',
								),
								admin_url( 'admin.php?page=credential-vault' )
							);
							?>
							<a class="button button-small" href="<?php echo esc_url( $rotate_url ); ?>"><?php esc_html_e( 'Rotate', 'credvault' ); ?></a>
						<?php endif; ?>
						<?php if ( $sudo_active && $can_manage && $api_available && $in_vault ) : ?>
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
