#!/bin/sh
# Build frontend/ (Next.js static export) lokal, lalu kirim hasilnya ke
# public/ di server produksi lewat SSH. Tidak menyentuh git sama sekali --
# server tidak punya Node.js, jadi build harus dilakukan di luar server
# (lihat README.md "Struktur proyek").
#
# Jalankan manual tiap kali ada perubahan di frontend/ yang siap dideploy:
#   scripts/deploy-frontend.sh

set -e

SSH_PORT=65002
SSH_HOST=u375173189@37.44.245.89
REMOTE_PUBLIC=/home/u375173189/domains/anindyo.in/amanahfinance_api/public

REPO_ROOT=$(cd "$(dirname "$0")/.." && pwd)
TARBALL="/tmp/frontend-build-$$.tar.gz"

cd "$REPO_ROOT/frontend"

echo "==> Install dependencies..."
npm ci

echo "==> Build static export..."
npm run build

echo "==> Packing build output..."
(cd out && tar czf "$TARBALL" .)

echo "==> Upload ke server..."
scp -P "$SSH_PORT" "$TARBALL" "$SSH_HOST:/tmp/frontend-build.tar.gz"

echo "==> Extract di server..."
ssh -p "$SSH_PORT" "$SSH_HOST" "mkdir -p '$REMOTE_PUBLIC' && tar xzf /tmp/frontend-build.tar.gz -C '$REMOTE_PUBLIC' && rm /tmp/frontend-build.tar.gz"

rm -f "$TARBALL"
echo "==> Selesai. Cek https://afapi.anindyo.in/admin"
