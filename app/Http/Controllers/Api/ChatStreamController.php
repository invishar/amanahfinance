<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Models\ChatThread;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Short-lived SSE, per CLAUDE.md "Alur AI": no persistent connection, no
// Redis pub/sub (hPanel shared hosting has neither) -- this just polls the
// DB for new assistant messages / pending ai_actions and closes itself well
// under the shared-hosting max_execution_time. The client reconnects with
// ?after=<cursor> from the final `retry` event to keep watching. Consumers
// must dedupe by id: the resume cursor is the earlier of the two streams'
// last-seen timestamps, so a reconnect can re-deliver an event already seen
// on the other stream.
class ChatStreamController extends Controller
{
    public function stream(Request $request, ChatThread $chatThread): StreamedResponse
    {
        $this->authorize('view', $chatThread);

        $start = $request->query('after') ? Carbon::parse($request->query('after')) : now();
        $deadline = now()->addSeconds((int) config('amina.sse.duration_seconds', 20));
        $pollMicroseconds = (int) config('amina.sse.poll_interval_ms', 500) * 1000;

        return response()->stream(function () use ($chatThread, $start, $deadline, $pollMicroseconds) {
            $lastMessageAt = $start;
            $lastActionAt = $start;

            do {
                if (connection_aborted()) {
                    return;
                }

                $lastMessageAt = $this->emitNewMessages($chatThread, $lastMessageAt);
                $lastActionAt = $this->emitNewActionCards($chatThread, $lastActionAt);

                $this->flushBuffer();

                if (now()->gte($deadline)) {
                    break;
                }

                usleep($pollMicroseconds);
            } while (true);

            $this->emit('retry', [
                'after' => $lastMessageAt->lessThan($lastActionAt) ? $lastMessageAt->toIso8601String() : $lastActionAt->toIso8601String(),
            ]);
            $this->flushBuffer();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            // nginx (common in front of hPanel PHP-FPM) buffers proxied
            // responses by default, which defeats SSE entirely without this.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function emitNewMessages(ChatThread $chatThread, Carbon $cursor): Carbon
    {
        $messages = $chatThread->messages()
            ->where('role', 'assistant')
            ->where('created_at', '>', $cursor)
            ->orderBy('created_at')
            ->get(['id', 'content', 'created_at']);

        foreach ($messages as $message) {
            $this->emit('message', [
                'id' => $message->id,
                'content' => $message->content,
                'created_at' => $message->created_at->toIso8601String(),
            ]);
            $cursor = $message->created_at;
        }

        return $cursor;
    }

    private function emitNewActionCards(ChatThread $chatThread, Carbon $cursor): Carbon
    {
        $actions = AiAction::query()
            ->whereHas('message', fn ($query) => $query->where('thread_id', $chatThread->id))
            ->where('status', 'pending')
            ->where('created_at', '>', $cursor)
            ->orderBy('created_at')
            ->get();

        foreach ($actions as $action) {
            $this->emit('action_card', [
                'id' => $action->id,
                'action' => $action->action,
                'payload' => $action->payload,
                'created_at' => $action->created_at->toIso8601String(),
            ]);
            $cursor = $action->created_at;
        }

        return $cursor;
    }

    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
    }

    private function flushBuffer(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
