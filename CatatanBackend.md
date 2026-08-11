# Catatan untuk Backend — dari integrasi `amanafinance-web`

Ditulis 12 Agustus 2026 setelah frontend disambungkan ke `amanafinance-api` (lihat [TaskProject.md](TaskProject.md)).
Semua temuan di bawah berasal dari **memanggil API langsung** di `http://127.0.0.1:8000/api/v1`, bukan dari membaca spec saja. Item yang belum sempat diuji ditandai jelas di §5.

Ringkasan: auth, family, CRUD, dashboard, dan analisa **sudah jalan penuh**. Yang menahan produk adalah bagian AI/chat — layar utama aplikasi ini.

---

## 1. Yang sudah benar — mohon jangan diubah

Frontend sudah bergantung pada bentuk-bentuk ini:

| Kontrak | Bentuk |
| --- | --- |
| Envelope list | `{ data: [], links, meta }` + `?page=` |
| Envelope tunggal | `{ data: {} }` |
| Uang | `integer` di semua schema (tanpa desimal) |
| Auth | Sanctum bearer; `POST /auth/login` menerima email **atau** phone |
| Scope family | Ikut token, tanpa header tambahan |
| Belum punya family | `403 { "message": "Akun ini belum tergabung dalam family manapun." }` — dipakai frontend untuk mengarahkan ke onboarding |
| `GET /analytics/summary?month=` | `cashflow { total_income, total_expense, total_savings, net }` + `wallets[] { budget, spent, remaining, percent, status }` |
| `SavingsGoal.percent` | Dihitung server |
| Kode undangan | `token` bergaya `AMANA-AB12CD` |

Perubahan pada daftar di atas = perubahan yang merusak (breaking). Tolong kabari dulu.

---

## 2. P0 — Blocker: chat & AI belum ada isinya

Ini menghentikan fitur inti. Layar chat sekarang **mengirim & memuat pesan user lewat API sungguhan**, tapi balasan Amina masih tiruan di klien di balik flag `NEXT_PUBLIC_MOCK_AMINA`, dan headernya menampilkan tanda "Balasan demo" supaya tidak ada yang mengira itu jawaban asli.

### 2.1 `POST /chat-threads/{id}/messages` tidak pernah membalas

**Yang terjadi**: request mengembalikan `201` seketika berisi pesan user saja. `GET /chat-threads/{id}/messages` setelahnya hanya berisi pesan user. Tidak ada baris `role: assistant`, `GET /ai-actions` tetap `{ data: [] }`.

**Dampak**: chat tidak bisa dipakai untuk apa pun. Seluruh nilai produk ("cerita aja, nanti aku catat") belum ada.

**Yang dibutuhkan** — pilih salah satu, tolong beri tahu yang mana:

- **A. Sinkron** — balasan ikut di response, paling cepat dibuat, tapi user menunggu tanpa umpan balik selama LLM berpikir:
  ```json
  { "data": { "user_message": { }, "assistant_message": { }, "ai_action": { } } }
  ```
- **B. Asinkron + polling** — `202` lalu frontend memantau `GET /chat-threads/{id}/messages`. Butuh penanda status supaya indikator "sedang mengetik" tahu kapan berhenti.
- **C. SSE** (yang diasumsikan desain): event `thinking` → `token`* → `action_card` → `done` | `error`. Paling enak dipakai, indikator mengetik dipicu event `thinking` — bukan timer palsu.

Kalau C, tolong dokumentasikan di `openapi.json` (content-type, nama event, bentuk payload tiap event) karena sekarang belum ada sama sekali.

### 2.2 Tidak ada endpoint confirm/reject `ai_actions`

**Yang terjadi**: spec hanya punya `GET /ai-actions` dan `GET /ai-actions/{id}`. Schema `AiAction` sudah lengkap (`status: pending|confirmed|edited|rejected|expired`, `payload`, `result_table`, `result_id`), tapi tidak ada cara mengubah statusnya.

**Dampak**: tombol "Ya, lanjutkan" / "Edit" / "Batal" di kartu aksi tidak punya lawan bicara. Aturan produk melarang frontend menulis data langsung dari layar chat, jadi **saya tidak mengakalinya** dengan `POST /transactions` — tombolnya sengaja dibiarkan tidak menyimpan apa-apa dan diberi label demo.

**Usulan kontrak**:
```
POST /ai-actions/{id}/confirm     body opsional: { payload: {...} }  // payload = hasil edit user
POST /ai-actions/{id}/reject
```
Response idealnya `{ data: { ai_action, result } }` dengan `result_table` + `result_id` terisi, supaya frontend tahu cache mana yang perlu di-refresh (`transactions`, `wallets`, `accounts`, `analytics`). Perlu juga perilaku bila aksi sudah `confirmed`/`expired` — `409` dengan pesan yang bisa ditampilkan.

### 2.3 Belum ada endpoint unggah (struk & suara)

`ChatMessage.attachment_url` ada, tapi tidak ada endpoint untuk menghasilkan URL itu. Tombol kamera & mikrofon di composer sekarang hanya mengirim teks penanda.

**Usulan**: `POST /uploads` (multipart) → `{ data: { url, mime, size } }`, plus batas ukuran & tipe yang diizinkan. Sekalian: apakah OCR struk dan speech-to-text dikerjakan server setelah `attachment_url` masuk, atau frontend harus memberi tahu jenisnya lewat `input_mode`?

### 2.4 Wawancara awal belum dikirim server

Ada `/onboarding-answers` dan `ChatThread.kind: onboarding`, tapi tidak ada endpoint yang mengirim **daftar pertanyaannya**. Aturan produk melarang naskah pertanyaan disimpan di klien, tapi sekarang terpaksa ada di `lib/mock/assistant.ts` (demo) supaya alurnya bisa ditunjukkan.

**Usulan**: saat family dibuat, server membuat thread `kind: onboarding` berisi sapaan + pertanyaan pertama sebagai `ChatMessage` biasa, dan menyisipkan pertanyaan berikutnya setiap kali jawaban masuk. Dengan begitu frontend tidak perlu tahu apa-apa soal naskahnya. Tambahkan juga penanda progres (mis. `onboarding.step` / `onboarding.total`) kalau labelnya mau tetap ditampilkan.

---

## 3. P1 — Menghambat fitur yang sudah didesain

### 3.1 Pesan validasi `422` masih kunci mentah

```json
{ "message": "validation.required", "errors": { "name": ["validation.required"] } }
```

**Dampak**: tidak layak ditampilkan ke pengguna. Frontend sementara memetakannya di `translateValidation()` (`lib/api/client.ts`) — daftar kunci yang ditebak manual, gampang ketinggalan.

**Usulan**: kirim kalimat jadi berbahasa Indonesia (`"Nama wajib diisi."`). Sepertinya file terjemahan Laravel belum ter-load / `Accept-Language` belum ditangani. Pemetaan di klien akan dihapus begitu ini beres.

### 3.2 `GET /transactions` tanpa filter

Hanya ada `?page=`. Tidak ada filter bulan, wallet, akun, atau tipe, dan `per_page` tampaknya terkunci di 20.

**Dampak**:
- Dashboard "8 transaksi terbaru" bergantung sepenuhnya pada urutan halaman pertama.
- Layar riwayat transaksi per wallet/bulan belum bisa dibuat.

**Usulan**: `?month=YYYY-MM`, `?wallet_id=`, `?account_id=`, `?type=`, `?per_page=`, dan urutan baku `transaction_date desc, created_at desc`.

### 3.3 Realisasi per sumber pemasukan tidak tersedia

Desain menampilkan "Bulan ini: Rp …" per sumber pemasukan. Tidak ada endpoint maupun field untuk itu; `IncomeSource` hanya punya `expected_amount`. Sementara frontend menampilkan "Perkiraan" saja supaya tidak mengarang angka.

**Usulan**: tambahkan `income_sources[]` di `GET /analytics/summary` dengan `{ source_id, name, expected, actual }` — sejalan dengan cara `wallets[]` sudah bekerja di sana.

### 3.4 Estimasi tercapai & wawasan belum ada

Dua elemen desain yang sekarang **disembunyikan** karena datanya tidak ada:
- Kartu target: "Estimasi tercapai: April 2027" — butuh `eta` / `projected_completion` di `SavingsGoal` (server yang menghitung dari rata-rata kontribusi).
- Layar analisa: kartu "Wawasan dari Amina" — butuh `insights[]` di `analytics/summary` (atau endpoint sendiri).

### 3.5 Saldo akun tidak bisa diubah

`POST /accounts` menerima `opening_balance`, tapi `PUT /accounts/{id}` tidak menerima field saldo apa pun. Frontend menyesuaikan: field saldo hanya muncul saat menambah akun.

**Pertanyaan**: apakah ini disengaja (saldo murni turunan transaksi)? Kalau ya, bagaimana cara pengguna mengoreksi saldo yang meleset — lewat transaksi penyesuaian? Kalau begitu, mungkin perlu tipe transaksi `adjustment` atau endpoint rekonsiliasi.

### 3.6 `password_confirmation` wajib, desain hanya punya satu field sandi

`POST /auth/register` mewajibkan `password_confirmation`. Layar daftar di desain hanya punya Nama, Email, Kata sandi. Sementara frontend mengirim nilai sandi dua kali — artinya validasi itu tidak memberi manfaat apa pun.

**Usulan**: jadikan opsional, atau konfirmasi bahwa desain harus menambah field "Ulangi kata sandi".

---

## 4. P2 — Konsistensi & kenyamanan

- **Login gagal membalas `422`**, bukan `401`. Tidak masalah bagi frontend (sudah ditangani), tapi `401` lebih lazim untuk kredensial salah; `422` sebaiknya khusus bentuk input yang salah.
- **`meta.links[].label` juga kunci mentah** (`"pagination.previous"`, `"pagination.next"`) — sumber masalah yang sama dengan §3.1.
- **Tidak ada filter di `/savings-goals`, `/chat-threads`, `/family-members`** (hanya `?page=`). Belum menghambat, tapi akan terasa saat datanya banyak.
- **Multi-family belum ada jalannya.** Scope ikut token dan tidak ada cara memilih family aktif. Frontend memakai `data[0]` dari `GET /families`. Kalau satu user bisa punya lebih dari satu keluarga, perlu mekanisme resmi (endpoint "set active family", atau query `?family_id=`).
- **`Family.onboarding_done`** bisa ditulis lewat `PUT /families/{id}`. Siapa yang bertanggung jawab menyalakannya — server setelah wawancara selesai, atau frontend? Sekarang tidak ada yang menyentuhnya.
- **Entitas tanpa layar**: `recurring-rules`, `notifications`, `audit-logs`, `llm-settings` sudah ada di API tapi belum ada desainnya. Perlu kejelasan apakah masuk lingkup rilis ini.

---

## 5. Belum terverifikasi — tolong dikonfirmasi

Dua hal ini **belum sempat saya uji** (server dev mati saat pengecekan terakhir), jadi jangan dianggap temuan:

1. **Perilaku `DELETE` untuk entitas yang masih dipakai.** Spec menyebut `409` pada `DELETE /wallets/{id}`. Frontend sudah menyiapkan dialognya, tapi saya belum memancing kasusnya. Yang perlu dipastikan: apakah benar `409`, dan apakah `message`-nya kalimat siap tampil (bukan kunci terjemahan atau pesan teknis).
2. **Urutan baku `GET /transactions`.** Belum diuji dengan beberapa tanggal berbeda. Dashboard mengandalkan halaman pertama berisi transaksi terbaru; kalau urutannya `created_at asc`, tampilannya salah.

---

## 6. Lingkungan uji

- Frontend memanggil `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1` dari `http://localhost:3100`. **CORS sudah benar** (preflight `OPTIONS` dibalas `204`).
- Tipe TypeScript di-generate dari `openapi.json` (`npm run api:types`). Kalau spec berubah, frontend cukup men-generate ulang — jadi tolong jaga `openapi.json` tetap sinkron dengan implementasi, karena itu satu-satunya sumber kontrak yang dipakai.
- Akun uji dibuat lewat `POST /auth/register` dengan email bergaya `fe.e2e+<timestamp>@example.test`. Ada beberapa akun & family sisa pengujian di database lokal; aman dihapus.
