<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatMessageSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ChatThread::all()->each(function (ChatThread $thread) {
            ChatMessage::factory(6)->create([
                'thread_id' => $thread->id,
            ]);
        });
    }
}
