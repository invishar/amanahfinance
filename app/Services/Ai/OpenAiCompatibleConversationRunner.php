<?php

namespace App\Services\Ai;

use Anthropic\Core\Contracts\BaseModel;
use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Actions\LlmSettings\LlmSettingActions;
use App\Services\Ai\Contracts\ConversationRunner;
use Illuminate\Support\Collection;
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

    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
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
        $inputTokens = 0;
        $outputTokens = 0;

        for ($i = 0; $i < $maxIterations; $i++) {
            $response = Http::withToken($settings->key ?: '')
                ->timeout(60)
                ->acceptJson()
                ->post("{$baseUrl}/chat/completions", array_filter([
                    'model' => $model,
                    'messages' => $chatMessages,
                    'tools' => $payloadTools,
                    // Amina diminta menjawab 1-2 kalimat. Tetap configurable
                    // untuk model reasoning yang mungkin butuh ruang lebih.
                    'max_tokens' => (int) config('services.llm.max_tokens', 768),
                    // Eksplisit false: sebagian provider (mis. 9Router) kalau
                    // field ini tak dikirim, menempelkan literal `data:
                    // [DONE]` langsung setelah body JSON non-stream tanpa
                    // pemisah -- bikin json_decode() gagal senyap dan Amina
                    // dianggap membalas kosong.
                    'stream' => false,
                ], fn ($value) => $value !== null))
                ->throw();

            // Bentuk usage ala OpenAI Chat Completions (prompt_tokens /
            // completion_tokens) -- dijumlah per giliran, sama seperti
            // AnthropicConversationRunner, supaya mewakili satu turn penuh.
            $inputTokens += (int) ($response->json('usage.prompt_tokens') ?? 0);
            $outputTokens += (int) ($response->json('usage.completion_tokens') ?? 0);

            $message = $response->json('choices.0.message') ?? [];

            $toolCalls = $message['tool_calls'] ?? [];
            $chatMessages[] = $this->assistantMessageForHistory($message);

            if ($toolCalls === []) {
                $finalText = $this->stripReasoning((string) ($message['content'] ?? ''));
                break;
            }

            foreach ($toolCalls as $call) {
                $chatMessages[] = $this->runToolCall($call, $toolsByName);
            }
        }

        return new ConversationResult($finalText, $inputTokens, $outputTokens);
    }

    // Sebagian model reasoning (mis. deepseek-r1-distill di balik model
    // "amanafinance" pada 9Router) menaruh chain-of-thought-nya langsung di
    // `content` lewat tag <think>...</think>, bukan di field terpisah --
    // beda dari gpt-oss (Groq) yang reasoning-nya tidak pernah muncul di
    // content. Dibuang di sini supaya balasan Amina tidak bocor jadi
    // paragraf mikir panjang berbahasa Inggris (lihat aturan persona
    // "1-3 kalimat" di config/amina.php).
    private function stripReasoning(string $content): string
    {
        return trim((string) preg_replace('/<think>.*?<\/think>/is', '', $content));
    }

    /**
     * @param  array<string, mixed>  $call
     * @param  Collection<string, BetaRunnableTool>  $toolsByName
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
        // `content` HARUS selalu ada (string, boleh kosong) -- sebagian
        // provider (mis. 9Router/Cloudflare) menolak total request kalau
        // pesan assistant dengan tool_calls tidak menyertakan key `content`
        // sama sekali, bukan cuma mengabaikannya seperti OpenAI/Groq. Jadi
        // cuma `tool_calls` yang di-filter kalau kosong, bukan `content`.
        return array_filter([
            'role' => 'assistant',
            'content' => (string) ($message['content'] ?? ''),
            'tool_calls' => $message['tool_calls'] ?? null,
        ], fn ($value, $key) => $key === 'content' || $value !== null, ARRAY_FILTER_USE_BOTH);
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
