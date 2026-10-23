#!/usr/bin/env bash
# Builds the deliverables into dist/:
#   headline-recorder-deploy.zip    what changed: theme, hr-platform plugin (with vendor/), mu-plugins, web-root files,
#                                   infra configs and docs. Upload over the live site (see docs/DEPLOY.md).
#   headline-recorder-full-site.zip (optional, --full <webroot>) the whole site with the changes applied, minus
#                                   backups, logs, caches and the old wp-config.php secrets.
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$PWD
OUT=$ROOT/dist
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$OUT"

npm run build --silent
(cd wp-content/plugins/hr-platform && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --optimize-autoloader --quiet)

# --- deploy bundle -----------------------------------------------------------------------------------------------
mkdir -p "$STAGE/deploy/wp-content/themes" "$STAGE/deploy/wp-content/plugins" "$STAGE/deploy/wp-content/mu-plugins"
rsync -a --exclude 'assets/src' wp-content/themes/headlinerec-child "$STAGE/deploy/wp-content/themes/"
rsync -a -L --exclude 'assets/src' --exclude 'tests' wp-content/plugins/hr-platform "$STAGE/deploy/wp-content/plugins/"
rsync -a wp-content/mu-plugins/ "$STAGE/deploy/wp-content/mu-plugins/"
rsync -a deploy/webroot/ "$STAGE/deploy/"
cp deploy/wp-content/maintenance.php "$STAGE/deploy/wp-content/maintenance.php"
mkdir -p "$STAGE/deploy/_docs"
cp -r docs infra SECURITY.md "$STAGE/deploy/_docs/"
mkdir -p "$STAGE/deploy/_docs/signer"
rsync -a --exclude node_modules --exclude '*.secret.json' security/pq/ "$STAGE/deploy/_docs/signer/"
rm -f "$OUT/headline-recorder-deploy.zip"
(cd "$STAGE/deploy" && zip -qr "$OUT/headline-recorder-deploy.zip" . -x '*.secret.json' '*/node_modules/*')
echo "deploy bundle: $(du -h "$OUT/headline-recorder-deploy.zip" | cut -f1)"

# --- optional full site ------------------------------------------------------------------------------------------
if [ "${1:-}" = "--full" ]; then
  SRC=${2:?usage: scripts/package.sh --full /path/to/original/webroot}
  mkdir -p "$STAGE/full"
  rsync -a \
    --exclude 'wp-content/duplicator-backups/' --exclude 'wp-content/ai1wm-backups/' --exclude 'wp-content/aiowps_backups/' \
    --exclude 'wp-content/upgrade-temp-backup/' --exclude 'wp-content/litespeed/' --exclude 'wp-content/cache/' \
    --exclude 'error_log' --exclude '*/error_log' --exclude 'wp-content/debug.log' --exclude 'wp-config.php' \
    --exclude 'wp-content/mu-plugins/zz-*' --exclude 'wp-content/themes/headlinerec-child.zip' \
    --exclude 'wp-content/themes/headlinerec-child' --exclude 'wp-content/plugins/hr-platform' \
    "$SRC/" "$STAGE/full/"
  rsync -a "$STAGE/deploy/" "$STAGE/full/" --exclude '_docs'
  cp "$ROOT/deploy/wp-config.sample.php" "$STAGE/full/wp-config.sample.php"
  rm -f "$OUT/headline-recorder-full-site.zip"
  (cd "$STAGE/full" && zip -qr "$OUT/headline-recorder-full-site.zip" .)
  echo "full site: $(du -h "$OUT/headline-recorder-full-site.zip" | cut -f1)"
fi
