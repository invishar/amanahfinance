<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['family_id', 'actor_id', 'entity', 'entity_id', 'action', 'diff'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'diff' => 'array',
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
     * @return BelongsTo<FamilyMember, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'actor_id');
    }
}
