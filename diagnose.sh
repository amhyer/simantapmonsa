#!/usr/bin/env bash
# ============================================================
#  SIMANTAP — Diagnostik Parse Error & Code Health
#  Cara pakai: bash diagnose.sh
# ============================================================

set -e
RED='\033[0;31m'; GRN='\033[0;32m'; YLW='\033[1;33m'; CYN='\033[0;36m'; NC='\033[0m'

echo -e "${CYN}━━━ [1/6] Cek Sintaks Seluruh File PHP ━━━${NC}"
ERR=0
while IFS= read -r file; do
    OUT=$(php -l "$file" 2>&1)
    if [[ "$OUT" != *"No syntax errors"* ]]; then
        echo -e "${RED}✗ $file${NC}"
        echo "$OUT" | sed 's/^/    /'
        ERR=$((ERR+1))
    fi
done < <(find app routes database config -name "*.php" 2>/dev/null)
[[ $ERR -eq 0 ]] && echo -e "${GRN}✓ Semua file bersih${NC}" || echo -e "${RED}Total error: $ERR${NC}"

echo -e "\n${CYN}━━━ [2/6] Cek 3 File yang Dilaporkan Bermasalah ━━━${NC}"
for f in app/Http/Controllers/DapodikImportController.php \
         app/Http/Controllers/UserController.php \
         app/Http/Controllers/DapodikSyncController.php; do
    if [[ -f "$f" ]]; then
        echo -e "${YLW}▸ $f${NC}"
        php -l "$f" 2>&1 | sed 's/^/    /'
    else
        echo -e "${RED}✗ Tidak ditemukan: $f${NC}"
    fi
done

echo -e "\n${CYN}━━━ [3/6] Cek Route Terdaftar (target ≥ 187) ━━━${NC}"
COUNT=$(php artisan route:list --json 2>/dev/null | php -r 'echo count(json_decode(file_get_contents("php://stdin"), true) ?? []);')
echo -e "  Total route: ${YLW}$COUNT${NC}"

echo -e "\n${CYN}━━━ [4/6] Cek Model Load ━━━${NC}"
php artisan tinker --execute="
\$models = ['Guru','Siswa','Kelas','Mapel','Materi','Kuis','Soal'];
foreach (\$models as \$m) {
    \$cls = 'App\\\\Models\\\\' . \$m;
    try { \$cls::first(); echo \"✓ \$m\n\"; }
    catch (\Throwable \$e) { echo \"✗ \$m — \" . \$e->getMessage() . \"\n\"; }
}
" 2>/dev/null || echo -e "${RED}Gagal load model${NC}"

echo -e "\n${CYN}━━━ [5/6] Cek Config Penting ━━━${NC}"
php artisan tinker --execute="
echo 'Timezone : ' . config('app.timezone') . PHP_EOL;
echo 'Locale   : ' . config('app.locale') . PHP_EOL;
echo 'Env      : ' . config('app.env') . PHP_EOL;
echo 'Debug    : ' . (config('app.debug') ? 'true (BAHAYA di produksi)' : 'false (aman)') . PHP_EOL;
" 2>/dev/null

echo -e "\n${CYN}━━━ [6/6] Cek Soft Deletes di 9 Model ━━━${NC}"
php artisan tinker --execute="
\$models = ['Guru','Siswa','Kelas','Mapel','Materi','Kuis','Soal','Jawaban','Nilai'];
foreach (\$models as \$m) {
    \$cls = 'App\\\\Models\\\\' . \$m;
    if (!class_exists(\$cls)) { echo \"− \$m (tidak ada)\n\"; continue; }
    \$traits = class_uses_recursive(\$cls);
    \$ok = in_array('Illuminate\\\\Database\\\\Eloquent\\\\SoftDeletes', \$traits);
    echo (\$ok ? '✓' : '✗') . \" \$m\n\";
}
" 2>/dev/null

echo -e "\n${GRN}━━━ Diagnostik selesai ━━━${NC}"