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
      `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1`. **Ini cuma di mesin lokal ini**,
      tidak ikut ter-commit (lihat `.gitignore`: `.env*` kecuali `.env.production`).

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

---

## Belum dikerjakan (sengaja, di luar scope sesi ini)

- [ ] **P0 — Kartu aksi (action card) sungguhan.** `AiAction` (`create_transaction`, dst)
      sudah tersimpan `pending` lewat tool call asli, endpoint
      `POST /ai-actions/{id}/confirm|reject` juga **sudah ada** di backend — tapi
      `MessageList`/`ActionCard` di frontend cuma render bentuk `DemoActionCard` (data
      demo), belum ada mapping dari payload `AiAction` asli ke kartu, belum ada mutation
      confirm/reject yang manggil endpoint itu, belum ada invalidate query per
      `result_table` (`transactions`/`wallets`/dst) setelah confirm. **Ini yang bikin
      user belum bisa benar-benar menyimpan draft transaksi dari chat.** Event
      `action_card` dari SSE sudah sampai ke `useChatStream` tapi sengaja belum
      ditangani (lihat komentar di kode) — begitu kartu asli ada, tinggal disambungkan.
- [ ] **P1 — Alur onboarding terstruktur.** `POST /onboarding-answers`
      (`question_key`+`answer` terstruktur, bukan teks bebas) yang sesungguhnya
      menggerakkan `OnboardingConversationActions::advance()` **tidak** dipanggil dari
      kotak chat manapun — mengetik jawaban di `/chat` sekarang cuma masuk sebagai pesan
      LLM biasa (general assistant), bukan mengisi `onboarding_answers`. Naskah
      pertanyaannya ada di `config/amina.php` (`onboarding_questions`), tapi belum ada
      layar/form terstruktur untuk menjawabnya.
- [ ] **P1 — Commit.** Semua perubahan sesi ini (migrasi, backend runner, frontend SSE,
      dst) **masih belum di-commit** — belum diminta eksplisit selama sesi.
- [ ] **P2 — Operasional dev lokal.** Windows tidak punya cron; `queue:work` harus jalan
      terus manual untuk balasan AI muncul (lihat CLAUDE.md soal hPanel — masalah yang
      sama persis di lokal). Pertimbangkan skrip kecil (`composer run dev` ala Laravel
      default, atau task terpisah) supaya tidak perlu diingat manual tiap kali dev.
  - **Proses masih nyala dari sesi ini** (background): `php artisan serve` (:8000),
    `php artisan queue:work` (terus-menerus, bukan burst), `npm run dev` (:3000).
    Matikan kalau sudah tidak dipakai, atau ganti ke workflow biasa
    (Herd di `amanahfinance_api.test` + `NEXT_PUBLIC_API_URL` produksi-style).
- [ ] **P2 — Pantau kuota Groq.** Free tier `openai/gpt-oss-120b`: 1K request/hari,
      200K token/hari (lihat [GROQ-MODELS.md](GROQ-MODELS.md)) — model reasoning ini
      boros token (lihat catatan `max_tokens` di atas). Kalau dipakai serius/testing
      berulang, kuota harian bisa cepat habis; pertimbangkan model non-reasoning
      (`openai/gpt-oss-20b`) atau upgrade tier kalau itu terjadi.
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
`lib/api/schema.d.ts` (generated) · `app/admin/(dashboard)/llm-settings/page.tsx` ·
`app/(app)/chat/page.tsx` · `components/chat/message-list.tsx` · `.env.local` (baru,
git-ignored)

---

## Tugas selanjutnya (prioritas)

1. **Bangun kartu aksi asli** — pemetaan `AiAction.payload` → tampilan kartu per jenis
   action, mutation confirm/reject, invalidate query pasca-confirm. Ini yang membuat AI
   di chat benar-benar bisa dipakai mencatat transaksi, bukan cuma ngobrol.
2. **Putuskan nasib alur onboarding terstruktur** — apa tetap lewat `/onboarding-answers`
   dengan layar terpisah, atau digabung ke pengalaman chat teks bebas (kalau digabung,
   perlu tool baru di `AssistantService` untuk menulis `OnboardingAnswer`, bukan lewat
   endpoint terpisah).
3. **Commit** perubahan sesi ini begitu siap, dengan pesan yang memisahkan "provider
   abstraction (Groq)" dari "SSE wiring frontend" kalau mau riwayat commit yang rapi.
4. **Uji di luar happy path**: key Groq salah/dicabut (pastikan `ProcessAssistantMessage`
   gagal dengan rapi ke `role=system` error, bukan diam), rate limit Groq tercapai,
   `base_url` kosong untuk provider `openai_compatible` (belum ada validasi eksplisit
   bahwa `base_url` wajib diisi kalau provider = `openai_compatible` — sekarang cuma
   `nullable`).
