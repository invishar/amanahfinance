<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide LLM credentials (aturan #7: kunci hanya hidup di server).
     * Singleton by convention, not by DB constraint: the app always reads/
     * writes the single row via LlmSetting::current() (see LlmSettingActions).
     * `key` is stored through Laravel's `encrypted` cast, so even a raw DB
     * dump doesn't expose it without APP_KEY.
     */
    public function up(): void
    {
        Schema::create('llm_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('key')->nullable();
            $table->string('model');
            $table->text('base_url')->nullable();
            $table->foreignUuid('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_settings');
    }
};
