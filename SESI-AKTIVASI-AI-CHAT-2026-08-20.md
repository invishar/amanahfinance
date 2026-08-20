# Sesi 20 Agustus 2026 — Aktivasi AI Chat (Groq)

Ringkasan sesi Claude Code: mengaktifkan balasan Amina sungguhan di `/chat` (sebelumnya
backend cuma bisa bicara ke Anthropic, dan frontend 100% mode demo/mock). Dokumen ini
rujukan progres — update status tiap item begitu dikerjakan, jangan hapus yang selesai.

---

## Yang diminta

- Aktifkan AI di `http://localhost:3000/chat`, terutama setelah user baru mendaftar.
- Pakai konfigurasi di [`GROQ-MODELS.md`](GROQ-MODELS.md) (model `openai/gpt-oss-120b`,
  base URL `https://api.groq.com/openai/v1`).
- Simpan konfigurasinya ke database setting admin (`llm_settings`), bukan cuma `.env`.
- Pakai satu LLM key Groq yang diberikan user langsung di chat.

## Temuan yang mengubah scope

Dua gap ditemukan sebelum implementasi, dikonfirmasi ke user lewat pertanyaan (bukan
diasumsikan), lalu dikerjakan sesuai jawaban:

1. **Backend cuma bisa bicara Anthropic.** `AnthropicConversationRunner` pakai SDK resmi
   Anthropic (wire protocol Messages API, header `x-api-key`) — Groq cuma expose endpoint
   ala OpenAI Chat Completions, beda protokol total. Solusi: runner baru
   (`OpenAiCompatibleConversationRunner`) + field `provider` di `llm_settings` untuk
   memilih wire protocol yang benar.
2. **Frontend `/chat` 100% demo.** `NEXT_PUBLIC_MOCK_AMINA` default aktif kalau env var
   tidak diset, balasan Amina cuma `setTimeout` palsu (`lib/mock/assistant.ts`), dan SSE
   endpoint yang sudah ada di backend (`ChatStreamController`) tidak pernah dipanggil dari
   klien. Solusi: hook SSE baru (`useChatStream`) + matikan flag mock untuk dev lokal.

---

## Sudah selesai

### Backend

- [x] Migrasi `provider` di `llm_settings` (`anthropic` default / `openai_compatible`,
      CHECK constraint) —
      [2026_08_20_120000_add_provider_to_llm_settings_table.php](database/migrations/2026_08_20_120000_add_provider_to_llm_settings_table.php).
      **Sudah dijalankan di DB lokal** — perlu `php artisan migrate` di environment lain.
- [x] `config('services.llm.provider')` + `LLM_PROVIDER` di `.env`/`.env.example`
      (fallback `anthropic`, tidak menyimpan key Groq di `.env` — lihat bagian
      "Konfigurasi live" di bawah).
- [x] `LlmSetting` model, `LlmSettingFactory`, `UpdateLlmSettingRequest` (validasi
      `provider` via `Rule::in`), `LlmSettingResource`, `LlmSettingActions` — semua
      diperluas untuk field `provider`.
- [x] **`OpenAiCompatibleConversationRunner`** (baru) —
      [app/Services/Ai/OpenAiCompatibleConversationRunner.php](app/Services/Ai/OpenAiCompatibleConversationRunner.php).
      Loop tool-calling manual (SDK Anthropic tidak punya `toolRunner` untuk protokol
      ini): panggil `{base_url}/chat/completions`, proses `tool_calls`, jalankan closure
      `BetaRunnableTool::run()` yang sama dipakai `AnthropicConversationRunner`, umpan
      balik hasil sebagai `role: tool`. `max_tokens` dinaikkan ke 2048 (lebih tinggi dari
      punya Anthropic runner) karena model reasoning ala `gpt-oss` menghabiskan banyak
      token di `reasoning` sebelum `content` terisi — sempat diverifikasi manual lewat
      `curl` langsung ke Groq, `max_tokens` rendah bikin balasan kepotong kosong.
- [x] `AppServiceProvider` memilih runner berdasar `llm_settings.provider` saat resolve
      `ConversationRunner`, re-read tiap request (sama seperti pola `AnthropicClient`
      singleton yang sudah ada) — jadi ganti provider dari admin panel langsung berlaku.
- [x] `API-v1.md` + `OpenApiSpec.php` diperbarui untuk field `provider`.
- [x] Test baru: 3 test wire-level di
      [tests/Feature/OpenAiCompatibleConversationRunnerTest.php](tests/Feature/OpenAiCompatibleConversationRunnerTest.php)
      (`Http::fake()` — auth header, tool-calling loop, batas `maxIterations`), + 2 test
      baru di `LlmSettingTest.php` (switch provider, validasi provider tidak valid).
      **16 test lulus** (`OpenAiCompatibleConversationRunnerTest` +
      `LlmSettingTest` + `AssistantServiceTest`).

### Frontend

- [x] `useChatStream` (baru) di
      [lib/api/hooks.ts](frontend/lib/api/hooks.ts) — baca SSE lewat `fetch` + reader
      manual (native `EventSource` tidak bisa kirim header `Authorization` yang dipakai
      Sanctum), auto-reconnect pakai cursor dari event `retry`, event `thinking` /
      `message` / `error` ditangani (`action_card` sengaja belum — lihat "Belum
      dikerjakan").
- [x] `app/(app)/chat/page.tsx` — pakai `useChatStream`, gabung `isThinking` dengan
      indikator ketik demo, **perbaiki bug pemetaan role** (`system` sempat ketiban jadi
      `user` di bubble kanan — sekarang benar lewat `message-list.tsx`).
- [x] `components/chat/message-list.tsx` — `ChatItem.role` tambah `"system"`, dibedakan
      visual (border/warna `--color-accent-800`, token yang sudah ada, bukan warna baru)
      supaya pesan error dari `AssistantService::fail()` tidak disamarkan sebagai balasan
      normal.
- [x] `app/admin/(dashboard)/llm-settings/page.tsx` — dropdown `provider`
      (Anthropic / OpenAI-compatible), placeholder base URL ikut berubah sesuai pilihan.
- [x] `lib/api/schema.d.ts` **di-generate ulang** dari `openapi.json` (`npx
      openapi-typescript`) — sudah termasuk field `provider`. **Jangan diedit tangan**,
      generate ulang lagi kalau backend openapi spec berubah lagi.
- [x] `frontend/.env.local` (baru, git-ignored) — `NEXT_PUBLIC_MOCK_AMINA=0`,
      `NEXT_PUBLIC_API_URL=http://amanahfinance_api.test/api/v1` (Herd -- lihat "Catatan
      operasional" soal kenapa bukan `php artisan serve`). **Ini cuma di mesin lokal ini**,
      tidak ikut ter-commit (lihat `.gitignore`: `.env*` kecuali `.env.production`).

### Kartu aksi (action card) sungguhan

- [x] `AiAction` (tipe baru), `qk.aiActions`, `usePendingAiActions` / `useConfirmAiAction`
      / `useRejectAiAction` di [lib/api/hooks.ts](frontend/lib/api/hooks.ts) — confirm
      memicu `useInvalidateAll()` (di-`export` dari fungsi internal yang sudah ada,
      dipakai ulang alih-alih menulis mapping `result_table` sendiri) supaya
      wallets/accounts/income-sources/savings-goals/transactions/analytics semua ikut
      refresh begitu draft tersimpan.
- [x] `useChatStream` sekarang menerima `familyId`, event `action_card` (payload SSE cuma
      `{id, action, payload, created_at}` -- server sudah menyaring pending & thread ini,
      status `pending` diasumsikan) ditulis ke query cache `qk.aiActions`.
- [x] [lib/ai-action-view.ts](frontend/lib/ai-action-view.ts) (baru) — `describeAiAction()`
      memetakan `payload` (isinya id hasil resolusi server, bukan nama) balik ke nama
      entitas (`accounts`/`wallets`/`incomeSources`/`savingsGoals`) buat tiap 6 jenis
      action; field yang gagal diresolusi (nama ambigu) ditandai `missing` dan disorot.
- [x] [components/chat/ai-action-card.tsx](frontend/components/chat/ai-action-card.tsx)
      (baru) — kartu terpisah dari `ActionCard` (yang tetap demo-only). Tombol Confirm
      ("Ya, lanjutkan") & Reject ("Batal") memanggil endpoint asli, status pending
      mutation ditampilkan ("Menyimpan…"), error dari API (mis. validasi 422 field wajib
      kosong) ditampilkan di kartu. **Tidak ada tombol Edit** (sengaja, lihat komentar di
      file) -- field yang gagal diresolusi cuma disorot, user perlu kirim ulang lewat chat.
- [x] `message-list.tsx` render `AiActionCard` untuk `item.aiAction` (field baru di
      `ChatItem`, terpisah dari `card` yang demo-only), `chat/page.tsx` menyaring
      `/ai-actions` (family-wide, tidak thread-scoped) ke milik thread yang sedang dibuka
      lewat `message_id`.
- [x] **Diverifikasi end-to-end lewat browser sungguhan** (bukan cuma tsc/eslint): kirim
      "gue baru buka rekening GoPay saldo awal 100rb" → kartu "Tambah Akun Baru" muncul
      dengan data yang benar (Nama Akun GoPay, Jenis E-Wallet, Saldo Awal Rp 100.000) →
      klik "Ya, lanjutkan" → `POST /ai-actions/{id}/confirm` sukses (200) → kartu berubah
      jadi "Sudah disimpan" → baris `accounts`/`wallets` sungguhan muncul di DB dengan
      `ai_actions.status=confirmed` + `result_table`/`result_id` terisi benar. Reject
      ("Batal") juga diverifikasi terpisah dengan action `advice` → status `rejected`,
      kartu menampilkan "Dibatalkan".

### Konfigurasi live (database)

Baris `llm_settings` sudah diisi manual lewat `php artisan tinker` (bukan lewat UI admin,
karena skrip sesi ini yang mengisinya) — `provider=openai_compatible`,
`model=openai/gpt-oss-120b`, `base_url=https://api.groq.com/openai/v1`, `key` tersimpan
terenkripsi (`encrypted` cast, `APP_KEY`). Key **tidak** ditulis di `.env` atau dokumen
manapun — cuma ada di DB. Kalau butuh ganti/lihat key sekarang, lewat halaman admin
`/admin/llm-settings` (preview 4 karakter terakhir) atau `LlmSetting::query()->first()`.

### Verifikasi

- Test otomatis: 16 lulus (lihat di atas).
- **End-to-end manual lewat browser (Playwright headless)**: daftar user baru → dialihkan
  `/onboarding` → buat family → `/chat` menampilkan sapaan + pertanyaan onboarding **asli
  dari server** (bukan skrip demo lama) → kirim "abis jajan 20rb di gopay" → balasan
  Amina sungguhan dari Groq muncul ("Oke, jadi 20 ribuan di GoPay. Mau masukin ke wallet
  apa nih?") dalam ~15 detik lewat SSE, badge "Balasan demo" hilang, tidak ada error di
  console maupun `storage/logs/laravel.log`.

### Commit

- [x] Semua perubahan sesi ini **di-commit** — `5c17b01` "Activate real AI chat:
      OpenAI-compatible runner (Groq) + SSE wiring" (20 Agustus 2026). Kartu aksi
      sungguhan (lihat di atas) dikerjakan setelah commit itu — **belum di-commit**,
      lihat "Tugas selanjutnya". Belum ada yang di-push ke `origin/main`.

---

## Catatan operasional penting: `php artisan serve` vs Herd untuk halaman ini

Ditemukan saat verifikasi kartu aksi: `php artisan serve` (PHP built-in server) **satu
thread** — selama koneksi SSE `useChatStream` masih terbuka (bertahan sampai ~20-25 detik
per desain, lalu reconnect), **request lain dari tab browser yang sama ikut nge-block**
sampai koneksi SSE itu selesai. Diukur langsung: request `GET /families` biasa yang
harusnya instan, makan **21 detik** selagi SSE terbuka lewat `php artisan serve` — di Herd
(nginx + PHP-FPM, banyak worker), request yang sama **1.4 detik**. Ini yang awalnya bikin
tombol "Ya, lanjutkan" kelihatan macet di "Menyimpan…" saat racikan test pertama (bukan
bug di kode confirm/reject-nya — sudah dibuktikan lewat `curl` langsung ke endpoint,
selalu sukses).

**Implikasi:** jangan pakai `php artisan serve` untuk dev/test halaman `/chat` (atau
halaman apa pun yang membuka SSE lama). Pakai Herd (atau server PHP-FPM/Apache lain yang
sungguhan) — itu juga yang dipakai `frontend/.env.local` sekarang
(`http://amanahfinance_api.test/api/v1`). Di produksi (hPanel, Apache+PHP-FPM) ini bukan
masalah karena sudah multi-worker; ini murni gotcha dev-lokal.

---

## Belum dikerjakan (sengaja, di luar scope sesi ini)

- [ ] **P1 — Alur onboarding terstruktur.** `POST /onboarding-answers`
      (`question_key`+`answer` terstruktur, bukan teks bebas) yang sesungguhnya
      menggerakkan `OnboardingConversationActions::advance()` **tidak** dipanggil dari
      kotak chat manapun — mengetik jawaban di `/chat` sekarang cuma masuk sebagai pesan
      LLM biasa (general assistant), bukan mengisi `onboarding_answers`. Naskah
      pertanyaannya ada di `config/amina.php` (`onboarding_questions`), tapi belum ada
      layar/form terstruktur untuk menjawabnya.
- [ ] **P2 — Operasional dev lokal.** Windows tidak punya cron; `queue:work` harus jalan
      terus manual untuk balasan AI muncul (lihat CLAUDE.md soal hPanel — masalah yang
      sama persis di lokal). Pertimbangkan skrip kecil (`composer run dev` ala Laravel
      default, atau task terpisah) supaya tidak perlu diingat manual tiap kali dev.
  - **Proses masih nyala dari sesi ini** (background): `php artisan queue:work`
    (terus-menerus, bukan burst), `npm run dev` (:3000, dengan `.env.local` mengarah ke
    Herd). `php artisan serve` **sudah dimatikan** sesi ini (lihat gotcha SSE di atas).
    Matikan `queue:work`/`npm run dev` juga kalau sudah tidak dipakai.
- [x] **Pantau kuota Groq — sudah kejadian, bukan cuma risiko.** Sempat kena
      `429 Rate limit reached` beneran (lihat `storage/logs/laravel.log`,
      ~21:30) gara-gara testing berulang di sesi ini. Dimensi yang abis duluan ternyata
      **TPM (8000 token/menit), bukan RPD** (1000/hari, jarang tersentuh) -- masuk akal
      karena `openai/gpt-oss-120b` boros token `reasoning`, dan tiap panggilan bawa system
      prompt + 6 definisi tool sekaligus. Window TPM reset cepat (hitungan detik-menit),
      jadi cukup jeda sebentar antar pesan chat kalau kena ini lagi -- tapi kalau dipakai
      serius/banyak keluarga sekaligus, pertimbangkan model non-reasoning
      (`openai/gpt-oss-20b`) atau upgrade tier.
- [ ] **P3 — Frontend admin llm-settings**: placeholder field "Model" masih contoh
      Anthropic (`claude-sonnet-5`) terlepas dari provider yang dipilih — kosmetik,
      tidak fungsional.

---

## File yang berubah (ringkas)

**Backend:** `database/migrations/2026_08_20_120000_add_provider_to_llm_settings_table.php` (baru) ·
`config/services.php` · `.env` · `.env.example` · `app/Models/LlmSetting.php` ·
`app/Http/Requests/UpdateLlmSettingRequest.php` · `app/Http/Resources/LlmSettingResource.php` ·
`app/Actions/LlmSettings/LlmSettingActions.php` · `database/factories/LlmSettingFactory.php` ·
`app/Services/Ai/OpenAiCompatibleConversationRunner.php` (baru) ·
`app/Providers/AppServiceProvider.php` · `app/OpenApi/OpenApiSpec.php` · `API-v1.md` ·
`tests/Feature/OpenAiCompatibleConversationRunnerTest.php` (baru) ·
`tests/Feature/LlmSettingTest.php`

**Frontend:** `lib/api/client.ts` · `lib/api/hooks.ts` · `lib/api/admin-hooks.ts` ·
`lib/api/keys.ts` · `lib/api/schema.d.ts` (generated) · `lib/ai-action-view.ts` (baru) ·
`app/admin/(dashboard)/llm-settings/page.tsx` · `app/(app)/chat/page.tsx` ·
`components/chat/message-list.tsx` · `components/chat/ai-action-card.tsx` (baru) ·
`.env.local` (git-ignored, sekarang arah Herd -- lihat gotcha SSE di atas)

---

## Tugas selanjutnya (prioritas)

1. **Commit kartu aksi asli** — perubahan belum di-commit (lihat bagian "Commit" di
   atas). `git add` file frontend baru/berubah + jalankan `npx tsc --noEmit` sekali lagi
   sebelum commit kalau ada perubahan lanjutan.
2. **Putuskan nasib alur onboarding terstruktur** — apa tetap lewat `/onboarding-answers`
   dengan layar terpisah, atau digabung ke pengalaman chat teks bebas (kalau digabung,
   perlu tool baru di `AssistantService` untuk menulis `OnboardingAnswer`, bukan lewat
   endpoint terpisah).
3. **Bangun form Edit untuk kartu aksi** — sekarang field yang gagal diresolusi server
   cuma disorot italic, user harus kirim ulang lewat chat kalau mau perbaiki. Form edit
   per jenis action (6 jenis) yang mengirim `edits` ke `POST /ai-actions/{id}/confirm`
   akan menutup gap ini.
4. **Uji di luar happy path**: key Groq salah/dicabut (pastikan `ProcessAssistantMessage`
   gagal dengan rapi ke `role=system` error, bukan diam — sudah kejadian natural lewat
   rate limit sesi ini, lihat catatan Groq di atas, dan perilakunya sudah benar), 422 dari
   confirm saat field wajib kosong (`aiActionErrors` di `chat/page.tsx` sudah menangkap
   pesannya tapi belum diuji dengan draft yang sungguhan tidak lengkap), `base_url` kosong
   untuk provider `openai_compatible` (belum ada validasi eksplisit bahwa `base_url`
   wajib diisi kalau provider = `openai_compatible` — sekarang cuma `nullable`).
5. **Jangan pakai `php artisan serve` untuk dev/test `/chat`** — lihat catatan operasional
   di atas, pakai Herd atau server PHP-FPM/Apache lain.
