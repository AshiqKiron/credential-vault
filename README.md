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

**Free:** Centralized token dashboard, collections, health scores, block-content scanner, per-secret audit timeline, metadata diff (no secret values), slug linter, rotation iCal export, sudo-gated view/edit, auditor role, key usage audit log, site/network credentials, import wizard + plaintext scanner, credential metadata (environment, rotate-by, notes, collection), `manage_credential_vault` capability, Health/Migration/Developer admin tabs, one-click demo fixtures (`demo-` slugs; settings, onboarding, empty vault, token dashboard), WP-CLI (`wp credvault` — incl. `health-scores`, `blocks-scan`, `metadata-diff`, `rotation-calendar`, `hygiene`, `audit`, bundles, `demo`, usage), manifest validation for CI, encrypted bundle migration, dev profile on `local`/`development` (shorter sudo, demo UI, CLI/demo sudo skip), test storage via `CREDENTIAL_VAULT_TEST_MODE`, Site Health tests, `credential_vault_get()` / `credential_vault_set()` (audited; `source` normalized by Query Source).

**Pro:** REST API (`credential-vault/v1`, idempotency keys, manifest validate, scoped API tokens), JIT reveal grants, compromise playbook, extended audit store + search, webhook delivery log, remote health probes, outbound webhooks (incl. manifest validation failures), ACL/approvals/SSO gate, env sync CLI, template credentials, async import jobs, compliance/network tabs, SIEM/anomaly monitors, plus WP Sudo audit integration.

**Monorepo:** `npm run sync-check` / `composer sync-check` — legacy root mirrors must match `packages/free/`. PHPStan/IDE stubs: `stubs/credential-vault-stubs.php`. Example manifest: `docs/credvault.manifest.example.json`.

## Pro integration

The free plugin fires `credential_vault_loaded` after it registers hooks. Pro hooks there (see `packages/pro/includes/class-plugin.php`).
