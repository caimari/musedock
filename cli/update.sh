#!/bin/bash
# ============================================================
# MuseDock CMS — Updater
# Usage: sudo bash cli/update.sh [--auto]
#
# What it does:
#   1. Pulls latest code from GitHub
#   2. Runs composer install (if needed)
#   3. Runs pending database migrations
#   4. Clears view/HTML cache
#   5. Syncs public template.css
#
# Safe to run multiple times — idempotent.
# Never touches .env, storage/uploads, or database data.
# ============================================================

set -e

if [ -t 1 ]; then
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    RED='\033[0;31m'
    CYAN='\033[0;36m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    GREEN='' YELLOW='' RED='' CYAN='' BOLD='' NC=''
fi

ok()   { echo -e "  ${GREEN}✓${NC} $1"; }
warn() { echo -e "  ${YELLOW}!${NC} $1"; }
fail() { echo -e "  ${RED}✗ $1${NC}"; exit 1; }

CMS_DIR="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -f "${CMS_DIR}/.env" ]; then
    fail "No .env found in ${CMS_DIR}."
fi

if [ ! -d "${CMS_DIR}/.git" ]; then
    fail "No .git directory. Updates require a git-based installation."
fi

# Get current version
CURRENT=$(grep -oP '"version"\s*:\s*"\K[^"]+' "${CMS_DIR}/composer.json" 2>/dev/null || echo "unknown")
echo -e "${BOLD}MuseDock CMS Updater${NC}"
echo -e "Current version: ${CYAN}${CURRENT}${NC}"
echo ""

# 1. Git pull
echo -e "${BOLD}[1/5] Pulling latest code...${NC}"
cd "${CMS_DIR}"
git fetch origin main 2>&1 || fail "git fetch failed"

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

if [ "$LOCAL" = "$REMOTE" ]; then
    ok "Already up to date"
else
    git reset --hard origin/main 2>&1 || fail "git reset failed"
    NEW_VERSION=$(grep -oP '"version"\s*:\s*"\K[^"]+' "${CMS_DIR}/composer.json" 2>/dev/null || echo "unknown")
    ok "Updated to ${NEW_VERSION}"
fi

# 2. Composer install (if composer.lock changed)
echo -e "${BOLD}[2/5] Checking dependencies...${NC}"
if command -v composer &> /dev/null; then
    if [ -f "${CMS_DIR}/composer.lock" ]; then
        composer install --no-dev --optimize-autoloader --no-interaction --working-dir="${CMS_DIR}" 2>&1 | tail -3
        ok "Dependencies updated"
    else
        ok "No composer.lock, skipping"
    fi
else
    warn "Composer not found, skipping dependency check"
fi

# 3. Run migrations
echo -e "${BOLD}[3/5] Running migrations...${NC}"
PHP_BIN=$(which php8.3 2>/dev/null || which php 2>/dev/null)
if [ -f "${CMS_DIR}/cli/migrate.php" ]; then
    $PHP_BIN "${CMS_DIR}/cli/migrate.php" 2>&1 | tail -5
    ok "Migrations complete"
else
    warn "migrate.php not found, skipping"
fi

# 4. Clear caches
echo -e "${BOLD}[4/5] Clearing caches...${NC}"
rm -f "${CMS_DIR}/storage/views/"*.php 2>/dev/null
rm -rf "${CMS_DIR}/storage/cache/html/" 2>/dev/null
mkdir -p "${CMS_DIR}/storage/cache/html"
ok "View and HTML cache cleared"

# 5. Sync template.css to public
echo -e "${BOLD}[5/5] Syncing assets...${NC}"
if [ -f "${CMS_DIR}/themes/default/css/template.css" ] && [ -f "${CMS_DIR}/public/assets/themes/default/css/template.css" ]; then
    cp "${CMS_DIR}/themes/default/css/template.css" "${CMS_DIR}/public/assets/themes/default/css/template.css"
    ok "template.css synced"
else
    ok "No template.css sync needed"
fi

# Final
FINAL_VERSION=$(grep -oP '"version"\s*:\s*"\K[^"]+' "${CMS_DIR}/composer.json" 2>/dev/null || echo "unknown")
echo ""
echo -e "${GREEN}${BOLD}Update complete!${NC} Version: ${CYAN}${FINAL_VERSION}${NC}"
