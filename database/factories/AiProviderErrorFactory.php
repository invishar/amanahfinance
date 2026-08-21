<?php

namespace Database\Factories;

use App\Models\AiProviderError;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProviderError>
 */
class AiProviderErrorFactory extends Factory
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
            'thread_id' => null,
            'message_id' => null,
            'model' => 'openai/gpt-oss-120b',
            'status' => fake()->randomElement([413, 429, 500]),
            'exception' => 'Illuminate\\Http\\Client\\RequestException',
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
