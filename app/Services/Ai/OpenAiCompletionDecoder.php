<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use RuntimeException;

/** Decode a buffered completion, including gateways that only work in SSE mode. */
class OpenAiCompletionDecoder
{
    public static function decode(Response $response): array
    {
        $json = $response->json();
        if (is_array($json) && isset($json['choices'])) {
            return $json;
        }

        $message = ['role' => 'assistant', 'content' => ''];
        $calls = [];
        $usage = [];
        $finish = null;
        $complete = false;
        $body = str_replace(["\r\n", "\r"], "\n", $response->body());
        foreach (preg_split('/\n\n+/', $body) as $frame) {
            $data = [];
            foreach (explode("\n", $frame) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5), ' ');
                }
            }
            if ($data === []) {
                continue;
            }
            $data = implode("\n", $data);
            if (trim($data) === '[DONE]') {
                $complete = true;
                break;
            }
            $chunk = json_decode($data, true);
            if (! is_array($chunk) || isset($chunk['error'])) {
                throw new RuntimeException('Invalid provider stream frame.');
            }
            $usage = $chunk['usage'] ?? $usage;
            foreach ($chunk['choices'] ?? [] as $choice) {
                if (($choice['index'] ?? 0) !== 0) {
                    continue;
                }
                $delta = $choice['delta'] ?? [];
                $message['content'] .= $delta['content'] ?? '';
                foreach ($delta['tool_calls'] ?? [] as $call) {
                    // Some gateways send complete tool calls with an id but
                    // omit index. Never merge two different tools into one.
                    $index = $call['index'] ?? ($call['id'] ?? null);
                    if ($index === null) {
                        if (count($calls) !== 1) {
                            throw new RuntimeException('Ambiguous provider tool stream.');
                        }
                        $index = array_key_first($calls);
                    }
                    $calls[$index] ??= ['id' => '', 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];
                    if (isset($call['id'])) {
                        $calls[$index]['id'] = $call['id'];
                    }
                    $calls[$index]['function']['name'] .= $call['function']['name'] ?? '';
                    $calls[$index]['function']['arguments'] .= $call['function']['arguments'] ?? '';
                }
                if (isset($choice['finish_reason'])) {
                    $finish = $choice['finish_reason'];
                    $complete = true;
                }
            }
        }
        if (! $complete) {
            throw new RuntimeException('Provider stream ended before completion.');
        }
        if ($calls !== []) {
            $message['tool_calls'] = array_values($calls);
        }

        return ['choices' => [['message' => $message, 'finish_reason' => $finish]], 'usage' => $usage];
    }
}
