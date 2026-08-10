<?php

namespace App\Actions\Chat;

use App\Models\ChatThread;
use App\Support\CurrentFamily;

class ChatThreadActions
{
    public function create(array $data): ChatThread
    {
        $thread = ChatThread::create([
            ...$data,
            'kind' => $data['kind'] ?? 'general',
            'member_id' => app(CurrentFamily::class)->memberId(),
        ]);

        // created_at is DB useCurrent(), not set by create() itself.
        return $thread->fresh();
    }

    public function update(ChatThread $chatThread, array $data): ChatThread
    {
        $chatThread->update($data);

        return $chatThread->fresh();
    }

    public function delete(ChatThread $chatThread): void
    {
        $chatThread->delete();
    }
}
