#!/usr/bin/env bash
# Baut dist/cinerent-shop-<version>.zip (ohne Entwicklungsdateien, siehe .distignore).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(sed -n 's/^ \* Version: *//p' "$ROOT/cinerent-shop.php" | head -n1 | tr -d '[:space:]')"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/cinerent-shop" "$ROOT/dist"
tar -C "$ROOT" --exclude-from="$ROOT/.distignore" -cf - . | tar -C "$TMP/cinerent-shop" -xf -

rm -f "$ROOT/dist/cinerent-shop-$VERSION.zip"
(cd "$TMP" && zip -rqX "$ROOT/dist/cinerent-shop-$VERSION.zip" cinerent-shop)
echo "dist/cinerent-shop-$VERSION.zip"
