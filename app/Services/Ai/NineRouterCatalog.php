<?php

namespace App\Services\Ai;

use App\Actions\LlmSettings\LlmSettingActions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class NineRouterCatalog
{
    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages(['base_url' => 'Isi URL HTTP/HTTPS server 9Router tanpa username, password, query, atau fragment.']);
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $path = preg_replace('#/(chat/completions|models)$#', '', $path);
        if (! str_ends_with($path, '/v1')) {
            $path .= '/v1';
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }

    public function fetch(array $input): array
    {
        $baseUrl = self::normalizeUrl($input['base_url']);
        $current = app(LlmSettingActions::class)->current();
        $key = trim($input['key'] ?? '');
        if ($key === '' && filled($current->key)) {
            // A preview request must never forward an existing credential to
            // a different endpoint. Admins may supply a NEW key for that host.
            $sameEndpoint = $current->provider === 'openai_compatible' && filled($current->base_url)
                && self::normalizeUrl($current->base_url) === $baseUrl;
            if (! $sameEndpoint) {
                throw ValidationException::withMessages(['key' => 'Alamat atau layanan berubah. Masukkan API key 9Router untuk koneksi ini.']);
            }
            $key = $current->key;
        }

        try {
            $request = Http::acceptJson()->connectTimeout(5)->timeout(20)->withoutRedirecting();
            if ($key !== '') {
                $request = $request->withToken($key);
            }
            $response = $request->get($baseUrl.'/models');
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['base_url' => '9Router tidak dapat dihubungi dalam 20 detik. Periksa alamat dan koneksi server, lalu coba lagi.']);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw ValidationException::withMessages(['key' => '9Router menolak akses. Periksa API key dan izin koneksinya.']);
        }
        if (! $response->successful()) {
            throw ValidationException::withMessages(['base_url' => 'Daftar model 9Router belum bisa diambil (HTTP '.$response->status().'). Periksa koneksi lalu muat ulang.']);
        }
        $rows = $response->json('data');
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw ValidationException::withMessages(['base_url' => 'Respons bukan katalog model 9Router. Gunakan base URL API, misalnya http://server:20128/v1.']);
        }

        $models = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null) || trim($row['id']) === '' || strlen($row['id']) > 255) {
                continue;
            }
            if (! in_array($row['kind'] ?? $row['type'] ?? 'llm', ['llm', 'chat', 'imageToText', 'model'], true)) {
                continue;
            }
            $id = trim($row['id']);
            $owner = is_string($row['owned_by'] ?? null) ? trim($row['owned_by']) : '';
            $kind = strtolower($owner) === 'combo' ? 'combo' : 'model';
            $tools = $row['capabilities']['tools'] ?? null;
            $models[$id] = [
                'id' => $id,
                'name' => is_string($row['name'] ?? null) ? $row['name'] : $id,
                'provider' => $kind === 'combo' ? 'combo' : ($owner ?: (str_contains($id, '/') ? explode('/', $id, 2)[0] : 'Lainnya')),
                'kind' => $kind,
                'supports_tools' => is_bool($tools) ? $tools : null,
            ];
        }

        return ['base_url' => $baseUrl, 'models' => array_values($models), 'fetched_at' => now()->toIso8601String()];
    }

    public function validateSelection(array $input): array
    {
        $catalog = $this->fetch($input);
        $selected = collect($catalog['models'])->firstWhere('id', $input['model']);
        if (! $selected || $selected['kind'] !== ($input['selection_mode'] ?? 'model')) {
            throw ValidationException::withMessages(['model' => 'Pilihan tidak tersedia pada katalog 9Router ini. Muat ulang daftar lalu pilih combo atau model yang sesuai.']);
        }
        if ($selected['supports_tools'] === false) {
            throw ValidationException::withMessages(['model' => 'Model ini tidak mendukung tool calling yang dibutuhkan Amina untuk membuat formulir. Pilih model lain.']);
        }

        return [...$input, 'base_url' => $catalog['base_url'], 'provider' => 'openai_compatible'];
    }
}
