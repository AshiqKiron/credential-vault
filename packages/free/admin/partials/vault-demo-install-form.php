<?php
/**
 * One-click demo credential install button.
 *
 * @package CredentialVault
 *
 * @var bool   $demo_can_install
 * @var string $redirect_tab       dashboard|credentials|settings|audit
 * @var string $button_style       primary|secondary
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $demo_can_install ) ) {
	return;
}

$redirect_tab = isset( $redirect_tab ) ? sanitize_key( (string) $redirect_tab ) : 'dashboard';
if ( ! in_array( $redirect_tab, array( 'dashboard', 'credentials', 'settings', 'audit' ), true ) ) {
	$redirect_tab = 'dashboard';
}

$button_style = isset( $button_style ) ? (string) $button_style : 'primary';
$button_class = 'primary' === $button_style ? 'button-primary' : 'secondary';

?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-vault-demo-install">
	<?php wp_nonce_field( 'credential_vault_install_demo' ); ?>
	<input type="hidden" name="action" value="credential_vault_install_demo" />
	<input type="hidden" name="redirect_tab" value="<?php echo esc_attr( $redirect_tab ); ?>" />
	<?php submit_button( __( 'Install demo data', 'credvault' ), $button_class, '', false ); ?>
</form>
