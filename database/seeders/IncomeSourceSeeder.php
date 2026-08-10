<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\IncomeSource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IncomeSourceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            collect(['Gaji Bulanan', 'Freelance Desain'])->each(function (string $name) use ($family) {
                IncomeSource::factory()->create([
                    'family_id' => $family->id,
                    'name' => $name,
                ]);
            });
        });
    }
}
