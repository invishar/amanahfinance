<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()
                ->constrained('family_members')->nullOnDelete();
            $table->string('entity');
            $table->uuid('entity_id')->nullable();
            $table->text('action');
            $table->jsonb('diff')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['family_id', 'entity', 'entity_id']);
        });

        DB::statement("alter table audit_logs add constraint audit_logs_action_ck
            check (action in ('create','update','delete','restore'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
