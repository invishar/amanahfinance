<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Katalog paket langganan platform -- bukan resource per-family (mirip
     * llm_settings), dikelola lewat users.is_platform_admin. `code` dipakai
     * untuk referensi stabil (bukan `name`, yang boleh berubah tampilannya).
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->text('name');
            $table->bigInteger('price');
            $table->integer('duration_days');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('alter table subscription_plans add constraint subscription_plans_price_ck
            check (price > 0)');
        DB::statement('alter table subscription_plans add constraint subscription_plans_duration_ck
            check (duration_days > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
