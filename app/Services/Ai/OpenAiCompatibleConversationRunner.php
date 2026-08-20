<?php

namespace App\Services\Ai;

use Anthropic\Core\Contracts\BaseModel;
use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Actions\LlmSettings\LlmSettingActions;
use App\Services\Ai\Contracts\ConversationRunner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Untuk provider yang cuma expose endpoint Chat Completions ala OpenAI (Groq,
// dst) -- beda wire protocol total dari Anthropic SDK (bandingkan dengan
// AnthropicConversationRunner): header Authorization: Bearer alih-alih
// x-api-key, tool-calling lewat `tool_calls`/role=tool alih-alih blok content
// Anthropic. SDK resmi Anthropic tidak punya toolRunner untuk protokol ini,
// jadi loop tool-calling-nya diimplementasikan manual di sini.
class OpenAiCompatibleConversationRunner implements ConversationRunner
{
    public function __construct(private LlmSettingActions $settings) {}

    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): string
    {
        $settings = $this->settings->current();
        $baseUrl = rtrim($settings->base_url ?: '', '/');

        $chatMessages = [
            ['role' => 'system', 'content' => $system],
            ...$messages,
        ];

        $toolsByName = collect($tools)->keyBy(fn (BetaRunnableTool $tool) => $tool->name());
        $payloadTools = $toolsByName->isEmpty()
            ? null
            : $toolsByName->values()->map(fn (BetaRunnableTool $tool) => $this->toOpenAiTool($tool))->all();

        $finalText = '';

        for ($i = 0; $i < $maxIterations; $i++) {
            $message = Http::withToken($settings->key ?: '')
                ->timeout(60)
                ->acceptJson()
                ->post("{$baseUrl}/chat/completions", array_filter([
                    'model' => $model,
                    'messages' => $chatMessages,
                    'tools' => $payloadTools,
                    // Lebih tinggi dari batas Anthropic runner (1024) --
                    // model reasoning ala gpt-oss (Groq) menghabiskan banyak
                    // token buat `reasoning` sebelum `content` terisi; batas
                    // terlalu rendah membuat balasan terpotong kosong.
                    'max_tokens' => 2048,
                ], fn ($value) => $value !== null))
                ->throw()
                ->json('choices.0.message') ?? [];

            $toolCalls = $message['tool_calls'] ?? [];
            $chatMessages[] = $this->assistantMessageForHistory($message);

            if ($toolCalls === []) {
                $finalText = trim((string) ($message['content'] ?? ''));
                break;
            }

            foreach ($toolCalls as $call) {
                $chatMessages[] = $this->runToolCall($call, $toolsByName);
            }
        }

        return $finalText;
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  \Illuminate\Support\Collection<string, BetaRunnableTool>  $toolsByName
     * @return array{role: string, tool_call_id: string, content: string}
     */
    private function runToolCall(array $call, $toolsByName): array
    {
        $name = $call['function']['name'] ?? '';
        $tool = $toolsByName->get($name);
        $arguments = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

        $result = $tool !== null
            ? $tool->run($arguments)
            : "Tool \"{$name}\" tidak dikenal.";

        return [
            'role' => 'tool',
            'tool_call_id' => $call['id'] ?? Str::uuid()->toString(),
            'content' => is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string) $result,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function assistantMessageForHistory(array $message): array
    {
        return array_filter([
            'role' => 'assistant',
            'content' => $message['content'] ?? null,
            'tool_calls' => $message['tool_calls'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function toOpenAiTool(BetaRunnableTool $tool): array
    {
        $definition = $tool->definition instanceof BaseModel
            ? $tool->definition->toProperties()
            : $tool->definition;

        return [
            'type' => 'function',
            'function' => [
                'name' => $definition['name'],
                'description' => $definition['description'] ?? '',
                'parameters' => $definition['input_schema'] ?? ['type' => 'object', 'properties' => []],
            ],
        ];
    }
}
