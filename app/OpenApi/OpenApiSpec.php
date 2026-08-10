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
                'Chat Threads', 'Chat Messages', 'Onboarding Answers', 'Notifications',
                'AI Actions', 'Audit Logs', 'Analytics',
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
            self::readOnlyPaths(),
            self::analyticsPaths(),
        );
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
                        'required' => ['full_name', 'password', 'password_confirmation'],
                        'properties' => [
                            'full_name' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Wajib jika phone kosong'],
                            'phone' => ['type' => 'string', 'description' => 'Wajib jika email kosong'],
                            'password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8],
                            'password_confirmation' => ['type' => 'string', 'format' => 'password'],
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
                        '422' => self::jsonResponse('Kredensial salah (pesan generik, tidak membedakan user tidak ada vs password salah).', ['$ref' => '#/components/schemas/ValidationError']),
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
                'parameters' => [['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']]],
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
                'Read-only: AI tidak pernah menulis tabel bisnis (aturan #5); ai_actions hanya diubah oleh ConfirmAiAction (belum diimplementasikan), tidak pernah dihapus.'
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
