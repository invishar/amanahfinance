# amanafinance-web — Handoff Frontend

Klien AmanaFinance: seluruh antarmuka pengguna. **Tidak ada logika bisnis, tidak ada kunci API LLM di repo ini.** Semua data datang dari `amanafinance-api` (Laravel).

- Repo pasangan: `amanafinance-api` — kontrak endpoint ada di `API-v1.md` repo tersebut.
- Base URL API: `VITE_API_URL` / `NEXT_PUBLIC_API_URL`, mis. `https://api.amanafinance.id/api/v1`.

## Overview
Antarmuka utama adalah **percakapan dengan asisten AI "Amina"**. Pengguna mencatat transaksi lewat obrolan biasa (teks, foto struk, suara); backend mengubahnya jadi draft transaksi terstruktur dan mengembalikan **action card** yang harus dikonfirmasi user sebelum tersimpan. Layar lain (dashboard, wallet, akun, target, analisa) adalah tampilan atas data yang sama, dipakai bersama satu "family".

## About the design files
File dalam bundel ini adalah **referensi desain dalam HTML** — prototipe yang menunjukkan tampilan dan perilaku yang diinginkan, **bukan kode produksi untuk disalin**. Bangun ulang di codebase target (Next.js/React, Vue/Nuxt, Inertia, atau mobile) memakai pola yang sudah ada di sana.

Data pada prototipe seluruhnya **mock** (`lib/amana-lib.js`), bentuknya sengaja menyerupai response API supaya mudah ditukar.

## Fidelity
**High-fidelity.** Warna, tipografi, spacing, radius, bayangan, dan status interaksi sudah final dan harus direplikasi persis. Alur navigasi dan alur percakapan juga final. Belum final: integrasi backend, OCR struk, speech-to-text, prompt LLM sebenarnya.

---

## Lapisan data (yang menggantikan mock)

Buat satu modul `lib/api.ts` sebagai satu-satunya tempat `fetch` terjadi. Aturan:

1. **Jangan ketik ulang tipe.** API mengekspos OpenAPI di `/api/v1/openapi.json` — generate tipe klien dari situ (`openapi-typescript` + `openapi-fetch`, atau Orval). Commit hasil generate.
2. **Auth**: Bearer token dari `POST /auth/login`, disimpan di cookie `httpOnly` (SSR) atau memory + refresh (SPA). Semua request menyertakan header `X-Family-Id` bila user punya lebih dari satu family.
3. **Server state pakai TanStack Query** (atau SWR). Query key `['wallets', familyId]`, dst. Jangan menyalin data server ke state lokal — kecuali draft form.
4. **Uang adalah integer rupiah penuh** (`bigint` di DB, dikirim sebagai `number`/`string` desimal-0). Jangan pernah pakai float. Format tampilan: `'Rp ' + n.toLocaleString('id-ID')`.
5. **Perhitungan turunan dihitung server** (terpakai per wallet, status budget, progress target, estimasi tercapai, ringkasan analisa). Frontend hanya merender. `walletSpent` / `estimateGoalCompletion` di `lib/amana-lib.js` ada di prototipe hanya supaya mock terlihat hidup — jangan diporting.

Pemetaan layar → endpoint (detail di `API-v1.md`):

| Layar | Endpoint |
| --- | --- |
| Login / Register | `POST /auth/login`, `POST /auth/register`, `GET /me` |
| Onboarding | `POST /families`, `POST /families/join`, `GET /families/{id}` |
| Chat | `GET /chat/threads/{id}/messages`, `POST /chat/messages` (SSE), `POST /ai-actions/{id}/confirm`\|`/reject` |
| Dashboard | `GET /dashboard` (satu panggilan: total saldo, wallet bulan ini, target, 8 transaksi terbaru) |
| Wallets | `GET/POST/PATCH/DELETE /wallets` |
| Akun | `GET/POST/PATCH/DELETE /accounts` |
| Pemasukan | `GET/POST/PATCH/DELETE /income-sources` |
| Target | `GET/POST/PATCH/DELETE /savings-goals` |
| Analisa | `GET /analytics/summary?period=YYYY-MM` |
| Pengaturan | `GET /families/{id}/members`, `POST /families/{id}/invites` |

---

## Screens / Views

Empat "screen state" di level atas: `login`, `register`, `onboarding`, `app`. Di dalam `app` ada 8 tab: `chat`, `dashboard`, `wallets`, `accounts`, `income`, `goals`, `analysis`, `settings`.

### 1. Login
- **Layout**: satu kolom terpusat, `max-width: 380px`, padding 32px, rata tengah vertikal (`min-height:100vh; display:flex; align-items:center`).
- Lingkaran brand 56×56, `border: 1.5px solid var(--color-accent)`, teks "AF" (font heading 22px, warna accent).
- H1 "AmanaFinance" — 26px, font heading.
- Subjudul "Asisten keuangan keluarga yang ngerti obrolan sehari-hari" — 13px, `.text-muted`.
- Field Email + Kata sandi; label 12px semibold, input tinggi 44px, radius 10px, border 1.5px divider; fokus → border accent.
- Tombol primary blok "Masuk" — tinggi 44px, radius 999px, background emas `#b8912b`, teks putih.
- Baris "Belum punya akun? **Daftar**" — 13px, link warna accent.
- **Error API**: 422 → pesan per-field di bawah input (12px, warna accent-800); 401 → satu baris di atas tombol.

### 2. Register
Sama seperti Login + field "Nama lengkap"; judul "Buat akun baru", subjudul "Mulai catat keuangan keluarga bareng-bareng", tombol "Daftar", link ke "Masuk".

### 3. Onboarding (1 langkah)
- H1 "Siapkan keluargamu" (24px) + paragraf 14px muted.
- Segmented control (`.seg` / `.seg-opt`, radius 999px): "Buat baru" | "Gabung keluarga". Terpilih → background accent, teks putih.
- "Buat baru" → field "Nama keluarga" (placeholder `Keluarga Pratama`) → `POST /families`.
- "Gabung keluarga" → field "Kode undangan" (placeholder `AMANA-XXXXX`) → `POST /families/join`.
- Tombol primary blok "Lanjut ke AmanaFinance" → tab `chat`; backend otomatis membuat thread `kind: 'onboarding'` dan mengirim pertanyaan wawancara.

### 4. Chat (tab utama)
- **Layout**: kolom `max-width: 720px`, tinggi `calc(100vh - 82px)` mobile / `100vh` desktop; header → daftar pesan (scroll, `flex:1`) → chip saran → komposer.
- **Header**: avatar 38px huruf "A" (border accent), "Amina" (heading 16px), sub "Asisten keuangan Keluarga Pratama" (12px muted), border bawah 1px divider.
- **Bubble**: `max-width: 82%`, padding 10px 14px, radius 24px, border 1px divider, 14px/1.5. User → rata kanan, background `var(--color-accent-100)` (#faf1dc). Amina → rata kiri, `var(--color-surface)`.
- **Action card** (dari `ai_actions` di API): putih, border 1px divider, radius 16px, padding 16px, shadow-sm. Ikon Lucide + judul; baris "label (muted, kiri) — nilai (kanan)" 13px; tombol primary "Ya, lanjutkan" (ikon check) → `POST /ai-actions/{id}/confirm`, secondary "Edit" → modal prefilled lalu confirm dengan payload hasil edit, ghost "Batal" (ikon x) → `/reject`. Setelah sukses baris tombol diganti "Sudah disimpan" (12px accent-700 + ikon check); ditolak → "Dibatalkan" (12px muted). **Optimistic UI boleh, tapi rollback bila API gagal.**
- **Typing indicator**: tiga titik 6px accent, `amanaBlink` 1.2s infinite, delay 0/.15s/.3s (opacity .25 → 1). Dipicu event SSE `thinking`, bukan timer palsu.
- **Chip saran** (scroll horizontal, tombol secondary 12px): "Catat pengeluaran", "Buat wallet baru", "Tambah akun baru", "Minta saran keuangan". Saat wawancara awal: satu chip **"Lewati pertanyaan ini"** + teks di atasnya "Pertanyaan N dari 4 — boleh dilewati kapan saja" (11px muted).
- **Komposer**: tombol kamera (secondary, 40px bulat) → pilih/ambil foto struk, unggah via `POST /uploads` lalu kirim pesan `input_mode: 'image'`; tombol mikrofon (border 1.5px; saat merekam border+ikon accent, animasi `amanaPulse` 1s) → Web Speech API bila tersedia, fallback unggah audio; input teks `flex:1` placeholder "Tulis pesan ke Amina..."; tombol kirim (primary bulat, ikon `send`). Enter = kirim.

### 5. Dashboard
- Kolom `max-width: 720px`, padding 22px, gap 26px.
- **Header**: kiri — tanggal hari ini (13px muted, `Sabtu, 8 Agustus`) + H1 "Halo, Rizki" (26px); kanan — avatar 44px background accent, inisial putih.
- **Hero saldo**: kartu radius 24px, padding 26px, `background: linear-gradient(135deg, var(--color-accent-500), var(--color-accent-2-500))`, teks putih, shadow-md, `overflow:hidden`. Kicker "TOTAL SALDO" 11px letter-spacing .1em uppercase opacity .85; angka 32px heading; baris kartu akun `flex-wrap: wrap`, item `flex: 1 1 120px`, background `rgba(255,255,255,.18)`, radius 16px, padding 10px 12px, berisi ikon jenis + nama (12px, ellipsis) + saldo (14px heading).
- **Pengeluaran per Wallet**: judul 17px, grid 2 kolom gap 10px, kartu putih radius 16px padding 16px: lingkaran ikon 30px background accent-100 + ikon accent-700, nama 13px semibold, progress bar 6px (track `--color-neutral-200`, isi `--color-accent-500`), nominal 12px muted.
- **Target Tabungan**: kartu putih; tiap baris ring progress 44px `conic-gradient(var(--color-accent-500) N%, var(--color-neutral-200) 0)` + lingkaran putih 34px di tengah berisi persentase (10px bold), nama target (13px semibold), "Rp x / Rp y" (12px muted).
- **Transaksi Terbaru**: kartu putih, 8 terbaru; lingkaran ikon 34px (pemasukan → background `oklch(90% 0.06 150)`, ikon `oklch(45% 0.13 150)`, `arrow-down-left`; pengeluaran → accent-100 + accent-700, `arrow-up-right`), catatan 13px, sub "nama wallet · tanggal" 11px muted, nominal kanan 13px heading sewarna ikon dengan prefiks `+ ` / `− `.
- **Loading**: skeleton yang meniru tinggi kartu asli, bukan spinner tengah layar. **Empty state**: kalimat ajakan + tombol "Catat lewat chat".

### 6. Wallets
Header "Wallets" + tombol primary "Tambah" (ikon plus). Kartu: ikon wallet + nama (17px), tombol edit & hapus 30px di kanan; "Rp terpakai dari Rp budget" (12px muted) + status kanan ("Aman" / "Hampir habis" ≥80% / "Lewat budget" >100%, **status datang dari API**); progress bar 8px berwarna sesuai status (accent-400 / accent-600 / accent-800).

### 7. Akun Bank & E-Wallet
Header + "Tambah". Kartu satu baris: ikon jenis (`landmark` bank, `smartphone` e-wallet, `banknote` tunai), nama 15px, tag jenis (`.tag .tag-neutral`), saldo kanan 15px heading, tombol edit & hapus.

### 8. Sumber Pemasukan
Header + "Tambah". Tiap baris: nama 15px, kanan "Bulan ini: Rp …" (13px muted) + tombol edit & hapus.

### 9. Target Tabungan
Header + "Tambah". Kartu: ikon `target` + nama; "Rp terkumpul dari Rp target" + persentase; progress bar 8px accent-500; baris bawah "Target: <tanggal>" dan "Estimasi tercapai: <bulan tahun>" (12px muted, **nilai dari API**).

### 10. Analisa Keuangan
Tiga kartu sejajar (Pemasukan / Pengeluaran / Selisih; kicker 10px uppercase accent, angka 20px heading). Kartu "Breakdown per Wallet" (bar seperti dashboard). Kartu "Wawasan dari Amina": ikon `sparkles` + judul, 3 paragraf insight 13px/1.6 — teks dari `GET /analytics/summary`, di-cache server, jangan panggil LLM tiap render.

### 11. Pengaturan Keluarga
Kartu nama keluarga (kicker "Keluarga" + nama). Kartu Anggota: baris ikon `user` + nama + tag "Admin" (`.tag .tag-accent`); tombol secondary "Undang Anggota Keluarga" → memunculkan kode undangan (`AMANA-XXXXX`, heading 15px, letter-spacing .04em, background surface, ikon `copy`) dari `POST /families/{id}/invites`. Tombol secondary "Keluar" di bawah.

---

## Navigasi

### Mobile (< 900px) — bottom tab bar dengan notch
- Bar `position: fixed; bottom: 0`, tinggi **78px**, latar satu path SVG selebar viewport, `fill: var(--color-bg)`, `filter: drop-shadow(0 -3px 12px rgba(neutral-900, .09))`.
- Path notch (w = lebar viewport, cx = w/2, r = 36, f = 14):
  `M0,0 H{cx-r-f} q{f},0 {f},{f} a{r},{r} 0 0 0 {2r},0 q0,{-f} {f},{-f} H{w} V78 H0 Z`
  → garis atas datar, melengkung masuk (fillet 14px), setengah lingkaran radius 36px mencekung ke bawah, lalu keluar. **Hitung ulang saat resize** (window width jadi state).
- Tombol chat: `position:absolute; left:50%; top:-16px; transform:translateX(-50%)`, 56×56 bulat, background accent (accent-600 saat aktif), ikon `message-circle` 24px putih, shadow-md, hover `filter: brightness(1.08)`; sela ±8px di dalam cekungan.
- Tab lain (Dashboard, Wallets, [spacer 64px], Target, Lainnya): kolom ikon 20px + label 10px semibold, padding 7px 14px, radius 16px; aktif → background `var(--color-accent-100)` + teks accent; hover sama.
- "Lainnya" membuka bottom sheet (`border-radius: 24px 24px 0 0`, shadow-lg): Akun, Pemasukan, Analisa, Keluarga — ikon accent + label 15px, dipisah divider. Backdrop neutral-900 50%.
- Konten `padding-bottom: 82px`; layar chat `calc(100vh - 82px)`.

### Desktop (≥ 900px) — sidebar 240px
Fixed kiri, background surface, border kanan divider. Brand (lingkaran "AF" 34px + wordmark 16px), tombol **"Chat dengan Amina"** pil penuh (padding 12px 16px, radius 999px, background accent, teks putih 15px semibold, shadow-sm, hover accent-600), lalu nav biasa (padding 9px 10px, radius 16px, ikon 18px + label 14px; aktif → accent-100 + teks accent). Di bawah: "Keluar". Konten `margin-left: 240px`.

---

## Interactions & Behavior

### Alur wawancara keuangan awal
Setelah onboarding, backend membuka thread dan mengirim sapaan: "Halo! Aku Amina, asisten keuangan buat <nama keluarga>. Sebelum mulai, boleh aku tanya beberapa hal dasar biar bantuanku makin pas? Santai aja, kalau belum tahu jawabannya bisa dilewati dulu."

Empat pertanyaan berurutan (`question_key`): `members` — "Ada berapa anggota keluarga yang bakal ikut mencatat di sini?"; `income` — "Penghasilan bulanan biasanya dari mana saja?"; `expenses` — "Pengeluaran rutin bulanan yang paling besar biasanya buat apa?"; `goals` — "Ada target tabungan yang sedang dikejar?"

Chip "Lewati pertanyaan ini" → `POST /onboarding/answers` dengan `skipped: true`; Amina membalas "Oke, nggak masalah — bisa diisi belakangan." Penutup: "Makasih banyak infonya! Ini udah cukup buat aku bantu kamu. Sekarang kamu bisa langsung cerita transaksi, kirim foto struk, atau minta saran kapan aja."

Frontend hanya merender urutan yang dikirim API — **jangan menyimpan naskah pertanyaan di klien**; progres wawancara ada di response (`onboarding.step`, `onboarding.total`).

### Alur pencatatan transaksi lewat chat
1. User kirim teks bebas / foto struk / voice.
2. `POST /chat/messages` → stream SSE: `thinking` → `token`* → `action_card` → `done`.
3. Action card berisi field yang bisa dicek (Jenis, Nominal, Wallet, Sumber Dana, Catatan).
4. "Ya, lanjutkan" → `POST /ai-actions/{id}/confirm` → invalidasi query `transactions`, `wallets`, `dashboard`; kartu jadi "Sudah disimpan"; Amina mengirim "Sudah aku simpan. Ada lagi yang mau dicatat?".
- Aksi lain berpola sama: `create_wallet` (Nama Wallet, Budget Bulanan), `create_account` (Nama Akun, Jenis, Saldo Awal). `advice` membalas paragraf tanpa kartu.
- **Tidak ada penulisan data langsung dari klien saat chat** — hanya lewat confirm.

### CRUD
Tombol "Tambah" dan ikon pensil membuka modal (`.dialog-backdrop` + `.dialog`, radius 24px, shadow-lg, lebar `min(440px, 100%)`), judul 20px, field sesuai entitas, aksi "Batal" (secondary) + "Simpan" (primary). Ikon tempat sampah → konfirmasi lalu `DELETE`. Klik backdrop menutup modal. Validasi mengikuti pesan 422 dari API.

### Responsif
Breakpoint tunggal **900px** (sidebar ↔ bottom tab bar), dipantau lewat listener `resize`. Semua konten `max-width: 720px` dan berpusat.

### Status interaksi
Hover nav/tab: background accent-100. Primary hover accent-600, active accent-700, `:active { transform: scale(.97) }`. Fokus keyboard: `outline: 2px solid var(--color-accent); outline-offset: 2px`. Disabled: opacity .45.

---

## State Management (klien)
Hanya state UI yang tinggal di klien; sisanya milik server-cache.

- `screen`: `login | register | onboarding | app` (di produksi: routing)
- `appTab`: `chat | dashboard | wallets | accounts | income | goals | analysis | settings`
- `isDesktop`, `winW` (untuk path notch), `moreSheetOpen`
- `chatInput`, `isRecording`, `pendingMessageId`
- `modal`: `{ kind: 'wallet'|'account'|'income'|'goal', item }`
- Draft form + error validasi per-field

Milik server (TanStack Query): `messages`, `wallets`, `accounts`, `incomeSources`, `savingsGoals`, `transactions`, `dashboard`, `analytics`, `family`, `members`, `inviteCode`.

### Format
Mata uang `'Rp ' + n.toLocaleString('id-ID')` tanpa desimal. Tanggal `toLocaleDateString('id-ID')`. Timezone tampilan `Asia/Jakarta`; API mengirim `timestamptz` ISO-8601.

---

## Design Tokens
Sumber lengkap: `styles/soft.css`.

**Warna**
- bg `#fdf8f3` · surface `#ffffff` · text `#3a332c` · divider `color-mix(in srgb, #3a332c 12%, transparent)`
- Accent (emas) 100→900: `#faf1dc #f0dfb0 #e2c47d #cead54 #b8912b #967322 #75591b #543f13 #33260b` (base `--color-accent: #b8912b`)
- Accent-2 (hijau tua) 100→900: `#dfeae3 #b4d0bf #86b494 #4f8f68 #1f4d3a #17402f #113322 #0b2417 #05140c` (base `#1f4d3a`)
- Neutral 100→900: `#fefbf8 #f4ede4 #e8dccd #d3c1a8 #b8a488 #94826a #715f4a #4d4032 #322a20`
- Pemasukan (hijau): background `oklch(90% 0.06 150)`, teks/ikon `oklch(45% 0.13 150)`

**Tipografi** — Heading: **Sora** 600, letter-spacing −0.02em (h1 40 / h2 30 / h3 23 / h4 19 / h5 16 / h6 13px uppercase). Body: **Plus Jakarta Sans** 15px/1.6. Keduanya Google Fonts.

**Spacing** 6 / 10 / 16 / 22 / 26 / 32 / 44px · **Radius** sm 10 · md 16 · lg 24 · pil 999 · **Shadow** (nada hangat `#543f13`) sm `0 2px 8px 8%` · md `0 8px 20px 10%` · lg `0 20px 48px 16%` · **Keyframes** `amanaBlink`, `amanaPulse`.

## Assets
- **Ikon**: [Lucide](https://lucide.dev). Dipakai: `message-circle, home, wallet, landmark, banknote, target, bar-chart-2, users, grid-2x2, plus, pencil, trash-2, camera, mic, send, check, x, receipt, sparkles, user, user-plus, copy, log-out, arrow-up-right, arrow-down-left, shopping-cart, car, film, file-text, smartphone`.
- **Font**: Sora & Plus Jakarta Sans (self-host di produksi; jangan bergantung pada Google CDN).
- Tidak ada gambar/foto dalam desain ini.

## Files di bundel ini
- `AmanaFinance.dc.html` — seluruh prototipe (semua layar, navigasi, alur chat, modal CRUD).
- `styles/soft.css` — token desain dan kelas komponen.
- `lib/amana-lib.js` — data mock + helper; `mockAssistantReply` menunjukkan bentuk respons yang diharapkan dari API AI. **Diganti oleh klien API sungguhan.**
- `support.js` — runtime prototipe saja, **tidak perlu diporting**.

Buka `AmanaFinance.dc.html` di browser untuk melihat desain; perkecil jendela di bawah 900px untuk versi mobile.
