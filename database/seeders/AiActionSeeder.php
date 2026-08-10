<?php

namespace Database\Seeders;

use App\Models\AiAction;
use App\Models\ChatMessage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiActionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ChatMessage::where('role', 'assistant')->inRandomOrder()->limit(20)->get()
            ->each(function (ChatMessage $message) {
                AiAction::factory()->create([
                    'message_id' => $message->id,
                    'family_id' => $message->thread->family_id,
                ]);
            });
    }
}
