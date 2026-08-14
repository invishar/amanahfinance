# CLAUDE.md — amanafinance-web

Klien AmanaFinance: seluruh antarmuka pengguna. **Tidak ada logika bisnis dan tidak ada kunci API LLM di repo ini.** Semua data datang dari `amanafinance-api`.

Dokumen wajib baca sebelum menulis kode:
- `README.md` — spesifikasi tiap layar (layout, ukuran, warna, perilaku) — **high-fidelity, ikuti persis**
- `API-v1.md` di repo `amanafinance-api` — kontrak endpoint
- `styles/soft.css` — token desain

## Status file desain

`AmanaFinance.dc.html`, `lib/amana-lib.js`, `support.js` adalah **prototipe referensi, bukan kode produksi**. Bangun ulang di framework target memakai pola yang sudah ada di sana. `support.js` tidak perlu diporting sama sekali.

## Aturan yang tidak boleh dilanggar

1. **Uang adalah integer rupiah penuh.** Jangan pernah pakai float. Format tampilan: `'Rp ' + n.toLocaleString('id-ID')`, tanpa desimal.
2. **Jangan ketik ulang tipe API.** Generate dari `/api/v1/openapi.json` (`openapi-typescript` + `openapi-fetch` atau Orval) dan commit hasilnya.
3. **Jangan hitung apa pun yang dihitung server** — spent per wallet, status budget, percent, estimasi target, insight. `walletSpent` / `estimateGoalCompletion` di `lib/amana-lib.js` hanya agar mock terlihat hidup; **jangan diporting**.
4. **Semua `fetch` lewat satu modul `lib/api.ts`.** Tidak ada `fetch` tersebar di komponen.
5. **Server state pakai TanStack Query** dengan key ber-`familyId`, mis. `['wallets', familyId]`. Jangan menyalin data server ke state lokal kecuali draft form.
6. **Tidak ada penulisan data langsung dari klien saat chat** — hanya lewat `POST /ai-actions/{id}/confirm`.
7. **Naskah pertanyaan onboarding tidak disimpan di klien.** Render urutan yang dikirim API; progres ada di `onboarding.step` / `onboarding.total`.

## Token desain (sumber: `styles/soft.css`)

Selalu pakai `var(--*)`, jangan hard-code hex atau px yang sudah punya token.

- bg `#fdf8f3` · surface `#ffffff` · text `#3a332c`
- Accent emas base `#b8912b`, ramp 100→900; accent-2 hijau tua base `#1f4d3a`
- Pemasukan (hijau): bg `oklch(90% 0.06 150)`, teks `oklch(45% 0.13 150)`
- Heading **Sora** 600 (−0.02em) · Body **Plus Jakarta Sans** 15px/1.6 — self-host, jangan bergantung Google CDN
- Spacing 6/10/16/22/26/32/44 · Radius sm 10, md 16, lg 24, pil 999
- Ikon: **Lucide** saja
- Keyframes: `amanaBlink` (typing), `amanaPulse` (recording)

## Layout & navigasi

- Breakpoint tunggal **900px**, dipantau lewat listener `resize`. Semua konten `max-width: 720px` dan berpusat.
- < 900px: bottom tab bar 78px dengan **notch SVG** — path dihitung ulang tiap resize (lebar viewport jadi state), tombol chat 56px bulat di `top:-16px`. Konten `padding-bottom: 82px`.
- ≥ 900px: sidebar 240px fixed, konten `margin-left: 240px`.
- Screen state: `login | register | onboarding | app`; tab di dalam `app`: `chat | dashboard | wallets | accounts | income | goals | analysis | settings`. Di produksi ini jadi routing, bukan state.

## Status interaksi (jangan restyle per halaman)

Hover nav/tab → `accent-100`. Primary hover → `accent-600`, active → `accent-700` + `transform: scale(.97)`. Fokus keyboard → `outline: 2px solid var(--color-accent); outline-offset: 2px`. Disabled → `opacity .45`.

## Chat

- Balasan `POST /chat/messages` adalah **SSE**: `thinking` → `token`* → `action_card` → `done` | `error`. Typing indicator dipicu event `thinking`, **bukan timer palsu**.
- Action card = satu baris `ai_actions`. "Ya, lanjutkan" → confirm; "Edit" → modal prefilled lalu confirm dengan payload hasil edit; "Batal" → reject.
- Setelah confirm sukses: invalidasi query `transactions`, `wallets`, `accounts`, `dashboard`, `analytics` sesuai `result.table`; baris tombol diganti "Sudah disimpan".
- **Optimistic UI boleh, tapi wajib rollback bila API gagal.**

## State milik klien vs server

Klien: `screen`, `appTab`, `isDesktop`, `winW`, `moreSheetOpen`, `chatInput`, `isRecording`, `pendingMessageId`, `modal`, draft form + error per-field.

Server (TanStack Query): `messages`, `wallets`, `accounts`, `incomeSources`, `savingsGoals`, `transactions`, `dashboard`, `analytics`, `family`, `members`, `inviteCode`.

## Penanganan error

- `422` → pesan per-field di bawah input (12px, `accent-800`).
- `401` → satu baris di atas tombol / redirect ke login.
- `403` → bukan anggota family atau role kurang; sembunyikan aksi yang tidak diizinkan untuk `viewer`.
- Loading pakai **skeleton yang meniru tinggi kartu asli**, bukan spinner tengah layar. Empty state selalu punya ajakan tindakan.

## Jangan

- Jangan menambah warna, font, radius, atau bayangan di luar token.
- Jangan menaruh naskah Amina, prompt, atau kunci LLM di klien.
- Jangan menyalin markup prototipe apa adanya ke produksi.
- Jangan menukar istilah **Wallet** (kantong anggaran) dengan **Akun** (tempat uang berada) — keduanya beda entitas.
