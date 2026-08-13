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
| 2.1 | Balasan Amina — dipilih **opsi C (SSE)** | [ChatStreamController.php](app/Http/Controllers/Api/ChatStreamController.php), [ProcessAssistantMessage.php](app/Jobs/ProcessAssistantMessage.php), didokumentasikan `OpenApiSpec.php:1067-1079` | Event yang ada cuma `message`, `action_card`, `retry` — **tidak ada** `thinking`/`done`/`error` seperti asumsi desain FE. Perlu selaraskan pemicu indikator "sedang mengetik". |
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

- [ ] **Selaraskan kontrak SSE**
  Tambahkan (atau eksplisit putuskan untuk tidak menambahkan) event `thinking`/`done`/
  `error` di `ChatStreamController`, lalu update dokumentasi di `OpenApiSpec.php`.

---

## P1 — Menghambat fitur yang sudah didesain

- [ ] **3.1 — Pesan validasi Bahasa Indonesia**
  Tidak ada folder `lang/id` di repo. `422` masih balas kunci mentah
  (`"validation.required"`). Perlu file terjemahan Laravel + pastikan `Accept-Language`
  ditangani. FE akan hapus `translateValidation()` di `lib/api/client.ts` begitu ini beres.

- [ ] **3.2 — Filter & urutan stabil `GET /transactions`**
  `TransactionController::index()` cuma `orderByDesc('transaction_date')->paginate(20)`,
  tanpa filter dan tanpa tie-breaker. Perlu: `?month=YYYY-MM`, `?wallet_id=`,
  `?account_id=`, `?type=`, `?per_page=`, dan urutan baku
  `transaction_date desc, created_at desc` (menjawab §5.2 catatan asli sekaligus).

- [ ] **3.3 — Realisasi per sumber pemasukan**
  Belum ada di `AnalyticsActions::summary()`. Tambahkan `income_sources[]` dengan
  `{ source_id, name, expected, actual }`, mengikuti pola `wallets[]` yang sudah ada.

- [ ] **3.4 — ETA savings goal & insights**
  Belum ada `eta`/`projected_completion` di `SavingsGoal` (perlu dihitung server dari
  rata-rata kontribusi). Belum ada `insights[]` di `analytics/summary` atau endpoint
  sendiri untuk kartu "Wawasan dari Amina".

- [ ] **3.6 — `password_confirmation` di register**
  `RegisterRequest` masih mewajibkan `'confirmed'`, padahal desain FE cuma punya satu
  field sandi. Putuskan: field dibuat opsional, atau desain FE tambah field "Ulangi
  kata sandi".

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
- [ ] `meta.links[].label` juga kunci mentah (`pagination.previous/next`) — akar masalah
  sama dengan 3.1, kemungkinan selesai otomatis begitu lang file ditambahkan.
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
   `409` di confirm, endpoint `/uploads` baru, `onboarding` field di `ChatThread`) —
   mereka bisa langsung sambungkan tanpa menunggu.
2. ~~Kerjakan 2.3 (upload) dan 2.4 (onboarding server-driven)~~ — **selesai**.
3. Sapu P1 (3.1–3.4, 3.6) — kebanyakan perubahan kecil per file, tidak saling bergantung.
4. Tunggu keputusan produk untuk 3.5 sebelum mulai coding.
5. P2 dicicil kapan saja.
