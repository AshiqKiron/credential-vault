# Free plugin (`credential-vault`)

Distributable free plugin. Plugin header and bootstrap: `credential-vault.php`. Core wiring: `includes/class-plugin.php` (namespace `CredentialVault\`).

## Hooks

- Register hooks via `Loader` in `includes/class-loader.php` (pass `$loader` into `::register( Loader $loader )` where used).
- Fire **`credential_vault_loaded`** after hooks are registered so pro (or other extensions) can attach.

## i18n

- Text domain: **`credvault`**
- Template: `languages/credvault.pot` — regenerate with `npm run make-pot` from repo root when adding translatable strings.

## Secrets and capabilities

- **`Secrets_Bridge`** (`includes/class-secrets-bridge.php`) is the only path to WordPress Secrets API storage. Namespace: `credential-vault`; admin cap: **`manage_credential_vault`** (`Vault_Capabilities`). Auditor role: **`credvault_auditor`** (see **`Auditor_Experience`**).
- Permission checks use **`credential_vault_user_can`** (filter). Pro may override via bearer tokens or ACL.
- Never store or log plaintext secrets in options, transients, or audit payloads.
- **`Test_Storage`** — in-memory secrets when test mode is on; **`Dev_Profile`** — local/dev conveniences (non-production only).

## Audit and sudo

- **`Audit_Log`** — option-backed log; **`credential_vault_get()`** and reveal flows must log via `Audit_Log::log_secret_queried()` (optional caller `source` in context).
- **`Sudo_Gate`** / **`Sudo_Integration`** — time-limited elevation for view/edit; **`Admin_Bar_Sudo`**, **`Menu_Badge`** surface sudo state in admin chrome.
- Settings: **`Vault_Settings`** (`credential_vault_sudo_ttl`, `credential_vault_sudo_mode`).
- Extension hooks: `credential_vault_audit_entry`, `credential_vault_log_secret_queries`, `credential_vault_before_sudo_establish`, `credential_vault_before_set_secret`.

## Admin UI

- Menu and assets: `admin/class-admin-admin.php`
- POST/AJAX handlers: `admin/class-admin-vault-actions.php`
- Tabbed dashboard: `admin/views/vault-dashboard.php` — extend with **`credential_vault_admin_tabs`**, **`credential_vault_render_admin_tab`**, and **`credential_vault_admin_context`**
- Dedicated admin: **`Developer_Admin`** (`vault-developer.php`), **`Features_Admin`** (features overview)
- **`Dashboard_Widget`** — wp-admin dashboard summary
- Views also include: onboarding, migrate, health, audit, import, rotation guide, settings, sudo panel, token dashboard, scope panel (`admin/views/`)

## Boot order (`Plugin` constructor)

Registered before admin hooks: **`Site_Health`**, **`Rotation_Reminders`**, **`Rotation_Recipes`**, **`Vault_Capabilities`**, **`Hygiene_Scheduler`**, **`Plugin_Adapters`**, **`Auditor_Experience`**, **`Dev_Profile`**, **`Test_Storage`**, then admin-only **`Developer_Admin`** / **`Features_Admin`**.

Admin hooks: **`Audit_Log`**, **`Sudo_Integration`**, **`Vault_Actions`**, **`Dashboard_Widget`**, **`Admin_Bar_Sudo`**, **`Menu_Badge`**, **`Admin`**.

## Other free modules

| Area | Class / file |
|------|----------------|
| Public API | `includes/functions-api.php` (`credential_vault_get`, etc.) |
| Metadata / env | `class-credential-meta.php`, `class-environment-guard.php` |
| Rotation | `class-rotation-workflow.php`, `class-rotation-recipes.php`, `class-rotation-reminders.php` |
| Import / hygiene | `class-import-candidates.php`, `class-plaintext-scanner.php`, `class-duplicate-detector.php`, `class-hygiene-scheduler.php` |
| Health / onboarding | `class-vault-health.php`, `class-onboarding.php`, `class-audit-activity.php` |
| Demo fixtures | `class-demo-data.php` (slug prefix; gated by `credential_vault_demo_data_ui`) |
| CLI | `class-cli.php` (list, scan, validate, bundle, demo, usage, dedupe, …) |
| Manifest / CI | `class-secrets-manifest.php`, `class-config-constant-scanner.php` |
| Encrypted migration | `class-vault-bundle.php` |
| Dev profile / test mode | `class-dev-profile.php`, `class-test-storage.php` |
| Developer UX | `class-integration-snippets.php`, `class-usage-map.php`, `class-credentials-filter.php` |
| Third-party shims | `class-plugin-adapters.php` (free; distinct from pro homonym) |
| Site Health | `class-site-health.php` |

## Admin / public

- Public: `public/class-public-public-facing.php`
- Enqueue assets only on relevant screens / front-end as today’s classes do.

Do not add pro-only logic here; keep free standalone for org submission builds (`bin/build-zips.sh free`).
