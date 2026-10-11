=== Credential Vault Pro ===
Contributors: your-name
Tags: secrets, rest-api, credentials
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

REST API and secret discovery for Credential Vault.

== Description ==

Requires the free **Credential Vault** plugin.

* REST routes under `credential-vault/v1` for list, rotate, reveal, delete, retire
* Sudo via active session or `sudo_password` / `POST /sudo`
* **Other secrets** tab listing credentials registered by other plugins

== Installation ==

1. Install and activate Credential Vault (free).
2. Upload `credential-vault-pro` to `/wp-content/plugins/` and activate.

== Changelog ==

= 1.1.0 =
* Initial Pro release with REST and discovery.
