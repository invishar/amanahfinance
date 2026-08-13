<?php

namespace App\Http\Requests;

use App\Support\CurrentFamily;
use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    // Sama seperti gate ChatMessagePolicy::create() -- berkas ini nantinya
    // dipakai sebagai attachment_url pesan chat, jadi role minimumnya sama:
    // viewer boleh baca chat tapi tidak boleh mengirim.
    public function authorize(): bool
    {
        return in_array(app(CurrentFamily::class)->role(), ['admin', 'member'], true);
    }

    public function rules(): array
    {
        $mimes = [...config('amina.uploads.image_mimes'), ...config('amina.uploads.voice_mimes')];

        return [
            'file' => [
                'required', 'file',
                'max:'.config('amina.uploads.max_kb'),
                'mimes:'.implode(',', $mimes),
            ],
        ];
    }
}
