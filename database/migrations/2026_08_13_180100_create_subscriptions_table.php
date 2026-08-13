<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur: pending_payment (family pilih paket + konfirmasi pembayaran)
     * -> active (platform admin approve, starts_at/ends_at diisi dari
     * plan.duration_days) atau rejected (admin tolak) -> expired (job
     * harian amana:expire-subscriptions, lihat routes/console.php).
     * `amount` adalah snapshot harga plan saat request, bukan referensi
     * live ke subscription_plans.price yang bisa berubah kemudian.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('subscription_plans');
            $table->string('status')->default('pending_payment');
            $table->bigInteger('amount');
            $table->text('payment_note')->nullable();
            $table->text('payment_proof_url')->nullable(); // dari POST /uploads, mis. foto bukti transfer
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignUuid('requested_by')->nullable()
                ->constrained('family_members')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();
            $table->index(['family_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        DB::statement('alter table subscriptions add constraint subscriptions_amount_ck
            check (amount > 0)');
        DB::statement("alter table subscriptions add constraint subscriptions_status_ck
            check (status in ('pending_payment','active','rejected','expired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
