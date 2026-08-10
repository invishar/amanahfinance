<?php

namespace App\Actions\Chat;

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

        $chatThread->update(['last_message_at' => $message->created_at]);

        return $message;
    }
}
