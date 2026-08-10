<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\SavingsGoal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SavingsGoalSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            $account = $family->accounts()->inRandomOrder()->first();

            SavingsGoal::factory(2)->create([
                'family_id' => $family->id,
                'account_id' => $account?->id,
            ]);
        });
    }
}
