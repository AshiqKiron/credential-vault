# Pro plugin (`credential-vault-pro`)

Distributable pro add-on. Bootstrap: `credential-vault-pro.php`. Namespace: `CredentialVaultPro\`.

## Dependency on free

- Pro must not assume free is loaded until verified (see `Plugin::is_free_active()`).
- Register features on **`credential_vault_loaded`**; if that action already ran, call registration immediately (pattern in `includes/class-plugin.php`).
- **`Plugin::activate()`** delegates to **`Activator::activate()`** (DB tables, purge cron).

## i18n

- Text domain: **`credential-vault-pro`**
- Template: `languages/credential-vault-pro.pot` — regenerate with `npm run make-pot` from repo root.

## Bootstrapping new pro code

- Runtime: add `Some_Class::register()` inside **`Plugin::register_pro_features()`** (order matters when one module depends on another).
- Schema / scheduled events on activate: extend **`Activator`**; keep `dbDelta` usage consistent with **`Audit_Store`**.
- Admin-only views: `packages/pro/admin/views/` — register tabs via **`credential_vault_admin_tabs`** / **`credential_vault_render_admin_tab`** where applicable.
- Do not hook pro features before free fires **`credential_vault_loaded`**.

## Registered modules (`register_pro_features`)

| Module | Role |
|--------|------|
| `Audit_Store` | Append-only audit table, retention, SIEM JSONL stream |
| `Api_Tokens` | Bearer tokens; scopes via **`credential_vault_user_can`** |
| `Secret_Acl` | Per-slug role/user grants |
| `Approval_Queue` | Gated secret changes requiring approval |
| `Sso_Gate` | Extra gate (e.g. 2FA) before sudo (`credential_vault_pro_2fa_verified`) |
| `Rotation_Nudges` | Cron emails/webhooks for overdue `rotate_by` |
| `Rotation_Automation` | Provider rotation orchestration + REST |
| `Import_Jobs` | Async import batches + REST status |
| `Env_Sync` | WP-CLI env import / `.env.example` (`credvault pro env-sync`, `env-example`) |
| `Plugin_Adapters` | Option-read shims for migrated third-party plugins |
| `Health_Monitor` | Periodic rotation test pings / dashboard status |
| `Anomaly_Monitor` | Unusual access patterns |
| `Siem_Export` | Audit export hooks for external sinks |
| `Compliance_Reports` | Compliance admin tab (`admin/views/compliance-reports.php`) |
| `License_Seats` | Seat / licensing enforcement (if enabled) |
| `Template_Credentials` | Reusable credential templates |
| `Multisite_Network` | Network overview tab (`admin/views/network-overview.php`) |
| `Rest_Controller` | REST **`credential-vault/v1`** (delegates extra routes to **`Rest_Extended`**) |
| `Sudo_Audit_Logger`, `Sudo_Security_Validator`, `Sudo_Vault_Experience`, `Sudo_Audit_Admin` | WP Sudo audit UI and validation |
| `Audit_Pro` | Merges extended search/retention into free audit UI |
| `Webhooks` | Outbound automation (`admin/views/automation-settings.php`) |
| `Manifest_Webhooks` | Fires on **`credential_vault_manifest_validation_failed`** |

Admin JS context: filter **`credential_vault_admin_context`** (pro sets `pro_active`, optional `external_secrets`).

## Features

- REST, webhooks, and CLI must use the same capability, sudo, and audit paths as free (no silent secret reads).
- Free **`Plugin_Adapters`** (namespace `CredentialVault\`) is separate from pro **`Plugin_Adapters`** — do not conflate the two classes.
