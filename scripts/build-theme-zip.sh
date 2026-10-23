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
# Web-root files, dotfiles included: robots.txt, .well-known/, and .user.ini (PHP settings the installer merges).
rsync -a deploy/webroot/ "$T/bundled/webroot/"
[ -f "$T/bundled/webroot/.user.ini" ] || { echo "deploy/webroot/.user.ini missing"; exit 1; }
cp infra/litespeed/htaccess-hardening.conf infra/litespeed/multipart-guard.conf deploy/wp-config.sample.php "$T/bundled/"
# Maintenance: the update-time drop-in (bundled/wp-content/maintenance.php), the static page (bundled/webroot/
# maintenance.html, copied above) and the opt-in .htaccess block. The static page must be the current output of
# hr_maintenance_document() in the hr-maintenance mu-plugin (docs/MAINTENANCE.md: "Changing the design").
mkdir -p "$T/bundled/wp-content"
cp deploy/wp-content/maintenance.php "$T/bundled/wp-content/"
cp infra/litespeed/maintenance-mode.conf "$T/bundled/"
php -r 'define("ABSPATH", "/"); define("HR_MAINTENANCE_TEMPLATE_ONLY", true); require "wp-content/mu-plugins/hr-maintenance.php"; echo hr_maintenance_document();' \
    | cmp -s - deploy/webroot/maintenance.html \
    || { echo "deploy/webroot/maintenance.html is out of date: regenerate it (docs/MAINTENANCE.md)"; exit 1; }
find "$T" \( -name '*.secret.json' -o -name '.git' -o -name '*.map' \) -prune -exec rm -rf {} +
# Never ship tests or unbundled sources.
if find "$T" \( -type d \( -name tests -o -path '*/assets/src' \) \) | grep -q .; then echo "tests/ or assets/src leaked into the bundle"; exit 1; fi
rm -f headline-recorder-theme.zip
(cd "$STAGE" && zip -qr "$OLDPWD/headline-recorder-theme.zip" headlinerec-child)
ls -lh headline-recorder-theme.zip
