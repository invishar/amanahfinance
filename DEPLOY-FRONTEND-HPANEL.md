# Catatan — Deploy Frontend Static Export ke hPanel

Dibuat 19 Agustus 2026. Log kronologis proses membuat `frontend/` (Next.js static
export) benar-benar ter-build dan ter-serve dari `public/` di server hPanel produksi
(`afapi.anindyo.in`). Ditulis sebagai rujukan kalau masalah serupa muncul lagi di
server lain, atau kalau server ini di-reset/redeploy dari nol. Update status tiap
item begitu dikerjakan, jangan hapus riwayatnya — tambahkan entri baru di bawah.

---

## Gejala awal

`afapi.anindyo.in/admin` (dan kemungkinan seluruh path frontend lain) mengembalikan
404 default Laravel, padahal source route-nya ada di `frontend/app/admin/`.

## Root cause #1 — hook belum pernah jalan

[deploy/hooks/post-merge](deploy/hooks/post-merge) yang mem-build frontend dan
sync ke `public/` baru ditambahkan di commit `8122b45`. Hook itu:
1. Tidak otomatis aktif — butuh `git config core.hooksPath deploy/hooks` sekali per
   checkout server.
2. Bahkan kalau sudah aktif dari commit sebelumnya, pull yang menaikkan HEAD ke
   `8122b45` sendiri kemungkinan terjadi **sebelum** `core.hooksPath` di-set, karena
   file hook-nya baru muncul di commit itu juga (chicken-and-egg).

Hasilnya: `public/` di server belum pernah berisi hasil build frontend sama sekali.

**Fix:** `git config core.hooksPath deploy/hooks` dijalankan manual di server.
Status: **selesai**.

## Root cause #2 — tidak ada Node.js di server

Setelah hook aktif, langkah build manual gagal: `npm: command not found`.

- Dicoba: cari `$HOME/nodevenv` (pola CloudLinux Node.js Selector, disebut di
  komentar hook) — **tidak ada**, `find` return "No such file or directory".
- Dicek: hPanel akun ini **tidak punya menu "Setup Node.js App"** sama sekali
  (bukan cuma belum di-setup — menunya memang tidak muncul di panel, kemungkinan
  plan hosting tidak include Node App Manager).

**Fix yang dipakai:** install Node.js portable di `$HOME` tanpa root/panel:
```bash
ARCH=$(uname -m)   # x86_64 -> x64
BASE_URL="https://nodejs.org/dist/latest-v22.x/"
FILENAME=$(curl -s "$BASE_URL" | grep -oE "node-v22\.[0-9]+\.[0-9]+-linux-x64\.tar\.gz" | head -1)
curl -fsSLO "${BASE_URL}${FILENAME}"
mkdir -p "$HOME/node"
tar -xzf "$FILENAME" -C "$HOME/node" --strip-components=1
echo 'export PATH="$HOME/node/bin:$PATH"' >> ~/.bashrc
echo 'export PATH="$HOME/node/bin:$PATH"' >> ~/.bash_profile
```

Sub-kendala saat ini: server tidak punya binary `xz`, jadi `.tar.xz` resmi dari
nodejs.org gagal di-extract (`tar (child): xz: Cannot exec`). Solusinya pakai varian
`.tar.gz` yang juga disediakan nodejs.org (pakai gzip, tersedia di mana pun).

Hasil: `node v22.23.2` / `npm 10.9.8` terpasang di `$HOME/node`, masuk `PATH` lewat
`~/.bashrc` **dan** `~/.bash_profile` (supaya kepakai baik di shell login maupun
non-login — tidak yakin mana yang dipakai proses `git pull` di server ini).
Status: **selesai**. Perlu diulang manual kalau home dir server pernah di-reset.

## Root cause #3 — Turbopack timeout di CPU throttled hosting

`npm run build` (Next.js 16, default builder = Turbopack) macet lalu gagal setelah
beberapa menit:
```
FATAL: An unexpected Turbopack error occurred.
...
- creating new process
- timed out waiting for the Node.js process to connect (30s timeout)
```
Turbopack men-spawn subprocess Node terpisah untuk evaluasi PostCSS loader
(`app/globals.css`). Di shared hosting yang CPU-nya di-throttle berat (CloudLinux
LVE), subprocess itu tidak sempat connect dalam 30 detik.

**Fix:** paksa builder classic webpack (single-process, tidak butuh IPC subprocess
dengan timeout keras) lewat `frontend/package.json`:
```json
"build": "next build --webpack",
```
Commit `9858be0`, sudah di-push ke `main`. Status: **selesai** — dikonfirmasi build
lokal tetap sukses dengan `--webpack` dan menghasilkan semua route termasuk `/admin`.

## Root cause #4 — limit jumlah proses akun (nproc / LVE) kena worker paralel

Setelah fix Turbopack, `git pull` di server memicu hook, `npm run build` jalan
(builder webpack, bukan Turbopack lagi), tapi macet di "Creating an optimized
production build ..." selama >15 menit tanpa progress maupun error.

Diagnosa:
- Percobaan buka sesi SSH kedua untuk investigasi **ditolak** ("Connection closed
  by remote host" tepat setelah password) — awalnya diduga limit "1 sesi SSH
  bersamaan" khas shared hosting.
- Di sesi yang sama, proses di-suspend (`Ctrl+Z`), lalu `ps aux | grep node` /
  `free -h` **gagal fork**: `-bash: fork: retry: Resource temporarily unavailable`.

Ini konfirmasi lebih kuat dari sekadar limit sesi SSH: akun kena **limit nproc**
(CloudLinux LVE) karena Next.js/webpack secara default men-spawn satu worker
process per CPU core yang terdeteksi (lokal terdeteksi 15 core → "using 15
workers" di build lokal) untuk fase type-checking & static generation. Di server
dengan nproc cap rendah, ini menghabiskan seluruh jatah proses akun sekaligus,
membuat build macet diam-diam **dan** membuat sesi SSH baru/perintah baru tidak
bisa fork sama sekali.

**Fix (di-draft, belum dikonfirmasi jalan di server):** batasi Next.js ke 1 worker
lewat `frontend/next.config.ts`:
```ts
const nextConfig: NextConfig = {
  output: "export",
  experimental: {
    cpus: 1,
    workerThreads: false,
  },
};
```
Diverifikasi lokal: build tetap sukses ("Generating static pages using 1 worker"),
semua route termasuk `/admin` tetap ter-generate.

---

## Status saat dokumen ini ditulis

- [x] Hook `post-merge` aktif (`core.hooksPath` di-set)
- [x] Node.js portable terpasang di `$HOME/node`, masuk `PATH`
- [x] `frontend/package.json` dipaksa `--webpack` (commit `9858be0`, sudah di-push)
- [ ] **Belum dikonfirmasi:** apakah `Ctrl+C` di server berhasil membebaskan proses
      yang tersandera (nproc exhausted) — menunggu konfirmasi user
- [ ] `next.config.ts` (`experimental.cpus: 1`) sudah diedit lokal, **belum di-commit
      & push** — menunggu server pulih dulu sebelum retry
- [ ] Setelah push: `git pull` di server, pastikan `npm run build` selesai tanpa
      hang/fork error, dan `curl -sI https://afapi.anindyo.in/admin` mengembalikan
      `200`

## Langkah berikutnya (kalau lanjut dari sesi ini)

1. Pastikan server sudah pulih dari proses yang tersandera (`ps aux`/`free -h` bisa
   jalan lagi tanpa error fork).
2. Commit + push perubahan `next.config.ts` (`experimental.cpus: 1`).
3. `git pull` di server, biarkan hook `post-merge` jalan otomatis.
4. Kalau build masih lambat (bukan hang) — itu wajar di shared hosting throttled,
   cukup ditunggu (order beberapa menit, bukan puluhan menit).
5. Kalau build masih hang meski `cpus: 1` — kemungkinan limit nproc akun ini sangat
   rendah (di bawah kebutuhan minimum Next.js: proses utama + 1 worker + child
   proses `npm`/`sh` yang dipicu hook + proses lain yang sedang jalan di akun yang
   sama). Opsi lanjutan: cek limit pasti lewat `ulimit -u`, atau tanya support
   hosting berapa nproc cap akun ini, atau build di CI (GitHub Actions) lalu upload
   hasil `frontend/out/` jadi artifact ke server (lihat pendekatan alternatif di
   bawah).

## Alternatif yang dipertimbangkan tapi belum dipakai

- **Build lokal + upload manual** (`tar` hasil `frontend/out/` lalu `scp` ke server,
  extract, `rsync` ke `public/`): terbukti bisa jadi cadangan cepat kalau server
  tidak sanggup build sendiri, tapi berarti hook `post-merge` otomatis jadi tidak
  berguna untuk perubahan `frontend/` — tiap update harus manual dari lokal.
- **Build di CI (GitHub Actions) lalu deploy artifact**: belum dieksplorasi, tapi
  ini yang paling robust kalau limit resource hPanel akun ini ternyata terlalu
  ketat untuk build Next.js apa pun (bahkan dengan `cpus: 1`) — CI resource jauh
  lebih longgar daripada shared hosting LVE.

## Fakta lingkungan server (untuk rujukan cepat, tanpa kredensial)

- Domain publik: `afapi.anindyo.in`
- Path aplikasi di server: `~/domains/anindyo.in/amanahfinance_api`
- Hosting: hPanel (kemungkinan Hostinger), CloudLinux dengan LVE resource limits
- **Tidak ada** menu "Setup Node.js App" di panel akun ini
- **Tidak ada** binary `xz` di shell server (pakai `.tar.gz`, bukan `.tar.xz`)
- Hanya **1 sesi SSH bersamaan** yang diizinkan per akun (atau setidaknya, sesi
  kedua gagal connect saat sesi pertama sedang menjalankan proses berat — belum
  dipastikan apakah ini limit sesi murni atau efek samping nproc exhaustion)
- `proc_open` dan `symlink()` juga punya gotcha terpisah di `CLAUDE.md` bagian
  "Perintah" — kemungkinan besar server ini satu keluarga constraint yang sama
  (CloudLinux `disable_functions` + LVE), worth dicek ulang kalau ada masalah baru
