<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(30)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw(['full_name' => 'Test User', 'email' => 'test@example.com'])
        );
    }
}
