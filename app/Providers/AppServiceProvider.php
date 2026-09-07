<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Actions\LlmSettings\LlmSettingActions;
use App\Services\Ai\AnthropicConversationRunner;
use App\Services\Ai\Contracts\ConversationRunner;
use App\Services\Ai\OpenAiCompatibleConversationRunner;
use App\Support\CurrentFamily;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Inertia\Ssr\SsrRenderFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentFamily::class);

        // Lazy: the factory only runs when something actually resolves the
        // client, so a request that never touches the AI flow never hits the
        // DB for this. Re-read every time the container is fresh (every
        // request, no Octane here), so an admin's llm_settings update takes
        // effect immediately -- no redeploy/restart needed.
        $this->app->singleton(AnthropicClient::class, function () {
            $settings = $this->app->make(LlmSettingActions::class)->current();

            return new AnthropicClient(
                apiKey: $settings->key ?: '',
                baseUrl: $settings->base_url ?: null,
            );
        });

        // Picks the runner per llm_settings.provider -- Anthropic's SDK and a
        // generic OpenAI-compatible client (Groq, dst) speak incompatible
        // wire protocols, so this can't be inferred from base_url/model.
        // Re-read every request, same reasoning as the AnthropicClient
        // singleton above. Tests rebind this to a fake that invokes the tool
        // closures directly instead of faking either wire format.
        $this->app->bind(ConversationRunner::class, function ($app) {
            $provider = $app->make(LlmSettingActions::class)->current()->provider;

            return $provider === 'openai_compatible'
                ? $app->make(OpenAiCompatibleConversationRunner::class)
                : $app->make(AnthropicConversationRunner::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SSR dimatikan selama `npm run dev`: saat Vite jalan hot, Inertia
        // mengarahkan permintaan render ke dev server Vite (/__inertia_ssr),
        // dan setup di sini tidak menyediakan endpoint itu. Tanpa baris ini
        // tiap halaman di dev memicu satu request gagal dulu (fallback tetap
        // jalan, tapi ribut di log). Di build produksi hot file tidak ada,
        // jadi SSR aktif seperti biasa.
        Inertia::disableSsr(fn () => Vite::isRunningHot());

        // Kegagalan SSR tidak pernah bikin halaman mati -- Inertia langsung
        // jatuh ke render sisi klien. Bagusnya user tidak kena error, jeleknya
        // proses SSR bisa mati berhari-hari tanpa ada yang tahu (masalah yang
        // sama dengan cron silent di CLAUDE.md). Jadi dicatat: `connection`
        // hampir selalu berarti proses Node-nya tidak jalan, tipe lain berarti
        // ada kode halaman yang tidak aman dirender tanpa DOM.
        Event::listen(function (SsrRenderFailed $event): void {
            Log::warning('Inertia SSR gagal, halaman dirender di klien saja.', $event->toArray());
        });
    }
}
