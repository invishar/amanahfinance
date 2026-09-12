#!/usr/bin/env bash
# Deploy amanahfinance ke server (hPanel/cPanel, shared hosting).
#
# SEJARAH SINGKAT, supaya tidak diputar balik lagi:
# Versi lama script ini membangun static export Next.js di laptop lalu
# mengirim tarball, karena saat itu (1 September 2026) server tidak punya
# Node.js. Dua hal sudah berubah:
#   1. Frontend pindah ke Inertia + Vite (routes/web.php). Folder `frontend/`
#      tinggal sisa arsip -- package.json-nya sudah pindah ke frontend_backup/,
#      jadi script lama otomatis gagal di `npm --prefix frontend run build`.
#   2. Server SUDAH punya Node (dikonfirmasi 12 September 2026: node v26,
#      npm 11), dan .gitignore repo ini menegaskan `/public/*` serta
#      `/bootstrap/ssr` memang DIBANGUN DI SERVER, bukan dikomit atau dikirim.
# Jadi sekarang build-nya di server, dan tidak ada lagi artefak yang di-scp.
#
# Pakai:
#   scripts/deploy.sh              # deploy penuh
#   scripts/deploy.sh --assets     # lewati git pull, composer & migrasi
#   scripts/deploy.sh --dry-run    # tampilkan yang akan dikerjakan, jangan eksekusi
set -euo pipefail

# ---------------------------------------------------------------------------
# Konfigurasi. Detail koneksi TIDAK ditaruh di sini: repo ini publik.
# Semuanya dibaca dari scripts/deploy.env yang di-gitignore.
#   cp scripts/deploy.env.example scripts/deploy.env
# ---------------------------------------------------------------------------
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

ASSETS_ONLY=0
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --assets)  ASSETS_ONLY=1 ;;
    --dry-run) DRY_RUN=1 ;;
    *) echo "Argumen tidak dikenal: $arg" >&2; exit 2 ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$*" >&2; }
die()  { printf '\033[1;31mXX %s\033[0m\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Preflight -- gagal cepat, sebelum menyentuh server sama sekali
# ---------------------------------------------------------------------------
say "Preflight"

if [ -z "$SSH_HOST" ] || [ -z "$SSH_USER" ] || [ -z "$REMOTE_APP" ] || [ -z "$SITE_URL" ]; then
  die "Konfigurasi belum lengkap. Jalankan: cp scripts/deploy.env.example scripts/deploy.env, lalu isi nilainya (file itu di-gitignore, aman untuk data server)."
fi
[ -f "$SSH_KEY" ] || die "SSH key tidak ditemukan di $SSH_KEY"
command -v ssh  >/dev/null || die "ssh tidak ada di PATH"
command -v curl >/dev/null || die "curl tidak ada di PATH"

BRANCH_LOCAL="$(git branch --show-current)"
echo "   branch lokal : $BRANCH_LOCAL"
echo "   target       : $SSH_USER@$SSH_HOST:$SSH_PORT ($REMOTE_APP)"
echo "   situs        : $SITE_URL"

# Aset dibangun DI SERVER dari commit yang sudah di-push, jadi working tree
# kotor tidak bisa lagi menyelundup ke rilis -- tapi tetap diperingatkan
# supaya tidak ada yang mengira perubahannya ikut terkirim.
if [ -n "$(git status --porcelain)" ]; then
  warn "Working tree tidak bersih. Perubahan yang belum di-commit TIDAK ikut ter-deploy;"
  warn "server membangun dari commit yang sudah di-push ke '$REMOTE_BRANCH'."
fi

if [ "$ASSETS_ONLY" -eq 0 ]; then
  UNPUSHED="$(git log --oneline "origin/$REMOTE_BRANCH..$BRANCH_LOCAL" 2>/dev/null | wc -l | tr -d ' ')"
  if [ "$UNPUSHED" != "0" ]; then
    warn "Ada $UNPUSHED commit lokal yang belum di-push ke origin/$REMOTE_BRANCH -- itu tidak akan ikut."
  fi
  if [ "$BRANCH_LOCAL" != "$REMOTE_BRANCH" ]; then
    warn "Anda di branch '$BRANCH_LOCAL', server checkout '$REMOTE_BRANCH'."
  fi
fi

SSH_OPTS=(-i "$SSH_KEY" -p "$SSH_PORT" -o StrictHostKeyChecking=accept-new)
remote() { ssh "${SSH_OPTS[@]}" "$SSH_USER@$SSH_HOST" "$@"; }
run() {
  if [ "$DRY_RUN" -eq 1 ]; then printf '   [dry-run] %s\n' "$*"; else "$@"; fi
}

ROLLBACK=""
say "Cek koneksi ke server"
if [ "$DRY_RUN" -eq 0 ]; then
  remote "test -d '$REMOTE_APP/.git'" || die "Tidak bisa SSH, atau $REMOTE_APP bukan git repo."
  ROLLBACK="$(remote "cd '$REMOTE_APP' && git rev-parse --short HEAD")"
  echo "   OK. Commit server sekarang: $ROLLBACK"
fi

# npm dari Node Selector kadang di luar PATH SSH default; sumberkan nodevenv
# kalau ada. Bentuk masalahnya sama dengan composer2/php.ini di CLAUDE.md.
NODE_PREP='if ! command -v npm >/dev/null 2>&1; then A=$(find "$HOME/nodevenv" -maxdepth 3 -name activate 2>/dev/null | head -n1); if [ -n "$A" ]; then . "$A"; fi; fi'

# ---------------------------------------------------------------------------
# 1. Kode -- git pull DENGAN HOOK DIMATIKAN.
#
# deploy/hooks/post-merge menjalankan migrate + build sendiri, tapi urutannya
# salah untuk rilis: migrate jalan sebelum composer install, padahal migrasi
# bisa butuh paket baru. Di sini hook dilewati (core.hooksPath diarahkan ke
# folder yang tidak ada) dan tiap langkah dijalankan script ini dalam urutan
# yang benar. Hook tetap terpasang untuk orang yang `git pull` manual.
# ---------------------------------------------------------------------------
if [ "$ASSETS_ONLY" -eq 0 ]; then
  say "Kode: git pull di server (hook dilewati, urutannya diurus script ini)"
  run remote "cd '$REMOTE_APP' && git -c core.hooksPath=/nonexistent pull --ff-only origin '$REMOTE_BRANCH'"

  say "Composer: install dependency produksi"
  # --no-scripts wajib: composer2 di hPanel memakai php.ini terpisah, jadi
  # package:discover dijalankan terpisah setelahnya (lihat CLAUDE.md).
  run remote "cd '$REMOTE_APP' && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts && php artisan package:discover --ansi"

  say "Database: jalankan migrasi"
  run remote "cd '$REMOTE_APP' && php artisan migrate --force"
fi

# ---------------------------------------------------------------------------
# 2. Aset Inertia -- dibangun DI SERVER (lihat .gitignore: /public/* dan
#    /bootstrap/ssr memang tidak dikomit).
#
#    `npm run build` = `vite build && vite build --ssr`. Yang pertama mengisi
#    public/build beserta manifest.json yang dicari Laravel; yang kedua
#    mengisi bootstrap/ssr/ssr.js. Tanpa manifest, SETIAP halaman mati dengan
#    ViteManifestNotFoundException -- karena itu keberadaannya diperiksa.
# ---------------------------------------------------------------------------
say "Aset: npm ci + vite build (klien & SSR) di server"
run remote "cd '$REMOTE_APP' && $NODE_PREP; command -v npm >/dev/null || { echo 'npm tidak ditemukan di server' >&2; exit 1; }"
run remote "cd '$REMOTE_APP' && $NODE_PREP; npm ci --include=dev && npm run build"
run remote "cd '$REMOTE_APP' && test -f public/build/manifest.json || { echo 'manifest.json tidak terbentuk -- build gagal' >&2; exit 1; }"

# ---------------------------------------------------------------------------
# 3. Bersihkan sisa static export Next.js.
#
#    Rilis Inertia meninggalkan berkas .html lama di public/ (16 berkas per
#    12 September 2026). Apache DirectoryIndex menyajikan index.html itu dan
#    menutupi index.php, jadi situs tampak "tidak berubah" padahal sudah
#    ter-deploy -- gagal diam-diam. index.php, .htaccess, robots.txt,
#    favicon.ico, storage, dan build tidak disentuh.
# ---------------------------------------------------------------------------
say "Bersihkan sisa static export Next.js di public/"
run remote "cd '$REMOTE_APP/public' && rm -rf _next && find . -maxdepth 2 -type f -name '*.html' -delete && find . -maxdepth 2 -type f -name '*.txt' ! -name 'robots.txt' -delete"

# ---------------------------------------------------------------------------
# 4. Cache -- config:cache WAJIB. Ada config yang baru terbaca setelah cache
#    dibangun ulang (mis. config/amina_playbook.php); kalau dilewati, tool
#    playbook Amina diam-diam menjawab "Topik playbook tidak dikenal."
# ---------------------------------------------------------------------------
say "Cache: bangun ulang config, route, dan view"
run remote "cd '$REMOTE_APP' && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache"

# ---------------------------------------------------------------------------
# 5. Verifikasi -- deploy dianggap gagal kalau situs tidak sehat
# ---------------------------------------------------------------------------
say "Verifikasi"
if [ "$DRY_RUN" -eq 1 ]; then
  echo "   [dry-run] lewati"
  exit 0
fi

fail=0
for path in "/" "/login" "/admin/login" "/api/v1/openapi.json"; do
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 25 "$SITE_URL$path" || echo 000)"
  printf '   %-24s -> %s\n' "$path" "$code"
  [ "$code" = "200" ] || fail=1
done

if [ "$fail" -eq 1 ]; then
  warn "Ada endpoint yang tidak balas 200. Cek $REMOTE_APP/storage/logs/laravel.log"
  warn "Rollback ke $ROLLBACK:"
  warn "  cd $REMOTE_APP && git reset --hard $ROLLBACK && composer install --no-dev --no-scripts && php artisan package:discover && php artisan optimize:clear && php artisan config:cache"
  die "Deploy gagal verifikasi."
fi

say "Deploy selesai. $SITE_URL"
