# Free plugin (`credential-vault`)

Distributable free plugin. Plugin header and bootstrap: `credential-vault.php`. Core wiring: `includes/class-plugin.php` (namespace `CredentialVault\`).

## Code layout

- **`includes/`** — `CredentialVault\*` domain classes (`class-*.php`).
- **`admin/`** — `CredentialVault\Admin\*` UI/helpers (`class-*.php`, `class-admin-*.php` for legacy names like `Admin`). Autoloader maps sub-namespaces under `admin/` (see `includes/class-autoloader.php`).
- **`admin/views/`** and **`admin/partials/`** — tab templates only; keep business logic in classes.

## Hooks

- Register hooks via `Loader` in `includes/class-loader.php` (pass `$loader` into `::register( Loader $loader )` where used).
- Fire **`credential_vault_loaded`** after hooks are registered so pro (or other extensions) can attach.

## i18n

- Text domain: **`credvault`**
- Template: `languages/credvault.pot` — regenerate with `npm run make-pot` from repo root when adding translatable strings.

## Secrets and capabilities

- **`Secrets_Bridge`** (`includes/class-secrets-bridge.php`) is the only path to WordPress Secrets API storage. Namespace: `credential-vault`; admin cap: **`manage_credential_vault`** (`Vault_Capabilities`). Auditor role: **`credvault_auditor`** (see **`Auditor_Experience`**).
- Permission checks use **`credential_vault_user_can`** (filter). Pro may override via bearer tokens or ACL.
- Never store or log plaintext secrets in options, transients, or audit payloads. Demo audit rows store SHA-256 fingerprints only (`Demo_Data::fingerprint()`).
- **`Test_Storage`** — in-memory secrets when test mode is on (`CREDENTIAL_VAULT_TEST_MODE` or filter **`credential_vault_test_mode`**). It forces **`credential_vault_available`** and short-circuits **`pre_credential_vault_get`** / **`pre_credential_vault_set`**.
- **`Dev_Profile`** — active on `local` / `development` (or filter **`credential_vault_dev_profile`**). On those environments it shortens sudo TTL, shows demo UI, and allows skipping sudo for CLI and demo install.

## Audit and sudo

- **`Audit_Log`** — option-backed log; **`credential_vault_get()`** / **`credential_vault_set()`** must log via `Audit_Log::log_secret_queried()`. Pass a `source` in the context array; **`Query_Source`** normalizes it through **`credential_vault_normalize_query_source`** (presets: `cli`, `wp-cron`, `rest-api`, `woocommerce`, `rotation-test`; extend via **`credential_vault_query_source_presets`**).
- Short-circuit storage with **`pre_credential_vault_get`** / **`pre_credential_vault_set`** (still audit the read). Availability is filterable: **`credential_vault_available`**.
- **`Sudo_Gate`** / **`Sudo_Integration`** — time-limited elevation for view/edit; **`Admin_Bar_Sudo`**, **`Menu_Badge`** surface sudo state in admin chrome.
- Settings: **`Vault_Settings`** (`credential_vault_sudo_ttl`, `credential_vault_sudo_mode`).
- Extension hooks: `credential_vault_audit_entry`, `credential_vault_log_secret_queries`, `credential_vault_before_sudo_establish`, `credential_vault_before_set_secret`, `credential_vault_skip_sudo_for_cli`, `credential_vault_user_can`.

## Admin UI

- Menu and assets: `admin/class-admin-admin.php`
- POST/AJAX handlers: `admin/class-admin-vault-actions.php`
- Tabbed dashboard: `admin/views/vault-dashboard.php` — extend with **`credential_vault_admin_tabs`**, **`credential_vault_render_admin_tab`**, and **`credential_vault_admin_context`**
- Tab registrars: **`Developer_Admin`** (`developer` → `vault-developer.php`), **`Features_Admin`** (`health`, `migrate` → `vault-health.php`, `vault-migrate.php`); both respect **`Auditor_Experience::tab_allowed()`**
- **`Field_Help`** — shared label tooltips in admin forms
- **`Admin\Dashboard_Widget`** — wp-admin dashboard summary; **`Admin\Admin_Bar_Sudo`**, **`Admin\Menu_Badge`** — sudo chrome
- Admin script (`admin/js/admin.js`) clears the clipboard after reveal. Default **60** seconds; filter **`credential_vault_clipboard_clear_seconds`** (localized as `credentialVaultConfig.clipboardClearSeconds`).
- Dashboard context (`credential_vault_admin_context`) includes `demo_can_install`, `audit_activity` (from **`Audit_Activity::index_by_secret()`**), and `auditor_only`. **`Credentials_Filter`** and the credentials table use that activity index for last-seen labels.
- Views also include: onboarding, migrate, health, audit, import, rotation guide, settings, sudo panel, token dashboard, scope panel (`admin/views/`)
- Demo install UI: shared form **`admin/partials/vault-demo-install-form.php`** (settings, onboarding, empty credentials table) and promo card **`admin/partials/vault-demo-install-card.php`** (token dashboard). Show only when **`Demo_Data::can_install()`** is true.

## Boot order (`Plugin` constructor)

Registered before admin hooks: **`Site_Health`**, **`Rotation_Reminders`**, **`Rotation_Recipes`**, **`Vault_Capabilities`**, **`Hygiene_Scheduler`**, **`Plugin_Adapters`**, **`Auditor_Experience`**, **`Extended_Features`**, **`Dev_Profile`**, **`Test_Storage`**, then admin-only **`Developer_Admin`** / **`Features_Admin`**.

**`Extended_Features`** is the bundle for hook-based free add-ons (`Slug_Linter`, `Rotation_Calendar`, `Query_Source`). Add new `::register()` calls there when a module needs `rest_api_init`, WP-CLI subcommands wired at boot, etc. Pure helpers used only from admin/CLI/health (no hooks) can stay standalone classes.

Admin hooks: **`Audit_Log`**, **`Sudo_Integration`**, **`Admin\Vault_Actions`**, **`Admin\Dashboard_Widget`**, **`Admin\Admin_Bar_Sudo`**, **`Admin\Menu_Badge`**, **`Admin\Admin`** (menu/assets).

## Other free modules

| Area | Class / file |
|------|----------------|
| Public API | `includes/functions-api.php` — `credential_vault_available()`, `credential_vault_get()`, `credential_vault_set()`, `credential_vault_register()` |
| Metadata / env | `class-credential-meta.php` (incl. **`Secret_Collections`** on credentials), `class-environment-guard.php`, `class-config-constant-scanner.php` |
| Rotation | `class-rotation-workflow.php`, `class-rotation-recipes.php`, `class-rotation-reminders.php` |
| Import / hygiene | `class-import-candidates.php`, `class-plaintext-scanner.php`, `class-duplicate-detector.php`, `class-hygiene-scheduler.php` |
| Health / onboarding | `class-vault-health.php`, `class-onboarding.php`, `class-audit-activity.php` (metadata-only last activity per secret) |
| Demo fixtures | `class-demo-data.php` — slug prefix `demo-`; UI filter **`credential_vault_demo_data_ui`**; skip sudo filter **`credential_vault_skip_sudo_for_demo_install`** (default false); fixture list **`credential_vault_demo_data_fixtures`**. CLI: `wp credvault demo`. |
| CLI | `class-cli.php` — `wp credvault` subcommands: `list`, `status`, `scan`, `get`, `validate`, `set`, `bundle-export`, `bundle-import`, `dedupe`, `rotate`, `demo`, `usage`, `import`, `hygiene`, `audit`, `health-scores`, `blocks-scan`, `metadata-diff`, `rotation-calendar` |
| Manifest / CI | `class-secrets-manifest.php` |
| Encrypted migration | `class-vault-bundle.php` |
| Dev profile / test mode | `class-dev-profile.php`, `class-test-storage.php` |
| Developer UX | `class-integration-snippets.php`, `class-usage-map.php`, `class-credentials-filter.php` |
| Extended / hygiene UX | `class-slug-linter.php`, `class-rotation-calendar.php`, `class-query-source.php` (via **`Extended_Features`**) |
| Collections / scores / scan | `class-secret-collections.php`, `class-secret-health-scores.php`, `class-block-scanner.php`, `class-secret-timeline.php`, `class-metadata-diff.php` (metadata-only; used in health, features admin, CLI) |
| Third-party shims | `class-plugin-adapters.php` (free; distinct from pro homonym) |
| Site Health | `class-site-health.php` |

## Admin / public

- Public: `public/class-public-public-facing.php`
- Enqueue assets only on relevant screens / front-end as today’s classes do.

Do not add pro-only logic here; keep free standalone for org submission builds (`bin/build-zips.sh free`).
