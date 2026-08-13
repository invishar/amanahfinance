<?php

namespace App\Services\Ai;

use Anthropic\Lib\Tools\BetaRunnableTool;
use App\Actions\Analytics\AnalyticsActions;
use App\Actions\LlmSettings\LlmSettingActions;
use App\Models\Account;
use App\Models\AiAction;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Family;
use App\Models\IncomeSource;
use App\Models\OnboardingAnswer;
use App\Models\SavingsGoal;
use App\Models\Wallet;
use App\Services\Ai\Contracts\ConversationRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Pesan masuk -> AssistantService -> LLM tool calling -> payload disimpan
// sebagai ai_actions.pending -> SSE action_card ke klien (lihat CLAUDE.md
// "Alur AI"). Tool tidak pernah menulis tabel bisnis -- baik draft tools
// maupun get_financial_summary hanya membaca/menyimpan ke ai_actions.
//
// LLM call itself is delegated to ConversationRunner so tests can fake the
// whole SDK boundary and still exercise the real tool closures below (lihat
// CLAUDE.md: "LLM selalu di-mock di test").
class AssistantService
{
    public function __construct(
        private ConversationRunner $runner,
        private AnalyticsActions $analytics,
    ) {}

    public function respond(ChatMessage $userMessage): ChatMessage
    {
        $thread = $userMessage->thread()->withoutGlobalScope('family')->with('family')->firstOrFail();
        $family = $thread->family;

        $wallets = $this->activeCandidates(Wallet::query()->where('family_id', $family->id)->where('is_archived', false), 'name');
        $accounts = $this->activeCandidates(Account::query()->where('family_id', $family->id)->where('is_archived', false), 'name');
        $sources = $this->activeCandidates(IncomeSource::query()->where('family_id', $family->id)->where('is_archived', false), 'name');
        $goals = $this->activeCandidates(SavingsGoal::query()->where('family_id', $family->id)->where('status', 'active'), 'target_name');

        $finalText = $this->runner->run(
            model: $this->llmSettingsModel(),
            system: $this->buildSystemPrompt($family),
            messages: $this->buildHistory($thread),
            tools: $this->buildTools($family, $userMessage, $wallets, $accounts, $sources, $goals),
            maxIterations: 4,
        );

        return $thread->messages()->create([
            'role' => 'assistant',
            'content' => $finalText !== ''
                ? $finalText
                : 'Maaf, aku belum paham maksudnya. Bisa dijelaskan lagi?',
        ]);
    }

    // Dipanggil dari ProcessAssistantMessage::failed() begitu job kehabisan
    // percobaan (LLM error, timeout, dst). role=system supaya klien bisa
    // membedakannya dari balasan Amina sungguhan -- SSE meneruskannya lewat
    // event `error` (lihat ChatStreamController), dan tetap kelihatan di
    // riwayat biasa (GET .../messages) kalau klien melewatkan event live-nya.
    public function fail(ChatMessage $userMessage): ChatMessage
    {
        $thread = $userMessage->thread()->withoutGlobalScope('family')->firstOrFail();

        $errorMessage = $thread->messages()->create([
            'role' => 'system',
            'content' => 'Amina lagi ada gangguan teknis. Coba kirim pesan itu lagi beberapa saat lagi ya.',
        ])->fresh();

        $thread->update(['last_message_at' => $errorMessage->created_at]);

        return $errorMessage;
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildHistory(ChatThread $thread): array
    {
        return $thread->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->filter(fn (ChatMessage $m) => filled($m->content))
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => (string) $m->content])
            ->all();
    }

    private function buildSystemPrompt(Family $family): string
    {
        $answers = OnboardingAnswer::query()
            ->where('family_id', $family->id)
            ->where('skipped', false)
            ->get(['question_key', 'answer'])
            ->mapWithKeys(fn (OnboardingAnswer $a) => [$a->question_key => $a->answer])
            ->all();

        $summary = $this->analytics->summary($family->id, now()->startOfMonth());

        $context = [
            'family_name' => $family->name,
            'currency' => $family->currency,
            'onboarding_answers' => $answers,
            'ringkasan_bulan_berjalan' => $summary,
        ];

        return config('amina.persona')
            ."\n\nKonteks keluarga (JANGAN mengarang di luar ini):\n"
            .json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return Collection<int, array{id: string, name: string}>
     */
    private function activeCandidates($query, string $nameColumn): Collection
    {
        return $query->get(['id', $nameColumn])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->{$nameColumn}])
            ->values();
    }

    /**
     * @param  Collection<int, array{id:string,name:string}>  $wallets
     * @param  Collection<int, array{id:string,name:string}>  $accounts
     * @param  Collection<int, array{id:string,name:string}>  $sources
     * @param  Collection<int, array{id:string,name:string}>  $goals
     * @return array<int, BetaRunnableTool>
     */
    private function buildTools(
        Family $family,
        ChatMessage $userMessage,
        Collection $wallets,
        Collection $accounts,
        Collection $sources,
        Collection $goals,
    ): array {
        $stageDraft = function (string $action, array $payload) use ($family, $userMessage): string {
            $aiAction = AiAction::create([
                'message_id' => $userMessage->id,
                'family_id' => $family->id,
                'action' => $action,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            return "Draft {$action} tersimpan (id={$aiAction->id}), menunggu konfirmasi user lewat action card. Jangan menganggap datanya sudah tercatat.";
        };

        return [
            new BetaRunnableTool(
                definition: ToolDefinitions::createTransaction(),
                run: function (array $input) use ($accounts, $wallets, $sources, $goals, $stageDraft) {
                    $type = $input['type'] ?? null;

                    return $stageDraft('create_transaction', [
                        'type' => $type,
                        'amount' => $input['amount'] ?? null,
                        'transaction_date' => $input['transaction_date'] ?? now()->toDateString(),
                        'note' => $input['note'] ?? null,
                        'account_id' => NameResolver::resolve($input['account_name'] ?? null, $accounts->all()),
                        'wallet_id' => $type === 'expense' ? NameResolver::resolve($input['wallet_name'] ?? null, $wallets->all()) : null,
                        'source_id' => $type === 'income' ? NameResolver::resolve($input['source_name'] ?? null, $sources->all()) : null,
                        'to_account_id' => $type === 'transfer' ? NameResolver::resolve($input['to_account_name'] ?? null, $accounts->all()) : null,
                        'goal_id' => $type === 'savings' ? NameResolver::resolve($input['goal_name'] ?? null, $goals->all()) : null,
                    ]);
                },
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::createWallet(),
                run: fn (array $input) => $stageDraft('create_wallet', [
                    'name' => $input['name'] ?? null,
                    'icon' => $input['icon'] ?? null,
                    'color' => $input['color'] ?? null,
                    'monthly_budget' => $input['monthly_budget'] ?? null,
                ]),
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::createAccount(),
                run: fn (array $input) => $stageDraft('create_account', [
                    'name' => $input['name'] ?? null,
                    'account_type' => $input['account_type'] ?? null,
                    'institution' => $input['institution'] ?? null,
                    'opening_balance' => $input['opening_balance'] ?? 0,
                ]),
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::createIncomeSource(),
                run: fn (array $input) => $stageDraft('create_income_source', [
                    'name' => $input['name'] ?? null,
                    'expected_amount' => $input['expected_amount'] ?? null,
                    'cadence' => $input['cadence'] ?? null,
                ]),
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::createSavingsGoal(),
                run: function (array $input) use ($accounts, $stageDraft) {
                    return $stageDraft('create_savings_goal', [
                        'target_name' => $input['target_name'] ?? null,
                        'target_amount' => $input['target_amount'] ?? null,
                        'deadline' => $input['deadline'] ?? null,
                        'account_id' => NameResolver::resolve($input['account_name'] ?? null, $accounts->all()),
                    ]);
                },
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::advice(),
                run: fn (array $input) => $stageDraft('advice', [
                    'message' => $input['message'] ?? null,
                ]),
            ),
            new BetaRunnableTool(
                definition: ToolDefinitions::getFinancialSummary(),
                run: function (array $input) use ($family) {
                    $period = filled($input['month'] ?? null)
                        ? Carbon::createFromFormat('Y-m', $input['month'])->startOfMonth()
                        : now()->startOfMonth();

                    return json_encode($this->analytics->summary($family->id, $period), JSON_UNESCAPED_UNICODE);
                },
            ),
        ];
    }

    private function llmSettingsModel(): string
    {
        return app(LlmSettingActions::class)->current()->model;
    }
}
