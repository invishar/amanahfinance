<?php

namespace App\Actions\Chat;

use App\Models\ChatThread;
use App\Support\CurrentFamily;

class ChatThreadActions
{
    public function create(array $data): ChatThread
    {
        return ChatThread::create([
            ...$data,
            'kind' => $data['kind'] ?? 'general',
            'member_id' => app(CurrentFamily::class)->memberId(),
        ]);
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
