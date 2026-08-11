<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Actions\LlmSettings\LlmSettingActions;
use App\Services\Ai\AnthropicConversationRunner;
use App\Services\Ai\Contracts\ConversationRunner;
use App\Support\CurrentFamily;
use Illuminate\Support\ServiceProvider;

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

        // Bound to the real SDK wrapper by default; tests rebind this to a
        // fake that invokes the tool closures directly instead of faking
        // Anthropic's wire format.
        $this->app->bind(ConversationRunner::class, AnthropicConversationRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
