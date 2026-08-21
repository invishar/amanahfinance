<?php

namespace App\Http\Controllers\Api;

use App\Actions\AiProviderErrors\AiProviderErrorActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAiProviderErrorRequest;
use App\Http\Resources\AiProviderErrorResource;
use App\Models\AiProviderError;

// Platform admin monitoring untuk kegagalan panggilan LLM (lihat
// AssistantService::logProviderError()) -- gated is_admin
// (AiProviderErrorPolicy), lintas-family seperti AdminUserController.
// Read-only: baris ditulis internal, tidak ada store/update/destroy.
class AdminAiErrorController extends Controller
{
    public function __construct(private AiProviderErrorActions $actions) {}

    public function index(IndexAiProviderErrorRequest $request)
    {
        $this->authorize('viewAny', AiProviderError::class);

        return AiProviderErrorResource::collection($this->actions->index($request->validated()));
    }
}
