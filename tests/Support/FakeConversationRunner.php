<?php

namespace Tests\Support;

use App\Services\Ai\Contracts\ConversationRunner;
use App\Services\Ai\ConversationResult;

// Fakes only the LLM network boundary. The scripted plan drives real
// BetaRunnableTool::run() closures from AssistantService::buildTools(), so
// AiAction rows, NameResolver fuzzy-matching, etc. are exercised for real --
// satisfies CLAUDE.md's "LLM selalu di-mock di test" without hand-faking
// Anthropic's wire JSON.
class FakeConversationRunner implements ConversationRunner
{
    /** @var array<int, array{tool: string, input: array<string, mixed>}> */
    private array $toolCalls;

    private string $finalText;

    private ?int $inputTokens;

    private ?int $outputTokens;

    /**
     * @param  array<int, array{tool: string, input: array<string, mixed>}>  $toolCalls
     */
    public function __construct(array $toolCalls = [], string $finalText = 'Oke, siap!', ?int $inputTokens = 123, ?int $outputTokens = 45)
    {
        $this->toolCalls = $toolCalls;
        $this->finalText = $finalText;
        $this->inputTokens = $inputTokens;
        $this->outputTokens = $outputTokens;
    }

    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): ConversationResult
    {
        foreach ($this->toolCalls as $call) {
            $tool = collect($tools)->first(fn ($t) => $t->name() === $call['tool']);

            if ($tool === null) {
                throw new \RuntimeException("FakeConversationRunner: no tool named \"{$call['tool']}\" was registered.");
            }

            $tool->run($call['input']);
        }

        return new ConversationResult($this->finalText, $this->inputTokens, $this->outputTokens);
    }
}
