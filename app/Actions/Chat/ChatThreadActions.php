<?php

namespace App\Actions\Chat;

use App\Models\ChatThread;
use App\Support\CurrentFamily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChatThreadActions
{
    /**
     * @param  array{kind?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = ChatThread::query();

        if (filled($filters['kind'] ?? null)) {
            $query->where('kind', $filters['kind']);
        }

        return $query->orderByDesc('last_message_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

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
