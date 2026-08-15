<?php

namespace Database\Seeders;

use App\Models\LlmSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LlmSettingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     *
     * Singleton by convention (see migration): only one row should ever
     * exist, so this seeder never inserts a second one on re-run.
     */
    public function run(): void
    {
        if (LlmSetting::query()->exists()) {
            return;
        }

        $admin = User::query()->where('is_platform_admin', true)->first()
            ?? User::query()->first();

        LlmSetting::factory()->create([
            'updated_by' => $admin?->id,
        ]);
    }
}
