#!/usr/bin/env bash
# Builds dist/hsc-google-calendar.zip containing a single hsc-google-calendar/ folder.
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=hsc-google-calendar
rm -rf dist && mkdir -p "dist/$SLUG"
cp -R "$SLUG.php" includes readme.txt CHANGELOG.md LICENSE "dist/$SLUG/"
(cd dist && zip -qr "$SLUG.zip" "$SLUG")
rm -rf "dist/$SLUG"
echo "dist/$SLUG.zip"
