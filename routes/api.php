<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminAiErrorController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AiActionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\ChatStreamController;
use App\Http\Controllers\Api\ChatThreadController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\FamilyInviteController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\IncomeSourceController;
use App\Http\Controllers\Api\LlmSettingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingAnswerController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\RecurringRuleController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WalletBudgetController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Public API documentation -- no auth, mirrors API-v1.md.
    Route::get('/openapi.json', [OpenApiController::class, 'index']);

    // Public: no token yet, so nothing here can be behind auth:sanctum.
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Also public: browsing the plan catalog should not require an account
    // yet (mis. landing page sebelum user register/login). No policy gate on
    // these two -- see SubscriptionPlanController.
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Family is the tenant root itself, so it lives outside resolve.family --
        // a user may have zero families (first login) or be choosing among several.
        Route::apiResource('families', FamilyController::class);

        // Also outside resolve.family: the user accepting has no membership in
        // the target family yet, so there is nothing for ResolveFamily to resolve.
        Route::post('/family-invites/accept', [FamilyInviteController::class, 'accept']);

        // Platform-wide, not family-scoped -- gated by users.is_admin
        // (LlmSettingPolicy), never by any family's role.
        Route::get('/llm-settings', [LlmSettingController::class, 'show']);
        Route::put('/llm-settings', [LlmSettingController::class, 'update']);

        // Direktori user platform, lintas-family -- gated is_admin (UserPolicy).
        // Read-only: tidak ada promote/demote is_admin lewat API (tinker-only).
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);

        // Monitoring kegagalan provider LLM lintas-family (lihat
        // AssistantService::logProviderError()) -- gated is_admin
        // (AiProviderErrorPolicy). Read-only, ditulis internal.
        Route::get('/admin/ai-errors', [AdminAiErrorController::class, 'index']);

        // Katalog paket langganan: mutasi gated is_admin
        // (SubscriptionPlanPolicy). GET index/show ada di blok publik di atas.
        Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
        Route::put('/subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'update']);
        Route::delete('/subscription-plans/{subscription_plan}', [SubscriptionPlanController::class, 'destroy']);

        // Review lintas-family oleh platform admin -- SENGAJA di luar
        // resolve.family: admin memutuskan permintaan family manapun, bukan
        // cuma family miliknya sendiri (SubscriptionPolicy::reviewAny/activate/reject).
        Route::get('/admin/subscriptions', [SubscriptionController::class, 'adminIndex']);
        Route::post('/admin/subscriptions/{subscription}/activate', [SubscriptionController::class, 'activate']);
        Route::post('/admin/subscriptions/{subscription}/reject', [SubscriptionController::class, 'reject']);

        // Everything below acts on the family resolved by ResolveFamily from the
        // authenticated user's own memberships (+ optional X-Family-Id header).
        Route::middleware('resolve.family')->group(function () {
            Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);

            Route::apiResource('family-members', FamilyMemberController::class);
            Route::apiResource('family-invites', FamilyInviteController::class);
            Route::apiResource('accounts', AccountController::class);
            Route::apiResource('wallets', WalletController::class);
            Route::apiResource('wallets.budgets', WalletBudgetController::class)->shallow();
            Route::apiResource('income-sources', IncomeSourceController::class);
            Route::apiResource('savings-goals', SavingsGoalController::class);
            // Family sendiri: pilih paket + konfirmasi pembayaran (store),
            // lihat riwayat (index/show). activate/reject ada di blok admin
            // lintas-family di atas, bukan di sini.
            Route::apiResource('subscriptions', SubscriptionController::class)->only(['index', 'show', 'store']);
            Route::apiResource('transactions', TransactionController::class);
            Route::apiResource('recurring-rules', RecurringRuleController::class);
            Route::apiResource('chat-threads', ChatThreadController::class);
            Route::get('/chat-threads/{chat_thread}/stream', [ChatStreamController::class, 'stream']);
            Route::apiResource('chat-threads.messages', ChatMessageController::class)
                ->shallow()
                ->only(['index', 'store', 'show']);
            Route::apiResource('onboarding-answers', OnboardingAnswerController::class);
            Route::apiResource('notifications', NotificationController::class);
            Route::post('/uploads', [UploadController::class, 'store']);

            // No store/update/destroy: ai_actions rows are only ever created
            // by AssistantService and only ever mutated via confirm/reject
            // below (aturan #5 & jejak audit -- baris tidak pernah dihapus).
            Route::apiResource('ai-actions', AiActionController::class)->only(['index', 'show']);
            Route::post('/ai-actions/{ai_action}/confirm', [AiActionController::class, 'confirm']);
            Route::post('/ai-actions/{ai_action}/reject', [AiActionController::class, 'reject']);
            Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
        });
    });
});
