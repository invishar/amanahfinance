<?php

namespace App\Models;

use Database\Factories\AiProviderErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Platform-wide, not family-scoped -- no BelongsToFamily trait here on
// purpose (sama seperti LlmSetting): admin perlu melihat kegagalan provider
// lintas semua family sekaligus lewat GET /admin/ai-errors. Ditulis
// satu-satunya dari AssistantService::logProviderError(), tidak pernah lewat
// endpoint publik.
#[Fillable(['family_id', 'thread_id', 'message_id', 'model', 'status', 'exception', 'body'])]
class AiProviderError extends Model
{
    /** @use HasFactory<AiProviderErrorFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<ChatThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
