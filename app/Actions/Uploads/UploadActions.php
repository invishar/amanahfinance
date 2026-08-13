<?php

namespace App\Actions\Uploads;

use App\Support\CurrentFamily;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Menyimpan berkas mentah saja (foto struk, rekaman suara) ke disk public
// dan mengembalikan URL-nya -- OCR/speech-to-text belum dikerjakan di sini,
// itu keputusan produk terpisah (lihat PLAN-INTEGRASI-FRONTEND.md §2.3).
class UploadActions
{
    /**
     * @return array{url: string, mime: string, size: int}
     */
    public function store(UploadedFile $file): array
    {
        $familyId = app(CurrentFamily::class)->id();
        $filename = Str::uuid()->toString().'.'.$file->extension();
        $path = $file->storeAs("uploads/{$familyId}", $filename, 'public');

        return [
            'url' => Storage::disk('public')->url($path),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }
}
