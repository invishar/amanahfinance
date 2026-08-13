# API v1 — AmanaFinance

Kontrak endpoint REST untuk `amanafinance-api`. Dokumen ini adalah **sumber kebenaran tunggal**: setiap endpoint baru wajib didaftarkan di sini pada PR yang sama.

Base path: `/api/v1`

Versi mesin-terbaca (OpenAPI 3.0.3) dari kontrak yang sama tersedia publik di `GET /api/v1/openapi.json` (`app/OpenApi/OpenApiSpec.php`), dijaga tetap sinkron oleh test `tests/Feature/OpenApiSpecTest.php` yang membandingkan setiap route terdaftar dengan path yang terdokumentasi.

## Autentikasi

Semua endpoint di bawah `/api/v1`, kecuali `POST /auth/register` dan `POST /auth/login`, memerlukan Sanctum bearer token:

```
Authorization: Bearer <token>
```

### Auth endpoints

| Method | Path | Body | Auth |
| --- | --- | --- | --- |
| POST | `/auth/register` | `full_name*`, `email` atau `phone` (salah satu wajib), `password*` (min 8), `password_confirmation` (opsional — kalau dikirim harus cocok dengan `password`, kalau tidak dikirim tidak diwajibkan) | Publik, throttle 10/menit |
| POST | `/auth/login` | `email` atau `phone` (salah satu wajib), `password*` | Publik, throttle 10/menit |
| GET | `/auth/me` | — | Bearer token |
| POST | `/auth/logout` | — | Bearer token — mencabut token yang dipakai di request ini saja (device lain tetap login) |

Response `register`/`login` (`201`/`200`):

```json
{ "data": { "user": { "id": "...", "full_name": "...", "email": "...", "phone": null, "avatar_url": null, "created_at": "..." }, "token": "1|xxxxxxxx..." } }
```

Response `login` gagal:
- `401` `{ "message": "Email/telepon atau kata sandi salah." }` — email/phone valid secara format tapi user tidak ada atau password salah. Pesan sengaja generik, tidak membedakan "user tidak ada" vs "password salah" untuk mencegah user enumeration.
- `422` — bentuk input salah (mis. `email` maupun `phone` sama-sama kosong, atau `password` tidak dikirim) — format error validasi standar.

Setelah register/login, klien memanggil `GET /families` untuk melihat family yang sudah dimiliki, atau `POST /families` untuk membuat family pertama (lihat bagian Families).

## Header `X-Family-Id`

Sebagian besar resource beroperasi dalam konteks satu family. Family aktif ditentukan oleh middleware `ResolveFamily`:

1. Ambil semua `family_members` milik user yang login (`removed_at is null`).
2. Jika header `X-Family-Id` dikirim, family tersebut harus ada di antara membership user itu — kalau tidak, `403`.
3. Jika header tidak dikirim, dipakai membership pertama user.
4. Jika user tidak punya membership sama sekali → `403`.

`family_id` **tidak pernah** diambil dari body request — hanya dari resolusi di atas. Endpoint `Family` (`/families`) sendiri adalah pengecualian karena dia adalah akar tenant (lihat bagian Families).

**Ini juga mekanisme resmi untuk "pilih family aktif"** pada user dengan >1 family — tidak
perlu endpoint terpisah. Alurnya: klien simpan `family_id` pilihan user (mis. di
local storage) begitu dipilih dari hasil `GET /families`, lalu kirim sebagai
`X-Family-Id` di **setiap** request berikutnya. Tidak ada preferensi tersimpan di
server (mis. "family default terakhir dipakai") — kalau header tidak dikirim, fallback-nya
cuma "membership pertama user" (urutan DB, bukan pilihan user), jadi klien yang harus
selalu mengirim headernya begitu punya pilihan tersimpan.

## Bentuk respons

Sukses (single resource):

```json
{ "data": { "...": "..." } }
```

Sukses (list, dengan pagination):

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 20, "total": 3, "...": "..." }
}
```

Error validasi (422) & error umum:

```json
{ "message": "Nama wajib diisi.", "errors": { "name": ["Nama wajib diisi."] } }
```

```json
{ "message": "Human readable message." }
```

Semua pesan (`message` maupun isi `errors`) sudah kalimat Bahasa Indonesia siap tampil
(`lang/id/validation.php`, `lang/id/pagination.php` untuk `meta.links[].label`), bukan
kunci mentah seperti `"validation.required"` — `message` di atas **adalah** pesan error
field pertama (perilaku bawaan Laravel: `ValidationException::summarize()`), bukan string
generik. Satu sudut kasar yang belum ditangani: kalau lebih dari satu field gagal
sekaligus, `message` menambahkan akhiran `"(and N more errors)"` dalam Bahasa Inggris
(quirk `ValidationException` sendiri) — `errors` per field tetap sepenuhnya Indonesia.

Kode status yang dipakai secara konsisten:

- `401` — tidak terautentikasi.
- `403` — terautentikasi tapi tidak diizinkan (Policy menolak, atau `X-Family-Id` invalid).
- `404` — resource tidak ditemukan **atau** milik family lain (global scope Eloquent menyembunyikannya; ini yang mencegah kebocoran tenant di sebagian besar resource).
- `409` — operasi delete diblokir oleh FK `restrictOnDelete` (mis. account masih dipakai transaksi).
- `422` — validasi gagal.

## Konvensi tipe data

- **Uang**: integer rupiah penuh (bigint), tidak pernah desimal/float.
- **ID**: UUID v7 string di semua resource.
- **Timestamp** (`created_at`, `updated_at`, `*_at`): ISO-8601 UTC, mis. `2026-08-10T13:27:33.000000Z`.
- **Tanggal murni** (`transaction_date`, `deadline`, `next_run_on`, `period`): `YYYY-MM-DD`, tanpa komponen waktu/timezone.
- Role family: `admin` | `member` | `viewer`.

## Otorisasi per role (default di seluruh resource kecuali disebutkan lain)

| Aksi | Role minimum |
| --- | --- |
| Lihat (index/show) | `viewer` |
| Buat/ubah (create/update) | `member` |
| Hapus (delete) | `admin` |

Pengecualian dicatat per resource di bawah.

---

## Families

Akar tenant. Tidak berada di bawah `X-Family-Id` — `store` dipakai untuk membuat family pertama kali (sebelum family manapun terpilih), dan `show`/`update`/`destroy` diotorisasi langsung lewat membership user pada `{family}` di URL, terlepas dari header.

| Method | Path | Body | Catatan |
| --- | --- | --- | --- |
| GET | `/families` | — | List family milik user yang login saja. |
| POST | `/families` | `name*` (string), `currency` (3 huruf, default `IDR`), `timezone` (default `Asia/Jakarta`) | User pembuat otomatis jadi `family_members.role=admin`. Server juga langsung membuat `ChatThread kind=onboarding` berisi sapaan + pertanyaan pertama Amina (lihat bagian Onboarding Answers) — naskahnya tidak pernah dikirim dari klien. |
| GET | `/families/{family}` | — | 403 jika user bukan member family ini. |
| PUT/PATCH | `/families/{family}` | `name`, `currency`, `timezone`, `onboarding_done` (semua opsional) | Admin only. |
| DELETE | `/families/{family}` | — | Admin only. Cascade ke seluruh data family (FK `cascadeOnDelete`). |

Response `data`: `id, name, currency, timezone, onboarding_done, created_at, updated_at`.

---

## Family Members

Butuh `X-Family-Id` (di bawah `resolve.family`). Mengelola role/keanggotaan adalah aksi admin — bukan `member` — untuk mencegah eskalasi privilege.

| Method | Path | Body/Query | Role |
| --- | --- | --- | --- |
| GET | `/family-members` | `?role=` (`admin`\|`member`\|`viewer`), `?per_page=` (default 20, maks 100) — opsional | `viewer` |
| POST | `/family-members` | `user_id*` (uuid, harus user terdaftar), `role*` (`admin`\|`member`\|`viewer`), `nickname`, `monthly_quota` (int) | **`admin`** |
| GET | `/family-members/{family_member}` | — | `viewer` |
| PUT/PATCH | `/family-members/{family_member}` | `role`, `nickname`, `monthly_quota` | **`admin`** |
| DELETE | `/family-members/{family_member}` | — | **`admin`** — soft-remove: set `removed_at`, baris tidak dihapus. |

Response `data`: `id, family_id, user_id, role, nickname, monthly_quota, joined_at, removed_at, user: { id, full_name, avatar_url }`.

---

## Family Invites

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/family-invites` | — | `viewer` |
| POST | `/family-invites` | `email` atau `phone` (salah satu wajib), `role` (default `member`) | **`admin`** |
| GET | `/family-invites/{family_invite}` | — | `viewer` |
| PUT/PATCH | `/family-invites/{family_invite}` | `role`, `expires_at` | **`admin`** |
| DELETE | `/family-invites/{family_invite}` | — | **`admin`** (revoke) |
| POST | `/family-invites/accept` | `token*` | Bearer token saja — **di luar `resolve.family`**, lihat di bawah |

`token` (`AMANA-XXXXXX`) dan `expires_at` (+7 hari) dibuat di server. `invited_by` = user yang login.

### Menerima invite

`POST /family-invites/accept` sengaja berada di luar middleware `resolve.family` — user yang menerima belum jadi anggota family tujuan, jadi tidak ada family aktif untuk di-resolve. Body hanya `{ "token": "AMANA-XXXXXX" }`. Response `201` berisi `FamilyMemberResource` (family_id, role, dst — lihat bagian Family Members) untuk membership baru yang dibuat.

Semua kegagalan mengembalikan `422` dengan error di field `token` (bukan `403`/`404`, supaya token yang salah/dicuri tidak membocorkan info soal keberadaannya):

- Token tidak ditemukan.
- Invite sudah dipakai (`accepted_at` sudah terisi).
- Invite sudah kedaluwarsa (`expires_at` sudah lewat).
- `email`/`phone` di invite tidak cocok dengan akun yang sedang login (pencocokan email case-insensitive).
- User sudah jadi anggota aktif family tersebut.

Response `data` (untuk endpoint selain `accept`): `id, family_id, invited_by, email, phone, role, token, expires_at, accepted_at, created_at`.

---

## Accounts

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/accounts` | — | `viewer` |
| POST | `/accounts` | `name*` (unik per family), `account_type*` (`bank`\|`ewallet`\|`cash`\|`other`), `institution`, `masked_number`, `opening_balance` (int, default 0), `owner_member_id`, `is_shared`, `is_archived`, `sort_order` | `member` |
| GET | `/accounts/{account}` | — | `viewer` |
| PUT/PATCH | `/accounts/{account}` | Sama seperti store minus `opening_balance`/`current_balance` (tidak bisa diubah — hanya lewat transaksi) | `member` |
| DELETE | `/accounts/{account}` | — | `admin`; **409** jika masih direferensikan transaksi (arsipkan lewat `is_archived` alih-alih hapus) |

`current_balance` diisi = `opening_balance` saat create, lalu hanya berubah lewat efek Transaction.

Response `data`: `id, family_id, name, account_type, institution, masked_number, opening_balance, current_balance, owner_member_id, is_shared, is_archived, sort_order, created_at`.

---

## Wallets

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/wallets` | — | `viewer` |
| POST | `/wallets` | `name*` (unik per family), `icon`, `color`, `monthly_budget` (int, default 0), `rollover` (bool), `is_archived`, `sort_order` | `member` |
| GET | `/wallets/{wallet}` | — | `viewer` |
| PUT/PATCH | `/wallets/{wallet}` | Sama seperti store, semua opsional | `member` |
| DELETE | `/wallets/{wallet}` | — | `admin`; **409** jika masih direferensikan transaksi |

Response `data`: `id, family_id, name, icon, color, monthly_budget, rollover, is_archived, sort_order, created_at`.

---

## Wallet Budgets

Nested di bawah wallet (`index`/`store`); shallow untuk `show`/`update`/`destroy`.

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/wallets/{wallet}/budgets` | — | `viewer` |
| POST | `/wallets/{wallet}/budgets` | `period*` (date, dinormalisasi ke tgl 1 bulan tsb), `amount*` (int) | `member` |
| GET | `/budgets/{budget}` | — | `viewer` |
| PUT/PATCH | `/budgets/{budget}` | `period`, `amount` | `member` |
| DELETE | `/budgets/{budget}` | — | `admin` |

Response `data`: `id, wallet_id, period, amount, created_at`.

---

## Income Sources

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/income-sources` | — | `viewer` |
| POST | `/income-sources` | `name*` (unik per family), `owner_member_id`, `expected_amount` (int), `cadence` (`monthly`\|`biweekly`\|`weekly`\|`irregular`), `is_archived` | `member` |
| GET | `/income-sources/{income_source}` | — | `viewer` |
| PUT/PATCH | `/income-sources/{income_source}` | Sama seperti store, semua opsional | `member` |
| DELETE | `/income-sources/{income_source}` | — | `admin`; **409** jika masih direferensikan transaksi |

Response `data`: `id, family_id, name, owner_member_id, expected_amount, cadence, is_archived, created_at`.

---

## Savings Goals

| Method | Path | Body/Query | Role |
| --- | --- | --- | --- |
| GET | `/savings-goals` | `?status=` (`active`\|`achieved`\|`paused`\|`cancelled`), `?per_page=` (default 20, maks 100) — opsional | `viewer` |
| POST | `/savings-goals` | `target_name*`, `target_amount*` (int > 0), `deadline`, `icon`, `color`, `account_id`, `status` (default `active`) | `member` |
| GET | `/savings-goals/{savings_goal}` | — | `viewer` |
| PUT/PATCH | `/savings-goals/{savings_goal}` | Sama seperti store minus `current_amount` (cache, tidak bisa diubah langsung) | `member` |
| DELETE | `/savings-goals/{savings_goal}` | — | `admin`; **409** jika masih direferensikan transaksi (pakai `status=cancelled` alih-alih hapus) |

Mengubah `status` menjadi `achieved` otomatis mengisi `achieved_at`; mengubahnya ke status lain mengosongkan `achieved_at`.

Response `data`: `id, family_id, target_name, target_amount, current_amount, percent (0-100, dihitung server), deadline, eta, icon, color, account_id, status, created_at, achieved_at`.

`eta` (`YYYY-MM-DD`, awal bulan — estimasi, bukan tanggal presisi) diproyeksikan server dari
rata-rata kontribusi bulanan sejak setoran (`transactions.type=savings`) pertama ke goal ini,
linear terhadap sisa `target_amount - current_amount`. `null` kalau: `status` bukan `active`,
`current_amount` sudah `>= target_amount`, atau belum ada histori setoran sama sekali untuk
diproyeksikan (server sengaja tidak menebak-nebak tanpa data).

---

## Transactions

`transactions` adalah sumber kebenaran ledger — lihat aturan #4 di `CLAUDE.md`. Field wajib berbeda per `type`, direplikasi dari CHECK constraint di DB:

| `type` | Field wajib tambahan |
| --- | --- |
| `expense` | `wallet_id` |
| `income` | `source_id` |
| `transfer` | `to_account_id` (harus beda dari `account_id`) |
| `savings` | `goal_id` |

`account_id` selalu wajib untuk semua tipe. `amount` harus `> 0`.

| Method | Path | Body/Query | Role |
| --- | --- | --- | --- |
| GET | `/transactions` | `?month=YYYY-MM`, `?wallet_id=`, `?account_id=`, `?type=`, `?per_page=` (default 20, maks 100) — semua opsional, bisa digabung | `viewer` |
| POST | `/transactions` | `type*`, `amount*` (int), `transaction_date*` (date), `account_id*`, + field wajib per tipe di atas, `note`, `receipt_url` | `member` |
| GET | `/transactions/{transaction}` | — | `viewer` |
| PUT/PATCH | `/transactions/{transaction}` | Field yang sama, semua opsional (partial update); jika `type` diganti, foreign key yang tidak relevan otomatis dikosongkan | `member` |
| DELETE | `/transactions/{transaction}` | — | `member` (soft delete — bukan admin-only, ini penggunaan sehari-hari untuk koreksi kesalahan input) |

`origin` dan `created_by` **tidak bisa** dikirim dari klien: `origin` selalu `manual` untuk endpoint ini (origin lain seperti `chat_text`/`receipt_ocr` hanya ditulis oleh job internal), `created_by` selalu diisi dari member yang login.

Efek saldo (dalam satu DB transaction dengan penulisan baris):

- `expense` → `accounts.current_balance -= amount` (pada `account_id`)
- `income` → `accounts.current_balance += amount`
- `transfer` → `account_id -= amount`, `to_account_id += amount`
- `savings` → `account_id -= amount`, `savings_goals.current_amount += amount` (pada `goal_id`)

Update membalik efek lama lalu menerapkan efek baru; delete (soft delete) membalik efek sepenuhnya.

Response `data`: `id, family_id, type, amount, transaction_date, account_id, to_account_id, wallet_id, source_id, goal_id, note, created_by, origin, receipt_url, created_at, updated_at`.

Urutan baku (tidak dipengaruhi filter): `transaction_date desc, created_at desc` — cukup stabil
untuk diandalkan halaman pertama sebagai "transaksi terbaru" di dashboard. `?month=` memfilter
`transaction_date` dalam rentang bulan kalender itu (bukan `created_at`). `?account_id=` hanya
mencocokkan `transactions.account_id` (sisi asal) — transaksi `transfer` yang **masuk** ke akun
itu lewat `to_account_id` tidak ikut terfilter oleh parameter ini.

---

## Recurring Rules

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/recurring-rules` | — | `viewer` |
| POST | `/recurring-rules` | `type*` (`income`\|`expense`\|`savings`), `amount*` (int), `wallet_id` (wajib jika `expense`), `source_id` (wajib jika `income`), `account_id` (wajib jika `savings`), `note`, `rrule*` (string RRULE), `next_run_on*` (date), `is_active` | `member` |
| GET | `/recurring-rules/{recurring_rule}` | — | `viewer` |
| PUT/PATCH | `/recurring-rules/{recurring_rule}` | Sama seperti store, semua opsional; ganti `type` otomatis mengosongkan FK yang tidak relevan | `member` |
| DELETE | `/recurring-rules/{recurring_rule}` | — | `admin` |

Endpoint ini hanya CRUD definisi aturan — eksekusi terjadwal (membuat `transactions` nyata) adalah job terpisah, di luar cakupan dokumen ini.

Response `data`: `id, family_id, type, amount, wallet_id, source_id, account_id, note, rrule, next_run_on, is_active, created_at`.

---

## Chat Threads

| Method | Path | Body/Query | Role |
| --- | --- | --- | --- |
| GET | `/chat-threads` | `?kind=` (`general`\|`onboarding`), `?per_page=` (default 20, maks 100) — opsional | `viewer` |
| POST | `/chat-threads` | `title`, `kind` (`general`\|`onboarding`, default `general`) | `member` |
| GET | `/chat-threads/{chat_thread}` | — | `viewer` |
| PUT/PATCH | `/chat-threads/{chat_thread}` | `title`, `kind` | `member` |
| DELETE | `/chat-threads/{chat_thread}` | — | `member` |

`member_id` selalu diisi dari member yang login, tidak bisa dikirim lewat body.

Response `data`: `id, family_id, member_id, title, kind, last_message_at, created_at, onboarding`.

`onboarding` adalah `{ step, total, done }` (jumlah pertanyaan naskah onboarding &
progres keluarga ini menjawabnya) untuk thread `kind=onboarding`, dan `null` untuk
thread `kind=general`.

---

## Chat Messages

Nested di bawah thread; **read-only setelah dibuat** (tidak ada `update`/`destroy` — riwayat chat tidak bisa diedit).

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/chat-threads/{chat_thread}/messages` | — | `viewer` |
| POST | `/chat-threads/{chat_thread}/messages` | `content` (wajib jika tidak ada `attachment_url`), `input_mode` (`text`\|`voice`\|`image`), `attachment_url` | `member` |
| GET | `/messages/{message}` | — | `viewer` |

`role` selalu dipaksa `user` untuk pesan yang dibuat lewat endpoint ini — balasan `assistant`/`system` hanya ditulis oleh `AssistantService` (alur AI, lihat `CLAUDE.md`), bukan lewat API publik ini. Membuat pesan memperbarui `chat_threads.last_message_at` dan mengantre `ProcessAssistantMessage` (balasan Amina serta `action_card` apa pun muncul belakangan lewat SSE di bawah, bukan di response `POST` ini).

Response `data`: `id, thread_id, role, content, input_mode, attachment_url, token_usage, created_at`.

---

## Chat Stream (SSE)

Server-Sent Events **berumur pendek** (CLAUDE.md "Alur AI"): server menutup koneksi sendiri sebelum ±20-25 detik ketimbang menahannya tanpa batas, supaya aman dari `max_execution_time` shared hosting. Tidak ada Redis/pub-sub di baliknya — endpoint ini polling DB tiap ~500ms selama stream masih terbuka. Klien **wajib reconnect** begitu koneksi ditutup, memakai cursor dari event `retry` terakhir.

| Method | Path | Query | Role |
| --- | --- | --- | --- |
| GET | `/chat-threads/{chat_thread}/stream` | `after` (opsional, ISO-8601; default waktu koneksi dibuka) | `viewer` |

Event yang dikirim (`Content-Type: text/event-stream`):

| Event | `data` | Kapan |
| --- | --- | --- |
| `thinking` | `message_id` | Sekali di awal koneksi/reconnect, kalau pesan terbaru di thread ini masih `role=user` yang belum dibalas |
| `message` | `id, content, created_at` | Ada balasan baru `role=assistant` di thread ini sejak cursor |
| `action_card` | `id, action, payload, created_at` | Ada `ai_actions` baru berstatus `pending` dari pesan di thread ini sejak cursor |
| `error` | `id, content, created_at` | Ada pesan baru `role=system` di thread ini sejak cursor — ditulis `ProcessAssistantMessage::failed()` saat job LLM gagal total (habis retry) |
| `retry` | `after` | Selalu dikirim tepat sebelum stream ditutup — pakai nilai ini sebagai `?after=` saat reconnect |

Tidak ada event `token` (balasan LLM ditulis sekali jadi, bukan streaming token-by-token —
`ProcessAssistantMessage` memanggil LLM satu kali dalam job, bukan di request web) maupun
`done` terpisah — `message` dan `error` **adalah** sinyal selesainya satu giliran; hentikan
indikator "sedang mengetik" begitu salah satunya (atau `retry` tanpa keduanya) diterima.
`error` juga tetap muncul di riwayat biasa (`GET .../messages`) kalau klien melewatkan
event live-nya (mis. reconnect terlambat).

Klien **wajib dedupe berdasarkan `id`**: cursor resume di event `retry` adalah yang **paling lama** di antara kedua cursor internal (`message` dan `action_card`), jadi reconnect bisa mengirim ulang satu event yang sudah pernah diterima di stream sebelumnya.

`GET /chat-threads/{chat_thread}/stream` memakai policy `view` pada `ChatThread` yang sama seperti resource chat lain — bukan endpoint terpisah dari isolasi tenant.

---

## Uploads

Menghasilkan URL untuk attachment chat (foto struk, rekaman suara). Endpoint ini **hanya
menyimpan berkas** dan mengembalikan URL-nya — tidak melakukan OCR struk atau
speech-to-text. Alurnya: `POST /uploads` dulu untuk dapat `url`, lalu kirim `url` itu
sebagai `attachment_url` saat `POST /chat-threads/{chat_thread}/messages`.

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| POST | `/uploads` | `file*` (multipart, gambar atau audio) | `member` |

Batas: `AMINA_UPLOAD_MAX_KB` (default 15360 KB / 15 MB), mime diizinkan gambar
(`jpg`, `jpeg`, `png`, `webp`, `heic`) atau audio (`mp3`, `wav`, `m4a`, `ogg`, `webm`,
`aac`).

Response `data` (`201`): `url, mime, size` (byte).

---

## Onboarding Answers

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/onboarding-answers` | — | `viewer` |
| POST | `/onboarding-answers` | `question_key*` (unik per family), `answer` (object/array, nullable), `skipped` (bool) | `member` |
| GET | `/onboarding-answers/{onboarding_answer}` | — | `viewer` |
| PUT/PATCH | `/onboarding-answers/{onboarding_answer}` | `answer`, `skipped` (`question_key` tidak bisa diubah — identitas jawaban) | `member` |
| DELETE | `/onboarding-answers/{onboarding_answer}` | — | `admin` |

Response `data`: `id, family_id, question_key, answer, skipped, answered_at`.

Naskah pertanyaan (`question_key` yang valid & urutannya) hidup di server
(`config('amina.onboarding_questions')`), **bukan** di klien. Setiap `POST` yang
berhasil (baik `skipped=true` maupun jawaban asli) memicu server:

1. Cari `question_key` pertama di naskah yang belum ada di `onboarding_answers` family
   ini.
2. Kalau masih ada — tulis pesan `role=assistant` baru berisi pertanyaan itu ke
   `ChatThread kind=onboarding` milik family (muncul di klien lewat SSE/polling
   seperti balasan Amina lainnya).
3. Kalau sudah tidak ada — tandai `families.onboarding_done = true`. Klien tidak
   perlu (dan sebaiknya tidak) menyalakan `onboarding_done` sendiri lewat
   `PUT /families/{family}`.

---

## Notifications

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/notifications` | — | `viewer` |
| POST | `/notifications` | `member_id`, `kind*` (`budget_warning`\|`goal_progress`\|`bill_due`\|`weekly_digest`), `title*`, `body`, `deeplink` | **`admin`** (notifikasi normal dibuat oleh job harian, bukan user) |
| GET | `/notifications/{notification}` | — | `viewer` |
| PUT/PATCH | `/notifications/{notification}` | `read_at` (dipakai untuk tandai baca/belum) | `viewer` ke atas — semua member boleh menandai baca notifikasi mereka sendiri |
| DELETE | `/notifications/{notification}` | — | `viewer` ke atas |

Response `data`: `id, family_id, member_id, kind, title, body, deeplink, read_at, created_at`.

---

## AI Actions

Jejak audit AI → data nyata (lihat "Alur AI" di `CLAUDE.md`). Tidak ada `store`/`update`/`destroy` generik: AI tidak pernah menulis tabel bisnis, dan baris `ai_actions` sendiri hanya diubah lewat `confirm`/`reject` di bawah (keduanya delegasi ke `ConfirmAiAction`) — tidak pernah dihapus.

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/ai-actions` | — | `viewer` |
| GET | `/ai-actions/{ai_action}` | — | `viewer` |
| POST | `/ai-actions/{ai_action}/confirm` | (opsional) field pengganti sebagian dari `payload` draft, mis. `{"amount": 25000}` | `member` |
| POST | `/ai-actions/{ai_action}/reject` | — | `member` |

Response `data`: `id, message_id, family_id, action, payload, status, result_table, result_id, confidence, resolved_at, resolved_by, created_at`.

`confirm` menulis baris nyata sesuai `action`: `create_transaction`→`transactions` (origin otomatis `chat_text`/`chat_voice`/`receipt_ocr` dari `input_mode` pesan asal, bukan `manual`), `create_wallet`→`wallets`, `create_account`→`accounts`, `create_income_source`→`income_sources`, `create_savings_goal`→`savings_goals`. `action=advice` tidak menulis apa pun — `confirm` di situ cuma menandai kartu saran sudah "diakui" (`result_table`/`result_id` tetap `null`).

Body `confirm` opsional: field apa pun di dalamnya menimpa `payload` draft sebelum divalidasi & ditulis (mis. user mengoreksi `amount` sebelum konfirmasi). Ada perubahan → `status` jadi `edited`; tanpa body → `confirmed`. Semua id di `payload`/body (`account_id`, `wallet_id`, dst.) divalidasi ulang tervalidasi milik family yang sama seperti `ai_action`-nya — tidak pernah dipercaya mentah dari draft LLM maupun dari body request. Field yang wajib untuk `action` terkait tapi masih `null` di draft (NameResolver ragu, lihat "Alur AI") membuat `confirm` gagal `422` sampai user melengkapinya lewat body.

`confirm`/`reject` pada draft yang sudah tidak `pending` (`confirmed`/`edited`/`rejected`/`expired`) gagal `422`.

---

## Audit Logs — read only

Jejak audit umum. Tidak ada `store`/`update`/`destroy` lewat API — baris ditulis oleh proses internal saat entity lain berubah.

| Method | Path | Role |
| --- | --- | --- |
| GET | `/audit-logs` | `viewer` |
| GET | `/audit-logs/{audit_log}` | `viewer` |

Response `data`: `id, family_id, actor_id, entity, entity_id, action, diff, created_at`.

---

## LLM Settings — platform admin only

Kredensial LLM platform-wide (aturan #7), **bukan** resource per-family — tidak berada di bawah `resolve.family` maupun `X-Family-Id`. Otorisasi lewat `users.is_platform_admin` (kolom terpisah dari `family_members.role`; admin family manapun **tidak** otomatis punya akses ini). Flag ini hanya bisa di-set manual (tinker/seeder), tidak ada endpoint self-service untuk menaikkan privilege.

Singleton: hanya ada satu baris `llm_settings` yang berlaku untuk seluruh platform. Tidak ada `index`/`store`/`destroy` — cuma `show`/`update` pada "the" settings.

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/llm-settings` | — | `is_platform_admin` |
| PUT | `/llm-settings` | `key` (opsional; kosongkan untuk mempertahankan key lama), `model*`, `base_url` (opsional) | `is_platform_admin` |

`key` **tidak pernah** dikembalikan lewat response — disimpan ter-enkripsi (Laravel `encrypted` cast, pakai `APP_KEY`) dan hanya diekspos sebagai `has_key` (boolean) + `key_preview` (4 karakter terakhir, untuk verifikasi visual saja). Sebelum baris DB pernah dibuat, `GET` menampilkan fallback dari `.env` (`LLM_API_KEY`/`LLM_MODEL`/`LLM_BASE_URL`) supaya admin tahu apa yang sedang efektif dipakai.

`AssistantService` membaca setting ini **dinamis di setiap pemanggilan** (bukan di-cache lintas request) — ganti `model`/`key`/`base_url` langsung berlaku tanpa restart/redeploy.

Response `data`: `model, base_url, has_key, key_preview, updated_at, updated_by`.

---

## Analytics — read only

Sumber data **wajib** view `v_wallet_month` / `v_cashflow_month` (aturan CLAUDE.md: "bukan query ad-hoc"). Tidak pernah memanggil LLM di sini — `insight` (komentar naratif dari AI) di-cache oleh job harian terpisah dan **belum** termasuk dalam response ini (job tersebut belum diimplementasikan).

| Method | Path | Query | Role |
| --- | --- | --- | --- |
| GET | `/analytics/summary` | `month` (opsional, format `YYYY-MM`, default bulan berjalan) | `viewer` |

Response `data`:

```json
{
  "period": "2026-08-01",
  "cashflow": {
    "total_income": 5000000,
    "total_expense": 1200000,
    "total_savings": 500000,
    "net": 3800000
  },
  "wallets": [
    {
      "wallet_id": "...",
      "name": "Makan & Minum",
      "icon": "wallet",
      "color": "#00bbff",
      "budget": 1500000,
      "spent": 1200000,
      "remaining": 300000,
      "percent": 80,
      "status": "warning"
    }
  ],
  "income_sources": [
    {
      "source_id": "...",
      "name": "Gaji Bulanan",
      "expected": 8000000,
      "actual": 8000000
    }
  ]
}
```

- `wallets` mencakup **semua wallet aktif** (`is_archived=false`) family, termasuk yang belum ada transaksi bulan ini (`spent=0`).
- `budget` mengikuti override `wallet_budgets` bulan berjalan bila ada, jika tidak jatuh ke `wallets.monthly_budget`. Catatan kuirk dari view: override budget hanya berlaku untuk **bulan kalender saat ini** (`curdate()`) — memanggil dengan `month` bulan lampau tetap memakai budget bulan berjalan sebagai pembandingnya, bukan budget historis bulan itu.
- `percent` = `min(spent, budget) / budget * 100`, dibulatkan; `0` jika `budget<=0`.
- `status`: `no_budget` (budget ≤ 0), `over` (≥100%), `warning` (≥80%), `ok` (selain itu).
- `net` = `total_income - total_expense`.
- `income_sources` mencakup **semua sumber pemasukan aktif** (`is_archived=false`) family, termasuk yang belum ada transaksi bulan ini (`actual=0`). `expected` = `income_sources.expected_amount` apa adanya (bisa `null` kalau belum diisi — beda dari `0`, yang berarti "diperkirakan tidak ada pemasukan"). `actual` = total `transactions.amount` bertipe `income` dengan `source_id` ini di bulan `period` (dari view `v_income_source_month`, mengikuti pola `v_wallet_month`/`v_cashflow_month`; tidak ada quirk `curdate()` seperti `wallets` karena `expected_amount` bukan nilai per-bulan).

---

## Belum diimplementasikan (di luar cakupan dokumen ini)

- Job terjadwal `recurring_rules`.
- Job harian yang mengisi `insight` naratif untuk `GET /analytics/summary`.
