<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kartu konfirmasi yang dibuat Amina; jejak audit AI -> data nyata.
        // Baris ini TIDAK menulis apa pun sampai statusnya confirmed/edited.
        Schema::create('ai_actions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('action');
            $table->jsonb('payload');                        // draft yang ditawarkan ke user
            $table->text('status')->default('pending');
            $table->text('result_table')->nullable();
            $table->uuid('result_id')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()
                  ->constrained('family_members')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['family_id', 'status']);
        });

        DB::statement("alter table ai_actions add constraint ai_actions_action_ck
            check (action in ('create_transaction','create_wallet','create_account',
                              'create_income_source','create_savings_goal','advice'))");
        DB::statement("alter table ai_actions add constraint ai_actions_status_ck
            check (status in ('pending','confirmed','edited','rejected','expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
    }
};
