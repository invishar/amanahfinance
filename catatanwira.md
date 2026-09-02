# Catatan Kerja — Setup Lokal & Jalur Deploy

Dikerjakan 31 Agustus – 2 September 2026. Ringkasan poin penting saja; detail
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
- `.env` dibuat dari `.env.example`, diarahkan ke MySQL lokal (db `amanahfinance_dev`).
- 33 migrasi + seeder jalan bersih.

Menyalakan (tiga terminal terpisah, dari root repo):

```bash
C:/Users/user/tools/php-8.4.25/php.exe artisan serve --host=127.0.0.1 --port=8000
C:/Users/user/tools/php-8.4.25/php.exe artisan queue:work --tries=2
npm --prefix frontend run dev
```

Kalau MariaDB mati: jalankan `C:\xampp\mysql\bin\mysqld.exe`.

Akun demo dicetak seeder di akhir `db:seed` (email user berubah tiap kali
di-seed ulang). Admin selalu `admin@example.com` / `password`.

**Test suite: 230 test, semua lolos (~16 detik).**
Jalankan dengan `php artisan test` — butuh database `afapi_testing` (dibuat
terpisah, lihat `phpunit.xml`).

### Gotcha: PHP portable tidak punya CA bundle → semua HTTPS gagal

Paket ZIP php.net tidak menyertakan sertifikat CA, dan `php.ini-development`
membiarkan `curl.cainfo`/`openssl.cafile` kosong. Akibatnya **setiap** panggilan
HTTPS dari PHP gagal — termasuk panggilan ke penyedia LLM, yang muncul sebagai
`APIConnectionException` di `ai_provider_errors` dan gampang disalahartikan
sebagai masalah jaringan atau kunci. Padahal `curl` dari terminal jalan normal;
yang tidak bisa hanya PHP.

Sudah dipasang: `cacert.pem` dari <https://curl.se/ca/cacert.pem> ditaruh di
folder PHP, lalu di `php.ini`:

```ini
curl.cainfo = "C:\Users\user\tools\php-8.4.25\cacert.pem"
openssl.cafile = "C:\Users\user\tools\php-8.4.25\cacert.pem"
```

Perlu diulang kalau PHP dipasang ulang atau versinya diganti. Setelah mengubah
php.ini, **restart queue worker** — proses lama masih memegang konfigurasi lama.

### Gotcha: MariaDB mati paksa → "Tablespace exists"

Kalau mysqld terbunuh tidak bersih (mis. proses di-kill), InnoDB bisa
meninggalkan file `.ibd` yatim, dan migrasi berikutnya gagal dengan
`SQLSTATE[HY000] 1813 Tablespace for table ... exists` atau
`1932 ... doesn't exist in engine`. Menghapus folder database saat mysqld
masih **berjalan** tidak menyelesaikan apa pun — dictionary InnoDB tetap
memegang entri basinya. Bahkan setelah shutdown + hapus folder + start ulang,
entri basi kadang MASIH tersisa (terjadi 2 September 2026); yang akhirnya
berhasil adalah menjalankan `DROP DATABASE` + `CREATE DATABASE` sekali lagi
setelah restart. Urutan yang dipakai:

```powershell
C:\xampp\mysql\bin\mysqladmin.exe -u root shutdown
Remove-Item C:\xampp\mysql\data\<nama_db> -Recurse -Force
Start-Process C:\xampp\mysql\bin\mysqld.exe -ArgumentList '--standalone'
# lalu create database lagi, migrate, seed
```

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

---

## 6. Audit alur AI Amina (2 September 2026)

Dipicu pertanyaan "apakah pemakaian token Amina sudah efektif". Jawabannya
belum — tapi temuan terbesarnya justru bukan soal token.

### 6a. Kebocoran data antar-keluarga (SUDAH DIPERBAIKI)

Konteks yang dikirim Amina ke penyedia LLM memuat wallet **9 keluarga lain**
(45 dari 50 wallet) lengkap dengan nama, budget, dan pengeluarannya — di
setiap pesan.

Akar masalah: `AnalyticsActions::wallets()` dan `incomeSources()` tidak
memfilter `family_id`, melainkan mengandalkan global scope `BelongsToFamily`.
Scope itu **fail-open**:

```php
$familyId = app(CurrentFamily::class)->id();
if ($familyId !== null) {        // null -> TIDAK ADA filter sama sekali
    $query->where(...);
}
```

`CurrentFamily` diisi middleware `ResolveFamily`, yang **hanya jalan di
request HTTP**. Amina jalan di queue job → `CurrentFamily::id()` null → scope
diam → seluruh tabel terbuka.

| Konteks | Wallet terlihat |
| --- | --- |
| HTTP (dashboard, analisa) | 5 — benar |
| Queue job (jalur Amina) | 50 — 45 milik keluarga lain |

Lolos dari 227 test karena **semua** uji kebocoran tenant menembak endpoint
HTTP, tempat scope memang bekerja. Test regresi baru di `AnalyticsTest`
sengaja **tidak** memakai `actingAs` supaya meniru konteks queue — sudah
diverifikasi gagal pada kode lama dan lolos pada kode baru.

> **Pelajaran yang lebih luas:** pola `if ($familyId !== null)` itu masih ada
> di `BelongsToFamily` dan melindungi seluruh model. Setiap kode yang jalan di
> luar request HTTP (job, command, scheduler, seeder) tidak terlindungi sama
> sekali. Yang diperbaiki di sini baru `AnalyticsActions`. **Audit pemakaian
> model lain dari dalam job sebelum menambah fitur AI/terjadwal.**

### 6b. Pemakaian token: −60%

| | Sebelum | Sesudah |
| --- | --- | --- |
| System prompt | ~3.655 token | ~670 token |
| Definisi tool | ~1.320 token | ~1.320 token |
| **Overhead per panggilan LLM** | **~4.976** | **~1.990** |

Sebagian besar pemborosan itu memang kebocoran di atas — 90% isi payload
adalah data keluarga lain. Satu perbaikan menutup dua masalah.

### 6c. Metode konteks yang dipakai sekarang

Prinsip: **yang selalu dibutuhkan tapi murah → inline; yang besar tapi jarang
dipakai → ambil lewat tool.** Sebelumnya terbalik.

Yang berubah di `AssistantService::buildSystemPrompt()`:

- **Katalog nama dikirim**: `wallets`, `accounts`, `income_sources`,
  `savings_goals`. Sebelumnya nama akun & target **tidak pernah dikirim sama
  sekali**, padahal `create_transaction` mewajibkan `account_name` di semua
  jenis transaksi — model disuruh menyebut nama yang tak pernah ia lihat,
  lalu `NameResolver` menebak.
- **`hari_ini` ditambahkan** supaya "kemarin"/"senin lalu" bisa dihitung jadi
  `transaction_date`. Sebelumnya konteks cuma punya awal bulan.
- **Rincian per-wallet dikeluarkan** dari prompt (budget/spent/remaining/
  percent/status) — sekarang hanya lewat `get_financial_summary`.
- **`kas_bulan_ini` tetap inline.** Persona lama memerintahkan "selalu panggil
  get_financial_summary dulu" padahal ringkasan yang sama sudah tertempel di
  prompt, jadi satu pertanyaan membayar data itu 3x (di prompt, di hasil tool,
  di prompt panggilan kedua). Sekarang pertanyaan umum selesai dalam satu
  panggilan LLM, bukan dua.
- Isi tiap pesan riwayat dipotong 1.000 karakter.

Persona di `config/amina.php` ikut diselaraskan — kalau tidak, Amina tetap
memanggil tool untuk angka yang sudah ada di konteks.

Dikunci dua test di `AssistantServiceTest`: satu memastikan katalog nama &
tanggal ikut terkirim, satu memastikan rincian per-wallet **tidak**.

### 6d. Belum dikerjakan

- **Kualitas jawaban belum diuji dengan LLM sungguhan** — kunci LLM lokal masih
  dummy dari seeder. Perbaikan token & kebocoran terukur pasti; klaim soal
  akurasi resolusi nama masih penalaran, belum bukti empiris. Isi kunci di
  `/admin/llm-settings` untuk mengujinya.
- **Prompt caching Anthropic** (`cache_control`) untuk memangkas ~1.320 token
  definisi tool pada panggilan berulang. Setelah pemangkasan ini nilainya
  berkurang, dan jalur Groq tidak mendukungnya — tinjau lagi kalau volume naik.
