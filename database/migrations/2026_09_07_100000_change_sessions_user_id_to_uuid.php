<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions.user_id` dibuat dengan `foreignId()` (bigint unsigned) dari stub
 * bawaan Laravel, padahal `users.id` di aplikasi ini UUID (aturan #2
 * CLAUDE.md). Akibatnya setiap sesi yang berisi user login gagal ditulis:
 * MySQL menolak UUID di kolom bigint, dan DatabaseSessionHandler menelan
 * QueryException-nya (performInsert menangkap lalu mencoba update yang tidak
 * kena baris apa pun). Hasilnya login berhasil tapi sesinya hilang, dan
 * request berikutnya kembali jadi tamu — gagal tanpa jejak error sama sekali.
 *
 * Baru terasa sekarang karena sampai sebelum ini tidak ada sesi berisi user:
 * seluruh auth lewat Bearer token, dan sesi anonim (user_id NULL) tersimpan
 * dengan baik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris sesi yang user_id-nya UUID tidak muat di bigint; buang dulu
        // supaya rollback tidak gagal di tengah jalan.
        DB::table('sessions')->whereNotNull('user_id')->delete();

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }
};
