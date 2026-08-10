<?php

namespace App\Http\Controllers\Api;

use App\Actions\Analytics\AnalyticsActions;
use App\Http\Controllers\Controller;
use App\Support\CurrentFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

// Read-only, no model of its own: available to any role in the resolved
// family (resolve.family middleware already guarantees that much), same
// baseline as index/show elsewhere. Never calls the LLM here -- any AI
// "insight" text is pre-cached by a daily job (aturan CLAUDE.md), not
// computed live, and isn't included yet since that job doesn't exist.
class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsActions $actions) {}

    public function summary(Request $request)
    {
        $request->validate([
            'month' => ['sometimes', 'date_format:Y-m'],
        ]);

        $period = $request->query('month')
            ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
            : now()->startOfMonth();

        return response()->json([
            'data' => $this->actions->summary(app(CurrentFamily::class)->id(), $period),
        ]);
    }
}
