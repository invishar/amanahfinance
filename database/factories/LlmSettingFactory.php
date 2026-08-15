<?php

namespace Database\Factories;

use App\Models\LlmSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmSetting>
 */
class LlmSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->uuid(),
            'model' => config('services.llm.model', 'claude-sonnet-5'),
            'base_url' => null,
            'updated_by' => User::factory(),
        ];
    }
}
