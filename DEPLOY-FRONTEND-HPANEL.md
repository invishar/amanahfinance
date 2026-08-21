# Catatan — Deploy Frontend Static Export ke hPanel

Dibuat 19 Agustus 2026. Log kronologis proses membuat `frontend/` (Next.js static
export) benar-benar ter-build dan ter-serve dari `public/` di server hPanel produksi
(`<DOMAIN>`). Ditulis sebagai rujukan kalau masalah serupa muncul lagi di
server lain, atau kalau server ini di-reset/redeploy dari nol. Update status tiap
item begitu dikerjakan, jangan hapus riwayatnya — tambahkan entri baru di bawah.

**Placeholder di dokumen ini** — sesuaikan dengan server kamu sendiri:
- `<DOMAIN>` — domain publik aplikasi (mis. `api.contohdomain.com`)
- `<APP_PATH>` — path aplikasi di home directory server (mis.
  `~/domains/contohdomain.com/nama-app`)

---

## Gejala awal

`<DOMAIN>/admin` (dan kemungkinan seluruh path frontend lain) mengembalikan
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

**Fix dicoba:** batasi Next.js ke 1 worker lewat `frontend/next.config.ts`:
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
semua route termasuk `/admin` tetap ter-generate. Commit terpisah, sudah di-push.
Di server: sesi yang tersandera berhasil dipulihkan (`Ctrl+Z` lalu `Ctrl+C` di
`fg`), `ps aux`/`free -h` normal lagi. **Tapi** `cpus: 1` ternyata tidak cukup —
lihat root cause #5.

## Root cause #5 — limit thread (bukan cuma proses) juga kena, `cpus: 1` tidak cukup

Setelah `next.config.ts` (`cpus: 1`) di-pull dan `npm run build` dicoba lagi (dua
kali, termasuk setelah export `PATH` manual buat Node yang sempat tidak
ke-`.bashrc`/`.bash_profile` di percobaan pertama — itu insiden terpisah, bukan
root cause, cuma salah urutan langkah), build **konsisten** gagal di titik yang
sama:
```
node[2103934]: pthread_create: Resource temporarily unavailable
Warning: Failed to load CA certificates off thread: resource temporarily unavailable
Next.js build worker exited with code: null and signal: SIGABRT
```
`cpus: 1` cuma membatasi jumlah **worker process** Next sendiri — tapi Node.js
selalu butuh beberapa **thread** internal (libuv threadpool, loading CA
certificate off-thread, dll) terlepas dari setting itu. Thread-thread kecil ini
pun sudah cukup untuk kena limit akun ini.

Dicek `ulimit -a` di server: `max user processes (-u)` menunjukkan **1027471**
(jutaan, jelas bukan pembatasnya) — mengkonfirmasi limitnya **bukan** rlimit POSIX
biasa, melainkan CloudLinux LVE yang diterapkan di level kernel/cgroup terpisah,
tidak pernah terlihat lewat `ulimit`. Tool `lveinfo` (biasanya dipakai user
CloudLinux buat lihat limit sendiri) juga tidak tersedia di akun ini
(`command not found`) — jadi angka limit LVE pastinya tidak bisa dipastikan dari
shell, cuma bisa disimpulkan dari gejala.

**Kesimpulan: build Next.js apa pun (sekecil apapun paralelismenya) tidak bisa
diandalkan jalan di akun ini.** Bukan soal lambat, bukan soal butuh tuning lebih
lanjut — dua limit berbeda (proses lalu thread) sudah kena berturut-turut meski
sudah dikurangi ke minimum yang Next.js izinkan (`cpus: 1`). Tidak ada tuning
lanjutan yang masuk akal dicoba di titik ini.

**Fix final: build lokal, upload hasil static-nya.** Hook `post-merge` otomatis
di server **tidak dipakai lagi untuk frontend** (tetap jalan untuk migrasi DB).
Prosedur build-lokal-upload didokumentasikan lengkap di bagian
["Prosedur deploy frontend (final)"](#prosedur-deploy-frontend-final) di bawah.

---

## Prosedur deploy frontend (final)

**Sekali per server** (atau kalau ragu setelah setup ulang/reset), verifikasi
document root domain benar-benar symlink ke `<APP_PATH>/public` — lihat
[root cause #7](#root-cause-7--document-root-fisik-terpisah-dari-apppathpublic-21-agustus-2026)
kenapa ini penting:
```bash
readlink -f ~/domains/<domain-utama>/public_html/<subdomain-atau-folder-docroot>
```
Kalau hasilnya path itu sendiri (bukan mengarah ke `<APP_PATH>/public`), berarti
itu folder fisik terpisah — perbaiki dulu (pindahkan isinya, buat symlink) sebelum
lanjut, kalau tidak rsync di bawah akan menulis ke tempat yang tidak pernah
tersaji.

Setiap kali `frontend/` berubah dan siap di-deploy, dari laptop lokal:

```bash
cd frontend
npm run build          # next build --webpack, otomatis jalanin postbuild fix-nested-index
cd out
tar -czf ../frontend-out.tar.gz .
cd ../..
```

Upload + sync (isi `HOST`/`PORT` SSH sesuai server):
```bash
scp -P <PORT> frontend/frontend-out.tar.gz <user>@<host>:~/frontend-out.tar.gz
```
Lalu di sesi SSH server:
```bash
cd <APP_PATH>
mkdir -p /tmp/frontend-out
tar -xzf ~/frontend-out.tar.gz -C /tmp/frontend-out
rsync -a --delete \
  --exclude "index.php" --exclude ".htaccess" \
  --exclude "robots.txt" --exclude "storage" \
  /tmp/frontend-out/ public/
rm -rf /tmp/frontend-out ~/frontend-out.tar.gz
curl -sI https://<DOMAIN>/ | head -1
curl -sI https://<DOMAIN>/admin | head -1
```

Hapus `frontend/frontend-out.tar.gz` lokal setelah selesai upload — itu build
artifact, bukan sesuatu yang perlu dikomit.

## Status saat dokumen ini ditulis (19 Agustus 2026)

- [x] Hook `post-merge` aktif untuk migrasi DB
- [x] Node.js portable terpasang di `$HOME/node`, masuk `PATH` (`~/.bashrc` +
      `~/.bash_profile`) — **tapi tidak lagi dipakai untuk build**, cuma tersisa
      di server, tidak perlu dicabut
- [x] `frontend/package.json` dipaksa `--webpack`, `next.config.ts` dibatasi
      `cpus: 1` — kedua fix ini tetap berguna untuk build **lokal** yang jadi
      prosedur resmi, meski tidak menyelesaikan masalah di server
- [x] Build-lokal-upload dieksekusi 19 Agustus 2026, dikonfirmasi:
      `<DOMAIN>/`, `/admin`, `/admin/login` semua `200`
- [x] Prosedur build-lokal-upload jadi cara resmi deploy frontend ke depannya
      (bukan lagi cadangan)
- [x] **Root cause #6 ditemukan & difix**: `/admin` bisa dibuka tapi blank +
      `/admin/login` tanpa style — lihat detail di bawah
- [x] **Root cause #7 ditemukan & difix (21 Agustus 2026)**: document root domain
      ternyata folder fisik terpisah dari `<APP_PATH>/public`, sudah diganti jadi
      symlink — lihat detail di bawah
- [ ] **TODO**: hapus `public_html/afapi.bak-20260821` (backup folder document
      root lama dari fix root cause #7) setelah dipastikan stabil beberapa hari

## Root cause #6 — fallback Laravel tidak bisa serve asset statis (`_next/static/*`)

Setelah frontend live, `/admin` cuma nongol logo "AF" (tidak sempat hydrate/redirect
ke login), dan `/admin/login` tampil tanpa CSS sama sekali. Dicek: file asset-nya
(`_next/static/css/*.css`, `_next/static/chunks/main-app-*.js`, dll) **ada** secara
fisik di `public/` (dikonfirmasi lewat `ls` di server), tapi `curl` ke path-nya
langsung mengembalikan 404 — dan response header-nya menunjukkan itu lewat Laravel
(`x-powered-by: PHP`, ada `Set-Cookie` session), bukan diserve statis oleh web
server/CDN (`hcdn`, milik Hostinger).

Root cause: `Route::fallback` di `routes/web.php` cuma tahu cara serve **halaman
HTML** — dia coba kandidat `{path}.html` dan `{path}/index.html`, tidak pernah coba
serve `{path}` apa adanya. Untuk request asset (`.css`, `.js`, font, dst.) yang
somehow sampai ke Laravel (bukan diserve langsung oleh `hcdn`/web server — perilaku
CDN ini tidak konsisten, sebagian file lolos sebagian tidak, worth diasumsikan tidak
bisa diandalkan), fallback selalu 404 karena tidak ada kandidat yang cocok.

**Fix (bagian 1):** tambah exact-path check di awal closure, sebelum coba kandidat
`.html` — kalau `public_path($path)` adalah file yang benar-benar ada, serve
langsung pakai `response()->file()`. Commit `fcbf9e6`.

**Fix (bagian 2) — sub-masalah baru ketemu setelah bagian 1 di-deploy:** asset
sudah `200`, tapi `/admin/login` di browser **masih tanpa style**. Dicek
`Content-Type`-nya ternyata `text/plain`, bukan `text/css` — `response()->file()`
mengandalkan deteksi MIME otomatis via extension PHP `fileinfo`, dan itu persis
gotcha yang sudah didokumentasikan di `CLAUDE.md` bagian composer2/`composer-php.ini`
(extension bisa hilang di luar `php.ini` situs). Ketika deteksi gagal, Symfony diam-diam
fallback ke `text/plain` — browser menolak apply CSS / eksekusi JS yang di-serve
dengan Content-Type itu (strict MIME checking), makanya `/admin/login` tetap polos
meski asset-nya sudah `200`.

Fix final: tentukan `Content-Type` manual dari peta ekstensi (`css`, `js`, `svg`,
font, dst.) di closure, bukan mengandalkan `fileinfo`. Commit `d88d34b`.

**Status: dikonfirmasi beres oleh user 19 Agustus 2026** — `/admin` redirect ke
login dengan benar, `/admin/login` tampil bergaya normal.

Kedua fix ini murni perubahan kode PHP (`routes/web.php`), tidak menyentuh
`frontend/` — deploy ke server cukup `git pull` biasa, tidak perlu build ulang
frontend sama sekali.

## Root cause #7 — document root fisik terpisah dari `<APP_PATH>/public` (21 Agustus 2026)

Setelah root cause #1–#6 di atas beres dan prosedur build-lokal-upload dijalankan
berulang kali, `<DOMAIN>` **tetap** menyajikan versi lama — `curl -sI` menunjukkan
`Last-Modified` beku beberapa hari ke belakang dan sama sekali tidak ada header
Laravel (`x-powered-by`, `Set-Cookie`), padahal `<APP_PATH>/public/` di server
sudah dikonfirmasi berisi build terbaru.

Diagnosa lewat SSH:
```bash
find ~ -maxdepth 5 -iname "index.php"
```
menunjukkan **dua** `index.php` berbeda untuk domain ini:
- `~/domains/anindyo.in/amanahfinance_api/public/index.php` — repo git, target
  rsync prosedur deploy di atas (`<APP_PATH>/public`).
- `~/domains/anindyo.in/public_html/afapi/index.php` — folder **fisik terpisah**,
  ternyata inilah document root asli yang dipetakan hPanel untuk subdomain
  `afapi.anindyo.in`.

`readlink -f` pada folder kedua mengembalikan path itu sendiri (bukan symlink) —
folder biasa, terakhir berubah beberapa hari sebelumnya, cocok dengan tanggal
`Last-Modified` yang stale. Setiap siklus build-lokal-upload di prosedur di atas
selama ini menulis ke `<APP_PATH>/public/` dengan benar, tapi **tidak pernah
tersaji** karena web server membaca dari folder lain.

Bukti tambahan: `index.php` di folder lama itu ternyata sudah **dipatch manual**
(path `../../amanahfinance_api/...` alih-alih `../...` bawaan Laravel) — indikasi
seseorang sebelumnya sudah pernah menambal gejala yang sama dengan cara manual per
file, bukan memperbaiki penyebabnya.

**Fix:** jadikan folder document root itu symlink ke `<APP_PATH>/public`, bukan
folder fisik terpisah:
```bash
mv ~/domains/anindyo.in/public_html/afapi ~/domains/anindyo.in/public_html/afapi.bak-20260821
ln -s ~/domains/anindyo.in/amanahfinance_api/public ~/domains/anindyo.in/public_html/afapi
```
(Symlink `storage` dan isi `.htaccess` di `<APP_PATH>/public/` sudah benar duluan,
tidak perlu disentuh — cuma folder pembungkusnya yang diganti jadi symlink.)

Efek samping positif: `.htaccess` yang live sekarang adalah yang di-track git,
jadi fix `CacheLookup off` (LiteSpeed page-cache, commit `3a68520`, lihat
`CLAUDE.md`) yang sebelumnya sudah di-commit tapi tidak pernah benar-benar live
(karena live docroot adalah folder lain) ikut aktif. `curl -sI` setelah fix
menunjukkan header Laravel yang benar (`x-powered-by: PHP/8.4.23`,
`Set-Cookie: afapi-session=...`, `cache-control: no-cache, private`) — konfirmasi
request sekarang benar-benar sampai ke kode terbaru.

**Status: dikonfirmasi beres oleh user 21 Agustus 2026.** Folder lama dipindah
(bukan dihapus) ke `public_html/afapi.bak-20260821` sebagai jaring pengaman —
**TODO: hapus setelah beberapa hari stabil**, lihat checklist di bawah.

**Implikasi untuk server/domain lain:** kalau setup ulang di server baru, JANGAN
asumsikan folder document root yang ditampilkan hPanel otomatis = `<APP_PATH>/public`.
Selalu verifikasi dengan `readlink -f` pada folder document root sebelum
mengandalkan prosedur rsync di bawah — lihat langkah verifikasi yang ditambahkan
di ["Prosedur deploy frontend (final)"](#prosedur-deploy-frontend-final).

## Alternatif yang dipertimbangkan tapi belum dipakai

- **Build di CI (GitHub Actions) lalu deploy artifact**: belum dieksplorasi.
  Kelebihan dibanding build-lokal-upload manual: tidak bergantung laptop developer
  tertentu harus nyala & terhubung, bisa dipicu otomatis dari push seperti hook
  `post-merge` yang sekarang tidak bisa dipakai lagi untuk frontend. Worth
  dipertimbangkan kalau proses upload manual ini mulai terasa membebani (banyak
  developer, atau frontend sering berubah).

## Fakta lingkungan server (untuk rujukan cepat, tanpa kredensial)

- Domain publik: `<DOMAIN>`
- Path aplikasi di server: `<APP_PATH>`
- Hosting: hPanel (kemungkinan Hostinger), CloudLinux dengan LVE resource limits
- **Tidak ada** menu "Setup Node.js App" di panel akun ini
- **Tidak ada** binary `xz` di shell server (pakai `.tar.gz`, bukan `.tar.xz`)
- Hanya **1 sesi SSH bersamaan** yang diizinkan per akun (atau setidaknya, sesi
  kedua gagal connect saat sesi pertama sedang menjalankan proses berat — belum
  dipastikan apakah ini limit sesi murni atau efek samping nproc exhaustion)
- `proc_open` dan `symlink()` juga punya gotcha terpisah di `CLAUDE.md` bagian
  "Perintah" — kemungkinan besar server ini satu keluarga constraint yang sama
  (CloudLinux `disable_functions` + LVE), worth dicek ulang kalau ada masalah baru
- Document root domain adalah `~/domains/anindyo.in/public_html/afapi` — sejak
  fix [root cause #7](#root-cause-7--document-root-fisik-terpisah-dari-apppathpublic-21-agustus-2026)
  ini adalah **symlink** ke `~/domains/anindyo.in/amanahfinance_api/public`
  (bukan folder fisik terpisah lagi). Verifikasi dengan `readlink -f` kalau ada
  masalah "deploy sudah jalan tapi web tidak berubah" lagi di masa depan.
