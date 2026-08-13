<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Database\Factories\AiActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'message_id', 'family_id', 'action', 'payload', 'status', 'result_table',
    'result_id', 'confidence', 'resolved_at', 'resolved_by',
])]
class AiAction extends Model
{
    /** @use HasFactory<AiActionFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confidence' => 'decimal:2',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'resolved_by');
    }
}
