<?php
/**
 * Credential vault admin view.
 *
 * @package CredentialVault
 *
 * @var bool   $api_available
 * @var bool   $sudo_active
 * @var int    $sudo_remaining
 * @var bool   $writable
 * @var bool   $network_available
 * @var string $provider_label
 * @var list<array<string, mixed>> $credentials
 * @var list<array<string, mixed>> $network_credentials
 * @var list<array<string, mixed>> $external_secrets
 * @var list<array<string, mixed>> $audit_entries
 * @var list<array<string, mixed>> $import_candidates
 * @var bool   $pro_active
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

$presets = Credential_Meta::service_presets();
$tab     = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'credentials'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$base_url = admin_url( 'admin.php?page=credential-vault' );

?>
<div class="wrap new-vault-wrap">
	<h1><?php esc_html_e( 'Credential Vault', 'credvault' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Store third-party API keys in the WordPress Secrets API with encrypted storage, rotation slots, and an audit trail.', 'credvault' ); ?>
	</p>

	<?php if ( ! $api_available ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Install WordPress 7.2 or the Secrets API feature plugin to enable encrypted credential storage.', 'credvault' ); ?>
				<a href="https://github.com/ericmann/secrets-api" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Secrets API on GitHub', 'credvault' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<p class="new-vault-meta">
			<strong><?php esc_html_e( 'Storage:', 'credvault' ); ?></strong>
			<?php echo esc_html( $provider_label ); ?>
			<?php if ( ! $writable ) : ?>
				<span class="new-vault-badge new-vault-badge--readonly"><?php esc_html_e( 'Read-only provider', 'credvault' ); ?></span>
			<?php endif; ?>
			<?php if ( $pro_active ) : ?>
				<span class="new-vault-badge new-vault-badge--active"><?php esc_html_e( 'Pro', 'credvault' ); ?></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo 'credentials' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Site credentials', 'credvault' ); ?></a>
		<?php if ( $network_available ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'network', $base_url ) ); ?>" class="nav-tab <?php echo 'network' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Network', 'credvault' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base_url ) ); ?>" class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import', 'credvault' ); ?></a>
		<?php if ( $pro_active ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'discover', $base_url ) ); ?>" class="nav-tab <?php echo 'discover' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Other secrets', 'credvault' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit', $base_url ) ); ?>" class="nav-tab <?php echo 'audit' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Audit log', 'credvault' ); ?></a>
	</nav>

	<?php if ( 'audit' === $tab ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-audit.php'; ?>
	<?php elseif ( 'import' === $tab ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-import.php'; ?>
	<?php elseif ( 'discover' === $tab && $pro_active ) : ?>
		<h2><?php esc_html_e( 'Secrets registered by other plugins', 'credvault' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Read-only inventory from wp_list_secrets(). Manage values in the owning plugin or via WP-CLI.', 'credvault' ); ?></p>
		<?php
		$credentials = $external_secrets;
		$scope       = 'site';
		include CREDENTIAL_VAULT_DIR . 'admin/partials/credentials-table.php';
		?>
	<?php elseif ( 'network' === $tab && $network_available ) : ?>
		<?php
		$scope       = 'network';
		$credentials = $network_credentials;
		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-scope-panel.php';
		?>
	<?php else : ?>
		<?php
		$scope       = 'site';
		$credentials = $credentials;
		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-scope-panel.php';
		?>
	<?php endif; ?>
</div>
