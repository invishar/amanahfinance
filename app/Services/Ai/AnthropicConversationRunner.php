<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Services\Ai\Contracts\ConversationRunner;

class AnthropicConversationRunner implements ConversationRunner
{
    public function __construct(private Client $client) {}

    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
    {
        $runner = $this->client->beta->messages->toolRunner(
            maxTokens: 1024,
            messages: $messages,
            model: $model,
            tools: $tools,
            maxIterations: $maxIterations,
            extraParams: [
                'system' => $system,
            ],
        );

        $finalText = '';
        $inputTokens = 0;
        $outputTokens = 0;

        // toolRunner mengiterasi satu BetaMessage per giliran (bisa lebih
        // dari satu kalau ada tool_use di tengah); usage-nya per giliran,
        // jadi dijumlah supaya cost akhirnya mewakili satu turn percakapan
        // penuh, bukan cuma giliran terakhir.
        foreach ($runner as $message) {
            $inputTokens += $message->usage->inputTokens;
            $outputTokens += $message->usage->outputTokens;

            foreach ($message->content as $block) {
                if ($block->type === 'text' && trim($block->text) !== '') {
                    $finalText = $block->text;
                }
            }
        }

        return new ConversationResult($finalText, $inputTokens, $outputTokens);
    }
}
