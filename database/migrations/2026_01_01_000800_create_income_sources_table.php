<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->string('name');                          // "Gaji Bulanan", "Freelance Desain"
            $table->foreignUuid('owner_member_id')->nullable()
                  ->constrained('family_members')->nullOnDelete();
            $table->bigInteger('expected_amount')->nullable();
            $table->text('cadence')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['family_id', 'name']);
        });

        DB::statement("alter table income_sources add constraint income_sources_cadence_ck
            check (cadence is null or cadence in ('monthly','biweekly','weekly','irregular'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('income_sources');
    }
};
