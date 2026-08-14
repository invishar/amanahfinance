# Plan — Penggabungan `amanahfinance_front` ke Repo Ini

Dibuat 14 Agustus 2026. Keputusan tim: repo FE (`amanahfinance_front`, Next.js 16
App Router) dan repo BE (`amanafinance-api`, repo ini) **disatukan jadi satu repo**.
FE tetap murni klien — komunikasi ke backend tetap lewat `fetch()`, sekarang
**same-origin** (bukan lintas domain seperti `localhost:3000` → `localhost:8000`).

Dokumen ini rencana kerja sebelum eksekusi. Belum ada langkah teknis yang dijalankan;
update status tiap item begitu dikerjakan, jangan hapus — pindahkan ke "Selesai".

---

## Kenapa ini masuk akal (temuan dari review repo FE)

- Semua halaman `"use client"`, **tidak ada** `app/api/*`, `middleware.ts`, atau
  segmen route dinamis (`[id]`) di `amanahfinance_front`. Token sesi disimpan di
  `localStorage` ([lib/token-store.ts](https://github.com/invishar/amanahfinance_front/blob/main/lib/token-store.ts)),
  bukan cookie session.
- Konsekuensinya: aplikasi FE **tidak butuh Node server saat runtime** — bisa
  di-build sebagai static export (`output: 'export'`) menjadi HTML/JS/CSS murni.
  Ini yang bikin skema ini kompatibel dengan constraint hPanel di `CLAUDE.md`
  ("tidak ada proses persisten, tidak ada worker daemon").
- `lib/api/client.ts` sudah disiplin satu pintu (`fetch` cuma di situ, base URL dari
  `NEXT_PUBLIC_API_URL`, auth Bearer token) — tinggal diarahkan ke path relatif.
- FE sudah punya `CLAUDE.md` sendiri yang isinya konsisten dengan disiplin di repo
  ini (uang integer, tidak ada logika bisnis di klien, semua turunan dihitung
  server) — tidak perlu ditulis ulang dari nol, cuma disesuaikan bagian deploy/env.

---

## Keputusan yang perlu difinalkan sebelum eksekusi

Rekomendasi di bawah sudah mempertimbangkan isi riwayat commit `amanahfinance_front`
(17 commit, 2 kontributor — `priana13`, `invishar` — bukan cuma satu commit scaffold,
lihat pesan commit "integrasi ke backend", "adopsi halaman dari template").

**Disetujui tim 14 Agustus 2026** — tiga item pertama di bawah final, tidak lagi
open question. Item keempat (siapa yang eksekusi) menyusul saat mulai langkah 1.

- [x] **Strategi penggabungan git history** — **rekomendasi: `git subtree add`**
  (pertahankan riwayat commit FE), bukan copy manual. Dengan 17 commit dari 2 orang,
  riwayatnya bukan noise; `git blame`/`git log` di `frontend/` akan sering dipakai
  untuk investigasi begitu FE & BE mulai sering diubah bareng dalam satu PR. Biaya
  subtree cuma satu command tambahan di awal, tidak ada downside berarti.
- [x] **Lokasi folder FE** — **rekomendasi: `frontend/`**. Tidak ada alternatif yang
  lebih jelas, konsisten dengan konvensi monorepo umum.
  Catatan kecil di luar keputusan ini: penamaan tidak konsisten antar dokumen — repo
  lama disebut "amanafinance-web" di `PLAN-INTEGRASI-FRONTEND.md`, tapi nama repo
  asli `amanahfinance_front` ("amanah" vs "amana"). Seragamkan sekalian saat update
  dokumentasi di langkah 5, supaya tidak membingungkan pembaca riwayat nanti.
- [x] **Nama repo/branch pasca-gabung** — **rekomendasi: tetap `amanafinance-api`
  ini** yang jadi rumah final, `amanahfinance_front` diarsipkan. Repo ini sudah
  punya disiplin lebih matang (CLAUDE.md, test suite Pest, migrasi berurutan) dan
  riwayat kerja BE yang panjang — bikin repo baru cuma nambah overhead administratif
  (setup CI ulang, secrets, branch protection, deploy key) tanpa manfaat teknis.
  Kalau nama `amanafinance-api` terasa janggal setelah tidak lagi API-only, **rename**
  repo (bukan bikin baru) adalah opsi terpisah yang bisa menyusul kapan saja — GitHub
  redirect otomatis dari nama lama, jadi tidak mendesak.
- [ ] **Siapa yang menjalankan migrasi**: bukan keputusan teknis — perlu satu orang
  dengan write access ke repo ini untuk push hasil `git subtree`. Perintahnya sendiri
  bisa dijalankan begitu tiga keputusan di atas fix.

---

## Langkah eksekusi

### 1. Gabungkan source code — SELESAI (14 Agustus 2026)

- [x] `git subtree add --prefix=frontend https://github.com/invishar/amanahfinance_front main`
  dijalankan, riwayat commit FE ikut masuk (commit merge `c9291af`). Catatan: hanya
  6 commit yang ikut (5 `priana13`, 1 `invishar`), bukan 17 seperti disebut di bagian
  "Keputusan" di atas — kemungkinan riwayat `main` FE sudah di-rewrite/rebase sejak
  survei itu ditulis, atau ada commit di branch lain yang tidak ikut `main`. Riwayat
  yang masuk tetap utuh sesuai yang ada di `main` saat fetch dilakukan.
- [x] `.gitignore` root **tidak perlu diubah** — `frontend/.gitignore` ikut terbawa dari
  repo FE dan sudah mengecualikan `/node_modules`, `/.next/`, `/out/` relatif terhadap
  foldernya sendiri (Git menghormati `.gitignore` bersarang).
- [x] Root `package.json`/`composer.json` tidak berubah — dikonfirmasi, subtree cuma
  menyentuh `frontend/`.

### 2. Konfigurasi Next.js untuk static export — SELESAI (14 Agustus 2026)

- [x] `frontend/next.config.ts`: tambah `output: 'export'`. `images: { unoptimized: true }`
  **tidak** ditambahkan — dicek dulu, tidak ada import `next/image` di kode
  (`app/`, `components/`) saat ini, jadi opsi itu belum perlu.
- [x] `NEXT_PUBLIC_API_URL=/api/v1` diset lewat `frontend/.env.production` (Next.js
  memuat file ini otomatis hanya untuk `next build`/`next start`, `.env.development`
  untuk `next dev` — jadi default dev `http://127.0.0.1:8000/api/v1` di `lib/api/client.ts`
  tetap dipakai saat `npm run dev`, tidak perlu diubah). Ditambahkan pengecualian di
  `frontend/.gitignore` (`!.env.production`) supaya file ini ikut ter-commit — isinya
  bukan rahasia (prefix `NEXT_PUBLIC_*` toh sudah publik di bundle klien).
- [x] `npm ci` + `npm run build` dijalankan lokal — sukses, semua 13 route
  ter-prerender sebagai static content (`○ (Static)`), tidak ada halaman yang butuh
  SSR/dynamic. `frontend/out/` lengkap: `index.html`, `login.html`, `chat.html`,
  `dashboard.html`, dst. per-route sesuai ekspektasi static export.

### 3. Serve hasil build dari Laravel

- [ ] Tentukan target salin: `frontend/out/*` → `public/` (atau subpath) repo ini.
  Static export Next menghasilkan file per-route (`dashboard/index.html`, dst.),
  jadi **tidak perlu** catch-all route SPA di `routes/web.php` — file diserve apa
  adanya oleh web server.
- [ ] Audit tabrakan path di `public/`: pastikan output Next tidak menimpa
  `public/storage` (symlink upload, lihat aturan upload di `CLAUDE.md`),
  `public/build` (aset Vite untuk `docs.blade.php`), atau prefix `/api`.
- [ ] Pastikan `routes/api.php` tetap satu-satunya pemilik prefix `/api/*` — tidak
  ada file statis FE yang kebetulan menempati path itu.

### 4. CI/CD

- [ ] Tambah job FE di pipeline CI: `npm ci`, `npm run lint`, `tsc --noEmit`,
  `npm run build` (dengan `NEXT_PUBLIC_API_URL=/api/v1`) — terpisah dari job PHP
  (Pest/Pint) supaya kegagalan salah satu tidak saling menutupi.
- [ ] Tambah step deploy: copy `frontend/out/*` ke `public/` sebelum push/rsync ke
  hPanel. Build **tidak** terjadi di server hPanel (Node runtime di shared hosting
  tidak bisa diandalkan) — hasil build yang dikirim, bukan source Node-nya.

### 5. Dokumentasi

- [ ] Root `CLAUDE.md`: revisi baris pembuka "Tidak ada UI di sini" (sudah tidak
  berlaku), tambahkan pointer ke `frontend/CLAUDE.md` untuk aturan sisi klien.
- [ ] `frontend/CLAUDE.md` (bawa dari repo FE, sesuaikan bagian yang berubah):
  - Base URL dev tetap `http://127.0.0.1:8000/api/v1`, base URL produksi jadi
    relatif `/api/v1`.
  - Hapus referensi "Deploy on Vercel" dari `frontend/README.md` — deploy sekarang
    ikut proses hPanel repo gabungan, bukan Vercel.
  - Pertahankan aturan yang sudah bagus apa adanya: satu pintu `fetch` lewat
    `lib/api/client.ts`, tidak ada logika bisnis di klien, semua turunan (spent
    per wallet, status budget, percent, ETA) dihitung server.
- [ ] `README.md` root: tambahkan bagian struktur folder yang menyebut `frontend/`.

### 6. Beres-beres integrasi yang tertunda (dari `PLAN-INTEGRASI-FRONTEND.md`)

- [ ] Matikan flag `NEXT_PUBLIC_MOCK_AMINA` dan hapus `frontend/lib/mock/assistant.ts`
  — backend sudah menuntaskan SSE `action_card` + `POST /ai-actions/{id}/confirm|reject`
  (lihat item 2.1–2.2 di `PLAN-INTEGRASI-FRONTEND.md`). Ini jauh lebih gampang
  diverifikasi sekarang karena dua sisi ada di satu repo/PR.
  Catatan kontrak yang beda dari desain awal FE (`translateValidation()` di
  `lib/api/client.ts` bisa dihapus — backend sudah kirim pesan Indonesia jadi,
  bukan kunci mentah; event SSE final `thinking → message|error → action_card`,
  bukan `thinking → token* → done|error`).

### 7. Testing

- [ ] Pest tetap jalan seperti biasa untuk BE.
- [ ] FE: pastikan `tsc --noEmit` dan `npm run lint` bersih pasca-`output: 'export'`.
- [ ] Uji manual end-to-end sekali di lingkungan mirip produksi (build static +
  Laravel serve) sebelum cutover — pastikan routing, auth Bearer token, dan
  upload/chat SSE tetap jalan same-origin.

---

## Risiko & mitigasi

| Risiko | Mitigasi |
| --- | --- |
| Ada halaman FE yang ternyata butuh SSR (belum terlihat sekarang) → gagal di `output: 'export'` | Ketahuan langsung saat `npm run build` gagal; tidak ada cara diam-diam lolos ke produksi. |
| Tabrakan path antara file statis Next dan `public/storage` atau aset Vite Laravel | Audit eksplisit di langkah 3 sebelum merge pertama. |
| Kunci `LLM_*`/`ANTHROPIC_*` bocor ke bundle klien lewat env prefix publik | Tidak berubah dari sebelumnya — FE tidak pernah menyentuh env itu; tetap audit `.env`/CI FE tidak pernah mendefinisikan ulang `LLM_*` dengan prefix `NEXT_PUBLIC_*`. |
| Riwayat commit FE hilang kalau pakai copy manual alih-alih `git subtree` | Putuskan strategi merge dulu (lihat bagian "Keputusan") sebelum eksekusi langkah 1. |

---

## Definition of done

1. `frontend/` masuk repo ini dengan histori (atau sesuai strategi yang disepakati).
2. `npm run build` di `frontend/` sukses dengan `output: 'export'`, hasil `out/`
   ke-serve benar dari Laravel di lingkungan lokal (uji manual).
3. CI menjalankan job PHP dan FE terpisah, keduanya hijau.
4. `CLAUDE.md` root + `frontend/CLAUDE.md` sudah direvisi mencerminkan struktur baru.
5. `NEXT_PUBLIC_MOCK_AMINA` mati, `lib/mock/assistant.ts` terhapus, chat pakai data
   sungguhan end-to-end.
6. Deploy sekali ke hPanel berhasil, dicoba manual (login → chat → salah satu CRUD)
   dari domain produksi.
