<?php

namespace App\Policies;

use App\Models\AiLog;
use App\Models\User;

// Platform admin only -- gated by users.is_admin, sama seperti
// AiProviderErrorPolicy. Baris ai_logs sendiri cuma pernah ditulis di
// app()->environment('local') (lihat AssistantService::logLocalDebug()), jadi
// di luar local endpoint ini selalu mengembalikan list kosong -- tidak perlu
// digating environment di sini juga.
class AiLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AiLog $aiLog): bool
    {
        return $user->isAdmin();
    }
}
