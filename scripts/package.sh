#!/usr/bin/env bash
#
# Build the Zen Cart artifact, reproducibly: dist/tack-zencart.zip.
#
# Split out of the hub repository's scripts/package-all.sh
# (https://github.com/tackquote/tack-ecommerce-extensions) when this companion moved
# to its own repository.
#
# No package installer: the admin copies a file tree over the store root and runs
# the SQL through Admin > Tools > Install SQL Patches. The wrapper dir keeps the
# SQL findable and stops the tree from being dumped loose into a download folder;
# what the admin copies is the contents of store-root/.
#
# Usage: scripts/package.sh [outdir]     (default: dist)

set -Eeuo pipefail

OUT="${1:-dist}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
rm -rf "$OUT" && mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Excluded from every artifact. `-x` patterns are matched by zip against the
# paths as it stores them, so they are relative to the staged tree.
COMMON_EX=( -x '*/.git/*' -x '*/.gitignore' -x '*/.DS_Store' -x '*/__MACOSX/*' -x '*/._*' )

say() { printf '  %s
' "$*"; }

pack() { # pack <zipname> <top-level-dir> [extra zip -x args...]
  local name="$1" top="$2"; shift 2
  ( cd "$STAGE" && zip -q -r -X "$OUT/$name" "$top" "${COMMON_EX[@]}" "$@" )
  say "$name  $(wc -c < "$OUT/$name" | tr -d ' ') bytes"
}

say "zencart"
rm -rf "$STAGE/tack-zencart" && mkdir -p "$STAGE/tack-zencart/store-root"
for p in tack-connector includes ajax_tack_quote_request.php; do
  [ -e "$ROOT/$p" ] && cp -R "$ROOT/$p" "$STAGE/tack-zencart/store-root/"
done
cp -R "$ROOT/zc_install" "$STAGE/tack-zencart/"
cp "$ROOT/README.md" "$STAGE/tack-zencart/"
find "$STAGE/tack-zencart" -name '.DS_Store' -delete 2>/dev/null || true
pack tack-zencart.zip tack-zencart

echo
echo "artifacts in $OUT:"
ls -1 "$OUT"
