<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local-only debugging trail: satu baris per panggilan AssistantService
     * yang berhasil, isinya prompt user, system prompt yang dibangun
     * (buildSystemPrompt()), dan token usage yang dilaporkan provider. Ditulis
     * dari AssistantService::respond() dan cuma jalan kalau
     * app()->environment('local') -- tidak pernah aktif di produksi (isi
     * system_prompt bisa memuat ringkasan finansial family, jangan sampai
     * menumpuk di server orang lain). family_id/thread_id/message_id
     * nullOnDelete supaya baris tetap jadi jejak walau resource yang
     * direferensikan sudah dihapus (sama seperti ai_provider_errors).
     */
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->nullable()
                ->constrained('families')->nullOnDelete();
            $table->foreignUuid('thread_id')->nullable()
                ->constrained('chat_threads')->nullOnDelete();
            $table->foreignUuid('message_id')->nullable()
                ->constrained('chat_messages')->nullOnDelete();
            $table->string('model');
            $table->text('user_prompt')->nullable();
            $table->longText('system_prompt')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
