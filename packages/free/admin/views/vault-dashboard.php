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
 * @var list<array<string, mixed>> $scan_results
 * @var array<string, mixed>       $audit_filters
 * @var bool   $pro_active
 * @var bool   $can_manage
 * @var list<array<string, mixed>> $hygiene_issues
 * @var array<string, int|bool|string> $vault_health
 * @var array<string, array{time: int, action: string, source: string}> $audit_activity
 * @var bool   $auditor_only
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Auditor_Experience;
use CredentialVault\Credential_Meta;
use CredentialVault\Secrets_Bridge;

$presets = Credential_Meta::service_presets();
$tab     = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$base_url = admin_url( 'admin.php?page=credential-vault' );

/**
 * Additional admin tabs (Pro adds Automation).
 *
 * @param array<string, string> $extra_tabs slug => label
 */
$extra_tabs = apply_filters( 'credential_vault_admin_tabs', array() );

?>
<div class="wrap new-vault-wrap">
	<h1><?php esc_html_e( 'Credential Vault', 'credvault' ); ?></h1>

	<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-features-overview.php'; ?>

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
		<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Token dashboard', 'credvault' ); ?></a>
		<?php if ( ! ( $auditor_only ?? false ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'credentials', $base_url ) ); ?>" class="nav-tab <?php echo 'credentials' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Site credentials', 'credvault' ); ?></a>
		<?php endif; ?>
		<?php if ( $network_available && ! ( $auditor_only ?? false ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'network', $base_url ) ); ?>" class="nav-tab <?php echo 'network' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Network', 'credvault' ); ?></a>
		<?php endif; ?>
		<?php if ( $can_manage && ! ( $auditor_only ?? false ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base_url ) ); ?>" class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import', 'credvault' ); ?></a>
		<?php endif; ?>
		<?php if ( $api_available ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'discover', $base_url ) ); ?>" class="nav-tab <?php echo 'discover' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Other secrets', 'credvault' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'audit', $base_url ) ); ?>" class="nav-tab <?php echo 'audit' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Audit log', 'credvault' ); ?></a>
		<?php if ( $can_manage && ! ( $auditor_only ?? false ) ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'credvault' ); ?></a>
		<?php endif; ?>
		<?php foreach ( $extra_tabs as $slug => $label ) : ?>
			<?php if ( Auditor_Experience::tab_allowed( $slug ) ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>

	<?php if ( isset( $extra_tabs[ $tab ] ) ) : ?>
		<?php
		/**
		 * Render a plugin-extended admin tab.
		 *
		 * @param string               $tab     Tab slug.
		 * @param array<string, mixed> $context Dashboard context.
		 */
		do_action( 'credential_vault_render_admin_tab', $tab, $context );
		?>
	<?php elseif ( 'audit' === $tab ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-audit.php'; ?>
	<?php elseif ( 'dashboard' === $tab ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-token-dashboard.php'; ?>
	<?php elseif ( 'import' === $tab && $can_manage ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-import.php'; ?>
	<?php elseif ( 'settings' === $tab && $can_manage ) : ?>
		<?php include CREDENTIAL_VAULT_DIR . 'admin/views/vault-settings.php'; ?>
	<?php elseif ( 'discover' === $tab && $api_available ) : ?>
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
		if ( $network_available && ! empty( $network_credentials ) && 'credentials' === $tab ) :
			?>
			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'This site may inherit network-scoped credentials when a site-specific slug is not configured. Check the Network tab for shared secrets.', 'credvault' ); ?>
				</p>
			</div>
			<?php
		endif;
		$scope       = 'site';
		$credentials = $credentials;
		include CREDENTIAL_VAULT_DIR . 'admin/views/vault-scope-panel.php';
		?>
	<?php endif; ?>
</div>
