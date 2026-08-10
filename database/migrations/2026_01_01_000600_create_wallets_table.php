<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wallets = kantong anggaran / kategori pengeluaran
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->text('name');
            $table->text('icon')->default('wallet');        // nama ikon Lucide
            $table->text('color')->nullable();
            $table->bigInteger('monthly_budget')->default(0);
            $table->boolean('rollover')->default(false);    // sisa budget dibawa ke bulan depan
            $table->boolean('is_archived')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['family_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
