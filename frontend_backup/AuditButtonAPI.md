# Audit Tombol/Button vs Integrasi API — Frontend AmanaFinance

Dokumen ini memetakan **setiap tombol/aksi klik** di `frontend/` dan status integrasinya
terhadap API backend (Laravel). Disusun dengan membaca seluruh file `.tsx`/`.ts` di
`app/`, `components/`, dan `lib/`.

Legenda:
- ✅ **Terintegrasi** — memanggil hook di `lib/api/hooks.ts` yang menembak endpoint backend sungguhan lewat `lib/api/client.ts` (`fetch`).
- ⚠️ **Sebagian/Mock** — ada pemanggilan API, tapi sebagian alurnya masih data tiruan (mock) atau hanya state lokal yang mengarah ke aksi nyata.
- ❌ **Belum terintegrasi** — tidak ada pemanggilan API sama sekali (mock murni, placeholder, atau tanpa handler).
- 🔘 **UI lokal (memang tidak butuh API)** — toggle/dismiss/copy/navigasi klien yang secara desain tidak perlu memanggil backend.

> Catatan penting: repo ini sudah mendokumentasikan sendiri status "demo" untuk fitur chat
> di [`CLAUDE.md`](../CLAUDE.md), [`TaskProject.md`](../TaskProject.md), dan
> [`CatatanBackend.md`](../CatatanBackend.md) — lewat flag `NEXT_PUBLIC_MOCK_AMINA`
> (default **aktif**, `!== "0"`). Audit ini mengonfirmasi & merinci dampaknya ke level tombol.

---

## Ringkasan Cepat

| Area | Status |
|---|---|
| Auth (login, register, onboarding, logout) | ✅ Semua terintegrasi |
| CRUD Wallets / Accounts / Income Sources / Goals (Tambah/Edit/Hapus) | ✅ Semua terintegrasi |
| Settings — undang anggota keluarga | ✅ Terintegrasi |
| Dashboard, Analysis | ✅ Read-only, data dari API asli (tidak ada tombol aksi selain navigasi) |
| **Chat (Amina) — balasan asisten & action card** | ❌ **Mock murni**, dipagari `NEXT_PUBLIC_MOCK_AMINA` |
| **Chat — upload foto struk & rekam suara** | ❌ **Placeholder**, belum ada endpoint upload/STT |

**Kesimpulan utama:** satu-satunya area dengan tombol yang *belum* terhubung ke API sungguhan
adalah halaman **Chat/Amina** — dan ini sudah diketahui & didokumentasikan tim sebagai
pekerjaan yang menunggu backend (`POST /chat-threads/{id}/messages` menyimpan pesan user,
tapi belum ada balasan asisten maupun endpoint confirm/reject `ai_actions`). Semua modul CRUD
dan auth lainnya sudah terhubung penuh.

---

## 1. Auth — `app/(auth)/*`

| Tombol | Lokasi | Handler | Status |
|---|---|---|---|
| "Masuk" (submit login) | `login/page.tsx:99-106` | `submit` → `useSession().login()` | ✅ `POST /auth/login` |
| "Daftar" (submit register) | `register/page.tsx:123-130` | `submit` → `useSession().register()` | ✅ `POST /auth/register` |
| "Lanjut ke AmanaFinance" | `onboarding/page.tsx:117-125` | `createFamily.mutateAsync(name)` atau `acceptInvite.mutateAsync(code)` | ✅ `POST /families` / `POST /family-invites/accept` |
| Radio "Buat baru" / "Gabung keluarga" | `onboarding/page.tsx:71-89` | `setMode(...)` | 🔘 State lokal (memang tidak perlu API) |
| "Masuk" di homepage (`app/page.tsx:22-28`) | — | `<Link href="/login">` | 🔘 Navigasi klien |

---

## 2. CRUD — Wallets, Accounts, Income Sources, Goals

Keempat halaman ini memakai komponen bersama `components/crud-dialog.tsx` (Tambah/Edit) dan
`components/use-delete-flow.tsx` + `components/confirm-dialog.tsx` (Hapus). Semua sudah
terhubung ke `useSaveEntity` / `useDeleteEntity` di `lib/api/hooks.ts`, yang memanggil
`api.one(...)` di `lib/api/client.ts` — satu-satunya titik `fetch` di seluruh app.

| Halaman | Tombol | Status | Endpoint |
|---|---|---|---|
| Wallets (`wallets/page.tsx`) | Tambah, Edit, Hapus | ✅ | `POST/PUT/DELETE /wallets` |
| Accounts (`accounts/page.tsx`) | Tambah, Edit, Hapus | ✅ | `POST/PUT/DELETE /accounts` |
| Income Sources (`income/page.tsx`) | Tambah, Edit, Hapus | ✅ | `POST/PUT/DELETE /income-sources` |
| Goals (`goals/page.tsx`) | Tambah, Edit, Hapus | ✅ | `POST/PUT/DELETE /savings-goals` |
| `CrudDialog` — "Batal" | `crud-dialog.tsx:127-129` | 🔘 Tutup modal lokal |
| `CrudDialog` — "Simpan" | `crud-dialog.tsx:130-132` | ✅ Dispatch ke endpoint sesuai `modal.kind` |
| `ConfirmDialog` — "Batal" | `confirm-dialog.tsx:40-42` | 🔘 Tutup dialog lokal |
| `ConfirmDialog` — "Hapus" (confirm) | `confirm-dialog.tsx:43-50` | ✅ Selalu dipanggil dari `useDeleteFlow` → `DELETE {path}/{id}`, termasuk penanganan 409 conflict |

Delete flow menampilkan pesan `"Tidak bisa dihapus karena masih dipakai data lain."` saat
backend menolak (409) — konsisten dengan aturan `transactions` sebagai sumber kebenaran di
[`CLAUDE.md`](../CLAUDE.md).

---

## 3. Dashboard & Analysis

Tidak ada tombol aksi mutasi. Halaman read-only berbasis `useAccounts`, `useAnalytics`,
`useWallets`, `useSavingsGoals`, `useTransactions`, `useIncomeSources` — semua ✅ data asli.

| Tombol | Lokasi | Status |
|---|---|---|
| "Catat lewat chat" (EmptyState saat belum ada wallet) | `dashboard/page.tsx:208-212` | 🔘 Navigasi ke `/chat` (bukan mutasi API) |

---

## 4. Settings

| Tombol | Lokasi | Handler | Status |
|---|---|---|---|
| "Undang Anggota Keluarga" (buka form) | `settings/page.tsx:149-160` | `setInviteOpen(true)` | 🔘 State lokal |
| Form undangan — "Batal" | `settings/page.tsx:132-137` | `setInviteOpen(false)` | 🔘 State lokal |
| Form undangan — "Buat undangan" (submit) | `settings/page.tsx:139-145` | `submitInvite` → `createInvite.mutateAsync(...)` | ✅ `POST /family-invites` |
| "Salin kode undangan" | `settings/page.tsx:185-197` | `navigator.clipboard.writeText(...)` | 🔘 Clipboard klien (memang tidak perlu API) |
| "Keluar" (logout) | `settings/page.tsx:202-212` | `logout()` → redirect `/login` | ✅ `POST /auth/logout` |

`app-shell.tsx` juga punya tombol "Keluar" di sidebar desktop (`app-shell.tsx:101-119`) —
✅ sama-sama memanggil `POST /auth/logout`. Nav link & tombol "Lainnya" (mobile) di
`app-shell.tsx` bersifat navigasi/UI lokal saja — 🔘.

---

## 5. Chat (Amina) — **area dengan tombol belum terintegrasi**

Halaman ini **campuran**: pengiriman pesan user tersimpan lewat API asli, tetapi seluruh
balasan asisten dan action card berasal dari `lib/mock/assistant.ts` (dipagari
`NEXT_PUBLIC_MOCK_AMINA`, default aktif — lihat `lib/mock/assistant.ts:12`).

| Tombol | Lokasi | Handler | Status | Catatan |
|---|---|---|---|---|
| Chip saran demo (`DEMO_CHIPS`) | `chat/page.tsx:302-312` | `send(c.demoText, c.scenario)` | ⚠️ Sebagian | Pesan user asli terkirim (`POST /chat-threads/{id}/messages`), tapi balasan Amina dibuat oleh `runDemoAnswer()` dari mock |
| Input teks + Enter | `chat/page.tsx:365-370` | `send(input)` | ⚠️ Sebagian | Sama seperti di atas |
| Tombol "Kirim" | `chat/page.tsx:374-383` | `send(input)` | ⚠️ Sebagian | Sama seperti di atas |
| "Lewati pertanyaan ini" (onboarding chat) | `chat/page.tsx:293-300` | `skipOnboardStep` | ❌ Mock murni | Hanya `setTimeout`/state lokal berbasis `DEMO_ONBOARD_QUESTIONS`, tidak memanggil API |
| "Kirim foto struk" (ikon kamera) | `chat/page.tsx:332-340` | Kirim string placeholder `"[Foto struk diunggah]"` | ❌ Belum terintegrasi | Tidak ada upload file sungguhan. Komentar di kode: *"Unggah struk & rekaman menunggu endpoint upload di API."* |
| "Rekam suara" (ikon mikrofon) | `chat/page.tsx:341-358` | `toggleRecording` → transkrip palsu via `setTimeout` | ❌ Belum terintegrasi | Tidak ada rekaman/STT sungguhan |
| **Action Card — "Ya, lanjutkan"** | `action-card.tsx` → `message-list.tsx:83` → `chat/page.tsx` `resolveCard()` | `onResolveCard(id, "confirmed")` | ❌ Mock murni | Hanya mengubah state lokal `demoItems`; **tidak ada** endpoint confirm `ai_actions` yang dipanggil (memang belum ada di backend, sesuai `CLAUDE.md`) |
| **Action Card — "Batal"** | `action-card.tsx` → `message-list.tsx:84` | `onResolveCard(id, "cancelled")` | ❌ Mock murni | Sama seperti di atas — tidak ada endpoint reject `ai_actions` |
| **Action Card — "Edit"** | `action-card.tsx` | *(tidak ada onClick)* | ❌ Belum terintegrasi | Tidak ada handler sama sekali. Komentar di kode: *"Butuh endpoint confirm dengan payload hasil edit — lihat TaskProject.md"* |

### Kenapa ini terjadi (bukan bug, tapi utang teknis yang didokumentasikan)

Sesuai [`CLAUDE.md`](../CLAUDE.md) baris 76 dan [`TaskProject.md`](../TaskProject.md):
- `POST /chat-threads/{id}/messages` di backend baru menyimpan pesan user, **belum
  menghasilkan balasan LLM**.
- Belum ada endpoint **confirm/reject** untuk `ai_actions` (lihat alur AI di `CLAUDE.md`:
  seharusnya `ConfirmAiAction` yang menulis baris nyata dari `ai_actions.pending`).
- Belum ada endpoint **upload lampiran** (foto struk) maupun **STT** (rekam suara).
- Header chat menampilkan penanda **"Balasan demo"** selama `NEXT_PUBLIC_MOCK_AMINA` aktif,
  supaya user tidak mengira itu jawaban asli.
- `TaskProject.md` sudah mencatat item TODO: *"Hapus `lib/mock/assistant.ts` + flag
  `NEXT_PUBLIC_MOCK_AMINA` — menunggu blocker #1 & #2"*.

### Yang perlu dibangun di backend agar tombol-tombol ini bisa terintegrasi

1. Endpoint balasan asisten (job antrian LLM tool-calling → `ai_actions.pending` → SSE
   `action_card`), sesuai "Alur AI" di `CLAUDE.md`.
2. Endpoint `ConfirmAiAction` (confirm) dan endpoint reject untuk `ai_actions`, dipanggil dari
   tombol "Ya, lanjutkan" dan "Batal".
3. Handler + UI untuk tombol "Edit" pada action card (kirim payload hasil edit ke endpoint
   confirm).
4. Endpoint upload lampiran (foto struk) — tombol kamera saat ini hanya mengirim string
   placeholder.
5. Endpoint/alur STT (speech-to-text) — tombol mikrofon saat ini hanya simulasi.

Begitu backend siap, set `NEXT_PUBLIC_MOCK_AMINA=0` dan hapus `lib/mock/assistant.ts` sesuai
rencana yang sudah ada di `TaskProject.md`.

---

## Lampiran — File yang dianalisis

`app/(app)/{accounts,analysis,chat,dashboard,goals,income,settings,wallets}/page.tsx`,
`app/(app)/layout.tsx`, `app/(auth)/{login,onboarding,register}/page.tsx`, `app/layout.tsx`,
`app/page.tsx`, `app/providers.tsx`, `components/{app-shell,auth-header,confirm-dialog,
crud-dialog,icon,require-session,ui,use-delete-flow}.tsx`,
`components/chat/{action-card,message-list}.tsx`, `lib/auth.tsx`, `lib/ui-store.tsx`,
`lib/api/{client,hooks,keys}.ts`, `lib/entity-forms.ts`, `lib/mock/assistant.ts`.
