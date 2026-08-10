<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FamilyMemberSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        Family::all()->each(function (Family $family) use ($users) {
            $users->random(min(3, $users->count()))
                ->values()
                ->each(function (User $user, int $index) use ($family) {
                    FamilyMember::factory()->create([
                        'family_id' => $family->id,
                        'user_id' => $user->id,
                        'role' => $index === 0 ? 'admin' : 'member',
                    ]);
                });
        });
    }
}
