<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->randomElement(['user', 'assistant']);

        return [
            'thread_id' => ChatThread::factory(),
            'role' => $role,
            'content' => fake()->sentence(10),
            'input_mode' => $role === 'user' ? fake()->randomElement(['text', 'voice', 'image']) : null,
            'attachment_url' => null,
            'token_usage' => $role === 'assistant' ? fake()->numberBetween(50, 500) : null,
            'created_at' => now(),
        ];
    }
}
