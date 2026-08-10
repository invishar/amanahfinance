<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignUuid('member_id')->nullable()
                  ->constrained('family_members')->cascadeOnDelete();
            $table->text('kind');
            $table->text('title');
            $table->text('body')->nullable();
            $table->text('deeplink')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['family_id', 'read_at']);
        });

        DB::statement("alter table notifications add constraint notifications_kind_ck
            check (kind in ('budget_warning','goal_progress','bill_due','weekly_digest'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
