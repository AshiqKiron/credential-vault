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

use CredentialVault\Admin\Field_Help;
use CredentialVault\Audit_Activity;
use CredentialVault\Credential_Meta;
use CredentialVault\Environment_Guard;
use CredentialVault\Integration_Snippets;
use CredentialVault\Secret_Collections;
use CredentialVault\Secrets_Bridge;
use CredentialVault\Vault_Settings;

$audit_activity = $audit_activity ?? array();

$scope       = $scope ?? 'site';
$can_manage  = $can_manage ?? Secrets_Bridge::current_user_can_manage();
$can_edit    = $can_manage && ( $sudo_active || ! Vault_Settings::sudo_required_for_writes() );
$field_templates = Credential_Meta::service_field_templates();

?>
<div class="new-vault-table-scroll">
<table class="widefat striped new-vault-table">
	<thead>
		<tr>
			<?php if ( $can_manage ) : ?>
				<th class="check-column"><input type="checkbox" id="cv-bulk-all" aria-label="<?php esc_attr_e( 'Select all', 'credvault' ); ?>" /></th>
			<?php endif; ?>
			<th><?php esc_html_e( 'Credential', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Status', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Schedule', 'credvault' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'credvault' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $credentials ) ) : ?>
			<tr>
				<td colspan="<?php echo $can_manage ? 5 : 4; ?>">
					<?php esc_html_e( 'No credentials in this scope yet.', 'credvault' ); ?>
					<?php if ( ! empty( $demo_can_install ) && 'site' === $scope ) : ?>
						<?php
						$redirect_tab = 'credentials';
						$button_style = 'secondary';
						include CREDENTIAL_VAULT_DIR . 'admin/partials/vault-demo-install-form.php';
						?>
					<?php endif; ?>
				</td>
			</tr>
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
				$collection = (string) ( $meta['collection'] ?? '' );
				$env_labels = Credential_Meta::environment_choices();
				$has_prev  = ! empty( $row['has_previous'] );
				$needs_rot = ! empty( $row['needs_rotation'] );
				$in_vault  = 0 === strpos( $name, Secrets_Bridge::VAULT_NAMESPACE . '/' );
				$overdue   = $rotate_by > 0 && $rotate_by < time();
				$slug      = $in_vault ? basename( $name ) : sanitize_key( basename( $name ) );
				$snippets  = $in_vault ? Integration_Snippets::for_slug( $slug, 'network' === $scope ) : array();
				$activity  = $audit_activity[ $name ] ?? null;
				$env_warn  = $in_vault ? Environment_Guard::mismatch_for_secret( $name ) : null;
				$has_meta  = '' !== $owner || '' !== $notes || '' !== $runbook || ( is_array( $public ) && ! empty( $public ) );
				$code_sample = '';
				if ( ! empty( $snippets ) ) {
					$parts = array_filter(
						array(
							(string) ( $snippets['php_get'] ?? '' ),
							(string) ( $snippets['php_context'] ?? '' ),
							(string) ( $snippets['php_adapter'] ?? '' ),
						)
					);
					$code_sample = implode( "\n\n", $parts );
				}
				?>
				<tr>
					<?php if ( $can_manage ) : ?>
						<th scope="row" class="check-column">
							<?php if ( $in_vault ) : ?>
								<input type="checkbox" class="cv-bulk-row" name="bulk_secrets[]" value="<?php echo esc_attr( $name ); ?>" form="cv-bulk-meta-form" />
							<?php endif; ?>
						</th>
					<?php endif; ?>
					<td class="new-vault-credential">
						<strong class="new-vault-credential__label"><?php echo esc_html( $label ); ?></strong>
						<?php
						$show_name = $sudo_active || ! $in_vault;
						if ( $show_name && $in_vault ) {
							$primary    = basename( $name );
							$name_title = $name;
						} elseif ( $show_name ) {
							$primary    = $name;
							$name_title = $name;
						} else {
							$primary    = Secrets_Bridge::VAULT_NAMESPACE . '/••••••';
							$name_title = '';
						}
						$fingerprint  = (string) $row['fingerprint'];
						$short_fp     = strlen( $fingerprint ) > 12 ? substr( $fingerprint, 0, 12 ) . '…' : $fingerprint;
						$preview_text = ( $sudo_active && $in_vault ) ? $preview : '••••••••';
						?>
						<code class="new-vault-credential__slug"<?php echo '' !== $name_title ? ' title="' . esc_attr( $name_title ) . '"' : ''; ?>>
							<?php
							$name_parts = explode( '/', $primary );
							$last_part  = count( $name_parts ) - 1;
							foreach ( $name_parts as $index => $part ) {
								echo esc_html( $part );
								if ( $index < $last_part ) {
									echo '/<wbr>';
								}
							}
							?>
						</code>
						<dl class="new-vault-facts">
							<div>
								<dt><?php esc_html_e( 'Preview', 'credvault' ); ?></dt>
								<dd><code class="new-vault-preview"><?php echo esc_html( $preview_text ); ?></code></dd>
							</div>
							<?php if ( '' !== $fingerprint ) : ?>
								<div>
									<dt><?php esc_html_e( 'Fingerprint', 'credvault' ); ?></dt>
									<dd><code class="new-vault-fingerprint" title="<?php echo esc_attr( $fingerprint ); ?>"><?php echo esc_html( $short_fp ); ?></code></dd>
								</div>
							<?php endif; ?>
						</dl>
						<?php if ( $has_meta ) : ?>
							<details class="new-vault-meta-details">
								<summary><?php esc_html_e( 'Notes & metadata', 'credvault' ); ?></summary>
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
							</details>
						<?php endif; ?>
						<?php if ( '' !== $code_sample ) : ?>
							<details class="new-vault-meta-details">
								<summary class="new-vault-heading-tip">
									<?php
									echo Field_Help::heading(
										__( 'Code samples', 'credvault' ),
										__( 'Copy into theme or plugin code. Secret values are never shown here.', 'credvault' )
									);
									?>
								</summary>
								<pre class="new-vault-code-sample"><code><?php echo esc_html( $code_sample ); ?></code></pre>
							</details>
						<?php endif; ?>
					</td>
					<td class="new-vault-status">
						<?php
						$has_status = false;
						if ( '' !== $env && isset( $env_labels[ $env ] ) ) {
							$has_status = true;
							$badge      = Credential_Meta::environment_badge_class( $env );
							printf(
								'<span class="new-vault-badge %1$s">%2$s</span>',
								esc_attr( $badge ),
								esc_html( $env_labels[ $env ] )
							);
						}
						if ( $overdue ) {
							$has_status = true;
							echo '<span class="new-vault-badge new-vault-badge--warn">' . esc_html__( 'Overdue', 'credvault' ) . '</span>';
						} elseif ( $needs_rot ) {
							$has_status = true;
							echo '<span class="new-vault-badge new-vault-badge--warn">' . esc_html__( 'Rotate recommended', 'credvault' ) . '</span>';
						}
						if ( null !== $env_warn ) {
							$has_status = true;
							printf(
								'<span class="new-vault-badge new-vault-badge--warn" title="%1$s">%2$s</span>',
								esc_attr( $env_warn ),
								esc_html__( 'Env mismatch', 'credvault' )
							);
						}
						if ( $has_prev ) {
							$has_status = true;
							printf(
								'<span class="new-vault-badge" title="%1$s">%2$s</span>',
								esc_attr__( 'The previous secret is still stored until you retire it.', 'credvault' ),
								esc_html__( 'Old key kept', 'credvault' )
							);
						}
						if ( ! $has_status ) {
							echo '—';
						}
						?>
					</td>
					<td>
						<div class="new-vault-timing">
							<div>
								<span class="new-vault-kicker"><?php esc_html_e( 'Rotate by', 'credvault' ); ?></span>
								<?php
								if ( $rotate_by > 0 ) {
									$abs  = wp_date( 'M j, Y', $rotate_by );
									$diff = human_time_diff( $rotate_by, time() );
									if ( $overdue ) {
										/* translators: %s: human time difference, e.g. "16 days". */
										$rel = sprintf( __( '%s overdue', 'credvault' ), $diff );
									} else {
										/* translators: %s: human time difference, e.g. "20 days". */
										$rel = sprintf( __( 'Due in %s', 'credvault' ), $diff );
									}
									$full = wp_date( get_option( 'date_format' ), $rotate_by );
									?>
									<span class="new-vault-when__rel<?php echo $overdue ? ' new-vault-when__rel--overdue' : ''; ?>" title="<?php echo esc_attr( $full ); ?>"><?php echo esc_html( $rel ); ?></span>
									<span class="new-vault-when__abs"><?php echo esc_html( $abs ); ?></span>
									<?php
								} else {
									echo '<span class="new-vault-when__abs">' . esc_html__( 'Not scheduled', 'credvault' ) . '</span>';
								}
								?>
							</div>
							<div>
								<span class="new-vault-kicker"><?php esc_html_e( 'Last activity', 'credvault' ); ?></span>
								<?php
								if ( is_array( $activity ) && ! empty( $activity['time'] ) ) {
									$activity_time = (int) $activity['time'];
									$full          = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $activity_time );
									/* translators: %s: human time difference, e.g. "6 hours". */
									$rel    = sprintf( __( '%s ago', 'credvault' ), human_time_diff( $activity_time, time() ) );
									$action = Audit_Activity::action_label( (string) ( $activity['action'] ?? '' ) );
									?>
									<span class="new-vault-when__rel" title="<?php echo esc_attr( $full ); ?>"><?php echo esc_html( $rel ); ?></span>
									<?php if ( '' !== $action ) : ?>
										<span class="new-vault-when__abs"><?php echo esc_html( $action ); ?></span>
									<?php endif; ?>
									<?php
								} else {
									echo '<span class="new-vault-when__abs">' . esc_html__( 'No activity yet', 'credvault' ) . '</span>';
								}
								?>
							</div>
						</div>
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
										<label><?php echo Field_Help::inline( __( 'Label', 'credvault' ), Field_Help::credential_tip( 'label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<input type="text" name="credential_label" class="regular-text" value="<?php echo esc_attr( $label ); ?>" />
										</label>
									</p>
									<p>
										<label><?php echo Field_Help::inline( __( 'Collection', 'credvault' ), Field_Help::credential_tip( 'collection' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<select name="credential_collection">
												<?php foreach ( Secret_Collections::choices() as $col_key => $col_label ) : ?>
													<option value="<?php echo esc_attr( $col_key ); ?>" <?php selected( $collection, $col_key ); ?>><?php echo esc_html( $col_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
									<p>
										<label><?php echo Field_Help::inline( __( 'Environment', 'credvault' ), Field_Help::credential_tip( 'environment' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<select name="credential_environment">
												<?php foreach ( $env_labels as $env_key => $env_label ) : ?>
													<option value="<?php echo esc_attr( $env_key ); ?>" <?php selected( $env, $env_key ); ?>><?php echo esc_html( $env_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
									<p>
										<label><?php echo Field_Help::inline( __( 'Rotate by', 'credvault' ), Field_Help::credential_tip( 'rotate_by' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<input type="date" name="credential_rotate_by" value="<?php echo esc_attr( $rotate_by > 0 ? gmdate( 'Y-m-d', $rotate_by ) : '' ); ?>" />
										</label>
									</p>
									<p>
										<label><?php echo Field_Help::inline( __( 'Owner', 'credvault' ), Field_Help::credential_tip( 'owner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<input type="text" name="credential_owner" class="regular-text" value="<?php echo esc_attr( $owner ); ?>" />
										</label>
									</p>
									<p>
										<label><?php echo Field_Help::inline( __( 'Runbook URL', 'credvault' ), Field_Help::credential_tip( 'runbook_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
												<label><?php echo Field_Help::inline( (string) ( $field['label'] ?? $field_id ), Field_Help::credential_tip( $field_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
													<input type="text" name="<?php echo esc_attr( $input_name ); ?>" class="large-text" value="<?php echo esc_attr( $val ); ?>" />
												</label>
											</p>
										<?php endforeach; ?>
									<?php endif; ?>
									<p>
										<label><?php echo Field_Help::inline( __( 'Notes', 'credvault' ), Field_Help::credential_tip( 'notes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
							<span class="description"><?php esc_html_e( 'Unlock the vault to manage', 'credvault' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
</div>
