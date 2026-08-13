<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Platform-wide, not family-scoped -- no BelongsToFamily trait here on purpose.
#[Fillable(['key', 'model', 'base_url', 'updated_by'])]
#[Hidden(['key'])]
class LlmSetting extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            // Encrypted at rest with APP_KEY -- a raw DB dump/backup never
            // exposes the key (aturan #7).
            'key' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
