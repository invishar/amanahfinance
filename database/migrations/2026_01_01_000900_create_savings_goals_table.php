<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('target_name');
            $table->bigInteger('target_amount');
            $table->bigInteger('current_amount')->default(0); // cache dari transactions type='savings'
            $table->date('deadline')->nullable();
            $table->text('icon')->nullable();
            $table->text('color')->nullable();
            $table->foreignUuid('account_id')->nullable()
                  ->constrained('accounts')->nullOnDelete();  // rekening penampung
            $table->string('status')->default('active');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('achieved_at')->nullable();
            $table->index(['family_id', 'status']);
        });

        DB::statement('alter table savings_goals add constraint savings_goals_amount_ck
            check (target_amount > 0)');
        DB::statement("alter table savings_goals add constraint savings_goals_status_ck
            check (status in ('active','achieved','paused','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
