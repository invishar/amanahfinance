<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FamilyInviteSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $inviter = User::inRandomOrder()->first();

        Family::all()->each(function (Family $family) use ($inviter) {
            FamilyInvite::factory(2)->create([
                'family_id' => $family->id,
                'invited_by' => $inviter->id,
            ]);
        });
    }
}
