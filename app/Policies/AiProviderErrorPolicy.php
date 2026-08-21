<?php

namespace App\Policies;

use App\Models\AiProviderError;
use App\Models\User;

// Platform admin only -- gated by users.is_admin, sama seperti
// UserPolicy/LlmSettingPolicy. Read-only: baris ditulis internal oleh
// AssistantService::logProviderError(), tidak ada store/update/destroy lewat API.
class AiProviderErrorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AiProviderError $aiProviderError): bool
    {
        return $user->isAdmin();
    }
}
