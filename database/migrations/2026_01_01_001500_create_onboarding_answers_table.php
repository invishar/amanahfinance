<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jawaban wawancara awal; semua pertanyaan boleh dilewati -> answer nullable
        Schema::create('onboarding_answers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('question_key');                    // 'members','income','expenses','goals'
            $table->jsonb('answer')->nullable();
            $table->boolean('skipped')->default(false);
            $table->timestampTz('answered_at')->useCurrent();
            $table->unique(['family_id', 'question_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_answers');
    }
};
