# Free plugin (`credential-vault`)

Distributable free plugin. Plugin header and bootstrap: `credential-vault.php`. Core wiring: `includes/class-plugin.php` (namespace `CredentialVault\`).

## Hooks

- Register hooks via `Loader` in `includes/class-loader.php`.
- Fire **`credential_vault_loaded`** after hooks are registered so pro (or other extensions) can attach.

## i18n

- Text domain: **`credvault`**
- Strings in `languages/`; update `.pot` when adding translatable strings.

## Admin / public

- Admin: `admin/class-admin-admin.php`
- Public: `public/class-public-public-facing.php`
- Enqueue assets only on relevant screens / front-end as today’s classes do.

Do not add pro-only logic here; keep free standalone for org submission builds (`bin/build-zips.sh free`).
