<?php

namespace App\Services\Ai;

// Tool `name` values match ai_actions.action exactly (see ai_actions_action_ck
// in 2026_01_01_001400_create_ai_actions_table.php) except read tools, which
// never create an ai_actions row.
// Every draft tool asks for human-readable *names*, never ids -- Claude
// doesn't know the family's UUIDs; NameResolver resolves them server-side.
class ToolDefinitions
{
    public static function createTransaction(): array
    {
        return [
            'name' => 'create_transaction',
            'description' => 'Siapkan draft transaksi keuangan (pengeluaran, pemasukan, transfer antar akun, atau setor tabungan) dari permintaan user dalam Bahasa Indonesia sehari-hari, mis. "abis jajan 20rb di gopay" atau "gajian 5 juta masuk BCA". Draft ini TIDAK langsung tercatat -- akan muncul sebagai action card untuk dikonfirmasi user. Panggil begitu ada nominal & jenis transaksi yang jelas.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['expense', 'income', 'transfer', 'savings'], 'description' => 'Jenis transaksi.'],
                    'amount' => ['type' => 'integer', 'description' => 'Nominal rupiah penuh (bukan desimal), harus > 0.'],
                    'transaction_date' => ['type' => 'string', 'description' => 'Tanggal format YYYY-MM-DD. Kosongkan untuk pakai hari ini.'],
                    'account_name' => ['type' => 'string', 'description' => 'Nama akun/rekening/e-wallet sumber dana, mis. "gopay", "BCA". Wajib untuk semua jenis.'],
                    'wallet_name' => ['type' => 'string', 'description' => 'Nama wallet/kantong anggaran, mis. "Makan & Minum". Wajib jika type=expense.'],
                    'source_name' => ['type' => 'string', 'description' => 'Nama sumber pemasukan, mis. "Gaji Bulanan". Wajib jika type=income.'],
                    'to_account_name' => ['type' => 'string', 'description' => 'Nama akun tujuan, harus beda dari account_name. Wajib jika type=transfer.'],
                    'goal_name' => ['type' => 'string', 'description' => 'Nama target tabungan tujuan. Wajib jika type=savings.'],
                    'note' => ['type' => 'string', 'description' => 'Catatan singkat opsional.'],
                ],
                'required' => ['type', 'amount', 'account_name'],
            ],
        ];
    }

    public static function createWallet(): array
    {
        return [
            'name' => 'create_wallet',
            'description' => 'Siapkan draft wallet (kantong anggaran/kategori pengeluaran) baru, mis. user bilang "bikin kantong buat hiburan dong". Draft, bukan langsung dibuat.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Nama wallet.'],
                    'icon' => ['type' => 'string', 'description' => 'Nama ikon Lucide, opsional.'],
                    'color' => ['type' => 'string', 'description' => 'Warna hex, opsional.'],
                    'monthly_budget' => ['type' => 'integer', 'description' => 'Budget bulanan rupiah penuh, opsional.'],
                ],
                'required' => ['name'],
            ],
        ];
    }

    public static function createAccount(): array
    {
        return [
            'name' => 'create_account',
            'description' => 'Siapkan draft akun/rekening/e-wallet baru (tempat uang benar-benar berada), mis. "gue baru buka rekening Jago". Draft, bukan langsung dibuat.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Nama akun, mis. "Jago", "GoPay".'],
                    'account_type' => ['type' => 'string', 'enum' => ['bank', 'ewallet', 'cash', 'other']],
                    'institution' => ['type' => 'string', 'description' => 'Nama bank/penyedia, opsional.'],
                    'opening_balance' => ['type' => 'integer', 'description' => 'Saldo awal rupiah penuh, opsional (default 0).'],
                ],
                'required' => ['name', 'account_type'],
            ],
        ];
    }

    public static function createIncomeSource(): array
    {
        return [
            'name' => 'create_income_source',
            'description' => 'Siapkan draft sumber pemasukan baru, mis. "gue mulai freelance desain nih". Draft, bukan langsung dibuat.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Nama sumber pemasukan, mis. "Freelance Desain".'],
                    'expected_amount' => ['type' => 'integer', 'description' => 'Perkiraan nominal rupiah penuh per periode, opsional.'],
                    'cadence' => ['type' => 'string', 'enum' => ['monthly', 'biweekly', 'weekly', 'irregular'], 'description' => 'Opsional.'],
                ],
                'required' => ['name'],
            ],
        ];
    }

    public static function createSavingsGoal(): array
    {
        return [
            'name' => 'create_savings_goal',
            'description' => 'Siapkan draft target tabungan baru, mis. "gue mau nabung buat DP rumah 50 juta". Draft, bukan langsung dibuat.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'target_name' => ['type' => 'string', 'description' => 'Nama target, mis. "DP Rumah".'],
                    'target_amount' => ['type' => 'integer', 'description' => 'Nominal target rupiah penuh, harus > 0.'],
                    'deadline' => ['type' => 'string', 'description' => 'Tenggat format YYYY-MM-DD, opsional.'],
                    'account_name' => ['type' => 'string', 'description' => 'Nama akun penampung dana, opsional.'],
                ],
                'required' => ['target_name', 'target_amount'],
            ],
        ];
    }

    public static function advice(): array
    {
        return [
            'name' => 'advice',
            'description' => 'Sampaikan saran/insight keuangan terstruktur ke user sebagai kartu tersendiri (bukan cuma teks biasa) -- mis. peringatan budget hampir habis, atau saran realokasi. Tidak menulis apa pun ke data.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'description' => 'Isi saran, singkat dan actionable, dalam Bahasa Indonesia.'],
                ],
                'required' => ['message'],
            ],
        ];
    }

    // Hanya didaftarkan selama wawancara awal (lihat AssistantService::
    // buildTools). Satu-satunya tool yang menulis langsung tanpa lewat
    // ai_actions -- pengecualian sadar dari aturan #5 CLAUDE.md, karena
    // onboarding_done adalah penanda status UI, bukan data keuangan: tidak
    // ada rupiah, saldo, atau transaksi yang tersentuh.
    public static function finishOnboarding(): array
    {
        return [
            'name' => 'finish_onboarding',
            'description' => 'Tandai wawancara awal selesai. Panggil SEKALI setelah semua topik pondasi tergali atau user bilang sudah cukup. Tidak menyimpan data keuangan apa pun -- draft yang sudah kamu siapkan tetap menunggu konfirmasi user.',
            'input_schema' => [
                'type' => 'object',
                'properties' => new \stdClass,
                'required' => [],
            ],
        ];
    }

    public static function getFinancialSummary(): array
    {
        return [
            'name' => 'get_financial_summary',
            'description' => 'Baca ringkasan keuangan family bulan berjalan (atau bulan lain): total pemasukan/pengeluaran/tabungan, dan status budget tiap wallet. Satu-satunya tool baca -- panggil ini untuk menjawab pertanyaan user soal kondisi keuangan mereka, jangan mengarang angka.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'month' => ['type' => 'string', 'description' => 'Format YYYY-MM, opsional. Default bulan berjalan.'],
                ],
                'required' => [],
            ],
        ];
    }

    public static function getFamilyFinancialData(): array
    {
        return [
            'name' => 'get_family_financial_data',
            'description' => 'Baca data nyata milik family aktif secara aman dan on-demand. WAJIB panggil sebelum menjawab pertanyaan tentang saldo akun, target tabungan, transaksi tertentu/terbaru, pemasukan atau pengeluaran historis, transaksi rutin, anggota/profil keluarga, atau status langganan. Jangan menebak data yang tidak dikembalikan tool.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'enum' => ['accounts', 'savings_goals', 'recent_transactions', 'recurring_rules', 'subscription', 'family_profile'],
                        'description' => 'Jenis data yang diperlukan untuk menjawab pertanyaan.',
                    ],
                    'month' => ['type' => 'string', 'description' => 'Filter YYYY-MM untuk recent_transactions, opsional.'],
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer', 'savings'], 'description' => 'Filter jenis transaksi, opsional.'],
                    'limit' => ['type' => 'integer', 'description' => 'Jumlah transaksi terbaru, 1-50; default 20.'],
                ],
                'required' => ['topic'],
            ],
        ];
    }
}
