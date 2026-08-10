<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wallets = kantong anggaran / kategori pengeluaran
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('wallet');        // nama ikon Lucide
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
