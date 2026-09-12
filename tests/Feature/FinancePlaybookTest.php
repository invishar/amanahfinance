<?php

use App\Services\Ai\FinancePlaybook;
use App\Services\Ai\ToolDefinitions;

// Gateway bahan ajar diuji di filenya sendiri (sejajar FamilyFinancialDataTest):
// tanpa LLM, tanpa DB. Integrasi tool-nya diuji di AssistantServiceTest.

test('modul playbook memuat prinsip, angka patokan, langkah, dan catatan', function () {
    $reader = app(FinancePlaybook::class);

    $darurat = $reader->read('dana_darurat');
    expect($darurat)
        ->toHaveKeys(['judul', 'prinsip', 'angka_patokan', 'langkah', 'catatan'])
        ->and($darurat['prinsip'])->not->toBeEmpty()
        ->and($darurat['langkah'])->not->toBeEmpty()
        ->and(json_encode($darurat, JSON_UNESCAPED_UNICODE))->toContain('3-6');

    $budgeting = $reader->read('budgeting');
    expect($budgeting)
        ->toHaveKeys(['judul', 'prinsip', 'angka_patokan', 'langkah', 'catatan'])
        ->and(json_encode($budgeting, JSON_UNESCAPED_UNICODE))
        ->toContain('50%')
        ->toContain('sinking');
});

test('topic yang tidak dikenal dibalas error, bukan exception', function () {
    $reader = app(FinancePlaybook::class);
    $error = ['error' => 'Topik playbook tidak dikenal.'];

    // Hasil tool dikirim balik ke LLM, jadi kegagalan harus jadi kalimat yang
    // bisa dipahami model -- bukan exception yang mematikan job antrian.
    expect($reader->read('kripto'))->toBe($error)
        ->and($reader->read(''))->toBe($error)
        // 'modules' akan menyentuh config('amina_playbook.modules.modules');
        // pastikan tidak membocorkan seluruh daftar modul.
        ->and($reader->read('modules'))->toBe($error);
});

test('enum tool sama persis dengan modul yang tersedia', function () {
    $topics = FinancePlaybook::topics();

    // Pagar dua arah: modul baru yang ditambahkan diam-diam langsung
    // menggagalkan test, jadi kontrak enum tidak pernah berubah tanpa
    // keputusan sadar.
    expect($topics)->toBe(['dana_darurat', 'budgeting'])
        ->and(ToolDefinitions::getFinancePlaybook()['input_schema']['properties']['topic']['enum'])
        ->toBe($topics);
});

test('tiap modul tetap ringkas supaya hemat token', function () {
    $reader = app(FinancePlaybook::class);

    // Hasil tool masuk balik ke prompt, sementara anggaran output cuma
    // 768-1024 token (services.llm.max_tokens / AnthropicConversationRunner).
    foreach (FinancePlaybook::topics() as $topic) {
        expect(strlen(json_encode($reader->read($topic), JSON_UNESCAPED_UNICODE)))
            ->toBeLessThanOrEqual(1400, "Modul {$topic} kepanjangan");
    }
});

test('playbook tidak memuat dalil atau penetapan hukum', function () {
    $reader = app(FinancePlaybook::class);
    $json = strtolower(json_encode(
        collect(FinancePlaybook::topics())->mapWithKeys(fn (string $t) => [$t => $reader->read($t)]),
        JSON_UNESCAPED_UNICODE,
    ));

    // Keputusan produk "prinsip saja, tanpa kutipan, bukan otoritas agama"
    // dijadikan pagar yang dijalankan CI, bukan sekadar komentar.
    expect($json)
        ->not->toContain('qs.')
        ->not->toContain('hr.')
        ->not->toContain('hadis')
        ->not->toContain('ayat')
        ->not->toContain('haram')
        ->not->toContain('halal')
        ->not->toContain('fatwa');
});
