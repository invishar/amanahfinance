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

---

## 7. Wawancara awal dipegang Amina (2 September 2026)

Sebelumnya thread `kind=onboarding` **tidak lewat LLM sama sekali**: server
menyisipkan 4 pertanyaan skrip dari `config('amina.onboarding_questions')`, dan
jawabannya disimpan sebagai teks bebas di `onboarding_answers`. Tidak ada satu
baris kode pun yang mengubahnya jadi wallet/akun/sumber pemasukan — jadi selesai
wawancara, database keuangan keluarga masih kosong.

Sekarang Amina yang mewawancarai, dan hasilnya masuk database setelah user
konfirmasi.

### Cara kerjanya

Selama thread `kind=onboarding` dan `families.onboarding_done` masih false,
server menempelkan `config('amina.onboarding_briefing')` ke system prompt dan
mendaftarkan satu tool ekstra, `finish_onboarding`. Selebihnya jalur yang sama
persis dengan chat biasa.

Amina menggali empat hal satu per satu (sumber pemasukan, kantong pengeluaran +
budget, tempat uang disimpan, target tabungan) dan **langsung menyiapkan draft
begitu satu hal jelas** lewat tool `create_*` yang memang sudah ada. Draft itu
muncul sebagai kartu aksi yang bisa diedit/dibatalkan satu per satu. Aturan #5
tetap utuh: tidak ada baris bisnis ditulis sebelum user menekan konfirmasi.

`finish_onboarding` adalah **satu-satunya tool yang menulis langsung** tanpa
lewat `ai_actions` — pengecualian sadar, karena yang disentuh cuma penanda
status UI (`onboarding_done`), bukan rupiah atau transaksi.

### Kontrak API berubah

`ChatThreadResource.onboarding` yang tadinya `{step, total, done, question_key}`
kini **`{done}` saja**. Tidak ada lagi wizard berlangkah tetap — jumlah giliran
ditentukan percakapan. Klien mengirim jawaban sebagai **pesan chat biasa** ke
`POST /chat-threads/{id}/messages`, bukan lagi ke `/onboarding-answers`.

Endpoint `/onboarding-answers` tetap ada sebagai penyimpan catatan profil
keluarga (ikut dikirim ke prompt sebagai `tentang_keluarga`), tapi tidak dipakai
klien untuk wawancara lagi.

Halaman chat di frontend menyusut ~240 baris: jalur paralel onboarding (echo
bubble lokal, tombol "Lewati", mesin penyisipan berbasis `anchor` untuk
menangani beda jam client-server) semuanya tidak diperlukan lagi.

### Risiko yang diterima sadar

Onboarding kini **sepenuhnya bergantung LLM**. Kalau provider mati atau kena
rate limit, user baru tidak bisa menyelesaikan setup sama sekali — mereka
terjebak di chat yang tidak membalas. Groq free tier dibatasi 1.000 request/hari
untuk seluruh pengguna. Kalau nanti terasa rawan, fallback bisa ditambahkan
tanpa membongkar apa pun.

---

## 8. Balasan Amina tidak lagi menunggu cron (2 September 2026)

### Masalahnya

Panggilan LLM berjalan di job antrian (aturan `CLAUDE.md`: tidak pernah di
request web). Tapi shared hosting tidak mengizinkan worker daemon, jadi job baru
dikerjakan saat cron `schedule:run` berikutnya menyala. Di staging cron-nya tiap
**8 menit**.

Akibatnya, flow nyatanya begini: user kirim pesan pukul 20:00:00 → job masuk
antrean → tidak ada siapa pun yang mengerjakannya → layar menampilkan "Amina
sedang mengetik" selama 8 menit → 20:08:00 cron menyala → LLM menjawab dalam 2
detik → balasan muncul.

**Total tunggu 8 menit, yang benar-benar dipakai berpikir 2 detik.**

Cron cocok untuk pekerjaan yang tidak ada orang menungguinya (mis.
`amana:expire-subscriptions`, harian). Dipakai sebagai pemicu sesuatu yang
sedang ditatap orang, itu salah tempat.

### Perbaikannya

Orang yang menunggu **sudah tersambung** lewat `GET /chat-threads/{id}/stream`.
Sekarang stream itu sekalian menjalankan worker antreannya sendiri — worker yang
sama persis dengan yang dipanggil scheduler, cuma pemicunya orang yang menunggu,
bukan jadwal. Lihat `ChatStreamController::runQueuedWorkInline()`.

Tiga pengaman:

- **Urutan**: `thinking` dikirim & di-flush dulu, baru worker jalan. Kalau
  dibalik, layar diam beberapa detik tanpa tanda apa pun.
- **Lock cache**: hanya SATU stream menjalankan worker pada satu waktu. Tanpa
  itu, sepuluh user membuka chat = sepuluh proses worker, berat untuk shared
  hosting. Yang tidak kebagian lock cukup lanjut memantau — worker yang sedang
  jalan menghabiskan seluruh antrean.
- **Worker gagal tidak mematikan stream**: dibungkus try/catch, dicatat ke
  channel `ai`. Ada test yang memastikan event penutup `retry` tetap terkirim,
  karena tanpa itu klien kehilangan kursor `after` dan reconnect-nya kacau.

Dilewati kalau driver antrean `sync` (job sudah jalan saat dispatch, jadi
memanggil `queue:work` cuma buang waktu — efeknya test SSE dua kali lebih cepat).
Bisa dimatikan lewat `AMINA_SSE_INLINE_WORKER=false`.

**Cron tetap dipasang**, tapi turun peran jadi cadangan: untuk job yang tidak ada
lagi yang menungguinya, misalnya user keburu menutup tab. Untuk itu `*/8` sudah
memadai.

### Diverifikasi nyata, bukan cuma test

Worker cron dimatikan total, satu job dibiarkan menggantung di antrean, lalu
stream dibuka. Hasilnya: `ai_provider_errors` bertambah dengan HTTP **401
`invalid x-api-key`** pada detik yang sama stream berjalan — artinya request
benar-benar sampai ke server provider dalam hitungan detik, tanpa cron. Yang
tersisa cuma kunci API yang sah.

---

## 9. Amina lebih cepat, faktual, dan fokus keuangan (4 September 2026)

### Konfigurasi & performa lokal

- Integrasi lokal diuji lewat endpoint 9Router OpenAI-compatible dengan model
  `amana` (router/owner `combo`). Kredensial tetap hanya ada di `.env` lokal
  dan baris `llm_settings` terenkripsi; tidak ada key yang masuk Git.
- Output OpenAI-compatible sekarang dibatasi lewat `LLM_MAX_TOKENS` (default
  768), sesuai gaya jawaban Amina yang hanya 1-2 kalimat.
- Tes langsung tool calling model `amana` berhasil menjawab saldo dari payload
  tool dalam sekitar 2,85 detik.

Kelambatan aplikasi lokal ternyata bukan terutama dari login. SSE chat dahulu
dibuka terus selama sekitar 20 detik walaupun tidak ada balasan yang ditunggu.
PHP development server di Windows hanya melayani satu request pada satu waktu,
sehingga stream itu menahan request login, navigasi, dan query halaman lain.

Frontend sekarang hanya membuka SSE setelah pesan user sudah diterima server
dan masih belum memiliki balasan. Stream langsung dibatalkan setelah menerima
pesan/error atau saat halaman Chat ditinggalkan. Login API lokal setelah
perubahan terukur sekitar 866 ms dan pemuatan family sekitar 223 ms.

### Feedback chat

- Percakapan awal menampilkan skeleton yang menyerupai bubble chat.
- Pesan optimistic menampilkan status `Mengirim...`.
- Pesan yang sudah diterima server menampilkan centang ganda dan `Dibaca Amina`.
- Selama menunggu LLM, indikator titik dilengkapi teks
  `Amina sedang menyiapkan jawaban`.
- Tombol kirim dinonaktifkan selama request pengiriman masih berjalan agar
  pesan tidak terkirim ganda.

### Otak dan akses data Amina

Sebelumnya Amina hanya selalu menerima katalog nama entitas dan ringkasan kas
bulan berjalan. Pertanyaan tentang saldo, transaksi, target, jadwal rutin, atau
langganan belum punya sumber data lengkap dan berisiko dijawab terlalu umum.

Sekarang `FamilyFinancialData` menjadi gateway baca tunggal untuk AI. Tool
`get_family_financial_data` mengambil data **hanya ketika diperlukan**, meliputi:

- saldo akun aktif;
- progres dan sisa target tabungan;
- transaksi terbaru atau transaksi pada bulan/jenis tertentu;
- transaksi rutin aktif;
- profil dan anggota keluarga;
- status langganan.

Ringkasan arus kas, wallet, dan sumber pemasukan tetap melalui
`get_financial_summary`. Setiap root query tool AI memakai filter `family_id`
eksplisit karena job queue tidak melewati middleware `ResolveFamily`. Lookup
nama relasi juga dibangun hanya dari family yang sama, sehingga data family
lain tidak ikut ke prompt walaupun terdapat data referensi yang buruk.

Persona Amina kini berperan sebagai asisten keuangan rumah tangga: membantu
arus kas, budget, dana rutin/darurat, dan target tabungan secara realistis dan
tidak menghakimi. Amina wajib membedakan fakta aplikasi, perkiraan hasil
hitungan, dan saran; jika data tidak tersedia, ia harus mengakuinya atau
bertanya satu hal, bukan mengarang.

### Batas topik

Amina hanya menjawab keuangan keluarga/pribadi, fitur AmanaFinance, serta
pengetahuan ekonomi yang berdampak nyata pada keputusan keluarga (inflasi,
suku bunga, cicilan, pajak, harga kebutuhan, atau nilai tukar). Pertanyaan
random seperti sejarah, geografi, hiburan, olahraga, coding, resep, atau
kesehatan ditolak halus dalam satu kalimat tanpa menjawab isi pertanyaan dan
tanpa memanggil tool.

Perilaku ini diuji langsung pada model `amana`: pertanyaan ibu kota Prancis
ditolak, sedangkan dampak inflasi terhadap belanja keluarga tetap dijawab dari
sudut keuangan.

### Verifikasi

- 244 test backend lulus dengan 860 assertion.
- Test baru mencakup data akun/target/transaksi/rutin/langganan/profil,
  read-only tool, aturan anti-halusinasi, batas topik, dan isolasi antar-family.
- Laravel Pint, ESLint, TypeScript, dan build static Next.js lulus.
- Environment test diisolasi dari konfigurasi LLM lokal melalui `phpunit.xml`,
  sehingga key/provider developer tidak mengubah hasil test.

## 10. Redesign frontend dan mode manual (4 September 2026)

Redesign awalnya dikerjakan aman di branch `redesign/frontend-v2`, lalu setelah
disetujui di-fast-forward ke `main` dan menjadi frontend aktif. Riwayat frontend
lama tetap dapat dipulihkan dari commit sebelum merge (`a177348`). Arah visual
baru memakai gaya minimal modern yang lebih hangat: midnight plum sebagai warna utama, latar ivory, lalu aksen
champagne, coral, mint, dan biru untuk memberi energi tanpa membuat layar
ramai. Hero memakai tekstur satin geometris dari CSS agar terasa lebih mewah
tanpa menambah aset gambar atau beban unduhan.

Perubahan utama:

- navigasi menonjolkan Beranda, Transaksi, Amina, Anggaran, dan Lainnya;
- dashboard memiliki aksi cepat, ringkasan arus kas berwarna, serta akses jelas
  ke pengelolaan transaksi;
- tersedia halaman `/transactions` untuk tambah, ubah, dan hapus transaksi
  secara manual;
- halaman akun, sumber pemasukan, wallet/anggaran, dan target tetap memiliki
  CRUD manual lengkap;
- layar Amina selalu menampilkan pintasan manual, dan ketika request AI/SSE
  gagal pengguna diarahkan ke pencatatan manual tanpa kehilangan akses app;
- komponen header, empty state, kartu, warna, jarak, sidebar desktop, dan bottom
  navigation mobile diselaraskan dengan sistem visual baru.

Frontend berhasil melewati ESLint, TypeScript, dan static production build,
termasuk route baru `/transactions`. Pemeriksaan browser otomatis belum dapat
dijalankan karena runtime Tabbit lokal gagal membuat tab; build dan pemeriksaan
statis tetap berhasil.

## 11. Stabilitas layout mobile dan performa staging (5 September 2026)

- Class tekstur hero yang sempat salah menempel pada avatar dipindahkan ke kartu
  Total Saldo sehingga background hero selalu tampil.
- Bottom navigation dibuat fixed dengan z-index eksplisit, safe-area perangkat,
  lebar adaptif, dan proteksi overflow horizontal.
- Chat memakai tinggi `100dvh`; hanya daftar pesan yang bergulir, sedangkan
  header, quick prompts, dan composer tetap berada di dalam viewport. Composer
  selalu berhenti di atas bottom navigation.
- Cache TanStack Query dinaikkan menjadi dua menit. Mutasi tetap langsung
  menginvalidasi data terkait, tetapi perpindahan halaman tidak meminta ulang
  data yang sama secara berlebihan.
- SSE kini menutup koneksi segera setelah pesan, kartu aksi, atau error terkirim,
  sehingga proses PHP shared hosting tidak tertahan sampai deadline 20 detik.
- Deploy membangun `config`, `route`, dan `view` cache Laravel setelah clear,
  bukan hanya menghapus cache seperti sebelumnya.

### Perbaikan khusus mobile

- Root shell memakai `100dvh` sebagai pelengkap `100vh`, sehingga tinggi layout
  mengikuti address bar dan keyboard virtual browser mobile.
- Bottom navigation memiliki `z-index` tetap, lebar maksimum satu viewport,
  tab yang fleksibel, dan padding `safe-area` untuk perangkat ber-notch.
- Shell memotong overflow horizontal agar navigation bar tidak terdorong keluar
  layar saat konten lebar atau viewport berubah.
- Composer chat bersifat sticky, memiliki background blur dan shadow pemisah,
  serta ukuran tombol yang dipadatkan pada layar sempit.
- Panel bantuan manual disembunyikan pada layar sangat kecil agar ruang pesan
  dan input tidak terjepit; jalur manual tetap tersedia melalui bottom nav.

### 9Router dan deployment staging

- 9Router di VPS berjalan melalui PM2 pada port internal `20127`. NAT provider
  memetakan endpoint publik `103.168.148.18:20128` ke port internal tersebut;
  keduanya tidak boleh disamakan tanpa mengubah aturan NAT.
- Open Tunnel bawaan 9Router diaktifkan melalui klien lokal karena endpoint
  aktivasinya menolak browser publik dengan pesan `Local only: CLI token required`.
- Default target tunnel disesuaikan ke port internal `20127`. Endpoint tunnel
  stabil yang dipakai AmanaFinance adalah `https://r62dmm3.abc-tunnel.us/v1`.
- Konfigurasi staging memakai provider `openai_compatible` dan model combo
  `amana`. Uji end-to-end menghasilkan balasan berbasis data keluarga.
- Staging dijalankan dengan `APP_ENV=production`, `APP_DEBUG=false`, serta cache
  konfigurasi, route, dan view aktif.
- Commit `b081fc3` sudah di-push ke `main` dan di-deploy ke Domainesia. Health
  check dashboard, chat, login, admin login, dan OpenAPI semuanya memberi 200.
- Verifikasi terakhir: halaman dashboard sekitar 188 ms, halaman chat 28 ms,
  dan satu balasan Amina end-to-end sekitar 7,3 detik.
