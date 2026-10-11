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

**Free:** Vault dashboard, sudo re-auth, site/network credentials, import wizard, Site Health tests, `credential_vault_get()` helper.

**Pro:** REST API (`credential-vault/v1`), discovery tab for other plugins’ secrets.

## Pro integration

The free plugin fires `credential_vault_loaded` after it registers hooks. Pro hooks there (see `packages/pro/includes/class-plugin.php`).
