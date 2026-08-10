<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * MySQL/MariaDB tidak punya row level security seperti Postgres
     * (tidak ada `enable row level security` / `create policy`), jadi migrasi
     * ini sengaja dikosongkan untuk koneksi MySQL/MariaDB.
     *
     * Konsekuensinya: jaring pengaman kedua di level database ini TIDAK ada.
     * Isolasi antar family jadi 100% bergantung pada global scope Eloquent +
     * middleware ResolveFamily di level aplikasi -- pastikan keduanya benar
     * dipasang di setiap model & query sebelum fitur multi-family dipakai.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
