<?php

namespace App\Actions\Chat;

use App\Jobs\ProcessAssistantMessage;
use App\Models\ChatMessage;
use App\Models\ChatThread;

class ChatMessageActions
{
    public function create(ChatThread $chatThread, array $data): ChatMessage
    {
        $message = $chatThread->messages()->create([
            ...$data,
            'role' => 'user',
        ]);

        // fresh() first: created_at is DB useCurrent(), not set by create()
        // itself, and last_message_at below must use the real value.
        $message = $message->fresh();

        $chatThread->update(['last_message_at' => $message->created_at]);

        // LLM call happens in the queue, never in this web request (aturan
        // CLAUDE.md). Amina's reply arrives later as its own ChatMessage.
        ProcessAssistantMessage::dispatch($message->id);

        return $message;
    }
}
