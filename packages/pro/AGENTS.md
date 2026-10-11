# Pro plugin (`credential-vault-pro`)

Distributable pro add-on. Bootstrap: `credential-vault-pro.php`. Namespace: `CredentialVaultPro\`.

## Dependency on free

- Pro must not assume free is loaded until verified (see `Plugin::is_free_active()`).
- Register features on **`credential_vault_loaded`**; if that action already ran, call registration immediately (pattern in `includes/class-plugin.php`).

## i18n

- Text domain: **`credential-vault-pro`**

## Features

- Replace placeholder admin notices with real pro features without breaking free when pro is inactive.
