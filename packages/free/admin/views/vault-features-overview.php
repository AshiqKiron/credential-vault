<?php
/**
 * Feature highlights aligned with WordPress 7.2 Secrets API and sudo mode.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CredentialVault\Admin\Field_Help;

?>
<details class="new-vault-features card">
	<summary class="new-vault-heading-tip">
		<?php esc_html_e( 'How Credential Vault protects your site', 'credvault' ); ?>
		<?php
		echo Field_Help::icon(
			__( 'Encrypted Secrets API storage, sudo re-auth, centralized dashboard, audit trail, and two-slot rotation.', 'credvault' )
		);
		?>
	</summary>
	<ul class="new-vault-features__list">
		<li>
			<strong><?php esc_html_e( 'Encrypted storage', 'credvault' ); ?></strong>
			<?php esc_html_e( 'API keys live in the WordPress Secrets API instead of plain text in wp_options, using core encryption primitives.', 'credvault' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Sudo mode', 'credvault' ); ?></strong>
			<?php esc_html_e( 'Administrators re-enter their password before viewing, copying, or changing production secrets.', 'credvault' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Central dashboard', 'credvault' ); ?></strong>
			<?php esc_html_e( 'Stripe, OpenAI, AWS, and other tokens are managed from one screen instead of scattered plugin settings.', 'credvault' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Audit trail', 'credvault' ); ?></strong>
			<?php esc_html_e( 'Every query, update, rotation, and sudo session is logged with timestamps and user IDs.', 'credvault' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Safe rotation', 'credvault' ); ?></strong>
			<?php esc_html_e( 'Two-slot rotation keeps the previous key available until you verify the new one and retire the old slot.', 'credvault' ); ?>
		</li>
	</ul>
</details>
