<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
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
            // Suffixed with a unique number: `name` is unique per family, and this
            // fixed pool would otherwise collide whenever a test creates more than
            // one wallet per family.
            'name' => fake()->randomElement([
                'Makan & Minum', 'Transportasi', 'Belanja Bulanan', 'Hiburan',
                'Tagihan', 'Kesehatan', 'Pendidikan', 'Lain-lain',
            ]).' '.fake()->unique()->numerify('####'),
            'icon' => 'wallet',
            'color' => fake()->safeHexColor(),
            'monthly_budget' => fake()->numberBetween(200_000, 3_000_000),
            'rollover' => fake()->boolean(30),
            'is_archived' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'created_at' => now(),
        ];
    }
}
