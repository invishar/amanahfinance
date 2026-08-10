<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Family;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Family::all()->each(function (Family $family) {
            collect(['Tunai', 'BCA', 'GoPay'])->each(function (string $name) use ($family) {
                Account::factory()->create([
                    'family_id' => $family->id,
                    'name' => $name,
                    'account_type' => match ($name) {
                        'Tunai' => 'cash',
                        'GoPay' => 'ewallet',
                        default => 'bank',
                    },
                ]);
            });
        });
    }
}
