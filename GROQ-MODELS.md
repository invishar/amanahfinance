# Groq — Model Teks & API Key

Dicek langsung dari `console.groq.com/docs/models` dan `console.groq.com/docs/rate-limits` pada 2026-08-20. Lineup model Groq berubah cukup sering — model lama seperti `llama-3.3-70b-versatile`, `gemma2-9b-it`, `mixtral-8x7b` sudah tidak ada di daftar aktif per tanggal ini. Cek ulang docs sebelum production kalau sudah lama tidak update.

## Free tier

Groq tidak membedakan "model gratis" vs "model berbayar" — **semua model API bisa dipakai lewat Free tier** (tanpa perlu isi billing/kartu kredit untuk mulai), tapi dibatasi rate limit. Upgrade ke Dev tier kalau butuh limit lebih tinggi (bayar per token).

## Model teks/chat (production, stabil)

| Model ID | Free tier limit |
| --- | --- |
| `openai/gpt-oss-120b` | 30 RPM · 1K RPD · 8K TPM · 200K TPD |
| `openai/gpt-oss-20b` | 30 RPM · 1K RPD · 8K TPM · 200K TPD |
| `groq/compound` (system, ada web search + code exec bawaan) | 30 RPM · 250 RPD · 70K TPM |
| `groq/compound-mini` | 30 RPM · 250 RPD · 70K TPM |

## Preview (bisa berubah/dihapus sewaktu-waktu, jangan dipakai di production)

- `qwen/qwen3.6-27b` — 30 RPM · 1K RPD · 8K TPM · 200K TPD
- `openai/gpt-oss-safeguard-20b` — fokus moderasi/safety, bukan general chat
- `minimaxai/minimax-m2.7`

## Rekomendasi untuk Amina (assistant AI project ini)

`openai/gpt-oss-120b` — paling capable, tool-calling didukung. Cukup untuk dev/testing di free tier, tapi 1K request/hari + 200K token/hari bisa cepat habis kalau dipakai serius — pantau konsumsi sebelum production.

## API key

Buat di **https://console.groq.com/keys** (perlu login/daftar, tidak wajib kartu kredit untuk generate key di free tier).

## Konfigurasi `.env`

Sesuai `CLAUDE.md`, konfigurasi LLM selalu lewat `LLM_*` (nama generik, bukan `GROQ_*`/`ANTHROPIC_*`) supaya provider bisa diganti tanpa ubah kode:

```
LLM_BASE_URL=https://api.groq.com/openai/v1
LLM_MODEL=openai/gpt-oss-120b
LLM_KEY=gsk_...
```

Groq expose endpoint OpenAI-compatible di atas, cocok kalau `config('services.llm')` pakai HTTP client generik ala OpenAI chat completions.
