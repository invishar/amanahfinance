<?php

// Naskah pertanyaan onboarding dan sapaan Amina disimpan di server, bukan
// klien (CLAUDE.md, "Alur AI"). question_key harus persis sama dengan
// onboarding_answers.question_key yang sudah dipakai di seed/aplikasi.
return [

    'persona' => <<<'TEXT'
Kamu adalah Amina, asisten keuangan keluarga dari AmanaFinance. Bicara dalam Bahasa Indonesia sehari-hari yang hangat, ringkas, dan membantu -- bukan formal/kaku. Panggil user dengan nama panggilan mereka kalau tahu.

Aturan penting:
- Kamu TIDAK PERNAH menulis data apa pun secara langsung. Setiap transaksi/wallet/akun/sumber pemasukan/target tabungan baru harus lewat tool yang tersedia, yang membuat draft untuk dikonfirmasi user -- bukan tercatat otomatis.
- Kalau user menyebut nama akun/wallet/sumber pemasukan/target yang kamu tidak yakin cocok dengan data keluarga ini, tetap panggil tool dengan nama yang disebut user apa adanya -- server yang akan mencocokkan. Jangan menebak-nebak ejaan atau mengarang nama baru.
- Untuk pertanyaan soal kondisi keuangan (sisa budget, total pengeluaran bulan ini, dsb), selalu panggil tool get_financial_summary dulu -- jangan pernah mengarang angka dari ingatan atau perkiraan.
- Jangan kirim balasan yang sangat panjang. Satu sampai tiga kalimat biasanya cukup.
TEXT,

    'greeting' => 'Halo! Aku Amina, asisten keuangan keluargamu di AmanaFinance. Cerita aja soal pemasukan, pengeluaran, atau tabungan kamu -- nanti aku bantu catetin. Mau mulai dari mana?',

    'onboarding_questions' => [
        'members' => 'Siapa aja yang bakal ikut ngatur keuangan keluarga ini bareng-bareng?',
        'income' => 'Sumber pemasukan keluarga sekarang apa aja?',
        'expenses' => 'Pengeluaran apa yang paling sering muncul tiap bulan?',
        'goals' => 'Ada target tabungan yang lagi pengen dicapai keluarga?',
    ],

    // Short-lived SSE (CLAUDE.md "Alur AI"): the server closes the stream
    // itself well under shared-hosting max_execution_time; the client
    // reconnects with ?after=<cursor> from the final `retry` event.
    // Overridden in tests to near-zero so the polling loop stays fast.
    'sse' => [
        'duration_seconds' => env('AMINA_SSE_DURATION_SECONDS', 20),
        'poll_interval_ms' => env('AMINA_SSE_POLL_INTERVAL_MS', 500),
    ],

    // Berkas mentah saja (foto struk, rekaman suara) -- OCR/STT belum
    // dikerjakan di sini, itu keputusan produk terpisah.
    'uploads' => [
        'max_kb' => env('AMINA_UPLOAD_MAX_KB', 15360),
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'heic'],
        'voice_mimes' => ['mp3', 'wav', 'm4a', 'ogg', 'webm', 'aac'],
    ],

];
