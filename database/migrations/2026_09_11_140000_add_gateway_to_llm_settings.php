<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_settings', function (Blueprint $table) {
            $table->string('gateway')->default('direct');
            $table->string('selection_mode')->default('model');
        });
    }

    public function down(): void
    {
        Schema::table('llm_settings', fn (Blueprint $table) => $table->dropColumn(['gateway', 'selection_mode']));
    }
};
