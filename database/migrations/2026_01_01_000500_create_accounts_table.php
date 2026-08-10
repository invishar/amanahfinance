<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // accounts = tempat uang benar-benar berada (bank / e-wallet / tunai)
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('name');
            $table->text('account_type');
            $table->text('institution')->nullable();        // "BCA", "GoPay"
            $table->text('masked_number')->nullable();
            $table->bigInteger('opening_balance')->default(0);
            $table->bigInteger('current_balance')->default(0); // cache; sumber kebenaran = transactions
            $table->foreignUuid('owner_member_id')->nullable()
                  ->constrained('family_members')->nullOnDelete();
            $table->boolean('is_shared')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['family_id', 'name']);
        });

        DB::statement("alter table accounts add constraint accounts_type_ck
            check (account_type in ('bank','ewallet','cash','other'))");
        DB::statement('create index accounts_active_idx on accounts (family_id)
            where is_archived = false');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
