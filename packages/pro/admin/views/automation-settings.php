<?php
/**
 * Pro automation & agency settings.
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;
use CredentialVaultPro\Api_Tokens;
use CredentialVaultPro\Approval_Queue;
use CredentialVaultPro\Audit_Store;
use CredentialVaultPro\Env_Sync;
use CredentialVaultPro\License_Seats;
use CredentialVaultPro\Plugin_Adapters;
use CredentialVaultPro\Sso_Gate;
use CredentialVaultPro\Template_Credentials;
use CredentialVaultPro\Webhooks;

$hooks = Webhooks::all();
if ( empty( $hooks ) ) {
	$hooks = array(
		array(
			'url'     => '',
			'secret'  => '',
			'enabled' => true,
			'events'  => array( 'all' ),
			'format'  => 'generic',
		),
	);
}

$new_token = get_transient( 'credential_vault_pro_new_token_' . get_current_user_id() );
if ( is_string( $new_token ) && '' !== $new_token ) {
	delete_transient( 'credential_vault_pro_new_token_' . get_current_user_id() );
}

$license = License_Seats::status();
$templates = Template_Credentials::all();
$pending = Approval_Queue::pending();
$env_map = Env_Sync::mapping();
$env_lines = '';
foreach ( $env_map as $env => $slug ) {
	$env_lines .= $env . '=' . $slug . "\n";
}

?>
<?php if ( is_string( $new_token ) && '' !== $new_token ) : ?>
<div class="notice notice-success"><p>
	<?php esc_html_e( 'Copy this API token now — it will not be shown again:', 'credential-vault-pro' ); ?>
	<code><?php echo esc_html( $new_token ); ?></code>
</p></div>
<?php endif; ?>

<div class="card new-vault-add">
	<h2 class="new-vault-heading-tip">
		<?php esc_html_e( 'Webhook notifications', 'credential-vault-pro' ); ?>
		<?php
		echo Field_Help::icon(
			__( 'Events: rotate, retire, failed rotation tests, undecryptable secrets, overdue rotation, and anomalies. Formats: generic HTTPS, Slack incoming webhook, PagerDuty Events v2 (use routing key as signing secret).', 'credential-vault-pro' )
		);
		?>
	</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_webhooks' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_webhooks" />
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo Field_Help::th_column( __( 'On', 'credential-vault-pro' ), Field_Help::pro_tip( 'webhook_enabled' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo Field_Help::th_column( __( 'URL', 'credential-vault-pro' ), Field_Help::pro_tip( 'webhook_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo Field_Help::th_column( __( 'Secret / routing key', 'credential-vault-pro' ), Field_Help::pro_tip( 'webhook_secret' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo Field_Help::th_column( __( 'Format', 'credential-vault-pro' ), Field_Help::pro_tip( 'webhook_format' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo Field_Help::th_column( __( 'Events (comma, or all)', 'credential-vault-pro' ), Field_Help::pro_tip( 'webhook_events' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $hooks as $index => $hook ) : ?>
					<tr>
						<td><input type="checkbox" name="webhook_enabled[<?php echo (int) $index; ?>]" value="1" <?php checked( ! empty( $hook['enabled'] ) ); ?> /></td>
						<td><input type="url" class="large-text" name="webhook_url[<?php echo (int) $index; ?>]" value="<?php echo esc_attr( (string) ( $hook['url'] ?? '' ) ); ?>" /></td>
						<td><input type="password" class="regular-text" name="webhook_secret[<?php echo (int) $index; ?>]" value="<?php echo esc_attr( (string) ( $hook['secret'] ?? '' ) ); ?>" autocomplete="new-password" /></td>
						<td>
							<select name="webhook_format[<?php echo (int) $index; ?>]">
								<option value="generic" <?php selected( (string) ( $hook['format'] ?? 'generic' ), 'generic' ); ?>><?php esc_html_e( 'Generic JSON', 'credential-vault-pro' ); ?></option>
								<option value="slack" <?php selected( (string) ( $hook['format'] ?? '' ), 'slack' ); ?>>Slack</option>
								<option value="pagerduty" <?php selected( (string) ( $hook['format'] ?? '' ), 'pagerduty' ); ?>>PagerDuty</option>
							</select>
						</td>
						<td><input type="text" class="regular-text" name="webhook_events[<?php echo (int) $index; ?>]" value="<?php echo esc_attr( implode( ',', (array) ( $hook['events'] ?? array( 'all' ) ) ) ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Save webhooks', 'credential-vault-pro' ) ); ?>
	</form>
</div>

<div class="card">
	<h2 class="new-vault-heading-tip">
		<?php esc_html_e( 'Scoped API tokens', 'credential-vault-pro' ); ?>
		<?php
		echo Field_Help::icon(
			__( 'Use Authorization: Bearer <token> on REST routes. Works alongside Application Passwords.', 'credential-vault-pro' )
		);
		?>
	</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_create_api_token' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_create_api_token" />
		<p>
			<label><?php echo Field_Help::inline( __( 'Label', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" name="token_label" class="regular-text" />
			</label>
		</p>
		<p>
			<label><?php echo Field_Help::inline( __( 'Slugs (comma, empty = all)', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_slugs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" name="token_slugs" class="large-text" placeholder="stripe-api-key,smtp-password" />
			</label>
		</p>
		<p>
			<label><input type="checkbox" name="token_caps[]" value="metadata" checked="checked" /> <?php echo Field_Help::inline( __( 'Metadata', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_cap_metadata' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<label><input type="checkbox" name="token_caps[]" value="reveal" /> <?php echo Field_Help::inline( __( 'Reveal', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_cap_reveal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<label><input type="checkbox" name="token_caps[]" value="rotate" /> <?php echo Field_Help::inline( __( 'Rotate', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_cap_rotate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			<label><input type="checkbox" name="token_caps[]" value="manage" /> <?php echo Field_Help::inline( __( 'Manage', 'credential-vault-pro' ), Field_Help::pro_tip( 'token_cap_manage' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
		</p>
		<?php submit_button( __( 'Create token', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Label', 'credential-vault-pro' ); ?></th><th><?php esc_html_e( 'Caps', 'credential-vault-pro' ); ?></th><th></th></tr></thead>
		<tbody>
			<?php foreach ( Api_Tokens::list_tokens() as $token ) : ?>
				<tr>
					<td><?php echo esc_html( (string) ( $token['label'] ?? $token['id'] ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', (array) ( $token['caps'] ?? array() ) ) ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'credential_vault_pro_revoke_api_token' ); ?>
							<input type="hidden" name="action" value="credential_vault_pro_revoke_api_token" />
							<input type="hidden" name="token_id" value="<?php echo esc_attr( (string) ( $token['id'] ?? '' ) ); ?>" />
							<?php submit_button( __( 'Revoke', 'credential-vault-pro' ), 'delete small', '', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Per-secret ACL', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_secret_acl' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_secret_acl" />
		<p><label><?php echo Field_Help::inline( __( 'Slug', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_slug" type="text" class="regular-text" required /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'Roles (comma)', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_roles' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_roles" type="text" class="large-text" /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'User IDs (comma)', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_users' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_users" type="text" class="large-text" /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'May reveal (roles or user IDs)', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_reveal' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_reveal" type="text" class="large-text" /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'May rotate', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_rotate' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_rotate" type="text" class="large-text" /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'Metadata only', 'credential-vault-pro' ), Field_Help::pro_tip( 'acl_metadata' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input name="acl_metadata" type="text" class="large-text" /></label></p>
		<?php submit_button( __( 'Save ACL', 'credential-vault-pro' ) ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Approval queue (two-person rule)', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_approval_settings' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_approval_settings" />
		<label><input type="checkbox" name="approval_enabled" value="1" <?php checked( Approval_Queue::enabled() ); ?> /> <?php echo Field_Help::inline( __( 'Queue non–super-admin writes for approval', 'credential-vault-pro' ), Field_Help::pro_tip( 'approval_enabled' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
		<?php submit_button( __( 'Save', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
	<?php if ( ! empty( $pending ) ) : ?>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Secret', 'credential-vault-pro' ); ?></th><th><?php esc_html_e( 'Requested', 'credential-vault-pro' ); ?></th><th></th></tr></thead>
			<tbody>
				<?php foreach ( $pending as $item ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $item['secret'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( gmdate( 'c', (int) ( $item['requested'] ?? 0 ) ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'credential_vault_pro_approve_change' ); ?>
								<input type="hidden" name="action" value="credential_vault_pro_approve_change" />
								<input type="hidden" name="queue_id" value="<?php echo esc_attr( (string) ( $item['id'] ?? '' ) ); ?>" />
								<?php submit_button( __( 'Approve', 'credential-vault-pro' ), 'primary small', '', false ); ?>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'credential_vault_pro_reject_change' ); ?>
								<input type="hidden" name="action" value="credential_vault_pro_reject_change" />
								<input type="hidden" name="queue_id" value="<?php echo esc_attr( (string) ( $item['id'] ?? '' ) ); ?>" />
								<?php submit_button( __( 'Reject', 'credential-vault-pro' ), 'delete small', '', false ); ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="card">
	<h2><?php esc_html_e( 'SSO / 2FA before sudo', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_sso_settings' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_sso_settings" />
		<label><input type="checkbox" name="require_2fa_for_sudo" value="1" <?php checked( Sso_Gate::require_2fa() ); ?> /> <?php echo Field_Help::inline( __( 'Require credential_vault_pro_2fa_verified filter before sudo', 'credential-vault-pro' ), Field_Help::pro_tip( 'require_2fa_for_sudo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
		<?php submit_button( __( 'Save', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Rotation nudges', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_nudge_settings' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_nudge_settings" />
		<label><input type="checkbox" name="nudge_email" value="1" <?php checked( (bool) get_option( 'credential_vault_pro_nudge_email', true ) ); ?> /> <?php echo Field_Help::inline( __( 'Email admin when rotate_by passes (webhooks always fire)', 'credential-vault-pro' ), Field_Help::pro_tip( 'nudge_email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
		<?php submit_button( __( 'Save', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Long-retention audit & SIEM', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_audit_retention' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_audit_retention" />
		<label><?php echo Field_Help::inline( __( 'Retention (days)', 'credential-vault-pro' ), Field_Help::pro_tip( 'audit_retention_days' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="number" min="30" max="3650" name="audit_retention_days" value="<?php echo (int) Audit_Store::retention_days(); ?>" />
		</label>
		<?php submit_button( __( 'Save retention', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_siem' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_siem" />
		<label><input type="checkbox" name="siem_push_enabled" value="1" <?php checked( (bool) get_option( 'credential_vault_pro_siem_push_enabled', false ) ); ?> /> <?php echo Field_Help::inline( __( 'Hourly push to SIEM sink', 'credential-vault-pro' ), Field_Help::pro_tip( 'siem_push_enabled' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
		<p>
			<label><?php echo Field_Help::inline( __( 'Sink type', 'credential-vault-pro' ), Field_Help::pro_tip( 'siem_type' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<select name="siem_type">
				<option value="https">HTTPS (JSON Lines)</option>
				<option value="syslog">Syslog</option>
			</select></label>
			<label><?php echo Field_Help::inline( __( 'Sink URL / address', 'credential-vault-pro' ), Field_Help::pro_tip( 'siem_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="url" name="siem_url" class="large-text" placeholder="https://siem.example/ingest/vault" value="<?php echo esc_attr( (string) ( get_option( 'credential_vault_pro_siem_sink', array() )['url'] ?? '' ) ); ?>" /></label>
		</p>
		<?php submit_button( __( 'Save SIEM sink', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Plugin adapters (read from vault)', 'credential-vault-pro' ); ?></h2>
	<p><?php echo Field_Help::inline( __( 'Option map', 'credential-vault-pro' ), Field_Help::pro_tip( 'adapter_map' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
	<?php
	$adapter_lines = '';
	foreach ( Plugin_Adapters::map() as $option => $slug ) {
		$adapter_lines .= $option . '=' . $slug . "\n";
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_adapters' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_adapters" />
		<textarea name="adapter_map" class="large-text" rows="6"><?php echo esc_textarea( trim( $adapter_lines ) ); ?></textarea>
		<?php submit_button( __( 'Save adapters', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>

<div class="card">
	<h2 class="new-vault-heading-tip">
		<?php esc_html_e( 'Env sync (one-way import)', 'credential-vault-pro' ); ?>
		<?php
		echo Field_Help::icon(
			__( 'WP-CLI: wp credvault pro env-sync --file=/path/.env — example placeholders: wp credvault pro env-example', 'credential-vault-pro' )
		);
		?>
	</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_env_sync' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_env_sync" />
		<label><?php echo Field_Help::inline( __( 'ENV_KEY=slug map (one per line)', 'credential-vault-pro' ), Field_Help::pro_tip( 'env_map' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<textarea name="env_map" class="large-text" rows="4"><?php echo esc_textarea( trim( $env_lines ) ); ?></textarea>
		</label>
		<p><label><?php echo Field_Help::inline( __( 'HTTP vault URL (Doppler / 1Password / generic read-only JSON)', 'credential-vault-pro' ), Field_Help::pro_tip( 'vault_http_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="url" name="vault_http_url" class="large-text" value="<?php echo esc_attr( (string) ( get_option( 'credential_vault_pro_vault_http', array() )['url'] ?? '' ) ); ?>" /></label></p>
		<p><label><?php echo Field_Help::inline( __( 'Bearer token', 'credential-vault-pro' ), Field_Help::pro_tip( 'vault_http_token' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="password" name="vault_http_token" class="regular-text" autocomplete="new-password" /></label></p>
		<?php submit_button( __( 'Save env sync', 'credential-vault-pro' ) ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'Template credentials (new subsites)', 'credential-vault-pro' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_templates' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_templates" />
		<p class="description">
			<?php echo Field_Help::inline( __( 'Slug', 'credential-vault-pro' ), Field_Help::pro_tip( 'template_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo Field_Help::inline( __( 'Label', 'credential-vault-pro' ), Field_Help::pro_tip( 'template_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</p>
		<?php foreach ( array_merge( $templates, array( array( 'slug' => '', 'label' => '' ) ) ) as $i => $tpl ) : ?>
			<p>
				<input type="text" name="template_slug[]" placeholder="slug" value="<?php echo esc_attr( (string) ( $tpl['slug'] ?? '' ) ); ?>" />
				<input type="text" name="template_label[]" placeholder="<?php esc_attr_e( 'Label', 'credential-vault-pro' ); ?>" value="<?php echo esc_attr( (string) ( $tpl['label'] ?? '' ) ); ?>" class="regular-text" />
			</p>
		<?php endforeach; ?>
		<?php submit_button( __( 'Save templates', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>

<div class="card">
	<h2><?php esc_html_e( 'License / seats', 'credential-vault-pro' ); ?></h2>
	<?php if ( ! License_Seats::within_limits() ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Site count exceeds licensed seats.', 'credential-vault-pro' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_license' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_license" />
		<p><label><?php echo Field_Help::inline( __( 'Seats', 'credential-vault-pro' ), Field_Help::pro_tip( 'license_seats' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input type="number" min="1" name="license_seats" value="<?php echo (int) $license['seats']; ?>" /></label>
		<?php printf( esc_html__( 'In use: %d', 'credential-vault-pro' ), (int) $license['sites_in_use'] ); ?></p>
		<p><label><?php echo Field_Help::inline( __( 'License key', 'credential-vault-pro' ), Field_Help::pro_tip( 'license_key' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <input type="text" name="license_key" class="regular-text" value="<?php echo esc_attr( (string) $license['key'] ); ?>" /></label></p>
		<?php submit_button( __( 'Save license', 'credential-vault-pro' ), 'secondary' ); ?>
	</form>
</div>
