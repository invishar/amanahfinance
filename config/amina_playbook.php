<?php

// Bahan ajar keuangan rumah tangga yang dibaca Amina lewat tool
// get_finance_playbook (lihat app/Services/Ai/FinancePlaybook.php).
//
// SENGAJA TIDAK ditempel ke system prompt: pengetahuan ini hanya dibayar
// token-nya saat pertanyaannya memang butuh, sama seperti pola
// get_family_financial_data. Ada test yang menjaga keputusan itu
// (tests/Feature/AssistantServiceTest.php: system prompt tidak boleh memuat
// prosa modul di bawah).
//
// Aturan menulis di file ini:
// - Prinsip, bukan dalil. TIDAK BOLEH ada kutipan ayat/hadis, nomor surat,
//   atau penetapan hukum (halal/haram/sah/wajib) -- Amina bukan otoritas
//   agama (lihat pagar di config/amina.php).
// - Ringkas. Satu modul maksimal 1400 byte setelah json_encode, karena
//   hasilnya masuk balik ke prompt sementara anggaran output cuma 768-1024
//   token (services.llm.max_tokens / AnthropicConversationRunner).
// - Kunci array = nilai enum `topic` pada tool. Menambah kunci di sini
//   otomatis menambah pilihan enum, jadi jangan salah ketik.
//
// Dua aturan terakhir dijaga tests/Feature/FinancePlaybookTest.php.
return [

    'modules' => [

        'dana_darurat' => [
            'judul' => 'Dana darurat keluarga',
            'prinsip' => [
                'Dana darurat adalah bantalan kas untuk kejadian mendesak (sakit, kehilangan pemasukan, perbaikan mendadak) -- bukan tabungan target, bukan investasi.',
                'Simpan di tempat likuid yang terpisah dari rekening belanja harian supaya tidak terpakai diam-diam.',
                'Penuhi dana darurat sebelum menambah keinginan atau cicilan baru.',
            ],
            'angka_patokan' => [
                'target_umum' => '3-6x pengeluaran rutin bulanan. 3x untuk pemasukan tetap; 6x atau lebih untuk pemasukan tidak tetap atau satu pencari nafkah.',
                'target_awal' => 'Kalau masih nol, kejar 1x pengeluaran bulanan dulu, baru naikkan bertahap.',
                'porsi_menabung' => 'Sisihkan 10-20% pemasukan. Kalau berat, mulai dari nominal tetap yang pasti sanggup tiap bulan.',
                'rasio_cicilan' => 'Total cicilan yang sehat maksimal 30% pemasukan bulanan; di atas itu dana darurat sulit tumbuh.',
            ],
            'langkah' => [
                'Hitung pengeluaran rutin sebulan (kebutuhan pokok, tagihan, cicilan), lalu kalikan 3-6.',
                'Buat target tabungan khusus dana darurat dan setor di awal bulan, bukan dari sisa.',
                'Pisahkan ke akun tersendiri yang tidak dipakai belanja harian.',
                'Kalau terpakai, isi ulang sampai penuh sebelum lanjut ke target lain.',
            ],
            'catatan' => 'Untuk kebutuhan mendesak, dahulukan dana sendiri lalu bantuan keluarga tanpa bunga; utang berbunga menambah beban saat kas sedang sempit. Angka di sini patokan umum, bukan hitungan keluarga ini.',
        ],

        'budgeting' => [
            'judul' => 'Menyusun anggaran bulanan',
            'prinsip' => [
                'Anggaran adalah rencana ke mana uang pergi sebelum dibelanjakan, bukan catatan penyesalan akhir bulan.',
                'Bayar diri sendiri dulu: tabungan dan pos wajib disisihkan di awal, bukan sisa.',
                'Dahulukan kebutuhan atas keinginan; jaga gaya hidup tidak ikut naik tiap pemasukan naik.',
            ],
            'angka_patokan' => [
                '50_30_20' => '50% kebutuhan, 30% keinginan, 20% tabungan atau bayar utang. Titik awal, boleh digeser sesuai kondisi.',
                'zero_based' => 'Tiap rupiah diberi tugas sampai sisa rencana nol. Cocok saat pemasukan pas-pasan.',
                'amplop' => 'Satu pos = satu kantong dengan batas sendiri. Kalau habis, tahan belanja atau geser dari pos lain.',
                'sinking_fund' => 'Biaya besar berkala (sekolah, qurban, Ramadan, mudik) dibagi rata per bulan supaya tidak jadi kejutan.',
            ],
            'langkah' => [
                'Dari total pemasukan sebulan, sisihkan lebih dulu tabungan, zakat/sedekah, dan cicilan wajib.',
                'Bagi sisanya ke pos kebutuhan (pangan, tagihan, transport, sekolah) dengan batas jelas.',
                'Beri pos target berjangka (haji/umrah, qurban, Ramadan, pendidikan, dana darurat) dengan setoran bulanan tetap.',
                'Cek mingguan pos mana yang mepet, evaluasi ulang tiap awal bulan.',
            ],
            'catatan' => 'Zakat dan sedekah layak jadi pos tetap sejak awal, bukan sisa. Anggaran realistis yang dijalankan lebih berguna daripada anggaran ideal yang jebol. Angka di sini patokan umum, bukan hitungan keluarga ini.',
        ],

        // Ruang tumbuh yang SUDAH direncanakan tapi BELUM dikerjakan:
        // 'utang' (prioritas pelunasan, bahaya bunga berbunga, alternatif
        // non-riba) dan 'zakat_sedekah' (zakat sebagai pos anggaran rutin,
        // bukan penetapan nisab/kadar). Jangan tambahkan tanpa memperbarui
        // ekspektasi enum di tests/Feature/FinancePlaybookTest.php.
    ],

];
