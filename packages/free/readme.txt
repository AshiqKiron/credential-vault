=== Credential Vault ===
Contributors: ashiquzzaman
Tags: secrets, security, api-keys, credentials, encryption
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Centralized admin for the WordPress 7.2 Secrets API — encrypted keys, sudo re-auth, rotation, and audit history.

== Description ==

Credential Vault addresses common API key pain points on WordPress sites:

* **Encrypted storage** — Uses the WordPress Secrets API (core 7.2 or the [Secrets API feature plugin](https://github.com/ericmann/secrets-api)) instead of plain text in `wp_options`.
* **Sudo mode** — Administrators must re-enter their password before viewing, copying, or changing sensitive keys. Integrates with the [WP Sudo](https://github.com/dknauss/Sudo) plugin when installed.
* **Central dashboard** — Token cards for Stripe, OpenAI, AWS, Mailchimp, SendGrid, reCAPTCHA, Twilio, Postmark, Slack, GitHub, Cloudflare, Google Maps, and OAuth client secrets.
* **Discovery & hygiene** — Plaintext scanner (options, user meta, plugin prefixes), duplicate detection, bulk import, and rotate-by reminders with admin notices.
* **Audit trail** — CSV export (metadata only: user IDs, actions, fingerprints — never secret values). GDPR-friendly documentation in the Audit tab.
* **Safe rotation** — Two-slot workflow, built-in Stripe/OpenAI smoke tests, and `credential_vault_rotation_test` filter.
* **Access control** — `manage_credential_vault` capability, optional **Credential Vault Auditor** read-only role, configurable sudo TTL and sudo policy.
* **Developer helpers** — `credential_vault_get()`, `credential_vault_set()`, `credential_vault_register()`.
* **WP-CLI** — `wp credvault list`, `status`, `scan`, `import --dry-run`, `get <slug> [--reveal]`.

Also includes environment badges, owner/runbook metadata, custom public fields (AWS access key ID, webhook URL, OAuth client ID), site/network inheritance hints, metadata JSON export, and Site Health tests.

== Installation ==

1. Upload the `credential-vault` folder to `/wp-content/plugins/` and activate.
2. Install WordPress 7.2 or the Secrets API feature plugin.
3. Open **Credential Vault** in the admin menu.
4. Unlock the vault with your password before managing secrets.

== Changelog ==

= 1.0.0 =
* Initial release: Credential Vault admin dashboard for the Secrets API.
