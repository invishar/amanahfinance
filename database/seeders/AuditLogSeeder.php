<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Family;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            $actor = $family->members()->inRandomOrder()->first();

            AuditLog::factory(5)->create([
                'family_id' => $family->id,
                'actor_id' => $actor?->id,
            ]);
        });
    }
}
