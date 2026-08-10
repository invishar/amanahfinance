<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('type');
            $table->bigInteger('amount');
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->cascadeOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('income_sources')->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->text('rrule');                           // iCal RRULE, mis. FREQ=MONTHLY;BYMONTHDAY=1
            $table->date('next_run_on');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->index('next_run_on');
        });

        DB::statement("alter table recurring_rules add constraint recurring_rules_type_ck
            check (type in ('income','expense','savings'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
