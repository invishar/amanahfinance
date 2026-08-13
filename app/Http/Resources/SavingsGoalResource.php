<?php

namespace App\Http\Resources;

use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin SavingsGoal */
class SavingsGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'target_name' => $this->target_name,
            'target_amount' => $this->target_amount,
            'current_amount' => $this->current_amount,
            'percent' => $this->target_amount > 0
                ? (int) round(min($this->current_amount, $this->target_amount) / $this->target_amount * 100)
                : 0,
            'deadline' => $this->deadline?->toDateString(),
            'eta' => $this->eta(),
            'icon' => $this->icon,
            'color' => $this->color,
            'account_id' => $this->account_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'achieved_at' => $this->achieved_at,
        ];
    }

    // Estimasi tercapai (CLAUDE.md aturan #6: "estimasi target" dihitung
    // server, bukan klien) -- diproyeksikan linear dari rata-rata kontribusi
    // bulanan sejak setoran pertama. null kalau: goal tidak/tidak lagi aktif,
    // sudah tercapai, atau belum ada histori setoran untuk diproyeksikan.
    private function eta(): ?string
    {
        if ($this->status !== 'active' || $this->current_amount <= 0 || $this->current_amount >= $this->target_amount) {
            return null;
        }

        $firstContribution = Transaction::query()
            ->where('family_id', $this->family_id)
            ->where('goal_id', $this->id)
            ->where('type', 'savings')
            ->min('transaction_date');

        if ($firstContribution === null) {
            return null;
        }

        // (int) cast: diffInMonths() returns a float (fractional months) in
        // this Carbon version -- first contribution is a bare date (midnight)
        // while now() carries a time-of-day, so the raw diff is always a hair
        // over N whole months and would silently inflate monthsElapsed by 1.
        $monthsElapsed = max(1, (int) Carbon::parse($firstContribution)->diffInMonths(now()) + 1);
        $avgMonthly = $this->current_amount / $monthsElapsed;

        if ($avgMonthly <= 0) {
            return null;
        }

        $monthsRemaining = (int) ceil(($this->target_amount - $this->current_amount) / $avgMonthly);

        return now()->addMonthsNoOverflow($monthsRemaining)->startOfMonth()->toDateString();
    }
}
