<?php

namespace Database\Factories;

use App\Models\ChatThread;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'member_id' => FamilyMember::factory(),
            'title' => fake()->optional()->sentence(4),
            'kind' => 'general',
            'last_message_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the thread is the onboarding interview.
     */
    public function onboarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'onboarding',
            'title' => 'Wawancara Awal',
        ]);
    }
}
