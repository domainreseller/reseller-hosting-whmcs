#!/usr/bin/env bash
# Build the WHMCS release package.
#
#   tools/build-release-zip.sh [ref] [output-dir]
#
# Archive root: the modules/ folder, ready to unpack over a WHMCS installation, plus
# the README files and CHANGELOG. Only tracked files are packed, so OS files
# (__MACOSX, ._*, .DS_Store) and editor folders can never end up in it.
#
# Writes two identical files: DNAHosting-WHMCS-v<version>.zip and DNAHosting-WHMCS.zip.
# The unversioned name keeps releases/latest/download/DNAHosting-WHMCS.zip stable for
# the WHMCS Marketplace download link.
set -euo pipefail

cd "$(dirname "$0")/.."
REF="${1:-HEAD}"
OUT_DIR="${2:-dist}"

VERSION="$(git show "$REF:VERSION" | tr -d '[:space:]')"
[ -n "$VERSION" ] || { echo "VERSION not found at $REF" >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

git archive "$REF" modules | tar -x -C "$WORK"

EXTRA=()
while IFS= read -r f; do
    git show "$REF:$f" > "$WORK/$f"
    EXTRA+=("$f")
done < <(git ls-tree --name-only "$REF" | grep -E '^(README.*\.md|CHANGELOG\.md|LICENSE)$')

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"
VERSIONED="$OUT_DIR/DNAHosting-WHMCS-v${VERSION}.zip"
LATEST="$OUT_DIR/DNAHosting-WHMCS.zip"
rm -f "$VERSIONED" "$LATEST"
(cd "$WORK" && zip -q -r -X "$VERSIONED" modules "${EXTRA[@]}")
cp "$VERSIONED" "$LATEST"

echo "$VERSIONED"
echo "$LATEST"
