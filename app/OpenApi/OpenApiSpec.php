<?php

namespace App\OpenApi;

// Hand-assembled OpenAPI 3.0 document mirroring API-v1.md exactly (the
// human-readable contract is the source of truth; this is its machine-
// readable twin, kept in the same PR whenever an endpoint changes). Built
// as a PHP array rather than annotations scattered across controllers so
// the whole contract stays in one reviewable place.
class OpenApiSpec
{
    public static function generate(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'AmanaFinance API',
                'version' => 'v1',
                'description' => 'Backend AmanaFinance: keluarga, akun, wallet, transaksi, dan asisten AI Amina. '
                    .'Uang selalu integer rupiah penuh (bigint), id selalu UUID, timestamp selalu ISO-8601 UTC.',
            ],
            'servers' => [
                ['url' => '/api/v1'],
            ],
            'security' => [['bearerAuth' => []]],
            'tags' => self::tags(),
            'paths' => self::paths(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum personal access token',
                    ],
                ],
                'parameters' => [
                    'XFamilyId' => [
                        'name' => 'X-Family-Id',
                        'in' => 'header',
                        'required' => false,
                        'description' => 'Pilih family aktif di antara membership user. Default: membership pertama user.',
                        'schema' => ['type' => 'string', 'format' => 'uuid'],
                    ],
                ],
                'schemas' => self::schemas(),
                'responses' => self::commonResponses(),
            ],
        ];
    }

    private static function tags(): array
    {
        return array_map(
            fn ($name) => ['name' => $name],
            [
                'Auth', 'Families', 'Family Members', 'Family Invites', 'Accounts', 'Wallets',
                'Wallet Budgets', 'Income Sources', 'Savings Goals', 'Transactions', 'Recurring Rules',
                'Chat Threads', 'Chat Messages', 'Uploads', 'Onboarding Answers', 'Notifications',
                'AI Actions', 'Audit Logs', 'Analytics', 'LLM Settings', 'Subscription Plans', 'Subscriptions',
                'Users',
            ]
        );
    }

    // ------------------------------------------------------------------
    // Shared envelope / error schemas
    // ------------------------------------------------------------------

    private static function envelope(string $ref): array
    {
        return [
            'type' => 'object',
            'properties' => ['data' => ['$ref' => "#/components/schemas/{$ref}"]],
        ];
    }

    private static function paginatedEnvelope(string $ref): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$ref}"]],
                'links' => ['$ref' => '#/components/schemas/PaginationLinks'],
                'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
            ],
        ];
    }

    private static function jsonResponse(string $description, array $schema): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => $schema]],
        ];
    }

    private static function commonResponses(): array
    {
        return [
            'Unauthorized' => self::jsonResponse('Token tidak ada/tidak valid.', ['$ref' => '#/components/schemas/MessageError']),
            'Forbidden' => self::jsonResponse('Policy menolak, atau X-Family-Id invalid.', ['$ref' => '#/components/schemas/MessageError']),
            'NotFound' => self::jsonResponse('Tidak ditemukan atau milik family lain (disembunyikan oleh global scope).', ['$ref' => '#/components/schemas/MessageError']),
            'Conflict' => self::jsonResponse('Diblokir oleh FK restrictOnDelete (masih direferensikan resource lain).', ['$ref' => '#/components/schemas/MessageError']),
            'ValidationError' => self::jsonResponse('Validasi gagal.', ['$ref' => '#/components/schemas/ValidationError']),
        ];
    }

    private static function refResponse(string $name): array
    {
        return ['$ref' => "#/components/responses/{$name}"];
    }

    // ------------------------------------------------------------------
    // Component schemas
    // ------------------------------------------------------------------

    private static function schemas(): array
    {
        return [
            'MessageError' => [
                'type' => 'object',
                'properties' => ['message' => ['type' => 'string']],
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
            'PaginationLinks' => [
                'type' => 'object',
                'properties' => [
                    'first' => ['type' => 'string', 'nullable' => true],
                    'last' => ['type' => 'string', 'nullable' => true],
                    'prev' => ['type' => 'string', 'nullable' => true],
                    'next' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer', 'nullable' => true],
                    'last_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'to' => ['type' => 'integer', 'nullable' => true],
                    'total' => ['type' => 'integer'],
                ],
            ],

            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'full_name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'avatar_url' => ['type' => 'string', 'nullable' => true],
                    'is_admin' => ['type' => 'boolean', 'description' => 'Selalu self-view (register/login/me) -- tidak pernah dipakai untuk profil user lain.'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AuthPayload' => [
                'type' => 'object',
                'properties' => [
                    'user' => ['$ref' => '#/components/schemas/User'],
                    'token' => ['type' => 'string'],
                ],
            ],

            'AdminUser' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'full_name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'avatar_url' => ['type' => 'string', 'nullable' => true],
                    'is_admin' => ['type' => 'boolean'],
                    'families_count' => ['type' => 'integer'],
                    'last_login_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AdminUserDetail' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'full_name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'avatar_url' => ['type' => 'string', 'nullable' => true],
                    'is_admin' => ['type' => 'boolean'],
                    'last_login_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'families' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'family_id' => ['type' => 'string', 'format' => 'uuid'],
                                'family_name' => ['type' => 'string'],
                                'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                                'joined_at' => ['type' => 'string', 'format' => 'date-time'],
                            ],
                        ],
                    ],
                ],
            ],

            'Family' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3, 'example' => 'IDR'],
                    'timezone' => ['type' => 'string', 'example' => 'Asia/Jakarta'],
                    'onboarding_done' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'FamilyMemberUser' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'full_name' => ['type' => 'string'],
                    'avatar_url' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'FamilyMember' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'user_id' => ['type' => 'string', 'format' => 'uuid'],
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                    'nickname' => ['type' => 'string', 'nullable' => true],
                    'monthly_quota' => ['type' => 'integer', 'nullable' => true],
                    'joined_at' => ['type' => 'string', 'format' => 'date-time'],
                    'removed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'user' => ['$ref' => '#/components/schemas/FamilyMemberUser'],
                ],
            ],
            'FamilyInvite' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'invited_by' => ['type' => 'string', 'format' => 'uuid'],
                    'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                    'token' => ['type' => 'string', 'example' => 'AMANA-AB12CD'],
                    'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                    'accepted_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Account' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'account_type' => ['type' => 'string', 'enum' => ['bank', 'ewallet', 'cash', 'other']],
                    'institution' => ['type' => 'string', 'nullable' => true],
                    'masked_number' => ['type' => 'string', 'nullable' => true],
                    'opening_balance' => ['type' => 'integer'],
                    'current_balance' => ['type' => 'integer'],
                    'owner_member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'is_shared' => ['type' => 'boolean'],
                    'is_archived' => ['type' => 'boolean'],
                    'sort_order' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Wallet' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'icon' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'monthly_budget' => ['type' => 'integer'],
                    'rollover' => ['type' => 'boolean'],
                    'is_archived' => ['type' => 'boolean'],
                    'sort_order' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'WalletBudget' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid'],
                    'period' => ['type' => 'string', 'format' => 'date'],
                    'amount' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'IncomeSource' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'owner_member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'expected_amount' => ['type' => 'integer', 'nullable' => true],
                    'cadence' => ['type' => 'string', 'enum' => ['monthly', 'biweekly', 'weekly', 'irregular'], 'nullable' => true],
                    'is_archived' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'SavingsGoal' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'target_name' => ['type' => 'string'],
                    'target_amount' => ['type' => 'integer'],
                    'current_amount' => ['type' => 'integer'],
                    'percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'deadline' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'eta' => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'description' => 'Estimasi tercapai (awal bulan), diproyeksikan server dari rata-rata kontribusi. null kalau status bukan active, sudah tercapai, atau belum ada histori setoran.'],
                    'icon' => ['type' => 'string', 'nullable' => true],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => ['active', 'achieved', 'paused', 'cancelled']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'achieved_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'Transaction' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer', 'savings']],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'transaction_date' => ['type' => 'string', 'format' => 'date'],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'to_account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'source_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'goal_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'created_by' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'origin' => ['type' => 'string', 'enum' => ['manual', 'chat_text', 'chat_voice', 'receipt_ocr', 'import']],
                    'receipt_url' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'RecurringRule' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'savings']],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'source_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'rrule' => ['type' => 'string', 'example' => 'FREQ=MONTHLY;BYMONTHDAY=1'],
                    'next_run_on' => ['type' => 'string', 'format' => 'date'],
                    'is_active' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'ChatThread' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'member_id' => ['type' => 'string', 'format' => 'uuid'],
                    'title' => ['type' => 'string', 'nullable' => true],
                    'kind' => ['type' => 'string', 'enum' => ['general', 'onboarding']],
                    'last_message_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'onboarding' => [
                        'type' => 'object', 'nullable' => true,
                        'description' => 'null kecuali kind=onboarding.',
                        'properties' => [
                            'step' => ['type' => 'integer', 'description' => 'Nomor pertanyaan yang sedang berjalan (1-based).'],
                            'total' => ['type' => 'integer'],
                            'done' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
            'ChatMessage' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'thread_id' => ['type' => 'string', 'format' => 'uuid'],
                    'role' => ['type' => 'string', 'enum' => ['user', 'assistant', 'system']],
                    'content' => ['type' => 'string', 'nullable' => true],
                    'input_mode' => ['type' => 'string', 'enum' => ['text', 'voice', 'image'], 'nullable' => true],
                    'attachment_url' => ['type' => 'string', 'nullable' => true],
                    'token_usage' => ['type' => 'integer', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'OnboardingAnswer' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'question_key' => ['type' => 'string', 'example' => 'goals'],
                    'answer' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'skipped' => ['type' => 'boolean'],
                    'answered_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Notification' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'kind' => ['type' => 'string', 'enum' => ['budget_warning', 'goal_progress', 'bill_due', 'weekly_digest']],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string', 'nullable' => true],
                    'deeplink' => ['type' => 'string', 'nullable' => true],
                    'read_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AiAction' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'message_id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'action' => ['type' => 'string', 'enum' => [
                        'create_transaction', 'create_wallet', 'create_account',
                        'create_income_source', 'create_savings_goal', 'advice',
                    ]],
                    'payload' => ['type' => 'object', 'additionalProperties' => true],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'edited', 'rejected', 'expired']],
                    'result_table' => ['type' => 'string', 'nullable' => true],
                    'result_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'confidence' => ['type' => 'number', 'format' => 'float', 'nullable' => true],
                    'resolved_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'resolved_by' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AuditLog' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'actor_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'entity' => ['type' => 'string'],
                    'entity_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'action' => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'restore']],
                    'diff' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AnalyticsWallet' => [
                'type' => 'object',
                'properties' => [
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'icon' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'budget' => ['type' => 'integer'],
                    'spent' => ['type' => 'integer'],
                    'remaining' => ['type' => 'integer'],
                    'percent' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ['no_budget', 'over', 'warning', 'ok']],
                ],
            ],
            'AnalyticsIncomeSource' => [
                'type' => 'object',
                'properties' => [
                    'source_id' => ['type' => 'string', 'format' => 'uuid'],
                    'name' => ['type' => 'string'],
                    'expected' => ['type' => 'integer', 'nullable' => true, 'description' => 'income_sources.expected_amount, bisa null kalau belum diisi.'],
                    'actual' => ['type' => 'integer', 'description' => 'Total transactions type=income untuk source ini di period ini.'],
                ],
            ],
            'AnalyticsSummary' => [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'format' => 'date'],
                    'cashflow' => [
                        'type' => 'object',
                        'properties' => [
                            'total_income' => ['type' => 'integer'],
                            'total_expense' => ['type' => 'integer'],
                            'total_savings' => ['type' => 'integer'],
                            'net' => ['type' => 'integer'],
                        ],
                    ],
                    'wallets' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AnalyticsWallet']],
                    'income_sources' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AnalyticsIncomeSource']],
                ],
            ],
            'LlmSetting' => [
                'type' => 'object',
                'description' => 'Kredensial LLM platform-wide. key tidak pernah dikembalikan lewat API.',
                'properties' => [
                    'model' => ['type' => 'string', 'example' => 'claude-sonnet-5'],
                    'base_url' => ['type' => 'string', 'nullable' => true],
                    'has_key' => ['type' => 'boolean'],
                    'key_preview' => ['type' => 'string', 'nullable' => true, 'example' => '...alue'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'updated_by' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                ],
            ],
            'SubscriptionPlan' => [
                'type' => 'object',
                'description' => 'Katalog paket langganan platform-wide, bukan per-family.',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'code' => ['type' => 'string', 'example' => 'bulanan'],
                    'name' => ['type' => 'string'],
                    'price' => ['type' => 'integer', 'minimum' => 1],
                    'duration_days' => ['type' => 'integer', 'minimum' => 1],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'is_active' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Subscription' => [
                'type' => 'object',
                'description' => 'Alur: pending_payment (family pilih paket + konfirmasi bayar) -> active/rejected (platform admin review) -> expired (job harian amana:expire-subscriptions).',
                'properties' => [
                    'id' => ['type' => 'string', 'format' => 'uuid'],
                    'family_id' => ['type' => 'string', 'format' => 'uuid'],
                    'plan_id' => ['type' => 'string', 'format' => 'uuid'],
                    'plan_code' => ['type' => 'string'],
                    'plan_name' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['pending_payment', 'active', 'rejected', 'expired']],
                    'amount' => ['type' => 'integer', 'description' => 'Snapshot subscription_plans.price saat request dibuat.'],
                    'payment_note' => ['type' => 'string', 'nullable' => true],
                    'payment_proof_url' => ['type' => 'string', 'nullable' => true, 'description' => 'URL hasil POST /uploads (foto bukti transfer).'],
                    'paid_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'starts_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'ends_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'requested_by' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'reviewed_by' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'reviewed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'review_note' => ['type' => 'string', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Paths
    // ------------------------------------------------------------------

    private static function paths(): array
    {
        return array_merge(
            self::authPaths(),
            self::familyPaths(),
            self::standardCrudPaths(),
            self::walletBudgetPaths(),
            self::chatMessagePaths(),
            self::chatStreamPaths(),
            self::uploadPaths(),
            self::readOnlyPaths(),
            self::aiActionMutationPaths(),
            self::analyticsPaths(),
            self::llmSettingPaths(),
            self::subscriptionPlanPaths(),
            self::subscriptionPaths(),
            self::adminUserPaths(),
        );
    }

    private static function adminUserPaths(): array
    {
        return [
            '/admin/users' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'Direktori user platform (is_admin)',
                    'description' => 'Lintas-family, bukan resource per-family. Read-only: tidak ada endpoint untuk promote/demote is_admin (tinker-only, lihat User model).',
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'description' => 'Cocok sebagian pada full_name, email, atau phone.', 'schema' => ['type' => 'string']],
                        ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                    ],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('AdminUser')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
            ],
            '/admin/users/{user}' => [
                'parameters' => [['name' => 'user', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'Detail user + family memberships (is_admin)',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('AdminUserDetail')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
            ],
        ];
    }

    private static function subscriptionPlanPaths(): array
    {
        $storeBody = ['type' => 'object', 'required' => ['code', 'name', 'price', 'duration_days'], 'properties' => [
            'code' => ['type' => 'string', 'description' => 'Unik, referensi stabil (mis. "bulanan").'],
            'name' => ['type' => 'string'],
            'price' => ['type' => 'integer', 'minimum' => 1],
            'duration_days' => ['type' => 'integer', 'minimum' => 1],
            'description' => ['type' => 'string', 'nullable' => true],
            'is_active' => ['type' => 'boolean', 'default' => true],
        ]];

        return [
            '/subscription-plans' => [
                'get' => [
                    'tags' => ['Subscription Plans'],
                    'summary' => 'List paket langganan',
                    'description' => 'Sepenuhnya publik -- tidak perlu login sama sekali (mirip /auth/register), supaya katalog bisa ditampilkan sebelum user punya akun.',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'is_active', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => [
                        '200' => self::jsonResponse('OK', [
                            'type' => 'object',
                            'properties' => ['data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/SubscriptionPlan']]],
                        ]),
                    ],
                ],
                'post' => [
                    'tags' => ['Subscription Plans'],
                    'summary' => 'Tambah paket (is_admin)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $storeBody]]],
                    'responses' => [
                        '201' => self::jsonResponse('Tersimpan.', self::envelope('SubscriptionPlan')),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/subscription-plans/{subscription_plan}' => [
                'parameters' => [['name' => 'subscription_plan', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Subscription Plans'],
                    'summary' => 'Detail paket',
                    'description' => 'Sepenuhnya publik -- tidak perlu login sama sekali.',
                    'security' => [],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('SubscriptionPlan')),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
                'put' => [
                    'tags' => ['Subscription Plans'],
                    'summary' => 'Update paket (is_admin)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        ...$storeBody, 'required' => [],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('SubscriptionPlan')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
                'delete' => [
                    'tags' => ['Subscription Plans'],
                    'summary' => 'Hapus paket (is_admin)',
                    'description' => 'Masih dipakai subscriptions -> 409; pakai is_active=false alih-alih menghapus.',
                    'responses' => [
                        '204' => ['description' => 'Terhapus.'],
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '409' => self::refResponse('Conflict'),
                    ],
                ],
            ],
        ];
    }

    private static function subscriptionPaths(): array
    {
        $statusEnum = ['pending_payment', 'active', 'rejected', 'expired'];
        $indexParams = [
            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => $statusEnum]],
            ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
        ];

        return [
            '/subscriptions' => [
                'get' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Riwayat langganan family sendiri',
                    'parameters' => $indexParams,
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('Subscription')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
                'post' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Pilih paket + konfirmasi pembayaran',
                    'description' => 'Satu langkah: submit langsung menandai paid_at=now dan berstatus pending_payment, menunggu platform admin activate/reject. Ditolak 409 kalau family masih punya request pending_payment/active.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object', 'required' => ['plan_id'], 'properties' => [
                            'plan_id' => ['type' => 'string', 'format' => 'uuid', 'description' => 'Harus mengacu ke paket dengan is_active=true.'],
                            'payment_note' => ['type' => 'string', 'nullable' => true, 'description' => 'Mis. metode transfer & referensi, bebas teks.'],
                            'payment_proof_url' => ['type' => 'string', 'nullable' => true, 'description' => 'URL foto bukti transfer, didapat dari POST /uploads terlebih dulu.'],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('Tersimpan.', self::envelope('Subscription')),
                        '403' => self::refResponse('Forbidden'),
                        '409' => self::refResponse('Conflict'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/subscriptions/{subscription}' => [
                'parameters' => [['name' => 'subscription', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Detail langganan',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('Subscription')),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
            ],
            '/admin/subscriptions' => [
                'get' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Antrean review lintas-family (is_admin)',
                    'description' => 'SENGAJA di luar resolve.family: admin melihat permintaan family manapun, bukan cuma family miliknya sendiri.',
                    'parameters' => $indexParams,
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('Subscription')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
            ],
            '/admin/subscriptions/{subscription}/activate' => [
                'parameters' => [['name' => 'subscription', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'post' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Aktifkan langganan (is_admin)',
                    'description' => 'Hanya dari status pending_payment. starts_at=now, ends_at=starts_at+plan.duration_days.',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('Subscription')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/admin/subscriptions/{subscription}/reject' => [
                'parameters' => [['name' => 'subscription', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'post' => [
                    'tags' => ['Subscriptions'],
                    'summary' => 'Tolak langganan (is_admin)',
                    'description' => 'Hanya dari status pending_payment.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object', 'required' => ['review_note'], 'properties' => [
                            'review_note' => ['type' => 'string'],
                        ],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('Subscription')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
        ];
    }

    private static function llmSettingPaths(): array
    {
        return [
            '/llm-settings' => [
                'get' => [
                    'tags' => ['LLM Settings'],
                    'summary' => 'Lihat setting LLM platform (is_admin)',
                    'description' => 'Platform-wide, bukan per-family -- tidak di bawah resolve.family/X-Family-Id. Sebelum baris DB pernah dibuat, menampilkan fallback dari .env.',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('LlmSetting')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
                'put' => [
                    'tags' => ['LLM Settings'],
                    'summary' => 'Update setting LLM platform (is_admin)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['model'],
                        'properties' => [
                            'key' => ['type' => 'string', 'nullable' => true, 'description' => 'Opsional; kosongkan untuk mempertahankan key lama. Tidak pernah dikembalikan lewat GET.'],
                            'model' => ['type' => 'string'],
                            'base_url' => ['type' => 'string', 'nullable' => true],
                        ],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('LlmSetting')),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
        ];
    }

    private static function authPaths(): array
    {
        return [
            '/auth/register' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Register',
                    'security' => [],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['full_name', 'password'],
                        'properties' => [
                            'full_name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Wajib jika phone kosong'],
                            'phone' => ['type' => 'string', 'description' => 'Wajib jika email kosong'],
                            'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8],
                            'password_confirmation' => ['type' => 'string', 'format' => 'password', 'description' => 'Opsional -- kalau dikirim harus cocok dengan password.'],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('User terdaftar.', self::envelope('AuthPayload')),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/auth/login' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Login',
                    'security' => [],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['password'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Wajib jika phone kosong'],
                            'phone' => ['type' => 'string', 'description' => 'Wajib jika email kosong'],
                            'password' => ['type' => 'string', 'format' => 'password'],
                        ],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('Login berhasil.', self::envelope('AuthPayload')),
                        '401' => self::jsonResponse('Kredensial salah (pesan generik, tidak membedakan user tidak ada vs password salah).', ['$ref' => '#/components/schemas/MessageError']),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/auth/me' => [
                'get' => [
                    'tags' => ['Auth'],
                    'summary' => 'User yang sedang login',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('User')),
                        '401' => self::refResponse('Unauthorized'),
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Auth'],
                    'summary' => 'Cabut token yang sedang dipakai',
                    'responses' => [
                        '204' => ['description' => 'Token dicabut.'],
                        '401' => self::refResponse('Unauthorized'),
                    ],
                ],
            ],
        ];
    }

    private static function familyPaths(): array
    {
        return [
            '/families' => [
                'get' => [
                    'tags' => ['Families'],
                    'summary' => 'List family milik user yang login',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('Family')),
                        '401' => self::refResponse('Unauthorized'),
                    ],
                ],
                'post' => [
                    'tags' => ['Families'],
                    'summary' => 'Buat family baru (pembuat otomatis jadi admin)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3, 'default' => 'IDR'],
                            'timezone' => ['type' => 'string', 'default' => 'Asia/Jakarta'],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('Family dibuat.', self::envelope('Family')),
                        '401' => self::refResponse('Unauthorized'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/families/{family}' => [
                'parameters' => [['name' => 'family', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Families'],
                    'summary' => 'Detail family (harus member)',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('Family')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
                'put' => [
                    'tags' => ['Families'],
                    'summary' => 'Update family (admin only)',
                    'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                            'timezone' => ['type' => 'string'],
                            'onboarding_done' => ['type' => 'boolean'],
                        ],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('Family')),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
                'delete' => [
                    'tags' => ['Families'],
                    'summary' => 'Hapus family (admin only, cascade)',
                    'responses' => [
                        '204' => ['description' => 'Family dihapus.'],
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
            ],
            '/family-invites/accept' => [
                'post' => [
                    'tags' => ['Family Invites'],
                    'summary' => 'Terima invite lewat token (di luar resolve.family)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'required' => ['token'],
                        'properties' => ['token' => ['type' => 'string', 'example' => 'AMANA-AB12CD']],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('Membership dibuat.', self::envelope('FamilyMember')),
                        '401' => self::refResponse('Unauthorized'),
                        '422' => self::jsonResponse('Token invalid/dipakai/kedaluwarsa/tidak cocok/sudah member.', ['$ref' => '#/components/schemas/ValidationError']),
                    ],
                ],
            ],
        ];
    }

    /**
     * Standard index/store/show/update/destroy resources living under
     * resolve.family, all sharing the same envelope/error shape.
     */
    private static function standardCrudPaths(): array
    {
        $resources = [
            [
                'tag' => 'Family Members', 'base' => '/family-members', 'param' => 'family_member', 'schema' => 'FamilyMember',
                'create' => ['user_id', 'role'], 'createRole' => 'admin', 'updateRole' => 'admin', 'deleteRole' => 'admin',
                'indexQuery' => [
                    ['name' => 'role', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']]],
                    ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'storeBody' => ['type' => 'object', 'required' => ['user_id', 'role'], 'properties' => [
                    'user_id' => ['type' => 'string', 'format' => 'uuid'],
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                    'nickname' => ['type' => 'string', 'nullable' => true],
                    'monthly_quota' => ['type' => 'integer', 'nullable' => true],
                ]],
                'updateBody' => ['type' => 'object', 'properties' => [
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                    'nickname' => ['type' => 'string', 'nullable' => true],
                    'monthly_quota' => ['type' => 'integer', 'nullable' => true],
                ]],
                'deleteNote' => 'Soft-remove: set removed_at, baris tidak dihapus.',
            ],
            [
                'tag' => 'Family Invites', 'base' => '/family-invites', 'param' => 'family_invite', 'schema' => 'FamilyInvite',
                'createRole' => 'admin', 'updateRole' => 'admin', 'deleteRole' => 'admin',
                'storeBody' => ['type' => 'object', 'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Wajib jika phone kosong'],
                    'phone' => ['type' => 'string', 'description' => 'Wajib jika email kosong'],
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer'], 'default' => 'member'],
                ]],
                'updateBody' => ['type' => 'object', 'properties' => [
                    'role' => ['type' => 'string', 'enum' => ['admin', 'member', 'viewer']],
                    'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                ]],
                'deleteNote' => 'Revoke.',
            ],
            [
                'tag' => 'Accounts', 'base' => '/accounts', 'param' => 'account', 'schema' => 'Account',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin', 'deleteConflict' => true,
                'storeBody' => ['type' => 'object', 'required' => ['name', 'account_type'], 'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Unik per family'],
                    'account_type' => ['type' => 'string', 'enum' => ['bank', 'ewallet', 'cash', 'other']],
                    'institution' => ['type' => 'string', 'nullable' => true],
                    'masked_number' => ['type' => 'string', 'nullable' => true],
                    'opening_balance' => ['type' => 'integer', 'default' => 0],
                    'owner_member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'is_shared' => ['type' => 'boolean'],
                    'is_archived' => ['type' => 'boolean'],
                    'sort_order' => ['type' => 'integer'],
                ]],
                'updateBody' => ['type' => 'object', 'description' => 'Sama seperti store minus opening_balance/current_balance (tidak bisa diubah, hanya lewat transaksi).', 'properties' => [
                    'name' => ['type' => 'string'],
                    'account_type' => ['type' => 'string', 'enum' => ['bank', 'ewallet', 'cash', 'other']],
                    'institution' => ['type' => 'string', 'nullable' => true],
                    'masked_number' => ['type' => 'string', 'nullable' => true],
                    'owner_member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'is_shared' => ['type' => 'boolean'],
                    'is_archived' => ['type' => 'boolean'],
                    'sort_order' => ['type' => 'integer'],
                ]],
                'deleteNote' => 'Arsipkan (is_archived) alih-alih menghapus jika masih dipakai transaksi.',
            ],
            [
                'tag' => 'Wallets', 'base' => '/wallets', 'param' => 'wallet', 'schema' => 'Wallet',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin', 'deleteConflict' => true,
                'storeBody' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Unik per family'],
                    'icon' => ['type' => 'string'],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'monthly_budget' => ['type' => 'integer', 'default' => 0],
                    'rollover' => ['type' => 'boolean'],
                    'is_archived' => ['type' => 'boolean'],
                    'sort_order' => ['type' => 'integer'],
                ]],
                'updateBody' => null,
                'deleteNote' => 'Masih dipakai transaksi -> 409.',
            ],
            [
                'tag' => 'Income Sources', 'base' => '/income-sources', 'param' => 'income_source', 'schema' => 'IncomeSource',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin', 'deleteConflict' => true,
                'storeBody' => ['type' => 'object', 'required' => ['name'], 'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Unik per family'],
                    'owner_member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'expected_amount' => ['type' => 'integer', 'nullable' => true],
                    'cadence' => ['type' => 'string', 'enum' => ['monthly', 'biweekly', 'weekly', 'irregular'], 'nullable' => true],
                    'is_archived' => ['type' => 'boolean'],
                ]],
                'updateBody' => null,
                'deleteNote' => 'Masih dipakai transaksi -> 409.',
            ],
            [
                'tag' => 'Savings Goals', 'base' => '/savings-goals', 'param' => 'savings_goal', 'schema' => 'SavingsGoal',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin', 'deleteConflict' => true,
                'indexQuery' => [
                    ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['active', 'achieved', 'paused', 'cancelled']]],
                    ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'storeBody' => ['type' => 'object', 'required' => ['target_name', 'target_amount'], 'properties' => [
                    'target_name' => ['type' => 'string'],
                    'target_amount' => ['type' => 'integer', 'minimum' => 1],
                    'deadline' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'icon' => ['type' => 'string', 'nullable' => true],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => ['active', 'achieved', 'paused', 'cancelled'], 'default' => 'active'],
                ]],
                'updateBody' => ['type' => 'object', 'description' => 'current_amount tidak bisa diubah langsung (cache). Mengubah status ke achieved otomatis mengisi achieved_at.', 'properties' => [
                    'target_name' => ['type' => 'string'],
                    'target_amount' => ['type' => 'integer', 'minimum' => 1],
                    'deadline' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                    'icon' => ['type' => 'string', 'nullable' => true],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'status' => ['type' => 'string', 'enum' => ['active', 'achieved', 'paused', 'cancelled']],
                ]],
                'deleteNote' => 'Masih dipakai transaksi -> 409; pakai status=cancelled alih-alih menghapus.',
            ],
            [
                'tag' => 'Transactions', 'base' => '/transactions', 'param' => 'transaction', 'schema' => 'Transaction',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'member',
                'indexQuery' => [
                    ['name' => 'month', 'in' => 'query', 'description' => 'YYYY-MM, filter transaction_date.', 'schema' => ['type' => 'string', 'example' => '2026-08']],
                    ['name' => 'wallet_id', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                    ['name' => 'account_id', 'in' => 'query', 'description' => 'Hanya mencocokkan account_id (sisi asal), bukan to_account_id.', 'schema' => ['type' => 'string', 'format' => 'uuid']],
                    ['name' => 'type', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer', 'savings']]],
                    ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'storeBody' => ['type' => 'object', 'required' => ['type', 'amount', 'transaction_date', 'account_id'], 'description' => 'Field wajib tambahan per type: expense->wallet_id, income->source_id, transfer->to_account_id (!=account_id), savings->goal_id.', 'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer', 'savings']],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'transaction_date' => ['type' => 'string', 'format' => 'date'],
                    'account_id' => ['type' => 'string', 'format' => 'uuid'],
                    'to_account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'source_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'goal_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'receipt_url' => ['type' => 'string', 'nullable' => true],
                ]],
                'updateBody' => ['type' => 'object', 'description' => 'Semua field opsional (partial). Ganti type otomatis mengosongkan FK yang tidak relevan. origin/created_by tidak menerima input klien.', 'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer', 'savings']],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'transaction_date' => ['type' => 'string', 'format' => 'date'],
                    'account_id' => ['type' => 'string', 'format' => 'uuid'],
                    'to_account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'source_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'goal_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'receipt_url' => ['type' => 'string', 'nullable' => true],
                ]],
                'deleteNote' => 'Soft delete; membalik efek saldo.',
            ],
            [
                'tag' => 'Recurring Rules', 'base' => '/recurring-rules', 'param' => 'recurring_rule', 'schema' => 'RecurringRule',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin',
                'storeBody' => ['type' => 'object', 'required' => ['type', 'amount', 'rrule', 'next_run_on'], 'description' => 'wallet_id wajib jika type=expense, source_id jika income, account_id jika savings.', 'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'savings']],
                    'amount' => ['type' => 'integer', 'minimum' => 1],
                    'wallet_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'source_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'account_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'note' => ['type' => 'string', 'nullable' => true],
                    'rrule' => ['type' => 'string', 'example' => 'FREQ=MONTHLY;BYMONTHDAY=1'],
                    'next_run_on' => ['type' => 'string', 'format' => 'date'],
                    'is_active' => ['type' => 'boolean'],
                ]],
                'updateBody' => null,
                'deleteNote' => null,
            ],
            [
                'tag' => 'Chat Threads', 'base' => '/chat-threads', 'param' => 'chat_thread', 'schema' => 'ChatThread',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'member',
                'indexQuery' => [
                    ['name' => 'kind', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['general', 'onboarding']]],
                    ['name' => 'per_page', 'in' => 'query', 'description' => 'Default 20, maks 100.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                ],
                'storeBody' => ['type' => 'object', 'description' => 'member_id selalu diisi dari member yang login, tidak bisa dikirim lewat body.', 'properties' => [
                    'title' => ['type' => 'string', 'nullable' => true],
                    'kind' => ['type' => 'string', 'enum' => ['general', 'onboarding'], 'default' => 'general'],
                ]],
                'updateBody' => ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'nullable' => true],
                    'kind' => ['type' => 'string', 'enum' => ['general', 'onboarding']],
                ]],
                'deleteNote' => null,
            ],
            [
                'tag' => 'Onboarding Answers', 'base' => '/onboarding-answers', 'param' => 'onboarding_answer', 'schema' => 'OnboardingAnswer',
                'createRole' => 'member', 'updateRole' => 'member', 'deleteRole' => 'admin',
                'storeBody' => ['type' => 'object', 'required' => ['question_key'], 'properties' => [
                    'question_key' => ['type' => 'string', 'description' => 'Unik per family'],
                    'answer' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'skipped' => ['type' => 'boolean'],
                ]],
                'updateBody' => ['type' => 'object', 'description' => 'question_key tidak bisa diubah (identitas jawaban).', 'properties' => [
                    'answer' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'skipped' => ['type' => 'boolean'],
                ]],
                'deleteNote' => null,
            ],
            [
                'tag' => 'Notifications', 'base' => '/notifications', 'param' => 'notification', 'schema' => 'Notification',
                'createRole' => 'admin', 'updateRole' => 'viewer', 'deleteRole' => 'viewer',
                'storeBody' => ['type' => 'object', 'required' => ['kind', 'title'], 'description' => 'Notifikasi normal dibuat oleh job harian, bukan user.', 'properties' => [
                    'member_id' => ['type' => 'string', 'format' => 'uuid', 'nullable' => true],
                    'kind' => ['type' => 'string', 'enum' => ['budget_warning', 'goal_progress', 'bill_due', 'weekly_digest']],
                    'title' => ['type' => 'string'],
                    'body' => ['type' => 'string', 'nullable' => true],
                    'deeplink' => ['type' => 'string', 'nullable' => true],
                ]],
                'updateBody' => ['type' => 'object', 'description' => 'Dipakai untuk tandai baca/belum. Semua role (viewer ke atas) boleh menandai baca notifikasi mereka sendiri.', 'properties' => [
                    'read_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ]],
                'deleteNote' => null,
            ],
        ];

        $paths = [];

        foreach ($resources as $r) {
            $item = [
                'parameters' => [['name' => $r['param'], 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
            ];

            $collectionItem = [];

            $collectionItem['get'] = [
                'tags' => [$r['tag']],
                'summary' => "List {$r['tag']}",
                'parameters' => [
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ...($r['indexQuery'] ?? []),
                ],
                'responses' => [
                    '200' => self::jsonResponse('OK', self::paginatedEnvelope($r['schema'])),
                    '403' => self::refResponse('Forbidden'),
                ],
            ];

            $collectionItem['post'] = [
                'tags' => [$r['tag']],
                'summary' => "Buat {$r['tag']} baru ({$r['createRole']})",
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $r['storeBody']]]],
                'responses' => [
                    '201' => self::jsonResponse('Dibuat.', self::envelope($r['schema'])),
                    '403' => self::refResponse('Forbidden'),
                    '422' => self::refResponse('ValidationError'),
                ],
            ];

            $item['get'] = [
                'tags' => [$r['tag']],
                'summary' => 'Detail',
                'responses' => [
                    '200' => self::jsonResponse('OK', self::envelope($r['schema'])),
                    '403' => self::refResponse('Forbidden'),
                    '404' => self::refResponse('NotFound'),
                ],
            ];

            $item['put'] = [
                'tags' => [$r['tag']],
                'summary' => "Update ({$r['updateRole']})",
                'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => $r['updateBody'] ?? $r['storeBody']]]],
                'responses' => [
                    '200' => self::jsonResponse('OK', self::envelope($r['schema'])),
                    '403' => self::refResponse('Forbidden'),
                    '404' => self::refResponse('NotFound'),
                    '422' => self::refResponse('ValidationError'),
                ],
            ];

            $deleteResponses = [
                '204' => ['description' => 'Dihapus.'.($r['deleteNote'] ? ' '.$r['deleteNote'] : '')],
                '403' => self::refResponse('Forbidden'),
                '404' => self::refResponse('NotFound'),
            ];
            if (! empty($r['deleteConflict'])) {
                $deleteResponses['409'] = self::refResponse('Conflict');
            }

            $item['delete'] = [
                'tags' => [$r['tag']],
                'summary' => "Hapus ({$r['deleteRole']})",
                'responses' => $deleteResponses,
            ];

            $paths[$r['base']] = $collectionItem;
            $paths[$r['base'].'/{'.$r['param'].'}'] = $item;
        }

        return $paths;
    }

    private static function walletBudgetPaths(): array
    {
        $storeBody = ['type' => 'object', 'required' => ['period', 'amount'], 'description' => 'period dinormalisasi ke tanggal 1 bulan tsb.', 'properties' => [
            'period' => ['type' => 'string', 'format' => 'date'],
            'amount' => ['type' => 'integer'],
        ]];

        return [
            '/wallets/{wallet}/budgets' => [
                'parameters' => [['name' => 'wallet', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Wallet Budgets'],
                    'summary' => 'List budget per wallet',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('WalletBudget')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
                'post' => [
                    'tags' => ['Wallet Budgets'],
                    'summary' => 'Set budget wallet untuk satu periode (member)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $storeBody]]],
                    'responses' => [
                        '201' => self::jsonResponse('Dibuat.', self::envelope('WalletBudget')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/budgets/{budget}' => [
                'parameters' => [['name' => 'budget', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Wallet Budgets'],
                    'summary' => 'Detail (shallow route)',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('WalletBudget')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
                'put' => [
                    'tags' => ['Wallet Budgets'],
                    'summary' => 'Update (member)',
                    'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => ['period' => ['type' => 'string', 'format' => 'date'], 'amount' => ['type' => 'integer']],
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('WalletBudget')),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
                'delete' => [
                    'tags' => ['Wallet Budgets'],
                    'summary' => 'Hapus (admin)',
                    'responses' => [
                        '204' => ['description' => 'Dihapus.'],
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
            ],
        ];
    }

    private static function chatMessagePaths(): array
    {
        return [
            '/chat-threads/{chat_thread}/messages' => [
                'parameters' => [['name' => 'chat_thread', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Chat Messages'],
                    'summary' => 'List pesan dalam thread',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::paginatedEnvelope('ChatMessage')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
                'post' => [
                    'tags' => ['Chat Messages'],
                    'summary' => 'Kirim pesan (role selalu dipaksa user)',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'description' => 'content wajib jika attachment_url kosong.',
                        'properties' => [
                            'content' => ['type' => 'string', 'nullable' => true],
                            'input_mode' => ['type' => 'string', 'enum' => ['text', 'voice', 'image'], 'nullable' => true],
                            'attachment_url' => ['type' => 'string', 'nullable' => true],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('Dibuat. Memperbarui chat_threads.last_message_at.', self::envelope('ChatMessage')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/messages/{message}' => [
                'parameters' => [['name' => 'message', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Chat Messages'],
                    'summary' => 'Detail pesan (shallow route). Tidak ada update/destroy -- riwayat chat tidak bisa diedit.',
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('ChatMessage')),
                        '403' => self::refResponse('Forbidden'),
                    ],
                ],
            ],
        ];
    }

    private static function chatStreamPaths(): array
    {
        return [
            '/chat-threads/{chat_thread}/stream' => [
                'parameters' => [['name' => 'chat_thread', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'get' => [
                    'tags' => ['Chat Threads'],
                    'summary' => 'SSE thinking/action_card/balasan Amina/error (berumur pendek, klien wajib reconnect)',
                    'description' => 'Server menutup stream sendiri sebelum ±20-25 detik (aman dari max_execution_time shared hosting, tidak ada Redis/pub-sub). Event: thinking (sekali di awal kalau pesan terakhir masih role=user belum dibalas), message, action_card, error (role=system, job LLM gagal total), retry (berisi cursor untuk ?after= saat reconnect). Tidak ada token/done terpisah -- LLM dipanggil sekali per job (bukan streaming), dan message/error sendiri adalah sinyal selesainya giliran. Bukan JSON biasa -- Content-Type: text/event-stream.',
                    'parameters' => [[
                        'name' => 'after', 'in' => 'query', 'required' => false,
                        'description' => 'Cursor ISO-8601; ambil dari event retry terakhir. Default: waktu koneksi dibuka.',
                        'schema' => ['type' => 'string', 'format' => 'date-time'],
                    ]],
                    'responses' => [
                        '200' => ['description' => 'text/event-stream', 'content' => ['text/event-stream' => ['schema' => ['type' => 'string']]]],
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                    ],
                ],
            ],
        ];
    }

    private static function uploadPaths(): array
    {
        return [
            '/uploads' => [
                'post' => [
                    'tags' => ['Uploads'],
                    'summary' => 'Unggah berkas attachment chat (foto struk / rekaman suara)',
                    'description' => 'Hanya menyimpan berkas dan mengembalikan URL-nya -- tidak melakukan OCR/speech-to-text. Kirim url hasilnya sebagai attachment_url saat POST chat-threads/{chat_thread}/messages.',
                    'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
                        'type' => 'object',
                        'required' => ['file'],
                        'properties' => [
                            'file' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ]]]],
                    'responses' => [
                        '201' => self::jsonResponse('Tersimpan.', [
                            'type' => 'object',
                            'properties' => ['data' => [
                                'type' => 'object',
                                'properties' => [
                                    'url' => ['type' => 'string'],
                                    'mime' => ['type' => 'string'],
                                    'size' => ['type' => 'integer', 'description' => 'Byte'],
                                ],
                            ]],
                        ]),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
        ];
    }

    private static function aiActionMutationPaths(): array
    {
        return [
            '/ai-actions/{ai_action}/confirm' => [
                'parameters' => [['name' => 'ai_action', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'post' => [
                    'tags' => ['AI Actions'],
                    'summary' => 'Konfirmasi draft -- menulis baris nyata (ConfirmAiAction)',
                    'description' => 'Body opsional: field apa pun di dalamnya menimpa payload draft sebelum divalidasi & ditulis (status jadi edited, bukan confirmed). Semua id divalidasi ulang harus milik family yang sama dengan ai_action ini, tidak pernah dipercaya mentah.',
                    'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => [
                        'type' => 'object', 'description' => 'Subset dari payload draft untuk ditimpa sebelum konfirmasi.', 'additionalProperties' => true,
                    ]]]],
                    'responses' => [
                        '200' => self::jsonResponse('Dikonfirmasi (atau edited jika body mengirim perubahan).', self::envelope('AiAction')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
            '/ai-actions/{ai_action}/reject' => [
                'parameters' => [['name' => 'ai_action', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                'post' => [
                    'tags' => ['AI Actions'],
                    'summary' => 'Tolak draft -- tidak menulis apa pun',
                    'responses' => [
                        '200' => self::jsonResponse('Ditolak.', self::envelope('AiAction')),
                        '403' => self::refResponse('Forbidden'),
                        '404' => self::refResponse('NotFound'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
        ];
    }

    private static function readOnlyPaths(): array
    {
        $make = function (string $tag, string $base, string $param, string $schema, string $description): array {
            return [
                $base => [
                    'get' => [
                        'tags' => [$tag],
                        'summary' => "List {$tag}",
                        'description' => $description,
                        'responses' => [
                            '200' => self::jsonResponse('OK', self::paginatedEnvelope($schema)),
                            '403' => self::refResponse('Forbidden'),
                        ],
                    ],
                ],
                $base.'/{'.$param.'}' => [
                    'parameters' => [['name' => $param, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'format' => 'uuid']]],
                    'get' => [
                        'tags' => [$tag],
                        'summary' => 'Detail',
                        'responses' => [
                            '200' => self::jsonResponse('OK', self::envelope($schema)),
                            '403' => self::refResponse('Forbidden'),
                            '404' => self::refResponse('NotFound'),
                        ],
                    ],
                ],
            ];
        };

        return array_merge(
            $make(
                'AI Actions', '/ai-actions', 'ai_action', 'AiAction',
                'AI tidak pernah menulis tabel bisnis langsung (aturan #5); ai_actions hanya diubah lewat POST .../confirm dan .../reject (lihat di bawah), tidak pernah dihapus.'
            ),
            $make(
                'Audit Logs', '/audit-logs', 'audit_log', 'AuditLog',
                'Read-only: baris ditulis oleh proses internal saat entity lain berubah.'
            ),
        );
    }

    private static function analyticsPaths(): array
    {
        return [
            '/analytics/summary' => [
                'get' => [
                    'tags' => ['Analytics'],
                    'summary' => 'Ringkasan cashflow & budget per wallet bulan berjalan',
                    'description' => 'Sumber data wajib v_wallet_month/v_cashflow_month (bukan query ad-hoc). Tidak pernah memanggil LLM; insight naratif belum diimplementasikan (menunggu job harian).',
                    'parameters' => [[
                        'name' => 'month', 'in' => 'query', 'required' => false,
                        'description' => 'Format YYYY-MM, default bulan berjalan.',
                        'schema' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}$', 'example' => '2026-08'],
                    ]],
                    'responses' => [
                        '200' => self::jsonResponse('OK', self::envelope('AnalyticsSummary')),
                        '403' => self::refResponse('Forbidden'),
                        '422' => self::refResponse('ValidationError'),
                    ],
                ],
            ],
        ];
    }
}
