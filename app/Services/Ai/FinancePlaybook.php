<?php

namespace App\Services\Ai;

/**
 * Gateway baca untuk bahan ajar keuangan rumah tangga (config/amina_playbook.php).
 *
 * Sengaja dibentuk sama seperti FamilyFinancialData: satu method read() dengan
 * topic, dan topic asing dibalas ['error' => ...] alih-alih exception -- hasil
 * tool dikirim balik ke LLM, jadi kegagalan harus jadi kalimat yang bisa
 * dipahami model, bukan job antrian yang mati.
 *
 * Tidak menyentuh database dan tidak tahu apa-apa soal Family: isinya acuan
 * umum, bukan data keluarga. Pembedaan itu juga ditegaskan di persona supaya
 * Amina tidak menyebut angka patokan seolah-olah hitungan keluarga ini.
 */
class FinancePlaybook
{
    /**
     * Sumber tunggal nilai enum `topic` pada tool. Static supaya
     * ToolDefinitions (seluruhnya static) bisa memakainya tanpa container.
     *
     * @return array<int, string>
     */
    public static function topics(): array
    {
        return array_keys((array) config('amina_playbook.modules', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $topic): array
    {
        $module = config('amina_playbook.modules.'.$topic);

        if (! is_array($module)) {
            return ['error' => 'Topik playbook tidak dikenal.'];
        }

        return $module;
    }
}
