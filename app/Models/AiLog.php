<?php

namespace App\Models;

use Database\Factories\AiLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Local-only debugging trail -- see migration 2026_08_24_100000_create_ai_logs_table
// and AssistantService::logLocalDebug(). Not family-scoped (no BelongsToFamily
// trait) on purpose: this never runs outside app()->environment('local'), so
// there is no cross-tenant exposure to guard against.
#[Fillable(['family_id', 'thread_id', 'message_id', 'model', 'user_prompt', 'system_prompt', 'input_tokens', 'output_tokens'])]
class AiLog extends Model
{
    /** @use HasFactory<AiLogFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
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
