#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGINS_DIR="$(cd "$ROOT/.." && pwd)"

link_target() {
	local name="$1"
	local source="$2"
	local link="$PLUGINS_DIR/$name"

	if [[ "$link" -ef "$ROOT" ]]; then
		echo "Skip $name: would replace the monorepo directory itself."
		echo "Use the root credential-vault.php for local free development, or clone outside wp-content/plugins."
		return 0
	fi

	ln -sfn "$source" "$link"
	echo "Linked $link -> $source"
}

link_target "credential-vault" "$ROOT/packages/free"
link_target "credential-vault-pro" "$ROOT/packages/pro"

echo "Done. Activate Credential Vault and Credential Vault Pro from the Plugins screen."
