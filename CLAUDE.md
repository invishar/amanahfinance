# CLAUDE.md — amanahfinance

Backend AmanaFinance (Laravel 11 + PHP 8.4 + MySQL/MariaDB) **dan** klien (`frontend/`,
Next.js static export, lihat [`frontend/CLAUDE.md`](frontend/CLAUDE.md)) dalam satu repo.
Repo ini memegang database, aturan bisnis, dan **seluruh** integrasi AI — sisi klien
murni render, tidak ada logika bisnis atau kunci API LLM di `frontend/`.

**Target deploy: hPanel (shared hosting).** Tidak ada proses persisten, tidak ada `pcntl`/`posix`, kemungkinan besar tidak ada Redis. Jangan desain fitur yang butuh worker daemon, Horizon, atau koneksi long-lived tanpa batas waktu — lihat "Perintah" dan "Alur AI" di bawah untuk pola yang sudah disesuaikan (cron + burst worker, SSE berumur pendek dengan reconnect).

Dokumen wajib baca sebelum menulis kode:
- `API-v1.md` — kontrak endpoint, **sumber kebenaran tunggal**
- `README.md` — arsitektur, struktur folder, job terjadwal
- `database/schema.sql` — skema referensi dari desain

## Aturan yang tidak boleh dilanggar

1. **Uang = `bigInteger` rupiah penuh.** Tidak ada `float`, `double`, atau `decimal` di mana pun — termasuk di cast model, migrasi, dan response.
2. **Semua id UUID** (`HasUuids`). Jangan pakai auto-increment.
3. **Multi-tenant per `family_id`.** `family_id` diambil dari membership user terautentikasi, **tidak pernah** dari body request. Header `X-Family-Id` hanya memilih di antara family milik user itu sendiri.
4. **`transactions` adalah sumber kebenaran.** `accounts.current_balance` dan `savings_goals.current_amount` hanya cache — perbarui di dalam transaksi DB yang sama.
5. **AI tidak pernah menulis ke tabel bisnis.** LLM hanya menghasilkan `ai_actions` berstatus `pending`. Penulisan nyata hanya terjadi di `ConfirmAiAction`.
6. **Semua perhitungan turunan di server** (spent per wallet, status budget, percent, estimasi target, insight). Klien hanya merender.
7. **Kunci API LLM hanya hidup di repo ini.** Jika satu kunci bocor ke klien, itu bug rilis.

## Konvensi kode

- Controller tipis: **validasi (FormRequest) → Action → API Resource**. Tidak ada query bisnis di controller.
- Otorisasi lewat **Policy**, bukan `if` di controller. Role: `admin` | `member` | `viewer`.
- Enum ditulis sebagai kolom `string`/`text` + `check constraint`, bukan tipe `ENUM` native database. MySQL/MariaDB tidak punya partial index (`WHERE ...`) atau row-level security seperti Postgres — isolasi antar family sepenuhnya di layer aplikasi (lihat aturan #3), bukan di DB.
- Bentuk JSON di Resource harus **persis** seperti `API-v1.md`. Sukses `{ "data": … }`, list `{ "data": [], "meta": {} }`, error `{ "message": …, "errors": {} }`.
- App timezone `Asia/Jakarta`, locale `id` (`config/app.php`, `.env`). Timestamp tetap disimpan & diserialisasikan sebagai ISO-8601, sekarang dengan offset `+07:00` alih-alih `Z`/UTC.
- Semua panggilan LLM / OCR / STT berjalan di **job antrian**, tidak pernah di request web.
- Dashboard & analitik memakai view `v_wallet_month` / `v_cashflow_month`, bukan query ad-hoc.
- Queue pakai driver `database`, bukan `redis` — shared hosting jarang sediakan Redis. Tidak ada worker daemon; job diproses lewat burst singkat yang dipicu scheduler (lihat "Perintah").
- Config LLM (`model`, `key`, `base_url`) selalu lewat `config('services.llm')` / `.env` (`LLM_*`, bukan `ANTHROPIC_*` — nama generik supaya provider bisa ganti) — jangan hardcode model string di kode.

## Constraint transaksi (replikasi di DB *dan* FormRequest)

| `type` | Wajib |
| --- | --- |
| `expense` | `wallet_id` + `account_id` |
| `income` | `source_id` + `account_id` |
| `transfer` | `account_id` + `to_account_id` (harus berbeda) |
| `savings` | `goal_id` + `account_id` |

`amount > 0` selalu. Penghapusan transaksi = soft delete (`deleted_at`).

## Alur AI

Pesan masuk → `AssistantService` → LLM tool calling → **payload disimpan sebagai `ai_actions.pending`** → SSE `action_card` ke klien → user konfirmasi → `ConfirmAiAction` menulis baris nyata dan mengisi `result_table` / `result_id`.

- SSE `action_card` **berumur pendek** (server tutup stream sendiri sebelum ±20-25 detik) dengan auto-reconnect di klien — bukan koneksi yang ditahan tanpa batas, supaya aman dari `max_execution_time` shared hosting.
- Tool tidak menulis apa pun. Satu-satunya tool baca adalah `get_financial_summary`.
- Resolusi nama → id ("gopay" → `accounts.id`) di server, fuzzy match pada data family. Ragu → kosongkan field agar user melengkapi lewat "Edit".
- Konteks prompt: nama family, daftar wallet/akun/sumber pemasukan, ringkasan bulan berjalan, `onboarding_answers`. **Jangan** kirim seluruh riwayat transaksi.
- Naskah pertanyaan onboarding dan sapaan Amina disimpan di server, bukan klien.
- Job yang gagal total (habis `$tries`) menulis `ChatMessage role=system` lewat `AssistantService::fail()` (`ProcessAssistantMessage::failed()`), diteruskan ke klien sebagai event SSE `error` — bukan diam saja.

## Perintah

```bash
php artisan migrate                    # migrasi berurutan 000100 → 001900
php artisan db:seed                    # jalan lewat DatabaseSeeder, urut per dependensi
php artisan test                       # Pest/PHPUnit
php artisan schedule:run                # dipanggil cron hPanel tiap menit (* * * * *)
php artisan queue:work --stop-when-empty --max-time=50   # burst worker, bukan daemon; lihat routes/console.php
php artisan amana:reconcile-balances   # hitung ulang cache saldo
```

Tidak ada `php artisan horizon` — Horizon butuh `pcntl`/`posix` yang tidak tersedia di target deploy (hPanel shared hosting) maupun di dev Windows lokal.

## Testing

- Feature test per endpoint, **wajib termasuk uji kebocoran tenant** (user family A tidak boleh melihat data family B) untuk setiap resource.
- Test constraint transaksi (expense tanpa wallet harus gagal), rekonsiliasi saldo, alur confirm/reject `ai_actions`.
- **LLM selalu di-mock** di test. Golden set kalimat Indonesia sehari-hari → payload yang diharapkan, dijalankan sebagai snapshot test di CI.

## Definition of done sebuah endpoint

1. Migrasi + model + policy
2. FormRequest + Action + API Resource sesuai `API-v1.md`
3. Feature test, termasuk uji kebocoran tenant
4. Terbit di `/api/v1/openapi.json`
5. `API-v1.md` diperbarui **di PR yang sama**

## Jangan

- Jangan menambah endpoint tanpa memperbarui `API-v1.md` di PR yang sama.
- Jangan mengedit saldo akun secara langsung — buat transaksi penyesuaian.
- Jangan memanggil LLM saat request `GET /analytics/summary`; insight di-cache oleh job harian.
- Jangan menghapus baris `ai_actions` — statusnya adalah jejak audit.
