# Credential Vault — monorepo agent instructions

WordPress plugin monorepo: **free** (`packages/free` → zip folder `credential-vault/`) and **pro** (`packages/pro` → `credential-vault-pro/`).

## Where to change code

- Put all product changes in **`packages/free/`** or **`packages/pro/`**.
- Root `credential-vault.php` is the local dev bootstrap; it requires `packages/free/credential-vault.php`.
- Legacy mirrors at repo root (`includes/`, `admin/`, `public/`) should stay in sync with `packages/free/` if you touch them—prefer editing **`packages/free/`** only.

## Build and release

- Zips: `npm run zip` or `composer zip` → `dist/credential-vault-{version}.zip`, `dist/credential-vault-pro-{version}.zip`.
- Pro depends on free; hook pro features on **`credential_vault_loaded`**, not before free boots.

## PHP conventions

- `declare( strict_types=1 );` on new PHP files.
- Namespaces: `CredentialVault\` (free), `CredentialVaultPro\` (pro).
- WordPress APIs, escaping, nonces, and capability checks for admin/user-facing code.
- Run `composer phpcs` before suggesting large PHP changes under `packages/`.

## Product boundaries

- **Free** — vault UI, Secrets API bridge, sudo-gated reveal, audit log, hygiene/onboarding/health, manifest CI, developer tab, bundle migration, test storage, WP-CLI. See [`packages/free/AGENTS.md`](packages/free/AGENTS.md).
- **Pro** — REST + API tokens, ACL, approvals, env sync, automation webhooks, extended audit store, compliance/network tabs, enterprise monitors; all register in **`Plugin::register_pro_features()`** on **`credential_vault_loaded`**. See [`packages/pro/AGENTS.md`](packages/pro/AGENTS.md).
- Route all secret I/O through **`CredentialVault\Secrets_Bridge`**; log programmatic reads; do not bypass sudo or capability filters for convenience.
- **Monorepo hygiene**: `npm run sync-check` keeps root legacy mirrors aligned with `packages/free/`. PHP stubs: `stubs/credential-vault-stubs.php`. Example manifest: `docs/credvault.manifest.example.json`.

## Scope

For package-specific detail, see nested `AGENTS.md` under `packages/free/` and `packages/pro/`. For Cursor setup, see [`docs/cursor-rules.md`](docs/cursor-rules.md).
