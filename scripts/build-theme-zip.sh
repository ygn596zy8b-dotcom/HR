#!/usr/bin/env bash
# Builds headline-recorder-theme.zip: the child theme with the hr-platform plugin (incl. vendor/), the mu-plugins
# and the web-root/server files bundled inside. Upload it in wp-admin → Appearance → Themes → Add New → Upload
# ("Replace current with uploaded"), then wp-admin → Headline Recorder → Install.
set -euo pipefail
cd "$(dirname "$0")/.."
STAGE=$(mktemp -d); trap 'rm -rf "$STAGE"' EXIT
T=$STAGE/headlinerec-child
rsync -a --exclude 'assets/src' --exclude 'tests' --exclude 'bundled' wp-content/themes/headlinerec-child/ "$T/"
mkdir -p "$T/bundled/mu-plugins"
rsync -a -L --exclude 'assets/src' --exclude 'tests' --exclude 'node_modules' wp-content/plugins/hr-platform/ "$T/bundled/hr-platform/"
[ -f "$T/bundled/hr-platform/vendor/autoload.php" ] || { echo "vendor missing: run composer install --no-dev in wp-content/plugins/hr-platform"; exit 1; }
cp wp-content/mu-plugins/*.php "$T/bundled/mu-plugins/"
rsync -a deploy/webroot/ "$T/bundled/webroot/"
cp infra/litespeed/htaccess-hardening.conf deploy/wp-config.sample.php "$T/bundled/"
find "$T" \( -name '*.secret.json' -o -name '.git' -o -name '*.map' \) -prune -exec rm -rf {} +
rm -f headline-recorder-theme.zip
(cd "$STAGE" && zip -qr "$OLDPWD/headline-recorder-theme.zip" headlinerec-child)
ls -lh headline-recorder-theme.zip
