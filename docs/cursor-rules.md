# Cursor rules in this repo

This monorepo uses [Cursor project rules](https://cursor.com/docs/context/rules) so Agent follows the free/pro layout and WordPress conventions.

## What lives where

| File / folder | Role |
|---------------|------|
| [`AGENTS.md`](../AGENTS.md) | Repo-wide agent instructions |
| [`packages/free/AGENTS.md`](../packages/free/AGENTS.md) | Free plugin package (zip `credential-vault/`) |
| [`packages/pro/AGENTS.md`](../packages/pro/AGENTS.md) | Pro plugin package (zip `credential-vault-pro/`) |
| [`.cursor/rules/`](../.cursor/rules/) | Scoped `.mdc` rules (PHP, build scripts) |

Nested `AGENTS.md` files apply when you edit files under that directory. Root `AGENTS.md` applies everywhere.

## Editing rules

1. **Quick prose**: Edit the relevant `AGENTS.md`.
2. **File-specific behavior**: Add or edit `.cursor/rules/*.mdc` with `globs` or `alwaysApply`.
3. **From chat**: Run `/create-rule` and describe what you want; review the generated file under `.cursor/rules/`.

Rule types (see official docs):

- **Always apply** — `alwaysApply: true` in frontmatter
- **Specific files** — `globs: packages/**/*.php`
- **Intelligent** — `description:` set, no globs
- **Manual** — `@rule-name` in chat

## Monorepo reminders for Agent

- **Shippable source**: `packages/free/` and `packages/pro/` only (see `bin/build-zips.sh`).
- **Local dev**: Root `credential-vault.php` loads the free package; use `npm run link` for pro.
- **Pro hook**: Extend free via `credential_vault_loaded` (see `packages/pro/includes/class-plugin.php`).
- **Coding standard**: `composer phpcs` targets `packages/` with WordPress CS.

Do not add release artifacts under `dist/` or `.build/` to rules as canonical source—they are build output.
