<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->text('email')->nullable()->unique();   // diubah ke citext di bawah
            $table->text('phone')->nullable()->unique();
            $table->text('full_name');
            $table->text('avatar_url')->nullable();
            $table->text('password_hash')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->rememberToken();
        });

        DB::statement('alter table users alter column email type citext');
        DB::statement('alter table users add constraint users_contact_ck
            check (email is not null or phone is not null)');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->text('email')->primary();
            $table->text('token');
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
