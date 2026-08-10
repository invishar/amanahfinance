<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Notification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            $members = $family->members;

            Notification::factory(4)->create([
                'family_id' => $family->id,
                'member_id' => fn () => $members->isNotEmpty() ? $members->random()->id : null,
            ]);
        });
    }
}
