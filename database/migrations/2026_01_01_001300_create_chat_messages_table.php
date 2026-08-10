<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->text('role');
            $table->text('content')->nullable();
            $table->text('input_mode')->nullable();
            $table->text('attachment_url')->nullable();      // foto struk / rekaman suara
            $table->integer('token_usage')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['thread_id', 'created_at']);
        });

        DB::statement("alter table chat_messages add constraint chat_messages_role_ck
            check (role in ('user','assistant','system'))");
        DB::statement("alter table chat_messages add constraint chat_messages_input_mode_ck
            check (input_mode is null or input_mode in ('text','voice','image'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
