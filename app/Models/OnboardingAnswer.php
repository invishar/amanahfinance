<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['family_id', 'question_key', 'answer', 'skipped', 'answered_at'])]
class OnboardingAnswer extends Model
{
    /** @use HasFactory<\Database\Factories\OnboardingAnswerFactory> */
    use BelongsToFamily, HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'skipped' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
