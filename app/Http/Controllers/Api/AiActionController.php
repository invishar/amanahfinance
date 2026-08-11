<?php

namespace App\Http\Controllers\Api;

use App\Actions\AiActions\ConfirmAiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmAiActionRequest;
use App\Http\Requests\RejectAiActionRequest;
use App\Http\Resources\AiActionResource;
use App\Models\AiAction;

// AI never writes business tables directly (aturan #5); confirm()/reject()
// are the only mutation entry points, both delegating to ConfirmAiAction --
// no generic create/update/destroy exists here on purpose.
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

    public function confirm(ConfirmAiActionRequest $request, AiAction $aiAction, ConfirmAiAction $action)
    {
        return new AiActionResource($action->confirm($aiAction, $request->all()));
    }

    public function reject(RejectAiActionRequest $request, AiAction $aiAction, ConfirmAiAction $action)
    {
        return new AiActionResource($action->reject($aiAction));
    }
}
