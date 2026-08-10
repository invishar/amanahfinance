<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists "pgcrypto"');
        DB::statement('create extension if not exists "citext"');
    }

    public function down(): void
    {
        // Ekstensi sengaja tidak di-drop: bisa dipakai objek lain di database yang sama.
    }
};
