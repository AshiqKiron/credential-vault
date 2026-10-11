# Credential Vault — free / pro monorepo

WordPress plugin source is split into distributable packages:

| Path | Zip output | Install folder |
|------|------------|----------------|
| `packages/free/` | `dist/credential-vault-{version}.zip` | `wp-content/plugins/credential-vault/` |
| `packages/pro/` | `dist/credential-vault-pro-{version}.zip` | `wp-content/plugins/credential-vault-pro/` |

## Local development

**Option A — monorepo inside `wp-content/plugins/credential-vault`**

Activate **Credential Vault** from the repo root (`credential-vault.php` loads `packages/free`). For pro:

```bash
npm run link   # symlinks credential-vault and credential-vault-pro under wp-content/plugins/
```

**Option B — symlinks only**

From any clone path, `npm run link` installs both plugin folders under `wp-content/plugins/`.

## Build release zips

```bash
npm run zip
```

Artifacts land in `dist/`. Each archive contains a single top-level folder ready for WordPress.

## Features

**Free:** Centralized token dashboard (Stripe, OpenAI, AWS, Mailchimp), sudo-gated view/edit, key usage audit log, site/network credentials, import wizard + plaintext option scanner, credential metadata (environment, rotate-by, notes), `manage_credential_vault` capability, audit CSV export, WP-CLI (`wp credvault list|status|scan|validate|set|rotate|demo|usage|dedupe|bundle-export|bundle-import`), manifest validation for CI, Developer admin tab (usage map + integration snippets), encrypted bundle migration, dev profile on `local`/`development`, test-mode in-memory storage, Site Health tests, `credential_vault_get()` helper (access is audited, optional caller `source` context).

**Pro:** REST API (`credential-vault/v1`, manifest validate, scoped API tokens), extended audit retention/search, outbound webhooks (incl. `vault.validation_failed`), plugin option adapters, env sync + `.env.example` CLI, template credentials, async import jobs, plus WP Sudo audit integration.

**Monorepo:** `npm run sync-check` / `composer sync-check` — legacy root mirrors must match `packages/free/`. PHPStan/IDE stubs: `stubs/credential-vault-stubs.php`. Example manifest: `docs/credvault.manifest.example.json`.

## Pro integration

The free plugin fires `credential_vault_loaded` after it registers hooks. Pro hooks there (see `packages/pro/includes/class-plugin.php`).
