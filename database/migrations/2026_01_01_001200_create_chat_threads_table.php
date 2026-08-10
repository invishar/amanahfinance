<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->text('title')->nullable();
            $table->string('kind')->default('general');
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement("alter table chat_threads add constraint chat_threads_kind_ck
            check (kind in ('general','onboarding'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};
