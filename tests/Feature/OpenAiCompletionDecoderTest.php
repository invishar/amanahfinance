<?php

use App\Services\Ai\OpenAiCompletionDecoder;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

function completionStream(array $chunks, bool $done = true): Response
{
    $body = implode("\r\n\r\n", array_map(fn ($chunk) => 'data: '.json_encode($chunk), $chunks));

    return new Response(new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $body.($done ? "\r\n\r\ndata: [DONE]\r\n\r\n" : '')));
}

test('stream decoder preserves fragmented text tool arguments and final usage', function () {
    $result = OpenAiCompletionDecoder::decode(completionStream([
        ['choices' => [['index' => 0, 'delta' => ['content' => 'Cek ', 'tool_calls' => [['index' => 0, 'id' => 'a', 'function' => ['name' => 'review', 'arguments' => '{"topic":']]]]]]],
        ['choices' => [['index' => 0, 'delta' => ['content' => 'saldo.', 'tool_calls' => [['index' => 0, 'function' => ['arguments' => '"accounts"}']]]]]]],
        ['choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']]],
        ['choices' => [], 'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10]],
    ]));
    expect($result['choices'][0]['message']['content'])->toBe('Cek saldo.')
        ->and($result['choices'][0]['message']['tool_calls'][0]['function']['arguments'])->toBe('{"topic":"accounts"}')
        ->and($result['usage']['completion_tokens'])->toBe(10);
});

test('gateway complete tool calls without indices stay separate', function () {
    $result = OpenAiCompletionDecoder::decode(completionStream([
        ['choices' => [['delta' => ['tool_calls' => [
            ['id' => 'a', 'function' => ['name' => 'review', 'arguments' => '{}']],
            ['id' => 'b', 'function' => ['name' => 'playbook', 'arguments' => '{}']],
        ]], 'finish_reason' => 'tool_calls']]],
    ]));
    expect(array_column(array_column($result['choices'][0]['message']['tool_calls'], 'function'), 'name'))->toBe(['review', 'playbook']);
});

test('truncated stream is rejected before executing partial tool arguments', function () {
    expect(fn () => OpenAiCompletionDecoder::decode(completionStream([
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'a', 'function' => ['name' => 'create_transaction', 'arguments' => '{']]]]]]],
    ], false)))->toThrow(RuntimeException::class);
});

test('gateway reusing index zero for different tool ids does not concatenate calls', function () {
    $result = OpenAiCompletionDecoder::decode(completionStream([
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'a', 'function' => ['name' => 'review', 'arguments' => '']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"topic":"financial_review"}']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'b', 'function' => ['name' => 'playbook', 'arguments' => '']]]]]]],
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '{"topic":"budgeting"}']]]], 'finish_reason' => 'tool_calls']]],
    ]));
    $calls = $result['choices'][0]['message']['tool_calls'];
    expect(array_column(array_column($calls, 'function'), 'name'))->toBe(['review', 'playbook'])
        ->and($calls[0]['function']['arguments'])->toBe('{"topic":"financial_review"}')
        ->and($calls[1]['function']['arguments'])->toBe('{"topic":"budgeting"}');
});
