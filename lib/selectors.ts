// View model per layar. SEMENTARA: seluruh perhitungan di sini milik server
// (`GET /dashboard`, `GET /analytics/summary`, field `spent`/`status`/`percent`
// pada wallet & goal). Begitu `lib/api.ts` hidup, fungsi-fungsi ini menyusut
// jadi pemetaan label saja.

import { formatDateID, formatRupiah } from "@/lib/format";
import { accountTypeIcon, accountTypeLabel } from "@/lib/mock/data";
import {
  budgetStatus,
  estimateGoalCompletion,
  walletSpent,
  type BudgetStatus,
} from "@/lib/mock/derive";
import type {
  Account,
  IncomeSource,
  SavingsGoal,
  Transaction,
  Wallet,
} from "@/lib/types";

const STATUS_LABEL: Record<BudgetStatus, string> = {
  safe: "Aman",
  near: "Hampir habis",
  over: "Lewat budget",
};

const STATUS_COLOR: Record<BudgetStatus, string> = {
  safe: "var(--color-text)",
  near: "var(--color-accent-600)",
  over: "var(--color-accent-800)",
};

const STATUS_BAR_COLOR: Record<BudgetStatus, string> = {
  safe: "var(--color-accent-400)",
  near: "var(--color-accent-600)",
  over: "var(--color-accent-800)",
};

export interface WalletView extends Wallet {
  spent: number;
  spentLabel: string;
  budgetLabel: string;
  pct: number;
  statusLabel: string;
  statusColor: string;
  barColor: string;
}

export function walletsView(
  wallets: Wallet[],
  transactions: Transaction[],
): WalletView[] {
  return wallets.map((w) => {
    const spent = walletSpent(w.id, transactions);
    const pct = w.monthly_budget
      ? Math.min(100, Math.round((spent / w.monthly_budget) * 100))
      : 0;
    const status = budgetStatus(spent, w.monthly_budget);
    return {
      ...w,
      spent,
      spentLabel: formatRupiah(spent),
      budgetLabel: formatRupiah(w.monthly_budget),
      pct,
      statusLabel: STATUS_LABEL[status],
      statusColor: STATUS_COLOR[status],
      barColor: STATUS_BAR_COLOR[status],
    };
  });
}

export interface WalletBar extends WalletView {
  /** Panjang bar relatif terhadap wallet paling boros. */
  barPct: number;
}

export function walletBars(
  wallets: Wallet[],
  transactions: Transaction[],
): WalletBar[] {
  const views = walletsView(wallets, transactions);
  const maxSpent = Math.max(1, ...views.map((w) => w.spent));
  return views
    .map((w) => ({ ...w, barPct: Math.round((w.spent / maxSpent) * 100) }))
    .sort((a, b) => b.barPct - a.barPct);
}

export interface AccountView extends Account {
  balanceLabel: string;
  typeLabel: string;
  typeIcon: string;
}

export function accountsView(accounts: Account[]): AccountView[] {
  return accounts.map((a) => ({
    ...a,
    balanceLabel: formatRupiah(a.current_balance),
    typeLabel: accountTypeLabel[a.account_type],
    typeIcon: accountTypeIcon[a.account_type],
  }));
}

export function totalBalance(accounts: Account[]): number {
  return accounts.reduce((sum, a) => sum + a.current_balance, 0);
}

export interface TransactionView extends Transaction {
  amountLabel: string;
  dateLabel: string;
  walletName: string;
  icon: string;
  iconBg: string;
  iconColor: string;
}

export function recentTransactions(
  transactions: Transaction[],
  wallets: Wallet[],
  incomeSources: IncomeSource[],
  limit = 8,
): TransactionView[] {
  return [...transactions]
    .sort(
      (a, b) =>
        new Date(b.transaction_date).getTime() -
        new Date(a.transaction_date).getTime(),
    )
    .slice(0, limit)
    .map((t) => {
      const isIncome = t.type === "income";
      return {
        ...t,
        amountLabel: (isIncome ? "+ " : "− ") + formatRupiah(t.amount),
        dateLabel: formatDateID(t.transaction_date),
        walletName:
          wallets.find((w) => w.id === t.wallet_id)?.name ??
          (isIncome
            ? (incomeSources.find((i) => i.id === t.source_id)?.name ??
              "Pemasukan")
            : "—"),
        icon: isIncome ? "arrow-down-left" : "arrow-up-right",
        iconBg: isIncome ? "var(--color-income-bg)" : "var(--color-accent-100)",
        iconColor: isIncome
          ? "var(--color-income-fg)"
          : "var(--color-accent-700)",
      };
    });
}

export interface GoalView extends SavingsGoal {
  pct: number;
  currentLabel: string;
  targetLabel: string;
  deadlineLabel: string;
  etaLabel: string;
}

export function goalsView(goals: SavingsGoal[]): GoalView[] {
  return goals.map((g) => ({
    ...g,
    pct: Math.round(Math.min(100, (g.current_amount / g.target_amount) * 100)),
    currentLabel: formatRupiah(g.current_amount),
    targetLabel: formatRupiah(g.target_amount),
    deadlineLabel: formatDateID(g.deadline),
    etaLabel: estimateGoalCompletion(g),
  }));
}

export interface IncomeSourceView extends IncomeSource {
  totalLabel: string;
}

export function incomeView(
  sources: IncomeSource[],
  transactions: Transaction[],
): IncomeSourceView[] {
  return sources.map((src) => ({
    ...src,
    totalLabel: formatRupiah(
      transactions
        .filter((t) => t.source_id === src.id)
        .reduce((sum, t) => sum + t.amount, 0),
    ),
  }));
}

export interface AnalyticsSummary {
  totalIncomeLabel: string;
  totalExpenseLabel: string;
  netLabel: string;
}

export function analyticsSummary(transactions: Transaction[]): AnalyticsSummary {
  const totalIncome = transactions
    .filter((t) => t.type === "income")
    .reduce((sum, t) => sum + t.amount, 0);
  const totalExpense = transactions
    .filter((t) => t.type === "expense")
    .reduce((sum, t) => sum + t.amount, 0);
  return {
    totalIncomeLabel: formatRupiah(totalIncome),
    totalExpenseLabel: formatRupiah(totalExpense),
    netLabel: formatRupiah(totalIncome - totalExpense),
  };
}
