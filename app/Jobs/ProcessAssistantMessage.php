<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\Ai\AssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// Semua panggilan LLM berjalan di job antrian, tidak pernah di request web
// (CLAUDE.md, "Konvensi kode"). Diproses lewat burst queue:work terjadwal
// (routes/console.php), bukan worker daemon -- lihat CLAUDE.md soal hPanel.
class ProcessAssistantMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Beberapa provider (mis. 9Router) sempat balas error transien lalu pulih
    // sendiri dalam hitungan puluhan detik (ditemukan lewat ai_provider_errors
    // -- request yang identik berhasil begitu diulang ~1 menit kemudian).
    // Tanpa jeda, database queue langsung meretry job begitu tersedia lagi,
    // jadi ketiga percobaan bisa habis sebelum provider sempat pulih --
    // pesan "gangguan teknis" pun muncul padahal sebenarnya cuma butuh
    // nunggu sebentar. Selaras juga dengan pola burst worker per menit
    // (CLAUDE.md "Perintah"): job yang belum available_at tidak diambil,
    // baru dicoba lagi di burst berikutnya.
    public array $backoff = [10, 30];

    public function __construct(public string $chatMessageId) {}

    public function handle(AssistantService $assistant): void
    {
        $message = ChatMessage::query()->find($this->chatMessageId);

        if (! $message || $message->role !== 'user') {
            return;
        }

        $assistant->respond($message);
    }

    // Dipanggil Laravel otomatis setelah $tries habis. Menulis balasan
    // role=system lewat AssistantService::fail() supaya SSE (event `error`)
    // dan GET .../messages tetap memberi tahu user, bukan diam saja.
    public function failed(\Throwable $exception): void
    {
        $message = ChatMessage::query()->find($this->chatMessageId);

        if ($message && $message->role === 'user') {
            app(AssistantService::class)->fail($message);
        }
    }
}
