<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\OnboardingAnswer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OnboardingAnswerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            collect(['members', 'income', 'expenses', 'goals'])->each(function (string $key) use ($family) {
                OnboardingAnswer::factory()->create([
                    'family_id' => $family->id,
                    'question_key' => $key,
                ]);
            });
        });
    }
}
