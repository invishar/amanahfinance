<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AiActionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\ChatThreadController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\FamilyInviteController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\IncomeSourceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingAnswerController;
use App\Http\Controllers\Api\RecurringRuleController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletBudgetController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // Public: no token yet, so nothing here can be behind auth:sanctum.
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Family is the tenant root itself, so it lives outside resolve.family --
        // a user may have zero families (first login) or be choosing among several.
        Route::apiResource('families', FamilyController::class);

        // Everything below acts on the family resolved by ResolveFamily from the
        // authenticated user's own memberships (+ optional X-Family-Id header).
        Route::middleware('resolve.family')->group(function () {
            Route::apiResource('family-members', FamilyMemberController::class);
            Route::apiResource('family-invites', FamilyInviteController::class);
            Route::apiResource('accounts', AccountController::class);
            Route::apiResource('wallets', WalletController::class);
            Route::apiResource('wallets.budgets', WalletBudgetController::class)->shallow();
            Route::apiResource('income-sources', IncomeSourceController::class);
            Route::apiResource('savings-goals', SavingsGoalController::class);
            Route::apiResource('transactions', TransactionController::class);
            Route::apiResource('recurring-rules', RecurringRuleController::class);
            Route::apiResource('chat-threads', ChatThreadController::class);
            Route::apiResource('chat-threads.messages', ChatMessageController::class)
                ->shallow()
                ->only(['index', 'store', 'show']);
            Route::apiResource('onboarding-answers', OnboardingAnswerController::class);
            Route::apiResource('notifications', NotificationController::class);

            // Read-only (aturan #5 & jejak audit): no store/update/destroy routes.
            Route::apiResource('ai-actions', AiActionController::class)->only(['index', 'show']);
            Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
        });
    });
});
