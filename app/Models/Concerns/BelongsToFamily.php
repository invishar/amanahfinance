<?php

namespace App\Models\Concerns;

use App\Support\CurrentFamily;
use Illuminate\Database\Eloquent\Builder;

/**
 * Second safety net for tenant isolation (first is ResolveFamily + explicit
 * policy checks). MySQL/MariaDB has no row-level security, so this global
 * scope is what actually keeps family A from ever loading family B's rows.
 *
 * Requires ResolveFamily to run before route model binding (SubstituteBindings)
 * -- see bootstrap/app.php priority list.
 */
trait BelongsToFamily
{
    protected static function bootBelongsToFamily(): void
    {
        static::addGlobalScope('family', function (Builder $query) {
            $familyId = app(CurrentFamily::class)->id();

            if ($familyId !== null) {
                $query->where($query->getModel()->getTable().'.family_id', $familyId);
            }
        });

        static::creating(function ($model) {
            if (! $model->family_id && $familyId = app(CurrentFamily::class)->id()) {
                $model->family_id = $familyId;
            }
        });
    }
}
