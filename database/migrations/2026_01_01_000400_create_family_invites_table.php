<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_invites', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->constrained('users');
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('role')->default('member');
            $table->text('token')->unique();               // kode "AMANA-XXXXX"
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('alter table family_invites alter column email type citext');
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invites');
    }
};
