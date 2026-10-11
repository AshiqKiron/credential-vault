=== Credential Vault ===
Contributors: ashiquzzaman
Tags: secrets, security, api keys, credentials, encryption
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Admin dashboard for the WordPress Secrets API — encrypted API keys, sudo re-auth, rotation tooling, and audit history.

== Description ==

**Credential Vault** is a WordPress-native secrets manager built for the upcoming WordPress **Secrets API** (WordPress 7.2 core or the [Secrets API feature plugin](https://github.com/ericmann/secrets-api)). Whether you operate a WooCommerce store, run membership sites, ship custom plugins, or manage client sites as an agency, Credential Vault gives administrators one secure place to store Stripe keys, OpenAI tokens, OAuth client secrets, and custom credentials — without scattering plaintext values across `wp_options`, theme files, or shared spreadsheets.

Stop copying API keys into `.env` files that never match production, hunting through plugin settings for duplicate secrets, or pasting sensitive values into Slack when developers need access. With Credential Vault, every secret lives in encrypted storage behind WordPress capabilities and optional **sudo** re-authentication, with metadata, rotation workflows, and an audit trail that never logs decrypted values.

**Built for Security, Designed for WordPress:** Credential Vault wraps the Secrets API with a tabbed admin dashboard, token cards for popular SaaS integrations, plaintext discovery scans, and developer helpers such as `credential_vault_get()`. Unlike generic password managers or external vault SaaS tools, Credential Vault keeps secret **values** on your server using core APIs, loads **no frontend assets** on public pages, and integrates with Site Health, WP-CLI, and optional [WP Sudo](https://github.com/dknauss/Sudo) elevation.

#### Why Choose a WordPress-Based Credential Vault?

* **Encrypted Storage, Not Plain Options:** Store secrets through the WordPress Secrets API namespace `credential-vault` instead of leaving API keys in autoloaded options or custom tables as cleartext.
* **Sudo Before Reveal:** Administrators re-enter their password (or use WP Sudo when installed) before viewing, copying, or editing sensitive values — with configurable TTL and policy in Settings.
* **One Dashboard for Every Integration:** Token dashboard cards for Stripe, OpenAI, AWS, Mailchimp, SendGrid, reCAPTCHA, Twilio, Postmark, Slack, GitHub, Cloudflare, Google Maps, and OAuth client secrets, plus custom credentials.
* **Hygiene & Discovery:** Scan options and user meta for plaintext leaks, detect duplicates, import candidates in bulk, and get rotation reminders with admin notices.
* **Developer-Friendly:** Public PHP helpers, integration snippets, usage mapping, rotation test recipes (Stripe, OpenAI, and more), and `wp credvault` CLI commands for automation-friendly workflows.

#### How It Works

1. **Enable the Secrets API:** Run WordPress 7.2 or install the Secrets API feature plugin so encrypted storage is available.
2. **Open Credential Vault:** Go to **Credential Vault** in the admin menu and unlock the vault with your password when sudo mode is required.
3. **Add or Import Secrets:** Use the **Token dashboard** for common providers or **Site credentials** for custom slugs; run **Import** to migrate discovered plaintext keys.
4. **Rotate with Confidence:** Use the two-slot rotation workflow, built-in provider smoke tests, and the `credential_vault_rotation_test` filter before promoting a new key.
5. **Audit & Export:** Review the **Audit log** tab (metadata only — user IDs, actions, fingerprints — never secret values) and export CSV for compliance reviews.

#### Designed for Every WordPress Workflow

* **For Site Owners & Store Managers:** Centralize payment, email, AI, and spam-protection keys with environment badges and owner/runbook metadata so staging and production stay separated.
* **For Plugin & Theme Developers:** Register secrets with `credential_vault_register()`, read values via `credential_vault_get()`, and expose only non-secret fields (AWS access key ID, OAuth client ID, webhook URLs) in the UI.
* **For Agencies:** Use environment tags, encrypted bundle export/import, duplicate detection, and the optional **Credential Vault Auditor** read-only role for client handoffs.
* **For Multisite Networks:** Manage network-scoped credentials on the **Network** tab with site inheritance hints when a site slug is not configured locally.
* **For DevOps & Platform Teams:** WP-CLI (`list`, `status`, `scan`, `import --dry-run`, `get <slug> [--reveal]`), Site Health tests, and metadata JSON export fit CI and runbooks.

#### Comprehensive Vault Dashboard

Manage the full credential lifecycle from dedicated admin tabs:

* **Token dashboard:** Quick-add flows and status for first-party integration slugs (Stripe API key, OpenAI API key, AWS secret access key, and more).
* **Site credentials:** Full CRUD for site-scoped secrets with service presets, public companion fields, rotation dates, and promote-previous workflows.
* **Network:** Shared credentials for multisite when the Secrets API network API is available.
* **Import:** Review scanner and import-candidate results, dry-run bulk imports, and migrate off plaintext options safely.
* **Other secrets:** Read-only inventory of credentials registered by other plugins via `wp_list_secrets()`.
* **Audit log:** Filterable history with GDPR-oriented documentation; CSV export never includes decrypted secret values.
* **Settings:** Sudo TTL and mode, capabilities, rotation reminders, demo-data UI gate, and vault behavior tuned to your organization.

#### Why Teams Choose Credential Vault

* **Stop Secret Sprawl:** Replace ad hoc `wp_options` keys and hard-coded constants with one audited namespace and consistent slugs across projects.
* **Know Who Accessed What:** Programmatic reads through `credential_vault_get()` log to the audit trail when query logging is enabled — without storing the secret itself.
* **Rotate Without Downtime Surprises:** Two-slot storage, retire/promote flows, and optional live API smoke tests reduce broken checkout or email after key rotation.
* **Find Plaintext Before Attackers Do:** The plaintext scanner surfaces risky option keys, user meta, and plugin prefixes so you can import them into encrypted storage.
* **Stay in WordPress:** No separate vault SaaS login, no browser extension required for day-to-day admin work — just native menus, caps, and nonces.
* **Extend Without Forking:** Filters and actions for sudo, audit entries, admin tabs, service field templates, and rotation tests match WordPress coding expectations.

#### Free vs. Credential Vault Pro Features

**Free Version Features:**

* Full admin dashboard: token dashboard, site credentials, import, other secrets, audit log, settings
* WordPress Secrets API bridge (`credential-vault` namespace) with `credential_vault_get()`, `credential_vault_set()`, `credential_vault_register()`
* Sudo gate and optional WP Sudo integration for time-limited elevation
* Plaintext scanner, duplicate detector, import candidates, rotation reminders, and rotation recipes with Stripe/OpenAI smoke tests
* Audit log (option-backed) with CSV export of metadata only
* `manage_credential_vault` capability and **Credential Vault Auditor** read-only role
* Environment badges, owner/runbook metadata, custom public fields, Site Health tests, encrypted bundle tools, WP-CLI commands
* Multisite network credential tab when network secrets API is available
* Developer admin: integration snippets, usage map, rotation guide, onboarding, and health views

**Credential Vault Pro Features:**

* **REST API** under `credential-vault/v1` for list, rotate, reveal, delete, retire, promote-previous, metadata, bulk rotate, audit query, import jobs, compliance, and network workflows (requires free plugin)
* **Scoped Bearer API tokens** and **webhooks** (generic HTTPS, Slack incoming webhook, PagerDuty Events v2) for rotate, retire, failed tests, and anomaly-style events
* **Extended audit** retention and search via dedicated storage, plus SIEM-friendly JSON Lines export patterns
* **WP Sudo audit stack** when WP Sudo is active — session events, blocks, passes, lockouts, IP and user agent logging, custom vault re-auth copy, and fingerprint mismatch detection
* **Automation tab** in the vault UI for tokens, webhooks, and per-secret ACL configuration (Pro modules continue to expand on the same `credential_vault_loaded` hook)

#### Supercharge Your Secret Operations with Credential Vault Pro

Free Credential Vault covers encrypted storage, sudo-gated admin UX, discovery/import, rotation helpers, audit metadata, CLI, and developer documentation. **Credential Vault Pro** is built for teams that need REST automation, outbound notifications, long-retention audit, and tighter integration with WP Sudo — without moving secrets to a third-party cloud vault.

* **Automate rotation and CI:** Use Bearer-scoped REST tokens alongside Application Passwords for pipelines that rotate keys on a schedule.
* **Alert the right channel:** Send webhook events to Slack or PagerDuty when rotations fail, secrets become undecryptable, or policies require human review.
* **Deepen compliance visibility:** Retain and query audit history beyond the free option store; export formats suited to SIEM ingestion.
* **Harden sudo sessions:** Layer WP Sudo session auditing, custom challenge copy, and extra validation hooks on top of the free sudo gate.
* **Upgrade without re-importing:** Install **Credential Vault Pro** alongside the free plugin, activate, and enter your license; existing Secrets API entries and free-plugin metadata remain in place.

Learn more: [Credential Vault on Asphalt Themes](https://asphaltthemes.com/credential-vault)

#### More Notable Products From the Author

Credential Vault is developed by [Asphalt Themes](https://asphaltthemes.com/). These free products from the same author may also help your WordPress projects:

* **[Trustbadg – Trust Badges, Payment Icons & Secure Checkout Seals](https://wordpress.org/plugins/trustbadg-trust-badges/)** - Display trust badges and payment icons with Gutenberg blocks, shortcodes, and automatic e-commerce placements.
* **[Rezicraft – Professional Resume & CV Builder](https://wordpress.org/plugins/rezicraft-professional-resume-cv-builder/)** - Create polished online resumes and CVs with Gutenberg-friendly layouts and sections for experience and skills.
* **[Resumee](https://wordpress.org/themes/resumee/)** - A free WordPress resume and portfolio theme with SEO-friendly markup and flexible profile sections.

#### Why Credential Vault vs. External Secret Managers

Hosted password managers and cloud secret platforms are excellent for company-wide credential sharing, but WordPress sites often need secrets **inside** PHP runtime — payment keys, mail API tokens, OAuth pairs — without syncing every environment to an external API on every request.

* **Complete Data Ownership & Privacy**
  Secret **values** stay on your WordPress host via the Secrets API. Credential Vault does not phone home with secret contents, and the free audit log stores metadata only — never decrypted payloads.
* **Zero Extra Frontend Surface**
  The vault is admin-only. Public visitors do not load vault JavaScript or tracking pixels; only administrators with capabilities (and sudo when enabled) manage credentials.
* **Native WordPress Integration**
  Capabilities, nonces, Site Health, multisite network APIs, and standard admin screens — no iframe embed of a external vault UI required for daily operations.
* **Rotation Tests You Control**
  Built-in Stripe and OpenAI smoke tests call those providers **only when you run a test** during rotation — not on every page load.
* **Optional Pro Without Lock-In**
  REST and webhooks extend automation on your infrastructure. The free plugin remains fully functional for manual vault operations if Pro is not installed.

= Privacy =

Credential Vault does not transmit secret values to Asphalt Themes or other vendor servers as part of normal vault storage. Audit and settings data remain in your WordPress database. When you optionally run rotation smoke tests or third-party APIs read keys from your site code, those requests go directly from your server to the provider you configured — not through a Credential Vault cloud service. No analytics or visitor tracking is bundled with the admin UI.

= Performance =

Credential Vault is built for low overhead on public pages:

* **Admin-only UI:** Vault screens, scripts, and styles load on Credential Vault admin pages — not on the frontend for anonymous visitors.
* **No public secret endpoints in free:** Programmatic access uses WordPress APIs and PHP helpers; Pro REST routes require authentication and capabilities.
* **Cache-friendly:** No frontend cookies or sessions for vault features; encrypted secrets are read server-side when your code calls `credential_vault_get()` or admin users reveal values under sudo.
* **Site Health:** Declares tests so administrators see Secrets API availability and vault readiness from the WordPress Site Health screen.

== Installation ==

#### Quick WordPress Installation

1. In your WordPress admin dashboard, navigate to **Plugins → Add New**.
2. Search for _Credential Vault_.
3. Click **Install Now**, then click **Activate**.
4. Ensure WordPress 7.2 (or the [Secrets API feature plugin](https://github.com/ericmann/secrets-api)) is available so encrypted storage works.
5. Open **Credential Vault** in the admin menu, unlock sudo if prompted, and add your first credential from the **Token dashboard** or **Site credentials** tab.

#### Manual Installation

1. Download the plugin archive and upload the `credential-vault` folder to the `/wp-content/plugins/` directory on your server.
2. Activate the plugin through the **Plugins** management screen in WordPress.
3. Install or upgrade to a WordPress version with the Secrets API, or install the Secrets API feature plugin from GitHub.
4. Visit **Credential Vault → Settings** to configure sudo TTL and capabilities, then store secrets from the dashboard tabs.

== Frequently Asked Questions ==

= Who is Credential Vault built for? =

Credential Vault is built for WordPress administrators, developers, agencies, and platform teams who need encrypted API key storage, sudo-gated access, rotation workflows, and audit metadata — especially as the WordPress Secrets API becomes available in core.

= Does this plugin require WordPress 7.2? =

Encrypted storage requires the WordPress Secrets API. Use WordPress 7.2 when released, or install the [Secrets API feature plugin](https://github.com/ericmann/secrets-api) on supported WordPress versions. The admin UI still loads without the API but shows a notice until storage is available.

= Where are secrets stored? =

Secret values are stored through the WordPress Secrets API under the `credential-vault` namespace. Non-secret metadata (environment, owner, runbook links, public companion fields) is stored separately according to the plugin’s metadata layer — never mixed into audit CSV exports as cleartext secrets.

= What is sudo mode? =

Sudo mode requires administrators to re-authenticate (typically by re-entering their WordPress password) before revealing or changing sensitive values. Session length and strictness are configurable under **Credential Vault → Settings**. If the [WP Sudo](https://github.com/dknauss/Sudo) plugin is active, Credential Vault can integrate with its elevation flow; Pro adds extended WP Sudo audit logging.

= Does Credential Vault log secret values in the audit trail? =

No. Audit entries record actions, user IDs, slugs, and fingerprints — not decrypted secret values. CSV export follows the same rule for GDPR-friendly reviews.

= Can I migrate existing plaintext keys? =

Yes. Use **Import** and discovery tools to scan options and user meta, review candidates, and import into encrypted storage. WP-CLI supports `wp credvault scan` and `wp credvault import --dry-run` for scripted workflows.

= Is REST API access available on Free? =

REST routes, Bearer API tokens, webhooks, extended audit storage, and the Automation admin tab are **Pro** features. The free plugin includes PHP helpers, admin UI, WP-CLI, and Site Health integration.

= Does Credential Vault work on multisite? =

Yes. When the network Secrets API is available, use the **Network** tab for shared credentials; site tabs show inheritance hints when a network slug applies.

= What happens when I uninstall the plugin? =

Review WordPress Secrets API and plugin documentation before uninstalling. Secrets stored via core APIs may remain according to core and provider behavior; plugin-specific options, audit entries, and metadata may be removed depending on your uninstall procedures. Export audit CSV and metadata JSON before removal if you need retention.

= How do I upgrade to Pro? =

Install and activate the free **Credential Vault** plugin first, then install **Credential Vault Pro** from Asphalt Themes, activate, and enter your license key. Existing encrypted secrets managed through the free bridge remain available to Pro features.

== Screenshots ==

1. Credential Vault overview with token dashboard and Secrets API status.
2. Token dashboard cards for Stripe, OpenAI, AWS, and other bundled integrations.
3. Site credentials table with environment badges, rotation dates, and sudo-gated actions.
4. Import tab showing plaintext scan results and import candidates.
5. Audit log with filters and CSV export (metadata only).
6. Settings tab for sudo TTL, capabilities, and rotation reminders.
7. Rotation workflow with two-slot promote and provider smoke test results.
8. Site Health section showing Credential Vault and Secrets API tests.
9. Developer integration snippets and usage map (Developer tools).
10. Network credentials panel on multisite installations.

== Changelog ==

#### 1.0.0

* Initial public release: Credential Vault admin dashboard for the WordPress Secrets API with token dashboard, site and network credentials, import and discovery, sudo gate, audit log, rotation workflows, WP-CLI, Site Health, and developer helpers.
* **Credential Vault Pro** (separate plugin) adds REST API, Bearer tokens, webhooks, extended audit, WP Sudo audit integration, and Automation admin settings. Details: https://asphaltthemes.com/credential-vault

== Upgrade Notice ==

= 1.0.0 =
Initial release. Requires the WordPress Secrets API (core 7.2 or feature plugin) for encrypted storage.
