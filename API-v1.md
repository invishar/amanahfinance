# API v1 — AmanaFinance

Kontrak endpoint REST untuk `amanafinance-api`. Dokumen ini adalah **sumber kebenaran tunggal**: setiap endpoint baru wajib didaftarkan di sini pada PR yang sama.

Base path: `/api/v1`

## Autentikasi

Semua endpoint di bawah `/api/v1`, kecuali `POST /auth/register` dan `POST /auth/login`, memerlukan Sanctum bearer token:

```
Authorization: Bearer <token>
```

### Auth endpoints

| Method | Path | Body | Auth |
| --- | --- | --- | --- |
| POST | `/auth/register` | `full_name*`, `email` atau `phone` (salah satu wajib), `password*` (min 8, wajib `password_confirmation`) | Publik, throttle 10/menit |
| POST | `/auth/login` | `email` atau `phone` (salah satu wajib), `password*` | Publik, throttle 10/menit |
| GET | `/auth/me` | — | Bearer token |
| POST | `/auth/logout` | — | Bearer token — mencabut token yang dipakai di request ini saja (device lain tetap login) |

Response `register`/`login` (`201`/`200`):

```json
{ "data": { "user": { "id": "...", "full_name": "...", "email": "...", "phone": null, "avatar_url": null, "created_at": "..." }, "token": "1|xxxxxxxx..." } }
```

Response `login` gagal (`422`): error validasi standar dengan pesan generik ("Email/telepon atau kata sandi salah.") pada field `email`/`phone` yang dipakai — sengaja tidak membedakan "user tidak ada" vs "password salah" untuk mencegah user enumeration.

Setelah register/login, klien memanggil `GET /families` untuk melihat family yang sudah dimiliki, atau `POST /families` untuk membuat family pertama (lihat bagian Families).

## Header `X-Family-Id`

Sebagian besar resource beroperasi dalam konteks satu family. Family aktif ditentukan oleh middleware `ResolveFamily`:

1. Ambil semua `family_members` milik user yang login (`removed_at is null`).
2. Jika header `X-Family-Id` dikirim, family tersebut harus ada di antara membership user itu — kalau tidak, `403`.
3. Jika header tidak dikirim, dipakai membership pertama user.
4. Jika user tidak punya membership sama sekali → `403`.

`family_id` **tidak pernah** diambil dari body request — hanya dari resolusi di atas. Endpoint `Family` (`/families`) sendiri adalah pengecualian karena dia adalah akar tenant (lihat bagian Families).

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
{ "message": "The given data was invalid.", "errors": { "field": ["..."] } }
```

```json
{ "message": "Human readable message." }
```

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
| POST | `/families` | `name*` (string), `currency` (3 huruf, default `IDR`), `timezone` (default `Asia/Jakarta`) | User pembuat otomatis jadi `family_members.role=admin`. |
| GET | `/families/{family}` | — | 403 jika user bukan member family ini. |
| PUT/PATCH | `/families/{family}` | `name`, `currency`, `timezone`, `onboarding_done` (semua opsional) | Admin only. |
| DELETE | `/families/{family}` | — | Admin only. Cascade ke seluruh data family (FK `cascadeOnDelete`). |

Response `data`: `id, name, currency, timezone, onboarding_done, created_at, updated_at`.

---

## Family Members

Butuh `X-Family-Id` (di bawah `resolve.family`). Mengelola role/keanggotaan adalah aksi admin — bukan `member` — untuk mencegah eskalasi privilege.

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/family-members` | — | `viewer` |
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

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/savings-goals` | — | `viewer` |
| POST | `/savings-goals` | `target_name*`, `target_amount*` (int > 0), `deadline`, `icon`, `color`, `account_id`, `status` (default `active`) | `member` |
| GET | `/savings-goals/{savings_goal}` | — | `viewer` |
| PUT/PATCH | `/savings-goals/{savings_goal}` | Sama seperti store minus `current_amount` (cache, tidak bisa diubah langsung) | `member` |
| DELETE | `/savings-goals/{savings_goal}` | — | `admin`; **409** jika masih direferensikan transaksi (pakai `status=cancelled` alih-alih hapus) |

Mengubah `status` menjadi `achieved` otomatis mengisi `achieved_at`; mengubahnya ke status lain mengosongkan `achieved_at`.

Response `data`: `id, family_id, target_name, target_amount, current_amount, percent (0-100, dihitung server), deadline, icon, color, account_id, status, created_at, achieved_at`.

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

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/transactions` | — | `viewer` |
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

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/chat-threads` | — | `viewer` |
| POST | `/chat-threads` | `title`, `kind` (`general`\|`onboarding`, default `general`) | `member` |
| GET | `/chat-threads/{chat_thread}` | — | `viewer` |
| PUT/PATCH | `/chat-threads/{chat_thread}` | `title`, `kind` | `member` |
| DELETE | `/chat-threads/{chat_thread}` | — | `member` |

`member_id` selalu diisi dari member yang login, tidak bisa dikirim lewat body.

Response `data`: `id, family_id, member_id, title, kind, last_message_at, created_at`.

---

## Chat Messages

Nested di bawah thread; **read-only setelah dibuat** (tidak ada `update`/`destroy` — riwayat chat tidak bisa diedit).

| Method | Path | Body | Role |
| --- | --- | --- | --- |
| GET | `/chat-threads/{chat_thread}/messages` | — | `viewer` |
| POST | `/chat-threads/{chat_thread}/messages` | `content` (wajib jika tidak ada `attachment_url`), `input_mode` (`text`\|`voice`\|`image`), `attachment_url` | `member` |
| GET | `/messages/{message}` | — | `viewer` |

`role` selalu dipaksa `user` untuk pesan yang dibuat lewat endpoint ini — balasan `assistant`/`system` hanya ditulis oleh `AssistantService` (alur AI, lihat `CLAUDE.md`), bukan lewat API publik ini. Membuat pesan memperbarui `chat_threads.last_message_at`.

Response `data`: `id, thread_id, role, content, input_mode, attachment_url, token_usage, created_at`.

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

## AI Actions — read only

Jejak audit AI → data nyata (lihat "Alur AI" di `CLAUDE.md`). Tidak ada `store`/`update`/`destroy`: AI tidak pernah menulis tabel bisnis, dan baris `ai_actions` sendiri hanya diubah oleh `ConfirmAiAction` (belum diimplementasikan) — tidak lewat endpoint publik ini, dan tidak pernah dihapus.

| Method | Path | Role |
| --- | --- | --- |
| GET | `/ai-actions` | `viewer` |
| GET | `/ai-actions/{ai_action}` | `viewer` |

Response `data`: `id, message_id, family_id, action, payload, status, result_table, result_id, confidence, resolved_at, resolved_by, created_at`.

---

## Audit Logs — read only

Jejak audit umum. Tidak ada `store`/`update`/`destroy` lewat API — baris ditulis oleh proses internal saat entity lain berubah.

| Method | Path | Role |
| --- | --- | --- |
| GET | `/audit-logs` | `viewer` |
| GET | `/audit-logs/{audit_log}` | `viewer` |

Response `data`: `id, family_id, actor_id, entity, entity_id, action, diff, created_at`.

---

## Belum diimplementasikan (di luar cakupan dokumen ini)

- `ConfirmAiAction` (menulis baris nyata dari `ai_actions.pending`).
- SSE `action_card`, `AssistantService`, job terjadwal `recurring_rules`.
- `GET /analytics/summary` dan endpoint lain yang memakai `v_wallet_month`/`v_cashflow_month`.
- `GET /api/v1/openapi.json`.
