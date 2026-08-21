<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak setiap kali ConversationRunner gagal (lihat
     * AssistantService::logProviderError()) -- platform-wide monitoring untuk
     * admin lewat GET /admin/ai-errors, bukan resource per-family. Ditulis
     * bareng dengan channel log `ai` (config/logging.php); tabel ini yang
     * bisa difilter/dipaginasi dari layar admin tanpa parse file log.
     * family_id/thread_id/message_id nullOnDelete supaya baris tetap jadi
     * jejak walau resource yang direferensikan sudah dihapus.
     */
    public function up(): void
    {
        Schema::create('ai_provider_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->nullable()
                ->constrained('families')->nullOnDelete();
            $table->foreignUuid('thread_id')->nullable()
                ->constrained('chat_threads')->nullOnDelete();
            $table->foreignUuid('message_id')->nullable()
                ->constrained('chat_messages')->nullOnDelete();
            $table->string('model');
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('exception');
            $table->text('body')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_errors');
    }
};
