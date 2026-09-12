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
function makeTool(string $name, Closure $run): BetaRunnableTool
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
            'usage' => ['prompt_tokens' => 42, 'completion_tokens' => 7],
        ]),
    ]);

    $result = app(OpenAiCompatibleConversationRunner::class)->run(
        model: 'openai/gpt-oss-120b',
        system: 'Kamu adalah Amina.',
        messages: [['role' => 'user', 'content' => 'halo']],
        tools: [],
        maxIterations: 4,
    );

    expect($result->text)->toBe('Halo juga!');
    expect($result->inputTokens)->toBe(42);
    expect($result->outputTokens)->toBe(7);

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
    expect($result->text)->toBe('Siap, sudah aku catat drafnya.');

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

    expect($result->text)->toBe('');
    Http::assertSentCount(2);
});

test('last round synthesizes review results with tool choice none', function () {
    LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://router.test/v1']);
    Http::fakeSequence()->push(['choices' => [['message' => ['tool_calls' => [[
        'id' => 'review', 'type' => 'function',
        'function' => ['name' => 'review', 'arguments' => '{}'],
    ]]]]]])->push(['choices' => [['message' => ['content' => 'Catatan belum lengkap; cocokkan saldo dulu.']]]]);
    $result = app(OpenAiCompatibleConversationRunner::class)->run('model', 'system', [], [makeTool('review', fn () => ['balance' => 2000000])], 2);
    expect($result->text)->toContain('cocokkan saldo');
    $requests = Http::recorded();
    expect($requests[1][0]['tool_choice'])->toBe('none')
        ->and(json_encode($requests[1][0]['messages']))->toContain('2000000');
});

test('empty or unfinished reasoning gets one recovery without leaking reasoning', function () {
    LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://router.test/v1']);
    Http::fakeSequence()->push(['choices' => [['message' => ['content' => '<think>private unfinished reasoning']]]])
        ->push(['choices' => [['message' => ['content' => 'Mulai dari kebutuhan pokok dan tagihan.']]]]);
    $result = app(OpenAiCompatibleConversationRunner::class)->run('model', 'system', [], [], 5);
    expect($result->text)->toBe('Mulai dari kebutuhan pokok dan tagihan.');
    Http::assertSentCount(2);
});

test('repeated empty output has bounded recovery', function () {
    LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://router.test/v1']);
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '']]]])]);
    expect(app(OpenAiCompatibleConversationRunner::class)->run('model', 'system', [], [], 5)->text)->toBe('');
    Http::assertSentCount(2);
});

test('empty length-limited output recovers with a larger token budget and SSE', function () {
    config(['services.llm.max_tokens' => 768]);
    LlmSetting::factory()->create(['provider' => 'openai_compatible', 'base_url' => 'https://router.test/v1']);
    $sse = 'data: '.json_encode(['choices' => [['index' => 0, 'delta' => ['content' => 'Cocokkan saldo dahulu.'], 'finish_reason' => 'stop']]])."\n\ndata: [DONE]\n\n";
    Http::fakeSequence()->push(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]])
        ->push($sse, 200, ['Content-Type' => 'text/event-stream']);
    $result = app(OpenAiCompatibleConversationRunner::class)->run('model', 'system', [], [], 5);
    expect($result->text)->toBe('Cocokkan saldo dahulu.');
    $requests = Http::recorded();
    expect($requests[1][0]['stream'])->toBeTrue()->and($requests[1][0]['max_tokens'])->toBe(2304);
});
