<?php
/**
 * Vault settings tab (sudo TTL and policy).
 *
 * @package CredentialVault
 *
 * @var bool                 $can_manage
 * @var bool                 $api_available
 * @var bool                 $writable
 * @var bool                 $demo_data_ui
 * @var bool                 $demo_data_installed
 * @var int                  $demo_data_count
 * @var array<string, mixed> $vault_settings
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;
use CredentialVault\Demo_Data;
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
				<th scope="row"><?php echo Field_Help::th( __( 'Sudo session length (seconds)', 'credvault' ), Field_Help::settings_tip( 'sudo_ttl' ), 'credential_vault_sudo_ttl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<td>
					<input type="number" min="60" max="3600" step="60" name="credential_vault_sudo_ttl" id="credential_vault_sudo_ttl" value="<?php echo esc_attr( (string) (int) ( $settings['sudo_ttl'] ?? 600 ) ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo Field_Help::th( __( 'Sudo policy', 'credvault' ), Field_Help::settings_tip( 'sudo_mode' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="credential_vault_sudo_mode" value="<?php echo esc_attr( Vault_Settings::SUDO_ALL_WRITES ); ?>" <?php checked( (string) ( $settings['sudo_mode'] ?? '' ), Vault_Settings::SUDO_ALL_WRITES ); ?> />
							<?php echo Field_Help::inline( __( 'All writes require sudo', 'credvault' ), Field_Help::settings_tip( 'sudo_all_writes' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</label><br />
						<label>
							<input type="radio" name="credential_vault_sudo_mode" value="<?php echo esc_attr( Vault_Settings::SUDO_REVEAL_ONLY ); ?>" <?php checked( (string) ( $settings['sudo_mode'] ?? '' ), Vault_Settings::SUDO_REVEAL_ONLY ); ?> />
							<?php echo Field_Help::inline( __( 'Sudo for reveal/copy only', 'credvault' ), Field_Help::settings_tip( 'sudo_reveal_only' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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

<?php
$demo_ui        = ! empty( $demo_data_ui );
$demo_installed = ! empty( $demo_data_installed );
$demo_count     = isset( $demo_data_count ) ? (int) $demo_data_count : 0;
$can_demo       = ! empty( $demo_can_install );
?>
<?php if ( $demo_ui ) : ?>
<div class="card new-vault-settings">
	<h2><?php esc_html_e( 'Demo data', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Install fake credentials (slug prefix “demo-”) to explore the dashboard, rotation reminders, environments, and audit log. Values are not real secrets.', 'credvault' ); ?>
	</p>
	<?php if ( ! $can_demo ) : ?>
		<p class="description">
			<?php esc_html_e( 'Demo install requires storage (Secrets API or local dev mode with WP_DEBUG) and manage access.', 'credvault' ); ?>
		</p>
	<?php else : ?>
		<?php if ( $demo_installed ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: number of tracked demo credentials */
					esc_html( _n( '%d demo credential is tracked.', '%d demo credentials are tracked.', $demo_count, 'credvault' ) ),
					$demo_count
				);
				?>
			</p>
		<?php endif; ?>
		<?php
		$redirect_tab = 'settings';
		$button_style = 'secondary';
		include CREDENTIAL_VAULT_DIR . 'admin/partials/vault-demo-install-form.php';
		?>
		<?php if ( $demo_installed ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Remove all tracked demo credentials?', 'credvault' ) ); ?>');">
				<?php wp_nonce_field( 'credential_vault_remove_demo' ); ?>
				<input type="hidden" name="action" value="credential_vault_remove_demo" />
				<input type="hidden" name="redirect_tab" value="settings" />
				<?php submit_button( __( 'Remove demo data', 'credvault' ), 'delete', '', false ); ?>
			</form>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: slug prefix */
				esc_html__( 'Slugs use the prefix “%s”. Disable this section with the credential_vault_demo_data_ui filter.', 'credvault' ),
				esc_html( Demo_Data::SLUG_PREFIX )
			);
			?>
		</p>
	<?php endif; ?>
</div>
<?php endif; ?>

<div class="card new-vault-settings">
	<h2><?php esc_html_e( 'Metadata export', 'credvault' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Download slugs, labels, fingerprints, and rotation state — never secret values.', 'credvault' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_export_metadata' ); ?>
		<input type="hidden" name="action" value="credential_vault_export_metadata" />
		<?php submit_button( __( 'Export JSON', 'credvault' ), 'secondary', '', false ); ?>
	</form>
</div>
