#!/usr/bin/env bash
# Fail if legacy root mirrors drift from packages/free (monorepo hygiene).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FREE="$ROOT/packages/free"
LEGACY_DIRS=(includes admin public)

missing=0

for dir in "${LEGACY_DIRS[@]}"; do
  if [[ ! -d "$ROOT/$dir" ]]; then
    continue
  fi
  while IFS= read -r -d '' file; do
    rel="${file#"$ROOT/$dir/"}"
    canonical="$FREE/$dir/$rel"
    if [[ ! -f "$canonical" ]]; then
      echo "Legacy file has no packages/free mirror: $dir/$rel"
      missing=1
      continue
    fi
    if ! diff -q "$file" "$canonical" >/dev/null 2>&1; then
      echo "Drift: $dir/$rel differs from packages/free/$dir/$rel"
      missing=1
    fi
  done < <(find "$ROOT/$dir" -type f -name '*.php' -print0 2>/dev/null || true)
done

if [[ "$missing" -ne 0 ]]; then
  echo "sync-check failed — edit packages/free/ and mirror to root if required."
  exit 1
fi

echo "sync-check passed."
