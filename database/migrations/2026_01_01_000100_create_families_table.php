<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->text('name');
            $table->char('currency', 3)->default('IDR');
            $table->text('timezone')->default('Asia/Jakarta');
            $table->boolean('onboarding_done')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
