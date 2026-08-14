@AGENTS.md


# CLAUDE.md — amanafinance-web

Klien AmanaFinance: seluruh antarmuka pengguna. **Tidak ada logika bisnis dan tidak ada kunci API LLM di repo ini.** Semua data datang dari `amanahfinance` (Laravel).

Dokumen wajib baca sebelum menulis kode:
- `template/README.md` — spesifikasi tiap layar (layout, ukuran, warna, perilaku) — **high-fidelity, ikuti persis**. Tabel "Layar → endpoint" di dalamnya **sudah kedaluwarsa**; pakai bagian [Backend](#backend) di bawah.
- OpenAPI: `http://127.0.0.1:8000/api/v1/openapi.json` (Swagger UI: `http://127.0.0.1:8000/`) — kontrak endpoint yang berlaku.
- `app/globals.css` — token desain yang dipakai kode (hasil port dari `template/styles/soft.css`).

## Stack & struktur

Next.js 16 App Router + React 19 + TypeScript. Tailwind v4 ikut terpasang, tapi **desain memakai token CSS + inline style**, bukan utility class — jangan campur gaya penulisan.

```
app/(auth)/{login,register,onboarding}   layar sebelum masuk aplikasi
app/(app)/{chat,dashboard,wallets,accounts,income,goals,analysis,settings}
app/(app)/layout.tsx    -> <AppShell>          app/globals.css  token + kelas komponen
components/app-shell.tsx  sidebar / tab bar / more sheet / modal
components/chat/*         message list, action card
components/{icon,ui,crud-dialog,confirm-dialog,require-session,auth-header}.tsx
lib/api/*          client, schema hasil generate, hooks, query key
lib/{auth,token-store,ui-store,entity-forms,selectors}.ts(x)
lib/mock/assistant.ts  DEMO balasan Amina (lihat "Status integrasi")
lib/{format,nav,use-viewport}.ts
```

Ikon: `lucide-react`, selalu lewat `components/icon.tsx`.

## Backend

Base URL dev: `http://127.0.0.1:8000/api/v1` (tanpa trailing slash) — taruh di `NEXT_PUBLIC_API_URL`, jangan hard-code di komponen. Ini default dari `lib/api/client.ts`, dipakai `npm run dev`.

Base URL produksi: `/api/v1` (relatif, same-origin) — diset lewat `frontend/.env.production`, dipakai otomatis oleh `next build`. Sejak digabung ke `amanahfinance`, hasil static export (`output: 'export'`) diserve dari domain Laravel yang sama, jadi tidak ada lagi CORS lintas domain seperti sebelumnya (`localhost:3000` → `localhost:8000`). Deploy tidak lagi lewat Vercel — hasil build masuk `public/` repo `amanahfinance` lalu ikut deploy hPanel repo itu.

- **Auth**: Laravel Sanctum, header `Authorization: Bearer <token>`. `POST /auth/login` (email **atau** phone + password) → `{ user, token }`; `POST /auth/register`, `GET /auth/me`, `POST /auth/logout`.
- **Tidak ada header `X-Family-Id`.** Scope family ikut token — jangan menambah header karangan sendiri.
- **Envelope**: list → `{ data: [], links, meta }` (berhalaman, query `?page=`); single → `{ data: {} }`. Jangan asumsikan array telanjang.
- **Uang**: `integer` di semua schema. ID: `uuid`. Tanggal: `date` / `date-time` ISO-8601.

Pemetaan layar → endpoint yang benar:

| Layar | Endpoint |
| --- | --- |
| Login / Register | `POST /auth/login`, `POST /auth/register`, `GET /auth/me` |
| Onboarding | `POST /families`, `POST /family-invites/accept` (body `{ token: "AMANA-AB12CD" }`) |
| Chat | `GET/POST /chat-threads`, `GET/POST /chat-threads/{id}/messages` (body `content` / `input_mode` / `attachment_url`), `GET /ai-actions` |
| Dashboard | **tidak ada `GET /dashboard`** — susun dari `/accounts`, `/analytics/summary`, `/savings-goals`, `/transactions` |
| Wallets | `GET/POST/PUT/DELETE /wallets`, budget per periode di `GET/POST /wallets/{id}/budgets` |
| Akun | `GET/POST/PUT/DELETE /accounts` |
| Pemasukan | `GET/POST/PUT/DELETE /income-sources` |
| Target | `GET/POST/PUT/DELETE /savings-goals` |
| Analisa | `GET /analytics/summary?month=YYYY-MM` |
| Keluarga | `GET/POST /family-members`, `GET/POST /family-invites` |

`GET /analytics/summary` mengembalikan `cashflow { total_income, total_expense, total_savings, net }` dan `wallets[] { budget, spent, remaining, percent, status: no_budget|over|warning|ok }` — **ini sumber angka & status budget untuk Dashboard, Wallets, dan Analisa**. `SavingsGoal.percent` juga dari server.

Beda lain dengan prototipe yang perlu ditangani saat menyambung API: `Transaction.type` punya `transfer` dan `savings` (desain baru meng-cover `income`/`expense`), ada `origin`, `receipt_url`, `to_account_id`, `goal_id`; ada entitas `recurring-rules`, `notifications`, `audit-logs`, `llm-settings` yang belum punya layar.

## Status integrasi

API **sudah tersambung** (lihat [TaskProject.md](TaskProject.md)): auth, family, CRUD wallet/akun/pemasukan/target, dashboard, analisa, dan pengaturan keluarga semuanya memakai data sungguhan lewat TanStack Query.

```
lib/api/client.ts   satu-satunya fetch + ApiError (status, fieldErrors)
lib/api/schema.d.ts hasil `npm run api:types` — jangan diedit tangan
lib/api/hooks.ts    query & mutation per entitas
lib/auth.tsx        sesi Sanctum (token via lib/token-store.ts)
lib/ui-store.tsx    state klien: modal, bottom sheet, draft form
lib/entity-forms.ts pemetaan field desain → field API
lib/selectors.ts    pemetaan label (tanpa hitungan turunan)
```

Yang **masih demo** dan wajib dihapus begitu backend siap: `lib/mock/assistant.ts` di balik flag `NEXT_PUBLIC_MOCK_AMINA` — balasan Amina, kartu aksi, dan naskah wawancara awal. Alasannya: `POST /chat-threads/{id}/messages` menyimpan pesan user tapi belum membalas, dan tidak ada endpoint confirm/reject `ai_actions`. Pesan user tetap dikirim ke API sungguhan; header chat menampilkan penanda "Balasan demo" supaya tidak ada yang mengira itu jawaban asli.

Catatan penting soal sesi: token dibaca dari `tokenStore` **pada tiap request**, bukan dicermin ke variabel modul lewat `useEffect` — versi effect membuat request pertama setelah reload jalan tanpa header dan memicu `401` palsu.

## Aturan yang tidak boleh dilanggar

1. **Uang adalah integer rupiah penuh.** Jangan pernah pakai float. Format tampilan: `'Rp ' + n.toLocaleString('id-ID')`, tanpa desimal — lewat `formatRupiah()` di `lib/format.ts`.
2. **Jangan ketik ulang tipe API.** Generate dari `/api/v1/openapi.json` (`openapi-typescript` + `openapi-fetch` atau Orval) dan commit hasilnya.
3. **Jangan hitung apa pun yang dihitung server** — spent per wallet, status budget, percent, estimasi target, insight.
4. **Semua `fetch` lewat `lib/api/client.ts`.** Tidak ada `fetch` tersebar di komponen.
5. **Server state pakai TanStack Query** dengan key ber-`familyId`, mis. `['wallets', familyId]`. Jangan menyalin data server ke state lokal kecuali draft form.
6. **Tidak ada penulisan data langsung dari klien saat chat** — hanya lewat konfirmasi `ai_actions`. Catatan: `openapi.json` baru mengekspos `GET /ai-actions`; endpoint confirm/reject belum ada. Kalau butuh, **tanya backend dulu — jangan diakali dengan `POST /transactions` dari layar chat.**
7. **Naskah pertanyaan onboarding tidak disimpan di klien.** Render urutan yang dikirim API (thread `kind: onboarding` + `/onboarding-answers`).

## Token desain (sumber: `app/globals.css`)

Selalu pakai `var(--*)`, jangan hard-code hex atau px yang sudah punya token.

- bg `#fdf8f3` · surface `#ffffff` · text `#3a332c`
- Accent emas base `#b8912b`, ramp 100→900; accent-2 hijau tua base `#1f4d3a`
- Pemasukan (hijau): `--color-income-bg` / `--color-income-fg`
- Heading **Sora** 600 (−0.02em) · Body **Plus Jakarta Sans** 15px/1.6 — di-self-host lewat `next/font`, jangan bergantung Google CDN
- Spacing 6/10/16/22/26/32/44 · Radius sm 10, md 16, lg 24, pil 999
- Ikon: **Lucide** saja
- Keyframes: `amanaBlink` (typing), `amanaPulse` (recording)

## Layout & navigasi

- Breakpoint tunggal **900px**, dipasang sebagai **media query** di `app/globals.css` (bukan listener JS, supaya bebas hydration mismatch). Listener `resize` hanya dipakai `lib/use-viewport.ts` untuk menghitung ulang path notch.
- < 900px: bottom tab bar 78px dengan **notch SVG**, tombol chat 56px bulat di `top:-16px`, konten `padding-bottom: 82px`. Tab bar dan tombolnya butuh `position: relative` supaya tidak tertutup path SVG latar.
- ≥ 900px: sidebar 240px fixed, konten `margin-left: 240px`.
- Semua konten `max-width: 720px` dan berpusat (`.amana-container` / `.amana-chat-pane`).
- Layar = route, bukan state: `/login`, `/register`, `/onboarding`, lalu `/chat` (default), `/dashboard`, `/wallets`, `/accounts`, `/income`, `/goals`, `/analysis`, `/settings`. Daftar tab & pembagian mobile/desktop ada di `lib/nav.ts` — ubah di situ, jangan hard-code per komponen.

## Status interaksi (jangan restyle per halaman)

Hover nav/tab → `accent-100`. Primary hover → `accent-600`, active → `accent-700` + `transform: scale(.97)`. Fokus keyboard → `outline: 2px solid var(--color-accent); outline-offset: 2px`. Disabled → `opacity .45`.

## Chat

- Typing indicator harus dipicu event dari server (`thinking` bila SSE tersedia), **bukan timer palsu**. Bentuk streaming balasan belum ada di `openapi.json` — konfirmasi ke backend sebelum membangun parser SSE.
- Action card = satu baris `ai_actions` (`status: pending|confirmed|edited|rejected|expired`, `payload`, `result_table`, `result_id`). "Ya, lanjutkan" → confirm; "Edit" → modal prefilled lalu confirm dengan payload hasil edit; "Batal" → reject.
- Setelah confirm sukses: invalidasi query sesuai `result_table` (`transactions`, `wallets`, `accounts`, `analytics`); baris tombol diganti "Sudah disimpan".
- **Optimistic UI boleh, tapi wajib rollback bila API gagal.**

## State milik klien vs server

Klien (`lib/ui-store.tsx` + state lokal komponen): `modal`, `moreSheetOpen`, `chatInput`, `isRecording`, draft form + error per-field. Layar & tab aktif ikut router; desktop/mobile ikut CSS — jangan dijadikan state lagi.

Server (TanStack Query, `lib/api/hooks.ts`): `messages`, `wallets`, `accounts`, `incomeSources`, `savingsGoals`, `transactions`, `analytics`, `family`, `members`, `invites`.

## Penanganan error

- `422` → pesan per-field di bawah input (12px, `accent-800`) — kelas `.field-error`. Backend masih mengirim kunci mentah (`validation.required`); pemetaan sementara ada di `translateValidation()` pada `lib/api/client.ts`.
- `401` → satu baris di atas tombol / redirect ke login.
- `403` → bukan anggota family atau role kurang; sembunyikan aksi yang tidak diizinkan untuk `viewer`.
- `409` → entitas masih dipakai data lain (mis. wallet dengan transaksi); tampilkan alasannya di dialog konfirmasi.
- Loading pakai **skeleton yang meniru tinggi kartu asli** (`components/ui.tsx`), bukan spinner tengah layar. Empty state selalu punya ajakan tindakan.

## Jangan

- Jangan menambah warna, font, radius, atau bayangan di luar token.
- Jangan menaruh naskah Amina, prompt, atau kunci LLM di klien.
- Jangan menyalin markup prototipe apa adanya ke produksi; `template/` adalah referensi, di-ignore ESLint.
- Jangan menukar istilah **Wallet** (kantong anggaran) dengan **Akun** (tempat uang berada) — keduanya beda entitas.
