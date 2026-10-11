<?php
/**
 * Admin info icons and tooltip copy for form labels.
 *
 * @package CredentialVault
 */

declare( strict_types=1 );

namespace CredentialVault\Admin;

/**
 * Renders accessible help tooltips next to vault admin options.
 */
final class Field_Help {

	/**
	 * Info icon with hover/focus tooltip.
	 */
	public static function icon( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		static $seq = 0;
		++$seq;
		$id = 'cv-field-tip-' . $seq;

		return sprintf(
			'<span class="cv-field-tip" tabindex="0" role="button" aria-describedby="%1$s"><span class="dashicons dashicons-info" aria-hidden="true"></span><span class="cv-field-tip__text" id="%1$s" role="tooltip">%2$s</span></span>',
			esc_attr( $id ),
			esc_html( $text )
		);
	}

	/**
	 * Form-table header: label text plus optional info icon.
	 */
	public static function th( string $label, string $tip, string $for = '' ): string {
		$label_html = '' !== $for
			? sprintf( '<label for="%1$s">%2$s</label>', esc_attr( $for ), esc_html( $label ) )
			: esc_html( $label );

		return self::icon( $tip ) . $label_html;
	}

	/**
	 * Inline label fragment (checkbox/radio rows).
	 */
	public static function inline( string $label, string $tip ): string {
		return '<span class="cv-field-label">' . self::icon( $tip ) . esc_html( $label ) . '</span>';
	}

	/**
	 * Table column header with tooltip.
	 */
	public static function th_column( string $label, string $tip ): string {
		return self::icon( $tip ) . esc_html( $label );
	}

	/**
	 * Section heading or summary: info icon before title text.
	 */
	public static function heading( string $title, string $tip ): string {
		return self::icon( $tip ) . esc_html( $title );
	}

	public static function credential_tip( string $field ): string {
		$tips = array(
			'search'       => __( 'Matches the credential label or slug. Leave blank to show every credential in this list.', 'credvault' ),
			'filter'       => __( 'Limit the list to overdue rotate-by dates, rotation flags, an active previous slot, or an environment mismatch.', 'credvault' ),
			'sort'         => __( 'Order the list by label, slug, rotate-by date, or most recent vault activity.', 'credvault' ),
			'service'      => __( 'Preset that adjusts the secret field label and optional public metadata fields (for example AWS access key ID).', 'credvault' ),
			'label'        => __( 'Friendly name shown in the vault list and exports. Does not affect the slug used in code.', 'credvault' ),
			'slug'         => __( 'Stable identifier after the app/ namespace. Use lowercase letters and hyphens only; referenced by credential_vault_get() and REST.', 'credvault' ),
			'secret_value' => __( 'Encrypted value stored in the WordPress Secrets API. Never shown in the audit log or metadata export.', 'credvault' ),
			'environment'  => __( 'Optional tag for production, staging, or local workflows. Shown as a badge on the dashboard.', 'credvault' ),
			'collection'   => __( 'Optional group (payments, email, AI, and similar) for filtering and health views. Stored in metadata only.', 'credvault' ),
			'rotate_by'    => __( 'Optional reminder date for agency rotation schedules. Does not rotate automatically; webhooks and emails may notify when this date passes.', 'credvault' ),
			'owner'        => __( 'Team or person accountable for this credential (for example DevOps or client contact).', 'credvault' ),
			'runbook_url'  => __( 'Link to internal docs or a runbook describing how to rotate or revoke this secret.', 'credvault' ),
			'notes'        => __( 'Free-form context for admins. Stored in metadata only, not in the encrypted secret payload.', 'credvault' ),
			'access_key_id' => __( 'Public AWS access key ID paired with the secret access key. Stored in metadata, not encrypted with the secret value.', 'credvault' ),
			'webhook_url'  => __( 'Destination URL for this integration webhook. Stored in metadata for reference.', 'credvault' ),
			'client_id'    => __( 'OAuth client ID paired with the client secret. Stored in metadata.', 'credvault' ),
		);

		return (string) ( $tips[ $field ] ?? '' );
	}

	public static function settings_tip( string $field ): string {
		$tips = array(
			'sudo_ttl'           => __( 'How long unlock lasts after password confirmation. Allowed range: 60–3600 seconds (default 600).', 'credvault' ),
			'sudo_mode'          => __( 'Controls when sudo (password re-entry) is required for vault actions.', 'credvault' ),
			'sudo_all_writes'    => __( 'Imports, saves, metadata edits, copy, and reveal all require an active sudo session after password confirmation.', 'credvault' ),
			'sudo_reveal_only'   => __( 'Only reveal and copy require sudo. Saving metadata, rotating keys, and imports use capability checks without sudo.', 'credvault' ),
		);

		return (string) ( $tips[ $field ] ?? '' );
	}

	public static function pro_tip( string $field ): string {
		$tips = array(
			'webhook_enabled'      => __( 'Disable to keep the row saved but stop delivery.', 'credential-vault-pro' ),
			'webhook_url'          => __( 'HTTPS endpoint that receives event payloads. Must accept POST requests.', 'credential-vault-pro' ),
			'webhook_secret'       => __( 'Signing secret for HMAC verification (generic format). For PagerDuty, paste the Events API v2 routing key here.', 'credential-vault-pro' ),
			'webhook_format'       => __( 'Generic JSON, Slack incoming webhook, or PagerDuty Events v2. Payload shape matches the selected format.', 'credential-vault-pro' ),
			'webhook_events'       => __( 'Comma-separated audit action names, or “all” for every supported event (rotation, import, sudo, anomalies, and similar).', 'credential-vault-pro' ),
			'token_label'          => __( 'Human-readable name for this token in the list below.', 'credential-vault-pro' ),
			'token_slugs'          => __( 'Restrict which secret slugs this token may access. Leave empty to allow all slugs (still limited by capabilities).', 'credential-vault-pro' ),
			'token_cap_metadata'   => __( 'Read non-secret metadata via REST.', 'credential-vault-pro' ),
			'token_cap_reveal'     => __( 'Reveal decrypted secret values (subject to sudo and ACL rules).', 'credential-vault-pro' ),
			'token_cap_rotate'     => __( 'Run rotation workflows and update secret values.', 'credential-vault-pro' ),
			'token_cap_manage'     => __( 'Create, update, or delete credentials and related settings.', 'credential-vault-pro' ),
			'acl_slug'             => __( 'Vault slug (without app/ prefix) this ACL row applies to.', 'credential-vault-pro' ),
			'acl_roles'            => __( 'WordPress roles that may access this secret (comma-separated).', 'credential-vault-pro' ),
			'acl_users'            => __( 'Specific user IDs allowed access (comma-separated).', 'credential-vault-pro' ),
			'acl_reveal'           => __( 'Roles or user IDs that may reveal plaintext values.', 'credential-vault-pro' ),
			'acl_rotate'           => __( 'Roles or user IDs that may rotate this secret.', 'credential-vault-pro' ),
			'acl_metadata'         => __( 'Roles or user IDs limited to metadata-only access.', 'credential-vault-pro' ),
			'approval_enabled'     => __( 'When enabled, writes from non–super-admins are queued until another admin approves.', 'credential-vault-pro' ),
			'require_2fa_for_sudo' => __( 'Blocks sudo until the credential_vault_pro_2fa_verified filter returns true (wire to your SSO or 2FA plugin).', 'credential-vault-pro' ),
			'nudge_email'          => __( 'Sends email to the site admin when rotate_by is overdue. Webhooks fire regardless of this setting.', 'credential-vault-pro' ),
			'audit_retention_days' => __( 'How long extended audit rows are kept in Pro storage before pruning (30–3650 days).', 'credential-vault-pro' ),
			'siem_push_enabled'    => __( 'Push audit events hourly to your SIEM sink using the settings below.', 'credential-vault-pro' ),
			'siem_type'            => __( 'HTTPS delivers JSON Lines; syslog writes to the host syslog facility.', 'credential-vault-pro' ),
			'siem_url'             => __( 'Ingest URL (HTTPS) or syslog receiver address, depending on type.', 'credential-vault-pro' ),
			'adapter_map'          => __( 'Each line maps a wp_options key to a vault slug. Reads are intercepted via pre_option and served from the vault.', 'credential-vault-pro' ),
			'env_map'              => __( 'Maps .env variable names to vault slugs for wp credvault pro env-sync.', 'credential-vault-pro' ),
			'vault_http_url'       => __( 'Optional read-only HTTP vault (Doppler, 1Password, or generic JSON) for env-sync imports.', 'credential-vault-pro' ),
			'vault_http_token'     => __( 'Bearer token for the HTTP vault. Stored encrypted; leave blank to keep the current token.', 'credential-vault-pro' ),
			'template_slug'        => __( 'Slug to seed on new multisite subsites when templates run.', 'credential-vault-pro' ),
			'template_label'       => __( 'Default label for the seeded credential.', 'credential-vault-pro' ),
			'license_seats'        => __( 'Maximum number of sites covered by this license key.', 'credential-vault-pro' ),
			'license_key'          => __( 'License key from your purchase; used for seat enforcement.', 'credential-vault-pro' ),
			'audit_filter_action'  => __( 'Filter audit rows by action slug (for example secret_revealed or secret_rotated).', 'credential-vault-pro' ),
			'audit_filter_secret'  => __( 'Partial match against the credential name column.', 'credential-vault-pro' ),
			'audit_filter_user'    => __( 'WordPress user ID of the actor; use 0 for system events.', 'credential-vault-pro' ),
		);

		return (string) ( $tips[ $field ] ?? '' );
	}
}
