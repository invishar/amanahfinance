<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            ['code' => 'bulanan', 'name' => 'Paket Bulanan', 'price' => 49_000, 'duration_days' => 30, 'description' => 'Akses penuh AmanaFinance selama 30 hari.'],
            ['code' => 'triwulan', 'name' => 'Paket 3 Bulan', 'price' => 129_000, 'duration_days' => 90, 'description' => 'Akses penuh AmanaFinance selama 90 hari.'],
            ['code' => 'tahunan', 'name' => 'Paket Tahunan', 'price' => 449_000, 'duration_days' => 365, 'description' => 'Akses penuh AmanaFinance selama 365 hari.'],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
