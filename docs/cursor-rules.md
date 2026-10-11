# Cursor rules in this repo

This monorepo uses [Cursor project rules](https://cursor.com/docs/context/rules) and nested **`AGENTS.md`** files so Agent follows the free/pro layout and WordPress conventions.

## What lives where

| File / folder | Role |
|---------------|------|
| [`AGENTS.md`](../AGENTS.md) | Repo-wide agent instructions (always in context for this project) |
| [`packages/free/AGENTS.md`](../packages/free/AGENTS.md) | Free plugin package (zip folder `credential-vault/`) |
| [`packages/pro/AGENTS.md`](../packages/pro/AGENTS.md) | Pro plugin package (zip folder `credential-vault-pro/`) |
| [`.cursor/rules/`](../.cursor/rules/) | Scoped `.mdc` rules with YAML frontmatter |

**`AGENTS.md`**: Cursor applies nested files when you work under that directory; root `AGENTS.md` applies everywhere in the repo.

**`.mdc` rules**: Loaded by frontmatter—either always, when matching files are in context, when the rule is `@`-mentioned, or when Cursor selects a rule by `description` (intelligent apply).

## Current `.mdc` rules

| File | `description` | `globs` | `alwaysApply` |
|------|-----------------|---------|---------------|
| [`wordpress-php.mdc`](../.cursor/rules/wordpress-php.mdc) | WordPress plugin PHP standards for packages/free and packages/pro | `packages/**/*.php` | `false` |
| [`monorepo-build.mdc`](../.cursor/rules/monorepo-build.mdc) | Release zips, symlinks, and monorepo layout for free/pro | `bin/**/*`, `package.json`, `composer.json` | `false` |

Keep this table in sync when you add or rename rules under `.cursor/rules/`.

## Editing rules

1. **Broad prose, no file patterns** — Edit the relevant `AGENTS.md` (root or under `packages/free/` / `packages/pro/`).
2. **File-specific behavior** — Add or edit `.cursor/rules/*.mdc` with `globs` and/or `alwaysApply`.
3. **From chat** — Run `/create-rule`, describe the behavior, then review the generated file under `.cursor/rules/`.

### `.mdc` frontmatter

```yaml
---
description: Shown in the rule picker; used for intelligent apply
globs: packages/**/*.php   # omit for always/manual/intelligent-only rules
alwaysApply: false           # true = every Agent session in this project
---
```

Rule types (see [official docs](https://cursor.com/docs/context/rules)):

| Type | Configuration |
|------|----------------|
| Always apply | `alwaysApply: true` |
| Specific files | `globs: …` (often with `alwaysApply: false`) |
| Intelligent | `description:` set; no `globs`; not always apply |
| Manual | Reference in chat with `@rule-name` |

Guidelines:

- One concern per rule; keep each file short and actionable (under ~50 lines of body when possible).
- Prefer `packages/**` globs over repo-root legacy paths (`includes/`, `admin/`, `public/` mirrors free for local dev only).
- Do not treat `dist/` or `.build/` as canonical source—they are build output.

## Monorepo reminders for Agent

- **Shippable source**: `packages/free/` and `packages/pro/` only (see `bin/build-zips.sh`).
- **Local dev**: Root `credential-vault.php` loads the free package; `npm run link` symlinks both packages into `wp-content/plugins/`.
- **Pro hook**: Extend free via `credential_vault_loaded` (see `packages/pro/includes/class-plugin.php`).
- **Coding standard**: `composer phpcs` targets `packages/` with WordPress CS.

### npm scripts (build / dev)

| Script | Purpose |
|--------|---------|
| `npm run zip` | Build free + pro zips under `dist/` |
| `npm run zip:free` / `npm run zip:pro` | Build one zip |
| `npm run link` | Symlink packages for MAMP/local WordPress |
| `npm run check:org` | Org submission checks (`bin/check-org-submission.sh`) |
| `npm run sync-check` | Legacy root `includes/`/`admin/`/`public/` mirrors match `packages/free/` |
| `npm run make-pot` | Regenerate `.pot` files for free + pro |

### Composer scripts (PHP quality / zips)

| Script | Purpose |
|--------|---------|
| `composer zip` | Same as `npm run zip` |
| `composer phpcs` | WordPress CS on `packages/` |
| `composer phpcbf` | Auto-fix where PHPCBF supports it |
| `composer sync-check` | Same as `npm run sync-check` |
| `composer make-pot` | Same as `npm run make-pot` |
| `composer dev:fixtures` | WP-CLI demo credentials (`WP_PATH` optional) |

## Product architecture (for Agent)

High-level feature map and extension hooks live in package **`AGENTS.md`** files (not in `.mdc` rules):

- [`packages/free/AGENTS.md`](../packages/free/AGENTS.md) — boot order, `Secrets_Bridge`, audit/sudo, admin tabs, CLI/manifest
- [`packages/pro/AGENTS.md`](../packages/pro/AGENTS.md) — full `register_pro_features()` map, REST/tokens, webhooks, activator

User-facing feature list: [`README.md`](../README.md). IDE stubs: [`stubs/credential-vault-stubs.php`](../stubs/credential-vault-stubs.php).

## Checklist for a new rule

- [ ] Correct location: `AGENTS.md` vs new `.cursor/rules/<name>.mdc`
- [ ] Frontmatter: `description`, and `globs` or `alwaysApply` as intended
- [ ] Body references `packages/free` and `packages/pro`, not only root legacy folders
- [ ] Row added to **Current `.mdc` rules** table in this doc (if you added an `.mdc` file)
