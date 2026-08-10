<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

// Read-only audit trail (aturan "Jangan menghapus baris ai_actions" extends
// in spirit to audit_logs too) -- no create/update/destroy on purpose.
class AuditLogController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', AuditLog::class);

        return AuditLogResource::collection(
            AuditLog::query()->orderByDesc('created_at')->paginate(20)
        );
    }

    public function show(AuditLog $auditLog)
    {
        $this->authorize('view', $auditLog);

        return new AuditLogResource($auditLog);
    }
}
