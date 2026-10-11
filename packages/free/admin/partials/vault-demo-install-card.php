<?php
/**
 * Demo data promo card with one-click install.
 *
 * @package CredentialVault
 *
 * @var bool $demo_can_install
 * @var string $redirect_tab
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $demo_can_install ) ) {
	return;
}

$redirect_tab = isset( $redirect_tab ) ? sanitize_key( (string) $redirect_tab ) : 'dashboard';

?>
<div class="card new-vault-settings new-vault-demo-card">
	<h2><?php esc_html_e( 'Try the vault with demo data', 'credvault' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Install fake credentials (slug prefix “demo-”) to explore the dashboard, rotation reminders, environments, and audit log. Values are not real secrets.', 'credvault' ); ?>
	</p>
	<?php
	$button_style = 'primary';
	include CREDENTIAL_VAULT_DIR . 'admin/partials/vault-demo-install-form.php';
	?>
</div>
