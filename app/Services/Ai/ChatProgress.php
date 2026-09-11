<?php

namespace App\Services\Ai;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class ChatProgress
{
    public static function report(ChatMessage $message, string $stage): void
    {
        $progress = ['message_id' => $message->id, 'stage' => $stage];
        Cache::put('amina:progress:'.$message->id, $progress, now()->addMinutes(10));
        // Inline workers relay immediately; streams watching cron workers read the cache.
        Event::dispatch('amina.progress', [$message->thread_id, $progress]);
    }
}
