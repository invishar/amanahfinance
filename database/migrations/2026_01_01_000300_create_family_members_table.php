<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('role');
            $table->text('nickname')->nullable();          // "Ayah", "Bunda", "Kakak"
            $table->bigInteger('monthly_quota')->nullable();
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('removed_at')->nullable();
            $table->unique(['family_id', 'user_id']);
        });

        DB::statement("alter table family_members add constraint family_members_role_ck
            check (role in ('admin','member','viewer'))");
        // MySQL/MariaDB tidak punya partial index (WHERE) seperti Postgres,
        // jadi removed_at diikutkan langsung ke index gabungan.
        DB::statement('create index family_members_active_idx on family_members (family_id, removed_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
