#!/usr/bin/env bash
# ============================================================
#  SIMANTAP — Verifikasi Siap Produksi
#  Jalankan SEBELUM deploy. Jika ada ✗, JANGAN deploy.
# ============================================================
set -e
GRN='\033[0;32m'; RED='\033[0;31m'; YLW='\033[1;33m'; CYN='\033[0;36m'; NC='\033[0m'
PASS=0; FAIL=0

check() {
    local label="$1"; shift
    if "$@" > /dev/null 2>&1; then
        echo -e "${GRN}✓${NC} $label"; PASS=$((PASS+1))
    else
        echo -e "${RED}✗${NC} $label"; FAIL=$((FAIL+1))
    fi
}

echo -e "${CYN}━━━ Verifikasi Produksi SIMANTAP ━━━${NC}\n"

# 1. Sintaks
echo "[1] Sintaks PHP"
BAD=$(find app routes database config -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | wc -l)
[[ $BAD -eq 0 ]] && { echo -e "${GRN}✓${NC} Semua file PHP bersih"; PASS=$((PASS+1)); } \
                 || { echo -e "${RED}✗${NC} $BAD file bermasalah"; FAIL=$((FAIL+1)); }

# 2. Route
echo "[2] Route terdaftar"
ROUTES=$(php artisan route:list --json 2>/dev/null | php -r 'echo count(json_decode(file_get_contents("php://stdin"), true) ?? []);')
[[ $ROUTES -ge 187 ]] && { echo -e "${GRN}✓${NC} $ROUTES route"; PASS=$((PASS+1)); } \
                      || { echo -e "${YLW}⚠${NC} Hanya $ROUTES route (target 187)"; FAIL=$((FAIL+1)); }

# 3. Config
echo "[3] Config produksi"
TZ=$(php artisan tinker --execute="echo config('app.timezone');" 2>/dev/null | tail -1)
[[ "$TZ" == "Asia/Jakarta" ]] && { echo -e "${GRN}✓${NC} Timezone: $TZ"; PASS=$((PASS+1)); } \
                              || { echo -e "${RED}✗${NC} Timezone salah: $TZ"; FAIL=$((FAIL+1)); }

# 4. Test suite
echo "[4] PHPUnit Tests"
if php artisan test > /tmp/simantap-test.log 2>&1; then
    LINES=$(grep -E "Tests:" /tmp/simantap-test.log | tail -1)
    echo -e "${GRN}✓${NC} $LINES"; PASS=$((PASS+1))
else
    echo -e "${RED}✗${NC} Test gagal — lihat /tmp/simantap-test.log"; FAIL=$((FAIL+1))
fi

# 5. Cache config
echo "[5] Cache config & route"
check "Config cache" php artisan config:cache
check "Route cache"  php artisan route:cache
check "View cache"   php artisan view:cache

# Ringkasan
echo ""
echo -e "${CYN}━━━ Ringkasan ━━━${NC}"
echo -e "  Lulus: ${GRN}$PASS${NC}"
echo -e "  Gagal: ${RED}$FAIL${NC}"

if [[ $FAIL -eq 0 ]]; then
    echo -e "\n${GRN}🎉 SIAP DEPLOY KE PRODUKSI${NC}"
    exit 0
else
    echo -e "\n${RED}⛔ JANGAN DEPLOY — perbaiki dulu${NC}"
    exit 1
fi