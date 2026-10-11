<?php
/**
 * Vault settings tab (sudo TTL and policy).
 *
 * @package CredentialVault
 *
 * @var bool                 $can_manage
 * @var array<string, mixed> $vault_settings
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Vault_Settings;

if ( ! $can_manage ) :
	?>
	<p><?php esc_html_e( 'You have read-only access to the vault.', 'credvault' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_export_metadata' ); ?>
		<input type="hidden" name="action" value="credential_vault_export_metadata" />
		<?php submit_button( __( 'Export metadata JSON', 'credvault' ), 'secondary', '', false ); ?>
	</form>
	<?php
	return;
endif;

$settings = $vault_settings ?? Vault_Settings::get();
?>
<div class="card new-vault-settings">
	<h2><?php esc_html_e( 'Security settings', 'credvault' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_save_settings' ); ?>
		<input type="hidden" name="action" value="credential_vault_save_settings" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="credential_vault_sudo_ttl"><?php esc_html_e( 'Sudo session length (seconds)', 'credvault' ); ?></label></th>
				<td>
					<input type="number" min="60" max="3600" step="60" name="credential_vault_sudo_ttl" id="credential_vault_sudo_ttl" value="<?php echo esc_attr( (string) (int) ( $settings['sudo_ttl'] ?? 600 ) ); ?>" class="small-text" />
					<p class="description"><?php esc_html_e( 'How long unlock lasts after password confirmation (default 600 seconds).', 'credvault' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sudo policy', 'credvault' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="credential_vault_sudo_mode" value="<?php echo esc_attr( Vault_Settings::SUDO_ALL_WRITES ); ?>" <?php checked( (string) ( $settings['sudo_mode'] ?? '' ), Vault_Settings::SUDO_ALL_WRITES ); ?> />
							<?php esc_html_e( 'Require sudo for all writes (imports, saves, metadata edits, copy)', 'credvault' ); ?>
						</label><br />
						<label>
							<input type="radio" name="credential_vault_sudo_mode" value="<?php echo esc_attr( Vault_Settings::SUDO_REVEAL_ONLY ); ?>" <?php checked( (string) ( $settings['sudo_mode'] ?? '' ), Vault_Settings::SUDO_REVEAL_ONLY ); ?> />
							<?php esc_html_e( 'Require sudo for reveal/copy only (metadata and secret saves use capability checks)', 'credvault' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save settings', 'credvault' ) ); ?>
	</form>
</div>

<div class="card new-vault-settings">
	<h2><?php esc_html_e( 'Break-glass read-only role', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Assign the “Credential Vault Auditor” role (or the view_credential_vault capability) to users who may view configured status and audit history but never reveal secret values.', 'credvault' ); ?>
	</p>
</div>

<div class="card new-vault-settings">
	<h2><?php esc_html_e( 'Metadata export', 'credvault' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Download slugs, labels, fingerprints, and rotation state — never secret values.', 'credvault' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_export_metadata' ); ?>
		<input type="hidden" name="action" value="credential_vault_export_metadata" />
		<?php submit_button( __( 'Export JSON', 'credvault' ), 'secondary', '', false ); ?>
	</form>
</div>
