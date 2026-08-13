<?php

namespace Tests;

use App\Services\Ai\Contracts\ConversationRunner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeConversationRunner;

abstract class TestCase extends BaseTestCase
{
    /**
     * migrate:fresh only drops tables by default; v_wallet_month and
     * v_cashflow_month are views (2026_01_01_001800_create_reporting_views),
     * so without this the second test run fails with "already exists".
     */
    protected $dropViews = true;

    protected function setUp(): void
    {
        parent::setUp();

        // QUEUE_CONNECTION=sync in testing, so posting a ChatMessage runs
        // ProcessAssistantMessage inline. Bind a no-op fake here so that
        // happens without ever touching the real LLM (CLAUDE.md: "LLM
        // selalu di-mock di test") -- tests exercising AssistantService
        // itself rebind this with a scripted plan.
        $this->app->bind(ConversationRunner::class, fn () => new FakeConversationRunner);
    }
}
