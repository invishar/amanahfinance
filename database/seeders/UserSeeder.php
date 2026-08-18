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

        // is_admin sengaja tidak di #[Fillable] (lihat User model) -- di-set
        // langsung lewat property, bukan lewat mass assignment di create().
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            User::factory()->raw(['full_name' => 'Admin Platform', 'email' => 'admin@example.com'])
        );
        $admin->is_admin = true;
        $admin->save();
    }
}
