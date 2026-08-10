<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\ChatThread;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatThreadSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            $member = $family->members()->inRandomOrder()->first();

            if (! $member) {
                return;
            }

            ChatThread::factory()->onboarding()->create([
                'family_id' => $family->id,
                'member_id' => $member->id,
            ]);

            ChatThread::factory(2)->create([
                'family_id' => $family->id,
                'member_id' => $member->id,
            ]);
        });
    }
}
