// Pemetaan data API → label siap tampil. TIDAK ada perhitungan turunan di
// sini: `spent`, `percent`, `status`, dan total kas semuanya datang dari
// `GET /analytics/summary` atau dari field entitasnya sendiri.

import { formatDateID, formatRupiah } from "@/lib/format";
import type {
  Account,
  AnalyticsSummary,
  IncomeSource,
  SavingsGoal,
  Transaction,
  Wallet,
} from "@/lib/api/hooks";

type AnalyticsWallet = NonNullable<AnalyticsSummary["wallets"]>[number];
type BudgetStatus = NonNullable<AnalyticsWallet["status"]>;

const STATUS_LABEL: Record<BudgetStatus, string> = {
  ok: "Aman",
  warning: "Hampir habis",
  over: "Lewat budget",
  no_budget: "Tanpa budget",
};

const STATUS_COLOR: Record<BudgetStatus, string> = {
  ok: "var(--color-text)",
  warning: "var(--color-accent-600)",
  over: "var(--color-accent-800)",
  no_budget: "var(--color-text)",
};

const STATUS_BAR_COLOR: Record<BudgetStatus, string> = {
  ok: "var(--color-accent-400)",
  warning: "var(--color-accent-600)",
  over: "var(--color-accent-800)",
  no_budget: "var(--color-neutral-400)",
};

export interface WalletView {
  id: string;
  name: string;
  icon: string;
  spentLabel: string;
  budgetLabel: string;
  percent: number;
  statusLabel: string;
  statusColor: string;
  barColor: string;
  raw: Wallet;
}

/** Gabungkan daftar wallet dengan angka bulan berjalan dari analytics. */
export function walletsView(
  wallets: Wallet[],
  analytics?: AnalyticsSummary,
): WalletView[] {
  const byId = new Map(
    (analytics?.wallets ?? []).map((w) => [w.wallet_id, w] as const),
  );

  return wallets.map((wallet) => {
    const stats = byId.get(wallet.id);
    const status: BudgetStatus = stats?.status ?? "no_budget";
    return {
      id: wallet.id ?? "",
      name: wallet.name ?? "",
      icon: wallet.icon ?? "shopping-cart",
      spentLabel: formatRupiah(stats?.spent ?? 0),
      budgetLabel: formatRupiah(stats?.budget ?? wallet.monthly_budget ?? 0),
      percent: stats?.percent ?? 0,
      statusLabel: STATUS_LABEL[status],
      statusColor: STATUS_COLOR[status],
      barColor: STATUS_BAR_COLOR[status],
      raw: wallet,
    };
  });
}

/** Urutan bar dashboard/analisa: paling boros di atas. */
export function sortBySpent(views: WalletView[]): WalletView[] {
  return [...views].sort((a, b) => b.percent - a.percent);
}

export interface AccountView {
  id: string;
  name: string;
  balanceLabel: string;
  typeLabel: string;
  typeIcon: string;
  raw: Account;
}

const ACCOUNT_TYPE_LABEL: Record<string, string> = {
  bank: "Bank",
  ewallet: "E-Wallet",
  cash: "Tunai",
  other: "Lainnya",
};

const ACCOUNT_TYPE_ICON: Record<string, string> = {
  bank: "landmark",
  ewallet: "smartphone",
  cash: "banknote",
  other: "wallet",
};

export function accountsView(accounts: Account[]): AccountView[] {
  return accounts.map((account) => ({
    id: account.id ?? "",
    name: account.name ?? "",
    balanceLabel: formatRupiah(account.current_balance ?? 0),
    typeLabel: ACCOUNT_TYPE_LABEL[account.account_type ?? "other"] ?? "Lainnya",
    typeIcon: ACCOUNT_TYPE_ICON[account.account_type ?? "other"] ?? "wallet",
    raw: account,
  }));
}

export function totalBalance(accounts: Account[]): number {
  return accounts.reduce((sum, a) => sum + (a.current_balance ?? 0), 0);
}

export interface GoalView {
  id: string;
  name: string;
  percent: number;
  currentLabel: string;
  targetLabel: string;
  deadlineLabel: string | null;
  raw: SavingsGoal;
}

export function goalsView(goals: SavingsGoal[]): GoalView[] {
  return goals.map((goal) => ({
    id: goal.id ?? "",
    name: goal.target_name ?? "",
    percent: goal.percent ?? 0,
    currentLabel: formatRupiah(goal.current_amount ?? 0),
    targetLabel: formatRupiah(goal.target_amount ?? 0),
    deadlineLabel: goal.deadline ? formatDateID(goal.deadline) : null,
    raw: goal,
  }));
}

export interface IncomeSourceView {
  id: string;
  name: string;
  /** `expected_amount` bila diisi — API belum menyediakan realisasi per sumber. */
  expectedLabel: string | null;
  raw: IncomeSource;
}

export function incomeSourcesView(sources: IncomeSource[]): IncomeSourceView[] {
  return sources.map((source) => ({
    id: source.id ?? "",
    name: source.name ?? "",
    expectedLabel:
      typeof source.expected_amount === "number"
        ? formatRupiah(source.expected_amount)
        : null,
    raw: source,
  }));
}

export interface TransactionView {
  id: string;
  note: string;
  amountLabel: string;
  dateLabel: string;
  walletName: string;
  icon: string;
  iconBg: string;
  iconColor: string;
  raw: Transaction;
}

const INFLOW: ReadonlySet<string> = new Set(["income"]);

export function recentTransactions(
  transactions: Transaction[],
  wallets: Wallet[],
  incomeSources: IncomeSource[],
  limit = 8,
): TransactionView[] {
  return [...transactions]
    .sort((a, b) =>
      (b.transaction_date ?? "").localeCompare(a.transaction_date ?? ""),
    )
    .slice(0, limit)
    .map((t) => {
      const isIncome = INFLOW.has(t.type ?? "");
      return {
        id: t.id ?? "",
        note: t.note || labelForType(t.type),
        amountLabel: (isIncome ? "+ " : "− ") + formatRupiah(t.amount ?? 0),
        dateLabel: t.transaction_date ? formatDateID(t.transaction_date) : "",
        walletName:
          wallets.find((w) => w.id === t.wallet_id)?.name ??
          incomeSources.find((s) => s.id === t.source_id)?.name ??
          labelForType(t.type),
        icon: isIncome ? "arrow-down-left" : "arrow-up-right",
        iconBg: isIncome ? "var(--color-income-bg)" : "var(--color-accent-100)",
        iconColor: isIncome
          ? "var(--color-income-fg)"
          : "var(--color-accent-700)",
        raw: t,
      };
    });
}

function labelForType(type: Transaction["type"]): string {
  switch (type) {
    case "income":
      return "Pemasukan";
    case "expense":
      return "Pengeluaran";
    case "transfer":
      return "Transfer";
    case "savings":
      return "Tabungan";
    default:
      return "—";
  }
}
