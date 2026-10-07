#!/usr/bin/env bash
# Builds dist/hsc-google-calendar.zip containing a single hsc-google-calendar/ folder
# with production-only composer dependencies (plugin-update-checker).
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=hsc-google-calendar
rm -rf dist && mkdir -p "dist/$SLUG"
cp -R "$SLUG.php" includes readme.txt CHANGELOG.md LICENSE composer.json composer.lock "dist/$SLUG/"
# Install into the build dir so the dev vendor/ of the working copy stays untouched.
composer install --working-dir="dist/$SLUG" --no-dev --optimize-autoloader --no-interaction --quiet
rm "dist/$SLUG/composer.json" "dist/$SLUG/composer.lock"
(cd dist && zip -qr "$SLUG.zip" "$SLUG" -x "*/.*" -x ".*")
rm -rf "dist/$SLUG"
echo "dist/$SLUG.zip"
