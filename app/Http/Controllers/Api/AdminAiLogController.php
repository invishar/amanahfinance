<?php

namespace App\Http\Controllers\Api;

use App\Actions\AiLogs\AiLogActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAiLogRequest;
use App\Http\Resources\AiLogResource;
use App\Models\AiLog;

// Debugging lokal untuk AssistantService (lihat AssistantService::logLocalDebug())
// -- gated is_admin (AiLogPolicy), lintas-family seperti AdminAiErrorController.
// Baris cuma pernah ada kalau server API sendiri jalan dengan APP_ENV=local, jadi
// di luar itu endpoint ini selalu mengembalikan list kosong. Read-only.
class AdminAiLogController extends Controller
{
    public function __construct(private AiLogActions $actions) {}

    public function index(IndexAiLogRequest $request)
    {
        $this->authorize('viewAny', AiLog::class);

        return AiLogResource::collection($this->actions->index($request->validated()));
    }
}
