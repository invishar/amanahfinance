<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiActionResource;
use App\Models\AiAction;

// Read-only: AI never writes business tables directly (aturan #5) and
// ai_actions itself is only ever mutated by ConfirmAiAction -- no
// create/update/destroy exists here on purpose.
class AiActionController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AiAction::class);

        return AiActionResource::collection(
            AiAction::query()->orderByDesc('created_at')->paginate(20)
        );
    }

    public function show(AiAction $aiAction)
    {
        $this->authorize('view', $aiAction);

        return new AiActionResource($aiAction);
    }
}
