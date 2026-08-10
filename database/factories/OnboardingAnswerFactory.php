<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\OnboardingAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingAnswer>
 */
class OnboardingAnswerFactory extends Factory
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
            'question_key' => fake()->randomElement(['members', 'income', 'expenses', 'goals']),
            'answer' => ['note' => fake()->sentence()],
            'skipped' => false,
            'answered_at' => now(),
        ];
    }

    /**
     * Indicate that the question was skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer' => null,
            'skipped' => true,
        ]);
    }
}
