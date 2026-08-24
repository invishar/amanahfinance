<?php

namespace App\Services\Ai;

// Token usage tags along so AssistantService can log it to ai_logs
// (local-only, lihat CLAUDE.md/AssistantService::logLocalDebug()) without
// ConversationRunner implementations having to know about that table.
// Nullable: providers that don't report usage per-call can still return text.
final readonly class ConversationResult
{
    public function __construct(
        public string $text,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
