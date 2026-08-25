<?php

namespace App\Actions\AiLogs;

use App\Models\AiLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiLogActions
{
    /**
     * @param  array{model?: string, family_id?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = AiLog::query()->with('family')->orderByDesc('created_at');

        if (filled($filters['model'] ?? null)) {
            $query->where('model', $filters['model']);
        }

        if (filled($filters['family_id'] ?? null)) {
            $query->where('family_id', $filters['family_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }
}
