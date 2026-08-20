<?php

use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Models\LlmSetting;
use App\Services\Ai\OpenAiCompatibleConversationRunner;
use Illuminate\Support\Facades\Http;

// Wire-level test for the Groq/OpenAI-compatible boundary itself (mirrors
// how FakeConversationRunner covers the Anthropic boundary for the rest of
// the suite) -- Http::fake() stands in for the actual HTTP call, but the
// request shape, tool-calling loop, and BetaRunnableTool::run() closures are
// exercised for real.
function makeTool(string $name, \Closure $run): BetaRunnableTool
{
    return new BetaRunnableTool(
        definition: [
            'name' => $name,
            'description' => 'test tool',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ],
        run: $run,
    );
}

test('sends chat completions request with bearer auth and system prompt', function () {
    LlmSetting::factory()->create([
        'key' => 'gsk_test_key',
        'model' => 'openai/gpt-oss-120b',
        'base_url' => 'https://api.groq.com/openai/v1',
        'provider' => 'openai_compatible',
    ]);

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Halo juga!']]],
        ]),
    ]);

    $result = app(OpenAiCompatibleConversationRunner::class)->run(
        model: 'openai/gpt-oss-120b',
        system: 'Kamu adalah Amina.',
        messages: [['role' => 'user', 'content' => 'halo']],
        tools: [],
        maxIterations: 4,
    );

    expect($result)->toBe('Halo juga!');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer gsk_test_key')
            && $request['model'] === 'openai/gpt-oss-120b'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'Kamu adalah Amina.'];
    });
});

test('executes tool calls and feeds results back before returning final text', function () {
    LlmSetting::factory()->create([
        'key' => 'gsk_test_key',
        'model' => 'openai/gpt-oss-120b',
        'base_url' => 'https://api.groq.com/openai/v1',
        'provider' => 'openai_compatible',
    ]);

    $received = null;

    $tool = makeTool('create_transaction', function (array $input) use (&$received) {
        $received = $input;

        return 'Draft create_transaction tersimpan.';
    });

    Http::fakeSequence()
        ->push([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => 'create_transaction',
                            'arguments' => json_encode(['amount' => 20000]),
                        ],
                    ]],
                ],
            ]],
        ])
        ->push([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap, sudah aku catat drafnya.']]],
        ]);

    $result = app(OpenAiCompatibleConversationRunner::class)->run(
        model: 'openai/gpt-oss-120b',
        system: 'Kamu adalah Amina.',
        messages: [['role' => 'user', 'content' => 'jajan 20rb']],
        tools: [$tool],
        maxIterations: 4,
    );

    expect($received)->toBe(['amount' => 20000]);
    expect($result)->toBe('Siap, sudah aku catat drafnya.');

    Http::assertSentCount(2);
});

test('stops after maxIterations without a final text response', function () {
    LlmSetting::factory()->create([
        'key' => 'gsk_test_key',
        'base_url' => 'https://api.groq.com/openai/v1',
        'provider' => 'openai_compatible',
    ]);

    $tool = makeTool('advice', fn () => 'noted');

    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'tool_calls' => [[
                        'id' => 'call_x',
                        'type' => 'function',
                        'function' => ['name' => 'advice', 'arguments' => '{}'],
                    ]],
                ],
            ]],
        ]),
    ]);

    $result = app(OpenAiCompatibleConversationRunner::class)->run(
        model: 'openai/gpt-oss-120b',
        system: 'Kamu adalah Amina.',
        messages: [],
        tools: [$tool],
        maxIterations: 2,
    );

    expect($result)->toBe('');
    Http::assertSentCount(2);
});
