<?php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Makan & Minum', 'Transportasi', 'Belanja Bulanan', 'Hiburan',
            'Tagihan', 'Kesehatan', 'Pendidikan', 'Lain-lain',
        ];

        Family::all()->each(function (Family $family) use ($categories) {
            collect($categories)->random(5)->each(function (string $name) use ($family) {
                Wallet::factory()->create([
                    'family_id' => $family->id,
                    'name' => $name,
                ]);
            });
        });
    }
}
