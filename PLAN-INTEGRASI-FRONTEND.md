# Plan — Tindak Lanjut Catatan Integrasi `amanafinance-web`

Dibuat 13 Agustus 2026, berdasarkan `CatatanBackend.md` (ditulis tim FE 12 Agustus 2026)
yang di-cross-check ulang terhadap kode di commit `1c33638`. Beberapa item di catatan
asli **sudah selesai dikerjakan** setelah catatan itu ditulis — dokumen ini adalah versi
yang sudah disinkronkan dengan kode aktual, dipakai sebagai rujukan progres ke depan.

Update status tiap item di sini begitu dikerjakan/diverifikasi. Jangan hapus item yang
selesai — pindahkan ke bagian "Selesai" dengan tanggal & commit terkait supaya riwayat
keputusan tetap terlacak.

---

## Sudah selesai (perlu dikonfirmasi ke FE)

| # | Item | Bukti di kode | Catatan selisih dari usulan FE |
| --- | --- | --- | --- |
| 2.1 | Balasan Amina — dipilih **opsi C (SSE)** | [ChatStreamController.php](app/Http/Controllers/Api/ChatStreamController.php), [ProcessAssistantMessage.php](app/Jobs/ProcessAssistantMessage.php), didokumentasikan `OpenApiSpec.php` | Event: `thinking`, `message`, `action_card`, `error`, `retry`. **Tidak ada** `token`/`done` terpisah (LLM tidak streaming token-by-token; `message`/`error` = sinyal selesai) — lihat detail di "Selaraskan kontrak SSE" di bawah. |
| 2.2 | `POST /ai-actions/{id}/confirm` & `/reject` | [AiActionController.php](app/Http/Controllers/Api/AiActionController.php), [ConfirmAiAction.php](app/Actions/AiActions/ConfirmAiAction.php) | Aksi yang statusnya sudah bukan `pending` membalas **`422`**, bukan `409` seperti usulan FE. Kabari perbedaan ini. |
| 5.1 | `DELETE` pada entitas yang masih dipakai → `409` | [DeletesSafely.php](app/Support/DeletesSafely.php), `ConflictException` | Terverifikasi: pesan sudah Bahasa Indonesia siap tampil, mis. "Wallet ini masih dipakai oleh transaksi yang ada. Arsipkan alih-alih menghapus." |

**Tindakan:** kabari FE supaya flag `NEXT_PUBLIC_MOCK_AMINA` bisa dilepas dan tombol
confirm/reject di action card disambungkan — tidak perlu menunggu item lain di bawah.

---

## P0 — Blocker produk (chat & AI)

- [x] **2.3 — Endpoint unggah (struk & suara)** *(selesai 13 Agustus 2026)*
  `POST /uploads` (multipart) → `{ data: { url, mime, size } }`. Hanya menyimpan berkas
  ke disk `public` (`uploads/{family_id}/{uuid}.ext`) — **tidak** melakukan OCR/STT,
  itu tetap keputusan produk terpisah yang belum diambil. Batas ukuran & mime lewat
  `config('amina.uploads')` (`AMINA_UPLOAD_MAX_KB`, default 15 MB). Role minimum
  `member` (sama seperti kirim chat message). Lihat
  [UploadController.php](app/Http/Controllers/Api/UploadController.php),
  [UploadActions.php](app/Actions/Uploads/UploadActions.php),
  [UploadTest.php](tests/Feature/UploadTest.php). Didokumentasikan di `API-v1.md`
  (bagian "Uploads") dan `openapi.json`.

- [x] **2.4 — Wawancara onboarding dikirim dari server** *(selesai 13 Agustus 2026)*
  `FamilyActions::create()` sekarang memicu `OnboardingConversationActions::start()`
  yang membuat `ChatThread kind=onboarding` + satu `ChatMessage` (sapaan + pertanyaan
  pertama dari `config('amina.onboarding_questions')`, yang sudah ada duluan di
  config sejak sebelum sesi ini). Tiap `POST /onboarding-answers` (termasuk
  `skipped=true`) memicu `OnboardingConversationActions::advance()`: menyisipkan
  pertanyaan berikutnya sebagai `ChatMessage` baru, atau — kalau sudah tidak ada
  pertanyaan tersisa — menandai `families.onboarding_done = true` sendiri (menjawab
  item P2 "siapa yang menyalakan onboarding_done" sekaligus). `ChatThreadResource`
  sekarang menyertakan `onboarding: { step, total, done }` untuk thread
  `kind=onboarding` (null untuk `kind=general`). Lihat
  [OnboardingConversationActions.php](app/Actions/Chat/OnboardingConversationActions.php).
  Field naskah pertanyaan itu sendiri sudah lama ada di
  [config/amina.php](config/amina.php) — bagian ini murni soal *wiring*-nya ke
  `ChatThread`/`ChatMessage`, bukan menulis naskah baru.

- [x] **Selaraskan kontrak SSE** *(selesai 13 Agustus 2026)*
  Ditambahkan `thinking` (sekali di awal koneksi/reconnect kalau pesan terakhir masih
  `role=user` yang belum dibalas) dan `error` (`role=system`, ditulis
  `AssistantService::fail()` via `ProcessAssistantMessage::failed()` saat job LLM habis
  retry). **Tidak** ditambahkan `token`/`done` terpisah — LLM dipanggil sekali per job
  (bukan streaming token-by-token), jadi tidak ada teks parsial untuk direlay, dan
  `message`/`error` sendiri sudah jadi sinyal selesainya giliran. Kabari FE soal
  penyesuaian ini dari desain awal mereka (`thinking → token* → action_card → done|error`).
  Lihat [ChatStreamController.php](app/Http/Controllers/Api/ChatStreamController.php),
  [AssistantService.php](app/Services/Ai/AssistantService.php) (`fail()`),
  [ProcessAssistantMessage.php](app/Jobs/ProcessAssistantMessage.php) (`failed()`).

---

## P1 — Menghambat fitur yang sudah didesain

- [x] **3.1 — Pesan validasi Bahasa Indonesia** *(selesai 13 Agustus 2026)*
  Ditambahkan [lang/id/validation.php](lang/id/validation.php) (semua rule Laravel +
  peta `attributes` untuk semua field yang dipakai FormRequest di app ini, huruf awal
  kapital karena hampir semua template pesan menaruh `:attribute` di kata pertama —
  cocok dengan contoh persis FE: `"Nama wajib diisi."`) dan
  [lang/id/pagination.php](lang/id/pagination.php) (sekalian menjawab item P2
  `meta.links[].label`). **Tidak** ditambahkan penanganan `Accept-Language` — app ini
  satu-locale (`config('app.locale')` sudah `id` by default), jadi tidak perlu content
  negotiation. Sudut kasar yang belum ditangani: kalau >1 field gagal sekaligus,
  `message` di level atas menambahkan akhiran `"(and N more errors)"` dalam Bahasa
  Inggris (quirk internal `ValidationException::summarize()`) — `errors` per field tetap
  100% Indonesia. FE bisa hapus `translateValidation()` di `lib/api/client.ts` sekarang.

- [x] **3.2 — Filter & urutan stabil `GET /transactions`** *(selesai 13 Agustus 2026)*
  Ditambahkan `?month=YYYY-MM`, `?wallet_id=`, `?account_id=`, `?type=`, `?per_page=`
  (default 20, maks 100, semua opsional & bisa digabung) lewat
  [IndexTransactionRequest.php](app/Http/Requests/IndexTransactionRequest.php) +
  `TransactionActions::index()`. Urutan baku sekarang
  `transaction_date desc, created_at desc` (menjawab §5.2 catatan asli sekaligus).
  Catatan: `?account_id=` hanya mencocokkan `account_id` (sisi asal) — transaksi
  `transfer` yang masuk ke akun itu lewat `to_account_id` tidak ikut terfilter;
  didokumentasikan eksplisit di `API-v1.md` supaya FE tidak kaget.

- [x] **3.3 — Realisasi per sumber pemasukan** *(selesai 13 Agustus 2026)*
  Ditambahkan `income_sources[]` di `GET /analytics/summary` dengan
  `{ source_id, name, expected, actual }`, mengikuti pola `wallets[]` yang sudah ada —
  termasuk view baru `v_income_source_month`
  ([migration](database/migrations/2026_08_13_170000_create_income_source_month_view.php))
  supaya konsisten dengan aturan "dashboard/analitik pakai view, bukan query ad-hoc"
  (CLAUDE.md). `expected` sengaja `null` (bukan `0`) kalau `expected_amount` belum diisi
  — beda makna dari "diperkirakan tidak ada pemasukan".

- [x] **3.4a — ETA savings goal** *(selesai 13 Agustus 2026)*
  Ditambahkan `eta` (`YYYY-MM-DD`, awal bulan) di `SavingsGoalResource`, diproyeksikan
  server secara linear dari rata-rata kontribusi bulanan sejak setoran pertama ke goal
  itu. `null` kalau `status` bukan `active`, sudah tercapai, atau belum ada histori
  setoran. Lihat [SavingsGoalResource.php](app/Http/Resources/SavingsGoalResource.php).
  (Bug kecil ketemu & diperbaiki di jalan: `Carbon::diffInMonths()` di versi ini
  mengembalikan float, bukan int, jadi harus di-cast eksplisit atau elapsed-months
  membengkak +1 bulan.)

- [ ] **3.4b — Insights (kartu "Wawasan dari Amina")** *(sengaja belum dikerjakan)*
  **Beda kategori dari item P1 lain di atas** — bukan cuma nulis field baru di response,
  tapi butuh infrastruktur baru: job harian terjadwal + pemanggilan LLM +
  tempat nyimpan hasilnya (CLAUDE.md sendiri sudah mencatat ini di "Belum
  diimplementasikan": *"Job harian yang mengisi insight naratif untuk
  GET /analytics/summary"*, dan aturan #6 tegas: **jangan** panggil LLM saat request
  `analytics/summary` — harus di-cache job terpisah). Mengerjakan ini asal-asalan (mis.
  panggil LLM sinkron di request) melanggar aturan itu. Perlu keputusan/scoping terpisah
  sebelum dikerjakan: jadwal job (harian? per keluarga?), skema penyimpanan hasil (tabel
  baru?), dan berapa insight per request. Tidak dihitung selesai di batch P1 ini.

- [x] **3.6 — `password_confirmation` di register** *(selesai 13 Agustus 2026)*
  Dibuat opsional (`Rule::when($this->filled('password_confirmation'), ['confirmed'])`
  di [RegisterRequest.php](app/Http/Requests/RegisterRequest.php)) — kalau tidak
  dikirim, tidak diwajibkan; kalau dikirim, tetap harus cocok dengan `password`. Cocok
  dengan desain FE yang cuma punya satu field sandi, tanpa perlu FE menambah field
  "Ulangi kata sandi".

---

## Butuh keputusan produk (bukan langsung kerja teknis)

- [ ] **3.5 — Cara koreksi saldo akun**
  `PUT /accounts/{id}` sengaja tidak menerima field saldo (komentar di
  `UpdateAccountRequest.php`: ledger transaksi adalah satu-satunya source of truth).
  Yang belum diputuskan: bagaimana user mengoreksi saldo yang meleset — tipe transaksi
  `adjustment` baru, atau endpoint rekonsiliasi terpisah?

---

## P2 — Konsistensi & kenyamanan (bisa dicicil, tidak menghalangi rilis)

- [ ] Login gagal balas `422`, sebaiknya `401` untuk kredensial salah.
- [x] ~~`meta.links[].label` juga kunci mentah~~ — **selesai bareng 3.1**:
  [lang/id/pagination.php](lang/id/pagination.php) ("Sebelumnya"/"Berikutnya").
- [ ] Tidak ada filter di `/savings-goals`, `/chat-threads`, `/family-members` (hanya
  `?page=`).
- [ ] Mekanisme pilih family aktif untuk user dengan >1 family (endpoint "set active
  family" atau `?family_id=`) — FE sementara pakai `data[0]` dari `GET /families`.
- [x] ~~Siapa yang menyalakan `Family.onboarding_done`~~ — **selesai bareng 2.4**: server
  (`OnboardingConversationActions::advance()`) yang menyalakannya otomatis begitu
  pertanyaan terakhir terjawab. `PUT /families/{family}` masih bisa override manual
  kalau admin perlu reset, tapi FE tidak perlu menyentuhnya sama sekali.
- [ ] Kejelasan scope rilis untuk entitas tanpa layar: `recurring-rules`, `notifications`,
  `audit-logs`, `llm-settings`.

---

## Urutan pengerjaan yang disarankan

1. Kabari FE soal item yang **sudah selesai** + selisih kontrak (SSE events, `422` vs
   `409` di confirm, endpoint `/uploads` baru, `onboarding` field di `ChatThread`,
   pesan error sekarang Bahasa Indonesia, filter `/transactions`, `income_sources[]` &
   `eta` baru, `password_confirmation` opsional) — mereka bisa langsung sambungkan
   tanpa menunggu.
2. ~~Kerjakan 2.3 (upload) dan 2.4 (onboarding server-driven)~~ — **selesai**.
3. ~~Sapu P1 (3.1–3.4a, 3.6)~~ — **selesai**. 3.4b (insights) sengaja tidak ikut, lihat
   catatan di bagian P1 — butuh scoping job harian + LLM terpisah, bukan quick fix.
4. Tunggu keputusan produk untuk 3.5 (dan scoping 3.4b) sebelum mulai coding.
5. P2 dicicil kapan saja (2 dari 6 item sudah kebetulan selesai lewat kerjaan di atas).
