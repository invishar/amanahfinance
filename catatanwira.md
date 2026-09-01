# Catatan Kerja — Setup Lokal & Jalur Deploy

Dikerjakan 31 Agustus – 1 September 2026. Ringkasan poin penting saja; detail
teknis lengkap ada di `CLAUDE.md`, `DEPLOY-FRONTEND-HPANEL.md`, dan komentar di
dalam `scripts/deploy.sh`.

> Detail akses server (host, port, user) **tidak ditulis di sini** — repo ini
> publik. Semuanya ada di `scripts/deploy.env` yang di-gitignore.

---

## 1. Environment lokal (Windows, tanpa hak admin)

- **PHP 8.4.25 portable** dipasang manual di `C:\Users\user\tools\php-8.4.25`
  (unduh dari windows.php.net, checksum SHA256 diverifikasi). XAMPP bawaan mesin
  ini PHP 7.3 — terlalu tua, `composer.json` minta `^8.4`.
- **Composer** sebagai `composer.phar` di `C:\Users\user\tools`.
- **Database: MariaDB dari XAMPP**, bukan SQLite. Ini bukan preferensi —
  `create_reporting_views.php` memakai `date_format()` dan `curdate()` yang
  MySQL-only, jadi SQLite pasti gagal saat migrasi.
- `.env` dibuat dari `.env.example`, diarahkan ke MySQL lokal (db `amanahfinance`).
- 33 migrasi + seeder jalan bersih.

Menyalakan (tiga terminal terpisah, dari root repo):

```bash
C:/Users/user/tools/php-8.4.25/php.exe artisan serve --host=127.0.0.1 --port=8000
C:/Users/user/tools/php-8.4.25/php.exe artisan queue:work --tries=2
npm --prefix frontend run dev
```

Kalau MariaDB mati: jalankan `C:\xampp\mysql\bin\mysqld.exe`.

Akun demo dari seeder: `admin@example.com` / `password` (admin),
`ega.mayasari@example.com` / `password` (user dengan 26 transaksi).

**Test suite: 227 test, 795 assertion, semua lolos (~54 detik).**
Jalankan dengan `php artisan test` — butuh database `afapi_testing` (dibuat
terpisah, lihat `phpunit.xml`).

---

## 2. Temuan soal hosting

- Staging **di-deploy ke DomaiNesia**, bukan hPanel/Hostinger. Seluruh dokumen
  deploy di repo (`DEPLOY-FRONTEND-HPANEL.md`, gotcha `composer2`, `proc_open`,
  nodevenv) ditulis untuk server Hostinger yang lama — **jangan dipercaya
  mentah-mentah** untuk server sekarang.
- Repo di server di-clone manual lewat SSH, checkout `main`.
- Hook `deploy/hooks/post-merge` **aktif** (`core.hooksPath=deploy/hooks`), jadi
  `git pull` di server otomatis menjalankan `migrate --force`.
- **Server TIDAK punya Node.js/npm.** Yang ada: PHP 8.4.24, composer, rsync, git.

### Konsekuensi paling penting

Karena npm tidak ada, langkah build frontend di dalam hook **selalu dilewati
tanpa error**:

```
post-merge: frontend/ changed, rebuilding static export...
post-merge: npm not found in PATH, skipping frontend build
```

Artinya `git pull` di server **tidak akan pernah memperbarui tampilan**. Backend
naik versi, frontend tetap versi lama, tanpa satu pun pesan gagal. Ini sudah
terkonfirmasi terjadi sungguhan, bukan dugaan.

Ditambah: hasil build memang tidak ada di git (`public/*` dan `frontend/out/`
di-gitignore), jadi commit saja tidak pernah cukup untuk mengubah tampilan.

---

## 3. Jalur deploy: `scripts/deploy.sh`

Build di laptop, kirim hasilnya ke server. Satu perintah mengurus dua paruh
deploy sekaligus.

```bash
scripts/deploy.sh              # backend (git pull + migrate) + frontend
scripts/deploy.sh --frontend   # frontend saja, lebih cepat
scripts/deploy.sh --dry-run    # lihat rencananya, jangan eksekusi
```

Urutan kerjanya: preflight → `git pull` di server (hook jalankan migrate) →
`composer install` → clear cache → build frontend di laptop → upload → rsync ke
`public/` → verifikasi `curl`. Kalau ada endpoint tidak balas 200, script
berhenti dengan status gagal.

**Setup sekali per mesin:**

```bash
cp scripts/deploy.env.example scripts/deploy.env   # lalu isi nilainya
```

Butuh SSH key yang public-nya sudah di-*authorize* di cPanel
(Security → SSH Access → Manage SSH Keys → Import → Authorize).

Alur harian: `git push` lalu `scripts/deploy.sh`.

---

## 4. Hasil deploy pertama (1 September 2026)

Server ternyata tertinggal **10 commit** (masih di `7efae34`, 26 Agustus).
Deploy ini menaikkannya ke `bfc82bb` — hampir sepekan pekerjaan yang belum
pernah tayang, termasuk rombakan kartu aksi AI dan bubble chat ala WhatsApp.

Verifikasi akhir: `/`, `/login/`, `/admin/login/`, `/api/v1/openapi.json`
semuanya 200.

---

## 5. Yang perlu diingat

- **Dua file di server sengaja "dirty"** dan sudah ditandai `skip-worktree`
  supaya tidak menghalangi `git pull`:
  `public/.htaccess` (blok `error_log` yang ditambahkan cPanel otomatis —
  jangan di-commit, path-nya spesifik server) dan `public/favicon.ico`
  (selalu ditimpa build Next; ini memang disengaja).
- **`scripts/deploy-frontend.sh` sudah usang** — masih memuat host Hostinger
  lama secara terbuka di repo publik. Perlu dihapus atau diredaksi.
- Kunci LLM diatur lewat **panel admin `/admin/llm-settings`**, dibaca dari tabel
  `llm_settings`, bukan dari `.env`. Seeder mengisi kunci dummy, jadi chat AI di
  lokal tidak akan membalas sampai diisi kunci asli.
- Catatan di `GROQ-MODELS.md` menulis contoh env `LLM_KEY=`, padahal kode membaca
  `LLM_API_KEY`. Dokumennya yang salah.
