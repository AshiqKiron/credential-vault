=== Credential Vault ===
Contributors: ashiquzzaman
Tags: secrets, security, api-keys, credentials, encryption
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Admin UI for the WordPress Secrets API (core 7.2 or the Secrets API feature plugin).

== Description ==

* Unified credential dashboard (`credential-vault/*` namespace)
* Password sudo mode before view, copy, or update
* Masked previews and cryptographic fingerprints
* Two-slot key rotation with explicit retire
* Site and network (multisite) scopes
* Bulk import wizard for known plaintext options
* Site Health tests for undecryptable and pending-rotation secrets
* Developer helper `credential_vault_get( 'slug' )`

Requires the [Secrets API feature plugin](https://github.com/ericmann/secrets-api) until WordPress 7.2 ships the same API.

== Installation ==

1. Upload the `credential-vault` folder to `/wp-content/plugins/` and activate.
2. Open **Credential Vault** in the admin menu.
3. Unlock the vault with your password before managing secrets.

== Changelog ==

= 1.0.0 =
* Initial release: Credential Vault admin dashboard for the Secrets API.
