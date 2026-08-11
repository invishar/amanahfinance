<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Services\Ai\Contracts\ConversationRunner;

class AnthropicConversationRunner implements ConversationRunner
{
    public function __construct(private Client $client) {}

    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): string
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

        foreach ($runner as $message) {
            foreach ($message->content as $block) {
                if ($block->type === 'text' && trim($block->text) !== '') {
                    $finalText = $block->text;
                }
            }
        }

        return $finalText;
    }
}
