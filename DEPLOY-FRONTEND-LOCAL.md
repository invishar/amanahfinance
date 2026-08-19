# Cara Build & Serve Frontend di Local (Herd)

Dibuat 19 Agustus 2026. Panduan supaya `frontend/` (Next.js static export) bisa
ditest lewat domain Laravel lokal (`http://amanahfinance_api.test/`), sama seperti
alur produksi di hPanel — bukan lewat `npm run dev` (`localhost:3000`) yang beda
mekanisme routing-nya. Lihat [DEPLOY-FRONTEND-HPANEL.md](DEPLOY-FRONTEND-HPANEL.md)
untuk log troubleshooting versi server produksi (root cause yang beririsan tapi
tidak identik dengan yang di sini).

---

## Prasyarat

- [Laravel Herd](https://herd.laravel.com/) terpasang, site `amanahfinance_api.test`
  aktif dan resolve ke folder repo ini (`C:\Repo\amanahfinance_api`) — **bukan**
  folder lain. Cara cek cepat kalau curl/browser menunjukkan hasil yang aneh/basi
  padahal kode sudah diubah, lihat [Gotcha #2](#gotcha-2-herd-serve-folder-yang-salah)
  di bawah.
- Node.js + npm terpasang (lokal, bukan constraint hPanel — laptop dev biasa aman).

## Langkah build & sync

`public/` di-gitignore (kecuali `index.php`, `.htaccess`, `robots.txt`,
`favicon.ico`) — hasil build frontend **tidak pernah** ikut commit, jadi harus
di-build ulang tiap kali `frontend/` berubah (baru clone, pull, atau habis edit).

```bash
cd frontend
npm ci              # sekali saja / kalau package.json berubah
npm run build       # next build --webpack, lalu otomatis jalanin postbuild
cd ..
```

`npm run build` otomatis memicu `postbuild` (`frontend/scripts/fix-nested-index.js`)
yang menambal `route/index.html` untuk tiap route yang Next taruh berdampingan
dengan direktori bernama sama (lihat [Gotcha #1](#gotcha-1-direktori-vs-file-collision)) —
tidak perlu langkah manual tambahan.

Sync hasil `frontend/out/` ke `public/`. Git Bash lokal **tidak punya `rsync`**
(beda dari server), jadi dari root repo:

```bash
rm -rf public/_next public/admin public/accounts public/analysis public/chat \
       public/dashboard public/goals public/income public/login public/onboarding \
       public/register public/settings public/wallets public/_not-found
rm -f public/*.html public/*.txt
cp -r frontend/out/. public/
```

(Hapus dulu baru copy, bukan copy timpa langsung — supaya file lama yang sudah
tidak diproduksi build terbaru ikut hilang, bukan menumpuk jadi sampah.)

## Verifikasi

```bash
curl -sI http://amanahfinance_api.test/ | head -1
curl -sI http://amanahfinance_api.test/admin | head -1
curl -sI http://amanahfinance_api.test/admin/login | head -1
```

Semua harus `200`. Kalau ada yang 404, cek dua gotcha di bawah dulu sebelum curiga
ke kode.

---

## Gotcha #1: direktori vs file collision

Next static export nulis `route.html` **sekaligus** direktori `route/` (isi RSC
payload + subroute) untuk hampir semua route — bukan cuma yang punya child page.
Kalau `route/` ada tapi tidak punya `index.html` di dalamnya, web server yang
mengecek direktori duluan (nginx `try_files`, PHP built-in server, dll — perilaku
persisnya beda-beda per server) bisa berhenti di situ dan 404 duluan sebelum
request sempat sampai ke Laravel.

**Sudah difix** lewat `frontend/scripts/fix-nested-index.js`, dipanggil otomatis
sebagai `postbuild` di `frontend/package.json`. Kalau suatu saat route baru 404
padahal filenya ada di `public/<route>.html`, cek dulu apakah `public/<route>/index.html`
juga ada — kalau tidak, kemungkinan build tidak lewat `npm run build` biasa (mis.
dipanggil manual tanpa lifecycle npm, sehingga `postbuild` ter-skip).

## Gotcha #2: Herd serve folder yang salah

Herd/Valet bisa punya **dua** sumber untuk site yang sama: folder ter-park
eksplisit di `~/.config/herd/config/valet/Sites/<nama>` (dari `herd park`/`link`)
dan auto-discovery dari folder yang cocok nama di bawah path yang terdaftar di
`~/.config/herd/config/valet/config.json` (`paths`, biasanya termasuk induk repo
kamu, mis. `C:\Repo`). Kalau keduanya ada dengan nama sama, **park eksplisit yang
menang** — dan kalau folder park itu adalah copy lama/basi (bukan symlink ke repo
aktif), request akan diam-diam diarahkan ke situ, bukan ke kode yang sedang kamu
edit. CLI (`php artisan route:list`, tinker, dst.) tetap menunjukkan state yang
benar karena jalan langsung di direktori kerja — cuma request HTTP lewat domain
`.test` yang salah arah. Ini bikin sangat membingungkan karena semua terlihat
benar di terminal tapi browser/`curl` tetap menunjukkan hasil lama.

**Cara cek:**
```bash
ls "$HOME/.config/herd/config/valet/Sites/" 2>/dev/null
```
Kalau ada folder dengan nama project ini di situ, bandingkan isinya dengan repo
aktif (mis. cek file yang baru saja kamu ubah) — kalau beda, itu bug-nya.

**Cara fix** (rename dulu, bukan hapus, supaya reversible sampai yakin tidak
dibutuhkan):
```powershell
Rename-Item "C:\Users\ACER\.config\herd\config\valet\Sites\amanahfinance_api" `
  "amanahfinance_api.stale-bak"
```
Herd otomatis fallback ke auto-discovery lewat `paths` (`C:\Repo`) setelah itu —
tidak perlu restart service apa pun, langsung kepakai di request berikutnya.

**Status di mesin ini:** sudah dilakukan 19 Agustus 2026, folder basi ada di
`C:\Users\ACER\.config\herd\config\valet\amanahfinance_api.stale-bak` — boleh
dihapus permanen kapan saja setelah dipastikan tidak ada yang perlu diselamatkan
dari situ (kemungkinan besar tidak ada, isinya cuma snapshot lama dari repo yang
sama).
