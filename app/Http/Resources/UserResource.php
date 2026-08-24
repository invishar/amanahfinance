<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Always self-view (register/login/me) -- never used to render another
// user's profile -- so it's safe to expose is_admin here, unlike
// UserSummaryResource which is embedded wherever any user can see another.
/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_admin' => $this->isAdmin(),
            // Klien pakai ini buat nunjukin/nyembunyiin menu admin yang cuma
            // berguna kalau API-nya sendiri jalan dengan APP_ENV=local (mis.
            // "Log Prompt", lihat GET /admin/ai-logs) -- server yang
            // menentukan, bukan NEXT_PUBLIC_* di klien, supaya build produksi
            // yang salah deploy tetap tidak menampilkan menunya.
            'is_local' => app()->environment('local'),
            'created_at' => $this->created_at,
        ];
    }
}
