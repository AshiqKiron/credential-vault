#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGING="$ROOT/.build"
FREE_SRC="$ROOT/packages/free"
PRO_SRC="$ROOT/packages/pro"

read_version() {
	local file="$1"
	grep -E '^\s*\*\s*Version:' "$file" | head -1 | sed -E 's/[^0-9.]*([0-9.]+).*/\1/'
}

FREE_VERSION="$(read_version "$FREE_SRC/credential-vault.php")"
PRO_VERSION="$(read_version "$PRO_SRC/credential-vault-pro.php")"

mkdir -p "$DIST"
rm -rf "$STAGING"
mkdir -p "$STAGING"

stage_plugin() {
	local slug="$1"
	local src="$2"
	local dest="$STAGING/$slug"
	mkdir -p "$dest"
	rsync -a \
		--exclude '.DS_Store' \
		--exclude '*.log' \
		"$src/" "$dest/"
}

zip_plugin() {
	local slug="$1"
	local version="$2"
	local archive="$DIST/${slug}-${version}.zip"
	(
		cd "$STAGING"
		zip -r -q "$archive" "$slug"
	)
	echo "Created $archive"
}

build_free() {
	if [[ "${SKIP_ORG_CHECK:-}" != "1" ]]; then
		"$ROOT/bin/check-org-submission.sh" "$FREE_SRC" "credential-vault"
	fi
	stage_plugin "credential-vault" "$FREE_SRC"
	zip_plugin "credential-vault" "$FREE_VERSION"
}

build_pro() {
	stage_plugin "credential-vault-pro" "$PRO_SRC"
	zip_plugin "credential-vault-pro" "$PRO_VERSION"
}

TARGET="${1:-all}"

case "$TARGET" in
	all)
		build_free
		build_pro
		;;
	free)
		build_free
		;;
	pro)
		build_pro
		;;
	*)
		echo "Usage: $(basename "$0") [all|free|pro]" >&2
		exit 1
		;;
esac

rm -rf "$STAGING"
