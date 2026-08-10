<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat budget per bulan agar laporan bulan lalu tidak ikut berubah
        Schema::create('wallet_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->date('period');                          // selalu tanggal 1 bulan ybs
            $table->bigInteger('amount');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['wallet_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_budgets');
    }
};
