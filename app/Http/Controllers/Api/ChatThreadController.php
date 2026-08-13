<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\ChatThreadActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexChatThreadRequest;
use App\Http\Requests\StoreChatThreadRequest;
use App\Http\Requests\UpdateChatThreadRequest;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;

class ChatThreadController extends Controller
{
    public function __construct(private ChatThreadActions $actions) {}

    public function index(IndexChatThreadRequest $request)
    {
        $this->authorize('viewAny', ChatThread::class);

        return ChatThreadResource::collection($this->actions->index($request->validated()));
    }

    public function store(StoreChatThreadRequest $request)
    {
        $chatThread = $this->actions->create($request->validated());

        return (new ChatThreadResource($chatThread))->response()->setStatusCode(201);
    }

    public function show(ChatThread $chatThread)
    {
        $this->authorize('view', $chatThread);

        return new ChatThreadResource($chatThread);
    }

    public function update(UpdateChatThreadRequest $request, ChatThread $chatThread)
    {
        $chatThread = $this->actions->update($chatThread, $request->validated());

        return new ChatThreadResource($chatThread);
    }

    public function destroy(ChatThread $chatThread)
    {
        $this->authorize('delete', $chatThread);

        $this->actions->delete($chatThread);

        return response()->json(null, 204);
    }
}
