<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Family;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['bank', 'ewallet', 'cash', 'other']);
        $opening = fake()->numberBetween(0, 20_000_000);

        return [
            'family_id' => Family::factory(),
            'name' => match ($type) {
                'bank' => fake()->randomElement(['BCA', 'Mandiri', 'BNI', 'BRI']),
                'ewallet' => fake()->randomElement(['GoPay', 'OVO', 'Dana', 'ShopeePay']),
                'cash' => 'Tunai',
                default => fake()->words(2, true),
            },
            'account_type' => $type,
            'institution' => $type === 'bank' ? fake()->company() : null,
            'masked_number' => $type === 'bank' ? '****'.fake()->numerify('####') : null,
            'opening_balance' => $opening,
            'current_balance' => $opening,
            'owner_member_id' => null,
            'is_shared' => fake()->boolean(70),
            'is_archived' => false,
            'sort_order' => fake()->numberBetween(0, 10),
            'created_at' => now(),
        ];
    }
}
