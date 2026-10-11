<?php
/**
 * First-run onboarding checklist.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Onboarding;

if ( Onboarding::is_complete() ) {
	return;
}

$items    = Onboarding::checklist();
$progress = Onboarding::progress_percent();
$base     = admin_url( 'admin.php?page=credential-vault' );

?>
<div class="card new-vault-onboarding">
	<h2><?php esc_html_e( 'Get started with Credential Vault', 'credvault' ); ?></h2>
	<p><?php echo esc_html( sprintf( __( 'Setup progress: %d%%', 'credvault' ), $progress ) ); ?></p>
	<ol>
		<?php foreach ( $items as $item ) : ?>
			<li>
				<?php echo ! empty( $item['done'] ) ? '✓' : '○'; ?>
				<?php echo esc_html( (string) $item['label'] ); ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base ) ); ?>"><?php esc_html_e( 'Run import scanner', 'credvault' ); ?></a>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', 'credentials', $base ) ); ?>"><?php esc_html_e( 'Add a credential', 'credvault' ); ?></a>
	</p>
	<?php if ( $can_manage ?? false ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-inline">
			<?php wp_nonce_field( 'credential_vault_onboarding_done' ); ?>
			<input type="hidden" name="action" value="credential_vault_onboarding_done" />
			<?php submit_button( __( 'Dismiss checklist', 'credvault' ), 'link', '', false ); ?>
		</form>
	<?php endif; ?>
</div>
