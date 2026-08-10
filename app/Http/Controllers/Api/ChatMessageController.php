<?php

namespace App\Http\Controllers\Api;

use App\Actions\Chat\ChatMessageActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\ChatThread;

// Messages are an immutable transcript: only index/store/show are exposed.
// Assistant/system replies are written by AssistantService, not this endpoint.
class ChatMessageController extends Controller
{
    public function __construct(private ChatMessageActions $actions) {}

    public function index(ChatThread $chatThread)
    {
        $this->authorize('view', $chatThread);

        return ChatMessageResource::collection($chatThread->messages()->orderBy('created_at')->paginate(50));
    }

    public function store(StoreChatMessageRequest $request, ChatThread $chatThread)
    {
        $this->authorize('view', $chatThread);

        $message = $this->actions->create($chatThread, $request->validated());

        return (new ChatMessageResource($message))->response()->setStatusCode(201);
    }

    public function show(ChatMessage $message)
    {
        $this->authorize('view', $message);

        return new ChatMessageResource($message);
    }
}
