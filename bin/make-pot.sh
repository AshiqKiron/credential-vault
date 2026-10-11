#!/usr/bin/env bash
# Regenerate translation template (.pot) files for free and pro packages.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FREE="$ROOT/packages/free"
PRO="$ROOT/packages/pro"

WP_CLI="${WP_CLI:-wp}"
if ! command -v "$WP_CLI" >/dev/null 2>&1; then
	if [[ -f "${WP_CLI_PHAR:-/tmp/wp-cli.phar}" ]]; then
		WP_CLI="php ${WP_CLI_PHAR:-/tmp/wp-cli.phar}"
	elif [[ -f "$ROOT/.tools/wp-cli.phar" ]]; then
		WP_CLI="php $ROOT/.tools/wp-cli.phar"
	else
		echo "make-pot: WP-CLI not found. Install wp, set WP_CLI, or download a phar to WP_CLI_PHAR." >&2
		exit 1
	fi
fi

EXCLUDE='node_modules,vendor,dist,.build'

mkdir -p "$FREE/languages" "$PRO/languages"

$WP_CLI i18n make-pot "$FREE" "$FREE/languages/credvault.pot" \
	--slug=credvault \
	--domain=credvault \
	--exclude="$EXCLUDE"

$WP_CLI i18n make-pot "$PRO" "$PRO/languages/credential-vault-pro.pot" \
	--slug=credential-vault-pro \
	--domain=credential-vault-pro \
	--exclude="$EXCLUDE"

echo "Updated:"
echo "  packages/free/languages/credvault.pot"
echo "  packages/pro/languages/credential-vault-pro.pot"
