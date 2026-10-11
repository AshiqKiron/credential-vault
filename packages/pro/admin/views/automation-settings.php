<?php
/**
 * Pro automation settings (webhooks).
 *
 * @package CredentialVaultPro
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVaultPro\Webhooks;

$hooks = Webhooks::all();
if ( empty( $hooks ) ) {
	$hooks = array(
		array(
			'url'     => '',
			'secret'  => '',
			'enabled' => true,
			'events'  => array( 'all' ),
		),
	);
}

?>
<div class="card new-vault-add">
	<h2><?php esc_html_e( 'Webhook notifications', 'credential-vault-pro' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Send signed JSON payloads when audit events are recorded (rotations, imports, sudo, programmatic access). Ideal for Slack bridges or agency monitoring.', 'credential-vault-pro' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'credential_vault_pro_save_webhooks' ); ?>
		<input type="hidden" name="action" value="credential_vault_pro_save_webhooks" />
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'credential-vault-pro' ); ?></th>
					<th><?php esc_html_e( 'HTTPS URL', 'credential-vault-pro' ); ?></th>
					<th><?php esc_html_e( 'Signing secret', 'credential-vault-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $hooks as $index => $hook ) : ?>
					<tr>
						<td>
							<input type="checkbox" name="webhook_enabled[<?php echo (int) $index; ?>]" value="1" <?php checked( ! empty( $hook['enabled'] ) ); ?> />
						</td>
						<td>
							<input type="url" class="large-text" name="webhook_url[<?php echo (int) $index; ?>]" value="<?php echo esc_attr( (string) ( $hook['url'] ?? '' ) ); ?>" placeholder="https://hooks.example.com/vault" />
						</td>
						<td>
							<input type="password" class="regular-text" name="webhook_secret[<?php echo (int) $index; ?>]" value="<?php echo esc_attr( (string) ( $hook['secret'] ?? '' ) ); ?>" autocomplete="new-password" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php submit_button( __( 'Save webhooks', 'credential-vault-pro' ) ); ?>
	</form>
</div>
