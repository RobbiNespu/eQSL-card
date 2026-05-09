#!/usr/bin/env bash
# Build a shared-hosting-deployable zip.
set -Eeuo pipefail

VERSION="${1:-0.1.0}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="${ROOT}/dist/stage-${VERSION}"
ZIP="${ROOT}/dist/eqsl-card-${VERSION}.zip"

rm -rf "${STAGE}" "${ZIP}"
mkdir -p "${STAGE}"

# Copy what ships
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.docker/' \
  --exclude='.superpowers/' \
  --exclude='docker-compose.yml' \
  --exclude='dist/' \
  --exclude='node_modules/' \
  --exclude='tests/' \
  --exclude='.phpunit.cache/' \
  --exclude='.phpunit.result.cache' \
  --exclude='coverage/' \
  --exclude='config/app_local.php' \
  --exclude='config/installed.lock' \
  --exclude='webroot/files/uploads/*' \
  --include='webroot/files/uploads/.gitkeep' \
  --exclude='webroot/files/cards/*' \
  --include='webroot/files/cards/.gitkeep' \
  --exclude='tmp/cache/*' \
  --exclude='tmp/sessions/*' \
  --exclude='tmp/tests/*' \
  --exclude='tmp/installed.lock' \
  --exclude='tmp/debug_kit.sqlite' \
  --exclude='logs/*.log' \
  --include='tmp/cache/.gitkeep' \
  --include='tmp/cache/models/.gitkeep' \
  --include='tmp/cache/persistent/.gitkeep' \
  --include='tmp/cache/views/.gitkeep' \
  --include='tmp/sessions/.gitkeep' \
  --include='tmp/tests/.gitkeep' \
  --include='logs/.gitkeep' \
  "${ROOT}/" "${STAGE}/"

# Make sure vendor/ exists with prod deps only
if [ ! -d "${STAGE}/vendor" ]; then
  echo "vendor/ not in stage; running composer install --no-dev"
  (cd "${ROOT}" && docker compose run --rm php composer install --no-dev --optimize-autoloader)
  rsync -a "${ROOT}/vendor/" "${STAGE}/vendor/"
fi

# Drop a deny-rule into vendor for hosts that may serve from there
cat > "${STAGE}/vendor/.htaccess" <<'EOF'
Require all denied
EOF

# Zip
(cd "${ROOT}/dist" && zip -qr "$(basename "${ZIP}")" "stage-${VERSION}")
rm -rf "${STAGE}"
echo "Built: ${ZIP}"
