<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = fake()->randomElement(['budget_warning', 'goal_progress', 'bill_due', 'weekly_digest']);

        return [
            'family_id' => Family::factory(),
            'member_id' => null,
            'kind' => $kind,
            'title' => match ($kind) {
                'budget_warning' => 'Anggaran hampir habis',
                'goal_progress' => 'Progres tabungan bertambah',
                'bill_due' => 'Tagihan akan jatuh tempo',
                default => 'Ringkasan mingguan',
            },
            'body' => fake()->sentence(),
            'deeplink' => null,
            'read_at' => fake()->boolean(40) ? now() : null,
            'created_at' => now(),
        ];
    }
}
