<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which wire protocol `key`/`base_url` should be spoken with. Anthropic's
     * SDK (AnthropicConversationRunner) and a generic OpenAI-compatible chat
     * completions client (OpenAiCompatibleConversationRunner, for providers
     * like Groq) are not wire-compatible, so the runner has to be selected
     * explicitly instead of guessed from base_url/model.
     */
    public function up(): void
    {
        Schema::table('llm_settings', function (Blueprint $table) {
            $table->string('provider')->default('anthropic')->after('base_url');
        });

        DB::statement("alter table llm_settings add constraint llm_settings_provider_ck
            check (provider in ('anthropic','openai_compatible'))");
    }

    public function down(): void
    {
        Schema::table('llm_settings', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
