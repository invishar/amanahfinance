# TaskProject — Integrasi `amanafinance-web` ↔ `amanafinance-api`

Status dokumen: **aktif** — Fase 0–7 selesai, Fase 8 sebagian. Terakhir diperbarui: 11 Agustus 2026.
Aturan main ada di [CLAUDE.md](CLAUDE.md); spesifikasi visual di [template/README.md](template/README.md).

## 1. Tujuan

UI seluruh layar sudah jadi dan berjalan di atas data mock. Sasaran pekerjaan ini: **mengganti mock dengan API sungguhan** tanpa mengubah tampilan, lalu menghapus seluruh lapisan mock.

- Base URL dev: `http://127.0.0.1:8000/api/v1` (env `NEXT_PUBLIC_API_URL`)
- Swagger: `http://127.0.0.1:8000/` · spec: `/api/v1/openapi.json`
- Auth: Laravel Sanctum, `Authorization: Bearer <token>`
- Envelope: list → `{ data, links, meta }` (`?page=`), single → `{ data }`
- Uang: `integer`; ID: `uuid`

## 2. Hasil verifikasi API (sudah dites langsung, bukan asumsi)

| Yang dites | Hasil |
| --- | --- |
| `POST /auth/register` → `{ data: { user, token } }` | ✅ jalan |
| `GET /auth/me`, `POST /auth/logout` | ✅ jalan |
| `GET /families` untuk user baru | ✅ `{ data: [] }` — dipakai untuk memutuskan perlu onboarding atau tidak |
| Endpoint ber-family tanpa family | ✅ `403 {"message":"Akun ini belum tergabung dalam family manapun."}` |
| `POST /families` → family + `onboarding_done: false` | ✅ jalan, pembuat otomatis jadi `admin` di `/family-members` |
| CRUD `wallets` | ✅ `201`, envelope `{ data }` |
| `GET /analytics/summary?month=YYYY-MM` | ✅ mengembalikan `cashflow` + `wallets[] {budget, spent, remaining, percent, status}` |
| `422` | ✅ `{ message, errors: { field: [pesan] } }` |
| `POST /chat-threads` + `POST /chat-threads/{id}/messages` | ⚠️ pesan user tersimpan, **tidak ada balasan asisten dan tidak ada `ai_actions`** |

### Blocker (butuh backend, bukan pekerjaan frontend)

> Daftar lengkap permintaan ke backend — beserta usulan kontraknya — ada di [CatatanBackend.md](CatatanBackend.md).

1. **Balasan Amina belum ada.** `POST /chat-threads/{id}/messages` hanya menyimpan pesan user lalu balik `201` seketika. Tidak ada pesan `role: assistant`, tidak ada baris `ai_actions`, tidak ada SSE/streaming di spec.
2. **Tidak ada endpoint confirm/reject `ai_actions`.** Spec cuma punya `GET /ai-actions` dan `GET /ai-actions/{id}`. Tombol "Ya, lanjutkan" tidak punya lawan bicara. **Jangan diakali dengan `POST /transactions` langsung dari layar chat** — melanggar aturan 6 di CLAUDE.md.
3. **Tidak ada `GET /dashboard`.** Dashboard dirakit dari beberapa endpoint (lihat Fase 5).
4. **Pesan validasi belum diterjemahkan** — `422` mengembalikan kunci mentah (`"validation.required"`), bukan kalimat untuk pengguna. Sementara dipetakan di klien; minta backend mengirim pesan jadi.
5. **`GET /transactions` tidak punya filter** (hanya `?page=`), `per_page` 20 dan tidak bisa diatur. "8 transaksi terbaru" diambil dari halaman pertama; perlu kepastian urutannya `transaction_date desc`.

### Keputusan yang diambil

- **Penyimpanan token**: `localStorage` + memori (SPA, tanpa SSR data fetching). Sanctum tidak punya refresh token. Konsekuensi: rentan XSS — kalau nanti butuh SSR/cookie `httpOnly`, itu pekerjaan terpisah dan ditulis sebagai follow-up, bukan dikerjakan diam-diam.
- **Chat**: blocker #1 & #2 sudah beres (backend membalas lewat SSE, endpoint confirm/reject `ai_actions` sudah ada) — kirim/muat pesan, balasan Amina, dan action card semuanya **lewat API sungguhan**. `lib/mock/assistant.ts` + flag `NEXT_PUBLIC_MOCK_AMINA` sudah dihapus.
- **Satu family per sesi**: API tidak punya header `X-Family-Id`; scope ikut token. Family aktif = `data[0]` dari `GET /families`. Pemilih family multi-keluarga = follow-up.

### Beda kontrak vs desain yang mengubah UI

| Layar | Perubahan yang wajib dilakukan |
| --- | --- |
| Akun (dialog) | `PUT /accounts` **tidak menerima saldo**. Field "Saldo (Rp)" hanya muncul saat tambah (`opening_balance`); saat edit disembunyikan — `current_balance` dihitung server dari transaksi |
| Target (dialog) | Tidak ada `current_amount` di `POST`/`PUT`. Field "Sudah terkumpul" **dihapus** dari form; nilainya read-only dari server |
| Target (kartu) | `percent` dari server; "Estimasi tercapai" tidak ada di API → sembunyikan sampai backend menyediakannya |
| Wallets | `status` (`ok\|warning\|over\|no_budget`) & `spent` dari `analytics/summary`, bukan hitungan klien |
| Keluarga | `POST /family-invites` butuh `email` **atau** `phone` + `role`. Tombol "Undang Anggota Keluarga" harus membuka form kecil, tidak bisa langsung memunculkan kode. Kode undangan = `token` dari response |
| Semua form | `full_name` (bukan `name`) untuk user; wallet/account/income unik per family → tangani `422` duplikat |

## 3. Rencana kerja

### Fase 0 — Fondasi ✅

- [x] `.env.local` berisi `NEXT_PUBLIC_API_URL`
- [x] `@tanstack/react-query` + `openapi-typescript` (devDependency)
- [x] `npm run api:types` → `lib/api/schema.d.ts` (di-commit)
- [x] `lib/api/client.ts` — satu-satunya `fetch`: base URL, bearer, unwrap `{ data }`, `ApiError { status, message, fieldErrors }`
- [x] `lib/api/keys.ts` — query key ber-`familyId`
- [x] `app/providers.tsx` — `QueryClientProvider` (retry mati untuk 4xx)

### Fase 1 — Auth ✅

- [x] `lib/token-store.ts` + `lib/auth.tsx` — token via `useSyncExternalStore`
- [x] Login & Register pakai API; `422` → error per-field, kredensial salah → satu baris di atas tombol
- [x] Guard `components/require-session.tsx`: tanpa token → `/login`; `GET /families` kosong → `/onboarding`
- [x] `401` global → bersihkan sesi + `/login`
- [x] "Keluar" → `POST /auth/logout` + `queryClient.clear()`
- [x] Diuji: register → onboarding → CRUD → logout → login ulang → mendarat di `/chat`

> Catatan implementasi: token **dibaca langsung dari store tiap request**, bukan dicermin ke variabel modul lewat `useEffect`. Versi pertama memakai effect dan menyebabkan request pertama setelah reload berjalan tanpa header → `401` palsu → sesi terhapus sendiri.

### Fase 2 — Onboarding ✅

- [x] "Buat baru" → `POST /families`; "Gabung keluarga" → `POST /family-invites/accept`
- [x] Invalidasi `families` lalu arahkan ke `/chat`
- [x] `422` → pesan di bawah tombol

### Fase 3 — Master data (CRUD) ✅

- [x] `wallets`, `accounts`, `income-sources`, `savings-goals` — list/create/update/delete
- [x] `lib/entity-forms.ts` — pemetaan field desain → field API, termasuk field khusus create
- [x] `CrudDialog` pakai mutation, tombol disabled saat pending, `422` per-field
- [x] Hapus pakai dialog konfirmasi bertoken desain (`components/confirm-dialog.tsx`), `409` → pesan yang bisa dibaca
- [x] Saldo akun hanya saat create (`opening_balance`) + keterangan kenapa
- [x] Field "Sudah terkumpul" dibuang dari form target

### Fase 4 — Analisa ✅

- [x] Kartu Pemasukan/Pengeluaran/Selisih dari `cashflow`
- [x] Breakdown per wallet dari `analytics.wallets[]`
- [x] Seksi "Wawasan dari Amina" disembunyikan (belum ada endpoint insight)

### Fase 5 — Dashboard ✅

- [x] Dirakit dari `/accounts`, `/analytics/summary`, `/savings-goals`, `/transactions`
- [x] Skeleton per kartu mengikuti status query masing-masing
- [x] Empty state per seksi

### Fase 6 — Chat

- [x] Ambil thread `GET /chat-threads`, buat otomatis bila kosong
- [x] Riwayat `GET /chat-threads/{id}/messages`
- [x] Kirim pesan `POST .../messages` dengan optimistic UI + rollback saat gagal
- [x] Balasan asisten dari server lewat SSE `GET /chat-threads/{id}/stream` (blocker #1 beres)
- [x] Confirm/reject `ai_actions` + tombol "Edit" (blocker #2 beres) — `AiActionCard` menampilkan "Sudah disimpan"/"Dibatalkan"/error validasi per-field
- [x] Wawancara awal dari server (`ChatThread.onboarding` + `/onboarding-answers`)
- [ ] **Blocked**: unggah struk & suara (belum ada UI upload — endpoint `POST /uploads` sudah ada di backend, tombol kamera/mic di layar chat masih placeholder teks)

### Fase 7 — Pengaturan keluarga ✅

- [x] Nama keluarga dari `GET /families`
- [x] Anggota dari `GET /family-members` + tag peran
- [x] Undang: form email + peran → `POST /family-invites` → tampilkan token + tombol salin

### Fase 8 — Bersih-bersih

- [x] Hapus `lib/mock/data.ts`, `lib/mock/derive.ts`, `lib/types.ts`, `lib/store.tsx`
- [x] `lib/selectors.ts` tinggal pemetaan label
- [x] State klien pindah ke `lib/ui-store.tsx` (modal, sheet, draft)
- [x] `npx next build` + `npx eslint .` bersih; alur utama dicek di browser (mobile 414px & desktop 1280px)
- [x] Hapus `lib/mock/assistant.ts` + flag `NEXT_PUBLIC_MOCK_AMINA` (blocker #1 & #2 beres)

## 4. Definition of done

1. Tidak ada `fetch` di luar `lib/api/`.
2. Tidak ada angka turunan yang dihitung di klien (spent, percent, status, total).
3. Tidak ada `lib/mock/*` tersisa di repo — seluruh layar (termasuk chat) memakai data sungguhan.
4. `422`/`401`/`403`/`409` punya tampilan masing-masing di layar yang relevan.
5. Tampilan tetap sama persis dengan `template/README.md` (kecuali perubahan di tabel §2 yang memang dipaksa kontrak API).
6. Build & lint bersih; alur register → onboarding → CRUD → dashboard jalan di backend lokal.

## 5. Follow-up (di luar cakupan sekarang)

- Token di cookie `httpOnly` + SSR bila nanti butuh SEO/proteksi lebih ketat
- Pemilih family untuk user dengan lebih dari satu keluarga
- Endpoint unggah struk & speech-to-text
- Pesan validasi `422` yang sudah diterjemahkan dari backend
- Layar untuk `recurring-rules`, `notifications`, `audit-logs`, `llm-settings` (ada di API, belum ada desainnya)
