<?php

namespace App\Services\Ai\Contracts;

use Anthropic\Lib\Tools\BetaRunnableTool;

// Seluruh batas SDK LLM ada di balik interface ini, supaya "LLM selalu
// di-mock di test" (CLAUDE.md) bisa dipenuhi dengan fake yang benar-benar
// menjalankan closure tool -- bukan menebak-nebak format JSON wire Anthropic.
interface ConversationRunner
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, BetaRunnableTool>  $tools
     */
    public function run(string $model, string $system, array $messages, array $tools, int $maxIterations): string;
}
