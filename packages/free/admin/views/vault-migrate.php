<?php
/**
 * Encrypted bundle export/import UI.
 *
 * @package CredentialVault
 *
 * @var bool $api_available
 * @var bool $writable
 * @var bool $sudo_active
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<h2><?php esc_html_e( 'Migration bundles', 'credvault' ); ?></h2>
<p class="description"><?php esc_html_e( 'Password-protected AES-256-GCM bundles for moving metadata and optional secret values between environments. All imports are audited.', 'credvault' ); ?></p>

<?php if ( ! $api_available ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Secrets API required.', 'credvault' ); ?></p></div>
	<?php return; ?>
<?php endif; ?>

<div class="card">
	<h3><?php esc_html_e( 'Export bundle', 'credvault' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_bundle_export' ); ?>
		<input type="hidden" name="action" value="credential_vault_bundle_export" />
		<p>
			<label><?php esc_html_e( 'Encryption password', 'credvault' ); ?>
				<input type="password" name="bundle_password" class="regular-text" required autocomplete="new-password" />
			</label>
		</p>
		<p><label><input type="checkbox" name="bundle_include_values" value="1" /> <?php esc_html_e( 'Include secret values (requires active sudo)', 'credvault' ); ?></label></p>
		<p><label><input type="checkbox" name="bundle_network" value="1" /> <?php esc_html_e( 'Network scope', 'credvault' ); ?></label></p>
		<?php submit_button( __( 'Download bundle', 'credvault' ), 'primary', '', false ); ?>
	</form>
</div>

<div class="card">
	<h3><?php esc_html_e( 'Import bundle', 'credvault' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( 'credential_vault_bundle_import' ); ?>
		<input type="hidden" name="action" value="credential_vault_bundle_import" />
		<p>
			<label><?php esc_html_e( 'Bundle file (base64 text or .cvbundle)', 'credvault' ); ?>
				<input type="file" name="bundle_file" accept=".txt,.cvbundle,text/plain" required />
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Decryption password', 'credvault' ); ?>
				<input type="password" name="bundle_password" class="regular-text" required autocomplete="current-password" />
			</label>
		</p>
		<p><label><input type="checkbox" name="bundle_skip_existing" value="1" /> <?php esc_html_e( 'Skip slugs that already exist', 'credvault' ); ?></label></p>
		<p><label><input type="checkbox" name="bundle_dry_run" value="1" /> <?php esc_html_e( 'Dry run (preview slugs only)', 'credvault' ); ?></label></p>
		<?php submit_button( __( 'Import bundle', 'credvault' ), 'secondary', '', false ); ?>
	</form>
</div>

<p class="description"><?php esc_html_e( 'CLI: wp credvault bundle_export / bundle_import', 'credvault' ); ?></p>
