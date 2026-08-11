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

    public function __construct(public string $chatMessageId) {}

    public function handle(AssistantService $assistant): void
    {
        $message = ChatMessage::query()->find($this->chatMessageId);

        if (! $message || $message->role !== 'user') {
            return;
        }

        $assistant->respond($message);
    }
}
