<?php
/**
 * Sudo authentication panel.
 *
 * @package CredentialVault
 *
 * @var bool $sudo_active
 * @var int  $sudo_remaining
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="new-vault-sudo-panel card">
	<?php if ( $sudo_active ) : ?>
		<p>
			<span class="new-vault-badge new-vault-badge--active"><?php esc_html_e( 'Sudo active', 'credvault' ); ?></span>
			<?php
			printf(
				esc_html__( 'Sensitive actions allowed for about %s more minutes.', 'credvault' ),
				esc_html( (string) max( 1, (int) ceil( $sudo_remaining / 60 ) ) )
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'credential_vault_sudo_end' ); ?>
			<input type="hidden" name="action" value="credential_vault_sudo_end" />
			<?php submit_button( __( 'End sudo session', 'credvault' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php else : ?>
		<h2><?php esc_html_e( 'Confirm your password', 'credvault' ); ?></h2>
		<p><?php esc_html_e( 'Re-authenticate before viewing, copying, or updating credentials.', 'credvault' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-sudo-form">
			<?php wp_nonce_field( 'credential_vault_sudo' ); ?>
			<input type="hidden" name="action" value="credential_vault_sudo" />
			<p>
				<label for="credential_vault_password"><?php esc_html_e( 'Password', 'credvault' ); ?></label>
				<input type="password" name="credential_vault_password" id="credential_vault_password" class="regular-text" autocomplete="current-password" required />
			</p>
			<?php submit_button( __( 'Unlock vault', 'credvault' ), 'primary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
</div>
