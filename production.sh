#!/usr/bin/env bash
#
# production.sh — pull terbaru, cek kesehatan, build, deploy, perbaiki.
#
# Cara pakai (dari root project di server):
#   ./production.sh            # full deploy: pull + build + migrate + cache + restart
#   ./production.sh --check    # CEK SAJA, tidak mengubah apa pun (dry diagnosa)
#   ./production.sh --no-pull  # skip git pull (deploy kode yang sudah ada)
#   ./production.sh --seed     # ikut jalankan db:seed --force
#
# Script ini idempotent & aman diulang. Saat deploy, app masuk maintenance mode
# lalu otomatis keluar di akhir (termasuk kalau ada error — via trap).

set -Eeuo pipefail

# Selalu kerja dari folder script (root project), berapa pun cwd pemanggil.
cd "$(dirname "$0")"

# ── Opsi ────────────────────────────────────────────────────────────────────
CHECK_ONLY=0
DO_PULL=1
DO_SEED=0
for arg in "$@"; do
  case "$arg" in
    --check)   CHECK_ONLY=1 ;;
    --no-pull) DO_PULL=0 ;;
    --seed)    DO_SEED=1 ;;
    *) echo "Opsi tidak dikenal: $arg" >&2; exit 1 ;;
  esac
done

# ── Util cetak ────────────────────────────────────────────────────────────────
c_green=$'\033[0;32m'; c_red=$'\033[0;31m'; c_yellow=$'\033[1;33m'; c_blue=$'\033[0;34m'; c_reset=$'\033[0m'
ok()   { echo "${c_green}✓${c_reset} $*"; }
warn() { echo "${c_yellow}!${c_reset} $*"; }
err()  { echo "${c_red}✗${c_reset} $*" >&2; }
step() { echo; echo "${c_blue}▶ $*${c_reset}"; }

PHP=${PHP_BIN:-php}
COMPOSER=${COMPOSER_BIN:-composer}

MAINT_DOWN=0
down_maintenance() {
  if [ "$MAINT_DOWN" = "1" ]; then
    "$PHP" artisan up >/dev/null 2>&1 || true
    MAINT_DOWN=0
  fi
}
trap 'rc=$?; if [ $rc -ne 0 ]; then err "Gagal (exit $rc). Keluar maintenance mode..."; fi; down_maintenance' EXIT

# ── 1. Pengecekan lingkungan ──────────────────────────────────────────────────
step "Cek lingkungan & tooling"
fail=0
command -v "$PHP" >/dev/null      && ok "php: $($PHP -r 'echo PHP_VERSION;')" || { err "php tidak ditemukan"; fail=1; }
command -v "$COMPOSER" >/dev/null && ok "composer ada"                        || { err "composer tidak ditemukan"; fail=1; }
command -v node >/dev/null        && ok "node: $(node -v)"                     || { err "node tidak ditemukan"; fail=1; }
command -v npm >/dev/null         && ok "npm: $(npm -v)"                       || { err "npm tidak ditemukan"; fail=1; }

[ -f .env ]      && ok ".env ada"           || { err ".env TIDAK ADA — salin dari .env.example lalu isi"; fail=1; }
[ -d .git ]      && ok "git repo terdeteksi" || warn "bukan git repo — --no-pull dipaksa"; [ -d .git ] || DO_PULL=0

if [ -f .env ] && grep -q '^APP_KEY=base64:' .env; then ok "APP_KEY terisi"; else err "APP_KEY kosong — jalankan: php artisan key:generate"; fail=1; fi
if [ -f .env ] && grep -q '^APP_ENV=production' .env; then ok "APP_ENV=production"; else warn "APP_ENV bukan production"; fi
if [ -f .env ] && grep -q '^APP_DEBUG=false' .env; then ok "APP_DEBUG=false"; else err "APP_DEBUG bukan false — WAJIB false di production (kebocoran info)"; fail=1; fi
if [ -f .env ] && grep -q '^MEDIA_DISK=public' .env; then err "MEDIA_DISK=public — file bisa diunduh tanpa auth. Set MEDIA_DISK=local"; fail=1; fi

# ── Cek Redis (M2) ────────────────────────────────────────────────────────────
# Kalau ada driver yang di-set ke redis, pastikan Redis BENAR-BENAR reachable via
# koneksi terkonfigurasi (host/port/password/phpredis dari .env). Kalau tidak,
# session/cache/queue akan gagal senyap saat runtime — lebih baik gagal di sini.
if [ -f .env ]; then
  redis_used=0
  for k in SESSION_DRIVER QUEUE_CONNECTION CACHE_STORE; do
    grep -qE "^${k}=redis([[:space:]]|\$)" .env && redis_used=1
  done

  if [ "$redis_used" = "1" ]; then
    ping="$("$PHP" artisan tinker --execute="try { Illuminate\Support\Facades\Redis::connection()->ping(); echo 'REDIS_OK'; } catch (\Throwable \$e) { echo 'REDIS_FAIL'; }" 2>/dev/null || true)"
    if printf '%s' "$ping" | grep -q REDIS_OK; then
      ok "Redis reachable (dipakai untuk session/cache/queue)"
    else
      err "Driver 'redis' dipakai di .env tapi Redis TIDAK reachable — cek 'redis-cli ping', ekstensi phpredis, & REDIS_HOST/PORT/PASSWORD"
      fail=1
    fi

    # Gotcha umum: QUEUE_CONNECTION=redis tapi worker supervisor masih
    # 'queue:work database' → job masuk Redis, worker kuras DB kosong.
    if grep -qE "^QUEUE_CONNECTION=redis([[:space:]]|\$)" .env; then
      wconf="$(grep -lR 'queue:work database' /etc/supervisor/conf.d/ 2>/dev/null | head -1 || true)"
      [ -n "$wconf" ] && warn "QUEUE_CONNECTION=redis tapi supervisor masih 'queue:work database' di ${wconf} — ganti ke 'queue:work redis' atau job Redis tidak akan diproses"
    fi
  fi
fi

if [ "$fail" = "1" ]; then err "Pengecekan lingkungan gagal. Perbaiki dulu hal di atas."; exit 1; fi

# ── Mode --check: diagnosa saja, stop di sini ─────────────────────────────────
if [ "$CHECK_ONLY" = "1" ]; then
  step "Diagnosa migrasi & storage (read-only)"
  "$PHP" artisan migrate:status 2>/dev/null | tail -n 20 || warn "Tidak bisa baca status migrasi (cek koneksi DB)"
  [ -L public/storage ] && ok "symlink public/storage ada" || warn "public/storage belum di-link"
  ok "Mode --check selesai. Tidak ada perubahan dilakukan."
  trap - EXIT
  exit 0
fi

# ── 2. Maintenance mode ───────────────────────────────────────────────────────
step "Masuk maintenance mode"
"$PHP" artisan down --render="errors::503" --retry=15 >/dev/null 2>&1 || "$PHP" artisan down --retry=15 || true
MAINT_DOWN=1
ok "Aplikasi dalam maintenance mode"

# ── 3. Pull kode terbaru ──────────────────────────────────────────────────────
if [ "$DO_PULL" = "1" ]; then
  step "Pull kode terbaru"
  git fetch --all --prune
  branch="$(git rev-parse --abbrev-ref HEAD)"
  git reset --hard "origin/${branch}"
  ok "Sinkron ke origin/${branch} @ $(git rev-parse --short HEAD)"
else
  warn "Pull dilewati (--no-pull / non-git)"
fi

# ── 4. Dependency PHP ─────────────────────────────────────────────────────────
step "Install dependency PHP (composer)"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
ok "Composer beres"

# ── 5. Build frontend (Inertia + Vite) ────────────────────────────────────────
step "Build frontend (npm)"
if [ -f package-lock.json ]; then npm ci; else npm install; fi
npm run build
ok "Vite build beres"

# ── 6. Deployment hook (migrate + cache + filament + queue restart) ───────────
step "Deployment hook"
SEED_FLAG=""; [ "$DO_SEED" = "1" ] && SEED_FLAG="--seed"
"$PHP" artisan deploy:hook $SEED_FLAG

# ── 7. Perbaiki permission ────────────────────────────────────────────────────
step "Perbaiki permission storage & cache"
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
# Samakan owner ke user web server kalau dijalankan sebagai root.
if [ "$(id -u)" = "0" ] && id -u www-data >/dev/null 2>&1; then
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  ok "Owner storage/cache → www-data"
else
  ok "Permission storage/cache disesuaikan"
fi

# ── 8. Restart queue worker (kalau pakai supervisor) ──────────────────────────
step "Restart queue worker"
if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl restart "lms-worker:*" 2>/dev/null && ok "Supervisor lms-worker di-restart" \
    || warn "supervisorctl gagal restart lms-worker:* — cek nama program di config"
else
  warn "supervisorctl tidak ada — queue:restart sudah dipanggil deploy:hook, worker akan reload sendiri"
fi

# ── 9. Keluar maintenance ─────────────────────────────────────────────────────
step "Keluar maintenance mode"
down_maintenance
ok "Aplikasi kembali ONLINE"

echo
ok "Deploy selesai. Versi: $(git rev-parse --short HEAD 2>/dev/null || echo 'n/a')"
trap - EXIT
