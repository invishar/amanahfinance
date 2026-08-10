<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('type');
            $table->bigInteger('amount');
            $table->date('transaction_date');
            $table->foreignUuid('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('to_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('income_sources')->nullOnDelete();
            $table->foreignUuid('goal_id')->nullable()->constrained('savings_goals')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignUuid('created_by')->nullable()
                  ->constrained('family_members')->nullOnDelete();
            $table->text('origin')->default('manual');
            $table->text('receipt_url')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("alter table transactions add constraint transactions_type_ck
            check (type in ('income','expense','transfer','savings'))");
        DB::statement("alter table transactions add constraint transactions_origin_ck
            check (origin in ('manual','chat_text','chat_voice','receipt_ocr','import'))");
        DB::statement('alter table transactions add constraint transactions_amount_ck
            check (amount > 0)');

        // Kelengkapan relasi per jenis transaksi
        DB::statement("alter table transactions add constraint tx_expense_ck
            check (type <> 'expense' or wallet_id is not null)");
        DB::statement("alter table transactions add constraint tx_income_ck
            check (type <> 'income' or source_id is not null)");
        DB::statement("alter table transactions add constraint tx_transfer_ck
            check (type <> 'transfer' or (to_account_id is not null and to_account_id <> account_id))");
        DB::statement("alter table transactions add constraint tx_savings_ck
            check (type <> 'savings' or goal_id is not null)");

        DB::statement('create index transactions_family_date_idx
            on transactions (family_id, transaction_date desc) where deleted_at is null');
        DB::statement('create index transactions_wallet_date_idx on transactions (wallet_id, transaction_date)');
        DB::statement('create index transactions_account_date_idx on transactions (account_id, transaction_date)');
        DB::statement('create index transactions_goal_idx on transactions (goal_id) where goal_id is not null');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
