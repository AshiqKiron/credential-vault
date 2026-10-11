#!/usr/bin/env bash
# WordPress.org Plugin Directory checks for the free package (packages/free).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-$ROOT/packages/free}"
SLUG="${2:-new}"
MAIN_FILE="${SLUG}.php"

FLAGS=0

flag() {
	echo "ORG SUBMISSION: $1 — This will flag in the org submission." >&2
	FLAGS=$(( FLAGS + 1 ))
}

read_header_field() {
	local file="$1"
	local field="$2"
	grep -E "\\* ${field}:" "$file" 2>/dev/null | head -1 | sed -E "s/^[^:]*:[[:space:]]*//" | tr -d '\r' | sed -E 's/[[:space:]]+$//'
}

read_readme_field() {
	local file="$1"
	local field="$2"
	grep -E "^${field}:" "$file" 2>/dev/null | head -1 | sed -E "s/^[^:]*:[[:space:]]*//" | tr -d '\r' | sed -E 's/[[:space:]]+$//'
}

if [[ ! -d "$SRC" ]]; then
	echo "check-org-submission: source directory not found: $SRC" >&2
	exit 1
fi

MAIN_PATH="$SRC/$MAIN_FILE"
if [[ ! -f "$MAIN_PATH" ]]; then
	flag "Missing main plugin file \"${MAIN_FILE}\" (expected for slug \"${SLUG}\")."
else
	if ! grep -q 'Plugin Name:' "$MAIN_PATH"; then
		flag "Main file \"${MAIN_FILE}\" has no \"Plugin Name:\" header."
	fi
fi

README="$SRC/readme.txt"
if [[ ! -f "$README" ]]; then
	flag "Missing readme.txt (required for WordPress.org)."
else
	for field in 'Requires at least' 'Tested up to' 'Stable tag' 'License'; do
		if ! grep -qE "^${field}:" "$README"; then
			flag "readme.txt is missing \"${field}:\" (required for WordPress.org)."
		fi
	done
	if ! grep -qE '^Requires PHP:' "$README"; then
		flag "readme.txt is missing \"Requires PHP:\" (strongly recommended; reviewers often ask for it)."
	fi
fi

if [[ -f "$MAIN_PATH" && -f "$README" ]]; then
	HEADER_VERSION="$(read_header_field "$MAIN_PATH" 'Version')"
	CONST_VERSION="$(grep -E "define\s*\(\s*'CREDENTIAL_VAULT_VERSION'" "$MAIN_PATH" 2>/dev/null | head -1 | sed -E "s/.*'CREDENTIAL_VAULT_VERSION'[^']*'([^']+)'.*/\1/" || true)"
	STABLE_TAG="$(read_readme_field "$README" 'Stable tag')"

	if [[ -n "$HEADER_VERSION" && -n "$STABLE_TAG" && "$HEADER_VERSION" != "$STABLE_TAG" ]]; then
		flag "Version mismatch: plugin header (${HEADER_VERSION}) vs readme Stable tag (${STABLE_TAG})."
	fi
	if [[ -n "$HEADER_VERSION" && -n "$CONST_VERSION" && "$HEADER_VERSION" != "$CONST_VERSION" ]]; then
		flag "Version mismatch: plugin header (${HEADER_VERSION}) vs CREDENTIAL_VAULT_VERSION constant (${CONST_VERSION})."
	fi

	TEXT_DOMAIN="$(read_header_field "$MAIN_PATH" 'Text Domain')"
	if [[ -n "$TEXT_DOMAIN" && "$TEXT_DOMAIN" != "$SLUG" ]]; then
		flag "Text Domain \"${TEXT_DOMAIN}\" should match plugin slug \"${SLUG}\" for WordPress.org."
	fi
fi

# Placeholder / scaffold values reviewers reject.
if [[ -f "$MAIN_PATH" ]]; then
	if grep -q 'example\.com' "$MAIN_PATH"; then
		flag "Plugin header still uses example.com (placeholder URI)."
	fi
	if grep -q 'Your Name' "$MAIN_PATH"; then
		flag "Plugin header still uses \"Your Name\" (placeholder author)."
	fi
fi

if [[ -f "$README" ]]; then
	if grep -qE '^Contributors:\s*your-name\s*$' "$README"; then
		flag "readme.txt Contributors is still \"your-name\" (use your WordPress.org username)."
	fi
	if grep -q 'Rename the plugin slug' "$README"; then
		flag "readme.txt still tells users to rename the plugin (remove scaffold notes before org submit)."
	fi
fi

# Free zip must not ship pro add-on references or paths.
if grep -R -l -E 'new-pro|New Pro|packages/pro' "$SRC" --include='*.php' --include='*.js' --include='*.txt' 2>/dev/null | grep -q .; then
	flag "Free package references the pro add-on (new-pro / packages/pro); org expects a self-contained free plugin."
fi

# Common automated / manual review triggers.
if grep -R -l -E '\beval\s*\(' "$SRC" --include='*.php' 2>/dev/null | grep -q .; then
	flag "Use of eval() in PHP is commonly rejected or heavily scrutinized on WordPress.org."
fi

if find "$SRC" -type f \( -name '.git' -o -name 'node_modules' -o -name '.env' \) 2>/dev/null | grep -q .; then
	flag "Package contains .git, node_modules, or .env (must not be in org zip)."
fi

if [[ "$FLAGS" -gt 0 ]]; then
	echo >&2
	echo "Found ${FLAGS} org submission issue(s). Fix them before submitting to WordPress.org." >&2
	exit 1
fi

echo "Org submission check passed for ${SRC}."
