#!/usr/bin/env bash
# Deploy amanahfinance ke server staging (DomaiNesia, cPanel).
#
# Kenapa scriptnya begini: server TIDAK punya Node.js (dikonfirmasi 1 September
# 2026 -- `command -v npm` kosong), jadi static export Next.js harus di-build di
# laptop lalu dikirim sebagai artefak. Hook deploy/hooks/post-merge di server
# AKTIF (core.hooksPath=deploy/hooks) dan mengurus `migrate --force` sendiri
# setiap `git pull`, tapi langkah build frontend-nya selalu di-skip diam-diam
# karena npm tidak ada -- itulah lubang yang ditambal script ini.
#
# Pakai:
#   scripts/deploy.sh              # deploy penuh (backend via git pull + frontend)
#   scripts/deploy.sh --frontend   # frontend saja, lewati git pull di server
#   scripts/deploy.sh --dry-run    # tampilkan yang akan dikerjakan, jangan eksekusi
set -euo pipefail

# ---------------------------------------------------------------------------
# Konfigurasi. Override lewat environment variable kalau perlu, mis:
#   DEPLOY_SSH_PORT=1234 scripts/deploy.sh
# ---------------------------------------------------------------------------
# Detail koneksi TIDAK ditaruh di sini: repo ini publik, jadi host/port/user
# server tidak boleh ikut ter-commit (pelajaran dari scripts/deploy-frontend.sh
# yang sampai sekarang masih memuat host Hostinger lama secara terbuka).
# Semuanya dibaca dari scripts/deploy.env yang di-gitignore.
# Salin scripts/deploy.env.example -> scripts/deploy.env lalu isi.
ENV_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/deploy.env"
if [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  . "$ENV_FILE"
fi

SSH_USER="${DEPLOY_SSH_USER:-}"
SSH_HOST="${DEPLOY_SSH_HOST:-}"
SSH_PORT="${DEPLOY_SSH_PORT:-22}"
SSH_KEY="${DEPLOY_SSH_KEY:-$HOME/.ssh/amanafinance_deploy}"
REMOTE_APP="${DEPLOY_REMOTE_APP:-}"
SITE_URL="${DEPLOY_SITE_URL:-}"
REMOTE_BRANCH="${DEPLOY_BRANCH:-main}"

FRONTEND_ONLY=0
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --frontend) FRONTEND_ONLY=1 ;;
    --dry-run)  DRY_RUN=1 ;;
    *) echo "Argumen tidak dikenal: $arg" >&2; exit 2 ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$*" >&2; }
die()  { printf '\033[1;31mXX %s\033[0m\n' "$*" >&2; exit 1; }

run() {
  if [ "$DRY_RUN" -eq 1 ]; then printf '   [dry-run] %s\n' "$*"; else "$@"; fi
}

# ---------------------------------------------------------------------------
# Preflight -- gagal cepat, sebelum menyentuh server sama sekali
# ---------------------------------------------------------------------------
say "Preflight"

if [ -z "$SSH_HOST" ] || [ -z "$SSH_USER" ] || [ -z "$REMOTE_APP" ] || [ -z "$SITE_URL" ]; then
  die "Konfigurasi belum lengkap. Jalankan: cp scripts/deploy.env.example scripts/deploy.env, lalu isi nilainya (file itu di-gitignore, aman untuk data server)."
fi
[ -f "$SSH_KEY" ]  || die "SSH key tidak ditemukan di $SSH_KEY"
command -v npm >/dev/null || die "npm tidak ada di PATH laptop ini"
command -v ssh >/dev/null || die "ssh tidak ada di PATH"
command -v scp >/dev/null || die "scp tidak ada di PATH"
[ -d frontend/node_modules ] || die "frontend/node_modules belum ada. Jalankan dulu: npm --prefix frontend install"

BRANCH_LOCAL="$(git branch --show-current)"
echo "   branch lokal : $BRANCH_LOCAL"
echo "   target       : $SSH_USER@$SSH_HOST:$SSH_PORT ($REMOTE_APP)"
echo "   situs        : $SITE_URL"

if [ -n "$(git status --porcelain)" ]; then
  warn "Working tree tidak bersih. Perubahan yang belum di-commit TIDAK akan ikut ter-deploy"
  warn "ke backend (server menarik lewat git pull), tapi TETAP ikut ke frontend build."
  warn "Ini bikin server jadi campuran versi. Commit dulu kalau ragu."
  printf '   Lanjut? [y/N] '
  read -r reply
  [ "$reply" = "y" ] || die "Dibatalkan."
fi

if [ "$FRONTEND_ONLY" -eq 0 ] && [ "$BRANCH_LOCAL" != "$REMOTE_BRANCH" ]; then
  warn "Anda di branch '$BRANCH_LOCAL', sedangkan server checkout '$REMOTE_BRANCH'."
  warn "git pull di server hanya akan menarik '$REMOTE_BRANCH' -- perubahan backend"
  warn "di branch Anda tidak akan ikut sampai di-merge."
fi

SSH_OPTS=(-i "$SSH_KEY" -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)
remote() { ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "$@"; }

say "Cek koneksi ke server"
if [ "$DRY_RUN" -eq 0 ]; then
  remote "test -d '$REMOTE_APP/.git'" \
    || die "Tidak bisa SSH, atau $REMOTE_APP bukan git repo."
  echo "   OK"
fi

# ---------------------------------------------------------------------------
# 1. Backend -- git pull di server. Hook post-merge otomatis jalankan migrate.
# ---------------------------------------------------------------------------
if [ "$FRONTEND_ONLY" -eq 0 ]; then
  say "Backend: git pull di server (hook akan jalankan migrate bila ada migrasi baru)"
  run remote "cd '$REMOTE_APP' && git pull --ff-only origin '$REMOTE_BRANCH'"

  say "Backend: composer install (kalau dependency berubah)"
  run remote "cd '$REMOTE_APP' && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts && php artisan package:discover --ansi"

  say "Backend: bangun cache config, route, dan view"
  run remote "cd '$REMOTE_APP' && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache"
fi

# ---------------------------------------------------------------------------
# 2. Frontend -- build di laptop, kirim artefaknya
# ---------------------------------------------------------------------------
say "Frontend: build static export di laptop"
run npm --prefix frontend run build
[ "$DRY_RUN" -eq 1 ] || [ -d frontend/out ] || die "frontend/out tidak terbentuk -- build gagal?"

TARBALL="frontend/frontend-out.tar.gz"
say "Frontend: pack hasil build"
run bash -c "cd frontend/out && tar -czf ../frontend-out.tar.gz ."

say "Frontend: upload ke server"
run scp -i "$SSH_KEY" -P "$SSH_PORT" "$TARBALL" "$SSH_USER@$SSH_HOST:~/frontend-out.tar.gz"

# rsync --delete membuang file dari build lama yang sudah tidak diproduksi.
# Exclude melindungi file milik Laravel yang tidak dihasilkan Next:
#   index.php/.htaccess -> entry point + aturan routing (versi server, sengaja
#                          dipertahankan karena isinya belum ada di repo)
#   robots.txt          -> tidak diproduksi build Next
#   storage             -> symlink hasil artisan storage:link
# favicon.ico SENGAJA tidak di-exclude: favicon dari build Next yang harus menang.
say "Frontend: extract & sync ke public/"
run remote "set -e; \
  rm -rf /tmp/amana-out && mkdir -p /tmp/amana-out && \
  tar -xzf ~/frontend-out.tar.gz -C /tmp/amana-out && \
  rsync -a --delete \
    --exclude 'index.php' --exclude '.htaccess' \
    --exclude 'robots.txt' --exclude 'storage' \
    /tmp/amana-out/ '$REMOTE_APP/public/' && \
  rm -rf /tmp/amana-out ~/frontend-out.tar.gz"

run rm -f "$TARBALL"

# ---------------------------------------------------------------------------
# 3. Verifikasi -- deploy dianggap gagal kalau situs tidak sehat
# ---------------------------------------------------------------------------
say "Verifikasi"
if [ "$DRY_RUN" -eq 1 ]; then
  echo "   [dry-run] lewati"
  exit 0
fi

fail=0
for path in "/" "/login/" "/admin/login/" "/api/v1/openapi.json"; do
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$SITE_URL$path" || echo 000)"
  printf '   %-24s -> %s\n' "$path" "$code"
  [ "$code" = "200" ] || fail=1
done

if [ "$fail" -eq 1 ]; then
  die "Ada endpoint yang tidak balas 200. Situs mungkin rusak -- cek storage/logs/laravel.log di server."
fi

say "Deploy selesai. $SITE_URL"
