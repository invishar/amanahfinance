<?php

namespace App\Actions\AiProviderErrors;

use App\Models\AiProviderError;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AiProviderErrorActions
{
    /**
     * @param  array{status?: int, model?: string, per_page?: int}  $filters
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $query = AiProviderError::query()->with('family')->orderByDesc('created_at');

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['model'] ?? null)) {
            $query->where('model', $filters['model']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }
}
